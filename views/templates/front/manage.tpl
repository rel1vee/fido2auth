{extends file='customer/page.tpl'}

{block name='page_title'}
    <h1 class="page-title">Manage Security Keys</h1>
{/block}

{block name='page_content'}
<div class="fido2-manage-container">
    <input type="hidden" id="fido2-manage-ajax-url" value="{$ajax_url}">
    
    <!-- Status Message -->
    <div id="manage-status" class="alert" style="display:none;"></div>

    <!-- Summary Card -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h4>Your Security Keys</h4>
                    <p class="text-muted">
                        You have <strong id="credentials-count">{$credential_count}</strong> security key(s) registered.
                        {if $require_mfa}
                            <span class="badge badge-warning">MFA Required</span>
                        {/if}
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <a href="{$registration_url}" class="btn btn-primary">
                        <i class="material-icons">add</i>
                        Add New Key
                    </a>
                    <button id="refresh-btn" class="btn btn-secondary">
                        <i class="material-icons">refresh</i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Credentials List -->
    <div class="card">
        <div class="card-header">
            <h3>Registered Security Keys</h3>
        </div>
        <div class="card-body">
            <div id="credentials-list">
                <!-- Will be populated by JavaScript -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2">Loading security keys...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Information Boxes -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h4><i class="material-icons">security</i> Security Tips</h4>
                </div>
                <div class="card-body">
                    <ul>
                        <li>Register multiple security keys as backup</li>
                        <li>Keep your security keys in a safe place</li>
                        <li>Don't share your security keys with anyone</li>
                        <li>Remove security keys you no longer use</li>
                        {if $require_mfa}
                        <li><strong>Note:</strong> You must keep at least one security key registered</li>
                        {/if}
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-warning">
                    <h4><i class="material-icons">warning</i> Lost Your Key?</h4>
                </div>
                <div class="card-body">
                    <p>If you've lost access to your security key:</p>
                    <ol>
                        <li>Sign in using an alternative security key if you have one</li>
                        <li>Remove the lost security key from this page</li>
                        <li>Register a new security key</li>
                    </ol>
                    <p>
                        If you've lost all your security keys and can't sign in, 
                        please <a href="{$urls.pages.contact}">contact support</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-4">
        <a href="{$urls.pages.my_account}" class="btn btn-secondary">
            <i class="material-icons">arrow_back</i>
            Back to My Account
        </a>
    </div>
</div>

{literal}
<script src="{/literal}{$urls.base_url}{literal}modules/fido2auth/views/js/manage.js"></script>
{/literal}
{/block}