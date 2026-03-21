@extends('layouts.app')

@section('title', 'Nueva Redirección de Dominio')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-arrow-right-circle"></i> Nueva Redirección de Dominio</h2>
                <p class="text-muted mb-0">Configura un dominio para redirigir a otra URL</p>
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
                Puedes crear la redirección, pero la configuración automática de Caddy no funcionará.
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Configuración de la Redirección</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/musedock/domain-manager/store-redirect">
                    {!! csrf_field() !!}

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-globe2"></i> Redirección</h6>

                            <div class="mb-3">
                                <label for="domain" class="form-label">Dominio Origen <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="domain" name="domain" required
                                       placeholder="viejo-dominio.com" value="{{ old('domain') }}">
                                <div class="form-text">El dominio que será redirigido (sin www ni https://)</div>
                            </div>

                            <div class="mb-3">
                                <label for="redirect_to" class="form-label">URL Destino <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="redirect_to" name="redirect_to" required
                                       placeholder="https://nuevo-dominio.com" value="{{ old('redirect_to') }}">
                                <div class="form-text">La URL de destino (se añadirá https:// si no lo incluyes)</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Redirección</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="redirect_type" id="redirect_301" value="301" checked>
                                    <label class="form-check-label" for="redirect_301">
                                        <strong>301 — Permanente</strong>
                                        <br><small class="text-muted">Los motores de búsqueda transferirán el SEO al destino</small>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="radio" name="redirect_type" id="redirect_302" value="302">
                                    <label class="form-check-label" for="redirect_302">
                                        <strong>302 — Temporal</strong>
                                        <br><small class="text-muted">Los motores de búsqueda mantendrán el dominio original indexado</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="preserve_path" name="preserve_path" checked>
                                    <label class="form-check-label" for="preserve_path">
                                        <strong>Preservar ruta</strong>
                                        <br><small class="text-muted">ejemplo.com/pagina → destino.com/pagina (en vez de destino.com/)</small>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_www" name="include_www" checked>
                                    <label class="form-check-label" for="include_www">
                                        <strong>Incluir www</strong>
                                        <br><small class="text-muted">También redirigir www.viejo-dominio.com</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="bi bi-cloud"></i> Cloudflare & Caddy</h6>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="skip_cloudflare" name="skip_cloudflare" checked>
                                    <label class="form-check-label" for="skip_cloudflare">
                                        <strong>No crear zona en Cloudflare</strong>
                                        <br><small class="text-muted">Marcar si el DNS ya está configurado manualmente</small>
                                    </label>
                                </div>
                            </div>

                            <div id="cloudflareOptions" style="display: none;">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="configure_cloudflare" name="configure_cloudflare" checked>
                                        <label class="form-check-label" for="configure_cloudflare">
                                            <strong>Crear zona y registros DNS en Cloudflare</strong>
                                            <br><small class="text-muted">Se creará la zona y los registros A/AAAA apuntando al servidor</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Caddy:</strong> Se configurará automáticamente una ruta de redirección HTTP.
                                El certificado SSL se generará automáticamente para el dominio origen.
                            </div>

                            <div class="alert alert-light border mt-3">
                                <h6 class="mb-2"><i class="bi bi-diagram-3"></i> Ejemplo de flujo:</h6>
                                <code id="redirectPreview">viejo-dominio.com/pagina → https://nuevo-dominio.com/pagina</code>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/musedock/domain-manager" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Crear Redirección
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
document.getElementById('skip_cloudflare').addEventListener('change', function() {
    document.getElementById('cloudflareOptions').style.display = this.checked ? 'none' : 'block';
});

// Live preview
function updatePreview() {
    const domain = document.getElementById('domain').value || 'viejo-dominio.com';
    const target = document.getElementById('redirect_to').value || 'https://nuevo-dominio.com';
    const preservePath = document.getElementById('preserve_path').checked;
    const path = preservePath ? '/pagina' : '/';
    document.getElementById('redirectPreview').textContent = `${domain}${path} → ${target}${path}`;
}
document.getElementById('domain').addEventListener('input', updatePreview);
document.getElementById('redirect_to').addEventListener('input', updatePreview);
document.getElementById('preserve_path').addEventListener('change', updatePreview);
</script>
@endpush

@endsection
