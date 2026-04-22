<?php

/**
 * FIDO2/WebAuthn Multi-Factor Authentication Module for PrestaShop
 *
 * Provides passwordless and second-factor authentication using the FIDO2/WebAuthn standard.
 * Customers can register hardware security keys (YubiKey), platform authenticators
 * (Windows Hello, Touch ID, Android Biometrics), or cross-device passkeys.
 *
 * Hooks used:
 *  - displayCustomerAccount: Adds "Manage Security Keys" link to My Account page
 *  - displayHeader: Loads module CSS on FIDO2 pages
 *  - displayCustomerLoginFormAfter: Injects "Sign in with Passkey" button on login form
 *  - actionAuthentication: Intercepts login to set MFA-pending flag
 *  - actionFrontControllerSetMedia: Redirects to MFA page if verification pending
 *
 * @author Muh. Zaki Erbai Syas
 * @license AFL-3.0
 * @see https://www.w3.org/TR/webauthn-2/
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\Module\Fido2Auth\Service\ChallengeManager;
use PrestaShop\Module\Fido2Auth\Service\CredentialManager;
use PrestaShop\Module\Fido2Auth\Repository\CredentialRepository;
use PrestaShop\Module\Fido2Auth\Repository\ChallengeRepository;

class Fido2Auth extends Module
{
    private ?ChallengeManager $challengeManager = null;
    private ?CredentialManager $credentialManager = null;

    public function __construct()
    {
        $this->name = 'fido2auth';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Muh. Zaki Erbai Syas';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MFA - FIDO2/WebAuthn');
        $this->description = $this->l('Advanced Security with FIDO2-based Multi-Factor Authentication.');

        $this->initializeServices();
    }

    private function initializeServices(): void
    {
        if (!Module::isInstalled($this->name)) {
            return;
        }

        try {
            $credentialRepo = new CredentialRepository();
            $challengeRepo = new ChallengeRepository();

            $this->challengeManager = new ChallengeManager($challengeRepo);
            $this->credentialManager = new CredentialManager($credentialRepo);
        } catch (Exception $e) {
            PrestaShopLogger::addLog('FIDO2 Init Error: ' . $e->getMessage());
        }
    }

    public function install(): bool
    {
        return parent::install()
            && $this->installDb()
            && $this->registerHook('displayCustomerAccount')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayCustomerLoginFormAfter')
            && $this->registerHook('actionAuthentication')
            && $this->registerHook('actionFrontControllerSetMedia')
            && Configuration::updateValue('FIDO2AUTH_ENABLED', true)
            && Configuration::updateValue('FIDO2AUTH_REQUIRE_MFA', false)
            && Configuration::updateValue('FIDO2AUTH_RP_NAME', Configuration::get('PS_SHOP_NAME'))
            && Configuration::updateValue('FIDO2AUTH_TIMEOUT', 60000);
    }

    public function uninstall(): bool
    {
        return $this->uninstallDb()
            && Configuration::deleteByName('FIDO2AUTH_ENABLED')
            && Configuration::deleteByName('FIDO2AUTH_REQUIRE_MFA')
            && Configuration::deleteByName('FIDO2AUTH_RP_NAME')
            && Configuration::deleteByName('FIDO2AUTH_TIMEOUT')
            && parent::uninstall();
    }

    private function installDb(): bool
    {
        return include(__DIR__ . '/sql/install.php');
    }

    private function uninstallDb(): bool
    {
        return include(__DIR__ . '/sql/uninstall.php');
    }

    public function getContent(): string
    {
        $output = '';
        if (Tools::isSubmit('submitFido2AuthConfig')) {
            Configuration::updateValue('FIDO2AUTH_ENABLED', (bool) Tools::getValue('FIDO2AUTH_ENABLED'));
            Configuration::updateValue('FIDO2AUTH_REQUIRE_MFA', (bool) Tools::getValue('FIDO2AUTH_REQUIRE_MFA'));
            Configuration::updateValue('FIDO2AUTH_RP_NAME', Tools::getValue('FIDO2AUTH_RP_NAME'));
            Configuration::updateValue('FIDO2AUTH_TIMEOUT', (int) Tools::getValue('FIDO2AUTH_TIMEOUT'));
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        if (Tools::isSubmit('reset_fido2_customer')) {
            $id_customer = (int) Tools::getValue('reset_fido2_customer');
            if ($id_customer) {
                Db::getInstance()->update(
                    'fido2_credentials',
                    ['is_active' => 0],
                    'id_customer = ' . $id_customer
                );
                $output .= $this->displayConfirmation(sprintf($this->l('FIDO2 credentials successfully reset for customer #%d.'), $id_customer));
            }
        }

        return $output . $this->displayForm() . $this->renderFido2CustomersList();
    }

    protected function displayForm(): string
    {
        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $this->l('Settings'), 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable FIDO2'),
                        'name' => 'FIDO2AUTH_ENABLED',
                        'is_bool' => true,
                        'values' => [['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')], ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')]]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Relying Party Name'),
                        'name' => 'FIDO2AUTH_RP_NAME',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Timeout (ms)'),
                        'name' => 'FIDO2AUTH_TIMEOUT',
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitFido2AuthConfig';
        $helper->fields_value['FIDO2AUTH_ENABLED'] = Configuration::get('FIDO2AUTH_ENABLED');
        $helper->fields_value['FIDO2AUTH_RP_NAME'] = Configuration::get('FIDO2AUTH_RP_NAME');
        $helper->fields_value['FIDO2AUTH_TIMEOUT'] = Configuration::get('FIDO2AUTH_TIMEOUT');

        return $helper->generateForm([$fieldsForm]);
    }

    protected function renderFido2CustomersList(): string
    {
        $sql = 'SELECT c.`id_customer`, c.`firstname`, c.`lastname`, c.`email`, COUNT(f.`id_fido2_credential`) as `total_keys`
                FROM `' . _DB_PREFIX_ . 'fido2_credentials` f
                JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = f.`id_customer`
                WHERE f.`is_active` = 1
                GROUP BY c.`id_customer`';

        $customers = Db::getInstance()->executeS($sql);

        if (!$customers) {
            return '<div class="panel"><h3><i class="icon-group"></i> ' . $this->l('FIDO2 Customers') . '</h3><p>' . $this->l('No customers have active FIDO2 credentials yet.') . '</p></div>';
        }

        $fields_list = [
            'id_customer' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'firstname' => [
                'title' => $this->l('First Name'),
            ],
            'lastname' => [
                'title' => $this->l('Last Name'),
            ],
            'email' => [
                'title' => $this->l('Email'),
            ],
            'total_keys' => [
                'title' => $this->l('Active Passkeys'),
                'align' => 'center',
                'class' => 'fixed-width-sm',
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->identifier = 'id_customer';
        $helper->actions = ['resetFido2'];
        $helper->show_toolbar = false;
        $helper->title = $this->l('Customers with Active FIDO2 Credentials');
        $helper->table = 'fido2_customer';
        $helper->module = $this;

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;

        return $helper->generateList($customers, $fields_list);
    }

    public function displayResetFido2Link($token, $id, $name = null): string
    {
        $url = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&reset_fido2_customer=' . (int) $id;

        return '
            <a href="' . $url . '" class="btn btn-default" title="' . $this->l('Reset FIDO2') . '" onclick="return confirm(\'' . $this->l('Are you sure you want to reset all FIDO2 credentials for this customer? They will need to register a new passkey or use password login.') . '\');">
                <i class="icon-trash"></i> ' . $this->l('Reset') . '
            </a>
        ';
    }

    /**
     * Hook Login Interception (MFA Check)
     */
    public function hookActionAuthentication($params): void
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED'))
            return;

        // If login via Passwordless (FIDO2 Controller), ignore this hook
        // to prevent infinite loops or re-triggering MFA
        if (isset($this->context->cookie->fido2_login_bypass)) {
            unset($this->context->cookie->fido2_login_bypass);
            $this->context->cookie->write();
            return;
        }

        $customer = $params['customer'];
        if (!$this->credentialManager)
            $this->initializeServices();

        if ($this->credentialManager && $this->credentialManager->hasCredentials((int) $customer->id)) {
            $this->context->cookie->fido2_mfa_pending = true;
            $this->context->cookie->write();
        }
    }

    /**
     * Hook Page Firewall
     * Redirects to MFA page if pending and user is trying to access protected pages.
     */
    public function hookActionFrontControllerSetMedia($params): void
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED'))
            return;

        if (
            $this->context->customer->isLogged() &&
            isset($this->context->cookie->fido2_mfa_pending) &&
            $this->context->cookie->fido2_mfa_pending == true
        ) {

            $controller = $this->context->controller;

            // Allow MFA controller and Login/Password controllers
            if (
                $controller instanceof Fido2AuthAuthenticationModuleFrontController ||
                $controller->php_self === 'authentication' ||
                $controller->php_self === 'password'
            ) {
                return;
            }

            // Define protected controllers that REQUIRE MFA before access
            // We allow browsing the catalog (index, category, product, etc.) but block account/checkout
            $protectedControllers = [
                'my-account',
                'identity',
                'address',
                'addresses',
                'history',
                'order-detail',
                'order-slip',
                'order-follow',
                'order',
                'order-confirmation',
                'discount',
                'guest-tracking',
            ];

            // If it's a module front controller, it might also be protected, but let's be selective
            // For now, we mainly block standard account pages.

            if (in_array($controller->php_self, $protectedControllers)) {
                // Save the page the user was trying to access for post-MFA redirect
                $this->context->cookie->fido2_redirect_url = Tools::getHttpHost(true) . $_SERVER['REQUEST_URI'];
                $this->context->cookie->write();
                Tools::redirect($this->context->link->getModuleLink('fido2auth', 'authentication'));
            }
        }
    }

    public function hookDisplayCustomerAccount()
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED'))
            return '';
        $this->context->smarty->assign(['fido2_manage_url' => $this->context->link->getModuleLink('fido2auth', 'manage', [], true)]);
        return $this->display(__FILE__, 'views/templates/hook/customer_account.tpl');
    }

    public function hookDisplayHeader()
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED'))
            return;

        $controller = $this->context->controller;
        $isModulePage = (
            $controller instanceof Fido2AuthAuthenticationModuleFrontController ||
            $controller instanceof Fido2AuthRegistrationModuleFrontController ||
            $controller instanceof Fido2AuthManageModuleFrontController
        );
        $isLoginPage = ($controller->php_self === 'authentication');

        if ($isModulePage || $isLoginPage) {
            $this->context->controller->registerStylesheet(
                'module-fido2auth-style',
                'modules/' . $this->name . '/views/css/front.css',
                ['media' => 'all', 'priority' => 150]
            );
        }
    }

    public function hookDisplayCustomerLoginFormAfter($params)
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED'))
            return '';
        $this->context->smarty->assign([
            'fido2_auth_url' => $this->context->link->getModuleLink('fido2auth', 'authentication', [], true),
            'fido2_auth_ajax_url' => $this->context->link->getModuleLink('fido2auth', 'authentication', [], true),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/login_form.tpl');
    }

    public function getChallengeManager(): ?ChallengeManager
    {
        if (!$this->challengeManager)
            $this->initializeServices();
        return $this->challengeManager;
    }

    public function getCredentialManager(): ?CredentialManager
    {
        if (!$this->credentialManager)
            $this->initializeServices();
        return $this->credentialManager;
    }
}
