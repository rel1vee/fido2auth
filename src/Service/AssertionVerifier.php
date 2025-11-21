<?php

/**
 * FIDO2 Assertion Verifier Service
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Service;

use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\UserVerificationRequirement;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Psr\Http\Message\ServerRequestInterface;
use PrestaShop\Module\Fido2Auth\Repository\CredentialRepository;

class AssertionVerifier implements PublicKeyCredentialSourceRepository
{
    /**
     * @var string
     */
    private $rpId;

    /**
     * @var CredentialRepository
     */
    private $credentialRepository;

    /**
     * @var PublicKeyCredentialLoader
     */
    private $publicKeyCredentialLoader;

    /**
     * @var AuthenticatorAssertionResponseValidator
     */
    private $assertionValidator;

    public function __construct(string $rpId, CredentialRepository $credentialRepository)
    {
        $this->rpId = $rpId;
        $this->credentialRepository = $credentialRepository;

        $this->initializeValidators();
    }

    /**
     * Initialize WebAuthn validators
     */
    private function initializeValidators(): void
    {
        // Algorithm Manager
        $algorithmManager = Manager::create()
            ->add(ECDSA\ES256::create())
            ->add(ECDSA\ES384::create())
            ->add(ECDSA\ES512::create())
            ->add(RSA\RS256::create())
            ->add(RSA\RS384::create())
            ->add(RSA\RS512::create())
            ->add(EdDSA\Ed25519::create());

        // Attestation Statement Support Manager (minimal for assertion)
        $attestationStatementSupportManager = AttestationStatementSupportManager::create()
            ->add(NoneAttestationStatementSupport::create());

        // Extension Output Checker Handler
        $extensionOutputCheckerHandler = ExtensionOutputCheckerHandler::create();

        // Public Key Credential Loader
        $this->publicKeyCredentialLoader = PublicKeyCredentialLoader::create(
            $attestationStatementSupportManager
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
            ->setUserVerification(UserVerificationRequirement::PREFERRED)
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
            null, // Trust path
            \Ramsey\Uuid\Uuid::fromString($credential->getAaguid() ?: '00000000-0000-0000-0000-000000000000'),
            base64_decode($credential->getCredentialPublicKey()),
            (string) $credential->getCustomerId(),
            $credential->getSignCount()
        );
    }

    /**
     * Find all credentials for a user handle
     * Required by PublicKeyCredentialSourceRepository interface
     *
     * @param string $userHandle
     * @return array
     */
    public function findAllForUserEntity(string $userHandle): array
    {
        // User handle in our case is the customer ID (as string)
        $customerId = (int) $userHandle;
        $credentials = $this->credentialRepository->findByCustomerId($customerId);

        $sources = [];
        foreach ($credentials as $credential) {
            $sources[] = PublicKeyCredentialSource::create(
                $this->base64UrlDecode($credential->getCredentialId()),
                'public-key',
                [],
                $credential->getAttestationType(),
                null,
                \Ramsey\Uuid\Uuid::fromString($credential->getAaguid() ?: '00000000-0000-0000-0000-000000000000'),
                base64_decode($credential->getCredentialPublicKey()),
                (string) $credential->getCustomerId(),
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
}
