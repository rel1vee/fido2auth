/**
 * FIDO2 Authentication Client
 *
 * Handles the client-side WebAuthn authentication (assertion) flow.
 * Communicates with the authentication controller via AJAX to:
 * 1. Fetch PublicKeyCredentialRequestOptions from the server
 * 2. Call navigator.credentials.get() to produce an assertion
 * 3. Send the assertion back to the server for verification
 *
 * @author Muh. Zaki Erbai Syas
 * @license AFL-3.0
 */
class Fido2Authentication {
  /**
   * @param {string} ajaxUrl - The authentication controller AJAX endpoint URL
   */
  constructor(ajaxUrl) {
    this.ajaxUrl = ajaxUrl;
    this.isAuthenticating = false;
  }

  isSupported() {
    return window.PublicKeyCredential !== undefined;
  }

  // Helper Base64URL
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
      throw new Error("Your browser does not support FIDO2/WebAuthn.");
    if (this.isAuthenticating) return;

    this.isAuthenticating = true;

    try {
      const options = await this.getAuthenticationOptions(email);
      const credential = await this.getCredential(options);
      return await this.verifyAuthentication(credential);
    } catch (error) {
      console.error("FIDO2 Error:", error);
      throw error;
    } finally {
      this.isAuthenticating = false;
    }
  }

  async getAuthenticationOptions(email) {
    const body = JSON.stringify({
      email: email,
      token: prestashop.static_token,
    });

    const separator = this.ajaxUrl.includes('?') ? '&' : '?';
    const response = await fetch(this.ajaxUrl + separator + "ajax=1&action=get_options", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: body,
    });

    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("Server Response Invalid JSON:", text);
      throw new Error(
        "Server Error: Invalid response. Please check console for details."
      );
    }

    if (!data.success)
      throw new Error(data.message || "Failed to get authentication options.");
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

    try {
      const credential = await navigator.credentials.get({ publicKey });
      if (!credential) throw new Error("Failed to read security key.");

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
    } catch (err) {
      if (err.name === 'NotAllowedError') {
        throw new Error("The operation cancelled or timed out.");
      }
      throw err;
    }
  }

  async verifyAuthentication(credential) {
    const separator = this.ajaxUrl.includes('?') ? '&' : '?';
    const response = await fetch(this.ajaxUrl + separator + "ajax=1&action=verify", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        credential: credential,
        token: prestashop.static_token,
      }),
    });

    const data = await response.json();
    if (!data.success) throw new Error(data.message || "Authentication Failed.");
    return data;
  }
}
