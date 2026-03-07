<?php

/**
 * FIDO2 Authentication Controller (Login & MFA Verification)
 *
 * Handles two authentication flows:
 *  1. Passwordless login: User signs in directly with a passkey (no password needed)
 *  2. MFA verification: User already logged in with password, verifies identity with passkey
 *
 * AJAX Endpoints:
 *  - action=get_options: Returns PublicKeyCredentialRequestOptions for navigator.credentials.get()
 *  - action=verify: Validates the assertion response and creates/verifies the session
 */

declare(strict_types=1);

use PrestaShop\Module\Fido2Auth\Service\AssertionVerifier;
use PrestaShop\Module\Fido2Auth\Repository\CredentialRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

class Fido2AuthAuthenticationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    /** @var Fido2Auth */
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
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $postData = json_decode($rawInput, true);

        if (!isset($postData['token']) || $postData['token'] !== Tools::getToken(false)) {
            die(json_encode(['success' => false, 'message' => 'Invalid security token']));
        }

        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'get_options':
                    $this->getAuthenticationOptions($postData);
                    break;
                case 'verify':
                    $this->verifyAuthentication($postData);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('FIDO2 Auth Error: ' . $e->getMessage(), 3, null, 'Fido2Auth');
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    private function getAuthenticationOptions(array $postData)
    {
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
        $credentialRepo = new CredentialRepository();
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
                    'id' => rtrim(strtr(base64_encode($cred->getId()), '+/', '-_'), '='),
                    'transports' => $cred->getTransports(),
                ];
            }, $requestOptions->getAllowCredentials());
        }

        die(json_encode([
            'success' => true,
            'options' => $optionsArray
        ]));
    }

    private function verifyAuthentication(array $postData)
    {
        if (!isset($postData['credential'])) throw new Exception('Invalid request data');

        $clientDataJSON = $this->module->getChallengeManager()->base64UrlDecode($postData['credential']['response']['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        $challengeString = $clientData['challenge'];

        $this->module->getChallengeManager()->validateChallenge(
            $challengeString,
            \PrestaShop\Module\Fido2Auth\Entity\Fido2Challenge::TYPE_AUTHENTICATION
        );

        $rpId = $this->getRpId();
        $credentialRepo = new CredentialRepository();
        $assertionVerifier = new AssertionVerifier($rpId, $credentialRepo);

        $requestOptions = $assertionVerifier->createRequestOptions(
            $this->module->getChallengeManager()->base64UrlDecode($challengeString)
        );
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

        if (!Validate::isLoadedObject($customer)) {
            throw new Exception('User not found linked to this credential');
        }

        if ($this->context->customer->isLogged() && $this->context->customer->id == $customer->id) {
            // Case: User is logged in (Password), verifying MFA
            unset($this->context->cookie->fido2_mfa_pending);

            // Dynamic redirect: return to the page user was trying to access
            $redirectUrl = isset($this->context->cookie->fido2_redirect_url)
                ? $this->context->cookie->fido2_redirect_url
                : $this->context->link->getPageLink('my-account', true);
            unset($this->context->cookie->fido2_redirect_url);
            $this->context->cookie->write();
        } else {
            // Case: User logging in via FIDO2 (Passwordless)
            $this->context->cookie->fido2_login_bypass = true;

            // Save guest cart before login to preserve cart contents
            $guestCartId = (int) $this->context->cart->id;
            $guestCartProducts = [];
            if ($guestCartId && $this->context->cart->nbProducts() > 0) {
                $guestCartProducts = $this->context->cart->getProducts();
            }

            // PrestaShop Login Flow
            $this->context->updateCustomer($customer);

            // Transfer guest cart products if they were lost during login
            if (!empty($guestCartProducts) && $this->context->cart->nbProducts() === 0) {
                foreach ($guestCartProducts as $product) {
                    $this->context->cart->updateQty(
                        (int) $product['cart_quantity'],
                        (int) $product['id_product'],
                        (int) $product['id_product_attribute']
                    );
                }
            }

            // Apply cart rules
            \CartRule::autoAddToCart($this->context);

            \Hook::exec('actionAuthentication', ['customer' => $customer]);

            // Clear MFA flag if exists (since FIDO2 login is considered strong auth)
            if (isset($this->context->cookie->fido2_mfa_pending)) {
                unset($this->context->cookie->fido2_mfa_pending);
            }
            $this->context->cookie->write();

            $redirectUrl = $this->context->link->getPageLink('my-account', true);
        }

        die(json_encode([
            'success' => true,
            'message' => $this->module->l('Authentication successful'),
            'redirect' => $redirectUrl
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
        // Use the current HTTP host (ignoring port) to support dynamic domains like Ngrok
        return \Tools::getHttpHost(false, false, true);
    }
}
