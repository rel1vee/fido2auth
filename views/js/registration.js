/**
 * FIDO2 Registration Client
 *
 * Handles the client-side WebAuthn registration (attestation) flow.
 * Communicates with the registration controller via AJAX to:
 * 1. Fetch PublicKeyCredentialCreationOptions from the server
 * 2. Call navigator.credentials.create() to produce an attestation
 * 3. Send the attestation back to the server for validation and storage
 *
 * @author Muh. Zaki Erbai Syas
 * @license AFL-3.0
 */
class Fido2Registration {
  /**
   * @param {string} ajaxUrl - The registration controller AJAX endpoint URL
   */
  constructor(ajaxUrl) {
    this.ajaxUrl = ajaxUrl;
    this.isRegistering = false;
  }

  isSupported() {
    return (
      window.PublicKeyCredential !== undefined &&
      navigator.credentials !== undefined
    );
  }

  base64UrlDecode(input) {
    input = input.replace(/-/g, "+").replace(/_/g, "/");

    const pad = input.length % 4;
    if (pad) {
      if (pad === 1) {
        throw new Error("Invalid base64url string");
      }
      input += new Array(5 - pad).join("=");
    }

    return atob(input);
  }

  base64UrlEncode(arrayBuffer) {
    const bytes = new Uint8Array(arrayBuffer);
    let binary = "";
    for (let i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary)
      .replace(/\+/g, "-")
      .replace(/\//g, "_")
      .replace(/=/g, "");
  }



  base64UrlToUint8Array(base64url) {
    if (!base64url) return new Uint8Array();
    const binary = this.base64UrlDecode(base64url);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  async register(deviceName) {
    if (!this.isSupported()) {
      throw new Error("WebAuthn/FIDO2 is not supported in this browser");
    }

    if (this.isRegistering) {
      throw new Error("Registration already in progress");
    }

    if (!deviceName || deviceName.trim().length === 0) {
      throw new Error("Device name is required");
    }

    this.isRegistering = true;

    try {
      // Step 1: Get registration options from server
      const options = await this.getRegistrationOptions();

      // Step 2: Create credentials using WebAuthn API
      const credential = await this.createCredential(options);

      // Step 3: Send credential to server for verification
      const result = await this.verifyRegistration(credential, deviceName);

      this.isRegistering = false;
      return result;
    } catch (error) {
      this.isRegistering = false;
      throw error;
    }
  }

  async getRegistrationOptions() {
    const separator = this.ajaxUrl.includes('?') ? '&' : '?';
    const response = await fetch(this.ajaxUrl + separator + "ajax=1&action=get_options", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ token: prestashop.static_token }),
      credentials: "same-origin",
    });

    const data = await response.json();
    if (!data.success) {
      throw new Error(data.message || "Failed to get registration options");
    }
    return data.options;
  }

  async createCredential(options) {
    // Convert base64url strings to Uint8Array
    const publicKeyCredentialCreationOptions = {
      challenge: this.base64UrlToUint8Array(options.challenge),
      rp: options.rp,
      user: {
        id: this.base64UrlToUint8Array(options.user.id),
        name: options.user.name,
        displayName: options.user.displayName,
      },
      pubKeyCredParams: options.pubKeyCredParams,
      timeout: options.timeout,
      attestation: options.attestation,
      authenticatorSelection: options.authenticatorSelection,
    };

    // Add excludeCredentials if present
    if (options.excludeCredentials && options.excludeCredentials.length > 0) {
      publicKeyCredentialCreationOptions.excludeCredentials =
        options.excludeCredentials.map((cred) => ({
          type: cred.type,
          id: this.base64UrlToUint8Array(cred.id),
        }));
    }

    try {
      // Create credential
      const credential = await navigator.credentials.create({
        publicKey: publicKeyCredentialCreationOptions,
      });

      if (!credential) {
        throw new Error("Failed to create credential");
      }

      // Convert credential to JSON-serializable format (base64url without padding per WebAuthn spec)
      return {
        id: credential.id,
        rawId: this.base64UrlEncode(credential.rawId),
        type: credential.type,
        response: {
          attestationObject: this.base64UrlEncode(
            credential.response.attestationObject
          ),
          clientDataJSON: this.base64UrlEncode(
            credential.response.clientDataJSON
          ),
        },
      };
    } catch (err) {
      if (err.name === 'NotAllowedError') {
        throw new Error("The operation cancelled or timed out.");
      }
      throw err;
    }
  }

  async verifyRegistration(credential, deviceName) {
    const separator = this.ajaxUrl.includes('?') ? '&' : '?';
    const response = await fetch(this.ajaxUrl + separator + "ajax=1&action=verify", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
      body: JSON.stringify({
        credential: credential,
        device_name: deviceName,
        token: prestashop.static_token,
      }),
    });

    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("Server Response:", text);
      throw new Error("Invalid server response. Check console for details.");
    }

    if (!data.success) {
      throw new Error(data.message || "Failed to verify registration");
    }

    return data;
  }
}
