<?php

/**
 * FIDO2 Credential Entity
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Entity;

use DateTime;

class Fido2Credential
{
    /**
     * @var int|null
     */
    private $id;

    /**
     * @var int
     */
    private $customerId;

    /**
     * @var string Base64URL encoded credential ID
     */
    private $credentialId;

    /**
     * @var string JSON encoded public key
     */
    private $credentialPublicKey;

    /**
     * @var string
     */
    private $attestationType;

    /**
     * @var string|null
     */
    private $aaguid;

    /**
     * @var int
     */
    private $signCount;

    /**
     * @var array|null
     */
    private $transports;

    /**
     * @var string|null
     */
    private $deviceName;

    /**
     * @var string|null
     */
    private $userAgent;

    /**
     * @var DateTime
     */
    private $createdAt;

    /**
     * @var DateTime|null
     */
    private $lastUsedAt;

    /**
     * @var bool
     */
    private $isActive;

    public function __construct()
    {
        $this->signCount = 0;
        $this->isActive = true;
        $this->createdAt = new DateTime();
    }

    /**
     * Get credential ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set credential ID
     *
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    /**
     * Set customer ID
     *
     * @param int $customerId
     * @return self
     */
    public function setCustomerId(int $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * Get credential ID (base64url)
     *
     * @return string
     */
    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    /**
     * Set credential ID
     *
     * @param string $credentialId
     * @return self
     */
    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    /**
     * Get public key
     *
     * @return string
     */
    public function getCredentialPublicKey(): string
    {
        return $this->credentialPublicKey;
    }

    /**
     * Set public key
     *
     * @param string $credentialPublicKey
     * @return self
     */
    public function setCredentialPublicKey(string $credentialPublicKey): self
    {
        $this->credentialPublicKey = $credentialPublicKey;
        return $this;
    }

    /**
     * Get attestation type
     *
     * @return string
     */
    public function getAttestationType(): string
    {
        return $this->attestationType;
    }

    /**
     * Set attestation type
     *
     * @param string $attestationType
     * @return self
     */
    public function setAttestationType(string $attestationType): self
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    /**
     * Get AAGUID
     *
     * @return string|null
     */
    public function getAaguid(): ?string
    {
        return $this->aaguid;
    }

    /**
     * Set AAGUID
     *
     * @param string|null $aaguid
     * @return self
     */
    public function setAaguid(?string $aaguid): self
    {
        $this->aaguid = $aaguid;
        return $this;
    }

    /**
     * Get sign count
     *
     * @return int
     */
    public function getSignCount(): int
    {
        return $this->signCount;
    }

    /**
     * Set sign count
     *
     * @param int $signCount
     * @return self
     */
    public function setSignCount(int $signCount): self
    {
        $this->signCount = $signCount;
        return $this;
    }

    /**
     * Increment sign count
     *
     * @return self
     */
    public function incrementSignCount(): self
    {
        $this->signCount++;
        return $this;
    }

    /**
     * Get transports
     *
     * @return array|null
     */
    public function getTransports(): ?array
    {
        return $this->transports;
    }

    /**
     * Set transports
     *
     * @param array|null $transports
     * @return self
     */
    public function setTransports(?array $transports): self
    {
        $this->transports = $transports;
        return $this;
    }

    /**
     * Get device name
     *
     * @return string|null
     */
    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    /**
     * Set device name
     *
     * @param string|null $deviceName
     * @return self
     */
    public function setDeviceName(?string $deviceName): self
    {
        $this->deviceName = $deviceName;
        return $this;
    }

    /**
     * Get user agent
     *
     * @return string|null
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * Set user agent
     *
     * @param string|null $userAgent
     * @return self
     */
    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Get created at
     *
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set created at
     *
     * @param DateTime $createdAt
     * @return self
     */
    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get last used at
     *
     * @return DateTime|null
     */
    public function getLastUsedAt(): ?DateTime
    {
        return $this->lastUsedAt;
    }

    /**
     * Set last used at
     *
     * @param DateTime|null $lastUsedAt
     * @return self
     */
    public function setLastUsedAt(?DateTime $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    /**
     * Update last used timestamp
     *
     * @return self
     */
    public function updateLastUsed(): self
    {
        $this->lastUsedAt = new DateTime();
        return $this;
    }

    /**
     * Check if credential is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Set active status
     *
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customerId,
            'credential_id' => $this->credentialId,
            'attestation_type' => $this->attestationType,
            'device_name' => $this->deviceName,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'last_used_at' => $this->lastUsedAt ? $this->lastUsedAt->format('Y-m-d H:i:s') : null,
            'is_active' => $this->isActive,
        ];
    }
}
