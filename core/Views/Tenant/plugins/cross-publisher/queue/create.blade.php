@extends('layouts.app')

@section('title', 'Publicar en la red - Cross-Publisher')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.cross-publisher._nav', ['activeTab' => 'queue'])

        {{-- Flash Messages --}}
        @if(session('flash_error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('flash_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Publicar en la red</h4>
            <a href="{{ admin_url('/plugins/cross-publisher/queue') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @if(empty($targetTenants))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                No hay tenants disponibles en tu red editorial.
                <a href="{{ admin_url('/plugins/cross-publisher/network') }}" class="alert-link">
                    Configurar red
                </a>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ admin_url('/plugins/cross-publisher/queue') }}" method="POST">
                        @csrf
                        {{-- Select Post --}}
                        <div class="mb-4">
                            <label for="post_id" class="form-label fw-semibold">Seleccionar post *</label>
                            <select class="form-select" id="post_id" name="post_id" required>
                                <option value="">-- Seleccionar --</option>
                                @foreach($posts as $post)
                                    <option value="{{ $post->id }}">
                                        {{ $post->title }}
                                        ({{ date('d/m/Y', strtotime($post->published_at)) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Select Targets with Mode per tenant --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Seleccionar destinos y modo de publicacion *</label>
                            <small class="text-muted d-block mb-3">
                                Para cada destino, elige como publicar el contenido.
                            </small>

                            @foreach($targetTenants as $tenant)
                                <div class="card mb-3 border">
                                    <div class="card-body py-3">
                                        <div class="row align-items-center">
                                            {{-- Checkbox + Tenant info --}}
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input target-check"
                                                           id="target_{{ $tenant->tenant_id }}"
                                                           name="target_tenant_ids[]"
                                                           value="{{ $tenant->tenant_id }}"
                                                           data-tenant-id="{{ $tenant->tenant_id }}">
                                                    <label class="form-check-label" for="target_{{ $tenant->tenant_id }}">
                                                        <strong>{{ $tenant->tenant_name }}</strong>
                                                        <br><small class="text-muted">
                                                            {{ $tenant->domain }}
                                                            <span class="badge bg-secondary">{{ strtoupper($tenant->default_language) }}</span>
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>

                                            {{-- Publish Mode --}}
                                            <div class="col-md-4 target-options" id="options_{{ $tenant->tenant_id }}" style="opacity: 0.4; pointer-events: none;">
                                                <label class="form-label small mb-1">Modo de publicacion</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                           name="publish_mode[{{ $tenant->tenant_id }}]"
                                                           id="mode_clone_{{ $tenant->tenant_id }}"
                                                           value="clone" checked>
                                                    <label class="form-check-label" for="mode_clone_{{ $tenant->tenant_id }}">
                                                        <strong>A) Clonar</strong>
                                                        <small class="text-muted d-block">Copia identica + canonical. Rapido, sin IA.</small>
                                                    </label>
                                                </div>
                                                <div class="form-check mt-1">
                                                    <input class="form-check-input" type="radio"
                                                           name="publish_mode[{{ $tenant->tenant_id }}]"
                                                           id="mode_adapt_{{ $tenant->tenant_id }}"
                                                           value="adapt">
                                                    <label class="form-check-label" for="mode_adapt_{{ $tenant->tenant_id }}">
                                                        <strong>B) Adaptar con IA</strong>
                                                        <small class="text-muted d-block">Reescribe para SEO. Sin canonical, doble indexacion.</small>
                                                    </label>
                                                </div>
                                            </div>

                                            {{-- Language --}}
                                            <div class="col-md-4 target-options" id="lang_{{ $tenant->tenant_id }}" style="opacity: 0.4; pointer-events: none;">
                                                <label class="form-label small mb-1">Idioma destino</label>
                                                <select class="form-select form-select-sm"
                                                        name="target_language[{{ $tenant->tenant_id }}]">
                                                    <option value="es" {{ ($tenant->default_language ?? 'es') === 'es' ? 'selected' : '' }}>Espanol</option>
                                                    <option value="en" {{ ($tenant->default_language ?? '') === 'en' ? 'selected' : '' }}>English</option>
                                                    <option value="ca" {{ ($tenant->default_language ?? '') === 'ca' ? 'selected' : '' }}>Catala</option>
                                                    <option value="fr" {{ ($tenant->default_language ?? '') === 'fr' ? 'selected' : '' }}>Francais</option>
                                                    <option value="de" {{ ($tenant->default_language ?? '') === 'de' ? 'selected' : '' }}>Deutsch</option>
                                                    <option value="it" {{ ($tenant->default_language ?? '') === 'it' ? 'selected' : '' }}>Italiano</option>
                                                    <option value="pt" {{ ($tenant->default_language ?? '') === 'pt' ? 'selected' : '' }}>Portugues</option>
                                                </select>
                                                <small class="text-muted">Si difiere del original, se traduce automaticamente</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Info cards --}}
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="alert alert-info mb-0 small">
                                    <i class="bi bi-files"></i> <strong>Opcion A - Clonar:</strong>
                                    Copia identica con <code>canonical</code> al original. Google solo indexa el original.
                                    Util para llenar medios rapidamente.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-success mb-0 small">
                                    <i class="bi bi-stars"></i> <strong>Opcion B - Adaptar:</strong>
                                    La IA reescribe titulo y parrafos clave. Ambos medios indexan por separado.
                                    Mejor para SEO a largo plazo.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Anadir a la cola
                            </button>
                            <a href="{{ admin_url('/plugins/cross-publisher/queue') }}" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// Enable/disable options when tenant checkbox is toggled
document.querySelectorAll('.target-check').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const tenantId = this.dataset.tenantId;
        const options = document.getElementById('options_' + tenantId);
        const lang = document.getElementById('lang_' + tenantId);

        if (this.checked) {
            if (options) { options.style.opacity = '1'; options.style.pointerEvents = 'auto'; }
            if (lang) { lang.style.opacity = '1'; lang.style.pointerEvents = 'auto'; }
        } else {
            if (options) { options.style.opacity = '0.4'; options.style.pointerEvents = 'none'; }
            if (lang) { lang.style.opacity = '0.4'; lang.style.pointerEvents = 'none'; }
        }
    });
});
</script>
@endpush
@endsection
