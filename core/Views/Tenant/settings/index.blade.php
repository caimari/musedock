@extends('layouts.app')

@section('title', $title)

@push('styles')
<style>
/* Placeholders más claros para distinguir del texto escrito */
.form-control::placeholder,
.form-select::placeholder,
input::placeholder,
textarea::placeholder {
    color: #adb5bd !important;
    opacity: 0.7 !important;
    font-style: italic;
}
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-gear"></i> {{ $title }}</h2>
                <p class="text-muted mb-0">Configura la informacion de tu sitio web</p>
            </div>
        </div>

        @include('partials.alerts-sweetalert2')

        <form method="POST" action="/{{ admin_path() }}/settings" enctype="multipart/form-data">
            {!! csrf_field() !!}

            <div class="row">
                <!-- Columna principal -->
                <div class="col-lg-8">
                    <!-- Informacion basica del sitio -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-building"></i> Informacion del sitio</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Titulo del sitio <span class="text-danger">*</span></label>
                                <input type="text" name="site_name" class="form-control"
                                       value="{{ $settings['site_name'] ?? '' }}" required>
                                <small class="text-muted">Nombre principal de tu sitio web</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subtitulo del sitio</label>
                                <input type="text" name="site_subtitle" class="form-control"
                                       value="{{ $settings['site_subtitle'] ?? '' }}"
                                       placeholder="Ej: Tu lema o eslogan">
                                <div class="form-check mt-2">
                                    <input type="checkbox" class="form-check-input" id="show_subtitle"
                                           name="show_subtitle" {{ ($settings['show_subtitle'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_subtitle">Mostrar subtitulo en la cabecera</label>
                                </div>
                                <small class="text-muted">Texto que se muestra debajo del logo en la cabecera</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Descripcion del sitio (SEO)</label>
                                <textarea name="site_description" class="form-control" rows="2"
                                          placeholder="Descripcion breve para buscadores y redes sociales">{{ $settings['site_description'] ?? '' }}</textarea>
                                <small class="text-muted">Solo para SEO y al compartir en redes sociales. No se muestra visualmente en el sitio.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Correo del administrador</label>
                                <input type="email" name="admin_email" class="form-control"
                                       value="{{ $settings['admin_email'] ?? '' }}">
                                <small class="text-muted">Correo para notificaciones y correspondencia</small>
                            </div>
                        </div>
                    </div>

                    <!-- Informacion de contacto -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-telephone"></i> Informacion de contacto</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telefono de contacto</label>
                                    <input type="text" name="contact_phone" class="form-control"
                                           value="{{ $settings['contact_phone'] ?? '' }}"
                                           placeholder="+34 600 000 000">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email de contacto</label>
                                    <input type="email" name="contact_email" class="form-control"
                                           value="{{ $settings['contact_email'] ?? '' }}"
                                           placeholder="info@tudominio.com">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="text" name="contact_whatsapp" class="form-control"
                                           value="{{ $settings['contact_whatsapp'] ?? '' }}"
                                           placeholder="+34 600 000 000">
                                    <small class="text-muted">Numero de WhatsApp con prefijo internacional</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Direccion</label>
                                    <textarea name="contact_address" class="form-control" rows="2"
                                              placeholder="Calle, numero, ciudad, pais">{{ $settings['contact_address'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Datos legales -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-shield-check"></i> Datos legales (Aviso Legal / Privacidad)</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Estos datos se usan para generar automáticamente las páginas de Aviso Legal, Política de Privacidad, etc. Son independientes de los datos de contacto públicos del sitio.</p>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Jurisdicción</label>
                                    <select name="legal_jurisdiction" class="form-select" id="legal_jurisdiction">
                                        @php $jurisdiction = $settings['legal_jurisdiction'] ?? 'ES'; @endphp
                                        <optgroup label="Europa">
                                            <option value="ES" @selected($jurisdiction === 'ES')>España (LSSI + RGPD + LOPD-GDD)</option>
                                            <option value="EU" @selected($jurisdiction === 'EU')>Unión Europea / EEE (RGPD)</option>
                                        </optgroup>
                                        <optgroup label="América">
                                            <option value="US" @selected($jurisdiction === 'US')>Estados Unidos (Terms of Service)</option>
                                            <option value="BR" @selected($jurisdiction === 'BR')>Brasil (LGPD)</option>
                                            <option value="MX" @selected($jurisdiction === 'MX')>México (LFPDPPP)</option>
                                            <option value="AR" @selected($jurisdiction === 'AR')>Argentina (Ley 25.326)</option>
                                            <option value="LATAM" @selected($jurisdiction === 'LATAM')>Otro país latinoamericano</option>
                                        </optgroup>
                                        <optgroup label="Otros">
                                            <option value="OTHER" @selected($jurisdiction === 'OTHER')>Otra jurisdicción</option>
                                        </optgroup>
                                    </select>
                                    <small class="text-muted">Determina el marco legal de las páginas generadas.</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tipo de titular</label>
                                    <select name="legal_entity_type" class="form-select" id="legal_entity_type">
                                        @php $entityType = $settings['legal_entity_type'] ?? 'personal'; @endphp
                                        <option value="personal" @selected($entityType === 'personal')>Persona física (blog personal)</option>
                                        <option value="autonomo" @selected($entityType === 'autonomo')>Autónomo / Freelance</option>
                                        <option value="empresa" @selected($entityType === 'empresa')>Empresa / Sociedad (S.L., S.A., LLC, etc.)</option>
                                    </select>
                                    <small class="text-muted">Nivel de detalle del aviso legal.</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Nombre o razón social</label>
                                    <input type="text" name="legal_name" class="form-control"
                                           value="{{ $settings['legal_name'] ?? '' }}"
                                           placeholder="Empresa Ejemplo S.L.">
                                    <small class="text-muted">Nombre legal real. Puede diferir del nombre del sitio.</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3" id="legal_nif_group">
                                    <label class="form-label" id="legal_nif_label">NIF / CIF</label>
                                    <input type="text" name="legal_nif" class="form-control"
                                           value="{{ $settings['legal_nif'] ?? '' }}"
                                           placeholder="B12345678" id="legal_nif_input">
                                    <small class="text-muted" id="legal_nif_help">Obligatorio para autónomos y empresas.</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email legal / DPO</label>
                                    <input type="email" name="legal_email" class="form-control"
                                           value="{{ $settings['legal_email'] ?? '' }}"
                                           placeholder="legal@tudominio.com">
                                    <small class="text-muted">Para cuestiones legales/RGPD. Si vacío, usa el email de contacto.</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Domicilio legal</label>
                                    <textarea name="legal_address" class="form-control" rows="2"
                                              placeholder="Calle, número, CP, ciudad">{{ $settings['legal_address'] ?? '' }}</textarea>
                                    <small class="text-muted">Si vacío, usa la dirección de contacto.</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3" id="legal_registry_group">
                                    <label class="form-label" id="legal_registry_label">Datos registrales</label>
                                    <input type="text" name="legal_registry_data" class="form-control"
                                           value="{{ $settings['legal_registry_data'] ?? '' }}"
                                           placeholder="" id="legal_registry_input">
                                    <small class="text-muted" id="legal_registry_help">Solo para sociedades inscritas.</small>
                                </div>

                                <div class="col-md-4 mb-3" id="legal_authority_group">
                                    <label class="form-label">Autoridad de control</label>
                                    <input type="text" name="legal_supervisory_authority" class="form-control"
                                           value="{{ $settings['legal_supervisory_authority'] ?? '' }}"
                                           placeholder="" id="legal_authority_input">
                                    <small class="text-muted" id="legal_authority_help">Autoridad de protección de datos. Se autocompleta según jurisdicción.</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-4 mb-2">
                                        <input type="checkbox" class="form-check-input" name="site_has_economic_activity" value="1" id="has_economic_activity"
                                               {{ ($settings['site_has_economic_activity'] ?? '0') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="has_economic_activity">Este sitio tiene actividad económica</label>
                                        <small class="d-block text-muted">Vende productos/servicios, muestra publicidad, etc. Activa obligaciones adicionales (LSSI en España).</small>
                                    </div>

                                    <div class="form-check mb-2" id="legal_eu_users_group">
                                        <input type="checkbox" class="form-check-input" name="legal_targets_eu" value="1" id="legal_targets_eu"
                                               {{ ($settings['legal_targets_eu'] ?? '0') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="legal_targets_eu">Este sitio se dirige a usuarios de la UE</label>
                                        <small class="d-block text-muted">Fuerza la capa RGPD en sitios con jurisdicción no-UE.</small>
                                    </div>

                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" name="site_uses_analytics_cookies" value="1" id="uses_analytics_cookies"
                                               {{ ($settings['site_uses_analytics_cookies'] ?? '0') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="uses_analytics_cookies">Usa cookies de analítica (Google Analytics, etc.)</label>
                                        <small class="d-block text-muted">Incluye tabla de cookies analíticas en la política de cookies.</small>
                                    </div>

                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" name="site_has_user_registration" value="1" id="has_user_registration"
                                               {{ ($settings['site_has_user_registration'] ?? '0') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="has_user_registration">Tiene registro de usuarios</label>
                                        <small class="d-block text-muted">Añade secciones de registro, cuentas y uso aceptable en Términos y Condiciones.</small>
                                    </div>

                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" name="site_has_paid_services" value="1" id="has_paid_services"
                                               {{ ($settings['site_has_paid_services'] ?? '0') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="has_paid_services">Ofrece servicios de pago</label>
                                        <small class="d-block text-muted">Añade condiciones de contratación, precios, reembolso y desistimiento en T&C.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var entitySelect = document.getElementById('legal_entity_type');
                        var jurisdictionSelect = document.getElementById('legal_jurisdiction');
                        var registryGroup = document.getElementById('legal_registry_group');
                        var nifGroup = document.getElementById('legal_nif_group');
                        var euUsersGroup = document.getElementById('legal_eu_users_group');
                        var authorityGroup = document.getElementById('legal_authority_group');
                        var nifLabel = document.getElementById('legal_nif_label');
                        var nifInput = document.getElementById('legal_nif_input');
                        var nifHelp = document.getElementById('legal_nif_help');
                        var registryLabel = document.getElementById('legal_registry_label');
                        var registryInput = document.getElementById('legal_registry_input');
                        var registryHelp = document.getElementById('legal_registry_help');
                        var authorityInput = document.getElementById('legal_authority_input');
                        var authorityHelp = document.getElementById('legal_authority_help');

                        // Default supervisory authorities per jurisdiction
                        var defaultAuthorities = {
                            'ES': 'Agencia Española de Protección de Datos (AEPD) — www.aepd.es',
                            'EU': '',
                            'BR': 'Autoridade Nacional de Proteção de Dados (ANPD) — www.gov.br/anpd',
                            'MX': 'Instituto Nacional de Transparencia (INAI) — www.inai.org.mx',
                            'AR': 'Agencia de Acceso a la Información Pública (AAIP) — www.argentina.gob.ar/aaip',
                            'US': '',
                            'LATAM': '',
                            'OTHER': ''
                        };

                        function updateLegalFields() {
                            var entity = entitySelect.value;
                            var jurisdiction = jurisdictionSelect.value;
                            var isEU = (jurisdiction === 'ES' || jurisdiction === 'EU');
                            var isUS = (jurisdiction === 'US');
                            var isBR = (jurisdiction === 'BR');
                            var hasDataProtection = isEU || isBR || jurisdiction === 'AR';

                            // Registry: only for empresa
                            registryGroup.style.display = entity === 'empresa' ? '' : 'none';

                            // NIF: hide for personal, show for autonomo/empresa
                            nifGroup.style.display = entity === 'personal' ? 'none' : '';

                            // EU users toggle: only show for non-EU jurisdictions
                            euUsersGroup.style.display = isEU ? 'none' : '';

                            // Authority: show for jurisdictions with data protection authority
                            authorityGroup.style.display = (hasDataProtection || jurisdiction === 'MX') ? '' : 'none';

                            // Set default authority placeholder if field is empty
                            var defaultAuth = defaultAuthorities[jurisdiction] || '';
                            authorityInput.placeholder = defaultAuth || 'Nombre de la autoridad de protección de datos';
                            if (defaultAuth) {
                                authorityHelp.textContent = 'Déjalo vacío para usar: ' + defaultAuth.split(' — ')[0];
                            } else if (jurisdiction === 'EU') {
                                authorityHelp.textContent = 'Ej: CNIL (Francia), DPC (Irlanda), BfDI (Alemania)...';
                                authorityInput.placeholder = 'Data Protection Commission (DPC) — www.dataprotection.ie';
                            } else {
                                authorityHelp.textContent = 'Autoridad de protección de datos de tu país.';
                            }

                            // Adapt labels/placeholders per jurisdiction
                            if (isUS) {
                                nifLabel.textContent = 'EIN / Tax ID';
                                nifInput.placeholder = '12-3456789';
                                nifHelp.textContent = 'Employer Identification Number (si aplica).';
                                registryLabel.textContent = 'State of Formation / Filing Number';
                                registryInput.placeholder = 'State of New Mexico, Filing No. 1234567';
                                registryHelp.textContent = 'Estado de registro y número de filing para LLC/Corp.';
                            } else if (isBR) {
                                nifLabel.textContent = 'CNPJ / CPF';
                                nifInput.placeholder = '12.345.678/0001-00';
                                nifHelp.textContent = 'CNPJ para empresas, CPF para personas físicas.';
                                registryLabel.textContent = 'Datos registrales';
                                registryInput.placeholder = 'Junta Comercial, NIRE...';
                                registryHelp.textContent = 'Datos de registro de la sociedad.';
                            } else if (jurisdiction === 'MX') {
                                nifLabel.textContent = 'RFC';
                                nifInput.placeholder = 'XAXX010101000';
                                nifHelp.textContent = 'Registro Federal de Contribuyentes.';
                                registryLabel.textContent = 'Datos registrales';
                                registryInput.placeholder = 'Registro Público de Comercio...';
                                registryHelp.textContent = 'Datos de registro de la sociedad.';
                            } else {
                                nifLabel.textContent = 'NIF / CIF';
                                nifInput.placeholder = 'B12345678';
                                nifHelp.textContent = 'Obligatorio para autónomos y empresas.';
                                registryLabel.textContent = 'Datos registrales';
                                registryInput.placeholder = 'Registro Mercantil de Madrid, Tomo X, Folio X, Hoja X';
                                registryHelp.textContent = 'Solo para sociedades inscritas en el Registro Mercantil.';
                            }
                        }

                        entitySelect.addEventListener('change', updateLegalFields);
                        jurisdictionSelect.addEventListener('change', updateLegalFields);
                        updateLegalFields();
                    });
                    </script>

                    <!-- Footer -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-layout-text-window-reverse"></i> Footer</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Descripcion corta del footer</label>
                                <small class="text-muted d-block mb-2">Texto que se mostrara en la primera columna del footer (traducible por idioma)</small>

                                @if(count($activeLanguages) > 1)
                                    <!-- Pestanas de idiomas -->
                                    <ul class="nav nav-tabs" id="footerDescTabs" role="tablist">
                                        @foreach($activeLanguages as $index => $lang)
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $index === 0 ? 'active' : '' }}"
                                                        id="footer-desc-{{ $lang->code }}-tab"
                                                        data-bs-toggle="tab"
                                                        data-bs-target="#footer-desc-{{ $lang->code }}"
                                                        type="button"
                                                        role="tab">
                                                    {{ strtoupper($lang->code) }} - {{ $lang->name }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <div class="tab-content border border-top-0 p-3 rounded-bottom" id="footerDescTabsContent">
                                        @foreach($activeLanguages as $index => $lang)
                                            <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                                                 id="footer-desc-{{ $lang->code }}"
                                                 role="tabpanel">
                                                <textarea name="footer_short_description_{{ $lang->code }}"
                                                          class="form-control" rows="3"
                                                          placeholder="Descripcion en {{ $lang->name }}">{{ $settings['footer_short_description_' . $lang->code] ?? '' }}</textarea>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <textarea name="footer_short_description_{{ $activeLanguages[0]->code ?? 'es' }}"
                                              class="form-control" rows="3"
                                              placeholder="Breve descripcion de tu empresa o sitio web">{{ $settings['footer_short_description_' . ($activeLanguages[0]->code ?? 'es')] ?? '' }}</textarea>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Texto de copyright</label>
                                <input type="text" name="footer_copyright" class="form-control"
                                       value="{{ $settings['footer_copyright'] ?? '' }}"
                                       placeholder='© Copyright <a href="https://tudominio.com">Tu Empresa</a> {{ date("Y") }}.'>
                                <small class="text-muted">Ejemplo: <code>&copy; Copyright &lt;a href="https://tudominio.com"&gt;Tu Empresa&lt;/a&gt; {{ date('Y') }}.</code></small>
                            </div>
                        </div>
                    </div>

                    <!-- Codigo personalizado -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-code-slash"></i> Codigo personalizado</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-light border mb-4">
                                <small><i class="bi bi-info-circle"></i> Inserta codigo HTML, CSS o JavaScript que se cargara en todas las paginas publicas de tu sitio. Ideal para Google Analytics, Search Console, Facebook Pixel, chatbots, etc. Este codigo <strong>no aparece</strong> en el panel de administracion.</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Codigo en &lt;head&gt; <small class="text-muted fw-normal">— meta tags, analytics, CSS</small></label>
                                <textarea name="custom_head_code" class="form-control font-monospace" rows="5" style="font-size: 0.85rem;"
                                          placeholder="<meta name=&quot;google-site-verification&quot; content=&quot;...&quot;>&#10;<script async src=&quot;https://www.googletagmanager.com/gtag/js?id=G-XXXXX&quot;></script>">{{ $settings['custom_head_code'] ?? '' }}</textarea>
                                <small class="text-muted">Google Search Console, Google Analytics, meta tags, CSS externo, web fonts.</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Codigo inicio de &lt;body&gt; <small class="text-muted fw-normal">— tag managers</small></label>
                                <textarea name="custom_body_start_code" class="form-control font-monospace" rows="3" style="font-size: 0.85rem;"
                                          placeholder="<noscript><iframe src=&quot;https://www.googletagmanager.com/ns.html?id=GTM-XXXXX&quot;></iframe></noscript>">{{ $settings['custom_body_start_code'] ?? '' }}</textarea>
                                <small class="text-muted">Google Tag Manager (noscript), scripts que deben cargar al inicio.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Codigo final de &lt;body&gt; <small class="text-muted fw-normal">— chatbots, widgets, tracking</small></label>
                                <textarea name="custom_body_end_code" class="form-control font-monospace" rows="5" style="font-size: 0.85rem;"
                                          placeholder="<script src=&quot;https://widget.example.com/chat.js&quot;></script>">{{ $settings['custom_body_end_code'] ?? '' }}</textarea>
                                <small class="text-muted">Chatbots (Tidio, Crisp, Intercom), Facebook Pixel, Hotjar, widgets externos.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna lateral -->
                <div class="col-lg-4">
                    <!-- Logo y favicon -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-image"></i> Identidad visual</h5>
                        </div>
                            <div class="card-body">
                            <!-- Logo -->
                            <div class="mb-4">
                                <label class="form-label">Logotipo del sitio</label>
                                <input type="file" name="site_logo" class="form-control" accept="image/*">

                                <div class="mt-3 text-center">
                                    @php
                                        $logoSetting = $settings['site_logo'] ?? '';
                                        $logoPreview = public_file_url($logoSetting, 'themes/default/img/logo/logo.png');
                                    @endphp
                                    <img src="{{ $logoPreview }}"
                                         alt="Logo actual"
                                         style="max-height: 80px; max-width: 100%; border: 1px solid #ddd; padding: 5px;"
                                         onerror="this.onerror=null; this.src='{{ asset('themes/default/img/logo/logo.png') }}';">
                                    @if(!empty($logoSetting))
                                      <p class="text-muted mt-2 small"><code>{{ $logoSetting }}</code></p>
                                      <a href="/{{ admin_path() }}/settings/delete-logo"
                                         class="btn btn-outline-danger btn-sm"
                                         onclick="return confirm('Estas seguro de eliminar el logo?')">
                                          <i class="bi bi-trash"></i> Eliminar logo
                                      </a>
                                    @endif
                                </div>
                            </div>

                            <hr>

                            <!-- Favicon -->
                            <div class="mb-3">
                                <label class="form-label">Favicon del sitio</label>
                                <input type="file" name="site_favicon" class="form-control" accept="image/x-icon,image/png">

                                <div class="mt-3 text-center">
                                    @php
                                        $faviconSetting = $settings['site_favicon'] ?? '';
                                        $faviconPreview = public_file_url($faviconSetting, 'img/favicon.png');
                                    @endphp
                                    <img src="{{ $faviconPreview }}"
                                         alt="Favicon actual"
                                         style="max-height: 40px; max-width: 40px; border: 1px solid #ddd; padding: 5px;"
                                         onerror="this.onerror=null; this.src='{{ asset('img/favicon.png') }}';">
                                    @if(!empty($faviconSetting))
                                      <p class="text-muted mt-2 small"><code>{{ $faviconSetting }}</code></p>
                                      <a href="/{{ admin_path() }}/settings/delete-favicon"
                                         class="btn btn-outline-danger btn-sm"
                                         onclick="return confirm('Estas seguro de eliminar el favicon?')">
                                          <i class="bi bi-trash"></i> Eliminar favicon
                                      </a>
                                    @endif
                                </div>
                            </div>

                            <hr>

                            <!-- Opciones de visualizacion -->
                            <div class="mb-3">
                                <label class="form-label">Opciones de visualizacion</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="show_logo"
                                           name="show_logo" {{ ($settings['show_logo'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_logo">Mostrar logotipo</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="show_title"
                                           name="show_title" {{ ($settings['show_title'] ?? '0') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_title">Mostrar titulo del sitio</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Idioma -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-translate"></i> Idioma</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Idioma por defecto</label>
                                <select name="default_lang" class="form-select">
                                    @foreach($activeLanguages as $lang)
                                        <option value="{{ $lang->code }}" {{ ($settings['default_lang'] ?? 'es') === $lang->code ? 'selected' : '' }}>
                                            {{ $lang->name }} ({{ $lang->code }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Idioma principal del sitio web</small>
                            </div>

                            @if(count($activeLanguages) > 1)
                            <div class="mb-3">
                                <label class="form-label">Forzar idioma del sitio</label>
                                <select name="force_lang" class="form-select">
                                    <option value="" {{ empty($settings['force_lang'] ?? '') ? 'selected' : '' }}>
                                        Auto (detectar idioma del navegador)
                                    </option>
                                    @foreach($activeLanguages as $lang)
                                        <option value="{{ $lang->code }}" {{ ($settings['force_lang'] ?? '') === $lang->code ? 'selected' : '' }}>
                                            Forzar {{ $lang->name }} ({{ $lang->code }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">En <strong>Auto</strong>, el sitio detecta el idioma del navegador del visitante. Si fuerzas un idioma, todos los visitantes veran el sitio en ese idioma.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Selector de idioma en la web</label>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" id="show_language_switcher"
                                           name="show_language_switcher" {{ ($settings['show_language_switcher'] ?? '1') == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_language_switcher">Mostrar selector de idioma</label>
                                </div>
                                <small class="text-muted">Si lo desactivas, se oculta el selector pero el idioma aun se detecta automaticamente (salvo que fuerces un idioma arriba)</small>
                            </div>
                            @endif

                            <a href="/{{ admin_path() }}/languages" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-gear"></i> Gestionar idiomas
                            </a>
                        </div>
                    </div>

                    <!-- Boton guardar -->
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-lg"></i> Guardar cambios
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
