<?php

/**
 * FIDO2 Credential Entity
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Entity;

use DateTime;

class Fido2Credential
{
    private $id;
    private $customerId;
    private $credentialId;
    private $credentialPublicKey;
    private $attestationType;
    private $aaguid;
    private $signCount;
    private $transports;
    private $deviceName;
    private $userAgent;
    private $createdAt;
    private $lastUsedAt;
    private $isActive;

    public function __construct()
    {
        $this->signCount = 0;
        $this->isActive = true;
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function setCustomerId(int $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): self
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    public function getCredentialPublicKey(): string
    {
        return $this->credentialPublicKey;
    }

    public function setCredentialPublicKey(string $credentialPublicKey): self
    {
        $this->credentialPublicKey = $credentialPublicKey;
        return $this;
    }

    public function getAttestationType(): string
    {
        return $this->attestationType;
    }

    public function setAttestationType(string $attestationType): self
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    public function getAaguid(): ?string
    {
        return $this->aaguid;
    }

    public function setAaguid(?string $aaguid): self
    {
        $this->aaguid = $aaguid;
        return $this;
    }

    public function getSignCount(): int
    {
        return $this->signCount;
    }

    public function setSignCount(int $signCount): self
    {
        $this->signCount = $signCount;
        return $this;
    }

    public function incrementSignCount(): self
    {
        $this->signCount++;
        return $this;
    }

    public function getTransports(): ?array
    {
        return $this->transports;
    }

    public function setTransports(?array $transports): self
    {
        $this->transports = $transports;
        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): self
    {
        $this->deviceName = $deviceName;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getLastUsedAt(): ?DateTime
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?DateTime $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function updateLastUsed(): self
    {
        $this->lastUsedAt = new DateTime();
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

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
