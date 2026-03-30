@extends('layouts.app')

@section('title', 'Configuración - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'settings', 'tenantId' => $tenantId ?? 0, 'tenants' => $tenants ?? []])

        @include('partials.alerts-sweetalert2')

        <form action="/musedock/news-aggregator/settings" method="POST">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenantId }}">

            {{-- IA y Reescritura --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-robot"></i> IA y Reescritura</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ai_provider_id" class="form-label">Proveedor de IA</label>
                                <select class="form-select" id="ai_provider_id" name="ai_provider_id">
                                    <option value="">-- Seleccionar --</option>
                                    @foreach($aiProviders as $provider)
                                        <option value="{{ $provider->id }}"
                                                {{ ($settings['ai_provider_id'] ?? '') == $provider->id ? 'selected' : '' }}>
                                            {{ $provider->name }} ({{ $provider->provider }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Proveedor a usar para reescribir noticias.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="output_language" class="form-label">Idioma de salida</label>
                                <select class="form-select" id="output_language" name="output_language">
                                    <option value="es" {{ ($settings['output_language'] ?? 'es') === 'es' ? 'selected' : '' }}>Español</option>
                                    <option value="en" {{ ($settings['output_language'] ?? '') === 'en' ? 'selected' : '' }}>English</option>
                                    <option value="ca" {{ ($settings['output_language'] ?? '') === 'ca' ? 'selected' : '' }}>Català</option>
                                    <option value="fr" {{ ($settings['output_language'] ?? '') === 'fr' ? 'selected' : '' }}>Français</option>
                                    <option value="de" {{ ($settings['output_language'] ?? '') === 'de' ? 'selected' : '' }}>Deutsch</option>
                                    <option value="it" {{ ($settings['output_language'] ?? '') === 'it' ? 'selected' : '' }}>Italiano</option>
                                    <option value="pt" {{ ($settings['output_language'] ?? '') === 'pt' ? 'selected' : '' }}>Português</option>
                                </select>
                                <div class="form-text">Idioma para el contenido reescrito.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="rewrite_prompt" class="form-label">Prompt de reescritura</label>
                        <textarea class="form-control" id="rewrite_prompt" name="rewrite_prompt" rows="4">{{ $settings['rewrite_prompt'] ?? '' }}</textarea>
                        <div class="form-text">Instrucciones para la IA al reescribir noticias.</div>
                    </div>
                </div>
            </div>

            {{-- Pipeline de Automatización --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-gear-wide-connected"></i> Pipeline de Automatización</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle"></i>
                        <strong>Flujo de noticias:</strong> Captura &rarr; Reescritura IA &rarr; Aprobación &rarr; Publicación.
                        Cada paso puede ser automático o manual.
                    </div>

                    <div class="row">
                        {{-- Paso 1: Reescritura --}}
                        <div class="col-md-4">
                            <div class="card h-100 {{ ($settings['auto_rewrite'] ?? false) ? 'border-success' : 'border-secondary' }}">
                                <div class="card-body text-center">
                                    <div class="mb-2" style="font-size: 2rem;">
                                        <i class="bi bi-pencil-square {{ ($settings['auto_rewrite'] ?? false) ? 'text-success' : 'text-secondary' }}"></i>
                                    </div>
                                    <h6>1. Reescritura IA</h6>
                                    <p class="text-muted small">Después de capturar, la IA reescribe automáticamente el contenido</p>
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input type="checkbox" class="form-check-input" id="auto_rewrite" name="auto_rewrite"
                                               role="switch"
                                               {{ ($settings['auto_rewrite'] ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label ms-2" for="auto_rewrite">
                                            <strong>{{ ($settings['auto_rewrite'] ?? false) ? 'Automático' : 'Manual' }}</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Paso 2: Aprobación --}}
                        <div class="col-md-4">
                            <div class="card h-100 {{ ($settings['auto_approve'] ?? false) ? 'border-success' : 'border-secondary' }}">
                                <div class="card-body text-center">
                                    <div class="mb-2" style="font-size: 2rem;">
                                        <i class="bi bi-check-circle {{ ($settings['auto_approve'] ?? false) ? 'text-success' : 'text-secondary' }}"></i>
                                    </div>
                                    <h6>2. Aprobación</h6>
                                    <p class="text-muted small">Después de reescribir, aprobar automáticamente o revisar manualmente</p>
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input type="checkbox" class="form-check-input" id="auto_approve" name="auto_approve"
                                               role="switch"
                                               {{ ($settings['auto_approve'] ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label ms-2" for="auto_approve">
                                            <strong>{{ ($settings['auto_approve'] ?? false) ? 'Automático' : 'Manual' }}</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Paso 3: Publicación --}}
                        <div class="col-md-4">
                            <div class="card h-100 {{ ($settings['auto_publish'] ?? false) ? 'border-success' : 'border-secondary' }}">
                                <div class="card-body text-center">
                                    <div class="mb-2" style="font-size: 2rem;">
                                        <i class="bi bi-send {{ ($settings['auto_publish'] ?? false) ? 'text-success' : 'text-secondary' }}"></i>
                                    </div>
                                    <h6>3. Publicación</h6>
                                    <p class="text-muted small">Después de aprobar, publicar automáticamente como post del blog</p>
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input type="checkbox" class="form-check-input" id="auto_publish" name="auto_publish"
                                               role="switch"
                                               {{ ($settings['auto_publish'] ?? false) ? 'checked' : '' }}>
                                        <label class="form-check-label ms-2" for="auto_publish">
                                            <strong>{{ ($settings['auto_publish'] ?? false) ? 'Automático' : 'Manual' }}</strong>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Indicador visual del flujo --}}
                    <div class="mt-4 p-3 bg-light rounded">
                        <div class="d-flex align-items-center justify-content-center flex-wrap gap-2">
                            <span class="badge bg-primary">Captura (cron)</span>
                            <i class="bi bi-arrow-right"></i>
                            <span class="badge {{ ($settings['auto_rewrite'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                                Reescritura {{ ($settings['auto_rewrite'] ?? false) ? '(auto)' : '(manual)' }}
                            </span>
                            <i class="bi bi-arrow-right"></i>
                            <span class="badge {{ ($settings['auto_approve'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                                Aprobación {{ ($settings['auto_approve'] ?? false) ? '(auto)' : '(manual)' }}
                            </span>
                            <i class="bi bi-arrow-right"></i>
                            <span class="badge {{ ($settings['auto_publish'] ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                                Publicación {{ ($settings['auto_publish'] ?? false) ? '(auto)' : '(manual)' }}
                            </span>
                            <i class="bi bi-arrow-right"></i>
                            <span class="badge bg-info">Blog Post</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Publicación --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-newspaper"></i> Publicación en Blog</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="default_category_id" class="form-label">Categoría por defecto</label>
                                <select class="form-select" id="default_category_id" name="default_category_id">
                                    <option value="">-- Sin categoría --</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}"
                                                {{ ($settings['default_category_id'] ?? '') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Categoría a asignar si la IA no sugiere otra.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="publish_status" class="form-label">Estado al publicar</label>
                                <select class="form-select" id="publish_status" name="publish_status">
                                    <option value="draft" {{ ($settings['publish_status'] ?? 'draft') === 'draft' ? 'selected' : '' }}>Borrador</option>
                                    <option value="published" {{ ($settings['publish_status'] ?? '') === 'published' ? 'selected' : '' }}>Publicado directamente</option>
                                </select>
                                <div class="form-text">Estado del post al crearse en el blog.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="duplicate_check_days" class="form-label">Días verificación duplicados</label>
                                <input type="number" class="form-control" id="duplicate_check_days"
                                       name="duplicate_check_days"
                                       value="{{ $settings['duplicate_check_days'] ?? 7 }}"
                                       min="1" max="90">
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="auto_generate_tags" name="auto_generate_tags"
                               {{ ($settings['auto_generate_tags'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="auto_generate_tags">
                            Generar categorías y etiquetas automáticamente con IA
                        </label>
                        <div class="form-text">La IA sugerirá categorías existentes y creará tags nuevos al reescribir.</div>
                    </div>
                </div>
            </div>

            {{-- APIs de Investigación --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-search"></i> APIs de Noticias (Investigación y Fuentes API)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Estas API keys se usan para: <strong>1)</strong> Crear fuentes de tipo API (NewsAPI, GNews, etc.) al dar de alta un medio.
                        <strong>2)</strong> La función "Investigar" en el detalle de cada noticia, que busca información adicional en múltiples servicios.
                        Solo necesitas configurar las que vayas a usar. El sistema rota automáticamente entre las disponibles.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="currentsapi_key" class="form-label">CurrentsAPI <span class="badge bg-success">600 req/día</span></label>
                                <input type="text" class="form-control" id="currentsapi_key" name="currentsapi_key"
                                       value="{{ $settings['currentsapi_key'] ?? '' }}"
                                       placeholder="Tu API key de CurrentsAPI">
                                <div class="form-text">Obtener en <a href="https://currentsapi.services/en/register" target="_blank">currentsapi.services</a></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="newsapi_key" class="form-label">NewsAPI.org <span class="badge bg-info">100 req/día</span></label>
                                <input type="text" class="form-control" id="newsapi_key" name="newsapi_key"
                                       value="{{ $settings['newsapi_key'] ?? '' }}"
                                       placeholder="Tu API key de NewsAPI">
                                <div class="form-text">Obtener en <a href="https://newsapi.org/register" target="_blank">newsapi.org</a></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="gnews_key" class="form-label">GNews.io <span class="badge bg-info">100 req/día</span></label>
                                <input type="text" class="form-control" id="gnews_key" name="gnews_key"
                                       value="{{ $settings['gnews_key'] ?? '' }}"
                                       placeholder="Tu API key de GNews">
                                <div class="form-text">Obtener en <a href="https://gnews.io/register" target="_blank">gnews.io</a></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="thenewsapi_key" class="form-label">TheNewsAPI.com <span class="badge bg-info">100 req/día</span></label>
                                <input type="text" class="form-control" id="thenewsapi_key" name="thenewsapi_key"
                                       value="{{ $settings['thenewsapi_key'] ?? '' }}"
                                       placeholder="Tu API key de TheNewsAPI">
                                <div class="form-text">Obtener en <a href="https://www.thenewsapi.com/register" target="_blank">thenewsapi.com</a></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="mediastack_key" class="form-label">MediaStack <span class="badge bg-secondary">500 req/mes</span></label>
                                <input type="text" class="form-control" id="mediastack_key" name="mediastack_key"
                                       value="{{ $settings['mediastack_key'] ?? '' }}"
                                       placeholder="Tu API key de MediaStack">
                                <div class="form-text">Obtener en <a href="https://mediastack.com/signup" target="_blank">mediastack.com</a></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Plugin activo --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="enabled" name="enabled"
                               {{ ($settings['enabled'] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="enabled">
                            <strong>Plugin activo</strong>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> Guardar configuración
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar labels de los switches dinámicamente
    document.querySelectorAll('.form-check-input[role="switch"]').forEach(function(sw) {
        sw.addEventListener('change', function() {
            const label = this.nextElementSibling;
            if (label) {
                label.innerHTML = '<strong>' + (this.checked ? 'Automático' : 'Manual') + '</strong>';
            }
            // Actualizar borde de la card
            const card = this.closest('.card');
            if (card) {
                card.classList.toggle('border-success', this.checked);
                card.classList.toggle('border-secondary', !this.checked);
                // Actualizar icono
                const icon = card.querySelector('.bi');
                if (icon) {
                    icon.classList.toggle('text-success', this.checked);
                    icon.classList.toggle('text-secondary', !this.checked);
                }
            }
        });
    });
});
</script>
@endsection
