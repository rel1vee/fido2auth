<?php

/**
 * FIDO2 Credential Manager Service
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Service;

use DateTime;
use PrestaShop\Module\Fido2Auth\Entity\Fido2Credential;
use PrestaShop\Module\Fido2Auth\Repository\CredentialRepository;

class CredentialManager
{
    private $credentialRepository;

    public function __construct(CredentialRepository $credentialRepository)
    {
        $this->credentialRepository = $credentialRepository;
    }

    /**
     * Register a new credential
     *
     * @param int $customerId
     * @param string $credentialId Base64URL encoded
     * @param string $publicKeyPem PEM format public key
     * @param string $attestationType
     * @param array $options Additional options
     * @return Fido2Credential
     * @throws \RuntimeException
     */
    public function registerCredential(
        int $customerId,
        string $credentialId,
        string $publicKeyPem,
        string $attestationType,
        array $options = []
    ): Fido2Credential {
        // Check if credential already exists
        if ($this->credentialRepository->findByCredentialId($credentialId)) {
            throw new \RuntimeException('Credential already registered');
        }

        // Create new credential entity
        $credential = new Fido2Credential();
        $credential->setCustomerId($customerId)
            ->setCredentialId($credentialId)
            ->setCredentialPublicKey($publicKeyPem)
            ->setAttestationType($attestationType)
            ->setSignCount(0)
            ->setCreatedAt(new DateTime())
            ->setIsActive(true);

        // Set optional fields
        if (isset($options['aaguid'])) {
            $credential->setAaguid($options['aaguid']);
        }

        if (isset($options['transports'])) {
            $credential->setTransports($options['transports']);
        }

        if (isset($options['device_name'])) {
            $credential->setDeviceName($options['device_name']);
        }

        if (isset($options['user_agent'])) {
            $credential->setUserAgent($options['user_agent']);
        }

        // Save credential
        if (!$this->credentialRepository->save($credential)) {
            throw new \RuntimeException('Failed to save credential');
        }

        return $credential;
    }

    /**
     * Get credential by credential ID
     *
     * @param string $credentialId
     * @return Fido2Credential|null
     */
    public function getCredential(string $credentialId): ?Fido2Credential
    {
        return $this->credentialRepository->findByCredentialId($credentialId);
    }

    /**
     * Get all credentials for a customer
     *
     * @param int $customerId
     * @return array
     */
    public function getCustomerCredentials(int $customerId): array
    {
        return $this->credentialRepository->findByCustomerId($customerId);
    }

    /**
     * Get credential IDs for a customer (for allowCredentials)
     *
     * @param int $customerId
     * @return array
     */
    public function getCustomerCredentialIds(int $customerId): array
    {
        return $this->credentialRepository->getCredentialIdsByCustomerId($customerId);
    }

    /**
     * Update credential sign count and last used timestamp
     *
     * @param Fido2Credential $credential
     * @param int $newSignCount
     * @return bool
     * @throws \RuntimeException
     */
    public function updateCredentialUsage(Fido2Credential $credential, int $newSignCount): bool
    {
        // Validate sign count (detect cloning)
        $currentSignCount = $credential->getSignCount();

        if ($newSignCount !== 0 && $newSignCount <= $currentSignCount) {
            throw new \RuntimeException('Sign count anomaly detected - possible credential cloning');
        }

        // Update credential
        $credential->setSignCount($newSignCount)
            ->updateLastUsed();

        return $this->credentialRepository->save($credential);
    }

    /**
     * Delete credential
     *
     * @param int $credentialId
     * @param int $customerId For security verification
     * @return bool
     * @throws \RuntimeException
     */
    public function deleteCredential(int $credentialId, int $customerId): bool
    {
        $credential = $this->credentialRepository->findById($credentialId);

        if (!$credential) {
            throw new \RuntimeException('Credential not found');
        }

        // Verify ownership
        if ($credential->getCustomerId() !== $customerId) {
            throw new \RuntimeException('Unauthorized to delete this credential');
        }

        // Soft delete (mark as inactive)
        return $this->credentialRepository->softDelete($credentialId);
    }

    /**
     * Check if customer has any registered credentials
     *
     * @param int $customerId
     * @return bool
     */
    public function hasCredentials(int $customerId): bool
    {
        return $this->credentialRepository->countByCustomerId($customerId) > 0;
    }

    /**
     * Count customer's active credentials
     *
     * @param int $customerId
     * @return int
     */
    public function countCredentials(int $customerId): int
    {
        return $this->credentialRepository->countByCustomerId($customerId);
    }

    /**
     * Update credential device name
     *
     * @param int $credentialId
     * @param int $customerId
     * @param string $deviceName
     * @return bool
     * @throws \RuntimeException
     */
    public function updateDeviceName(int $credentialId, int $customerId, string $deviceName): bool
    {
        $credential = $this->credentialRepository->findById($credentialId);

        if (!$credential) {
            throw new \RuntimeException('Credential not found');
        }

        // Verify ownership
        if ($credential->getCustomerId() !== $customerId) {
            throw new \RuntimeException('Unauthorized to update this credential');
        }

        $credential->setDeviceName($deviceName);

        return $this->credentialRepository->save($credential);
    }

    /**
     * Base64URL encode
     *
     * @param string $data
     * @return string
     */
    public function base64UrlEncode(string $data): string
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
