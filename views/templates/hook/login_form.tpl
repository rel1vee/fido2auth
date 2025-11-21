{* login_form.tpl - Mengubah tampilan login default menjadi FIDO2-First *}

<div id="fido2-primary-login-container" class="fido2-login-wrapper">
    <div class="fido2-login-card text-center">
        <div class="fido2-icon mb-3">
            <i class="material-icons" style="font-size: 48px; color: #2fb5d2;">fingerprint</i>
        </div>
        
        <h3 class="h4 mb-3">{l s='Masuk Cepat & Aman' mod='fido2auth'}</h3>
        
        <button id="btn-fido2-primary-login" class="btn btn-primary btn-lg btn-block mb-3">
            {l s='Masuk dengan Kunci Keamanan (FIDO2)' mod='fido2auth'}
        </button>

        <div id="fido2-status-msg" class="alert" style="display:none; margin-top:10px;"></div>

        <div class="separator text-muted my-3">{l s='ATAU' mod='fido2auth'}</div>

        <button id="btn-show-password-form" class="btn btn-outline-secondary btn-sm">
            {l s='Gunakan Password Biasa' mod='fido2auth'}
        </button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Logic untuk memanipulasi DOM Login Form Bawaan PrestaShop
    const loginForm = document.querySelector('#login-form');
    const fidoContainer = document.getElementById('fido2-primary-login-container');
    const showPassBtn = document.getElementById('btn-show-password-form');
    
    if (loginForm && fidoContainer) {
        // 1. Sembunyikan form login bawaan secara default
        loginForm.style.display = 'none';
        
        // 2. Masukkan UI FIDO2 kita SEBELUM form login bawaan
        loginForm.parentNode.insertBefore(fidoContainer, loginForm);

        // 3. Logic tombol "Gunakan Password Biasa"
        showPassBtn.addEventListener('click', function(e) {
            e.preventDefault();
            loginForm.style.display = 'block';
            fidoContainer.style.display = 'none';
        });

        // 4. Logic tombol "Masuk dengan FIDO2" - Redirect ke Controller Khusus
        document.getElementById('btn-fido2-primary-login').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = "{$fido2_auth_url}";
        });
    }
});
</script>

<style>
    .fido2-login-wrapper {
        background: #fff;
        padding: 2rem;
        border: 1px solid #e5e5e5;
        border-radius: 5px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .separator {
        display: flex;
        align-items: center;
        text-align: center;
    }
    .separator::before, .separator::after {
        content: '';
        flex: 1;
        border-bottom: 1px solid #e5e5e5;
    }
    .separator:not(:empty)::before {
        margin-right: .25em;
    }
    .separator:not(:empty)::after {
        margin-left: .25em;
    }
</style>