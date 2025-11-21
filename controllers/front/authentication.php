<?php

/**
 * FIDO2 Authentication Controller
 */

declare(strict_types=1);

use PrestaShop\Module\Fido2Auth\Service\AssertionVerifier;
use PrestaShop\Module\Fido2Auth\Repository\CredentialRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

class Fido2AuthAuthenticationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $module;

    public function init()
    {
        parent::init();
        if (!Configuration::get('FIDO2AUTH_ENABLED')) {
            Tools::redirect('index.php');
        }
    }

    public function postProcess()
    {
        if (!$this->ajax) return;

        // BERSIHKAN BUFFER OUTPUT SEBELUM KIRIM JSON
        // Ini memperbaiki error "Unexpected token <"
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'get_options':
                    $this->getAuthenticationOptions();
                    break;
                case 'verify':
                    $this->verifyAuthentication();
                    break;
                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    private function getAuthenticationOptions()
    {
        $rawInput = file_get_contents('php://input');
        $postData = json_decode($rawInput, true);
        $email = isset($postData['email']) ? pSQL($postData['email']) : null;

        $customerId = null;

        if ($this->context->customer->isLogged() && isset($this->context->cookie->fido2_mfa_pending)) {
            $customerId = (int)$this->context->customer->id;
        } elseif ($email) {
            $customer = new Customer();
            $customer->getByEmail($email);
            if (Validate::isLoadedObject($customer)) {
                $customerId = (int)$customer->id;
            }
        }

        $challengeManager = $this->module->getChallengeManager();
        $challengeData = $challengeManager->generateAuthenticationChallenge($customerId);

        $allowCredentials = [];
        if ($customerId) {
            $allowCredentials = $this->module->getCredentialManager()->getCustomerCredentialIds($customerId);
        }

        $rpId = $this->getRpId();
        $credentialRepo = new CredentialRepository($this->context->link);
        $assertionVerifier = new AssertionVerifier($rpId, $credentialRepo);

        $requestOptions = $assertionVerifier->createRequestOptions(
            $challengeData['challenge'],
            $allowCredentials,
            (int) Configuration::get('FIDO2AUTH_TIMEOUT')
        );

        $optionsArray = [
            'challenge' => $requestOptions->getChallenge(),
            'timeout' => $requestOptions->getTimeout(),
            'rpId' => $requestOptions->getRpId(),
            'userVerification' => $requestOptions->getUserVerification(),
        ];

        if (!empty($requestOptions->getAllowCredentials())) {
            $optionsArray['allowCredentials'] = array_map(function ($cred) {
                return [
                    'type' => $cred->getType(),
                    'id' => $cred->getId(),
                    'transports' => $cred->getTransports(),
                ];
            }, $requestOptions->getAllowCredentials());
        }

        die(json_encode([
            'success' => true,
            'options' => $optionsArray
        ]));
    }

    private function verifyAuthentication()
    {
        $rawInput = file_get_contents('php://input');
        $postData = json_decode($rawInput, true);

        if (!isset($postData['credential'])) throw new Exception('Invalid request data');

        $clientDataJSON = base64_decode($postData['credential']['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        $challengeString = $clientData['challenge'];

        $this->module->getChallengeManager()->validateChallenge(
            $challengeString,
            \PrestaShop\Module\Fido2Auth\Entity\Fido2Challenge::TYPE_AUTHENTICATION
        );

        $rpId = $this->getRpId();
        $credentialRepo = new CredentialRepository($this->context->link);
        $assertionVerifier = new AssertionVerifier($rpId, $credentialRepo);

        $requestOptions = $assertionVerifier->createRequestOptions($challengeString);
        $psr17Factory = new Psr17Factory();
        $serverRequest = (new ServerRequestCreator($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory))->fromGlobals();

        $validatedAssertion = $assertionVerifier->validateAssertion(
            $postData['credential'],
            $requestOptions,
            $serverRequest
        );

        $credentialManager = $this->module->getCredentialManager();
        $credential = $credentialManager->getCredential($validatedAssertion['credential_id']);

        if (!$credential) throw new Exception('Credential not found');

        $credentialManager->updateCredentialUsage($credential, $validatedAssertion['sign_count']);
        $this->module->getChallengeManager()->consumeChallenge($challengeString);

        $customer = new Customer($credential->getCustomerId());

        if ($this->context->customer->isLogged() && $this->context->customer->id == $customer->id) {
            unset($this->context->cookie->fido2_mfa_pending);
            $this->context->cookie->write();
        } else {
            $this->context->cookie->fido2_login_bypass = true;
            $this->context->updateCustomer($customer);
            Hook::exec('actionAuthentication', ['customer' => $customer]);

            if (isset($this->context->cookie->fido2_mfa_pending)) {
                unset($this->context->cookie->fido2_mfa_pending);
            }
            $this->context->cookie->write();
        }

        die(json_encode([
            'success' => true,
            'message' => $this->l('Authentication successful'),
            'redirect' => $this->context->link->getPageLink('my-account', true)
        ]));
    }

    public function initContent()
    {
        parent::initContent();

        $isMfaMode = isset($this->context->cookie->fido2_mfa_pending) && $this->context->cookie->fido2_mfa_pending;

        $this->context->smarty->assign([
            'is_mfa_mode' => $isMfaMode,
            'ajax_url' => $this->context->link->getModuleLink('fido2auth', 'authentication', [], true)
        ]);

        $this->setTemplate('module:fido2auth/views/templates/front/authentication.tpl');
    }

    private function getRpId(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return explode(':', $host)[0];
    }
}
