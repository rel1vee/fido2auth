/**
 * FIDO2 Authentication JavaScript - Enhanced
 */

class Fido2Authentication {
  constructor(ajaxUrl) {
    this.ajaxUrl = ajaxUrl;
    this.isAuthenticating = false;
  }

  isSupported() {
    return window.PublicKeyCredential !== undefined;
  }

  // Helper Base64URL (Wajib ada)
  base64UrlDecode(input) {
    input = input.replace(/-/g, "+").replace(/_/g, "/");
    const pad = input.length % 4;
    if (pad) input += new Array(5 - pad).join("=");
    return atob(input);
  }

  base64UrlToUint8Array(base64url) {
    if (!base64url) return new Uint8Array();
    const binary = this.base64UrlDecode(base64url);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
  }

  base64UrlEncode(arrayBuffer) {
    const bytes = new Uint8Array(arrayBuffer);
    let binary = "";
    for (let i = 0; i < bytes.byteLength; i++)
      binary += String.fromCharCode(bytes[i]);
    return btoa(binary)
      .replace(/\+/g, "-")
      .replace(/\//g, "_")
      .replace(/=/g, "");
  }

  async authenticate(email = null) {
    if (!this.isSupported())
      throw new Error("Browser Anda tidak mendukung FIDO2.");
    if (this.isAuthenticating) return;

    this.isAuthenticating = true;

    try {
      const options = await this.getAuthenticationOptions(email);
      const credential = await this.getCredential(options);
      return await this.verifyAuthentication(credential);
    } catch (error) {
      console.error("FIDO2 Error:", error);
      throw error; // Re-throw agar UI bisa menangkapnya
    } finally {
      this.isAuthenticating = false;
    }
  }

  async getAuthenticationOptions(email) {
    const body = JSON.stringify({
      email: email,
      token: prestashop.static_token,
    });

    const response = await fetch(this.ajaxUrl + "&action=get_options", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: body,
    });

    // Cek response mentah dulu untuk debug "Unexpected token"
    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("Server Response Invalid JSON:", text);
      throw new Error(
        "Server Error: Respon tidak valid. Cek console untuk detail."
      );
    }

    if (!data.success)
      throw new Error(data.message || "Gagal mendapatkan opsi autentikasi.");
    return data.options;
  }

  async getCredential(options) {
    const challenge = this.base64UrlToUint8Array(options.challenge);
    const allowCredentials = options.allowCredentials
      ? options.allowCredentials.map((c) => ({
          id: this.base64UrlToUint8Array(c.id),
          type: c.type,
          transports: c.transports,
        }))
      : [];

    const publicKey = {
      challenge: challenge,
      timeout: options.timeout,
      rpId: options.rpId,
      userVerification: options.userVerification,
    };

    if (allowCredentials.length > 0) {
      publicKey.allowCredentials = allowCredentials;
    }

    const credential = await navigator.credentials.get({ publicKey });
    if (!credential) throw new Error("Gagal membaca kunci keamanan.");

    return {
      id: credential.id,
      rawId: this.base64UrlEncode(credential.rawId),
      type: credential.type,
      response: {
        authenticatorData: this.base64UrlEncode(
          credential.response.authenticatorData
        ),
        clientDataJSON: this.base64UrlEncode(
          credential.response.clientDataJSON
        ),
        signature: this.base64UrlEncode(credential.response.signature),
        userHandle: credential.response.userHandle
          ? this.base64UrlEncode(credential.response.userHandle)
          : null,
      },
    };
  }

  async verifyAuthentication(credential) {
    const response = await fetch(this.ajaxUrl + "&action=verify", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        credential: credential,
        token: prestashop.static_token,
      }),
    });

    const data = await response.json();
    if (!data.success) throw new Error(data.message || "Verifikasi gagal.");
    return data;
  }
}

// UI Controller
document.addEventListener("DOMContentLoaded", function () {
  const ajaxUrlInput = document.getElementById("fido2-auth-ajax-url");
  if (!ajaxUrlInput) return;

  const fidoAuth = new Fido2Authentication(ajaxUrlInput.value);
  const authBtn = document.getElementById("fido2-auth-btn");
  const emailInput = document.getElementById("email");
  const statusDiv = document.getElementById("auth-status");

  if (authBtn) {
    authBtn.addEventListener("click", async () => {
      const email = emailInput ? emailInput.value.trim() : null;

      // UI Loading State
      authBtn.disabled = true;
      authBtn.innerHTML =
        '<i class="material-icons">hourglass_empty</i> Memproses...';
      statusDiv.style.display = "none";

      try {
        const result = await fidoAuth.authenticate(email);

        statusDiv.className = "alert alert-success";
        statusDiv.innerText = "Berhasil! Sedang mengalihkan...";
        statusDiv.style.display = "block";

        if (result.redirect) window.location.href = result.redirect;
      } catch (error) {
        statusDiv.className = "alert alert-danger";
        statusDiv.innerText = error.message;
        statusDiv.style.display = "block";

        authBtn.disabled = false;
        authBtn.innerHTML =
          '<i class="material-icons">fingerprint</i> Coba Lagi';
      }
    });
  }
});
