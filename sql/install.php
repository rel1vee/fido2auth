<?php

/**
 * Database Installation Script
 */

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fido2_credentials` (
    `id_fido2_credential` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_customer` INT(11) UNSIGNED NOT NULL,
    `credential_id` VARCHAR(255) NOT NULL,
    `credential_public_key` TEXT NOT NULL,
    `attestation_type` VARCHAR(50) NOT NULL,
    `aaguid` VARCHAR(36) NULL,
    `sign_count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `transports` VARCHAR(255) NULL,
    `device_name` VARCHAR(255) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `last_used_at` DATETIME NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_fido2_credential`),
    UNIQUE KEY `credential_id` (`credential_id`),
    KEY `idx_customer` (`id_customer`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'fido2_challenges` (
    `id_fido2_challenge` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `challenge` VARCHAR(255) NOT NULL,
    `user_handle` VARCHAR(255) NULL,
    `id_customer` INT(11) UNSIGNED NULL,
    `challenge_type` ENUM("registration", "authentication") NOT NULL,
    `created_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_fido2_challenge`),
    UNIQUE KEY `challenge` (`challenge`),
    KEY `idx_expires` (`expires_at`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
