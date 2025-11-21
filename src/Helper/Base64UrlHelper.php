<?php

/**
 * Base64URL Helper Utility
 */

declare(strict_types=1);

namespace PrestaShop\Module\Fido2Auth\Helper;

class Base64UrlHelper
{
    /**
     * Base64URL encode
     *
     * @param string $data
     * @return string
     */
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode
     *
     * @param string $data
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function decode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            if ($remainder === 1) {
                throw new \InvalidArgumentException('Invalid base64url string');
            }
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'));

        if ($decoded === false) {
            throw new \InvalidArgumentException('Failed to decode base64url string');
        }

        return $decoded;
    }

    /**
     * Check if string is valid base64url
     *
     * @param string $data
     * @return bool
     */
    public static function isValid(string $data): bool
    {
        try {
            self::decode($data);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
