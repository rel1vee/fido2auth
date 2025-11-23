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
    private $challengeRepository;
    private $challengeTimeout;

    public function __construct(ChallengeRepository $challengeRepository, int $challengeTimeout = 300)
    {
        $this->challengeRepository = $challengeRepository;
        $this->challengeTimeout = $challengeTimeout;
    }

    public function generateRegistrationChallenge(int $customerId): array
    {
        // Generate cryptographically secure random challenge
        $challengeBytes = random_bytes(32);
        $challenge = $this->base64UrlEncode($challengeBytes);

        // PERBAIKAN: User Handle harus konsisten untuk Customer ID yang sama
        // Kita gunakan SHA-256 dari ID Customer agar panjangnya pas dan tidak menebak ID asli secara langsung
        $userHandle = $this->base64UrlEncode(hash('sha256', (string)$customerId, true));

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
            'timeout' => $this->challengeTimeout * 1000,
        ];
    }

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
            'timeout' => $this->challengeTimeout * 1000,
        ];
    }

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

    public function consumeChallenge(string $challenge): bool
    {
        return $this->challengeRepository->markAsUsed($challenge);
    }

    public function cleanupExpiredChallenges(): bool
    {
        return $this->challengeRepository->deleteExpired();
    }

    public function cleanupUsedChallenges(int $hours = 24): bool
    {
        return $this->challengeRepository->deleteUsedOlderThan($hours);
    }

    private function getExpirationTime(): DateTime
    {
        $expiresAt = new DateTime();
        $expiresAt->add(new DateInterval('PT' . $this->challengeTimeout . 'S'));

        return $expiresAt;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

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
