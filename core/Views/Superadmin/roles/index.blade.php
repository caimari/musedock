@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ $title }}</h2>
      <a href="/musedock/roles/create" class="btn btn-success">Crear nuevo rol</a>
    </div>

    @php
        // Verificar si multitenencia está activada
        $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenantEnabled === null) {
            $multiTenantEnabled = setting('multi_tenant_enabled', false);
        }
    @endphp

    @include('partials.alerts')

    {{-- Roles del CMS --}}
    <div class="card mb-4">
      <div class="card-header bg-dark text-white">
        <strong>Roles del CMS principal</strong>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Descripción</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @php
              $cmsRoles = array_filter($roles, fn($r) => is_null($r['tenant_id']));
            @endphp
            @forelse ($cmsRoles as $role)
              <tr>
                <td>{{ $role['id'] }}</td>
                <td>{{ $role['name'] }}</td>
                <td>{{ $role['description'] }}</td>
                <td>{{ $role['created_at'] ?? '-' }}</td>
                <td class="d-flex gap-2">
                  <a href="/musedock/roles/{{ $role['id'] }}/edit" class="btn btn-sm btn-outline-secondary">Editar permisos</a>
                  <form method="POST" action="/musedock/roles/{{ $role['id'] }}/delete" onsubmit="return confirm('¿Eliminar este rol?')">
                    {!! csrf_field() !!}
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted">No hay roles para el CMS.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Roles de Tenants (Solo visible con multitenencia) --}}
    @if($multiTenantEnabled)
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <strong>Roles de Tenants</strong>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Descripción</th>
              <th>Dominio del Tenant</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            @php
              $tenantRoles = array_filter($roles, fn($r) => $r['tenant_id']);
            @endphp
            @forelse ($tenantRoles as $role)
              <tr>
                <td>{{ $role['id'] }}</td>
                <td>{{ $role['name'] }}</td>
                <td>{{ $role['description'] }}</td>
                <td>
                  @if (!empty($role['tenant_domain']))
                    <a href="https://{{ $role['tenant_domain'] }}" target="_blank">{{ $role['tenant_domain'] }}</a>
                  @else
                    (sin dominio)
                  @endif
                </td>
                <td>{{ $role['created_at'] ?? '-' }}</td>
                <td class="d-flex gap-2">
                  <a href="/musedock/roles/{{ $role['id'] }}/edit" class="btn btn-sm btn-outline-secondary">Editar permisos</a>
                  <form method="POST" action="/musedock/roles/{{ $role['id'] }}/delete" onsubmit="return confirm('¿Eliminar este rol?')">
                    {!! csrf_field() !!}
                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted">No hay roles de tenants.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection
