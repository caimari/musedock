@extends('layouts.app')

@section('title', 'Cloudflare Accounts')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-cloud-fill"></i> Cloudflare Accounts</h2>
                <p class="text-muted mb-0">Multi-account management for Caddy Domain Manager</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Domain Manager
                </a>
                <button class="btn btn-primary" onclick="openAccountForm()">
                    <i class="bi bi-plus-lg"></i> Add Account
                </button>
            </div>
        </div>

        @if(!empty($flash))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i> {{ $flash }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if($hasEnvFallback && empty($accounts))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Using .env fallback.</strong> Cloudflare tokens are read from environment variables.
            They will be auto-migrated to the database (encrypted) when you first visit this page with valid tokens.
        </div>
        @endif

        {{-- Accounts Table --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-muted">{{ count($accounts) }} account(s) configured</span>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px;">ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Account ID</th>
                            <th>Token</th>
                            <th>SSL</th>
                            <th style="width:80px;">Default</th>
                            <th style="width:80px;">Status</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                        <tr id="account-row-{{ $account['id'] }}">
                            <td><code>{{ $account['id'] }}</code></td>
                            <td><strong>{{ $account['name'] }}</strong></td>
                            <td>
                                @if($account['role'] === 'primary')
                                    <span class="badge bg-primary">Primary</span>
                                @elseif($account['role'] === 'domains')
                                    <span class="badge bg-success">Domains</span>
                                @else
                                    <span class="badge bg-secondary">Read-only</span>
                                @endif
                            </td>
                            <td><code class="small">{{ $account['account_id'] ?: '—' }}</code></td>
                            <td><code class="small">{{ $account['api_token_masked'] }}</code></td>
                            <td><span class="badge bg-info text-dark">{{ $account['ssl_mode'] }}</span></td>
                            <td class="text-center">
                                @if($account['is_default_for_new_domains'])
                                    <i class="bi bi-star-fill text-warning" title="Default for new domains"></i>
                                @else
                                    <button class="btn btn-sm btn-outline-warning" onclick="setDefault({{ $account['id'] }})" title="Set as default">
                                        <i class="bi bi-star"></i>
                                    </button>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($account['enabled'])
                                    <span class="badge bg-success" onclick="toggleEnabled({{ $account['id'] }})" style="cursor:pointer;" title="Click to disable">Active</span>
                                @else
                                    <span class="badge bg-danger" onclick="toggleEnabled({{ $account['id'] }})" style="cursor:pointer;" title="Click to enable">Disabled</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-info" onclick="testConnection({{ $account['id'] }})" title="Test connection">
                                        <i class="bi bi-wifi"></i>
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="openAccountForm({{ json_encode([
                                        'id' => $account['id'],
                                        'name' => $account['name'],
                                        'account_id' => $account['account_id'],
                                        'role' => $account['role'],
                                        'ssl_mode' => $account['ssl_mode'],
                                        'is_default_for_new_domains' => $account['is_default_for_new_domains'],
                                        'enabled' => $account['enabled'],
                                    ]) }})" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deleteAccount({{ $account['id'] }}, '{{ addslashes($account['name']) }}')" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No Cloudflare accounts configured.<br>
                                <small>Add one manually or configure tokens in .env to auto-migrate.</small>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Info card --}}
        <div class="card mt-3">
            <div class="card-body small text-muted">
                <h6 class="card-title"><i class="bi bi-info-circle"></i> How it works</h6>
                <ul class="mb-0">
                    <li><strong>Primary</strong> — Main Cloudflare account (DNS lookups, existing zones). Usually your own domain account.</li>
                    <li><strong>Domains</strong> — Account used to create new zones for custom domains (Full Setup / NS change).</li>
                    <li><strong>Read-only</strong> — Only used for zone lookups, never creates zones.</li>
                    <li><i class="bi bi-star-fill text-warning"></i> <strong>Default</strong> — New domains will be created in this account.</li>
                    <li>Tokens are encrypted with <strong>AES-256-CBC</strong> before storing in the database.</li>
                    <li>When provisioning a domain, <strong>all active accounts</strong> are checked for existing zones before creating a new one.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '/musedock/plugins/caddy-domain-manager/cloudflare-accounts';
const csrfToken = '<?= csrf_token() ?>';

function ajaxPost(url, formData) {
    if (formData instanceof FormData) {
        formData.append('_csrf_token', csrfToken);
    }
    return fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    });
}

function showToast(msg, type = 'success') {
    Swal.fire({ toast: true, position: 'top-end', icon: type, title: msg, showConfirmButton: false, timer: 3000 });
}

