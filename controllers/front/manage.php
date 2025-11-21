<?php

/**
 * FIDO2 Credential Management Controller
 */

declare(strict_types=1);

class Fido2AuthManageModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @var Fido2Auth
     */
    public $module;

    public function __construct()
    {
        parent::__construct();

        // Require customer to be logged in
        $this->auth = true;
        $this->authRedirection = 'my-account';
    }

    /**
     * Initialize controller
     */
    public function init()
    {
        parent::init();

        // Check if FIDO2 is enabled
        if (!Configuration::get('FIDO2AUTH_ENABLED')) {
            Tools::redirect('index.php?controller=my-account');
        }
    }

    /**
     * Post process - handle AJAX requests
     */
    public function postProcess()
    {
        if (!$this->ajax) return;

        // CLEAN BUFFER & SET HEADER
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'list':
                    $this->listCredentials();
                    break;
                case 'delete':
                    $this->deleteCredential();
                    break;
                case 'update_name':
                    $this->updateCredentialName();
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

    /**
     * List all credentials for current customer
     */
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
            $credentials = $credentialManager->getCustomerCredentials($customer->id);

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

    /**
     * Delete a credential
     */
    private function deleteCredential()
    {
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($customer)) {
            die(json_encode([
                'success' => false,
                'message' => 'Customer not authenticated',
            ]));
        }

        try {
            $postData = file_get_contents('php://input');
            $data = json_decode($postData, true);

            if (!isset($data['credential_id'])) {
                throw new Exception('Credential ID is required');
            }

            $credentialId = (int) $data['credential_id'];
            $credentialManager = $this->module->getCredentialManager();

            // Check if this is the last credential
            $count = $credentialManager->countCredentials($customer->id);

            if ($count <= 1 && Configuration::get('FIDO2AUTH_REQUIRE_MFA')) {
                throw new Exception('Cannot delete the last security key when MFA is required');
            }

            // Delete credential
            $result = $credentialManager->deleteCredential($credentialId, $customer->id);

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

    /**
     * Update credential device name
     */
    private function updateCredentialName()
    {
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($customer)) {
            die(json_encode([
                'success' => false,
                'message' => 'Customer not authenticated',
            ]));
        }

        try {
            $postData = file_get_contents('php://input');
            $data = json_decode($postData, true);

            if (!isset($data['credential_id']) || !isset($data['device_name'])) {
                throw new Exception('Credential ID and device name are required');
            }

            $credentialId = (int) $data['credential_id'];
            $deviceName = pSQL($data['device_name']);

            if (empty($deviceName) || strlen($deviceName) > 255) {
                throw new Exception('Invalid device name');
            }

            $credentialManager = $this->module->getCredentialManager();
            $result = $credentialManager->updateDeviceName($credentialId, $customer->id, $deviceName);

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

    /**
     * Display credential management page
     */
    public function initContent()
    {
        parent::initContent();

        $customer = $this->context->customer;

        // Get existing credentials
        $credentialManager = $this->module->getCredentialManager();
        $credentials = $credentialManager->getCustomerCredentials($customer->id);

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
            'registration_url' => $this->context->link->getModuleLink(
                'fido2auth',
                'registration',
                [],
                true
            ),
        ]);

        $this->setTemplate('module:fido2auth/views/templates/front/manage.tpl');
    }
}
