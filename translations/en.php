<?php

global $_MODULE;
$_MODULE = [];

// Module information
$_MODULE['<{fido2auth}prestashop>fido2auth'] = 'MFA - FIDO2/WebAuthn';
$_MODULE['<{fido2auth}prestashop>fido2auth_desc'] = 'Advanced security with FIDO2-based multi-factor authentication.';

// Configuration
$_MODULE['<{fido2auth}prestashop>config_title'] = 'FIDO2/WebAuthn Settings';
$_MODULE['<{fido2auth}prestashop>config_enable'] = 'Enable FIDO2 Authentication';
$_MODULE['<{fido2auth}prestashop>config_require_mfa'] = 'Require MFA for all customers';
$_MODULE['<{fido2auth}prestashop>config_require_mfa_desc'] = 'Force all customers to set up FIDO2 authentication';
$_MODULE['<{fido2auth}prestashop>config_rp_name'] = 'Relying Party Name';
$_MODULE['<{fido2auth}prestashop>config_rp_name_desc'] = 'The name displayed to users during authentication';
$_MODULE['<{fido2auth}prestashop>config_timeout'] = 'Timeout (milliseconds)';
$_MODULE['<{fido2auth}prestashop>config_timeout_desc'] = 'Time limit for authentication operations (default: 60000ms)';
$_MODULE['<{fido2auth}prestashop>config_save'] = 'Save';
$_MODULE['<{fido2auth}prestashop>config_saved'] = 'Settings updated successfully.';

// Registration
$_MODULE['<{fido2auth}prestashop>register_title'] = 'Register Security Key';
$_MODULE['<{fido2auth}prestashop>register_add_new'] = 'Add New Security Key';
$_MODULE['<{fido2auth}prestashop>register_description'] = 'Register a new security key (like YubiKey) or use your device\'s biometric authentication (Windows Hello, Touch ID, Face ID).';
$_MODULE['<{fido2auth}prestashop>register_device_name'] = 'Device Name';
$_MODULE['<{fido2auth}prestashop>register_device_name_placeholder'] = 'e.g., My YubiKey, MacBook Touch ID';
$_MODULE['<{fido2auth}prestashop>register_device_name_help'] = 'Give your security key a name to identify it later.';
$_MODULE['<{fido2auth}prestashop>register_button'] = 'Register Security Key';
$_MODULE['<{fido2auth}prestashop>register_success'] = 'Security key registered successfully';
$_MODULE['<{fido2auth}prestashop>register_failed'] = 'Registration failed';

// Authentication
$_MODULE['<{fido2auth}prestashop>auth_title'] = 'Sign in with Security Key';
$_MODULE['<{fido2auth}prestashop>auth_description'] = 'Use your security key or device biometrics to sign in securely without a password.';
$_MODULE['<{fido2auth}prestashop>auth_email'] = 'Email (Optional)';
$_MODULE['<{fido2auth}prestashop>auth_email_help'] = 'Leave empty for usernameless authentication if your security key supports it.';
$_MODULE['<{fido2auth}prestashop>auth_button'] = 'Sign in with Security Key';
$_MODULE['<{fido2auth}prestashop>auth_success'] = 'Authentication successful';
$_MODULE['<{fido2auth}prestashop>auth_failed'] = 'Authentication failed';

// Management
$_MODULE['<{fido2auth}prestashop>manage_title'] = 'Manage Security Keys';
$_MODULE['<{fido2auth}prestashop>manage_your_keys'] = 'Your Security Keys';
$_MODULE['<{fido2auth}prestashop>manage_count'] = 'You have %d security key(s) registered.';
$_MODULE['<{fido2auth}prestashop>manage_add_new'] = 'Add New Key';
$_MODULE['<{fido2auth}prestashop>manage_refresh'] = 'Refresh';
$_MODULE['<{fido2auth}prestashop>manage_device_name'] = 'Device Name';
$_MODULE['<{fido2auth}prestashop>manage_registered'] = 'Registered';
$_MODULE['<{fido2auth}prestashop>manage_last_used'] = 'Last Used';
$_MODULE['<{fido2auth}prestashop>manage_actions'] = 'Actions';
$_MODULE['<{fido2auth}prestashop>manage_rename'] = 'Rename';
$_MODULE['<{fido2auth}prestashop>manage_delete'] = 'Delete';
$_MODULE['<{fido2auth}prestashop>manage_delete_confirm'] = 'Are you sure you want to delete this security key?';
$_MODULE['<{fido2auth}prestashop>manage_delete_success'] = 'Security key deleted successfully';
$_MODULE['<{fido2auth}prestashop>manage_delete_last_error'] = 'Cannot delete the last security key when MFA is required';

// Errors
$_MODULE['<{fido2auth}prestashop>error_not_supported'] = 'WebAuthn is not supported in this browser';
$_MODULE['<{fido2auth}prestashop>error_device_name_required'] = 'Device name is required';
$_MODULE['<{fido2auth}prestashop>error_challenge_expired'] = 'Challenge not found or expired';
$_MODULE['<{fido2auth}prestashop>error_credential_not_found'] = 'Credential not found';
$_MODULE['<{fido2auth}prestashop>error_authentication_failed'] = 'Authentication failed';

// Customer account
$_MODULE['<{fido2auth}prestashop>customer_account_link'] = 'Manage Security Keys';