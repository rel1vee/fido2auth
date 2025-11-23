<?php

/**
 * FIDO2 Registration Controller
 */

declare(strict_types=1);

use PrestaShop\Module\Fido2Auth\Service\AttestationValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

class Fido2AuthRegistrationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
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
        if (!$this->ajax) return;
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $postData = json_decode($rawInput, true);

        if (!isset($postData['token']) || !Tools::isToken(false, $postData['token'])) {
            die(json_encode(['success' => false, 'message' => 'Invalid security token']));
        }

        $action = Tools::getValue('action');

        try {
            switch ($action) {
                case 'get_options':
                    $this->getRegistrationOptions();
                    break;
                case 'verify':
                    $this->verifyRegistration();
                    break;
                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            // Gunakan die() bukan ajaxDie() untuk menghindari wrapper PrestaShop yang kadang aneh
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    private function getRegistrationOptions()
    {
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($customer)) {
            die(json_encode([
                'success' => false,
                'message' => 'Customer not authenticated',
            ]));
        }

        try {
            // Get services
            $challengeManager = $this->module->getChallengeManager();
            $credentialManager = $this->module->getCredentialManager();

            // Generate challenge
            $challengeData = $challengeManager->generateRegistrationChallenge($customer->id);

            // Get existing credentials to exclude
            $excludeCredentials = $credentialManager->getCustomerCredentialIds($customer->id);

            // Create attestation validator
            $rpId = $this->getRpId();
            $rpName = Configuration::get('FIDO2AUTH_RP_NAME');
            $attestationValidator = new AttestationValidator($rpId, $rpName);

            // Create creation options
            $creationOptions = $attestationValidator->createCreationOptions(
                $challengeData['challenge'],
                $challengeData['user_handle'],
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                $excludeCredentials,
                (int) Configuration::get('FIDO2AUTH_TIMEOUT')
            );

            // Convert to array for JSON response
            $optionsArray = [
                'rp' => [
                    'name' => $creationOptions->getRp()->getName(),
                    'id' => $creationOptions->getRp()->getId(),
                ],
                'user' => [
                    'id' => $creationOptions->getUser()->getId(),
                    'name' => $creationOptions->getUser()->getName(),
                    'displayName' => $creationOptions->getUser()->getDisplayName(),
                ],
                'challenge' => $creationOptions->getChallenge(),
                'pubKeyCredParams' => array_map(function ($param) {
                    return [
                        'type' => $param->getType(),
                        'alg' => $param->getAlg(),
                    ];
                }, $creationOptions->getPubKeyCredParams()),
                'timeout' => $creationOptions->getTimeout(),
                'excludeCredentials' => array_map(function ($cred) {
                    return [
                        'type' => $cred->getType(),
                        'id' => $cred->getId(),
                    ];
                }, $creationOptions->getExcludeCredentials()),
                'authenticatorSelection' => [
                    'authenticatorAttachment' => $creationOptions->getAuthenticatorSelection()->getAuthenticatorAttachment(),
                    'requireResidentKey' => $creationOptions->getAuthenticatorSelection()->isRequireResidentKey(),
                    'residentKey' => $creationOptions->getAuthenticatorSelection()->getResidentKey(),
                    'userVerification' => $creationOptions->getAuthenticatorSelection()->getUserVerification(),
                ],
                'attestation' => $creationOptions->getAttestation(),
            ];

            die(json_encode([
                'success' => true,
                'options' => $optionsArray,
            ]));
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => 'Failed to generate registration options: ' . $e->getMessage(),
            ]));
        }
    }

    private function verifyRegistration()
    {
        $customer = $this->context->customer;

        if (!Validate::isLoadedObject($customer)) {
            die(json_encode([
                'success' => false,
                'message' => 'Customer not authenticated',
            ]));
        }

        try {
            // Get POST data
            $postData = file_get_contents('php://input');
            $data = json_decode($postData, true);

            if (!isset($data['credential']) || !isset($data['device_name'])) {
                throw new Exception('Invalid request data');
            }

            // Get services
            $challengeManager = $this->module->getChallengeManager();
            $credentialManager = $this->module->getCredentialManager();

            // Validate challenge
            $challenge = $data['credential']['response']['clientDataJSON'];
            $clientData = json_decode(base64_decode($challenge), true);
            $challengeString = $clientData['challenge'];

            $challengeEntity = $challengeManager->validateChallenge(
                $challengeString,
                \PrestaShop\Module\Fido2Auth\Entity\Fido2Challenge::TYPE_REGISTRATION
            );

            // Verify it belongs to this customer
            if ($challengeEntity->getCustomerId() !== $customer->id) {
                throw new Exception('Challenge does not belong to this customer');
            }

            // Create attestation validator
            $rpId = $this->getRpId();
            $rpName = Configuration::get('FIDO2AUTH_RP_NAME');
            $attestationValidator = new AttestationValidator($rpId, $rpName);

            // Recreate creation options for validation
            $creationOptions = $attestationValidator->createCreationOptions(
                $challengeString,
                $challengeEntity->getUserHandle(),
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                [],
                (int) Configuration::get('FIDO2AUTH_TIMEOUT')
            );

            // Create PSR-7 request
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
                $psr17Factory
            );
            $serverRequest = $creator->fromGlobals();

            // Validate attestation
            $validatedCredential = $attestationValidator->validateAttestation(
                $data['credential'],
                $creationOptions,
                $serverRequest
            );

            // Register credential
            $credential = $credentialManager->registerCredential(
                $customer->id,
                $validatedCredential['credential_id'],
                $validatedCredential['public_key_pem'],
                $validatedCredential['attestation_type'],
                [
                    'aaguid' => $validatedCredential['aaguid'],
                    'transports' => $validatedCredential['transports'],
                    'device_name' => pSQL($data['device_name']),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]
            );

            // Consume challenge
            $challengeManager->consumeChallenge($challengeString);

            die(json_encode([
                'success' => true,
                'message' => 'Security key registered successfully',
                'credential' => $credential->toArray(),
            ]));
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
            ]));
        }
    }

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
            'ajax_url' => $this->context->link->getModuleLink(
                'fido2auth',
                'registration',
                [],
                true
            ),
        ]);

        $this->setTemplate('module:fido2auth/views/templates/front/registration.tpl');
    }

    private function getRpId(): string
    {
        return \Tools::getShopDomain();
    }
}
