# FIDO2/WebAuthn Multi-Factor Authentication for PrestaShop

[![PrestaShop](https://img.shields.io/badge/PrestaShop-9.x-blue.svg)](https://www.prestashop.com/)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-AFL--3.0-green.svg)](https://opensource.org/licenses/AFL-3.0)

Modul autentikasi multi-faktor berbasis FIDO2/WebAuthn untuk PrestaShop yang memberikan keamanan tingkat enterprise dengan pengalaman pengguna yang seamless.

## 📋 Daftar Isi

- [Fitur](#-fitur)
- [Requirements](#-requirements)
- [Instalasi](#-instalasi)
- [Konfigurasi](#️-konfigurasi)
- [Penggunaan](#-penggunaan)
- [Testing](#-testing)
- [Keamanan](#-keamanan)
- [Troubleshooting](#-troubleshooting)
- [Kontribusi](#-kontribusi)

## ✨ Fitur

- **Phishing-Resistant Authentication**: Proteksi kriptografis terhadap serangan phishing
- **Passwordless Login**: Autentikasi tanpa kata sandi menggunakan security key atau biometrics
- **Multi-Device Support**:
  - Hardware security keys (YubiKey, Google Titan, dll)
  - Platform authenticators (Windows Hello, Touch ID, dll)
- **Credential Management**: Interface user-friendly untuk mengelola security keys
- **Backward Compatible**: Tetap mendukung autentikasi password tradisional
- **Standards Compliant**: Implementasi penuh WebAuthn Level 2 (W3C) dan FIDO2

## 📦 Requirements

### Server Requirements

- **PrestaShop**: 9.0.0 atau lebih tinggi
- **PHP**: 8.0 atau lebih tinggi
- **MySQL**: 5.7+ atau MariaDB 10.2+
- **HTTPS**: Wajib (WebAuthn hanya bekerja pada koneksi HTTPS)

### Browser Support

- Chrome/Edge 90+
- Firefox 90+
- Safari 14+
- Opera 76+

## 🚀 Instalasi

### Step 1: Clone atau Download Module

```bash
cd /path/to/prestashop/modules/
git clone https://github.com/rel1vee/fido2auth.git
# atau extract ZIP file ke direktori modules/fido2auth/
```

### Step 2: Install Dependencies dengan Composer

```bash
cd fido2auth
composer install --no-dev
```

### Step 3: Install Module via PrestaShop Admin

1. Login ke PrestaShop Back Office
2. Navigate ke **Modules > Module Manager**
3. Cari "FIDO2" di search bar
4. Click tombol **Install** pada module "MFA - FIDO2/WebAuthn"
5. Module akan otomatis:
   - Membuat database tables yang diperlukan
   - Register hooks ke PrestaShop
   - Set konfigurasi default

### Step 4: Verifikasi Instalasi

Setelah instalasi, verifikasi bahwa:

- ✅ Database tables `fido2_credentials` dan `fido2_challenges` telah dibuat
- ✅ Module muncul di **Modules > Module Manager** dengan status "Enabled"
- ✅ Link "Manage Security Keys" muncul di halaman Customer Account

## ⚙️ Konfigurasi

### Basic Configuration

1. Navigate ke **Modules > Module Manager**
2. Cari "FIDO2" dan click **Configure**
3. Atur settings berikut:

#### General Settings

| Setting                           | Description                            | Default    | Recommended           |
| --------------------------------- | -------------------------------------- | ---------- | --------------------- |
| **Enable FIDO2 Authentication**   | Aktifkan/nonaktifkan modul             | `Enabled`  | `Enabled`             |
| **Require MFA for all customers** | Paksa semua pelanggan menggunakan MFA  | `Disabled` | `Disabled` (optional) |
| **Relying Party Name**            | Nama yang ditampilkan saat autentikasi | Shop Name  | Nama toko Anda        |
| **Timeout (ms)**                  | Batas waktu untuk operasi WebAuthn     | `60000`    | `60000` (1 menit)     |

## 📖 Penggunaan

### Untuk Customer (End Users)

#### 1. Register Security Key

1. Login ke akun PrestaShop
2. Navigate ke **My Account**
3. Click **Manage Security Keys**
4. Click **Add New Security Key**
5. Masukkan nama device (contoh: "My YubiKey")
6. Click **Register Security Key**
7. Follow browser prompts:
   - Insert security key jika menggunakan hardware key
   - Atau gunakan biometric (fingerprint/face)

#### 2. Login dengan Security Key

**Method 1: Direct FIDO2 Login**

1. Navigate ke halaman login
2. Click **Sign in with FIDO2**
3. (Optional) Masukkan email address
4. Click **Sign in with Security Key**
5. Follow browser prompts

**Method 2: Password + FIDO2 (jika MFA required)**

1. Login dengan password seperti biasa
2. Browser akan prompt untuk FIDO2 authentication
3. Use security key atau biometric untuk verifikasi

#### 3. Manage Security Keys

1. Navigate ke **My Account > Manage Security Keys**
2. View semua registered keys
3. Actions:
   - **Rename**: Click "Rename" untuk mengubah nama device
   - **Delete**: Click "Delete" untuk menghapus key (minimum 1 key harus tetap ada jika MFA required)

### Untuk Developer

#### Custom Integration

```php
// Get module instance
$module = Module::getInstanceByName('fido2auth');

// Check if customer has FIDO2 credentials
$credentialManager = $module->getCredentialManager();
$hasCredentials = $credentialManager->hasCredentials($customerId);

// Get customer's credentials
$credentials = $credentialManager->getCustomerCredentials($customerId);

// Count credentials
$count = $credentialManager->countCredentials($customerId);
```

#### Hook Integration

Module register hooks berikut yang bisa Anda gunakan:

```php
// Display link di customer account
public function hookDisplayCustomerAccount($params)

// Display form di login page
public function hookDisplayCustomerLoginFormAfter($params)

// Add CSS/JS ke header
public function hookDisplayHeader($params)
```

## 🧪 Testing

### Unit Testing

Module sudah dilengkapi dengan PHPUnit tests:

```bash
cd modules/fido2auth
composer install --dev
./vendor/bin/phpunit tests/Unit
```

### Manual Testing Checklist

#### Registration Flow

- [ ] Browser menampilkan WebAuthn prompt
- [ ] Hardware key detected dan bisa digunakan
- [ ] Platform authenticator (Touch ID/Windows Hello) berfungsi
- [ ] Credential tersimpan di database
- [ ] Device name tersimpan dengan benar
- [ ] Error handling untuk credential yang sudah terdaftar

#### Authentication Flow

- [ ] Login berhasil dengan hardware key
- [ ] Login berhasil dengan platform authenticator
- [ ] Challenge validation bekerja
- [ ] Sign count increment dengan benar
- [ ] Session created dengan proper
- [ ] Redirect ke my-account setelah login

#### Management Flow

- [ ] List credentials tampil dengan benar
- [ ] Rename credential berfungsi
- [ ] Delete credential berfungsi
- [ ] Tidak bisa delete last credential (jika MFA required)
- [ ] Timestamps (created_at, last_used_at) update dengan benar

### Penetration Testing

Untuk testing keamanan, gunakan tools berikut:

```bash
# Burp Suite - Test WebAuthn flows
# OWASP ZAP - Scan for vulnerabilities
# sqlmap - Test SQL injection

# Test challenge reuse
curl -X POST https://yourdomain.com/module/fido2auth/authentication \
  -H "Content-Type: application/json" \
  -d '{"credential": {...}}'
```

## 🔒 Keamanan

### Security Features

1. **Cryptographic Origin Binding**: Credentials terikat ke domain spesifik
2. **Challenge-Response**: Setiap autentikasi menggunakan challenge unik yang expire
3. **Sign Counter Validation**: Deteksi credential cloning
4. **Secure Storage**: Public keys disimpan terenkripsi di database
5. **HTTPS Enforcement**: Autentikasi hanya berfungsi di HTTPS

### Security Best Practices

#### Server-Side

```php
// Validate RP ID matches domain
$rpId = parse_url($this->context->shop->getBaseURL(), PHP_URL_HOST);

// Verify challenge hasn't been used
$challenge->isValid(); // Check expiry and usage

// Validate sign count
if ($newSignCount <= $currentSignCount) {
    throw new \RuntimeException('Sign count anomaly - possible cloning');
}
```

#### Database Security

```sql
-- Regular cleanup of expired challenges
DELETE FROM fido2_challenges
WHERE expires_at < NOW();

-- Regular cleanup of used challenges
DELETE FROM fido2_challenges
WHERE used = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
```

### Vulnerability Reporting

Jika Anda menemukan security issue, please **DO NOT** create public issue.
Hubungi: muhzakierbaisyas@gmail.com dengan subject "FIDO2 Security Issue"

## ❗ Troubleshooting

### Common Issues

#### 1. "WebAuthn is not supported in this browser"

**Solution**:

- Update browser ke versi terbaru
- Pastikan HTTPS enabled
- Check browser compatibility di caniuse.com/webauthn

#### 2. "Failed to create credential"

**Possible Causes**:

- USB port tidak berfungsi (untuk hardware keys)
- Browser tidak detect authenticator
- User membatalkan prompt

**Solution**:

```javascript
// Enable debug logging
console.log(
  "PublicKeyCredential supported:",
  window.PublicKeyCredential !== undefined
);
```

#### 3. "Challenge not found or expired"

**Solution**:

- Increase timeout: Configuration > FIDO2 > Timeout > 120000ms
- Check server time synchronization (NTP)
- Clear browser cache dan cookies

#### 4. "Attestation validation failed"

**Common Causes**:

- Invalid RP ID (domain mismatch)
- HTTPS certificate issues
- Malformed attestation object

**Debug**:

```php
// Enable debug mode in AttestationValidator
try {
    $result = $attestationValidator->validateAttestation(...);
} catch (\Throwable $e) {
    error_log('Attestation error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
}
```

#### 5. Database Issues

**Tables not created**:

```sql
-- Manually run SQL dari sql/install.php
CREATE TABLE IF NOT EXISTS `fido2_credentials` ...
```

**Permission errors**:

```bash
# Fix MySQL permissions
GRANT ALL PRIVILEGES ON prestashop.* TO 'ps_user'@'localhost';
FLUSH PRIVILEGES;
```

### Debug Mode

Enable debug di `config/config.inc.php`:

```php
if (!defined('_PS_MODE_DEV_')) {
    define('_PS_MODE_DEV_', true);
}
```

### Logs

Check logs di:

```
/var/log/prestashop/fido2auth.log
/var/log/apache2/error.log  # Apache
/var/log/nginx/error.log    # Nginx
```

## 🤝 Kontribusi

Kontribusi sangat welcome! Berikut caranya:

1. Fork repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

### Development Setup

```bash
# Clone repository
git clone https://github.com/rel1vee/fido2auth.git
cd fido2auth

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Code style check
./vendor/bin/php-cs-fixer fix --dry-run
```

## 📄 License

This project is licensed under the Academic Free License 3.0 - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Muh. Zaki Erbai Syas**

- Universitas Islam Negeri Sultan Syarif Kasim Riau
- Email: muhzakierbaisyas@gmail.com
- GitHub: [@rel1vee](https://github.com/rel1vee)

## 🙏 Acknowledgments

- [FIDO Alliance](https://fidoalliance.org/) untuk standar FIDO2
- [W3C](https://www.w3.org/) untuk spesifikasi WebAuthn
- [web-auth/webauthn-framework](https://github.com/web-auth/webauthn-framework) untuk library PHP
- [PrestaShop Community](https://www.prestashop.com/) untuk platform e-commerce

## 📚 References

- [W3C WebAuthn Specification](https://www.w3.org/TR/webauthn-2/)
- [FIDO2 Project](https://fidoalliance.org/fido2/)
- [PrestaShop Developer Documentation](https://devdocs.prestashop-project.org/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)

---

**⭐ Jika project ini membantu, berikan star di GitHub!**
