/**
 * FIDO2 Registration JavaScript
 */

class Fido2Registration {
  constructor(ajaxUrl) {
    this.ajaxUrl = ajaxUrl;
    this.isRegistering = false;
  }

  /**
   * Check if WebAuthn is supported
   */
  isSupported() {
    return (
      window.PublicKeyCredential !== undefined &&
      navigator.credentials !== undefined
    );
  }

  /**
   * Base64URL decode
   */
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

  /**
   * Base64URL encode
   */
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

  /**
   * Convert base64url string to Uint8Array
   */
  base64UrlToUint8Array(base64url) {
    const base64 = this.base64UrlDecode(base64url);
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  /**
   * Start registration process
   */
  async register(deviceName) {
    if (!this.isSupported()) {
      throw new Error("WebAuthn is not supported in this browser");
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

  /**
   * Get registration options from server
   */
  async getRegistrationOptions() {
    const response = await fetch(this.ajaxUrl + "&action=get_options", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || "Failed to get registration options");
    }

    return data.options;
  }

  /**
   * Create credential using WebAuthn API
   */
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

    // Create credential
    const credential = await navigator.credentials.create({
      publicKey: publicKeyCredentialCreationOptions,
    });

    if (!credential) {
      throw new Error("Failed to create credential");
    }

    // Convert credential to JSON-serializable format
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
  }

  /**
   * Verify registration with server
   */
  async verifyRegistration(credential, deviceName) {
    const response = await fetch(this.ajaxUrl + "&action=verify", {
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

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || "Failed to verify registration");
    }

    return data;
  }
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  const ajaxUrl = document.getElementById("fido2-ajax-url")?.value;

  if (!ajaxUrl) {
    console.error("FIDO2 AJAX URL not found");
    return;
  }

  const fido2Registration = new Fido2Registration(ajaxUrl);

  // Check browser support
  if (!fido2Registration.isSupported()) {
    const notSupportedMsg = document.getElementById("webauthn-not-supported");
    if (notSupportedMsg) {
      notSupportedMsg.style.display = "block";
    }
    return;
  }

  // Register button handler
  const registerBtn = document.getElementById("fido2-register-btn");
  const deviceNameInput = document.getElementById("device-name");
  const statusDiv = document.getElementById("registration-status");

  if (registerBtn && deviceNameInput) {
    registerBtn.addEventListener("click", async function () {
      const deviceName = deviceNameInput.value.trim();

      if (!deviceName) {
        showStatus("Please enter a device name", "error");
        return;
      }

      // Disable button
      registerBtn.disabled = true;
      registerBtn.textContent = "Registering...";
      showStatus("Please interact with your security key...", "info");

      try {
        const result = await fido2Registration.register(deviceName);
        showStatus(
          result.message || "Security key registered successfully!",
          "success"
        );

        // Reload page after 2 seconds
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } catch (error) {
        console.error("Registration error:", error);
        showStatus(error.message || "Registration failed", "error");
        registerBtn.disabled = false;
        registerBtn.textContent = "Register Security Key";
      }
    });
  }

  function showStatus(message, type) {
    if (statusDiv) {
      statusDiv.textContent = message;
      statusDiv.className =
        "alert alert-" +
        (type === "error" ? "danger" : type === "success" ? "success" : "info");
      statusDiv.style.display = "block";
    }
  }
});
