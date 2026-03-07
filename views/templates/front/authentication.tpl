{extends file='page.tpl'}

{block name='page_title'}
    <h1 class="page-title">
        {l s='Security Verification' mod='fido2auth'}
    </h1>
{/block}

{block name='page_content'}
<div class="fido2-container">
    <input type="hidden" id="fido2-auth-ajax-url" value="{$ajax_url}">
    <div class="fido2-card">
        <div id="auth-status" class="fido2-status" style="display: none;"></div>
        <i class="material-icons fido2-icon">fingerprint</i>
        <p class="fido2-text">
            {l s='Your account is protected with a passkey. Verify your identity to continue.' mod='fido2auth'}
        </p>
        <button id="fido2-auth-btn" class="btn btn-primary btn-lg fido2-btn-block">
            <i class="material-icons" style="vertical-align: middle; margin-right: 6px; font-size: 20px;">verified_user</i>
            {l s='Verify Identity' mod='fido2auth'}
        </button>
    </div>
</div>

<script src="{$urls.base_url}modules/fido2auth/views/js/authentication.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ajaxUrl = document.getElementById("fido2-auth-ajax-url")?.value;
    const authBtn = document.getElementById("fido2-auth-btn");
    const statusDiv = document.getElementById("auth-status");
    
    // Auto-trigger WebAuthn prompt when in MFA verification mode
    const isMfa = {if $is_mfa_mode}true{else}false{/if};

    if (!ajaxUrl || !authBtn) return;

    const fidoAuth = new Fido2Authentication(ajaxUrl);

    async function doAuth() {
        authBtn.disabled = true;
        authBtn.innerHTML = '<i class="material-icons rotating" style="font-size: 20px;">hourglass_empty</i> ' + "{l s='Verifying...' mod='fido2auth'}";
        if (statusDiv) statusDiv.style.display = 'none';

        try {
            const result = await fidoAuth.authenticate();

            if (statusDiv) {
                statusDiv.innerHTML = '<i class="material-icons">check_circle</i> Success! Redirecting...';
                statusDiv.className = 'fido2-status fido2-status-success show';
                statusDiv.style.display = 'flex';
            }

            if (result.redirect) window.location.href = result.redirect;
        } catch (error) {
            if (statusDiv) {
                let msg = error.message || 'Verification failed.';
                statusDiv.innerHTML = '<i class="material-icons">error</i> <span>' + msg + '</span>';
                statusDiv.className = 'fido2-status fido2-status-error show';
                statusDiv.style.display = 'flex';
                
                setTimeout(() => { 
                    statusDiv.classList.remove('show');
                    setTimeout(() => statusDiv.style.display = "none", 300);
                }, 4000);
            }
            authBtn.disabled = false;
            authBtn.innerHTML = '<i class="material-icons" style="vertical-align: middle; margin-right: 6px; font-size: 20px;">verified_user</i> ' + "{l s='Verify Identity' mod='fido2auth'}";
        }
    }

    authBtn.addEventListener("click", doAuth);

    if (isMfa) {
        setTimeout(doAuth, 500);
    }
});
</script>
{/block}