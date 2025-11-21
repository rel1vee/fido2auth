{extends file='page.tpl'}

{block name='page_title'}
    <h1 class="page-title">
        {if $is_mfa_mode}
            {l s='Verifikasi Keamanan' mod='fido2auth'}
        {else}
            {l s='Masuk dengan Security Key' mod='fido2auth'}
        {/if}
    </h1>
{/block}

{block name='page_content'}
<div class="fido2-authentication-container">
    <input type="hidden" id="fido2-auth-ajax-url" value="{$ajax_url}">
    <div id="auth-status" class="alert" style="display:none;"></div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4 mt-3">
                        <i class="material-icons" style="font-size: 64px; color: #2fb5d2;">fingerprint</i>
                    </div>

                    {if $is_mfa_mode}
                        <div class="alert alert-warning">
                            {l s='Akun Anda dilindungi. Silakan verifikasi identitas Anda.' mod='fido2auth'}
                        </div>
                        <p class="text-muted">
                            {l s='Sentuh Kunci Keamanan Anda untuk melanjutkan.' mod='fido2auth'}
                        </p>
                    {else}
                        <p class="text-muted mb-4">
                            {l s='Masuk tanpa kata sandi menggunakan Kunci Keamanan.' mod='fido2auth'}
                        </p>
                        <div class="form-group text-left">
                            <label>{l s='Email (Opsional)' mod='fido2auth'}</label>
                            <input type="email" id="email" class="form-control" placeholder="nama@email.com" autocomplete="username webauthn">
                        </div>
                    {/if}

                    <button id="fido2-auth-btn" class="btn btn-primary btn-lg btn-block mt-3">
                        {if $is_mfa_mode}
                            {l s='Verifikasi Identitas' mod='fido2auth'}
                        {else}
                            {l s='Masuk' mod='fido2auth'}
                        {/if}
                    </button>
                </div>
            </div>
            
            {if !$is_mfa_mode}
            <div class="text-center mt-3">
                <a href="{$link->getPageLink('authentication')}" class="btn btn-link">
                    {l s='Kembali ke Login Password' mod='fido2auth'}
                </a>
            </div>
            {/if}
        </div>
    </div>
</div>

<script src="{$urls.base_url}modules/fido2auth/views/js/authentication.js"></script>
{/block}