<?php

/**
 * FIDO2 Assertion Verifier Service
 *
 * Handles WebAuthn authentication (assertion) flow:
 * - Creates PublicKeyCredentialRequestOptions for navigator.credentials.get()
 * - Validates the assertion response from the authenticator
 * - Implements PublicKeyCredentialSourceRepository to resolve stored credentials
 *   during the library's verification process
 *
 * @author  Muh. Zaki Erbai Syas
 * @license AFL-3.0
 * @package PrestaShop\Module\Fido2Auth\Service
 * @see     https://www.w3.org/TR/webauthn-2/#sctn-verifying-assertion
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Service;

use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Psr\Http\Message\ServerRequestInterface;
use Webauthn\TrustPath\EmptyTrustPath;
use Symfony\Component\Uid\Uuid;
use PrestaShop\Module\Fido2Auth\Repository\CredentialRepository;

class AssertionVerifier implements PublicKeyCredentialSourceRepository
{
    private $rpId;
    private $credentialRepository;
    private $publicKeyCredentialLoader;
    private $assertionValidator;

    public function __construct(string $rpId, CredentialRepository $credentialRepository)
    {
        $this->rpId = $rpId;
        $this->credentialRepository = $credentialRepository;

        $this->initializeValidators();
    }

    private function initializeValidators(): void
    {
        // Algorithm Manager
        $algorithmManager = Manager::create();
        $algorithmManager->add(ECDSA\ES256::create());
        $algorithmManager->add(ECDSA\ES384::create());
        $algorithmManager->add(ECDSA\ES512::create());
        $algorithmManager->add(RSA\RS256::create());
        $algorithmManager->add(RSA\RS384::create());
        $algorithmManager->add(RSA\RS512::create());
        $algorithmManager->add(EdDSA\Ed25519::create());

        // Attestation Statement Support Manager
        $attestationStatementSupportManager = AttestationStatementSupportManager::create();
        $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(FidoU2FAttestationStatementSupport::create());
        // AndroidSafetyNet Removed due to missing dependency
        $attestationStatementSupportManager->add(AndroidKeyAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(TPMAttestationStatementSupport::create());
        $attestationStatementSupportManager->add(PackedAttestationStatementSupport::create($algorithmManager));
        $attestationStatementSupportManager->add(AppleAttestationStatementSupport::create());

        // Extension Output Checker Handler
        $extensionOutputCheckerHandler = ExtensionOutputCheckerHandler::create();

        // Public Key Credential Loader
        $attestationObjectLoader = AttestationObjectLoader::create($attestationStatementSupportManager);
        $this->publicKeyCredentialLoader = PublicKeyCredentialLoader::create(
            $attestationObjectLoader
        );

        // Assertion Response Validator
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create(
            $this, // This class implements PublicKeyCredentialSourceRepository
            TokenBindingNotSupportedHandler::create(),
            $extensionOutputCheckerHandler,
            $algorithmManager
        );
    }

    /**
     * Create PublicKeyCredentialRequestOptions for authentication
     *
     * @param string $challenge Base64URL encoded challenge
     * @param array $allowCredentials Array of credential IDs
     * @param int $timeout Timeout in milliseconds
     * @return PublicKeyCredentialRequestOptions
     */
    public function createRequestOptions(
        string $challenge,
        array $allowCredentials = [],
        int $timeout = 60000
    ): PublicKeyCredentialRequestOptions {
        // Allow credentials
        $allowCreds = [];
        foreach ($allowCredentials as $credId) {
            $allowCreds[] = PublicKeyCredentialDescriptor::create(
                'public-key',
                $credId,
                ['usb', 'nfc', 'ble', 'internal']
            );
        }

        // Create request options
        $options = PublicKeyCredentialRequestOptions::create($challenge)
            ->setTimeout($timeout)
            ->setRpId($this->rpId)
            ->setUserVerification(PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED)
            ->setExtensions(AuthenticationExtensionsClientInputs::createFromArray([]));

        if (!empty($allowCreds)) {
            $options = $options->allowCredentials(...$allowCreds);
        }

        return $options;
    }

    /**
     * Validate assertion response
     *
     * @param array $response Client response data
     * @param PublicKeyCredentialRequestOptions $requestOptions
     * @param ServerRequestInterface $request PSR-7 request
     * @return array Validated assertion data
     * @throws \Throwable
     */
    public function validateAssertion(
        array $response,
        PublicKeyCredentialRequestOptions $requestOptions,
        ServerRequestInterface $request
    ): array {
        try {
            // Load the credential from response
            $publicKeyCredential = $this->publicKeyCredentialLoader->load(json_encode($response));

            // Get credential ID
            $credentialId = $this->base64UrlEncode($publicKeyCredential->getRawId());

            // Get the assertion response
            $authenticatorAssertionResponse = $publicKeyCredential->getResponse();

            if (!$authenticatorAssertionResponse instanceof AuthenticatorAssertionResponse) {
                throw new \RuntimeException('Invalid authenticator response type');
            }

            // Validate the assertion
            $publicKeyCredentialSource = $this->assertionValidator->check(
                $publicKeyCredential->getRawId(),
                $authenticatorAssertionResponse,
                $requestOptions,
                $request,
                null // userHandle - we'll get it from credential
            );

            // Get new sign count
            $newSignCount = $publicKeyCredentialSource->getCounter();

            return [
                'credential_id' => $credentialId,
                'sign_count' => $newSignCount,
                'user_handle' => $this->base64UrlEncode($publicKeyCredentialSource->getUserHandle()),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Assertion validation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find one credential by credential ID
     * Required by PublicKeyCredentialSourceRepository interface
     *
     * @param string $publicKeyCredentialId
     * @return PublicKeyCredentialSource|null
     */
    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credentialId = $this->base64UrlEncode($publicKeyCredentialId);
        $credential = $this->credentialRepository->findByCredentialId($credentialId);

        if (!$credential) {
            return null;
        }

        // Convert to PublicKeyCredentialSource
        return PublicKeyCredentialSource::create(
            $this->base64UrlDecode($credential->getCredentialId()),
            'public-key',
            [],
            $credential->getAttestationType(),
            EmptyTrustPath::create(), // Trust path
            Uuid::fromString($credential->getAaguid() ?: '00000000-0000-0000-0000-000000000000'),
            base64_decode($credential->getCredentialPublicKey()),
            $this->deriveUserHandle((string) $credential->getCustomerId()),
            $credential->getSignCount()
        );
    }

    /**
     * Find all credentials for a user handle
     * Required by PublicKeyCredentialSourceRepository interface
     *
     * @param PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity
     * @return array
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        // User handle in our case is the customer ID (as string)
        $userHandle = $publicKeyCredentialUserEntity->getId();
        $customerId = (int) $userHandle;
        $credentials = $this->credentialRepository->findByCustomerId($customerId);

        $sources = [];
        foreach ($credentials as $credential) {
            $sources[] = PublicKeyCredentialSource::create(
                $this->base64UrlDecode($credential->getCredentialId()),
                'public-key',
                [],
                $credential->getAttestationType(),
                EmptyTrustPath::create(),
                Uuid::fromString($credential->getAaguid() ?: '00000000-0000-0000-0000-000000000000'),
                base64_decode($credential->getCredentialPublicKey()),
                $this->deriveUserHandle((string) $credential->getCustomerId()),
                $credential->getSignCount()
            );
        }

        return $sources;
    }

    /**
     * Save credential source
     * Required by PublicKeyCredentialSourceRepository interface
     *
     * @param PublicKeyCredentialSource $publicKeyCredentialSource
     * @return void
     */
    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $credentialId = $this->base64UrlEncode($publicKeyCredentialSource->getPublicKeyCredentialId());
        $credential = $this->credentialRepository->findByCredentialId($credentialId);

        if ($credential) {
            // Update sign count
            $this->credentialRepository->updateSignCount(
                $credential->getId(),
                $publicKeyCredentialSource->getCounter()
            );
        }
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
     * Derive user handle from customer ID
     * Must match ChallengeManager::generateRegistrationChallenge
     *
     * @param string $customerId
     * @return string Raw binary user handle
     */
    private function deriveUserHandle(string $customerId): string
    {
        // Must return raw binary bytes, NOT base64url-encoded.
        // During registration, base64UrlEncode(sha256(id)) was sent as user.id string,
        // but the JS decoded it to raw bytes before passing to navigator.credentials.create().
        // The authenticator stores and returns these raw bytes.
        // The library also decodes the response userHandle back to raw bytes.
        // So this must match: raw SHA-256 hash bytes.
        return hash('sha256', $customerId, true);
    }
}
