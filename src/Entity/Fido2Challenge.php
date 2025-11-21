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
    /**
     * Challenge types
     */
    const TYPE_REGISTRATION = 'registration';
    const TYPE_AUTHENTICATION = 'authentication';

    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string Base64URL encoded challenge
     */
    private $challenge;

    /**
     * @var string|null Base64URL encoded user handle
     */
    private $userHandle;

    /**
     * @var int|null
     */
    private $customerId;

    /**
     * @var string
     */
    private $challengeType;

    /**
     * @var DateTime
     */
    private $createdAt;

    /**
     * @var DateTime
     */
    private $expiresAt;

    /**
     * @var bool
     */
    private $used;

    public function __construct()
    {
        $this->used = false;
        $this->createdAt = new DateTime();
    }

    /**
     * Get challenge ID
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set challenge ID
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
     * Get challenge string
     *
     * @return string
     */
    public function getChallenge(): string
    {
        return $this->challenge;
    }

    /**
     * Set challenge string
     *
     * @param string $challenge
     * @return self
     */
    public function setChallenge(string $challenge): self
    {
        $this->challenge = $challenge;
        return $this;
    }

    /**
     * Get user handle
     *
     * @return string|null
     */
    public function getUserHandle(): ?string
    {
        return $this->userHandle;
    }

    /**
     * Set user handle
     *
     * @param string|null $userHandle
     * @return self
     */
    public function setUserHandle(?string $userHandle): self
    {
        $this->userHandle = $userHandle;
        return $this;
    }

    /**
     * Get customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    /**
     * Set customer ID
     *
     * @param int|null $customerId
     * @return self
     */
    public function setCustomerId(?int $customerId): self
    {
        $this->customerId = $customerId;
        return $this;
    }

    /**
     * Get challenge type
     *
     * @return string
     */
    public function getChallengeType(): string
    {
        return $this->challengeType;
    }

    /**
     * Set challenge type
     *
     * @param string $challengeType
     * @return self
     */
    public function setChallengeType(string $challengeType): self
    {
        if (!in_array($challengeType, [self::TYPE_REGISTRATION, self::TYPE_AUTHENTICATION])) {
            throw new \InvalidArgumentException('Invalid challenge type');
        }

        $this->challengeType = $challengeType;
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
     * Get expires at
     *
     * @return DateTime
     */
    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    /**
     * Set expires at
     *
     * @param DateTime $expiresAt
     * @return self
     */
    public function setExpiresAt(DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Check if challenge is used
     *
     * @return bool
     */
    public function isUsed(): bool
    {
        return $this->used;
    }

    /**
     * Set used status
     *
     * @param bool $used
     * @return self
     */
    public function setUsed(bool $used): self
    {
        $this->used = $used;
        return $this;
    }

    /**
     * Check if challenge is valid (not used and not expired)
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->used) {
            return false;
        }

        $now = new DateTime();
        return $now <= $this->expiresAt;
    }

    /**
     * Check if challenge is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        $now = new DateTime();
        return $now > $this->expiresAt;
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
