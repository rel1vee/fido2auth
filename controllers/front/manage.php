<?php

/**
 * FIDO2 Credential Management Controller
 *
 * Provides a customer-facing page to manage registered WebAuthn credentials.
 * Requires the customer to be logged in.
 *
 * AJAX Endpoints:
 *  - action=list:        Returns all active credentials for the logged-in customer
 *  - action=delete:      Soft-deletes a credential (marks as inactive)
 *  - action=update_name: Updates the user-friendly device name of a credential
 */

declare(strict_types=1);

class Fido2AuthManageModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    /** @var Fido2Auth */
    public $module;

    public function __construct()
    {
        parent::__construct();

        // Require customer to be logged in
        $this->auth = true;
        $this->authRedirection = 'my-account';
    }

    public function init()
    {
        parent::init();

        // Check if FIDO2 is enabled
        if (!Configuration::get('FIDO2AUTH_ENABLED')) {
            Tools::redirect('index.php?controller=my-account');
        }
    }

    public function postProcess()
    {
        if (!$this->ajax)
            return;
        if (ob_get_length())
            ob_clean();
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $postData = json_decode($rawInput, true);

        if (!isset($postData['token']) || $postData['token'] !== Tools::getToken(false)) {
            die(json_encode(['success' => false, 'message' => 'Invalid security token']));
        }

        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'list':
                    $this->listCredentials();
                    break;
                case 'delete':
                    $this->deleteCredential($postData);
                    break;
                case 'update_name':
                    $this->updateCredentialName($postData);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    private function listCredentials()
    {
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($customer)) {
            die(json_encode([
                'success' => false,
                'message' => 'Customer not authenticated',
            ]));
        }

        try {
            $credentialManager = $this->module->getCredentialManager();
            $credentials = $credentialManager->getCustomerCredentials((int) $customer->id);

            $credentialList = array_map(function ($cred) {
                return $cred->toArray();
            }, $credentials);

            die(json_encode([
                'success' => true,
                'credentials' => $credentialList,
                'count' => count($credentialList),
            ]));
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => 'Failed to list credentials: ' . $e->getMessage(),
            ]));
        }
    }

    private function deleteCredential(array $data)
    {
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($customer)) {
            die(json_encode([
                'success' => false,
                'message' => 'Customer not authenticated',
            ]));
        }

        try {
            if (!isset($data['credential_id'])) {
                throw new Exception('Credential ID is required');
            }

            $credentialId = (int) $data['credential_id'];
            $credentialManager = $this->module->getCredentialManager();

            // Check if this is the last credential
            $count = $credentialManager->countCredentials((int) $customer->id);

            if ($count <= 1 && Configuration::get('FIDO2AUTH_REQUIRE_MFA')) {
                throw new Exception('Cannot delete the last security key when MFA is required');
            }

            // Delete credential
            $result = $credentialManager->deleteCredential($credentialId, (int) $customer->id);

            if ($result) {
                die(json_encode([
                    'success' => true,
                    'message' => 'Security key deleted successfully',
                ]));
            } else {
                throw new Exception('Failed to delete security key');
            }
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    private function updateCredentialName(array $data)
    {
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($customer)) {
            die(json_encode([
                'success' => false,
                'message' => 'Customer not authenticated',
            ]));
        }

        try {
            if (!isset($data['credential_id']) || !isset($data['device_name'])) {
                throw new Exception('Credential ID and device name are required');
            }

            $credentialId = (int) $data['credential_id'];
            $deviceName = pSQL($data['device_name']);

            if (empty($deviceName) || strlen($deviceName) > 255) {
                throw new Exception('Invalid device name');
            }

            $credentialManager = $this->module->getCredentialManager();
            $result = $credentialManager->updateDeviceName($credentialId, (int) $customer->id, $deviceName);

            if ($result) {
                die(json_encode([
                    'success' => true,
                    'message' => 'Device name updated successfully',
                ]));
            } else {
                throw new Exception('Failed to update device name');
            }
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    public function initContent()
    {
        parent::initContent();

        $customer = $this->context->customer;

        // Get existing credentials
        $credentialManager = $this->module->getCredentialManager();
        $credentials = $credentialManager->getCustomerCredentials((int) $customer->id);

        $this->context->smarty->assign([
            'credentials' => array_map(function ($cred) {
                return $cred->toArray();
            }, $credentials),
            'credential_count' => count($credentials),
            'require_mfa' => Configuration::get('FIDO2AUTH_REQUIRE_MFA'),
            'ajax_url' => $this->context->link->getModuleLink(
                'fido2auth',
                'manage',
                [],
                true
            ),
            'registration_ajax_url' => $this->context->link->getModuleLink(
                'fido2auth',
                'registration',
                [],
                true
            ),
        ]);

        $this->setTemplate('module:fido2auth/views/templates/front/manage.tpl');
    }
}
