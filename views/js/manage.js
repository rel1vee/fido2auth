/**
 * FIDO2 Credential Management UI
 *
 * Client-side logic for the "Manage Security Keys" page.
 * Handles inline key registration (delegates to Fido2Registration),
 * credential deletion with confirmation, and status message display.
 *
 * @author Muh. Zaki Erbai Syas
 * @license AFL-3.0
 */
document.addEventListener("DOMContentLoaded", function () {
  const manageAjaxUrl = document.getElementById("fido2-manage-ajax-url")?.value;
  const regAjaxUrl = document.getElementById("fido2-reg-ajax-url")?.value;
  if (!manageAjaxUrl) return;

  const statusDiv = document.getElementById("manage-status");
  const addKeyBtn = document.getElementById("fido2-add-key-btn");
  const deleteButtons = document.querySelectorAll(".delete-credential");

  // --- Status helpers ---
  function showStatus(message, type) {
    if (!statusDiv) return;

    let icon = '';
    if (type === 'success') icon = 'check_circle';
    else if (type === 'error') icon = 'error_outline';
    else if (type === 'info') icon = 'info_outline';

    statusDiv.innerHTML = icon ? `<i class="material-icons" style="font-size:24px;">${icon}</i> <span>${message}</span>` : message;
    statusDiv.className = "fido2-status " + (type ? "fido2-status-" + type : "") + " show";
    statusDiv.style.display = "flex";

    if (type === "success" || type === "error") {
      setTimeout(() => {
        statusDiv.classList.remove('show');
        // small delay to allow transition out before hiding
        setTimeout(() => statusDiv.style.display = "none", 300);
      }, 4000);
    }
  }

  // --- Inline Registration ---
  if (addKeyBtn && regAjaxUrl) {
    const fido2Reg = new Fido2Registration(regAjaxUrl);
    const deviceNameInput = document.getElementById("fido2-device-name");

    addKeyBtn.addEventListener("click", async function () {
      // Validate device name from user input
      const deviceName = (deviceNameInput?.value || "").trim();
      if (!deviceName) {
        showStatus("Please enter a name for this security key.", "error");
        deviceNameInput?.focus();
        return;
      }

      addKeyBtn.disabled = true;
      if (deviceNameInput) deviceNameInput.disabled = true;
      const originalHtml = addKeyBtn.innerHTML;
      addKeyBtn.innerHTML = '<i class="material-icons rotating" style="font-size:18px;">hourglass_empty</i> Registering...';
      showStatus("Follow the browser setup to add your passkey.", "info");

      try {
        const result = await fido2Reg.register(deviceName);
        showStatus("Success! Key Registered.", "success");
        setTimeout(() => window.location.reload(), 1000);
      } catch (error) {
        showStatus(error.message || "Registration Failed.", "error");
        addKeyBtn.disabled = false;
        if (deviceNameInput) deviceNameInput.disabled = false;
        addKeyBtn.innerHTML = originalHtml;
      }
    });

    // Allow pressing Enter in the input to trigger registration
    if (deviceNameInput) {
      deviceNameInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          addKeyBtn.click();
        }
      });
    }
  }

  // --- Delete Credential ---
  deleteButtons.forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      if (!confirm("Remove this security key? You won't be able to use it to sign in anymore.")) return;

      const id = btn.getAttribute("data-id");
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="material-icons rotating" style="font-size:16px;">autorenew</i>';

      try {
        const separator = manageAjaxUrl.includes("?") ? "&" : "?";
        const response = await fetch(manageAjaxUrl + separator + "ajax=1&action=delete", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({
            credential_id: id,
            token: prestashop.static_token,
          }),
        });

        const data = await response.json();
        if (data.success) {
          showStatus("Key Removed.", "success");
          setTimeout(() => window.location.reload(), 800);
        } else {
          showStatus(data.message || "Error Deleting Key.", "error");
          btn.disabled = false;
          btn.innerHTML = originalHtml;
        }
      } catch (e) {
        console.error(e);
        showStatus("Error Removing Key.", "error");
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
    });
  });
});
