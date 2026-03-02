{extends file='customer/page.tpl'}

{block name='page_title'}
    <h1 class="page-title">{l s='Manage Security Keys' mod='fido2auth'}</h1>
{/block}

{block name='page_content'}
<div class="fido2-container">
    <input type="hidden" id="fido2-manage-ajax-url" value="{$ajax_url}">
    <input type="hidden" id="fido2-reg-ajax-url" value="{$registration_ajax_url}">

    <div class="fido2-section-header">
        <div class="fido2-add-key-row">
            <div class="fido2-device-input-group">
                <input type="text" id="fido2-device-name" class="form-control"
                       placeholder="{l s='Device Name e.g. My Android Phone, Work Laptop, My YubiKey' mod='fido2auth'}"
                       maxlength="64" autocomplete="off">
            </div>
            <button id="fido2-add-key-btn" class="btn btn-primary">
                <i class="material-icons">add</i>
                {l s='Register Key' mod='fido2auth'}
            </button>
        </div>
    </div>

    <div id="manage-status" class="fido2-status"></div>

    {if $credentials}
        <div class="fido2-key-grid">
            {foreach from=$credentials item=credential}
                <div class="fido2-key-card">
                    <div class="fido2-key-icon-wrapper">
                        <i class="material-icons">fingerprint</i>
                    </div>
                    <span class="fido2-key-name">{$credential.device_name|escape:'html':'UTF-8'}</span>
                    <span class="fido2-key-meta">
                        {l s='Added on' mod='fido2auth'} {$credential.created_at|date_format:"%b %e, %Y"}
                    </span>
                    
                    <div class="fido2-key-actions">
                         <button class="btn btn-outline-danger btn-sm delete-credential" data-id="{$credential.id}" title="{l s='Remove Key' mod='fido2auth'}">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">delete_outline</i>
                            {l s='Remove' mod='fido2auth'}
                        </button>
                    </div>
                </div>
            {/foreach}
        </div>
    {else}
        <div class="fido2-empty-state">
            <i class="material-icons">lock_outline</i>
            <p>{l s='No security keys registered yet.' mod='fido2auth'}</p>
            <p class="fido2-empty-hint">{l s='Add a passkey to sign in securely without a password.' mod='fido2auth'}</p>
        </div>
    {/if}
</div>

<script src="{$urls.base_url}modules/fido2auth/views/js/registration.js"></script>
<script src="{$urls.base_url}modules/fido2auth/views/js/manage.js"></script>
{/block}