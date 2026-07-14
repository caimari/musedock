@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-envelope-paper me-2"></i>{{ $title }}</h2>
                <p class="text-muted mb-0">Suscriptores de newsletter de este tenant</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/{{ admin_path() }}/newsletter/campaigns" class="btn btn-primary">
                    <i class="bi bi-megaphone me-1"></i>Campañas
                </a>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="/{{ admin_path() }}/newsletter" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="email o nombre">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="" {{ $status === '' ? 'selected' : '' }}>Todos</option>
                            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pendiente</option>
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="unsubscribed" {{ $status === 'unsubscribed' ? 'selected' : '' }}>Baja</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search me-1"></i>Filtrar
                        </button>
                        <a href="/{{ admin_path() }}/newsletter" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @php
            $totals = ['pending' => 0, 'active' => 0, 'unsubscribed' => 0];
            foreach ($items as $it) {
                $st = $it->status ?? '';
                if (isset($totals[$st])) {
                    $totals[$st]++;
                }
            }
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card"><div class="card-body"><div class="text-muted">Pendientes</div><div class="h4 mb-0">{{ $totals['pending'] }}</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><div class="text-muted">Activos</div><div class="h4 mb-0">{{ $totals['active'] }}</div></div></div>
            </div>
            <div class="col-md-4">
                <div class="card"><div class="card-body"><div class="text-muted">Bajas</div><div class="h4 mb-0">{{ $totals['unsubscribed'] }}</div></div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive p-0">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Consentimiento</th>
                            <th>Alta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td><code>{{ $item->email }}</code></td>
                                <td>{{ $item->name ?: '-' }}</td>
                                <td>
                                    @if(($item->status ?? '') === 'active')
                                        <span class="badge bg-success">Activo</span>
                                    @elseif(($item->status ?? '') === 'pending')
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    @else
                                        <span class="badge bg-secondary">Baja</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($item->consent_at))
                                        <small>
                                            {{ $item->consent_at }}<br>
                                            <span class="text-muted">{{ $item->consent_ip ?: '-' }}</span>
                                        </small>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $item->created_at }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay suscriptores con ese filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
