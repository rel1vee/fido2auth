{extends file='customer/page.tpl'}

{block name='page_title'}
    <h1 class="page-title">Register Security Key</h1>
{/block}

{block name='page_content'}
<div class="fido2-registration-container">
    <input type="hidden" id="fido2-ajax-url" value="{$ajax_url}">
    
    <!-- Browser Support Check -->
    <div id="webauthn-not-supported" class="alert alert-warning" style="display:none;">
        <strong>WebAuthn Not Supported:</strong> Your browser does not support WebAuthn. Please use a modern browser like Chrome, Firefox, Safari, or Edge.
    </div>

    <!-- Status Message -->
    <div id="registration-status" class="alert" style="display:none;"></div>

    <!-- Registration Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Add New Security Key</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Register a new security key (like YubiKey) or use your device's biometric authentication (Windows Hello, Touch ID, Face ID).
            </p>
            
            <div class="form-group">
                <label for="device-name">Device Name <span class="text-danger">*</span></label>
                <input type="text" 
                       id="device-name" 
                       class="form-control" 
                       placeholder="e.g., My YubiKey, MacBook Touch ID"
                       maxlength="255"
                       required>
                <small class="form-text text-muted">
                    Give your security key a name to identify it later.
                </small>
            </div>

            <button id="fido2-register-btn" class="btn btn-primary btn-lg">
                <i class="material-icons">fingerprint</i>
                Register Security Key
            </button>
        </div>
    </div>

    <!-- Existing Keys -->
    {if $credentials|count > 0}
    <div class="card">
        <div class="card-header">
            <h3>Your Registered Security Keys</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Device Name</th>
                            <th>Registered</th>
                            <th>Last Used</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$credentials item=credential}
                        <tr>
                            <td>{$credential.device_name|default:'Unnamed Device'|escape:'html'}</td>
                            <td>{$credential.created_at}</td>
                            <td>{if $credential.last_used_at}{$credential.last_used_at}{else}<em>Never</em>{/if}</td>
                            <td>
                                {if $credential.is_active}
                                    <span class="badge badge-success">Active</span>
                                {else}
                                    <span class="badge badge-secondary">Inactive</span>
                                {/if}
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            
            <a href="{$urls.pages.my_account}" class="btn btn-secondary mt-3">
                <i class="material-icons">arrow_back</i>
                Back to My Account
            </a>
        </div>
    </div>
    {/if}

    <!-- Information Box -->
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h4><i class="material-icons">info</i> About Security Keys</h4>
        </div>
        <div class="card-body">
            <p><strong>What is a security key?</strong></p>
            <p>A security key is a physical device or your device's built-in biometric sensor that provides strong, phishing-resistant authentication.</p>
            
            <p><strong>Supported authenticators:</strong></p>
            <ul>
                <li>Hardware security keys (YubiKey, Google Titan Key, etc.)</li>
                <li>Windows Hello (face recognition, fingerprint, PIN)</li>
                <li>macOS Touch ID / Face ID</li>
                <li>Android fingerprint / face unlock</li>
                <li>iOS Face ID / Touch ID</li>
            </ul>

            <p><strong>Why use security keys?</strong></p>
            <ul>
                <li><strong>Phishing-resistant:</strong> Cannot be tricked by fake websites</li>
                <li><strong>No passwords to remember:</strong> Passwordless authentication</li>
                <li><strong>Fast and convenient:</strong> One-touch authentication</li>
                <li><strong>Privacy-preserving:</strong> No data shared across websites</li>
            </ul>
        </div>
    </div>
</div>

{literal}
<script src="{/literal}{$urls.base_url}{literal}modules/fido2auth/views/js/registration.js"></script>
{/literal}
{/block}