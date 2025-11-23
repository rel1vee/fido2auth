<?php

/**
 * FIDO2 Challenge Entity
 * 
 * @author Muh. Zaki Erbai Syas
 * @copyright 2025
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Entity;

use DateTime;

class Fido2Challenge
{
    const TYPE_REGISTRATION = 'registration';
    const TYPE_AUTHENTICATION = 'authentication';

    private $id;
    private $challenge;
    private $userHandle;
    private $customerId;
    private $challengeType;
    private $createdAt;
    private $expiresAt;
    private $used;

    public function __construct()
    {
        $this->used = false;
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

    public function getChallenge(): string
    {
        return $this->challenge;
    }

    public function setChallenge(string $challenge): self
    {
        $this->challenge = $challenge;
        return $this;
    }

    public function getUserHandle(): ?string
    {
        return $this->userHandle;
    }

    public function setUserHandle(?string $userHandle): self
    {
        $this->userHandle = $userHandle;
        return $this;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function setCustomerId(?int $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function getChallengeType(): string
    {
        return $this->challengeType;
    }

    public function setChallengeType(string $challengeType): self
    {
        if (!in_array($challengeType, [self::TYPE_REGISTRATION, self::TYPE_AUTHENTICATION])) {
            throw new \InvalidArgumentException('Invalid challenge type');
        }

        $this->challengeType = $challengeType;
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

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): self
    {
        $this->used = $used;
        return $this;
    }

    public function isValid(): bool
    {
        if ($this->used) {
            return false;
        }

        $now = new DateTime();
        return $now <= $this->expiresAt;
    }

    public function isExpired(): bool
    {
        $now = new DateTime();
        return $now > $this->expiresAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'challenge' => $this->challenge,
            'user_handle' => $this->userHandle,
            'customer_id' => $this->customerId,
            'challenge_type' => $this->challengeType,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
            'used' => $this->used,
            'is_valid' => $this->isValid(),
        ];
    }
}
