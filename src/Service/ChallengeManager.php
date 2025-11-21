<?php

/**
 * FIDO2 Challenge Manager Service
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Service;

use DateTime;
use DateInterval;
use PrestaShop\Module\Fido2Auth\Entity\Fido2Challenge;
use PrestaShop\Module\Fido2Auth\Repository\ChallengeRepository;

class ChallengeManager
{
    /**
     * @var ChallengeRepository
     */
    private $challengeRepository;

    /**
     * @var int Challenge timeout in seconds
     */
    private $challengeTimeout;

    public function __construct(ChallengeRepository $challengeRepository, int $challengeTimeout = 300)
    {
        $this->challengeRepository = $challengeRepository;
        $this->challengeTimeout = $challengeTimeout;
    }

    /**
     * Generate a new registration challenge
     *
     * @param int $customerId
     * @param string|null $userHandle
     * @return array Challenge data for client
     * @throws \Exception
     */
    public function generateRegistrationChallenge(int $customerId, ?string $userHandle = null): array
    {
        // Generate cryptographically secure random challenge
        $challengeBytes = random_bytes(32);
        $challenge = $this->base64UrlEncode($challengeBytes);

        // Generate user handle if not provided
        if ($userHandle === null) {
            $userHandleBytes = random_bytes(16);
            $userHandle = $this->base64UrlEncode($userHandleBytes);
        }

        // Create challenge entity
        $challengeEntity = new Fido2Challenge();
        $challengeEntity->setChallenge($challenge)
            ->setUserHandle($userHandle)
            ->setCustomerId($customerId)
            ->setChallengeType(Fido2Challenge::TYPE_REGISTRATION)
            ->setCreatedAt(new DateTime())
            ->setExpiresAt($this->getExpirationTime());

        // Save to database
        if (!$this->challengeRepository->save($challengeEntity)) {
            throw new \RuntimeException('Failed to save challenge');
        }

        return [
            'challenge' => $challenge,
            'user_handle' => $userHandle,
            'timeout' => $this->challengeTimeout * 1000, // Convert to milliseconds
        ];
    }

    /**
     * Generate a new authentication challenge
     *
     * @param int|null $customerId Optional customer ID for additional validation
     * @return array Challenge data for client
     * @throws \Exception
     */
    public function generateAuthenticationChallenge(?int $customerId = null): array
    {
        // Generate cryptographically secure random challenge
        $challengeBytes = random_bytes(32);
        $challenge = $this->base64UrlEncode($challengeBytes);

        // Create challenge entity
        $challengeEntity = new Fido2Challenge();
        $challengeEntity->setChallenge($challenge)
            ->setCustomerId($customerId)
            ->setChallengeType(Fido2Challenge::TYPE_AUTHENTICATION)
            ->setCreatedAt(new DateTime())
            ->setExpiresAt($this->getExpirationTime());

        // Save to database
        if (!$this->challengeRepository->save($challengeEntity)) {
            throw new \RuntimeException('Failed to save challenge');
        }

        return [
            'challenge' => $challenge,
            'timeout' => $this->challengeTimeout * 1000, // Convert to milliseconds
        ];
    }

    /**
     * Validate and retrieve challenge
     *
     * @param string $challenge
     * @param string $expectedType
     * @return Fido2Challenge
     * @throws \RuntimeException
     */
    public function validateChallenge(string $challenge, string $expectedType): Fido2Challenge
    {
        // Find valid challenge
        $challengeEntity = $this->challengeRepository->findValidChallenge($challenge);

        if ($challengeEntity === null) {
            throw new \RuntimeException('Challenge not found or expired');
        }

        // Validate challenge type
        if ($challengeEntity->getChallengeType() !== $expectedType) {
            throw new \RuntimeException('Invalid challenge type');
        }

        // Double-check validity
        if (!$challengeEntity->isValid()) {
            throw new \RuntimeException('Challenge is not valid');
        }

        return $challengeEntity;
    }

    /**
     * Consume challenge (mark as used)
     *
     * @param string $challenge
     * @return bool
     */
    public function consumeChallenge(string $challenge): bool
    {
        return $this->challengeRepository->markAsUsed($challenge);
    }

    /**
     * Clean up expired challenges
     *
     * @return bool
     */
    public function cleanupExpiredChallenges(): bool
    {
        return $this->challengeRepository->deleteExpired();
    }

    /**
     * Clean up used challenges older than specified hours
     *
     * @param int $hours
     * @return bool
     */
    public function cleanupUsedChallenges(int $hours = 24): bool
    {
        return $this->challengeRepository->deleteUsedOlderThan($hours);
    }

    /**
     * Get expiration time for challenge
     *
     * @return DateTime
     */
    private function getExpirationTime(): DateTime
    {
        $expiresAt = new DateTime();
        $expiresAt->add(new DateInterval('PT' . $this->challengeTimeout . 'S'));

        return $expiresAt;
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
