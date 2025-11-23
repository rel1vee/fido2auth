/**
 * FIDO2 Credential Management JavaScript
 */

class Fido2CredentialManager {
  constructor(ajaxUrl) {
    this.ajaxUrl = ajaxUrl;
  }

  /**
   * Load credentials from server
   */
  async loadCredentials() {
    const response = await fetch(this.ajaxUrl + "&action=list", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || "Failed to load credentials");
    }

    return data.credentials;
  }

  /**
   * Delete credential
   */
  async deleteCredential(credentialId) {
    const response = await fetch(this.ajaxUrl + "&action=delete", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
      body: JSON.stringify({
        credential_id: credentialId,
        token: prestashop.static_token,
      }),
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || "Failed to delete credential");
    }

    return data;
  }

  /**
   * Update credential device name
   */
  async updateDeviceName(credentialId, deviceName) {
    const response = await fetch(this.ajaxUrl + "&action=update_name", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
      body: JSON.stringify({
        credential_id: credentialId,
        device_name: deviceName,
        token: prestashop.static_token,
      }),
    });

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.message || "Failed to update device name");
    }

    return data;
  }

  /**
   * Format date
   */
  formatDate(dateString) {
    if (!dateString) return "Never";

    const date = new Date(dateString);
    return date.toLocaleDateString() + " " + date.toLocaleTimeString();
  }

  /**
   * Render credentials list
   */
  renderCredentials(credentials, container) {
    if (!container) return;

    if (credentials.length === 0) {
      container.innerHTML =
        '<p class="alert alert-info">No security keys registered yet.</p>';
      return;
    }

    let html =
      '<div class="table-responsive"><table class="table table-striped">';
    html += "<thead><tr>";
    html += "<th>Device Name</th>";
    html += "<th>Registered</th>";
    html += "<th>Last Used</th>";
    html += "<th>Actions</th>";
    html += "</tr></thead><tbody>";

    credentials.forEach((cred) => {
      html += '<tr data-credential-id="' + cred.id + '">';
      html +=
        '<td class="device-name">' +
        this.escapeHtml(cred.device_name || "Unnamed Device") +
        "</td>";
      html += "<td>" + this.formatDate(cred.created_at) + "</td>";
      html += "<td>" + this.formatDate(cred.last_used_at) + "</td>";
      html += "<td>";
      html +=
        '<button class="btn btn-sm btn-primary edit-btn" data-id="' +
        cred.id +
        '">Rename</button> ';
      html +=
        '<button class="btn btn-sm btn-danger delete-btn" data-id="' +
        cred.id +
        '">Delete</button>';
      html += "</td>";
      html += "</tr>";
    });

    html += "</tbody></table></div>";
    container.innerHTML = html;

    // Attach event listeners
    this.attachEventListeners(container);
  }

  /**
   * Escape HTML
   */
  escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
  }

  /**
   * Attach event listeners to buttons
   */
  attachEventListeners(container) {
    // Delete buttons
    const deleteButtons = container.querySelectorAll(".delete-btn");
    deleteButtons.forEach((btn) => {
      btn.addEventListener("click", async (e) => {
        const credentialId = e.target.getAttribute("data-id");

        if (!confirm("Are you sure you want to delete this security key?")) {
          return;
        }

        e.target.disabled = true;
        e.target.textContent = "Deleting...";

        try {
          await this.deleteCredential(credentialId);
          showStatus("Security key deleted successfully", "success");

          // Reload credentials
          await this.loadAndRender();
        } catch (error) {
          console.error("Delete error:", error);
          showStatus(error.message || "Failed to delete security key", "error");
          e.target.disabled = false;
          e.target.textContent = "Delete";
        }
      });
    });

    // Edit buttons
    const editButtons = container.querySelectorAll(".edit-btn");
    editButtons.forEach((btn) => {
      btn.addEventListener("click", async (e) => {
        const credentialId = e.target.getAttribute("data-id");
        const row = e.target.closest("tr");
        const deviceNameCell = row.querySelector(".device-name");
        const currentName = deviceNameCell.textContent;

        const newName = prompt("Enter new device name:", currentName);

        if (!newName || newName === currentName) {
          return;
        }

        e.target.disabled = true;
        e.target.textContent = "Updating...";

        try {
          await this.updateDeviceName(credentialId, newName);
          showStatus("Device name updated successfully", "success");
          deviceNameCell.textContent = newName;
        } catch (error) {
          console.error("Update error:", error);
          showStatus(error.message || "Failed to update device name", "error");
        } finally {
          e.target.disabled = false;
          e.target.textContent = "Rename";
        }
      });
    });
  }

  /**
   * Load and render credentials
   */
  async loadAndRender() {
    const container = document.getElementById("credentials-list");

    if (!container) {
      console.error("Credentials container not found");
      return;
    }

    try {
      showStatus("Loading security keys...", "info");
      const credentials = await this.loadCredentials();
      this.renderCredentials(credentials, container);

      // Update count
      const countElement = document.getElementById("credentials-count");
      if (countElement) {
        countElement.textContent = credentials.length;
      }

      hideStatus();
    } catch (error) {
      console.error("Load error:", error);
      showStatus(error.message || "Failed to load security keys", "error");
    }
  }
}

// Helper functions
function showStatus(message, type) {
  const statusDiv = document.getElementById("manage-status");
  if (statusDiv) {
    statusDiv.textContent = message;
    statusDiv.className =
      "alert alert-" +
      (type === "error" ? "danger" : type === "success" ? "success" : "info");
    statusDiv.style.display = "block";
  }
}

function hideStatus() {
  const statusDiv = document.getElementById("manage-status");
  if (statusDiv) {
    statusDiv.style.display = "none";
  }
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  const ajaxUrl = document.getElementById("fido2-manage-ajax-url")?.value;

  if (!ajaxUrl) {
    console.error("FIDO2 AJAX URL not found");
    return;
  }

  const manager = new Fido2CredentialManager(ajaxUrl);

  // Load credentials on page load
  manager.loadAndRender();

  // Refresh button
  const refreshBtn = document.getElementById("refresh-btn");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", () => {
      manager.loadAndRender();
    });
  }
});
