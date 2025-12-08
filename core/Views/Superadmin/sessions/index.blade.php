@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Sesiones activas</h3>
  </div>
  <div class="card-body">
    @php
        // Verificar si multitenencia está activada
        $multiTenantEnabled = \Screenart\Musedock\Env::get('MULTI_TENANT_ENABLED', null);
        if ($multiTenantEnabled === null) {
            $multiTenantEnabled = setting('multi_tenant_enabled', false);
        }
    @endphp

    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info">
      <h5><i class="fas fa-info-circle"></i> Gestión de sesiones</h5>
      <p><strong>Sesiones recordadas:</strong> Son sesiones persistentes que se mantienen activas durante 30 días. Al eliminarlas con el botón "Cerrar", el usuario podrá seguir navegando con su sesión actual, pero deberá iniciar sesión manualmente cuando ésta expire.</p>
      <p><strong>Usuarios activos:</strong> Son usuarios que tienen una actividad reciente en el sistema. Esta sección es solamente informativa y muestra la última vez que cada usuario interactuó con el sistema.</p>
      <p><strong>Nota:</strong> Para desconectar a un usuario, utilice el botón "Cerrar" en la sección de sesiones recordadas.</p>
    </div>

    {{-- PERSISTENTES - SUPERADMINS --}}
    <h4 class="mt-4">Superadmins - Recordadas</h4>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Email</th>
          <th>IP</th>
          <th>User Agent</th>
          <th>Desde</th>
          <th>Hasta</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        @forelse($superadmin_sessions as $s)
          <tr>
            <td>{{ $s['email'] }}</td>
            <td>{{ $s['ip'] }}</td>
            <td>{{ $s['user_agent'] }}</td>
            <td>{{ $s['created_at'] }}</td>
            <td>{{ $s['expires_at'] }}</td>
            <td>
              <form method="POST" action="/musedock/sessions/{{ $s['id'] }}/delete">
                {!! csrf_field() !!}
                <input type="hidden" name="user_type" value="superadmin">
                <input type="hidden" name="source" value="token">
                <input type="hidden" name="user_id" value="{{ $s['user_id'] }}">
                <button class="btn btn-danger btn-sm">Cerrar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6">Sin sesiones recordadas.</td></tr>
        @endforelse
      </tbody>
    </table>

    {{-- ACTIVIDAD - SUPERADMINS --}}
    <h4 class="mt-5">Superadmins - Usuarios activos</h4>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Email</th>
          <th>IP</th>
          <th>User Agent</th>
          <th>Última actividad</th>
        </tr>
      </thead>
      <tbody>
        @forelse($superadmin_actives as $s)
          <tr>
            <td>{{ $s['email'] }}</td>
            <td>{{ $s['ip'] }}</td>
            <td>{{ $s['user_agent'] }}</td>
            <td>{{ $s['last_active'] ?? 'N/A' }}</td>
          </tr>
        @empty
          <tr><td colspan="4">Sin usuarios activos.</td></tr>
        @endforelse
      </tbody>
    </table>

    @if($multiTenantEnabled)
    {{-- PERSISTENTES - ADMINS --}}
    <h4 class="mt-5">Admins (Tenants) - Recordadas</h4>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Email</th>
          <th>Tenant</th>
          <th>IP</th>
          <th>User Agent</th>
          <th>Desde</th>
          <th>Hasta</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        @forelse($admin_sessions as $s)
          <tr>
            <td>{{ $s['email'] }}</td>
            <td>{{ $s['tenant_name'] ?? 'N/A' }}</td>
            <td>{{ $s['ip'] }}</td>
            <td>{{ $s['user_agent'] }}</td>
            <td>{{ $s['created_at'] }}</td>
            <td>{{ $s['expires_at'] }}</td>
            <td>
              <form method="POST" action="/musedock/sessions/{{ $s['id'] }}/delete">
                {!! csrf_field() !!}
                <input type="hidden" name="user_type" value="admin">
                <input type="hidden" name="source" value="token">
                <input type="hidden" name="user_id" value="{{ $s['user_id'] }}">
                <button class="btn btn-danger btn-sm">Cerrar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7">Sin sesiones recordadas.</td></tr>
        @endforelse
      </tbody>
    </table>

   
    {{-- ACTIVIDAD - ADMINS --}}
    <h4 class="mt-5">Admins (Tenants) - Usuarios activos</h4>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Email</th>
          <th>Tenant</th>
          <th>IP</th>
          <th>User Agent</th>
          <th>Última actividad</th>
        </tr>
      </thead>
      <tbody>
        @forelse($admin_actives as $s)
          <tr>
            <td>{{ $s['email'] }}</td>
            <td>{{ $s['tenant_name'] ?? 'N/A' }}</td>
            <td>{{ $s['ip'] }}</td>
            <td>{{ $s['user_agent'] }}</td>
            <td>{{ $s['last_active'] ?? 'N/A' }}</td>
          </tr>
        @empty
          <tr><td colspan="5">Sin usuarios activos.</td></tr>
        @endforelse
      </tbody>
    </table>
    @endif

    @if($multiTenantEnabled)
	  {{-- PERSISTENTES - USUARIOS --}}
    <h4 class="mt-5">Usuarios regulares - Recordadas</h4>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Email</th>
          <th>Tenant</th>
          <th>IP</th>
          <th>User Agent</th>
          <th>Desde</th>
          <th>Hasta</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        @forelse($user_sessions as $s)
          <tr>
            <td>{{ $s['email'] }}</td>
            <td>{{ $s['tenant_name'] ?? 'N/A' }}</td>
            <td>{{ $s['ip'] }}</td>
            <td>{{ $s['user_agent'] }}</td>
            <td>{{ $s['created_at'] }}</td>
            <td>{{ $s['expires_at'] }}</td>
            <td>
              <form method="POST" action="/musedock/sessions/{{ $s['id'] }}/delete">
                {!! csrf_field() !!}
                <input type="hidden" name="user_type" value="user">
                <input type="hidden" name="source" value="token">
                <input type="hidden" name="user_id" value="{{ $s['user_id'] }}">
                <button class="btn btn-danger btn-sm">Cerrar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7">Sin sesiones recordadas.</td></tr>
        @endforelse
      </tbody>
    </table>
	  
    {{-- ACTIVIDAD - USUARIOS REGULARES --}}
    <h4 class="mt-5">Usuarios regulares - Activos</h4>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>Email</th>
          <th>Tenant</th>
          <th>IP</th>
          <th>User Agent</th>
          <th>Última actividad</th>
        </tr>
      </thead>
      <tbody>
        @forelse($user_actives as $s)
          <tr>
            <td>{{ $s['email'] }}</td>
            <td>{{ $s['tenant_name'] ?? 'N/A' }}</td>
            <td>{{ $s['ip'] }}</td>
            <td>{{ $s['user_agent'] }}</td>
            <td>{{ $s['last_active'] ?? 'N/A' }}</td>
          </tr>
        @empty
          <tr><td colspan="5">Sin usuarios activos.</td></tr>
        @endforelse
      </tbody>
    </table>
    @endif

  </div>
</div>
@endsection