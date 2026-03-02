# FIDO2/WebAuthn Authentication for PrestaShop

[![PrestaShop](https://img.shields.io/badge/PrestaShop-9.x-blue.svg)](https://www.prestashop.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-AFL--3.0-green.svg)](https://opensource.org/licenses/AFL-3.0)

A free, open-source PrestaShop module that adds **FIDO2/WebAuthn** multi-factor and passwordless authentication for customers. Customers can register hardware security keys, biometrics, or passkeys and use them to sign in — replacing or supplementing passwords with phishing-resistant cryptographic credentials.

## ✨ Features

- **Passwordless Login** — Sign in with a passkey directly from the login page, no password required
- **Multi-Factor Authentication (MFA)** — Optionally require a security key after password login
- **Multi-Device Support** — Hardware security keys (YubiKey, Titan), platform authenticators (Windows Hello, Touch ID, Face ID, Android biometrics), and cross-device passkeys
- **Credential Management** — Customer-facing page to register, view, and delete security keys
- **Phishing-Resistant** — Credentials are cryptographically bound to your domain
- **Standards Compliant** — WebAuthn Level 2 (W3C) and FIDO2

## 📦 Requirements

| Requirement   | Version                          |
| ------------- | -------------------------------- |
| PrestaShop    | 9.x                             |
| PHP           | 8.1+                            |
| HTTPS         | **Required** (WebAuthn mandates a secure context) |

### Supported Browsers

| Browser      | Minimum Version |
| ------------ | --------------- |
| Chrome / Edge | 90+            |
| Firefox       | 90+            |
| Safari        | 14+            |

## 🚀 Installation

1. Download the latest release `.zip` from the [Releases](https://github.com/rel1vee/fido2auth/releases) page
2. Log in to your **PrestaShop Back Office**
3. Go to **Modules → Module Manager**
4. Click **Upload a module** and select the `.zip` file

The module will automatically:
- Create the required database tables (`fido2_credentials`, `fido2_challenges`)
- Register all necessary hooks
- Set default configuration values

## ⚙️ Configuration

After installation, go to **Modules → Module Manager**, find "FIDO2" and click **Configure**.

| Setting                     | Description                                      | Default        |
| --------------------------- | ------------------------------------------------ | -------------- |
| **Enable FIDO2**            | Enable or disable the module                     | Enabled        |
| **Relying Party Name**      | Name shown during authenticator prompts          | Your shop name |
| **Timeout (ms)**            | How long the browser waits for the authenticator | 60000 (1 min)  |

> **Note:** The "Require MFA" option, when enabled, prevents customers who have registered a security key from accessing account and checkout pages until they verify with their key.

## 📖 Usage

### Registering a Security Key

1. Log in to your PrestaShop account
2. Go to **My Account → Manage Security Keys**
3. Enter a name for your device (e.g., "My YubiKey", "Work Laptop")
4. Click **Register Key**
5. Follow the browser prompt — insert your security key or use biometrics

### Signing In with a Passkey (Passwordless)

1. On the login page, click the **Passkey** button below the password form
2. Follow the browser prompt to select and use your registered key
3. You are signed in — no password needed

### Signing In with Password + MFA

If MFA is enabled and you have a registered key:

1. Enter your email and password as usual
2. You will be redirected to a verification page
3. Follow the browser prompt to verify with your security key
4. After verification, you are granted full access to your account

### Managing Your Keys

On the **Manage Security Keys** page you can:
- **View** all your registered keys with their names and registration dates
- **Remove** a key you no longer use (click the remove button and confirm)

## 🏗 Architecture

```
fido2auth/
├── fido2auth.php              # Main module class (hooks, install, config)
├── config.xml                 # Module metadata
├── composer.json              # PHP dependencies
├── controllers/front/
│   ├── authentication.php     # Passwordless login & MFA verification (AJAX)
│   ├── registration.php       # Key registration flow (AJAX)
│   └── manage.php             # Credential management page (AJAX + page)
├── src/
│   ├── Entity/
│   │   ├── Fido2Credential.php   # Credential entity
│   │   └── Fido2Challenge.php    # Challenge entity
│   ├── Repository/
│   │   ├── CredentialRepository.php  # Credential DB access
│   │   └── ChallengeRepository.php   # Challenge DB access
│   └── Service/
│       ├── AttestationValidator.php  # Registration (attestation) validation
│       ├── AssertionVerifier.php      # Authentication (assertion) verification
│       ├── ChallengeManager.php       # Challenge lifecycle management
│       └── CredentialManager.php      # Credential business logic
├── sql/
│   ├── install.php            # Database table creation
│   └── uninstall.php          # Database table removal
└── views/
    ├── css/front.css          # Module styles
    ├── js/
    │   ├── authentication.js  # WebAuthn assertion client
    │   ├── registration.js    # WebAuthn attestation client
    │   └── manage.js          # Manage page UI logic
    └── templates/
        ├── front/
        │   ├── authentication.tpl  # MFA verification page
        │   └── manage.tpl          # Manage Security Keys page
        └── hook/
            ├── customer_account.tpl  # "Manage Security Keys" link
            └── login_form.tpl        # Passkey button on login form
```

## ❗ Troubleshooting

### "WebAuthn is not supported in this browser"
- Update your browser to the latest version
- Ensure your site is served over **HTTPS** (WebAuthn requires a secure context)

### "Failed to create credential" or authenticator not detected
- Ensure your security key is properly inserted (for USB keys)
- Try a different USB port
- The user may have cancelled the browser prompt

### "Challenge not found or expired"
- Increase the timeout in **Configure → Timeout** (e.g., 120000 ms)
- Check that your server's clock is synchronized (NTP)
- Clear browser cookies and try again

### "Attestation validation failed"
- Verify that the Relying Party ID matches your domain
- Check for HTTPS certificate issues
- Review the PrestaShop logs (**Advanced Parameters → Logs**) for detailed error messages

## 🤝 Contributing

Contributions are welcome!

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'Add my feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/rel1vee/fido2auth.git
cd fido2auth
composer install
```

### Reporting Security Issues

If you discover a security vulnerability, please **do not** create a public issue. Email [muhzakierbaisyas@gmail.com](mailto:muhzakierbaisyas@gmail.com) with the subject "FIDO2 Security Issue".

## 📄 License

This project is licensed under the [Academic Free License 3.0](https://opensource.org/licenses/AFL-3.0).

## 👨‍💻 Author

**Muh. Zaki Erbai Syas**
- GitHub: [@rel1vee](https://github.com/rel1vee)

## 🙏 Acknowledgments

- [FIDO Alliance](https://fidoalliance.org/) — FIDO2 standard
- [W3C](https://www.w3.org/) — WebAuthn specification
- [web-auth/webauthn-framework](https://github.com/web-auth/webauthn-framework) — PHP WebAuthn library
- [PrestaShop Community](https://www.prestashop.com/) — e-commerce platform
