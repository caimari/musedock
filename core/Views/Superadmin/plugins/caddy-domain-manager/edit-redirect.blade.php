@extends('layouts.app')

@section('title', 'Editar Redirección: ' . ($redirect->domain ?? ''))

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-pencil"></i> Editar Redirección</h2>
                <p class="text-muted mb-0">{{ $redirect->domain ?? '' }}</p>
            </div>
            <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        @if(!$caddyApiAvailable)
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Caddy API no disponible.</strong>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configuración de la Redirección</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/musedock/domain-manager/redirect/{{ $redirect->id }}/update">
                    {!! csrf_field() !!}

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-globe2"></i> Redirección</h6>

                            <div class="mb-3">
                                <label class="form-label">Dominio Origen</label>
                                <input type="text" class="form-control" value="{{ $redirect->domain }}" disabled>
                                <div class="form-text">El dominio no se puede cambiar. Para usar otro, elimina esta redirección y crea una nueva.</div>
                            </div>

                            <div class="mb-3">
                                <label for="redirect_to" class="form-label">URL Destino <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="redirect_to" name="redirect_to" required
                                       value="{{ $redirect->redirect_to }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Redirección</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="redirect_type" id="redirect_301" value="301"
                                           {{ ($redirect->redirect_type ?? 301) == 301 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="redirect_301">
                                        <strong>301 — Permanente</strong>
                                        <br><small class="text-muted">Los motores de búsqueda transferirán el SEO al destino</small>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="radio" name="redirect_type" id="redirect_302" value="302"
                                           {{ ($redirect->redirect_type ?? 301) == 302 ? 'checked' : '' }}>
                                    <label class="form-check-label" for="redirect_302">
                                        <strong>302 — Temporal</strong>
                                        <br><small class="text-muted">Los motores de búsqueda mantendrán el dominio original indexado</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="preserve_path" name="preserve_path"
                                           {{ ($redirect->preserve_path ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="preserve_path">
                                        <strong>Preservar ruta</strong>
                                        <br><small class="text-muted">ejemplo.com/pagina → destino.com/pagina</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_www" name="include_www"
                                           {{ ($redirect->include_www ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="include_www">
                                        <strong>Incluir www</strong>
                                        <br><small class="text-muted">También redirigir www.{{ $redirect->domain }}</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="dns_provider">Proveedor DNS</label>
                                <select class="form-select" id="dns_provider" name="dns_provider">
                                    @php
                                        $currentDnsProvider = $redirect->dns_provider ?? (!empty($redirect->cloudflare_zone_id) || !empty($redirect->cloudflare_record_id) ? 'cloudflare' : ($defaultDnsProvider ?? 'cloudflare'));
                                    @endphp
                                    @foreach(($dnsProviders ?? []) as $key => $provider)
                                        <option value="{{ $key }}" {{ $currentDnsProvider === $key ? 'selected' : '' }}>
                                            {{ $provider['label'] ?? $key }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">No mueve DNS existente. Solo marca qué proveedor debe usarse para diagnóstico y DNS-01.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-info-circle"></i> Estado Actual</h6>

                            <table class="table table-sm">
                                <tr>
                                    <th>Estado</th>
                                    <td>
                                        @php
                                            $statusClass = match($redirect->status ?? 'pending') {
                                                'active' => 'success',
                                                'pending' => 'warning',
                                                'error' => 'danger',
                                                'suspended' => 'secondary',
                                                default => 'dark'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst($redirect->status ?? 'pending') }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Caddy</th>
                                    <td>
                                        @if($redirect->caddy_configured ?? false)
                                            <span class="text-success"><i class="bi bi-check-circle"></i> Configurado</span>
                                        @else
                                            <span class="text-muted"><i class="bi bi-x-circle"></i> No configurado</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>DNS</th>
                                    <td>
                                        @php
                                            $dnsLabel = ($dnsProviders[$redirect->dns_provider ?? ''] ?? null)['label'] ?? ($redirect->dns_provider ?? 'Manual / externo');
                                        @endphp
                                        <span class="badge {{ ($redirect->dns_provider ?? '') === 'cloudflare' ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $dnsLabel }}</span>
                                        <small class="text-muted">{{ $redirect->dns_mode ?? 'manual' }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Cloudflare</th>
                                    <td>
                                        @if(!empty($redirect->cloudflare_zone_id))
                                            <span class="text-success"><i class="bi bi-shield-fill-check"></i> Zona: {{ $redirect->cloudflare_zone_id }}</span>
                                        @else
                                            <span class="text-muted">No configurado</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($redirect->error_log ?? false)
                                <tr>
                                    <th>Error</th>
                                    <td><small class="text-danger">{{ $redirect->error_log }}</small></td>
                                </tr>
                                @endif
                                <tr>
                                    <th>Creado</th>
                                    <td>{{ !empty($redirect->created_at) ? date('d/m/Y H:i', strtotime($redirect->created_at)) : '—' }}</td>
                                </tr>
                                @if(!empty($redirect->updated_at))
                                <tr>
                                    <th>Actualizado</th>
                                    <td>{{ date('d/m/Y H:i', strtotime($redirect->updated_at)) }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/musedock/domain-manager" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
