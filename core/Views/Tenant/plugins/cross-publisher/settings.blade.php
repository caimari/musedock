@extends('layouts.app')

@section('title', 'Configuración - Cross-Publisher')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.cross-publisher._nav', ['activeTab' => 'settings'])

        {{-- Flash Messages --}}
        @if(session('flash_success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('flash_success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('flash_error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('flash_error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ admin_url('/plugins/cross-publisher/settings') }}" method="POST">
            @csrf
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Configuración General</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ai_provider_id" class="form-label">Proveedor de IA (para traducciones)</label>
                                <select class="form-select" id="ai_provider_id" name="ai_provider_id">
                                    <option value="">-- No usar IA --</option>
                                    @foreach($aiProviders as $provider)
                                        <option value="{{ $provider->id }}"
                                                {{ ($settings['ai_provider_id'] ?? '') == $provider->id ? 'selected' : '' }}>
                                            {{ $provider->name }} ({{ $provider->provider_type }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="default_status" class="form-label">Estado por defecto de posts</label>
                                <select class="form-select" id="default_status" name="default_status">
                                    <option value="draft" {{ ($settings['default_status'] ?? 'draft') === 'draft' ? 'selected' : '' }}>Borrador</option>
                                    <option value="published" {{ ($settings['default_status'] ?? '') === 'published' ? 'selected' : '' }}>Publicado</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="auto_translate" name="auto_translate"
                                       {{ ($settings['auto_translate'] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="auto_translate">
                                    Traducir automáticamente
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="include_featured_image" name="include_featured_image"
                                       {{ ($settings['include_featured_image'] ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="include_featured_image">
                                    Incluir imagen destacada
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="add_canonical_link" name="add_canonical_link"
                                       {{ ($settings['add_canonical_link'] ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="add_canonical_link">
                                    Añadir URL canónica
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Crédito de fuente</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="add_source_credit" name="add_source_credit"
                                   {{ ($settings['add_source_credit'] ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="add_source_credit">
                                Añadir crédito de fuente al final
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="source_credit_template" class="form-label">Plantilla de crédito</label>
                        <input type="text" class="form-control" id="source_credit_template" name="source_credit_template"
                               value="{{ $settings['source_credit_template'] ?? '' }}"
                               placeholder="Publicado originalmente en {source_name}">
                        <div class="form-text">Variables: {source_name}, {source_url}</div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="enabled" name="enabled"
                           {{ ($settings['enabled'] ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="enabled">
                        <strong>Plugin activo</strong>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> Guardar configuración
            </button>
        </form>
    </div>
</div>
@endsection
