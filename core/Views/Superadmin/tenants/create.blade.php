@extends('layouts.app')

@section('title', __('tenants.create'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('tenants.create') }}</h3>
                </div>

                <form id="tenantForm" action="/musedock/tenants/store" method="POST">
                    {!! csrf_field() !!}
                    <div class="card-body">

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('tenants.name') }}</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}">
                            {!! form_error('name') !!}
                        </div>

                        <div class="mb-3">
                            <label for="domain" class="form-label">{{ __('tenants.domain') }}</label>
                            <input type="text" class="form-control" id="domain" name="domain" value="{{ old('domain') }}">
                            {!! form_error('domain') !!}
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('tenants.status') }}</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active">{{ __('tenants.active') }}</option>
                                <option value="inactive">{{ __('tenants.inactive') }}</option>
                            </select>
                        </div>

                        {{-- Campos del admin que se rellenan en el modal --}}
                        <input type="hidden" name="admin_name" id="admin_name">
                        <input type="hidden" name="admin_email" id="admin_email">
                        <input type="hidden" name="admin_password" id="admin_password">
                    </div>

                    <div class="card-footer text-end">
                        <button type="button" class="btn btn-primary" id="openAdminSwal">
                            {{ __('common.save') }}
                        </button>
                        <a href="/musedock/tenants" class="btn btn-secondary">{{ __('common.cancel') }}</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const openButton = document.getElementById('openAdminSwal');
    const form = document.getElementById('tenantForm');

    const setHidden = (id, value) => {
        const input = document.getElementById(id);
        if (input) input.value = value ?? '';
    };

    const getHidden = (id) => {
        const input = document.getElementById(id);
        return input ? (input.value ?? '') : '';
    };

    const isValidEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());

    const strings = {
        title: {!! json_encode(__('tenants.admin_user')) !!},
        name: {!! json_encode(__('users.name')) !!},
        email: {!! json_encode(__('auth.email')) !!},
        password: {!! json_encode(__('auth.password')) !!},
        cancel: {!! json_encode(__('common.cancel')) !!},
        create: {!! json_encode(__('tenants.create')) !!},
        required: {!! json_encode(__('common.required') ?? 'Campo obligatorio') !!},
        invalidEmail: {!! json_encode(__('auth.invalid_email') ?? 'Email no válido') !!},
    };

    const openDialog = async () => {
        if (typeof Swal === 'undefined') {
            const adminName = window.prompt(strings.name, getHidden('admin_name'));
            const adminEmail = window.prompt(strings.email, getHidden('admin_email'));
            const adminPassword = window.prompt(strings.password, '');

            if (!adminName || !adminEmail || !adminPassword) return;
            if (!isValidEmail(adminEmail)) return;

            setHidden('admin_name', adminName);
            setHidden('admin_email', adminEmail);
            setHidden('admin_password', adminPassword);
            form.submit();
            return;
        }

        const result = await Swal.fire({
            title: strings.title,
            html: `
                <div class="text-start">
                    <label for="swal-admin-name" class="form-label fw-semibold">${strings.name}</label>
                    <input id="swal-admin-name" class="form-control mb-3" type="text" autocomplete="name" />

                    <label for="swal-admin-email" class="form-label fw-semibold">${strings.email}</label>
                    <input id="swal-admin-email" class="form-control mb-3" type="email" autocomplete="email" />

                    <label for="swal-admin-password" class="form-label fw-semibold">${strings.password}</label>
                    <input id="swal-admin-password" class="form-control" type="password" autocomplete="new-password" />
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: strings.create,
            cancelButtonText: strings.cancel,
            preConfirm: () => {
                const adminName = document.getElementById('swal-admin-name')?.value?.trim() || '';
                const adminEmail = document.getElementById('swal-admin-email')?.value?.trim() || '';
                const adminPassword = document.getElementById('swal-admin-password')?.value || '';

                if (!adminName || !adminEmail || !adminPassword) {
                    Swal.showValidationMessage(strings.required);
                    return false;
                }

                if (!isValidEmail(adminEmail)) {
                    Swal.showValidationMessage(strings.invalidEmail);
                    return false;
                }

                return { adminName, adminEmail, adminPassword };
            },
            didOpen: () => {
                const name = document.getElementById('swal-admin-name');
                const email = document.getElementById('swal-admin-email');
                const password = document.getElementById('swal-admin-password');

                if (name) name.value = getHidden('admin_name');
                if (email) email.value = getHidden('admin_email');
                if (password) password.value = '';

                if (name) name.focus();
            }
        });

        if (!result.isConfirmed || !result.value) return;

        setHidden('admin_name', result.value.adminName);
        setHidden('admin_email', result.value.adminEmail);
        setHidden('admin_password', result.value.adminPassword);

        form.submit();
    };

    if (openButton) {
        openButton.addEventListener('click', (e) => {
            e.preventDefault();
            openDialog();
        });
    }
})();
</script>
@endpush

@endsection
