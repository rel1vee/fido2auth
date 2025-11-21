<?php

/**
 * FIDO2 Attestation Validator Service
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Service;

use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\AuthenticatorAttachment;
use Webauthn\UserVerificationRequirement;
use Webauthn\AttestationConveyancePreference;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AndroidSafetyNetAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Psr\Http\Message\ServerRequestInterface;

class AttestationValidator
{
    /**
     * @var string
     */
    private $rpId;

    /**
     * @var string
     */
    private $rpName;

    /**
     * @var PublicKeyCredentialLoader
     */
    private $publicKeyCredentialLoader;

    /**
     * @var AuthenticatorAttestationResponseValidator
     */
    private $attestationValidator;

    public function __construct(string $rpId, string $rpName)
    {
        $this->rpId = $rpId;
        $this->rpName = $rpName;

        $this->initializeValidators();
    }

    /**
     * Initialize WebAuthn validators
     */
    private function initializeValidators(): void
    {
        // Algorithm Manager with support for common algorithms
        $algorithmManager = Manager::create()
            ->add(ECDSA\ES256::create())
            ->add(ECDSA\ES384::create())
            ->add(ECDSA\ES512::create())
            ->add(RSA\RS256::create())
            ->add(RSA\RS384::create())
            ->add(RSA\RS512::create())
            ->add(EdDSA\Ed25519::create());

        // Attestation Statement Support Manager
        $attestationStatementSupportManager = AttestationStatementSupportManager::create()
            ->add(NoneAttestationStatementSupport::create())
            ->add(FidoU2FAttestationStatementSupport::create())
            ->add(AndroidSafetyNetAttestationStatementSupport::create())
            ->add(AndroidKeyAttestationStatementSupport::create())
            ->add(TPMAttestationStatementSupport::create())
            ->add(PackedAttestationStatementSupport::create($algorithmManager))
            ->add(AppleAttestationStatementSupport::create());

        // Extension Output Checker Handler
        $extensionOutputCheckerHandler = ExtensionOutputCheckerHandler::create();

        // Public Key Credential Loader
        $this->publicKeyCredentialLoader = PublicKeyCredentialLoader::create(
            $attestationStatementSupportManager
        );

        // Attestation Response Validator
        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create(
            $attestationStatementSupportManager,
            null, // No repository needed for registration
            TokenBindingNotSupportedHandler::create(),
            $extensionOutputCheckerHandler
        );
    }

    /**
     * Create PublicKeyCredentialCreationOptions for registration
     *
     * @param string $challenge Base64URL encoded challenge
     * @param string $userHandle Base64URL encoded user handle
     * @param string $username User email/username
     * @param string $displayName User display name
     * @param array $excludeCredentials Array of credential IDs to exclude
     * @param int $timeout Timeout in milliseconds
     * @return PublicKeyCredentialCreationOptions
     */
    public function createCreationOptions(
        string $challenge,
        string $userHandle,
        string $username,
        string $displayName,
        array $excludeCredentials = [],
        int $timeout = 60000
    ): PublicKeyCredentialCreationOptions {
        // Relying Party Entity
        $rpEntity = PublicKeyCredentialRpEntity::create(
            $this->rpName,
            $this->rpId,
            null // icon (deprecated in Level 2)
        );

        // User Entity
        $userEntity = PublicKeyCredentialUserEntity::create(
            $username,
            $userHandle,
            $displayName,
            null // icon (deprecated in Level 2)
        );

        // Supported Public Key Credential Parameters (algorithms)
        $pubKeyCredParams = [
            PublicKeyCredentialParameters::create('public-key', -7),  // ES256
            PublicKeyCredentialParameters::create('public-key', -257), // RS256
            PublicKeyCredentialParameters::create('public-key', -8),  // EdDSA
        ];

        // Exclude credentials (prevent re-registration)
        $excludeCreds = [];
        foreach ($excludeCredentials as $credId) {
            $excludeCreds[] = PublicKeyCredentialDescriptor::create(
                'public-key',
                $credId
            );
        }

        // Authenticator Selection Criteria
        $authenticatorSelection = AuthenticatorSelectionCriteria::create()
            ->setAuthenticatorAttachment(AuthenticatorAttachment::CROSS_PLATFORM) // Allow both platform and cross-platform
            ->setUserVerification(UserVerificationRequirement::PREFERRED)
            ->setResidentKey('preferred');

        // Create options
        return PublicKeyCredentialCreationOptions::create(
            $rpEntity,
            $userEntity,
            $challenge,
            $pubKeyCredParams
        )
            ->setTimeout($timeout)
            ->excludeCredentials(...$excludeCreds)
            ->setAuthenticatorSelection($authenticatorSelection)
            ->setAttestation(AttestationConveyancePreference::NONE)
            ->setExtensions(AuthenticationExtensionsClientInputs::createFromArray([]));
    }

    /**
     * Validate attestation response
     *
     * @param array $response Client response data
     * @param PublicKeyCredentialCreationOptions $creationOptions
     * @param ServerRequestInterface $request PSR-7 request
     * @return array Validated credential data
     * @throws \Throwable
     */
    public function validateAttestation(
        array $response,
        PublicKeyCredentialCreationOptions $creationOptions,
        ServerRequestInterface $request
    ): array {
        try {
            // Load the credential from response
            $publicKeyCredential = $this->publicKeyCredentialLoader->load(json_encode($response));

            // Get the attestation response
            $authenticatorAttestationResponse = $publicKeyCredential->getResponse();

            if (!$authenticatorAttestationResponse instanceof AuthenticatorAttestationResponse) {
                throw new \RuntimeException('Invalid authenticator response type');
            }

            // Validate the attestation
            $publicKeyCredentialSource = $this->attestationValidator->check(
                $authenticatorAttestationResponse,
                $creationOptions,
                $request
            );

            // Extract credential data
            $credentialId = $this->base64UrlEncode($publicKeyCredentialSource->getPublicKeyCredentialId());
            $publicKeyPem = $this->convertPublicKeyToPem($publicKeyCredentialSource->getCredentialPublicKey());

            // Get attestation type
            $attestationObject = $authenticatorAttestationResponse->getAttestationObject();
            $attestationType = $attestationObject->getAttStmt()->getType();

            // Get AAGUID
            $aaguid = $attestationObject->getAuthData()->getAaguid()->toString();

            // Get transports
            $transports = $publicKeyCredential->getTransports();

            return [
                'credential_id' => $credentialId,
                'public_key_pem' => $publicKeyPem,
                'attestation_type' => $attestationType,
                'aaguid' => $aaguid,
                'transports' => $transports,
                'sign_count' => $publicKeyCredentialSource->getCounter(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Attestation validation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert COSE public key to PEM format
     *
     * @param string $credentialPublicKey
     * @return string
     */
    private function convertPublicKeyToPem(string $credentialPublicKey): string
    {
        // Store as base64 encoded for now
        // In production, you might want to convert to actual PEM format
        return base64_encode($credentialPublicKey);
    }

    /**
     * Base64URL encode
     *
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode
     *
     * @param string $data
     * @return string
     */
    public function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get RP ID
     *
     * @return string
     */
    public function getRpId(): string
    {
        return $this->rpId;
    }

    /**
     * Get RP Name
     *
     * @return string
     */
    public function getRpName(): string
    {
        return $this->rpName;
    }
}
