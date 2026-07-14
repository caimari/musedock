@extends('layouts.app')

@section('title', 'Nodos CMS')

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-0"><i class="bi bi-hdd-network"></i> Nodos CMS</h2>
                <small class="text-muted">Orquestacion de nodos para tenants gestionados desde este master</small>
            </div>
            <div class="d-flex gap-2">
                <a href="/musedock/tenants/create" class="btn btn-outline-secondary">
                    <i class="bi bi-plus-circle"></i> Nuevo tenant
                </a>
                <button class="btn btn-primary" id="btnCreateNode">
                    <i class="bi bi-plus-lg"></i> Nuevo nodo
                </button>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>API URL</th>
                            <th>Carga</th>
                            <th>Flags</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse(($nodes ?? []) as $node)
                        @php
                            $statusClass = match($node['status']) {
                                'active' => 'bg-success',
                                'maintenance' => 'bg-warning text-dark',
                                'offline' => 'bg-danger',
                                'full' => 'bg-secondary',
                                default => 'bg-secondary'
                            };
                        @endphp
                        <tr
                            data-node='@json($node)'
                            data-node-id="{{ $node['id'] }}"
                        >
                            <td class="text-muted">{{ $node['id'] }}</td>
                            <td class="fw-semibold">{{ $node['name'] }} <small class="text-muted">({{ $node['slug'] }})</small></td>
                            <td><span class="badge bg-light text-dark">{{ $node['type'] }}</span></td>
                            <td><span class="badge {{ $statusClass }}">{{ $node['status'] }}</span></td>
                            <td class="text-muted">{{ $node['api_url'] ?: '-' }}</td>
                            <td>{{ (int)($node['tenants_count'] ?? 0) }} / {{ (int)($node['max_accounts'] ?? 0) }}</td>
                            <td>
                                @if((int)($node['is_local'] ?? 0) === 1)
                                    <span class="badge bg-info text-dark">local</span>
                                @endif
                                @if((int)($node['is_default'] ?? 0) === 1)
                                    <span class="badge bg-primary">default</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary btnHealth" title="Health">
                                        <i class="bi bi-heart-pulse"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary btnEdit" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger btnDelete" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center p-4 text-muted">No hay nodos configurados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form id="nodeCreateForm" method="POST" action="/musedock/nodes/store" class="d-none">
            {!! csrf_field() !!}
            <input type="hidden" name="name">
            <input type="hidden" name="slug">
            <input type="hidden" name="type">
            <input type="hidden" name="status">
            <input type="hidden" name="api_url">
            <input type="hidden" name="api_key">
            <input type="hidden" name="ip_address">
            <input type="hidden" name="location">
            <input type="hidden" name="max_accounts">
            <input type="hidden" name="is_local">
            <input type="hidden" name="is_default">
        </form>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const csrfToken = document.querySelector('input[name="_csrf"]')?.value || '';
    const setFormValues = (form, data) => {
        Object.entries(data).forEach(([key, value]) => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) input.value = value ?? '';
        });
    };

    const buildNodeFormHtml = (node = {}) => `
        <div class="text-start">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Nombre</label>
                    <input id="sw-name" class="form-control" value="${node.name || ''}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Slug</label>
                    <input id="sw-slug" class="form-control" value="${node.slug || ''}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tipo</label>
                    <select id="sw-type" class="form-select">
                        <option value="cms">cms</option>
                        <option value="hybrid">hybrid</option>
                        <option value="panel">panel</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estado</label>
                    <select id="sw-status" class="form-select">
                        <option value="active">active</option>
                        <option value="maintenance">maintenance</option>
                        <option value="offline">offline</option>
                        <option value="full">full</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">API URL</label>
                    <input id="sw-api-url" class="form-control" value="${node.api_url || ''}" placeholder="https://nodo2.tld">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max cuentas</label>
                    <input id="sw-max" type="number" min="1" class="form-control" value="${node.max_accounts || 100}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">IP</label>
                    <input id="sw-ip" class="form-control" value="${node.ip_address || ''}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ubicacion</label>
                    <input id="sw-location" class="form-control" value="${node.location || ''}">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Token API ${node.id ? '(dejar vacio para mantener)' : ''}</label>
                    <input id="sw-api-key" type="password" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-md-6 form-check mt-2 ms-1">
                    <input id="sw-local" type="checkbox" class="form-check-input" ${Number(node.is_local || 0) === 1 ? 'checked' : ''}>
                    <label class="form-check-label" for="sw-local">Nodo local</label>
                </div>
                <div class="col-md-6 form-check mt-2 ms-1">
                    <input id="sw-default" type="checkbox" class="form-check-input" ${Number(node.is_default || 0) === 1 ? 'checked' : ''}>
                    <label class="form-check-label" for="sw-default">Nodo default</label>
                </div>
            </div>
        </div>
    `;

    const readNodeForm = () => ({
        name: document.getElementById('sw-name')?.value?.trim() || '',
        slug: document.getElementById('sw-slug')?.value?.trim() || '',
        type: document.getElementById('sw-type')?.value || 'cms',
        status: document.getElementById('sw-status')?.value || 'active',
        api_url: document.getElementById('sw-api-url')?.value?.trim() || '',
        api_key: document.getElementById('sw-api-key')?.value || '',
        ip_address: document.getElementById('sw-ip')?.value?.trim() || '',
        location: document.getElementById('sw-location')?.value?.trim() || '',
        max_accounts: document.getElementById('sw-max')?.value || '100',
        is_local: document.getElementById('sw-local')?.checked ? '1' : '',
        is_default: document.getElementById('sw-default')?.checked ? '1' : '',
    });

    const openCreateModal = async () => {
        const result = await Swal.fire({
            title: 'Nuevo nodo',
            html: buildNodeFormHtml(),
            width: 760,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const data = readNodeForm();
                if (!data.name || !data.slug) {
                    Swal.showValidationMessage('Nombre y slug son obligatorios');
                    return false;
                }
                return data;
            }
        });
        if (!result.isConfirmed || !result.value) return;

        const form = document.getElementById('nodeCreateForm');
        setFormValues(form, result.value);
        form.submit();
    };

    const openEditModal = async (node) => {
        const result = await Swal.fire({
            title: `Editar nodo #${node.id}`,
            html: buildNodeFormHtml(node),
            width: 760,
            showCancelButton: true,
            confirmButtonText: 'Guardar cambios',
            cancelButtonText: 'Cancelar',
            didOpen: () => {
                document.getElementById('sw-type').value = node.type || 'cms';
                document.getElementById('sw-status').value = node.status || 'active';
            },
            preConfirm: () => {
                const data = readNodeForm();
                if (!data.name) {
                    Swal.showValidationMessage('El nombre es obligatorio');
                    return false;
                }
                return data;
            }
        });
        if (!result.isConfirmed || !result.value) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/musedock/nodes/${node.id}/update`;
        form.classList.add('d-none');
        form.innerHTML = `<input type="hidden" name="_csrf" value="${csrfToken}">`;
        document.body.appendChild(form);
        Object.entries(result.value).forEach(([k, v]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = v ?? '';
            form.appendChild(input);
        });
        form.submit();
    };

    const openDeleteModal = async (node) => {
        const result = await Swal.fire({
            title: 'Eliminar nodo',
            text: `Se eliminara ${node.name} (${node.slug}).`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/musedock/nodes/${node.id}/delete`;
        form.classList.add('d-none');
        form.innerHTML = `<input type="hidden" name="_csrf" value="${csrfToken}">`;
        document.body.appendChild(form);
        form.submit();
    };

    const runHealth = async (node) => {
        try {
            const response = await fetch(`/musedock/nodes/${node.id}/health`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Nodo OK',
                    text: node.is_local ? 'Nodo local operativo' : (data.service || 'Health check correcto')
                });
            } else {
                await Swal.fire({
                    icon: 'error',
                    title: 'Nodo no disponible',
                    text: data.error || 'Health check fallido'
                });
            }
        } catch (e) {
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo consultar el estado del nodo'
            });
        }
    };

    document.getElementById('btnCreateNode')?.addEventListener('click', openCreateModal);

    document.querySelectorAll('tr[data-node]').forEach((row) => {
        const node = JSON.parse(row.getAttribute('data-node'));
        row.querySelector('.btnEdit')?.addEventListener('click', () => openEditModal(node));
        row.querySelector('.btnDelete')?.addEventListener('click', () => openDeleteModal(node));
        row.querySelector('.btnHealth')?.addEventListener('click', () => runHealth(node));
    });
})();
</script>
@endpush
@endsection
