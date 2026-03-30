@extends('layouts.app')

@section('title', 'Configuración - Cross-Publisher')

@section('content')
<div class="container-fluid p-4">
    <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-gear me-2"></i>Configuración Cross-Publisher</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/musedock/cross-publisher">Cross-Publisher</a></li>
                <li class="breadcrumb-item active">Configuración</li>
            </ol>
        </nav>
    </div>

    @include('partials.alerts-sweetalert2')

    <form method="POST" action="/musedock/cross-publisher/settings">
        {!! csrf_field() !!}
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Publicación</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="default_target_status" class="form-label">Estado por defecto del post destino</label>
                            <select class="form-select" id="default_target_status" name="default_target_status">
                                <option value="draft" {{ ($settings['default_target_status'] ?? '') === 'draft' ? 'selected' : '' }}>Borrador</option>
                                <option value="published" {{ ($settings['default_target_status'] ?? '') === 'published' ? 'selected' : '' }}>Publicado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="include_featured_image" name="include_featured_image" value="1" {{ !empty($settings['include_featured_image']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="include_featured_image">Copiar imagen destacada</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="include_categories" name="include_categories" value="1" {{ !empty($settings['include_categories']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="include_categories">Copiar categorías (crear si no existen)</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="include_tags" name="include_tags" value="1" {{ !empty($settings['include_tags']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="include_tags">Copiar tags (crear si no existen)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">SEO y Atribución</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add_canonical_link" name="add_canonical_link" value="1" {{ !empty($settings['add_canonical_link']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="add_canonical_link">Añadir URL canónica al post destino</label>
                            </div>
                            <small class="text-muted">Apunta al post original para evitar contenido duplicado en SEO.</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add_source_credit" name="add_source_credit" value="1" {{ !empty($settings['add_source_credit']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="add_source_credit">Añadir crédito de fuente al contenido</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="source_credit_template" class="form-label">Plantilla de crédito</label>
                            <input type="text" class="form-control" id="source_credit_template" name="source_credit_template" value="{{ $settings['source_credit_template'] ?? '' }}">
                            <small class="text-muted">Variables: {source_url}, {source_name}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Traducción IA</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="ai_provider_id" class="form-label">Proveedor de IA</label>
                            <select class="form-select" id="ai_provider_id" name="ai_provider_id">
                                <option value="">-- Sin IA --</option>
                                @foreach($providers as $provider)
                                <option value="{{ $provider->id }}" {{ ($settings['ai_provider_id'] ?? '') == $provider->id ? 'selected' : '' }}>
                                    {{ $provider->name }} ({{ $provider->provider_type }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="auto_translate" name="auto_translate" value="1" {{ !empty($settings['auto_translate']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="auto_translate">Traducir automáticamente al idioma del tenant destino</label>
                            </div>
                            <small class="text-muted">Si está activo, al publicar un post en un tenant con idioma diferente, la traducción se pre-activará automáticamente usando el idioma por defecto (<code>default_lang</code>) del tenant destino.</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title mb-0">Sincronización</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sync_enabled" name="sync_enabled" value="1" {{ !empty($settings['sync_enabled']) ? 'checked' : '' }}>
                                <label class="form-check-label" for="sync_enabled">Auto-sync habilitado</label>
                            </div>
                            <small class="text-muted">El cron detecta cambios en posts fuente y actualiza las copias automáticamente.</small>
                        </div>
                        <div class="mb-3">
                            <label for="sync_cron_interval" class="form-label">Intervalo de sync (minutos)</label>
                            <input type="number" class="form-control" id="sync_cron_interval" name="sync_cron_interval" value="{{ $settings['sync_cron_interval'] ?? 15 }}" min="5" max="1440">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Guardar Configuración
        </button>
    </form>
</div>
@endsection
