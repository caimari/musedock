@extends('layouts.app')

@section('title', 'AI Skin Generator')

@section('content')

<div class="container-fluid" style="max-width: 1400px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-palette me-2"></i>AI Skin Generator</h2>
            <p class="text-muted mb-0">Genera skins de tema con IA a partir de una descripcion</p>
        </div>
        <a href="/musedock/themes" class="btn btn-outline-secondary btn-sm text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Volver a Temas
        </a>
    </div>

    <div class="row">
        {{-- LEFT PANEL: Form + Generated Result --}}
        <div class="col-lg-8">

            {{-- STEP 0: AI CONSULTANT (optional) --}}
            <div class="card mb-4" id="consultantCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Paso 1: Consultor de proyecto <span class="badge bg-secondary ms-1">opcional</span></h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleConsultant()">
                        <i class="bi bi-chevron-down" id="consultantChevron"></i>
                    </button>
                </div>
                <div class="card-body" id="consultantBody">
                    <p class="text-muted small mb-3">No sabes como enfocar tu proyecto? Describe tu idea y la IA te ayudara a definir el estilo, sector y enfoque editorial. Luego generara automaticamente la descripcion para el skin.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Proveedor de IA</label>
                            <select id="consultant_provider" class="form-select">
                                <option value="">-- Seleccionar --</option>
                                @foreach($providers as $provider)
                                    <option value="{{ $provider['id'] }}">{{ $provider['name'] }} ({{ $provider['provider'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre del proyecto/dominio</label>
                            <input type="text" id="consultant_name" class="form-control" placeholder="Ej: freenet.es, muse diary, screenai...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Describe tu proyecto</label>
                            <textarea id="consultant_desc" class="form-control" rows="4" placeholder="Ej: 'freenet.es era una red social, ahora queremos convertirla en un medio editorial o blog. No tenemos claro a que sector enfocarlo. Que opinas? Que tipo de contenido funcionaria? Que estilo visual le iria bien?'"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-primary" onclick="consultProject()">
                                <i class="bi bi-chat-dots me-1"></i> Consultar a la IA
                            </button>
                        </div>
                    </div>
                    <div id="consultantLoading" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary spinner-border-sm me-2"></div>
                        <span class="text-muted">Analizando tu proyecto...</span>
                    </div>
                    <div id="consultantResult" class="d-none mt-3">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-2"><i class="bi bi-robot me-1"></i> Analisis del proyecto</h6>
                                <div id="consultantAnalysis" class="small" style="white-space: pre-wrap; line-height: 1.6;"></div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">Descripcion generada para el skin:</small>
                                        <div id="consultantSkinDesc" class="fw-semibold small text-primary"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="useConsultantResult()">
                                        <i class="bi bi-arrow-down-circle me-1"></i> Usar esta descripcion
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- STEP 1: GENERATION FORM --}}
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-magic me-2"></i>Paso 2: Configuracion del Skin</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="ai_provider_id" class="form-label fw-semibold">Proveedor de IA</label>
                            <select id="ai_provider_id" class="form-select" required>
                                <option value="">-- Seleccionar --</option>
                                @foreach($providers as $provider)
                                    <option value="{{ $provider['id'] }}">{{ $provider['name'] }} ({{ $provider['provider'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="base_skin" class="form-label fw-semibold">Skin base (opcional)</label>
                            <select id="base_skin" class="form-select">
                                <option value="">-- Generar desde cero --</option>
                                @foreach($skins as $skin)
                                    <option value="{{ $skin['slug'] }}">{{ $skin['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fijar estructura <span class="badge bg-secondary">opcional</span></label>
                            <select id="fixed_structure" class="form-select">
                                <option value="">-- Libre (la IA/azar elige) --</option>
                                <option value="portfolio">Portfolio/Personal (sidebar + grid + footer minimal)</option>
                                <option value="newspaper">Periodico (logo-above-left + newspaper + footer banner)</option>
                                <option value="magazine">Revista (centered + magazine + footer clasico)</option>
                                <option value="blog-clean">Blog limpio (default + grid + footer minimal)</option>
                                <option value="blog-modern">Blog moderno (tema1 + list + footer banner)</option>
                                <option value="editorial">Editorial clasico (logo-above + newspaper + footer banner)</option>
                                <option value="fashion">Moda/Lifestyle (centered + fashion + footer clasico)</option>
                                <option value="minimal">Ultra minimalista (default + minimal + footer minimal)</option>
                                <option value="corporate">Corporativo (default sticky + grid + footer clasico)</option>
                                <option value="banner">Marca fuerte (banner + magazine + footer banner)</option>
                            </select>
                            <div class="form-text">Fija headers, footers y blog layout. Solo cambian colores y tipografias.</div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="description" class="form-label fw-semibold mb-0">Descripcion del estilo</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="randomPrompt()" title="Ejemplo aleatorio">
                                    <i class="bi bi-dice-5 me-1"></i> Ejemplo
                                </button>
                            </div>
                            <textarea id="description" class="form-control" rows="3" placeholder="Describe el estilo: ej. 'Editorial de noticias tecnologicas, colores azul oscuro y blanco, estilo limpio y profesional'" required></textarea>
                            <div class="form-text">Puedes usar el consultor de arriba para que la IA te ayude a definirlo, o pulsa "Ejemplo" para inspirarte.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Colores de marca (opcional)</label>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-1">
                                    <input type="color" id="brand_color_1" value="#1a2a40" class="form-control form-control-color brand-picker" title="Color primario" style="width:38px;height:38px;padding:2px;">
                                    <small class="text-muted">Primario</small>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <input type="color" id="brand_color_2" value="#ff5e15" class="form-control form-control-color brand-picker" title="Color secundario/acento" style="width:38px;height:38px;padding:2px;">
                                    <small class="text-muted">Acento</small>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <input type="color" id="brand_color_3" value="#ffffff" class="form-control form-control-color brand-picker" title="Color de fondo claro" style="width:38px;height:38px;padding:2px;">
                                    <small class="text-muted">Fondo</small>
                                </div>
                                <label class="form-check form-check-inline ms-2 mb-0">
                                    <input type="checkbox" id="use_brand_colors" class="form-check-input">
                                    <span class="form-check-label small">Usar estos colores</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="button" id="btnGenerate" class="btn btn-primary" onclick="generateSkin()">
                                <i class="bi bi-stars me-1"></i> Generar Skin con IA
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="randomSkin()" title="Genera un skin aleatorio con colores y layouts al azar, sin usar IA">
                                <i class="bi bi-shuffle me-1"></i> Skin aleatorio (sin IA)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- LOADING STATE --}}
            <div id="loadingPanel" class="card mb-4 d-none">
                <div class="card-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Generando...</span>
                    </div>
                    <p class="text-muted mb-0" id="loadingText">Generando skin con IA...</p>
                </div>
            </div>

            {{-- ERROR PANEL --}}
            <div id="errorPanel" class="alert alert-danger d-none mb-4" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <span id="errorMessage"></span>
            </div>

            {{-- GENERATED RESULT --}}
            <div id="resultPanel" class="d-none">

                {{-- Color Swatches by Section --}}
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-palette2 me-2"></i>Resultado generado</h5>
                        <small class="text-muted" id="tokenInfo"></small>
                    </div>
                    <div class="card-body" id="swatchesContainer">
                        {{-- Filled by JS --}}
                    </div>
                </div>

                {{-- Refine + Shuffle --}}
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Refinar</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="shuffleLayouts()" title="Mezclar layouts al azar (sin cambiar colores)">
                            <i class="bi bi-shuffle me-1"></i> Mezclar layouts
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="input-group">
                            <input type="text" id="refinement" class="form-control" placeholder="Ej: 'Hazlo mas oscuro', 'Cambia el header a centered', 'Usa tonos verdes'...">
                            <button class="btn btn-outline-primary" type="button" onclick="refineSkin()">
                                <i class="bi bi-arrow-repeat me-1"></i> Refinar con IA
                            </button>
                        </div>
                        <div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>"Mezclar layouts" cambia header/blog/footer al azar sin tocar colores. "Refinar con IA" usa la IA para aplicar cambios.</div>
                    </div>
                </div>

                {{-- Save --}}
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-floppy me-2"></i>Guardar como Skin</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="skin_name" class="form-label fw-semibold">Nombre del skin</label>
                                <input type="text" id="skin_name" class="form-control" placeholder="Ej: Tech News Dark" required>
                            </div>
                            <div class="col-md-6">
                                <label for="skin_description" class="form-label fw-semibold">Descripcion</label>
                                <input type="text" id="skin_description" class="form-control" placeholder="Breve descripcion del estilo">
                            </div>
                            <div class="col-12">
                                <button type="button" id="btnSave" class="btn btn-success" onclick="saveSkin()">
                                    <i class="bi bi-check-lg me-1"></i> Guardar Skin
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- RIGHT PANEL: Live Preview + Existing Skins --}}
        <div class="col-lg-4">

            {{-- Live Color Preview --}}
            <div class="card mb-4 sticky-top" style="top: 80px; z-index: 10;">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Vista previa</h5>
                </div>
                <div class="card-body p-0" id="previewCard">
                    <div id="previewEmpty" class="text-center py-5 text-muted">
                        <i class="bi bi-image" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">Genera un skin para ver la vista previa</p>
                    </div>
                    <div id="previewContent" class="d-none" style="border-radius: 6px; overflow: hidden; position: relative;"></div>
                </div>
            </div>

            {{-- Existing Skins --}}
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-collection me-2"></i>Skins existentes ({{ count($skins) }})</h5>
                </div>
                <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                    @forelse($skins as $skin)
                        @php
                            $opts = is_string($skin['options']) ? json_decode($skin['options'], true) : $skin['options'];
                            $topbarBg = $opts['topbar']['topbar_bg_color'] ?? '#1a2a40';
                            $headerBg = $opts['header']['header_bg_color'] ?? '#f8f9fa';
                            $footerBg = $opts['footer']['footer_bg_color'] ?? '#f8fafe';
                            $accent = $opts['header']['header_link_hover_color'] ?? '#ff5e15';
                        @endphp
                        <div class="list-group-item">
                            <div class="d-flex align-items-center">
                                <div class="d-flex me-3" style="gap: 3px;">
                                    <div style="width:16px; height:16px; border-radius:3px; background:{{ $topbarBg }}; border:1px solid #ddd;"></div>
                                    <div style="width:16px; height:16px; border-radius:3px; background:{{ $headerBg }}; border:1px solid #ddd;"></div>
                                    <div style="width:16px; height:16px; border-radius:3px; background:{{ $accent }}; border:1px solid #ddd;"></div>
                                    <div style="width:16px; height:16px; border-radius:3px; background:{{ $footerBg }}; border:1px solid #ddd;"></div>
                                </div>
                                <div>
                                    <strong style="font-size: 0.85rem;">{{ $skin['name'] }}</strong>
                                    <div class="text-muted" style="font-size: 0.7rem;">{{ $skin['slug'] }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted py-4">
                            No hay skins guardados
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>

<script>
const csrfToken = '{{ csrf_token() }}';
let currentOptions = null;
let savedSkinNames = {!! json_encode(array_column($skins, 'name')) !!};

// =========================================================================
// CONSULTANT
// =========================================================================
function toggleConsultant() {
    const body = document.getElementById('consultantBody');
    const chevron = document.getElementById('consultantChevron');
    if (body.style.display === 'none') {
        body.style.display = '';
        chevron.className = 'bi bi-chevron-down';
    } else {
        body.style.display = 'none';
        chevron.className = 'bi bi-chevron-right';
    }
}

async function consultProject() {
    const providerId = document.getElementById('consultant_provider').value;
    const projectName = document.getElementById('consultant_name').value.trim();
    const projectDesc = document.getElementById('consultant_desc').value.trim();

    if (!providerId) { alert('Selecciona un proveedor de IA'); return; }
    if (!projectDesc) { alert('Describe tu proyecto'); return; }

    document.getElementById('consultantLoading').classList.remove('d-none');
    document.getElementById('consultantResult').classList.add('d-none');

    try {
        const formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('ai_provider_id', providerId);
        formData.append('project_name', projectName);
        formData.append('project_description', projectDesc);

        const resp = await fetch('/musedock/ai-skin-generator/consult', {
            method: 'POST',
            body: formData,
        });
        const data = await resp.json();

        document.getElementById('consultantLoading').classList.add('d-none');

        if (data.success) {
            document.getElementById('consultantAnalysis').textContent = data.analysis;
            document.getElementById('consultantSkinDesc').textContent = data.skin_description;
            document.getElementById('consultantResult').classList.remove('d-none');

            // Sync provider selection
            if (providerId && !document.getElementById('ai_provider_id').value) {
                document.getElementById('ai_provider_id').value = providerId;
            }
        } else {
            alert(data.error || 'Error al consultar');
        }
    } catch (err) {
        document.getElementById('consultantLoading').classList.add('d-none');
        alert('Error: ' + err.message);
    }
}

function useConsultantResult() {
    const desc = document.getElementById('consultantSkinDesc').textContent;
    if (desc) {
        document.getElementById('description').value = desc;
        // Sync provider
        const cp = document.getElementById('consultant_provider').value;
        if (cp) document.getElementById('ai_provider_id').value = cp;
        // Scroll to generation form
        document.getElementById('description').scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('description').focus();
    }
}

// =========================================================================
// RANDOM PROMPT EXAMPLES
// =========================================================================
const promptExamples = [
    "Periodico digital de noticias internacionales, tonos azul marino y blanco, estilo serio y profesional, tipografia serif Playfair Display para titulos, sans-serif Roboto para texto, layout newspaper con overlay y ticker de ultimas noticias, topbar oscuro con reloj",
    "Revista femenina de moda y belleza, tonos rosa empolvado y dorado con fondo crema, estilo elegante y sofisticado, tipografia serif Playfair Display, layout fashion con header centered",
    "Blog de tecnologia y startups, tonos azul electrico y negro con acentos verde neon, estilo futurista y moderno, tipografia sans-serif Montserrat, layout grid con topbar oscuro y header tema1",
    "Portal de gastronomia y recetas, tonos terracota, crema y verde oliva, estilo calido y acogedor, tipografia serif Merriweather, layout magazine con footer banner grande",
    "Medio de deportes y fitness, tonos rojo intenso y gris oscuro, estilo dinamico y energico, tipografia sans-serif Oswald en mayusculas, layout newspaper con ticker, header default sticky",
    "Blog de viajes y aventura, tonos turquesa y arena con acentos coral, estilo fresco y luminoso, tipografia sans-serif Quicksand, layout grid con header logo-above centrado",
    "Revista de arte y cultura, tonos negro y dorado con fondo blanco roto, estilo minimalista y premium, tipografia serif Georgia, layout minimal sin imagenes, footer minimal",
    "Portal de finanzas personales, tonos azul oscuro y verde esmeralda, estilo corporativo y confiable, tipografia sans-serif Lato, layout list con sidebar de categorias, topbar con email",
    "Magazine de cine y series, tonos purpura oscuro y amarillo mostaza, estilo cinematografico, tipografia sans-serif Poppins, layout magazine con hero oscuro, footer banner",
    "Blog de maternidad y familia, tonos lavanda suave y melocoton con blanco, estilo dulce y acogedor, tipografia sans-serif Nunito, layout grid con header centered y footer clasico",
    "Medio de politica y opinion, tonos granate y gris grafito, estilo sobrio y autoritativo, tipografia serif Times New Roman, layout newspaper con ticker y reloj en topbar y header",
    "Portal de musica y festivales, tonos negro y magenta con acentos amarillo, estilo bold y urbano, tipografia sans-serif Oswald, layout fashion con topbar oscuro",
    "Blog de arquitectura y diseno, tonos gris claro y negro con acento naranja, estilo clean y geometrico, tipografia sans-serif Raleway, layout grid boxed con footer minimal",
    "Revista de salud y bienestar, tonos verde menta y blanco con acentos azul cielo, estilo fresco y limpio, tipografia sans-serif Open Sans, layout list con sidebar busqueda",
    "Portal educativo y academico, tonos azul royal y blanco con bordes grises, estilo formal y organizado, tipografia sans-serif Roboto, layout grid con header default sticky y footer clasico",
    "Blog de fotografia y portfolio, tonos negro puro y blanco con minimo color, estilo galeria minimalista, tipografia sans-serif Helvetica, layout fashion con header sidebar lateral y footer minimal",
    "Medio de economia y mercados, tonos verde oscuro y dorado, estilo serio y financiero, tipografia serif Georgia, layout newspaper con ticker de noticias y reloj, header logo-above-left",
    "Revista de lifestyle urbano, tonos gris carbon y coral, estilo moderno y cool, tipografia sans-serif Montserrat, layout magazine con header centered y CTA suscribirse",
    "Blog de desarrollo personal y coaching, tonos indigo y blanco con acentos dorado, estilo inspirador y premium, tipografia serif Playfair Display para titulos, layout list con hero suave",
    "Portal de ciencia y divulgacion, tonos azul profundo y cian con fondo oscuro, estilo cosmos/espacial, tipografia sans-serif Poppins, layout newspaper con overlay, topbar oscuro con reloj",
];

function randomPrompt() {
    const textarea = document.getElementById('description');
    const example = promptExamples[Math.floor(Math.random() * promptExamples.length)];
    textarea.value = example;
    textarea.style.transition = 'background 0.3s';
    textarea.style.background = '#f0f9ff';
    setTimeout(() => textarea.style.background = '', 500);
}

// =========================================================================
// STRUCTURE TEMPLATES (fixed layouts, only colors/fonts change)
// =========================================================================
const structureTemplates = {
    portfolio: {
        topbar: { topbar_enabled:'0' },
        header: { header_layout:'sidebar', header_search_enabled:'0', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'0', header_cta_enabled:'0', header_menu_uppercase:'1', header_content_width:'full' },
        blog: { blog_layout:'grid', newspaper_overlay:'0', blog_header_ticker:'0', blog_topbar_ticker:'0', blog_sidebar_search:'0', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'0', blog_pagination_style:'minimal' },
        footer: { footer_layout:'minimal', footer_content_width:'full' },
    },
    newspaper: {
        topbar: { topbar_enabled:'1', topbar_show_social:'1', topbar_clock:'1' },
        header: { header_layout:'logo-above-left', header_search_enabled:'1', header_social_enabled:'1', header_clock_enabled:'1', header_sticky:'0', header_cta_enabled:'0', header_content_width:'boxed' },
        blog: { blog_layout:'newspaper', newspaper_overlay:'1', blog_header_ticker:'1', blog_topbar_ticker:'1', blog_sidebar_search:'1', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'1', blog_pagination_style:'minimal' },
        footer: { footer_layout:'banner', footer_content_width:'boxed' },
    },
    magazine: {
        topbar: { topbar_enabled:'1', topbar_show_social:'1', topbar_clock:'0' },
        header: { header_layout:'centered', header_search_enabled:'1', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'0', header_cta_enabled:'1', header_content_width:'full' },
        blog: { blog_layout:'magazine', newspaper_overlay:'0', blog_header_ticker:'0', blog_topbar_ticker:'0', blog_sidebar_search:'1', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'1', blog_pagination_style:'rounded' },
        footer: { footer_layout:'default', footer_content_width:'full' },
    },
    'blog-clean': {
        topbar: { topbar_enabled:'0' },
        header: { header_layout:'default', header_search_enabled:'1', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'1', header_cta_enabled:'0', header_content_width:'boxed' },
        blog: { blog_layout:'grid', newspaper_overlay:'0', blog_header_ticker:'0', blog_topbar_ticker:'0', blog_sidebar_search:'1', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'1', blog_pagination_style:'pill' },
        footer: { footer_layout:'minimal', footer_content_width:'boxed' },
    },
    'blog-modern': {
        topbar: { topbar_enabled:'1', topbar_show_social:'0', topbar_clock:'1' },
        header: { header_layout:'tema1', header_search_enabled:'1', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'1', header_cta_enabled:'1', header_content_width:'full' },
        blog: { blog_layout:'list', newspaper_overlay:'0', blog_header_ticker:'0', blog_topbar_ticker:'0', blog_sidebar_search:'1', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'0', blog_pagination_style:'underline' },
        footer: { footer_layout:'banner', footer_content_width:'full' },
    },
    editorial: {
        topbar: { topbar_enabled:'1', topbar_show_social:'1', topbar_clock:'1' },
        header: { header_layout:'logo-above', header_search_enabled:'1', header_social_enabled:'1', header_clock_enabled:'1', header_sticky:'0', header_cta_enabled:'0', header_content_width:'boxed' },
        blog: { blog_layout:'newspaper', newspaper_overlay:'1', blog_header_ticker:'1', blog_topbar_ticker:'0', blog_sidebar_search:'1', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'1', blog_pagination_style:'minimal' },
        footer: { footer_layout:'banner', footer_content_width:'boxed' },
    },
    fashion: {
        topbar: { topbar_enabled:'0' },
        header: { header_layout:'centered', header_search_enabled:'1', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'0', header_cta_enabled:'0', header_menu_uppercase:'1', header_content_width:'full' },
        blog: { blog_layout:'fashion', newspaper_overlay:'0', blog_header_ticker:'0', blog_topbar_ticker:'0', blog_sidebar_search:'0', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'0', blog_pagination_style:'dots' },
        footer: { footer_layout:'default', footer_content_width:'full' },
    },
    minimal: {
        topbar: { topbar_enabled:'0' },
        header: { header_layout:'default', header_search_enabled:'0', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'0', header_cta_enabled:'0', header_content_width:'boxed' },
        blog: { blog_layout:'minimal', newspaper_overlay:'0', blog_header_ticker:'0', blog_topbar_ticker:'0', blog_sidebar_search:'0', blog_sidebar_related_posts:'0', blog_sidebar_tags:'0', blog_sidebar_categories:'0', blog_pagination_style:'minimal' },
        footer: { footer_layout:'minimal', footer_content_width:'boxed' },
    },
    corporate: {
        topbar: { topbar_enabled:'1', topbar_show_social:'0', topbar_clock:'0' },
        header: { header_layout:'default', header_search_enabled:'1', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'1', header_cta_enabled:'1', header_content_width:'full' },
        blog: { blog_layout:'grid', newspaper_overlay:'0', blog_header_ticker:'0', blog_topbar_ticker:'0', blog_sidebar_search:'1', blog_sidebar_related_posts:'1', blog_sidebar_tags:'0', blog_sidebar_categories:'1', blog_pagination_style:'rounded' },
        footer: { footer_layout:'default', footer_content_width:'full' },
    },
    banner: {
        topbar: { topbar_enabled:'1', topbar_show_social:'1', topbar_clock:'0' },
        header: { header_layout:'banner', header_search_enabled:'0', header_social_enabled:'0', header_clock_enabled:'0', header_sticky:'0', header_cta_enabled:'1', header_content_width:'full' },
        blog: { blog_layout:'magazine', newspaper_overlay:'0', blog_header_ticker:'1', blog_topbar_ticker:'0', blog_sidebar_search:'1', blog_sidebar_related_posts:'1', blog_sidebar_tags:'1', blog_sidebar_categories:'1', blog_pagination_style:'pill' },
        footer: { footer_layout:'banner', footer_content_width:'full' },
    },
};

function applyStructureTemplate(options) {
    const templateKey = document.getElementById('fixed_structure').value;
    if (!templateKey || !structureTemplates[templateKey]) return options;

    const tmpl = structureTemplates[templateKey];
    for (const [section, overrides] of Object.entries(tmpl)) {
        if (!options[section]) options[section] = {};
        Object.assign(options[section], overrides);
    }
    return options;
}

// =========================================================================
// RANDOM SKIN (no AI - full random generation)
// =========================================================================
function randomSkin() {
    const pick = arr => arr[Math.floor(Math.random() * arr.length)];
    const randBool = () => Math.random() > 0.5 ? '1' : '0';

    // Predefined color palettes (harmonious combinations)
    const palettes = [
        { name:'Ocean', primary:'#0f172a', accent:'#0ea5e9', bg:'#f8fafc', text:'#1e293b', muted:'#64748b', light:'#e2e8f0' },
        { name:'Sunset', primary:'#1c1917', accent:'#f97316', bg:'#fffbeb', text:'#292524', muted:'#78716c', light:'#fef3c7' },
        { name:'Forest', primary:'#14532d', accent:'#22c55e', bg:'#f0fdf4', text:'#166534', muted:'#6b7280', light:'#dcfce7' },
        { name:'Berry', primary:'#4a044e', accent:'#d946ef', bg:'#fdf4ff', text:'#581c87', muted:'#a855f7', light:'#f3e8ff' },
        { name:'Crimson', primary:'#450a0a', accent:'#ef4444', bg:'#fef2f2', text:'#7f1d1d', muted:'#9ca3af', light:'#fee2e2' },
        { name:'Slate', primary:'#0f172a', accent:'#6366f1', bg:'#f8fafc', text:'#1e293b', muted:'#64748b', light:'#e2e8f0' },
        { name:'Gold', primary:'#1a1a2e', accent:'#d4af37', bg:'#fefce8', text:'#1c1917', muted:'#a3a3a3', light:'#fef9c3' },
        { name:'Rose', primary:'#4c0519', accent:'#f43f5e', bg:'#fff1f2', text:'#881337', muted:'#a3a3a3', light:'#ffe4e6' },
        { name:'Teal', primary:'#134e4a', accent:'#14b8a6', bg:'#f0fdfa', text:'#115e59', muted:'#6b7280', light:'#ccfbf1' },
        { name:'Amber', primary:'#451a03', accent:'#f59e0b', bg:'#fffbeb', text:'#78350f', muted:'#92400e', light:'#fef3c7' },
        { name:'Sky', primary:'#082f49', accent:'#38bdf8', bg:'#f0f9ff', text:'#0c4a6e', muted:'#64748b', light:'#e0f2fe' },
        { name:'Coral', primary:'#1e1b18', accent:'#fb923c', bg:'#fff7ed', text:'#431407', muted:'#a8a29e', light:'#ffedd5' },
        { name:'Midnight', primary:'#020617', accent:'#818cf8', bg:'#f8fafc', text:'#1e293b', muted:'#94a3b8', light:'#e2e8f0' },
        { name:'Olive', primary:'#1a2e05', accent:'#84cc16', bg:'#f7fee7', text:'#365314', muted:'#6b7280', light:'#ecfccb' },
        { name:'Wine', primary:'#2d1b2e', accent:'#be185d', bg:'#fdf2f8', text:'#4a044e', muted:'#9ca3af', light:'#fce7f3' },
    ];

    const headerLayouts = ['default', 'left', 'centered', 'logo-above', 'logo-above-left', 'tema1', 'banner'];
    const blogLayouts = ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion'];
    const footerLayouts = ['default', 'banner', 'minimal'];
    const paginationStyles = ['minimal', 'rounded', 'pill', 'underline', 'dots'];
    const cookieLayouts = ['card', 'bar', 'modal'];
    const fonts = ["'Poppins', sans-serif", "'Montserrat', sans-serif", "'Roboto', sans-serif", "'Open Sans', sans-serif", "'Lato', sans-serif", "'Raleway', sans-serif", "'Oswald', sans-serif", "'Playfair Display', serif", "'Merriweather', serif", "'Nunito', sans-serif", "'Quicksand', sans-serif"];
    const heroFonts = [...fonts, "inherit"];
    const sizes = ['30', '40', '45', '55', '65', '80'];

    // Mix colors from different palettes for unique combinations
    const p1 = pick(palettes);
    const p2 = pick(palettes);
    const p3 = pick(palettes);
    const p = {
        name: p1.name + '/' + p2.name,
        primary: pick([p1.primary, p2.primary, p3.primary]),
        accent: pick([p1.accent, p2.accent, p3.accent]),
        bg: pick([p1.bg, p2.bg, p3.bg, '#ffffff', '#f8f9fa', '#fafafa']),
        text: pick([p1.text, p2.text, '#1a1a1a', '#1e293b', '#292524']),
        muted: pick([p1.muted, p2.muted, '#6b7280', '#9ca3af']),
        light: pick([p1.light, p2.light, p3.light, '#f0f0f0', '#e5e7eb']),
    };
    // Ensure contrast: if primary is light, swap with a dark one
    const lum = (hex) => { const r=parseInt(hex.slice(1,3),16)/255, g=parseInt(hex.slice(3,5),16)/255, b=parseInt(hex.slice(5,7),16)/255; return 0.2126*r+0.7152*g+0.0722*b; };
    if (lum(p.primary) > 0.4) p.primary = pick(palettes).primary;
    if (lum(p.bg) < 0.6) p.bg = pick(['#ffffff', '#f8f9fa', '#fafafa', '#f0fdf4', '#fef2f2', '#f0f9ff']);
    // Ensure accent has enough saturation (not too close to gray)
    if (Math.abs(lum(p.accent) - lum(p.bg)) < 0.15) p.accent = pick(palettes).accent;

    const hLayout = pick(headerLayouts);
    const bLayout = pick(blogLayouts);
    const fLayout = pick(footerLayouts);
    const menuFont = pick(fonts);
    const logoFont = pick(fonts);
    const heroFont = pick(heroFonts);

    currentOptions = {
        topbar: {
            topbar_enabled: '1',
            topbar_show_address: '0',
            topbar_show_email: '1',
            topbar_show_whatsapp: randBool(),
            topbar_whatsapp_icon: 'whatsapp',
            topbar_show_social: '1',
            topbar_bg_color: p.primary,
            topbar_text_color: '#ffffff',
            topbar_clock: ['logo-above', 'logo-above-left', 'newspaper'].some(x => hLayout.includes(x) || bLayout.includes(x)) ? '1' : randBool(),
            topbar_ticker_clock: '0',
            topbar_clock_locale: 'es',
            topbar_clock_timezone: 'Europe/Madrid',
        },
        header: {
            header_layout: hLayout,
            header_content_width: pick(['full', 'boxed']),
            header_logo_max_height: pick(sizes),
            header_bg_color: p.bg,
            header_logo_text_color: p.text,
            header_logo_font: logoFont,
            header_link_color: p.text,
            header_link_hover_color: p.accent,
            header_menu_font: menuFont,
            header_menu_uppercase: randBool(),
            header_tagline_color: p.muted,
            header_tagline_enabled: '1',
            header_sticky: randBool(),
            header_cta_enabled: randBool(),
            header_cta_text_es: pick(['Suscribirse', 'Contacto', 'Acceder', 'Registrarse']),
            header_cta_text_en: pick(['Subscribe', 'Contact', 'Login', 'Sign Up']),
            header_cta_url: '#',
            header_cta_bg_color: p.accent,
            header_cta_text_color: '#ffffff',
            header_lang_selector_enabled: '1',
            header_search_enabled: ['logo-above', 'logo-above-left', 'centered'].includes(hLayout) ? '1' : randBool(),
            header_social_enabled: ['logo-above', 'logo-above-left'].includes(hLayout) ? '1' : '0',
            header_clock_enabled: ['logo-above', 'logo-above-left'].includes(hLayout) ? '1' : '0',
        },
        hero: {
            hero_title_color: '#ffffff',
            hero_title_font: heroFont,
            hero_subtitle_color: '#e5e5e5',
            hero_overlay_color: '#000000',
            hero_overlay_opacity: pick(['0.3', '0.4', '0.5', '0.6']),
        },
        blog: {
            blog_layout: bLayout,
            newspaper_overlay: bLayout === 'newspaper' ? randBool() : '0',
            blog_header_ticker: ['newspaper', 'magazine'].includes(bLayout) ? randBool() : '0',
            blog_topbar_ticker: '0',
            blog_ticker_bg_color: '#ffffff',
            blog_ticker_border_color: p.light,
            blog_ticker_label_bg: p.primary,
            blog_ticker_label_text: '#ffffff',
            blog_ticker_tag_bg: p.light,
            blog_ticker_tag_text: p.text,
            blog_ticker_post_text: p.text,
            blog_sidebar_search: randBool(),
            blog_sidebar_search_order: '1',
            blog_sidebar_related_posts: '1',
            blog_sidebar_related_posts_count: pick(['3', '4', '6']),
            blog_sidebar_related_posts_order: '2',
            blog_sidebar_tags: '1',
            blog_sidebar_tags_order: '3',
            blog_sidebar_categories: '1',
            blog_sidebar_categories_order: '4',
            blog_pagination_style: pick(paginationStyles),
        },
        footer: {
            footer_layout: fLayout,
            footer_content_width: pick(['full', 'boxed']),
            footer_logo_max_height: '50',
            footer_bg_color: p.primary,
            footer_bottom_bg_color: p.primary.replace(/[0-9a-f]/gi, c => Math.max(0, parseInt(c, 16) - 2).toString(16)),
            footer_text_color: '#d4d4d4',
            footer_heading_color: '#ffffff',
            footer_link_color: '#a3a3a3',
            footer_link_hover_color: p.accent,
            footer_icon_color: p.accent,
            footer_border_color: p.muted,
            footer_cookie_icon_enabled: '1',
            footer_cookie_icon: pick(['emoji', 'fa-cookie-bite', 'fa-shield-alt', 'none']),
            footer_cookie_banner_layout: pick(cookieLayouts),
        },
        scroll_to_top: {
            scroll_to_top_enabled: '1',
            scroll_to_top_bg_color: p.accent,
            scroll_to_top_icon_color: '#ffffff',
            scroll_to_top_hover_bg_color: p.primary,
        },
    };

    // Apply fixed structure if selected
    currentOptions = applyStructureTemplate(currentOptions);

    renderSwatches(currentOptions);
    updatePreview(currentOptions);
    showResult();
    const tmplName = document.getElementById('fixed_structure').value;
    document.getElementById('tokenInfo').textContent = 'Paleta: ' + p.name + (tmplName ? ' | Estructura: ' + tmplName : '') + ' | Sin IA';

    Swal.fire({
        icon: 'info',
        title: 'Skin aleatorio generado',
        html: '<div class="d-flex justify-content-center gap-1 mb-2">' +
              '<div style="width:28px;height:28px;border-radius:50%;background:'+p.primary+';border:2px solid #ddd;" title="Primary"></div>' +
              '<div style="width:28px;height:28px;border-radius:50%;background:'+p.accent+';border:2px solid #ddd;" title="Accent"></div>' +
              '<div style="width:28px;height:28px;border-radius:50%;background:'+p.bg+';border:2px solid #ddd;" title="Bg"></div>' +
              '<div style="width:28px;height:28px;border-radius:50%;background:'+p.text+';border:2px solid #ddd;" title="Text"></div>' +
              '</div>' +
              '<small>Mix: <strong>' + p.name + '</strong><br>Header: <strong>' + hLayout + '</strong> | Blog: <strong>' + bLayout + '</strong> | Footer: <strong>' + fLayout + '</strong></small>',
        timer: 3000,
        showConfirmButton: false
    });
}

// =========================================================================
// SKIN GENERATION
// =========================================================================
function generateSkin() {
    const providerId = document.getElementById('ai_provider_id').value;
    const description = document.getElementById('description').value.trim();
    const baseSkin = document.getElementById('base_skin').value;
    let brandColors = '';
    if (document.getElementById('use_brand_colors').checked) {
        brandColors = [
            document.getElementById('brand_color_1').value,
            document.getElementById('brand_color_2').value,
            document.getElementById('brand_color_3').value
        ].join(', ');
    }

    if (!providerId) { alert('Selecciona un proveedor de IA'); return; }
    if (!description) { alert('Escribe una descripcion del estilo'); return; }

    showLoading('Generando skin con IA...');
    hideError();
    hideResult();

    const formData = new FormData();
    const fixedStructure = document.getElementById('fixed_structure').value;

    formData.append('_token', csrfToken);
    formData.append('ai_provider_id', providerId);
    formData.append('description', description);
    formData.append('base_skin', baseSkin);
    formData.append('brand_colors', brandColors);
    formData.append('fixed_structure', fixedStructure);

    fetch('/musedock/ai-skin-generator/generate', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            currentOptions = applyStructureTemplate(data.options);
            renderSwatches(currentOptions);
            updatePreview(currentOptions);
            document.getElementById('tokenInfo').textContent = `${data.tokens} tokens | ${data.model}`;
            showResult();
        } else {
            showError(data.error || 'Error desconocido');
            if (data.raw) {
                console.log('Raw AI response:', data.raw);
            }
        }
    })
    .catch(err => {
        hideLoading();
        showError('Error de red: ' + err.message);
    });
}

function refineSkin() {
    const providerId = document.getElementById('ai_provider_id').value;
    const modification = document.getElementById('refinement').value.trim();

    if (!providerId) { alert('Selecciona un proveedor de IA'); return; }
    if (!modification) { alert('Describe que cambios quieres hacer'); return; }
    if (!currentOptions) { alert('Primero genera un skin'); return; }

    // Show thinking modal
    Swal.fire({
        title: '<i class="bi bi-arrow-repeat text-primary"></i> Refinando...',
        html: '<p class="text-muted">La IA esta aplicando tus cambios al skin</p>',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    const formData = new FormData();
    formData.append('_token', csrfToken);
    formData.append('ai_provider_id', providerId);
    formData.append('current_options', JSON.stringify(currentOptions));
    formData.append('modification', modification);

    fetch('/musedock/ai-skin-generator/refine', {
        method: 'POST',
        body: formData,
    })
    .then(r => r.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            currentOptions = data.options;
            renderSwatches(data.options);
            updatePreview(data.options);
            document.getElementById('tokenInfo').textContent = `${data.tokens} tokens | ${data.model}`;
            document.getElementById('refinement').value = '';
            Swal.fire({icon:'success', title:'Refinado', text:'Skin actualizado correctamente', timer:1500, showConfirmButton:false});
        } else {
            Swal.fire({icon:'error', title:'Error', text: data.error || 'Error al refinar'});
        }
    })
    .catch(err => {
        Swal.fire({icon:'error', title:'Error de red', text: err.message});
    });
}

function saveSkin() {
    const skinName = document.getElementById('skin_name').value.trim();
    const skinDescription = document.getElementById('skin_description').value.trim();

    if (!skinName) { alert('Escribe un nombre para el skin'); return; }
    if (!currentOptions) { alert('Primero genera un skin'); return; }

    // Generate slug same way as backend
    const skinSlug = skinName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'skin';

    // Check against ALL known names AND slugs
    const nameExists = savedSkinNames.some(n => n.toLowerCase() === skinName.toLowerCase())
                    || savedSkinNames.some(n => n.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') === skinSlug);

    function doSave() {
        Swal.fire({
            title: '<i class="bi bi-floppy text-primary"></i> Guardando...',
            html: '<p class="text-muted">Guardando skin en la base de datos</p>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        const formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('skin_name', skinName);
        formData.append('skin_description', skinDescription);
        formData.append('options', JSON.stringify(currentOptions));

        fetch('/musedock/ai-skin-generator/save', {
            method: 'POST',
            body: formData,
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update local list of saved names
                if (!savedSkinNames.some(n => n.toLowerCase() === skinName.toLowerCase())) {
                    savedSkinNames.push(skinName);
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Skin guardado',
                    html: '<p>El skin <strong>' + skinName + '</strong> se ha guardado correctamente.</p><small class="text-muted">Ya esta disponible en el catalogo de skins para aplicar a cualquier tenant.</small>',
                    confirmButtonColor: '#22c55e'
                });
            } else {
                Swal.fire({icon:'error', title:'Error', text: data.error || 'Error al guardar'});
            }
        })
        .catch(err => {
            Swal.fire({icon:'error', title:'Error de red', text: err.message});
        });
    }

    if (nameExists) {
        Swal.fire({
            title: '<i class="bi bi-exclamation-triangle text-warning"></i> Skin existente',
            html: '<p>Ya existe un skin llamado <strong>' + skinName + '</strong>.</p><p>Quieres sobreescribirlo?</p>',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-arrow-repeat me-1"></i> Sobreescribir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f59e0b'
        }).then(r => { if (r.isConfirmed) doSave(); });
    } else {
        doSave();
    }
}

// =========================================================================
// RANDOM LAYOUT SHUFFLE (changes layouts without changing colors)
// =========================================================================
function shuffleLayouts() {
    if (!currentOptions) { alert('Primero genera un skin'); return; }

    const headerLayouts = ['default', 'left', 'centered', 'logo-above', 'logo-above-left', 'tema1', 'banner'];
    const blogLayouts = ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion'];
    const footerLayouts = ['default', 'banner', 'minimal'];
    const paginationStyles = ['minimal', 'rounded', 'pill', 'underline', 'dots'];
    const cookieLayouts = ['card', 'bar', 'modal'];

    const pick = arr => arr[Math.floor(Math.random() * arr.length)];

    const newHeader = pick(headerLayouts);
    const newBlog = pick(blogLayouts);

    // Header
    if (currentOptions.header) {
        currentOptions.header.header_layout = newHeader;
        // Enable features that make sense for the layout
        currentOptions.header.header_search_enabled = Math.random() > 0.5 ? '1' : '0';
        currentOptions.header.header_social_enabled = ['logo-above', 'logo-above-left'].includes(newHeader) ? '1' : '0';
        currentOptions.header.header_clock_enabled = ['logo-above', 'logo-above-left'].includes(newHeader) ? '1' : '0';
        currentOptions.header.header_sticky = Math.random() > 0.7 ? '1' : '0';
    }

    // Blog
    if (currentOptions.blog) {
        currentOptions.blog.blog_layout = newBlog;
        currentOptions.blog.newspaper_overlay = newBlog === 'newspaper' ? (Math.random() > 0.3 ? '1' : '0') : '0';
        currentOptions.blog.blog_header_ticker = ['newspaper', 'magazine'].includes(newBlog) ? (Math.random() > 0.3 ? '1' : '0') : '0';
        currentOptions.blog.blog_pagination_style = pick(paginationStyles);
    }

    // Footer
    if (currentOptions.footer) {
        currentOptions.footer.footer_layout = pick(footerLayouts);
        currentOptions.footer.footer_cookie_banner_layout = pick(cookieLayouts);
    }

    // Topbar
    if (currentOptions.topbar) {
        currentOptions.topbar.topbar_clock = Math.random() > 0.5 ? '1' : '0';
    }

    renderSwatches(currentOptions);
    updatePreview(currentOptions);

    Swal.fire({icon:'info', title:'Layouts mezclados', html: '<small>Header: <strong>' + newHeader + '</strong> | Blog: <strong>' + newBlog + '</strong> | Footer: <strong>' + (currentOptions.footer?.footer_layout || 'default') + '</strong></small>', timer:2500, showConfirmButton:false});
}

// =========================================================================
// UI HELPERS
// =========================================================================

function showLoading(text) {
    document.getElementById('loadingPanel').classList.remove('d-none');
    document.getElementById('loadingText').textContent = text || 'Procesando...';
    document.getElementById('btnGenerate').disabled = true;
}

function hideLoading() {
    document.getElementById('loadingPanel').classList.add('d-none');
    document.getElementById('btnGenerate').disabled = false;
}

function showError(msg) {
    const el = document.getElementById('errorPanel');
    el.classList.remove('d-none');
    document.getElementById('errorMessage').textContent = msg;
}

function hideError() {
    document.getElementById('errorPanel').classList.add('d-none');
}

function showResult() {
    document.getElementById('resultPanel').classList.remove('d-none');
}

function hideResult() {
    document.getElementById('resultPanel').classList.add('d-none');
}

// =========================================================================
// RENDER SWATCHES
// =========================================================================

const sectionLabels = {
    topbar: 'Barra superior',
    header: 'Cabecera',
    hero: 'Hero',
    blog: 'Blog',
    footer: 'Pie de pagina',
    scroll_to_top: 'Boton volver arriba'
};

function renderSwatches(options) {
    const container = document.getElementById('swatchesContainer');
    let html = '';

    for (const [section, opts] of Object.entries(options)) {
        if (section === 'custom_code') continue;
        const label = sectionLabels[section] || section;

        html += `<div class="mb-3">`;
        html += `<h6 class="fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px; color: #666;">${label}</h6>`;
        html += `<div class="d-flex flex-wrap gap-2">`;

        for (const [key, value] of Object.entries(opts)) {
            if (typeof value === 'string' && value.match(/^#[0-9a-fA-F]{6}$/)) {
                // Color swatch
                const shortKey = key.replace(section.replace('scroll_to_top', 'scroll_to_top') + '_', '').replace(/_/g, ' ');
                html += `<div class="text-center" style="width: 70px;">
                    <div style="width:36px; height:36px; border-radius:6px; background:${value}; border:1px solid #ddd; margin: 0 auto;"></div>
                    <div style="font-size:0.6rem; color:#999; margin-top:2px;">${shortKey}</div>
                    <div style="font-size:0.6rem; font-family:monospace; color:#666;">${value}</div>
                </div>`;
            } else if (typeof value === 'boolean' || value === 'true' || value === 'false' || value === '0' || value === '1') {
                const isOn = value === true || value === 'true' || value === '1';
                const shortKey = key.replace(section.replace('scroll_to_top', 'scroll_to_top') + '_', '').replace(/_/g, ' ');
                html += `<div class="text-center" style="width: 70px;">
                    <div style="width:36px; height:36px; border-radius:6px; background:${isOn ? '#d4edda' : '#f8d7da'}; border:1px solid #ddd; margin: 0 auto; display:flex; align-items:center; justify-content:center;">
                        <i class="bi ${isOn ? 'bi-check-lg text-success' : 'bi-x-lg text-danger'}" style="font-size:1rem;"></i>
                    </div>
                    <div style="font-size:0.6rem; color:#999; margin-top:2px;">${shortKey}</div>
                </div>`;
            } else if (typeof value === 'string' && !value.startsWith('#')) {
                const shortKey = key.replace(section.replace('scroll_to_top', 'scroll_to_top') + '_', '').replace(/_/g, ' ');
                html += `<div class="text-center" style="width: 70px;">
                    <div style="width:36px; height:36px; border-radius:6px; background:#e9ecef; border:1px solid #ddd; margin: 0 auto; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-gear" style="font-size:0.8rem; color:#666;"></i>
                    </div>
                    <div style="font-size:0.6rem; color:#999; margin-top:2px;">${shortKey}</div>
                    <div style="font-size:0.55rem; font-family:monospace; color:#666; max-width:70px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${value}">${value}</div>
                </div>`;
            }
        }

        html += `</div></div>`;
    }

    container.innerHTML = html;
}

// =========================================================================
// UPDATE LIVE PREVIEW
// =========================================================================

function updatePreview(options) {
    document.getElementById('previewEmpty').classList.add('d-none');
    const container = document.getElementById('previewContent');
    container.classList.remove('d-none');

    const t = options.topbar || {};
    const h = options.header || {};
    const hero = options.hero || {};
    const blog = options.blog || {};
    const f = options.footer || {};
    const sc = options.scroll_to_top || {};

    const hLayout = h.header_layout || 'default';
    const bLayout = blog.blog_layout || 'grid';
    const fLayout = f.footer_layout || 'default';
    const topbarOn = t.topbar_enabled !== '0';
    const stickyOn = h.header_sticky === '1';
    const clockOn = h.header_clock_enabled === '1';
    const socialOn = h.header_social_enabled === '1';
    const searchOn = h.header_search_enabled === '1';
    const ctaOn = h.header_cta_enabled === '1';
    const tickerOn = blog.blog_header_ticker === '1';
    const uppercase = h.header_menu_uppercase === '1' ? 'text-transform:uppercase;' : '';
    const logoFont = (h.header_logo_font && h.header_logo_font !== 'inherit') ? 'font-family:'+h.header_logo_font+';' : '';
    const menuFont = (h.header_menu_font && h.header_menu_font !== 'inherit') ? 'font-family:'+h.header_menu_font+';' : '';
    const heroFont = (hero.hero_title_font && hero.hero_title_font !== 'inherit') ? 'font-family:'+hero.hero_title_font+';' : '';

    const layoutLabel = {'default':'Default','left':'Left','centered':'Centered','logo-above':'Logo Above','logo-above-left':'Logo Above Left','tema1':'Tema1','banner':'Banner','sidebar':'Sidebar'};
    const blogLabel = {'grid':'Grid','list':'List','magazine':'Magazine','minimal':'Minimal','newspaper':'Newspaper','fashion':'Fashion'};
    const footerLabel = {'default':'Clasico','banner':'Banner','minimal':'Minimal'};

    let html = '';

    // === TOPBAR ===
    if (topbarOn) {
        html += `<div style="background:${t.topbar_bg_color||'#1a2a40'};color:${t.topbar_text_color||'#fff'};padding:3px 10px;font-size:0.55rem;display:flex;justify-content:space-between;align-items:center;">`;
        html += `<span><i class="bi bi-envelope"></i> info@site.com</span>`;
        html += `<span>`;
        if (t.topbar_clock === '1') html += `<i class="bi bi-clock me-1"></i>Sab, 28 mar `;
        if (t.topbar_show_social !== '0') html += `<i class="bi bi-facebook ms-1"></i><i class="bi bi-instagram ms-1"></i>`;
        html += `</span></div>`;
    }

    // === HEADER ===
    const hBg = h.header_bg_color || '#f8f9fa';
    const logoColor = h.header_logo_text_color || '#1a2a40';
    const linkColor = h.header_link_color || '#333';
    const accentColor = h.header_link_hover_color || '#ff5e15';

    const menuHtml = `<span style="font-size:0.6rem;${menuFont}${uppercase}color:${linkColor};">Inicio &nbsp; Blog &nbsp; Contacto</span>`;
    const logoHtml = `<strong style="font-size:0.85rem;${logoFont}color:${logoColor};">MiSitio</strong>`;
    const extrasHtml = (socialOn ? '<i class="bi bi-facebook" style="font-size:0.6rem;color:'+linkColor+';"></i><i class="bi bi-instagram" style="font-size:0.6rem;color:'+linkColor+';margin-left:3px;"></i>' : '') + (searchOn ? '<i class="bi bi-search" style="font-size:0.6rem;color:'+linkColor+';margin-left:4px;"></i>' : '');
    const ctaHtml = ctaOn ? `<span style="background:${accentColor};color:${h.header_cta_text_color||'#fff'};padding:2px 8px;border-radius:3px;font-size:0.5rem;">${h.header_cta_text_es||'CTA'}</span>` : '';
    const clockHtml = clockOn ? `<span style="font-size:0.5rem;color:${linkColor};opacity:0.7;">vie, 28 mar 2026</span>` : '';

    html += `<div style="background:${hBg};padding:8px 10px;">`;

    if (hLayout === 'centered') {
        html += `<div style="text-align:center;margin-bottom:4px;">${logoHtml}</div>`;
        html += `<div style="display:flex;justify-content:center;align-items:center;gap:8px;">${menuHtml} ${ctaHtml}</div>`;
    } else if (hLayout === 'logo-above' || hLayout === 'logo-above-left') {
        const align = hLayout === 'logo-above' ? 'center' : 'flex-start';
        html += `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">`;
        html += `<div style="flex:1;"></div><div style="text-align:${align === 'center' ? 'center' : 'left'};flex:2;">${logoHtml}</div>`;
        html += `<div style="flex:1;text-align:right;">${extrasHtml}</div></div>`;
        html += `<div style="border-top:1px solid rgba(0,0,0,0.06);padding-top:4px;display:flex;justify-content:space-between;align-items:center;">${menuHtml} <span>${clockHtml} ${ctaHtml}</span></div>`;
    } else if (hLayout === 'tema1') {
        html += `<div style="display:flex;justify-content:space-between;align-items:center;">`;
        html += `<div style="display:flex;align-items:center;gap:8px;">${logoHtml} ${menuHtml}</div>`;
        html += `<div>${ctaHtml}</div></div>`;
    } else if (hLayout === 'banner') {
        html += `<div style="display:flex;align-items:center;">`;
        html += `<div style="background:${accentColor};padding:6px 12px;margin:-8px 10px -8px -10px;">${logoHtml.replace(logoColor,'#fff')}</div>`;
        html += `<div style="flex:1;display:flex;justify-content:space-between;align-items:center;">${menuHtml} ${ctaHtml}</div></div>`;
    } else if (hLayout === 'sidebar') {
        html += `<div style="display:flex;gap:6px;">`;
        html += `<div style="background:${t.topbar_bg_color||'#1a2a40'};padding:6px;border-radius:3px;min-width:50px;text-align:center;">`;
        html += `<div style="color:#fff;font-size:0.6rem;font-weight:700;">Logo</div>`;
        html += `<div style="color:rgba(255,255,255,0.6);font-size:0.45rem;margin-top:4px;">Menu<br>Inicio<br>Blog</div></div>`;
        html += `<div style="flex:1;font-size:0.5rem;color:#999;">Contenido principal...</div></div>`;
    } else if (hLayout === 'left') {
        html += `<div style="display:flex;align-items:center;gap:8px;">${logoHtml} ${menuHtml} ${ctaHtml}</div>`;
    } else {
        // default
        html += `<div style="display:flex;justify-content:space-between;align-items:center;">`;
        html += `${logoHtml}<div style="display:flex;align-items:center;gap:6px;">${menuHtml} ${ctaHtml} ${extrasHtml}</div></div>`;
    }
    if (stickyOn) html += `<div style="text-align:right;"><span style="font-size:0.4rem;background:${accentColor};color:#fff;padding:1px 4px;border-radius:2px;">STICKY</span></div>`;
    html += `<div style="font-size:0.4rem;color:${linkColor};opacity:0.3;text-align:right;">header: ${layoutLabel[hLayout]||hLayout}</div>`;
    html += `</div>`;

    // === HERO ===
    if (hLayout !== 'sidebar') {
        html += `<div style="background:#555;position:relative;padding:12px 10px;min-height:45px;">`;
        html += `<div style="position:absolute;inset:0;background:${hero.hero_overlay_color||'#000'};opacity:${hero.hero_overlay_opacity||'0.5'};"></div>`;
        html += `<div style="position:relative;">`;
        html += `<div style="font-size:0.75rem;font-weight:700;color:${hero.hero_title_color||'#fff'};${heroFont}">Titulo</div>`;
        html += `<div style="font-size:0.55rem;color:${hero.hero_subtitle_color||'#eee'};margin-top:1px;">Subtitulo de la pagina</div>`;
        html += `</div></div>`;
    }

    // === TICKER ===
    if (tickerOn) {
        html += `<div style="display:flex;align-items:center;border:1px solid ${blog.blog_ticker_border_color||'#e5e5e5'};background:${blog.blog_ticker_bg_color||'#fff'};font-size:0.5rem;overflow:hidden;">`;
        html += `<span style="background:${blog.blog_ticker_label_bg||'#333'};color:${blog.blog_ticker_label_text||'#fff'};padding:3px 6px;white-space:nowrap;font-weight:700;">Latest</span>`;
        html += `<span style="padding:3px 8px;color:${blog.blog_ticker_post_text||'#333'};white-space:nowrap;">Ultimo post publicado...</span>`;
        html += `</div>`;
    }

    // === BLOG ===
    html += `<div style="background:#fff;padding:8px 10px;">`;
    html += `<div style="font-size:0.4rem;color:#999;margin-bottom:4px;">blog: ${blogLabel[bLayout]||bLayout}</div>`;

    if (bLayout === 'newspaper') {
        html += `<div style="display:flex;gap:4px;">`;
        html += `<div style="flex:2;background:#e5e7eb;border-radius:3px;height:55px;position:relative;">`;
        if (blog.newspaper_overlay === '1') html += `<div style="position:absolute;bottom:2px;left:4px;font-size:0.45rem;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.5);">Post destacado</div>`;
        html += `</div><div style="flex:1;display:flex;flex-direction:column;gap:3px;">`;
        html += `<div style="flex:1;background:#e5e7eb;border-radius:3px;"></div>`;
        html += `<div style="flex:1;background:#e5e7eb;border-radius:3px;"></div></div></div>`;
    } else if (bLayout === 'magazine') {
        html += `<div style="display:flex;gap:4px;margin-bottom:4px;">`;
        html += `<div style="flex:1;background:#e5e7eb;border-radius:3px;height:40px;"></div></div>`;
        html += `<div style="display:flex;gap:3px;">`;
        html += `<div style="flex:1;background:#f0f2f5;border-radius:3px;height:25px;"></div>`.repeat(3) + `</div>`;
    } else if (bLayout === 'list') {
        for (let i=0;i<2;i++) html += `<div style="display:flex;gap:4px;margin-bottom:3px;"><div style="width:35px;height:25px;background:#e5e7eb;border-radius:3px;"></div><div style="flex:1;"><div style="height:4px;background:#ddd;border-radius:2px;width:80%;margin-bottom:2px;"></div><div style="height:4px;background:#eee;border-radius:2px;width:50%;"></div></div></div>`;
    } else if (bLayout === 'minimal') {
        for (let i=0;i<3;i++) html += `<div style="margin-bottom:3px;"><div style="height:5px;background:#ddd;border-radius:2px;width:${70+i*10}%;margin-bottom:2px;"></div><div style="height:3px;background:#eee;border-radius:2px;width:50%;"></div></div>`;
    } else if (bLayout === 'fashion') {
        html += `<div style="display:flex;gap:6px;justify-content:center;">`;
        for (let i=0;i<3;i++) html += `<div style="width:30px;height:30px;background:#e5e7eb;border-radius:50%;"></div>`;
        html += `</div>`;
    } else {
        // grid
        html += `<div style="display:flex;gap:3px;">`;
        html += `<div style="flex:1;background:#e5e7eb;border-radius:3px;height:30px;"></div>`.repeat(3) + `</div>`;
    }
    html += `</div>`;

    // === FOOTER ===
    const fBg = f.footer_bg_color || '#1a1a2e';
    const fText = f.footer_text_color || '#ccc';
    const fHeading = f.footer_heading_color || '#fff';
    const fLink = f.footer_link_color || '#aaa';
    const fBottomBg = f.footer_bottom_bg_color || '#111';

    if (fLayout === 'minimal') {
        html += `<div style="background:${fBg};padding:6px 10px;text-align:center;">`;
        html += `<div style="font-size:0.5rem;color:${fText};">&copy; 2026 MiSitio</div></div>`;
        html += `<div style="background:#fff;padding:4px 10px;text-align:center;border-top:1px solid #eee;">`;
        html += `<span style="font-size:0.45rem;color:#666;">Legal &middot; Privacidad &middot; Cookies</span></div>`;
    } else if (fLayout === 'banner') {
        html += `<div style="background:${fBg};padding:8px 10px;">`;
        html += `<div style="display:flex;gap:8px;">`;
        html += `<div style="flex:1;"><strong style="font-size:0.55rem;color:${fHeading};">Logo</strong><div style="font-size:0.4rem;color:${fText};margin-top:2px;">Descripcion breve</div></div>`;
        html += `<div style="flex:1;"><div style="font-size:0.45rem;color:${fHeading};font-weight:600;">Menu</div><div style="font-size:0.4rem;color:${fLink};">Link 1<br>Link 2</div></div>`;
        html += `<div style="flex:1;"><div style="font-size:0.45rem;color:${fHeading};font-weight:600;">Contacto</div><div style="font-size:0.4rem;color:${fLink};">email@site.com</div></div>`;
        html += `</div></div>`;
        html += `<div style="background:${fBottomBg};padding:3px 10px;text-align:center;font-size:0.4rem;color:${fText};">&copy; 2026 | Legal &middot; Privacidad</div>`;
    } else {
        // default
        html += `<div style="background:${fBg};padding:8px 10px;">`;
        html += `<div style="display:flex;gap:6px;">`;
        html += `<div style="flex:2;"><strong style="font-size:0.55rem;color:${fHeading};">Logo</strong><div style="font-size:0.4rem;color:${fText};margin-top:2px;">Breve desc.</div>`;
        html += `<div style="margin-top:3px;"><i class="bi bi-facebook" style="font-size:0.5rem;color:${f.footer_icon_color||'#999'};margin-right:3px;"></i><i class="bi bi-instagram" style="font-size:0.5rem;color:${f.footer_icon_color||'#999'};"></i></div></div>`;
        html += `<div style="flex:1;"><div style="font-size:0.45rem;color:${fHeading};font-weight:600;">Menu 1</div><div style="font-size:0.4rem;color:${fLink};">Link<br>Link</div></div>`;
        html += `<div style="flex:1;"><div style="font-size:0.45rem;color:${fHeading};font-weight:600;">Menu 2</div><div style="font-size:0.4rem;color:${fLink};">Link<br>Link</div></div>`;
        html += `</div></div>`;
        html += `<div style="background:${fBottomBg};padding:3px 10px;text-align:center;font-size:0.4rem;color:${fText};">&copy; 2026 | Legal &middot; Privacidad</div>`;
    }
    html += `<div style="font-size:0.35rem;color:#999;text-align:right;padding:1px 6px;background:${fLayout==='minimal'?'#fff':fBottomBg};">footer: ${footerLabel[fLayout]||fLayout}</div>`;

    // === SCROLL TO TOP ===
    if (sc.scroll_to_top_enabled !== '0') {
        html += `<div style="position:absolute;bottom:6px;right:6px;width:20px;height:20px;border-radius:3px;background:${sc.scroll_to_top_bg_color||'#ff5e15'};color:${sc.scroll_to_top_icon_color||'#fff'};display:flex;align-items:center;justify-content:center;font-size:0.55rem;"><i class="bi bi-arrow-up"></i></div>`;
    }

    container.innerHTML = html;
}
</script>

@endsection
