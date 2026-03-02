<?php

/**
 * FIDO2 Module - Database Uninstallation
 *
 * Drops the `fido2_credentials` and `fido2_challenges` tables.
 * Called automatically during module uninstallation.
 */

$sql = [
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'fido2_credentials`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'fido2_challenges`',
];

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
