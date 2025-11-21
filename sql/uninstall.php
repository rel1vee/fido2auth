<?php

/**
 * Database Uninstallation Script
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
