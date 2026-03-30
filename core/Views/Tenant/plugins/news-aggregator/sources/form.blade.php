@extends('layouts.app')

@section('title', ($action === 'edit' ? 'Editar' : 'Añadir') . ' Fuente - News Aggregator')

@section('content')
<div class="app-content">
    <div class="container-fluid">

        @include('plugins.news-aggregator._nav', ['activeTab' => 'sources'])

        @include('partials.alerts-sweetalert2')

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">{{ $action === 'edit' ? 'Editar fuente' : 'Añadir fuente' }}</h4>
            <a href="{{ admin_url('/plugins/news-aggregator/sources') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @php
                    $formAction = $action === 'edit'
                        ? admin_url('/plugins/news-aggregator/sources/' . $source->id . '/update')
                        : admin_url('/plugins/news-aggregator/sources');
                    $currentType = $source->source_type ?? 'rss';
                    $currentProcessing = $source->processing_type ?? 'direct';
                @endphp
                <form action="{{ $formAction }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="{{ $source->name ?? '' }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="source_type" class="form-label">Tipo de fuente *</label>
                                <select class="form-select" id="source_type" name="source_type">
                                    <option value="rss" {{ $currentType === 'rss' ? 'selected' : '' }}>RSS/Atom Feed</option>
                                    <option value="newsapi" {{ $currentType === 'newsapi' ? 'selected' : '' }}>NewsAPI.org</option>
                                    <option value="gnews" {{ $currentType === 'gnews' ? 'selected' : '' }}>GNews.io</option>
                                    <option value="mediastack" {{ $currentType === 'mediastack' ? 'selected' : '' }}>MediaStack</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Tipo de procesamiento (solo visible para RSS) --}}
                    <div id="processing-type-section" class="mb-4" style="{{ $currentType !== 'rss' ? 'display:none' : '' }}">
                        <label class="form-label fw-bold mb-2">Tipo de procesamiento</label>
                        <input type="hidden" name="processing_type" id="processing_type" value="{{ $currentProcessing }}">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100 cursor-pointer processing-card {{ $currentProcessing === 'direct' ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary' }}"
                                     data-type="direct" onclick="selectProcessingType('direct')">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-arrow-right-circle fs-4 me-2 {{ $currentProcessing === 'direct' ? 'text-primary' : 'text-secondary' }}"></i>
                                            <h6 class="mb-0">Fuente directa</h6>
                                        </div>
                                        <p class="text-muted small mb-0">1 feed RSS, procesado individualmente. La IA reescribe cada noticia por separado.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 cursor-pointer processing-card {{ $currentProcessing === 'verified' ? 'border-primary bg-primary bg-opacity-10' : 'border-secondary' }}"
                                     data-type="verified" onclick="selectProcessingType('verified')">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-shield-check fs-4 me-2 {{ $currentProcessing === 'verified' ? 'text-primary' : 'text-secondary' }}"></i>
                                            <h6 class="mb-0">Fuente verificada</h6>
                                        </div>
                                        <p class="text-muted small mb-0">2-5 feeds de medios distintos. Solo procesa cuando 2+ medios cubren la misma noticia.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Info panel que cambia según el tipo --}}
                    <div id="type-info" class="alert alert-light mb-3" style="display:none;"></div>

                    {{-- URL del Feed (fuente directa RSS o APIs) --}}
                    <div class="mb-3" id="field-url">
                        <label for="url" class="form-label" id="url-label">URL del Feed *</label>
                        <input type="url" class="form-control" id="url" name="url"
                               value="{{ $source->url ?? '' }}" placeholder="https://example.com/feed.xml">
                        <div class="form-text" id="url-help">URL completa del feed RSS o Atom.</div>
                    </div>

                    {{-- Feeds múltiples (fuente verificada) --}}
                    <div id="feeds-section" style="{{ $currentProcessing !== 'verified' || $currentType !== 'rss' ? 'display:none' : '' }}">
                        <div class="card border bg-light mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0"><i class="bi bi-rss"></i> Feeds de medios (2-5)</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-feed-btn" onclick="addFeedRow()">
                                        <i class="bi bi-plus-circle"></i> Añadir feed
                                    </button>
                                </div>
                                <div id="feeds-container">
                                    @if(!empty($feeds))
                                        @foreach($feeds as $i => $feed)
                                            <div class="feed-row row g-2 mb-2 align-items-end" data-index="{{ $i }}">
                                                <div class="col-md-4">
                                                    <label class="form-label small">Nombre del medio</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                           name="feeds[{{ $i }}][name]" value="{{ $feed->name ?? '' }}"
                                                           placeholder="Ej: Diario de Mallorca">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small">URL del feed RSS</label>
                                                    <input type="url" class="form-control form-control-sm"
                                                           name="feeds[{{ $i }}][url]" value="{{ $feed->url ?? '' }}"
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeFeedRow(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="feed-row row g-2 mb-2 align-items-end" data-index="0">
                                            <div class="col-md-4">
                                                <label class="form-label small">Nombre del medio</label>
                                                <input type="text" class="form-control form-control-sm"
                                                       name="feeds[0][name]" placeholder="Ej: Diario de Mallorca">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">URL del feed RSS</label>
                                                <input type="url" class="form-control form-control-sm"
                                                       name="feeds[0][url]" placeholder="https://...">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeFeedRow(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="feed-row row g-2 mb-2 align-items-end" data-index="1">
                                            <div class="col-md-4">
                                                <label class="form-label small">Nombre del medio</label>
                                                <input type="text" class="form-control form-control-sm"
                                                       name="feeds[1][name]" placeholder="Ej: Última Hora">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">URL del feed RSS</label>
                                                <input type="url" class="form-control form-control-sm"
                                                       name="feeds[1][url]" placeholder="https://...">
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeFeedRow(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-4">
                                            <label for="min_sources_for_publish" class="form-label small">Mínimo de medios para publicar</label>
                                            <select class="form-select form-select-sm" name="min_sources_for_publish" id="min_sources_for_publish">
                                                <option value="2" {{ ($source->min_sources_for_publish ?? 2) == 2 ? 'selected' : '' }}>2 medios</option>
                                                <option value="3" {{ ($source->min_sources_for_publish ?? 2) == 3 ? 'selected' : '' }}>3 medios</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-text mt-1">Solo se procesan noticias cubiertas por este mínimo de feeds distintos. Las que no lo cumplan quedan en cola manual.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- API Key (solo para APIs) --}}
                    <div class="mb-3" id="field-apikey" style="display:none;">
                        <label for="api_key" class="form-label">API Key *</label>
                        <input type="text" class="form-control" id="api_key" name="api_key"
                               value="{{ $source->api_key ?? '' }}" placeholder="Tu clave de API">
                        <div class="form-text" id="apikey-help">Clave de API necesaria para acceder al servicio.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="keywords" class="form-label">Palabras clave</label>
                                <input type="text" class="form-control" id="keywords" name="keywords"
                                       value="{{ $source->keywords ?? '' }}" placeholder="cultura, arte, música, Mallorca">
                                <div class="form-text" id="keywords-help">Separadas por comas. Busca en <strong>título + contenido + tags del medio</strong> (<code>&lt;media:keywords&gt;</code>). Si vacío, captura todo. Incluye stemming (ej: "libro" encuentra "libros").</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3" id="field-categories">
                                <label for="categories" class="form-label" id="categories-label">Categorías API</label>
                                <input type="text" class="form-control" id="categories" name="categories"
                                       value="{{ $source->categories ?? '' }}" placeholder="technology, science">
                                <div class="form-text" id="categories-help">Categoría o topic de la API.</div>
                            </div>
                        </div>
                    </div>

                    {{-- Filtros por tags del RSS --}}
                    <div class="row" id="tag-filters-section">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="excluded_tags" class="form-label">Tags prohibidos</label>
                                <input type="text" class="form-control" id="excluded_tags" name="excluded_tags"
                                       value="{{ $source->excluded_tags ?? '' }}" placeholder="deportes, fútbol, sucesos, política">
                                <div class="form-text">Separados por comas. Si una noticia del RSS lleva alguno de estos tags en sus metadatos (<code>&lt;category&gt;</code> o <code>&lt;media:keywords&gt;</code>), se descarta. Ejemplo: un feed general con "deportes" aquí eliminaria todas las noticias etiquetadas como deportes por el propio medio.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="required_tags" class="form-label">Tags requeridos</label>
                                <input type="text" class="form-control" id="required_tags" name="required_tags"
                                       value="{{ $source->required_tags ?? '' }}" placeholder="cultura, arte, música, teatro">
                                <div class="form-text">Separados por comas. Solo se capturan noticias que el medio haya etiquetado con al menos uno de estos tags en sus metadatos (<code>&lt;category&gt;</code> o <code>&lt;media:keywords&gt;</code>). Ejemplo: "cultura, arte" solo captura noticias que el medio clasifica en esas secciones.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="language" class="form-label">Idioma de la fuente</label>
                                <select class="form-select" id="language" name="language">
                                    <option value="">Auto-detectar</option>
                                    <option value="es" {{ ($source->language ?? '') === 'es' ? 'selected' : '' }}>Español</option>
                                    <option value="en" {{ ($source->language ?? '') === 'en' ? 'selected' : '' }}>English</option>
                                    <option value="ca" {{ ($source->language ?? '') === 'ca' ? 'selected' : '' }}>Català</option>
                                    <option value="fr" {{ ($source->language ?? '') === 'fr' ? 'selected' : '' }}>Français</option>
                                    <option value="de" {{ ($source->language ?? '') === 'de' ? 'selected' : '' }}>Deutsch</option>
                                    <option value="it" {{ ($source->language ?? '') === 'it' ? 'selected' : '' }}>Italiano</option>
                                    <option value="pt" {{ ($source->language ?? '') === 'pt' ? 'selected' : '' }}>Português</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Info sobre filtros --}}
                    <div class="alert alert-light border mb-3" id="filter-info">
                        <small>
                            <i class="bi bi-funnel"></i> <strong>Orden de filtrado:</strong>
                            1) <em>Palabras clave</em> filtra por texto en título + contenido + tags.
                            2) <em>Tags prohibidos</em> descarta noticias que el medio etiqueta con esos tags.
                            3) <em>Tags requeridos</em> solo deja pasar noticias etiquetadas con al menos uno de esos tags.
                            Todos los filtros son opcionales e independientes. Si dejas todos vacíos, se captura todo el feed.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="fetch_interval" class="form-label">Intervalo de captura</label>
                                <select class="form-select" id="fetch_interval" name="fetch_interval">
                                    <option value="900" {{ ($source->fetch_interval ?? 3600) == 900 ? 'selected' : '' }}>15 minutos</option>
                                    <option value="1800" {{ ($source->fetch_interval ?? 3600) == 1800 ? 'selected' : '' }}>30 minutos</option>
                                    <option value="3600" {{ ($source->fetch_interval ?? 3600) == 3600 ? 'selected' : '' }}>1 hora</option>
                                    <option value="7200" {{ ($source->fetch_interval ?? 3600) == 7200 ? 'selected' : '' }}>2 horas</option>
                                    <option value="14400" {{ ($source->fetch_interval ?? 3600) == 14400 ? 'selected' : '' }}>4 horas</option>
                                    <option value="28800" {{ ($source->fetch_interval ?? 3600) == 28800 ? 'selected' : '' }}>8 horas</option>
                                    <option value="86400" {{ ($source->fetch_interval ?? 3600) == 86400 ? 'selected' : '' }}>24 horas</option>
                                </select>
                                <div class="form-text">Frecuencia de captura automática.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_articles" class="form-label">Máx. artículos por captura</label>
                                <input type="number" class="form-control" id="max_articles" name="max_articles"
                                       value="{{ $source->max_articles ?? 10 }}" min="1" max="100">
                            </div>
                        </div>
                    </div>

                    {{-- Configuración editorial --}}
                    <div class="card border bg-light mb-3">
                        <div class="card-body">
                            <h6 class="card-title mb-3"><i class="bi bi-shield-check"></i> Configuración editorial y legal</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="attribution_mode" class="form-label">Modo de atribución</label>
                                        @php $currentAttribution = $source->attribution_mode ?? 'rewrite'; @endphp
                                        <select class="form-select" id="attribution_mode" name="attribution_mode">
                                            <option value="rewrite" {{ $currentAttribution === 'rewrite' ? 'selected' : '' }}>Reescritura con IA</option>
                                            <option value="headline_only" {{ $currentAttribution === 'headline_only' ? 'selected' : '' }}>Solo titular + enlace</option>
                                        </select>
                                        <div class="form-text">
                                            <strong>Reescritura:</strong> la IA genera texto nuevo a partir del extracto.<br>
                                            <strong>Solo titular:</strong> publica solo el título con enlace a la fuente (el más seguro legalmente).
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 d-flex align-items-end h-100 pb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="exclude_rewrite" name="exclude_rewrite"
                                                   {{ ($source->exclude_rewrite ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="exclude_rewrite"><strong>Excluir de reescritura IA</strong></label>
                                            <div class="form-text">Si marcas esto, las noticias de esta fuente nunca pasarán por la IA.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 d-flex align-items-end h-100 pb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="enabled" name="enabled"
                                                   {{ ($source->enabled ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="enabled"><strong>Fuente activa</strong></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="show_attribution" name="show_attribution"
                                                   {{ ($source->show_attribution ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="show_attribution"><strong>Mostrar atribución de fuente</strong></label>
                                            <div class="form-text">Si está activo, se añade automáticamente al final del artículo publicado una línea con el nombre del medio de origen. Para fuentes verificadas con múltiples medios, se listan todos los medios que cubrieron la noticia.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Guardar fuente
                        </button>
                        <a href="{{ admin_url('/plugins/news-aggregator/sources') }}" class="btn btn-outline-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.processing-card { cursor: pointer; transition: all 0.2s; }
.processing-card:hover { border-color: #0d6efd !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('source_type');
    const fieldUrl = document.getElementById('field-url');
    const fieldApiKey = document.getElementById('field-apikey');
    const fieldCategories = document.getElementById('field-categories');
    const filterInfo = document.getElementById('filter-info');
    const urlLabel = document.getElementById('url-label');
    const urlHelp = document.getElementById('url-help');
    const urlInput = document.getElementById('url');
    const apikeyHelp = document.getElementById('apikey-help');
    const categoriesLabel = document.getElementById('categories-label');
    const categoriesHelp = document.getElementById('categories-help');
    const keywordsHelp = document.getElementById('keywords-help');
    const typeInfo = document.getElementById('type-info');
    const processingSection = document.getElementById('processing-type-section');
    const feedsSection = document.getElementById('feeds-section');

    const typeConfig = {
        rss: {
            url: { show: true, required: true, label: 'URL del Feed *', help: 'URL completa del feed RSS o Atom.', placeholder: 'https://example.com/feed.xml' },
            apikey: { show: false },
            categories: { show: false },
            keywords: { help: 'Separadas por comas. Busca en <strong>título + contenido + tags del medio</strong>. Incluye stemming.' },
            filterInfo: true,
            info: null
        },
        newsapi: {
            url: { show: true, required: false, label: 'Dominio (opcional)', help: 'Dominio para filtrar noticias. Ej: elpais.com. Dejar vacío para buscar por keywords/categoría.', placeholder: 'elpais.com' },
            apikey: { show: true, help: 'Obtén tu API Key en <a href="https://newsapi.org" target="_blank">newsapi.org</a>' },
            categories: { show: true, label: 'Categoría', help: 'business, entertainment, general, health, science, sports, technology' },
            keywords: { help: 'Términos de búsqueda para el endpoint /everything de NewsAPI.' },
            filterInfo: false,
            info: '<i class="bi bi-info-circle"></i> <strong>NewsAPI:</strong> Necesitas API Key. Puedes buscar por keywords, categoría o dominio.'
        },
        gnews: {
            url: { show: false },
            apikey: { show: true, help: 'Obtén tu API Key en <a href="https://gnews.io" target="_blank">gnews.io</a>' },
            categories: { show: true, label: 'Topic', help: 'general, world, nation, business, technology, entertainment, sports, science, health' },
            keywords: { help: 'Términos de búsqueda. Si se especifican, se usa el endpoint /search.' },
            filterInfo: false,
            info: '<i class="bi bi-info-circle"></i> <strong>GNews:</strong> Necesitas API Key. Sin keywords muestra top-headlines del topic seleccionado.'
        },
        mediastack: {
            url: { show: true, required: false, label: 'Dominio fuente (opcional)', help: 'Filtrar por dominio. Ej: bbc.com.', placeholder: 'bbc.com' },
            apikey: { show: true, help: 'Obtén tu API Key en <a href="https://mediastack.com" target="_blank">mediastack.com</a>.' },
            categories: { show: true, label: 'Categorías', help: 'general, business, entertainment, health, science, sports, technology (separadas por coma)' },
            keywords: { help: 'Palabras clave para filtrar noticias de MediaStack.' },
            filterInfo: false,
            info: '<i class="bi bi-info-circle"></i> <strong>MediaStack:</strong> Necesitas API Key. El plan gratuito tiene límite de 100 requests/mes.'
        }
    };

    function updateFormFields() {
        const type = typeSelect.value;
        const config = typeConfig[type] || typeConfig.rss;
        const isRss = type === 'rss';
        const processingType = document.getElementById('processing_type').value;

        // Processing type section (solo RSS)
        processingSection.style.display = isRss ? '' : 'none';

        // URL field — para RSS verificada se oculta (usan feeds individuales)
        if (isRss && processingType === 'verified') {
            fieldUrl.style.display = 'none';
            urlInput.required = false;
        } else if (config.url.show) {
            fieldUrl.style.display = '';
            urlLabel.textContent = config.url.label;
            urlHelp.textContent = config.url.help;
            urlInput.placeholder = config.url.placeholder || '';
            urlInput.required = config.url.required || false;
        } else {
            fieldUrl.style.display = 'none';
            urlInput.required = false;
        }

        // Feeds section (solo RSS verificada)
        feedsSection.style.display = (isRss && processingType === 'verified') ? '' : 'none';

        // API Key field
        if (config.apikey.show) {
            fieldApiKey.style.display = '';
            apikeyHelp.innerHTML = config.apikey.help;
        } else {
            fieldApiKey.style.display = 'none';
        }

        // Categories field
        if (config.categories.show) {
            fieldCategories.style.display = '';
            categoriesLabel.textContent = config.categories.label;
            categoriesHelp.textContent = config.categories.help;
        } else {
            fieldCategories.style.display = 'none';
        }

        // Filter info panel (solo RSS)
        filterInfo.style.display = config.filterInfo ? '' : 'none';

        // Keywords help
        keywordsHelp.innerHTML = config.keywords.help;

        // Info panel
        if (config.info) {
            typeInfo.innerHTML = config.info;
            typeInfo.style.display = '';
        } else {
            typeInfo.style.display = 'none';
        }
    }

    typeSelect.addEventListener('change', updateFormFields);
    updateFormFields();
});

// Processing type selection
function selectProcessingType(type) {
    document.getElementById('processing_type').value = type;

    document.querySelectorAll('.processing-card').forEach(function(card) {
        const isSelected = card.dataset.type === type;
        card.classList.toggle('border-primary', isSelected);
        card.classList.toggle('bg-primary', isSelected);
        card.classList.toggle('bg-opacity-10', isSelected);
        card.classList.toggle('border-secondary', !isSelected);
        const icon = card.querySelector('.bi');
        if (icon) {
            icon.classList.toggle('text-primary', isSelected);
            icon.classList.toggle('text-secondary', !isSelected);
        }
    });

    // Toggle URL vs feeds
    const fieldUrl = document.getElementById('field-url');
    const urlInput = document.getElementById('url');
    const feedsSection = document.getElementById('feeds-section');

    if (type === 'verified') {
        fieldUrl.style.display = 'none';
        urlInput.required = false;
        feedsSection.style.display = '';
    } else {
        fieldUrl.style.display = '';
        urlInput.required = true;
        feedsSection.style.display = 'none';
    }
}

// Dynamic feeds management
let feedIndex = {{ !empty($feeds) ? count($feeds) : 2 }};

function addFeedRow() {
    const container = document.getElementById('feeds-container');
    const rows = container.querySelectorAll('.feed-row');
    if (rows.length >= 5) {
        alert('Máximo 5 feeds por fuente verificada');
        return;
    }

    const row = document.createElement('div');
    row.className = 'feed-row row g-2 mb-2 align-items-end';
    row.dataset.index = feedIndex;
    row.innerHTML = `
        <div class="col-md-4">
            <label class="form-label small">Nombre del medio</label>
            <input type="text" class="form-control form-control-sm"
                   name="feeds[${feedIndex}][name]" placeholder="Ej: El País">
        </div>
        <div class="col-md-6">
            <label class="form-label small">URL del feed RSS</label>
            <input type="url" class="form-control form-control-sm"
                   name="feeds[${feedIndex}][url]" placeholder="https://" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeFeedRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
    feedIndex++;
    updateAddButton();
}

function removeFeedRow(btn) {
    const container = document.getElementById('feeds-container');
    const rows = container.querySelectorAll('.feed-row');
    if (rows.length <= 2) {
        alert('Mínimo 2 feeds para fuente verificada');
        return;
    }
    btn.closest('.feed-row').remove();
    updateAddButton();
}

function updateAddButton() {
    const container = document.getElementById('feeds-container');
    const rows = container.querySelectorAll('.feed-row');
    const btn = document.getElementById('add-feed-btn');
    btn.disabled = rows.length >= 5;
}
</script>
@endsection
