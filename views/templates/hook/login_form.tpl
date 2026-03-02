{* login_form.tpl - FIDO2 Inline Passkey Login Button (injected into default login form) *}

<div id="fido2-inline-login-wrapper">
    <input type="hidden" id="fido2-login-ajax-url" value="{$fido2_auth_ajax_url}">
    
    <div style="font-style: italic; color: #6c757d; padding: 10px;">
        <span>{l s='— or continue with —' mod='fido2auth'}</span>
    </div>

    <button id="btn-fido2-inline-login" class="btn btn-secondary" type="button">
        <i class="material-icons">fingerprint</i>
        {l s='Passkey' mod='fido2auth'}
    </button>
    
    <div id="fido2-login-status" style="padding-top: 10px;"></div>
</div>

<script src="{$urls.base_url}modules/fido2auth/views/js/authentication.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const loginForm = document.querySelector('#login-form');
    const fidoWrapper = document.getElementById('fido2-inline-login-wrapper');
    const fidoBtn = document.getElementById('btn-fido2-inline-login');
    const statusDiv = document.getElementById('fido2-login-status');
    const ajaxUrl = document.getElementById('fido2-login-ajax-url')?.value;

    if (!loginForm || !fidoWrapper || !ajaxUrl) return;

    // Move the FIDO2 button into the login form footer
    const formFooter = loginForm.querySelector('.form-footer');
    if (formFooter) {
        fidoWrapper.style.display = 'block';
        formFooter.appendChild(fidoWrapper);
    }

    if (fidoBtn) {
        const fidoAuth = new Fido2Authentication(ajaxUrl);

        fidoBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            
            fidoBtn.disabled = true;
            const originalHtml = fidoBtn.innerHTML;
            fidoBtn.innerHTML = "{l s='Verifying...' mod='fido2auth'}";

            try {
                const result = await fidoAuth.authenticate();

                if (statusDiv) {
                    statusDiv.innerHTML = 'Success...';
                    statusDiv.className = 'show';
                    statusDiv.style.display = 'flex';
                    statusDiv.style.alignItems = 'center'; 
                    statusDiv.style.justifyContent = 'center'; 
                    statusDiv.style.textAlign = 'center'; 
                }

                if (result.redirect) {
                    window.location.href = result.redirect;
                }
            } catch (error) {
                let friendlyMessage = error.message || 'Authentication Failed';
                
                if (error.name === 'NotAllowedError' || error.message.includes('cancel')) {
                    friendlyMessage = "{l s='Action Cancelled...' mod='fido2auth'}";
                } else if (error.name === 'TimeoutError') {
                    friendlyMessage = "{l s='Timed Out...' mod='fido2auth'}";
                }

                if (statusDiv) {
                    statusDiv.innerHTML = friendlyMessage;
                    statusDiv.className = 'show';
                    statusDiv.style.display = 'flex';
                    statusDiv.style.alignItems = 'center'; 
                    statusDiv.style.justifyContent = 'center'; 
                    statusDiv.style.textAlign = 'center'; 
                    
                    setTimeout(() => { 
                        statusDiv.classList.remove('show');
                        setTimeout(() => statusDiv.style.display = "none", 300);
                    }, 4000);
                }

                fidoBtn.disabled = false;
                fidoBtn.innerHTML = originalHtml;
            }
        });
    }
});
</script>
