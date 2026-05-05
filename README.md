# FIDO2/WebAuthn Authentication for PrestaShop

[![PrestaShop](https://img.shields.io/badge/PrestaShop-9.x-blue.svg)](https://www.prestashop.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple.svg)](https://www.php.net/)
[![WebAuthn](https://img.shields.io/badge/WebAuthn-Level%202-orange.svg)](https://www.w3.org/TR/webauthn-2/)
[![FIDO2](https://img.shields.io/badge/FIDO2-Compliant-brightgreen.svg)](https://fidoalliance.org/fido2/)
[![License](https://img.shields.io/badge/License-AFL--3.0-green.svg)](https://opensource.org/licenses/AFL-3.0)

An open-source PrestaShop module that implements **FIDO2/WebAuthn-based Multi-Factor Authentication (MFA)** and **Passwordless Login** for e-commerce customers. Built on the W3C WebAuthn Level 2 standard and FIDO2 specification, this module enables phishing-resistant authentication using hardware security keys, platform biometrics, and cross-device passkeys — all integrated **non-invasively** through PrestaShop's native hook system.

> **Research Context:** This module was developed as part of a research study on e-commerce authentication security. The accompanying research paper is published at https://everant.org/index.php/etj/article/view/2731. The module is freely available as an open-source contribution to the PrestaShop community.

---

## Table of Contents

- [Features](#-features)
- [How It Works](#-how-it-works)
- [Security Architecture](#-security-architecture)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#%EF%B8%8F-configuration)
- [Usage](#-usage)
- [Module Architecture](#-module-architecture)
- [Database Schema](#-database-schema)
- [Hook Integration](#-hook-integration)
- [API Endpoints](#-api-endpoints)
- [Error Handling](#-error-handling)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)
- [License](#-license)
- [Author](#-author)
- [Acknowledgments](#-acknowledgments)

---

## ✨ Features

### Authentication Modes

| Mode | Description |
|------|-------------|
| **Passwordless Login** | Sign in with a passkey directly — no password required. The passkey button is injected inline on the login page. |
| **Multi-Factor Authentication (MFA)** | After standard password login, customers with registered credentials must verify their identity with a passkey before accessing protected pages (account, checkout). |

### Supported Authenticators

| Type | Examples |
|------|----------|
| **Platform Authenticators** | Windows Hello, macOS Touch ID, Android Biometrics |
| **Roaming Authenticators** | YubiKey, Google Titan Key, SoloKeys, Feitian |
| **Cross-Device Passkeys** | Synced passkeys via iCloud Keychain, Google Password Manager |

### Key Capabilities

- **Non-Invasive Integration** — Zero modifications to PrestaShop core files; uses 5 native hooks
- **Cart Retention** — Shopping cart contents are preserved during passwordless login
- **Dual-Mode Authentication** — Passwordless and MFA in a single module
- **Credential Management** — Customer self-service page to register, view, and delete security keys
- **Multi-Key Support** — Register multiple authenticators per account with `excludeCredentials` duplicate prevention
- **Backward Compatible** — Conventional password login remains fully functional
- **Soft-Delete Credentials** — Removed keys are deactivated (not deleted), preserving audit trail
- **Cross-Browser Compatible** — Tested on Chrome, Firefox, and Safari
- **Responsive UI** — Optimized for both desktop and mobile browsers

---

## 🔐 How It Works

### FIDO2/WebAuthn in Brief

FIDO2 replaces shared secrets (passwords, OTPs) with **asymmetric cryptography**:

1. **Registration (Attestation Ceremony):** The authenticator generates a unique **public-private key pair**. The public key is sent to the server; the **private key never leaves the device**.
2. **Authentication (Assertion Ceremony):** The server sends a random challenge. The authenticator signs it with the private key. The server verifies the signature with the stored public key.

```
┌──────────────┐          ┌──────────────┐          ┌───────────────────┐
│  Browser /   │          │  PrestaShop  │          │   Authenticator   │
│  JavaScript  │◄────────►│   Server     │          │  (YubiKey/Hello)  │
│  WebAuthn API│          │  PHP Backend │          │                   │
└──────┬───────┘          └─────┬────────┘          └──────┬────────────┘
       │                        │                          │
       │  1. Request options    │                          │
       │───────────────────────►│                          │
       │                        │                          │
       │  2. Challenge + params │                          │
       │◄───────────────────────│                          │
       │                        │                          │
       │  3. Create credential  │                          │
       │─────────────────────────────────────────────────► │
       │                        │                          │
       │  4. Signed response    │      (private key stays) │
       │◄───────────────────────────────────────────────── │
       │                        │                          │
       │  5. Send attestation   │                          │
       │───────────────────────►│                          │
       │                        │                          │
       │  6. Verify & store     │                          │
       │◄───────────────────────│                          │
```

### Why Phishing-Resistant?

- **Origin Binding:** Credentials are cryptographically bound to the domain (RP ID). They **will not work** on a phishing site with a different domain.
- **No Shared Secret:** The private key never leaves the device and is never transmitted, so there is nothing for an attacker to intercept.
- **Challenge-Response:** Each authentication uses a unique, server-generated challenge, preventing replay attacks.

---

## 🛡 Security Architecture

### Security Measures

| # | Measure | Implementation |
|---|---------|----------------|
| 1 | **Challenge Security** | 256-bit random challenges (`random_bytes(32)`), stored with expiration timestamps (configurable timeout), consumed after single use |
| 2 | **Origin Binding** | Relying Party ID dynamically derived from HTTP host, ensuring credentials are domain-bound |
| 3 | **Replay Prevention** | Challenges marked as `used` after validation; signature counter (`sign_count`) tracked and incremented on every authentication |
| 4 | **CSRF Protection** | All AJAX endpoints validate PrestaShop's static security token (`Tools::getToken()`) |
| 5 | **Credential Safety** | Soft-delete mechanism (`is_active` flag); ownership verification (`id_customer`) on all operations |
| 6 | **User Handle Privacy** | Customer ID hashed with SHA-256 (`hash('sha256', customerId, true)`) to prevent information leakage in WebAuthn payloads |

### Cryptographic Algorithms (COSE)

The module supports the following COSE algorithms for broad authenticator compatibility:

**Creation (sent to authenticator):**

| Algorithm | COSE ID | Family |
|-----------|---------|--------|
| ES256 | -7 | ECDSA (P-256 + SHA-256) |
| RS256 | -257 | RSA (PKCS#1 v1.5 + SHA-256) |
| EdDSA | -8 | Edwards-curve (Ed25519) |

**Verification (server-side, Algorithm Manager):**

ES256 (-7), ES384 (-35), ES512 (-36), RS256 (-257), RS384 (-258), RS512 (-259), EdDSA (-8) — 7 algorithms total for maximum backward compatibility.

### WebAuthn Level 2 Compliance

| Property | Value | Spec Requirement |
|----------|-------|------------------|
| `rp.id` | Dynamic (HTTP host) | Must match current domain |
| `challenge` | 32 bytes (256-bit) | ≥ 16 bytes (128-bit) |
| `pubKeyCredParams` | 3 algorithms | ≥ 1 algorithm |
| `userVerification` | `"preferred"` | Valid enum value |
| `residentKey` | `"preferred"` | Valid enum value |
| `attestation` | `"none"` | Valid enum value |
| `timeout` | Configurable (default: 60000ms) | Positive integer |

---

## 📦 Requirements

| Requirement | Version | Notes |
|-------------|---------|-------|
| PrestaShop | 9.x | Flashlight or standard installation |
| PHP | 8.1+ | Required for type safety and modern features |
| MySQL | 5.7+ | With `utf8mb4` charset |
| HTTPS | **Required** | WebAuthn mandates a secure context (`https://` or `localhost`) |
| Composer | 2.x | For dependency management |

### PHP Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `web-auth/webauthn-lib` | ^4.9 | Core WebAuthn PHP implementation |
| `web-auth/cose-lib` | ^4.3 | COSE algorithm support |
| `nyholm/psr7` | ^1.8 | PSR-7 HTTP message implementation |
| `nyholm/psr7-server` | ^1.1 | PSR-7 server request creator |
| `ramsey/uuid` | ^4.7 | UUID generation |
| `brick/math` | ^0.11.0 | Arbitrary precision arithmetic |

### Browser Support

| Browser | Minimum Version | Authenticator Support |
|---------|-----------------|----------------------|
| Chrome / Edge | 90+ | Platform + Roaming |
| Firefox | 90+ | Platform + Roaming |
| Safari | 14+ | Platform + Roaming |

---

## 🚀 Installation

### Option 1: PrestaShop Back Office

1. Download the latest release `.zip` from the [Releases](https://github.com/rel1vee/fido2auth/releases) page
2. Log in to your **PrestaShop Back Office**
3. Go to **Modules → Module Manager**
4. Click **Upload a module** and select the `.zip` file

### Option 2: Manual (Development)

```bash
cd /path/to/prestashop/modules/
git clone https://github.com/rel1vee/fido2auth.git
cd fido2auth
composer install --no-dev --optimize-autoloader
```

Then install via Back Office: **Modules → Module Manager → Search "FIDO2" → Install**.

### Option 3: Docker (Development)

```yaml
# docker-compose.yml
services:
  prestashop:
    image: prestashop/prestashop-flashlight:latest
    depends_on:
      mysql:
        condition: service_healthy
    environment:
      - PS_DOMAIN=localhost:8000
    ports:
      - 8000:80
    volumes:
      - ./modules/fido2auth:/var/www/html/modules/fido2auth

  mysql:
    image: mariadb:lts
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect"]
      interval: 10s
      timeout: 10s
      retries: 5
    environment:
      - MYSQL_USER=prestashop
      - MYSQL_PASSWORD=prestashop
      - MYSQL_ROOT_PASSWORD=prestashop
      - MYSQL_DATABASE=prestashop
```

> **Note for HTTPS in Development:** Use [ngrok](https://ngrok.com/) to tunnel HTTPS: `ngrok http 8000`. WebAuthn requires HTTPS except for `localhost`.

### What Happens on Install

The module automatically:
- Creates 2 database tables: `fido2_credentials` and `fido2_challenges`
- Registers 5 PrestaShop hooks
- Sets default configuration values (FIDO2 enabled, RP name = shop name, timeout = 60s)

### What Happens on Uninstall

- Both database tables are **dropped** (`DROP TABLE`)
- All registered credentials are permanently deleted
- PrestaShop returns to its original state (non-invasive)

> **Important:** Uninstalling removes ALL registered credentials for ALL customers. Private keys stored on user devices become orphaned (unusable) since the corresponding public keys are deleted from the server.

---

## ⚙️ Configuration

After installation, go to **Modules → Module Manager**, find "MFA - FIDO2/WebAuthn" and click **Configure**.

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable FIDO2** | Enable or disable the module globally | Enabled |
| **Relying Party Name** | Name displayed in authenticator prompts (e.g., "MyStore") | Shop name |
| **Timeout (ms)** | How long the browser waits for the authenticator response | 60000 (1 min) |

> **MFA Enforcement:** When a customer has registered at least one security key, the module automatically enforces MFA on protected pages (My Account, Checkout). The customer must verify with their passkey after password login before accessing these pages.

---

## 📖 Usage

### Registering a Security Key

1. Log in to your PrestaShop account
2. Navigate to **My Account → Manage Security Keys**
3. Enter a descriptive name for your device (e.g., "My YubiKey", "Work Laptop", "Samsung Galaxy")
4. Click **Register Key**
5. Follow the native OS prompt:
   - **Windows:** Windows Hello (fingerprint, face, or PIN)
   - **macOS:** Touch ID
   - **Android:** Fingerprint or screen lock
   - **USB Key:** Insert and tap your security key
6. Success! Your key is now registered.

### Passwordless Login

1. On the login page, click the **🔒 Passkey** button below the standard login form
2. Follow the browser/OS prompt to select and verify your identity
3. You are signed in — no password entered at any point
4. Your shopping cart (if any) is preserved

### Password + MFA Login

1. Enter your email and password as usual
2. After successful password verification, you are redirected to the **Security Verification** page
3. The WebAuthn prompt triggers automatically
4. Verify with your registered security key or biometric
5. Full access to your account is granted

### Managing Keys

On the **Manage Security Keys** page (`/module/fido2auth/manage`):

| Action | Description |
|--------|-------------|
| **Register** | Add a new security key with a custom device name |
| **View** | See all registered keys with device name, registration date, and last used date |
| **Remove** | Soft-delete a key (sets `is_active = 0`) — it no longer works for authentication |

---

## 🏗 Module Architecture

### Layered Architecture

```
┌────────────────────────────────────────────────────────┐
│                  Presentation Layer                    │
│  login_form.tpl │ manage.tpl │ authentication.tpl      │
│  front.css │ registration.js │ authentication.js       │
├────────────────────────────────────────────────────────┤
│                  Application Layer                     │
│  registration.php │ authentication.php │ manage.php    │
│               (Front Controllers — AJAX)               │
├────────────────────────────────────────────────────────┤
│                    Service Layer                       │
│  ChallengeManager │ AttestationValidator               │
│  AssertionVerifier │ CredentialManager                 │
├────────────────────────────────────────────────────────┤
│                  Data Access Layer                     │
│  CredentialRepository │ ChallengeRepository            │
├────────────────────────────────────────────────────────┤
│                   Database Layer                       │
│          fido2_credentials │ fido2_challenges          │
└────────────────────────────────────────────────────────┘
```

### Directory Structure

```
fido2auth/
├── fido2auth.php                # Main module class (hooks, install/uninstall, config)
├── config.xml                   # Module metadata (name, version, author)
├── composer.json                # PHP dependencies
├── logo.png                     # Module icon
│
├── controllers/front/
│   ├── authentication.php       # Passwordless login & MFA verification (AJAX)
│   ├── registration.php         # Key registration ceremony (AJAX)
│   └── manage.php               # Credential management page + AJAX
│
├── src/
│   ├── Entity/
│   │   ├── Fido2Credential.php  # Credential value object
│   │   └── Fido2Challenge.php   # Challenge value object
│   ├── Repository/
│   │   ├── CredentialRepository.php  # Credential CRUD operations
│   │   └── ChallengeRepository.php   # Challenge CRUD operations
│   └── Service/
│       ├── AttestationValidator.php   # Registration ceremony validation
│       ├── AssertionVerifier.php      # Authentication ceremony verification
│       ├── ChallengeManager.php       # Challenge creation & lifecycle
│       └── CredentialManager.php      # Credential business logic
│
├── sql/
│   ├── install.php              # CREATE TABLE statements
│   └── uninstall.php            # DROP TABLE statements
│
└── views/
    ├── css/
    │   └── front.css            # All module styles (responsive)
    ├── js/
    │   ├── authentication.js    # WebAuthn assertion (navigator.credentials.get)
    │   ├── registration.js      # WebAuthn attestation (navigator.credentials.create)
    │   └── manage.js            # Manage page UI (delete, refresh)
    └── templates/
        ├── front/
        │   ├── authentication.tpl   # MFA verification page
        │   └── manage.tpl           # Security key management page
        └── hook/
            ├── customer_account.tpl # "Manage Security Keys" link in My Account
            └── login_form.tpl       # Inline passkey button on login form
```

---

## 🗄 Database Schema

### `ps_fido2_credentials`

| Column | Type | Description |
|--------|------|-------------|
| `id_fido2_credential` | INT UNSIGNED PK | Auto-increment ID |
| `id_customer` | INT UNSIGNED | FK to PrestaShop customer |
| `credential_id` | VARCHAR(255) UNIQUE | Base64url-encoded credential identifier |
| `credential_public_key` | TEXT | Serialized public key (CBOR/PEM) |
| `attestation_type` | VARCHAR(50) | Attestation statement type |
| `aaguid` | VARCHAR(36) | Authenticator model identifier |
| `sign_count` | INT UNSIGNED | Signature counter for clone detection |
| `transports` | VARCHAR(255) | Supported transports (usb, nfc, ble, internal) |
| `device_name` | VARCHAR(255) | User-assigned device name |
| `user_agent` | TEXT | Browser user-agent at registration time |
| `created_at` | DATETIME | Registration timestamp |
| `last_used_at` | DATETIME | Last successful authentication timestamp |
| `is_active` | TINYINT(1) | Active flag (1=active, 0=soft-deleted) |

### `ps_fido2_challenges`

| Column | Type | Description |
|--------|------|-------------|
| `id_fido2_challenge` | INT UNSIGNED PK | Auto-increment ID |
| `challenge` | VARCHAR(255) UNIQUE | Base64url-encoded challenge bytes |
| `user_handle` | VARCHAR(255) | SHA-256 hashed customer ID |
| `id_customer` | INT UNSIGNED | FK to PrestaShop customer (nullable for passwordless) |
| `challenge_type` | ENUM | `"registration"` or `"authentication"` |
| `created_at` | DATETIME | Creation timestamp |
| `expires_at` | DATETIME | Expiration timestamp (based on configured timeout) |
| `used` | TINYINT(1) | Usage flag (0=pending, 1=consumed) |

---

## 🔗 Hook Integration

The module integrates with PrestaShop through **5 hooks**, requiring **zero modifications** to the platform's core codebase:

| Hook | Type | Purpose |
|------|------|---------|
| `displayCustomerAccount` | Display | Adds "Manage Security Keys" link to My Account page |
| `displayHeader` | Display | Loads module CSS on FIDO2 pages and login page |
| `displayCustomerLoginFormAfter` | Display | Injects inline "Passkey" button below the login form |
| `actionAuthentication` | Action | Sets `fido2_mfa_pending` session flag after password login if customer has registered credentials |
| `actionFrontControllerSetMedia` | Action | Saves intended URL and redirects protected pages (account, checkout) to MFA verification when pending |

---

## 🔌 API Endpoints

All endpoints are AJAX-based and require CSRF token validation.

### Registration

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/module/fido2auth/registration?action=get_options` | POST | Returns `PublicKeyCredentialCreationOptions` JSON |
| `/module/fido2auth/registration?action=verify` | POST | Validates attestation response and stores credential |

### Authentication

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/module/fido2auth/authentication?action=get_options` | POST | Returns `PublicKeyCredentialRequestOptions` JSON |
| `/module/fido2auth/authentication?action=verify` | POST | Validates assertion response and creates session |

### Credential Management

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/module/fido2auth/manage?action=delete` | POST | Soft-deletes a credential (requires `credential_id` and ownership verification) |

---

## ⚠️ Error Handling

### Server-Side (PHP)

All controllers implement `sanitizeErrorMessage()` which maps raw library exceptions to user-friendly messages:

| Raw Exception | User-Facing Message |
|---------------|---------------------|
| JSON parsing errors | "An error occurred during verification. Please try again." |
| Invalid attestation | "Security key verification failed. Please try again." |
| Challenge expired | "Verification timed out. Please try again." |
| Challenge reused | "This verification has already been used. Please start a new one." |
| Duplicate device name | "A security key with this name already exists." |

### Client-Side (JavaScript)

Both `registration.js` and `authentication.js` handle WebAuthn-specific errors:

| Error Name | User Message |
|------------|-------------|
| `NotAllowedError` | "Operation cancelled or timed out." |
| `InvalidStateError` | "This authenticator is already registered." |
| `AbortError` | "The operation was aborted." |
| `SecurityError` | "Security error. Please ensure you're using HTTPS." |
| `NotSupportedError` | "This authenticator type is not supported." |

---

## ❗ Troubleshooting

### "WebAuthn is not supported in this browser"
- Update your browser to the latest version
- Ensure your site is served over **HTTPS** (WebAuthn requires a secure context)
- `localhost` is exempt from the HTTPS requirement (for development)

### "Failed to create credential" or authenticator not detected
- Ensure your security key is properly inserted (for USB keys)
- Try a different USB port or reconnect the key
- The user may have cancelled the browser prompt (check for `NotAllowedError`)
- Verify the device/browser supports WebAuthn

### "Challenge not found or expired"
- Increase the timeout in **Configure → Timeout** (e.g., 120000 ms = 2 minutes)
- Check that your server's clock is synchronized (NTP)
- Clear browser cookies and try again
- Each challenge can only be used once (replay prevention)

### "Attestation validation failed"
- Verify that the Relying Party ID matches your domain
- Check for HTTPS certificate issues (self-signed certificates may cause problems)
- Review the PrestaShop logs (**Advanced Parameters → Logs**) for detailed error messages

### Device Lost / Account Recovery
If a customer loses their authenticator device:

1. **Admin Recovery (Database):**
   ```sql
   -- Deactivate all credentials for a specific customer
   UPDATE ps_fido2_credentials SET is_active = 0 WHERE id_customer = [CUSTOMER_ID];
   ```
2. The customer can then log in with their password (MFA is bypassed when no active credentials exist)
3. The customer registers a new device via **My Account → Manage Security Keys**

> **Note:** Uninstalling the module removes ALL credentials for ALL customers. Use the per-customer database approach for individual recovery.

---

## 🤝 Contributing

Contributions are welcome! This project follows the standard GitHub workflow:

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

Start the development environment with Docker:

```bash
docker compose up -d
```

Access PrestaShop at `http://localhost:8000` and use ngrok for HTTPS testing.

### Reporting Security Issues

If you discover a security vulnerability, please **do not** create a public issue. Email [muhzakierbaisyas@gmail.com](mailto:muhzakierbaisyas@gmail.com) with the subject "FIDO2 Security Issue".

---

## 📄 License

This project is licensed under the [Academic Free License 3.0 (AFL-3.0)](https://opensource.org/licenses/AFL-3.0).

---

## 👨‍💻 Author

**Muh. Zaki Erbai Syas**
- GitHub: [@rel1vee](https://github.com/rel1vee)
- Email: muhzakierbaisyas@gmail.com
- Institution: Department of Informatics Engineering, Faculty of Science and Technology, UIN Sultan Syarif Kasim Riau

---

## 🙏 Acknowledgments

- [FIDO Alliance](https://fidoalliance.org/) — FIDO2 standard and CTAP2 specification
- [W3C Web Authentication Working Group](https://www.w3.org/TR/webauthn-2/) — WebAuthn Level 2 specification
- [web-auth/webauthn-framework](https://github.com/web-auth/webauthn-framework) — PHP WebAuthn library by Spomky-Labs
- [PrestaShop Community](https://www.prestashop.com/) — Open-source e-commerce platform
- **Research Advisors:** Rahmad Abdillah, Novriyanto, Suwanto Sanjaya — UIN Sultan Syarif Kasim Riau