// ============================================
// ADD / EDIT ACCOUNT (SweetAlert2)
// ============================================
function openAccountForm(account = null) {
    const isEdit = !!account;
    const title = isEdit ? `<i class="bi bi-pencil"></i> Edit: ${account.name}` : '<i class="bi bi-plus-lg"></i> Add Cloudflare Account';

    Swal.fire({
        title: title,
        width: 600,
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="swal-name" value="${isEdit ? account.name : ''}" placeholder="e.g. Primary, Custom Domains, Client X">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">API Token <span class="text-danger">${isEdit ? '' : '*'}</span></label>
                    <input type="password" class="form-control" id="swal-token" placeholder="${isEdit ? 'Leave empty to keep current token' : 'Cloudflare API Token'}">
                    <div class="form-text">Encrypted with AES-256-CBC before storing.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Account ID</label>
                    <input type="text" class="form-control" id="swal-account-id" value="${isEdit ? (account.account_id || '') : ''}" placeholder="Cloudflare Account ID">
                    <div class="form-text">Found in Cloudflare dashboard &rarr; Overview &rarr; right sidebar.</div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-bold">Role</label>
                        <select class="form-select" id="swal-role">
                            <option value="primary" ${isEdit && account.role === 'primary' ? 'selected' : ''}>Primary</option>
                            <option value="domains" ${!isEdit || account.role === 'domains' ? 'selected' : ''}>Domains</option>
                            <option value="readonly" ${isEdit && account.role === 'readonly' ? 'selected' : ''}>Read-only</option>
                        </select>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-bold">SSL Mode</label>
                        <select class="form-select" id="swal-ssl">
                            <option value="full" ${isEdit && account.ssl_mode === 'full' ? 'selected' : ''}>Full</option>
                            <option value="strict" ${isEdit && account.ssl_mode === 'strict' ? 'selected' : ''}>Full (Strict)</option>
                            <option value="flexible" ${isEdit && account.ssl_mode === 'flexible' ? 'selected' : ''}>Flexible</option>
                            <option value="off" ${isEdit && account.ssl_mode === 'off' ? 'selected' : ''}>Off</option>
                        </select>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="swal-default" ${isEdit && parseInt(account.is_default_for_new_domains) ? 'checked' : ''}>
                    <label class="form-check-label" for="swal-default">Default for new domains</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="swal-enabled" ${!isEdit || parseInt(account.enabled) ? 'checked' : ''}>
                    <label class="form-check-label" for="swal-enabled">Enabled</label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg"></i> Save',
        cancelButtonText: 'Cancel',
        showDenyButton: true,
        denyButtonText: '<i class="bi bi-wifi"></i> Test Token',
        denyButtonColor: '#17a2b8',
        focusConfirm: false,
        returnFocus: false,
        preDeny: () => {
            // Test token without closing
            const token = document.getElementById('swal-token').value;
            const formData = new FormData();
            if (token) {
                formData.append('api_token', token);
            } else if (isEdit) {
                formData.append('account_id_db', account.id);
            } else {
                showToast('Enter an API token first', 'warning');
                return false;
            }
            ajaxPost(`${BASE_URL}/test-connection`, formData)
                .then(data => {
                    if (data.success) {
                        showToast('Token valid — Status: ' + (data.status || 'active'), 'success');
                    } else {
                        showToast('Token invalid: ' + data.error, 'error');
                    }
                })
                .catch(err => showToast('Error: ' + err.message, 'error'));
            return false; // Don't close
        },
        preConfirm: () => {
            const name = document.getElementById('swal-name').value.trim();
            const token = document.getElementById('swal-token').value.trim();

            if (!name) {
                Swal.showValidationMessage('Name is required');
                return false;
            }
            if (!isEdit && !token) {
                Swal.showValidationMessage('API Token is required for new accounts');
                return false;
            }

            return {
                name: name,
                api_token: token,
                account_id: document.getElementById('swal-account-id').value.trim(),
                role: document.getElementById('swal-role').value,
                ssl_mode: document.getElementById('swal-ssl').value,
                is_default_for_new_domains: document.getElementById('swal-default').checked ? '1' : '',
                enabled: document.getElementById('swal-enabled').checked ? '1' : '',
            };
        }
    }).then(result => {
        if (!result.isConfirmed) return;

        const formData = new FormData();
        for (const [key, val] of Object.entries(result.value)) {
            formData.append(key, val);
        }

        const url = isEdit ? `${BASE_URL}/${account.id}/update` : BASE_URL;

        ajaxPost(url, formData)
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'error'));
    });
}

// ============================================
// DELETE
// ============================================
function deleteAccount(id, name) {
    Swal.fire({
        title: 'Delete account?',
        html: `Delete <strong>${name}</strong>? This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: '<i class="bi bi-trash"></i> Delete',
    }).then(result => {
        if (!result.isConfirmed) return;
        const formData = new FormData();
        ajaxPost(`${BASE_URL}/${id}/delete`, formData)
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    document.getElementById('account-row-' + id)?.remove();
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'error'));
    });
}

// ============================================
// TEST CONNECTION
// ============================================
function testConnection(accountDbId) {
    const formData = new FormData();
    formData.append('account_id_db', accountDbId);

    ajaxPost(`${BASE_URL}/test-connection`, formData)
        .then(data => {
            if (data.success) {
                showToast('Connection OK — Status: ' + (data.status || 'active'), 'success');
            } else {
                showToast('Connection failed: ' + data.error, 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
}

// ============================================
// TOGGLE ENABLED
// ============================================
function toggleEnabled(id) {
    const formData = new FormData();
    ajaxPost(`${BASE_URL}/${id}/toggle`, formData)
        .then(data => {
            if (data.success) {
                showToast(data.message);
                setTimeout(() => location.reload(), 500);
            } else {
                showToast(data.error, 'error');
            }
        })
        .catch(err => showToast('Error: ' + err.message, 'error'));
}

// ============================================
// SET DEFAULT
// ============================================
function setDefault(id) {
    Swal.fire({
        title: 'Change default account?',
        text: 'New domains will be created in this account from now on.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-star-fill"></i> Set as default',
        confirmButtonColor: '#e0a800',
    }).then(result => {
        if (!result.isConfirmed) return;
        const formData = new FormData();
        ajaxPost(`${BASE_URL}/${id}/set-default`, formData)
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 500);
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(err => showToast('Error: ' + err.message, 'error'));
    });
}
</script>
@endsection
