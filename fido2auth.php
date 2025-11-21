<?php

/**
 * FIDO2/WebAuthn Multi-Factor Authentication Module
 *
 * @author    Muh. Zaki Erbai Syas
 * @copyright 2025
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
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
        $this->description = $this->l('Advanced security with FIDO2-based multi-factor authentication.');

        $this->initializeServices();
    }

    private function initializeServices(): void
    {
        // Cek koneksi DB sebelum init repository untuk menghindari error saat install fresh
        if (!Module::isInstalled($this->name)) {
            return;
        }

        try {
            $credentialRepo = new CredentialRepository($this->context->link);
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
        return $output . $this->displayForm();
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
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitFido2AuthConfig';
        $helper->fields_value['FIDO2AUTH_ENABLED'] = Configuration::get('FIDO2AUTH_ENABLED');
        $helper->fields_value['FIDO2AUTH_RP_NAME'] = Configuration::get('FIDO2AUTH_RP_NAME');
        $helper->fields_value['FIDO2AUTH_TIMEOUT'] = Configuration::get('FIDO2AUTH_TIMEOUT');

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * Hook Intersepsi Login (MFA Check)
     */
    public function hookActionAuthentication($params): void
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED')) return;

        // Jika login via Passwordless (FIDO2 Controller), abaikan hook ini
        // untuk mencegah infinite loop atau re-trigger MFA
        if (isset($this->context->cookie->fido2_login_bypass)) {
            unset($this->context->cookie->fido2_login_bypass);
            $this->context->cookie->write();
            return;
        }

        $customer = $params['customer'];
        // Pastikan service terload
        if (!$this->credentialManager) $this->initializeServices();

        if ($this->credentialManager && $this->credentialManager->hasCredentials((int)$customer->id)) {
            $this->context->cookie->fido2_mfa_pending = true;
            $this->context->cookie->write();
        }
    }

    /**
     * Hook Firewall Halaman
     */
    public function hookActionFrontControllerSetMedia($params): void
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED')) return;

        if (
            $this->context->customer->isLogged() &&
            isset($this->context->cookie->fido2_mfa_pending) &&
            $this->context->cookie->fido2_mfa_pending == true
        ) {

            $controller = $this->context->controller;

            // Izinkan hanya controller Auth FIDO2 atau proses logout
            if (
                !($controller instanceof Fido2AuthAuthenticationModuleFrontController) &&
                $controller->php_self !== 'authentication'
            ) {

                Tools::redirect($this->context->link->getModuleLink('fido2auth', 'authentication'));
            }
        }
    }

    public function hookDisplayCustomerAccount()
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED')) return '';
        $this->context->smarty->assign(['fido2_manage_url' => $this->context->link->getModuleLink('fido2auth', 'manage', [], true)]);
        return $this->display(__FILE__, 'views/templates/hook/customer_account.tpl');
    }

    public function hookDisplayHeader()
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED')) return;

        $controller = $this->context->controller;
        if (
            $controller instanceof Fido2AuthAuthenticationModuleFrontController ||
            $controller instanceof Fido2AuthRegistrationModuleFrontController ||
            $controller instanceof Fido2AuthManageModuleFrontController
        ) {

            $this->context->controller->registerStylesheet(
                'module-fido2auth-style',
                'modules/' . $this->name . '/views/css/front.css',
                ['media' => 'all', 'priority' => 150]
            );
        }
    }

    public function hookDisplayCustomerLoginFormAfter($params)
    {
        if (!Configuration::get('FIDO2AUTH_ENABLED')) return '';
        $this->context->smarty->assign([
            'fido2_auth_url' => $this->context->link->getModuleLink('fido2auth', 'authentication', [], true),
            'fido2_registration_url' => $this->context->link->getModuleLink('fido2auth', 'registration', [], true),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/login_form.tpl');
    }

    public function getChallengeManager(): ?ChallengeManager
    {
        if (!$this->challengeManager) $this->initializeServices();
        return $this->challengeManager;
    }

    public function getCredentialManager(): ?CredentialManager
    {
        if (!$this->credentialManager) $this->initializeServices();
        return $this->credentialManager;
    }
}
