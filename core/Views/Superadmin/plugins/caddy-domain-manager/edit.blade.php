@extends('layouts.app')

@section('title', 'Editar Dominio: ' . $tenant->domain)

@section('content')
<div class="app-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2><i class="bi bi-pencil"></i> Editar Dominio</h2>
                <p class="text-muted mb-0">{{ $tenant->domain }}</p>
            </div>
            <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @include('partials.alerts-sweetalert2')

        <div class="row">
            <!-- Formulario de edición -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Información del Tenant</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/musedock/domain-manager/{{ $tenant->id }}">
                            {!! csrf_field() !!}
                            <input type="hidden" name="_method" value="PUT">

                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="{{ $tenant->name }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dominio</label>
                                <input type="text" class="form-control" value="{{ $tenant->domain }}" disabled>
                                <div class="form-text text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    El dominio no se puede cambiar. Para usar otro dominio, crea un nuevo tenant.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">Estado del Tenant</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" {{ $tenant->status === 'active' ? 'selected' : '' }}>Activo</option>
                                    <option value="inactive" {{ $tenant->status === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                    <option value="suspended" {{ $tenant->status === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle text-info"></i>
                                    <strong>Solo afecta al CMS:</strong> Desactivar el tenant impide el acceso al panel /admin/, pero <strong>NO</strong> afecta a Caddy.
                                    El dominio seguira respondiendo si esta configurado en Caddy.
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="include_www" name="include_www"
                                           {{ ($tenant->include_www ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="include_www">
                                        Incluir www.{{ $tenant->domain }}
                                    </label>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle text-warning"></i>
                                    <strong>Requiere reconfigurar Caddy:</strong> Si cambias esta opcion, el sistema intentara reconfigurar automaticamente.
                                    Tambien puedes usar el boton "Reconfigurar en Caddy" del panel lateral para regenerar la ruta y el certificado SSL.
                                </div>
                            </div>

                            {{-- Grupo Editorial (Cross-Publisher) --}}
                            @if(!empty($domainGroups) || true)
                            <div class="mb-3">
                                <label for="group_id" class="form-label">
                                    <i class="bi bi-collection me-1"></i>Grupo Editorial
                                </label>
                                <select class="form-select" id="group_id" name="group_id">
                                    <option value="">-- Sin grupo --</option>
                                    @foreach($domainGroups ?? [] as $dg)
                                        <option value="{{ $dg->id }}" {{ ($tenant->group_id ?? '') == $dg->id ? 'selected' : '' }}>
                                            {{ $dg->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Asigna este tenant a un grupo editorial para cross-publishing.
                                    <a href="/musedock/cross-publisher/groups" target="_blank">Gestionar grupos</a>
                                </div>
                            </div>
                            @endif

                            {{-- Opciones de Cloudflare (solo si NO está configurado) --}}
                            @if(empty($tenant->cloudflare_zone_id))
                                <hr class="my-3">

                                <h6 class="text-success mb-3"><i class="bi bi-cloud"></i> Cloudflare (Opcional)</h6>

                                <div class="mb-3">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" id="configure_cloudflare" name="configure_cloudflare">
                                        <label class="form-check-label" for="configure_cloudflare">
                                            <strong>Añadir dominio a Cloudflare ahora (crear nueva zona)</strong>
                                        </label>
                                        <div class="form-text">
                                            Añade este dominio a Cloudflare Account 2 (Full Setup) con CNAMEs automáticos
                                        </div>
                                    </div>
                                </div>

                                <div id="cloudflare-options-edit" class="ps-4 d-none">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="enable_email_routing_edit" name="enable_email_routing">
                                            <label class="form-check-label" for="enable_email_routing_edit">
                                                <i class="bi bi-envelope-at"></i> Activar Email Routing
                                            </label>
                                        </div>
                                    </div>

                                    <div id="email-routing-options-edit" class="mb-3 d-none">
                                        <label for="email_routing_destination_edit" class="form-label">
                                            Email Destino para Catch-All
                                        </label>
                                        <input type="email" class="form-control" id="email_routing_destination_edit"
                                               name="email_routing_destination" placeholder="destino@gmail.com">
                                        <div class="form-text">
                                            Todos los emails enviados a {{ $tenant->domain }} serán redirigidos a este email
                                        </div>
                                    </div>

                                    <div class="alert alert-warning alert-sm mb-0">
                                        <small>
                                            <i class="bi bi-exclamation-triangle"></i> <strong>Importante:</strong>
                                            Al activar Cloudflare, se te proporcionarán los nameservers. El dominio cambiará a estado "waiting_ns_change".
                                        </small>
                                    </div>
                                </div>

                                {{-- Opción para vincular dominio existente de Cloudflare --}}
                                <div class="mt-3">
                                    <div class="alert alert-info py-2">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-info-circle me-2"></i>
                                            <div class="flex-grow-1">
                                                <small><strong>¿Este dominio ya existe en Cloudflare?</strong></small>
                                                <div class="form-text mb-0">
                                                    Si el dominio ya está configurado manualmente en Cloudflare, puedes vincularlo aquí sin perder tu configuración.
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="btnLinkCloudflare">
                                                <i class="bi bi-link-45deg"></i> Vincular Existente
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <hr class="my-3">

                            <div class="mb-3">
                                <label for="caddy_status" class="form-label">Estado Caddy</label>
                                <select class="form-select" id="caddy_status" name="caddy_status">
                                    <option value="not_configured" {{ ($tenant->caddy_status ?? 'not_configured') === 'not_configured' ? 'selected' : '' }}>No Configurado</option>
                                    <option value="pending_dns" {{ ($tenant->caddy_status ?? '') === 'pending_dns' ? 'selected' : '' }}>Pendiente DNS</option>
                                    <option value="configuring" {{ ($tenant->caddy_status ?? '') === 'configuring' ? 'selected' : '' }}>Configurando</option>
                                    <option value="active" {{ ($tenant->caddy_status ?? '') === 'active' ? 'selected' : '' }}>Activo</option>
                                    <option value="error" {{ ($tenant->caddy_status ?? '') === 'error' ? 'selected' : '' }}>Error</option>
                                    <option value="suspended" {{ ($tenant->caddy_status ?? '') === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                                </select>
                                <div class="form-text">Cambiar manualmente el estado (solo afecta la BD, no Caddy)</div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="/musedock/domain-manager" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-lg"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary" id="btnSubmit">
                                    <span class="btn-text"><i class="bi bi-check-lg"></i> Guardar Cambios</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                        Guardando...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ============================================ --}}
                {{-- SITE SETTINGS (Collapsible) --}}
                {{-- ============================================ --}}
                <div class="card mt-3">
                    <div class="card-header p-0">
                        <button class="btn btn-link text-decoration-none w-100 text-start p-3 d-flex justify-content-between align-items-center collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#siteSettingsCollapse"
                                aria-expanded="false"
                                aria-controls="siteSettingsCollapse">
                            <h5 class="mb-0"><i class="bi bi-gear"></i> Ajustes del Sitio</h5>
                            <i class="bi bi-chevron-down transition-transform" id="siteSettingsChevron" style="transition: transform 0.3s;"></i>
                        </button>
                    </div>
                    <div class="collapse" id="siteSettingsCollapse">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Configura la informacion del sitio <strong>{{ $tenant->domain }}</strong> sin necesidad de entrar en su panel de administracion.
                            </p>

                            <form id="siteSettingsForm" enctype="multipart/form-data">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                                {{-- Informacion del sitio --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-building"></i> Informacion del sitio</h6>

                                <div class="mb-3">
                                    <label class="form-label">Titulo del sitio <span class="text-danger">*</span></label>
                                    <input type="text" name="site_name" class="form-control"
                                           value="{{ $tenantSettings['site_name'] ?? '' }}">
                                    <small class="text-muted">Nombre principal del sitio web</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Subtitulo del sitio</label>
                                    <input type="text" name="site_subtitle" class="form-control"
                                           value="{{ $tenantSettings['site_subtitle'] ?? '' }}"
                                           placeholder="Ej: Tu lema o eslogan">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" id="sa_show_subtitle"
                                               name="show_subtitle" {{ ($tenantSettings['show_subtitle'] ?? '1') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="sa_show_subtitle">Mostrar subtitulo en la cabecera</label>
                                    </div>
                                    <small class="text-muted">Texto que se muestra debajo del logo en la cabecera</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Descripcion del sitio (SEO)</label>
                                    <textarea name="site_description" class="form-control" rows="2"
                                              placeholder="Descripcion breve para buscadores y redes sociales">{{ $tenantSettings['site_description'] ?? '' }}</textarea>
                                    <small class="text-muted">Solo para SEO y al compartir en redes sociales</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Correo del administrador</label>
                                    <input type="email" name="admin_email" class="form-control"
                                           value="{{ $tenantSettings['admin_email'] ?? '' }}">
                                    <small class="text-muted">Correo para notificaciones y correspondencia</small>
                                </div>

                                <hr>

                                {{-- Informacion de contacto --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-telephone"></i> Informacion de contacto</h6>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Telefono de contacto</label>
                                        <input type="text" name="contact_phone" class="form-control"
                                               value="{{ $tenantSettings['contact_phone'] ?? '' }}"
                                               placeholder="+34 600 000 000">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email de contacto</label>
                                        <input type="email" name="contact_email" class="form-control"
                                               value="{{ $tenantSettings['contact_email'] ?? '' }}"
                                               placeholder="info@tudominio.com">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">WhatsApp</label>
                                        <input type="text" name="contact_whatsapp" class="form-control"
                                               value="{{ $tenantSettings['contact_whatsapp'] ?? '' }}"
                                               placeholder="+34 600 000 000">
                                        <small class="text-muted">Numero de WhatsApp con prefijo internacional</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Direccion</label>
                                        <textarea name="contact_address" class="form-control" rows="2"
                                                  placeholder="Calle, numero, ciudad, pais">{{ $tenantSettings['contact_address'] ?? '' }}</textarea>
                                    </div>
                                </div>

                                <hr>

                                {{-- Footer --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-layout-text-window-reverse"></i> Footer</h6>

                                <div class="mb-3">
                                    <label class="form-label">Descripcion corta del footer</label>
                                    <small class="text-muted d-block mb-2">Texto que se mostrara en la primera columna del footer (traducible por idioma)</small>

                                    @if(count($activeLanguages) > 1)
                                        <ul class="nav nav-tabs" id="saFooterDescTabs" role="tablist">
                                            @foreach($activeLanguages as $index => $lang)
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link {{ $index === 0 ? 'active' : '' }}"
                                                            id="sa-footer-desc-{{ $lang->code }}-tab"
                                                            data-bs-toggle="tab"
                                                            data-bs-target="#sa-footer-desc-{{ $lang->code }}"
                                                            type="button" role="tab">
                                                        {{ strtoupper($lang->code) }} - {{ $lang->name }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="tab-content border border-top-0 p-3 rounded-bottom" id="saFooterDescTabsContent">
                                            @foreach($activeLanguages as $index => $lang)
                                                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                                                     id="sa-footer-desc-{{ $lang->code }}" role="tabpanel">
                                                    <textarea name="footer_short_description_{{ $lang->code }}"
                                                              class="form-control" rows="3"
                                                              placeholder="Descripcion en {{ $lang->name }}">{{ $tenantSettings['footer_short_description_' . $lang->code] ?? '' }}</textarea>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif(count($activeLanguages) === 1)
                                        <textarea name="footer_short_description_{{ $activeLanguages[0]->code }}"
                                                  class="form-control" rows="3"
                                                  placeholder="Breve descripcion de tu empresa o sitio web">{{ $tenantSettings['footer_short_description_' . $activeLanguages[0]->code] ?? '' }}</textarea>
                                    @else
                                        <textarea name="footer_short_description_es"
                                                  class="form-control" rows="3"
                                                  placeholder="Breve descripcion de tu empresa o sitio web">{{ $tenantSettings['footer_short_description_es'] ?? '' }}</textarea>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Texto de copyright</label>
                                    <input type="text" name="footer_copyright" class="form-control"
                                           value="{{ $tenantSettings['footer_copyright'] ?? '' }}"
                                           placeholder='&copy; Copyright <a href="https://tudominio.com">Tu Empresa</a> {{ date("Y") }}.'>
                                </div>

                                <hr>

                                {{-- Identidad visual --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-image"></i> Identidad visual</h6>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Logotipo del sitio</label>
                                        <input type="file" name="site_logo" class="form-control" accept="image/*">
                                        @php $saLogoVal = $tenantSettings['site_logo'] ?? ''; @endphp
                                        @if(!empty($saLogoVal))
                                            <div class="mt-2 text-center" id="currentLogoPreview">
                                                <img src="{{ $saLogoVal }}" alt="Logo actual"
                                                     style="max-height: 60px; max-width: 100%; border: 1px solid #ddd; padding: 3px;"
                                                     onerror="this.style.display='none'">
                                                <br>
                                                <button type="button" class="btn btn-outline-danger btn-sm mt-1" onclick="deleteTenantLogo()">
                                                    <i class="bi bi-trash"></i> Eliminar logo
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Favicon del sitio</label>
                                        <input type="file" name="site_favicon" class="form-control" accept="image/x-icon,image/png,image/svg+xml">
                                        @php $saFaviconVal = $tenantSettings['site_favicon'] ?? ''; @endphp
                                        @if(!empty($saFaviconVal))
                                            <div class="mt-2 text-center" id="currentFaviconPreview">
                                                <img src="{{ $saFaviconVal }}" alt="Favicon actual"
                                                     style="max-height: 40px; max-width: 40px; border: 1px solid #ddd; padding: 3px;"
                                                     onerror="this.style.display='none'">
                                                <br>
                                                <button type="button" class="btn btn-outline-danger btn-sm mt-1" onclick="deleteTenantFavicon()">
                                                    <i class="bi bi-trash"></i> Eliminar favicon
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Opciones de visualizacion</label>
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="sa_show_logo"
                                               name="show_logo" {{ ($tenantSettings['show_logo'] ?? '1') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="sa_show_logo">Mostrar logotipo</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox" class="form-check-input" id="sa_show_title"
                                               name="show_title" {{ ($tenantSettings['show_title'] ?? '0') == '1' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="sa_show_title">Mostrar titulo del sitio</label>
                                    </div>
                                </div>

                                <hr>

                                {{-- Idioma --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-translate"></i> Idioma</h6>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Idioma por defecto</label>
                                        <select name="default_lang" class="form-select">
                                            @foreach($activeLanguages as $lang)
                                                <option value="{{ $lang->code }}" {{ ($tenantSettings['default_lang'] ?? 'es') === $lang->code ? 'selected' : '' }}>
                                                    {{ $lang->name }} ({{ $lang->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Idioma principal de este tenant</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Forzar idioma</label>
                                        <select name="force_lang" class="form-select">
                                            <option value="" {{ empty($tenantSettings['force_lang'] ?? '') ? 'selected' : '' }}>
                                                Auto (detectar navegador)
                                            </option>
                                            @foreach($activeLanguages as $lang)
                                                <option value="{{ $lang->code }}" {{ ($tenantSettings['force_lang'] ?? '') === $lang->code ? 'selected' : '' }}>
                                                    Forzar {{ $lang->name }} ({{ $lang->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Auto = detecta navegador. Forzar = ignora navegador</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Selector de idioma</label>
                                        <div class="form-check form-switch mt-1">
                                            <input type="checkbox" class="form-check-input" id="sa_show_language_switcher"
                                                   name="show_language_switcher" {{ ($tenantSettings['show_language_switcher'] ?? '1') == '1' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="sa_show_language_switcher">Mostrar selector de idioma en la web</label>
                                        </div>
                                        <small class="text-muted">Oculta el widget selector, no afecta la deteccion de idioma</small>
                                    </div>
                                </div>

                                @if(count($activeLanguages) > 0)
                                <div class="mb-3">
                                    <label class="form-label">Idiomas activos</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($activeLanguages as $lang)
                                            <span class="badge bg-{{ ($tenantSettings['default_lang'] ?? 'es') === $lang->code ? 'primary' : 'secondary' }}">
                                                {{ $lang->name }} ({{ $lang->code }})
                                                @if(($tenantSettings['default_lang'] ?? 'es') === $lang->code)
                                                    <i class="bi bi-star-fill ms-1"></i>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">Para añadir o quitar idiomas, accede al panel del tenant: <code>/admin/languages</code></small>
                                </div>
                                @endif

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success" id="btnSaveSiteSettings">
                                        <span class="btn-text"><i class="bi bi-check-lg"></i> Guardar Ajustes del Sitio</span>
                                        <span class="btn-loading d-none">
                                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                            Guardando...
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ============================================ --}}
                {{-- SEO & SOCIAL SETTINGS (Collapsible) --}}
                {{-- ============================================ --}}
                <div class="card mt-3">
                    <div class="card-header p-0">
                        <button class="btn btn-link text-decoration-none w-100 text-start p-3 d-flex justify-content-between align-items-center collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#seoSettingsCollapse"
                                aria-expanded="false"
                                aria-controls="seoSettingsCollapse">
                            <h5 class="mb-0"><i class="bi bi-search"></i> SEO y Redes Sociales</h5>
                            <i class="bi bi-chevron-down" id="seoSettingsChevron" style="transition: transform 0.3s;"></i>
                        </button>
                    </div>
                    <div class="collapse" id="seoSettingsCollapse">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Configura el SEO y redes sociales de <strong>{{ $tenant->domain }}</strong> sin entrar en su panel de administracion.
                            </p>

                            <form id="seoSettingsForm" enctype="multipart/form-data">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                                {{-- Configuracion SEO --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-search"></i> Configuracion SEO</h6>

                                <div class="mb-3">
                                    <label class="form-label">Palabras clave (keywords)</label>
                                    <input type="text" name="site_keywords" class="form-control"
                                           value="{{ $tenantSettings['site_keywords'] ?? '' }}">
                                    <small class="text-muted">Palabras clave separadas por comas (ej: cms, web, contenido)</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Autor del sitio</label>
                                    <input type="text" name="site_author" class="form-control"
                                           value="{{ $tenantSettings['site_author'] ?? '' }}">
                                    <small class="text-muted">Nombre del autor o empresa propietaria del sitio</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Imagen para compartir en redes (Open Graph)</label>
                                    <input type="file" name="og_image" class="form-control" accept="image/*">
                                    <small class="text-muted">Tamano recomendado: 1200x630 pixeles</small>

                                    @php $saOgImage = $tenantSettings['og_image'] ?? ''; @endphp
                                    @if(!empty($saOgImage))
                                        <div class="mt-2" id="currentOgImagePreview">
                                            <img src="/public/{{ $saOgImage }}" alt="Imagen OG actual"
                                                 style="max-height: 120px; max-width: 100%;" class="border p-2 rounded"
                                                 onerror="this.style.display='none'">
                                            <p class="text-muted mt-1"><small>{{ $saOgImage }}</small></p>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteTenantOgImage()">
                                                <i class="bi bi-trash"></i> Eliminar imagen OG
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Usuario de X (Twitter)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">@</span>
                                        <input type="text" name="twitter_site" class="form-control"
                                               value="{{ $tenantSettings['twitter_site'] ?? '' }}"
                                               placeholder="usuario">
                                    </div>
                                    <small class="text-muted">Sin incluir el @ (ej: musedock). Se usa para las Twitter/X Cards.</small>
                                </div>

                                <hr>

                                {{-- Redes Sociales --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-share"></i> Redes Sociales</h6>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Facebook</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-facebook"></i></span>
                                            <input type="url" name="social_facebook" class="form-control"
                                                   value="{{ $tenantSettings['social_facebook'] ?? '' }}"
                                                   placeholder="https://facebook.com/tuempresa">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">X (Twitter)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-twitter-x"></i></span>
                                            <input type="url" name="social_twitter" class="form-control"
                                                   value="{{ $tenantSettings['social_twitter'] ?? '' }}"
                                                   placeholder="https://x.com/tuempresa">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Instagram</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-instagram"></i></span>
                                            <input type="url" name="social_instagram" class="form-control"
                                                   value="{{ $tenantSettings['social_instagram'] ?? '' }}"
                                                   placeholder="https://instagram.com/tuempresa">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">LinkedIn</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-linkedin"></i></span>
                                            <input type="url" name="social_linkedin" class="form-control"
                                                   value="{{ $tenantSettings['social_linkedin'] ?? '' }}"
                                                   placeholder="https://linkedin.com/company/tuempresa">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">YouTube</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-youtube"></i></span>
                                            <input type="url" name="social_youtube" class="form-control"
                                                   value="{{ $tenantSettings['social_youtube'] ?? '' }}"
                                                   placeholder="https://youtube.com/c/tuempresa">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Pinterest</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-pinterest"></i></span>
                                            <input type="url" name="social_pinterest" class="form-control"
                                                   value="{{ $tenantSettings['social_pinterest'] ?? '' }}"
                                                   placeholder="https://pinterest.com/tuempresa">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success" id="btnSaveSeoSettings">
                                        <span class="btn-text"><i class="bi bi-check-lg"></i> Guardar Ajustes SEO</span>
                                        <span class="btn-loading d-none">
                                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                            Guardando...
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ============================================ --}}
                {{-- BLOG / POST SETTINGS (Collapsible) --}}
                {{-- ============================================ --}}
                <div class="card mt-3">
                    <div class="card-header p-0">
                        <button class="btn btn-link text-decoration-none w-100 text-start p-3 d-flex justify-content-between align-items-center collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#blogSettingsCollapse"
                                aria-expanded="false"
                                aria-controls="blogSettingsCollapse">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-post"></i> Configuracion del Blog</h5>
                            <i class="bi bi-chevron-down" id="blogSettingsChevron" style="transition: transform 0.3s;"></i>
                        </button>
                    </div>
                    <div class="collapse" id="blogSettingsCollapse">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Configura la estructura de URLs y opciones del blog de <strong>{{ $tenant->domain }}</strong> sin entrar en su panel de administracion.
                            </p>

                            <form id="blogSettingsForm">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                                {{-- Estructura de URLs del blog --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-link-45deg"></i> Estructura de URLs del blog</h6>

                                @php
                                    $currentBlogPrefix = $tenantSettings['blog_url_prefix'] ?? 'blog';
                                    $hasBlogPrefix = ($currentBlogPrefix !== '');
                                @endphp

                                <div class="mb-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="blog_url_mode" id="sa_blog_url_with_prefix" value="prefix"
                                            @if($hasBlogPrefix) checked @endif>
                                        <label class="form-check-label" for="sa_blog_url_with_prefix">
                                            <strong>Con prefijo</strong>
                                            <small class="d-block text-muted">Los posts se acceden con un prefijo en la URL (ej: /blog/mi-post)</small>
                                        </label>
                                    </div>

                                    <div id="sa-blog-prefix-input" class="ms-4 mb-3" style="display: {{ $hasBlogPrefix ? 'block' : 'none' }};">
                                        <label class="form-label">Nombre del prefijo</label>
                                        <div class="input-group" style="max-width: 400px;">
                                            <span class="input-group-text">/</span>
                                            <input type="text" name="blog_url_prefix" id="sa_blog_url_prefix_input" class="form-control"
                                                value="{{ $currentBlogPrefix }}" placeholder="blog"
                                                pattern="[a-z0-9\-]+" title="Solo letras minusculas, numeros y guiones">
                                            <span class="input-group-text">/mi-post</span>
                                        </div>
                                        <small class="text-muted">Solo letras minusculas, numeros y guiones. Ejemplos: blog, noticias, articulos</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="blog_url_mode" id="sa_blog_url_no_prefix" value="none"
                                            @if(!$hasBlogPrefix) checked @endif>
                                        <label class="form-check-label" for="sa_blog_url_no_prefix">
                                            <strong>Sin prefijo</strong>
                                            <small class="d-block text-muted">Los posts se acceden directamente desde la raiz (ej: /mi-post)</small>
                                        </label>
                                    </div>
                                </div>

                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-eye"></i> <strong>Vista previa:</strong>
                                    <span id="sa-blog-url-preview">{{ $tenant->domain }}/<span id="sa-prefix-preview">{{ $hasBlogPrefix ? $currentBlogPrefix . '/' : '' }}</span>ejemplo-de-post</span>
                                </div>

                                <hr>

                                {{-- Numero de entradas --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-list-ol"></i> Numero de entradas</h6>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Entradas por pagina en el blog</label>
                                        <div class="input-group">
                                            <input type="number" name="posts_per_page" class="form-control"
                                                   value="{{ $tenantSettings['posts_per_page'] ?? '10' }}" min="1" max="100">
                                            <span class="input-group-text">entradas</span>
                                        </div>
                                        <small class="text-muted">Cuantos posts se muestran por pagina</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Entradas en el feed RSS</label>
                                        <div class="input-group">
                                            <input type="number" name="posts_per_rss" class="form-control"
                                                   value="{{ $tenantSettings['posts_per_rss'] ?? '10' }}" min="1" max="50">
                                            <span class="input-group-text">elementos</span>
                                        </div>
                                        <small class="text-muted">Numero de entradas en el feed RSS</small>
                                    </div>
                                </div>

                                <hr>

                                {{-- Visibilidad en buscadores --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-robot"></i> Visibilidad en buscadores</h6>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="blog_noindex" id="sa_blog_noindex"
                                           {{ ($tenantSettings['blog_public'] ?? '1') == '0' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sa_blog_noindex">
                                        <strong>Pedir a los motores de busqueda que no indexen este sitio</strong>
                                    </label>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-info-circle"></i> Depende de los motores de busqueda atender esta peticion o no.
                                    </small>
                                </div>

                                <hr>

                                {{-- Diseño del listado de blog --}}
                                <h6 class="text-primary mb-3"><i class="bi bi-layout-text-window-reverse"></i> Diseño del listado de blog</h6>

                                @php
                                    $currentBlogLayout = $tenantBlogLayout ?? 'grid';
                                @endphp

                                <div class="mb-3">
                                    <label class="form-label">Layout del listado de entradas</label>
                                    <select name="blog_layout" class="form-select" style="max-width: 400px;">
                                        <option value="grid" {{ $currentBlogLayout === 'grid' ? 'selected' : '' }}>Cuadricula (3 columnas con tarjetas)</option>
                                        <option value="list" {{ $currentBlogLayout === 'list' ? 'selected' : '' }}>Lista (imagen izquierda + contenido derecha)</option>
                                        <option value="magazine" {{ $currentBlogLayout === 'magazine' ? 'selected' : '' }}>Revista (destacado grande + cuadricula)</option>
                                        <option value="minimal" {{ $currentBlogLayout === 'minimal' ? 'selected' : '' }}>Minimalista (solo texto, sin imagenes)</option>
                                        <option value="newspaper" {{ $currentBlogLayout === 'newspaper' ? 'selected' : '' }}>Periodico (1 grande + 2 laterales + lista)</option>
                                        <option value="fashion" {{ $currentBlogLayout === 'fashion' ? 'selected' : '' }}>Fashion (imagenes circulares, estilo elegante)</option>
                                    </select>
                                    <small class="text-muted">Selecciona como se muestran las entradas en el listado del blog, categorias y etiquetas.</small>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="blog_show_views" id="sa_blog_show_views" value="1"
                                           {{ ($tenantSettings['blog_show_views'] ?? '1') === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sa_blog_show_views">
                                        <strong>Mostrar numero de visitas en los posts</strong>
                                    </label>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-info-circle"></i> Si se desactiva, el contador de visitas no se mostrara en la pagina del post.
                                    </small>
                                </div>

                                <h6 class="text-primary mb-3"><i class="bi bi-megaphone"></i> Barras de ticker</h6>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="blog_ticker_tags" id="sa_blog_ticker_tags" value="1"
                                                   {{ ($tenantBlogTickerTags ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="sa_blog_ticker_tags">
                                                <strong>Top Tags</strong>
                                            </label>
                                            <small class="text-muted d-block">Muestra las etiquetas mas populares.</small>
                                        </div>
                                        <div class="ms-4 mt-1 mb-2">
                                            <label class="form-label small mb-1">Posicion</label>
                                            <select name="blog_ticker_tags_position" class="form-select form-select-sm" style="max-width: 200px;">
                                                @php $tagsPos = $tenantBlogTickerTagsPosition ?? 'top'; @endphp
                                                <option value="top" {{ $tagsPos === 'top' ? 'selected' : '' }}>Arriba de los posts</option>
                                                <option value="bottom" {{ $tagsPos === 'bottom' ? 'selected' : '' }}>Debajo de los posts</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="blog_ticker_latest" id="sa_blog_ticker_latest" value="1"
                                                   {{ ($tenantBlogTickerLatest ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="sa_blog_ticker_latest">
                                                <strong>Latest Post</strong>
                                            </label>
                                            <small class="text-muted d-block">Ticker con los ultimos posts publicados.</small>
                                        </div>
                                        <div class="ms-4 mt-1 mb-2">
                                            <label class="form-label small mb-1">Posicion</label>
                                            <select name="blog_ticker_latest_position" class="form-select form-select-sm" style="max-width: 200px;">
                                                @php $latestPos = $tenantBlogTickerLatestPosition ?? 'top'; @endphp
                                                <option value="top" {{ $latestPos === 'top' ? 'selected' : '' }}>Arriba de los posts</option>
                                                <option value="bottom" {{ $latestPos === 'bottom' ? 'selected' : '' }}>Debajo de los posts</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="text-primary mb-3"><i class="bi bi-clock"></i> Reloj en tiempo real</h6>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input sa-clock-toggle" type="checkbox" name="blog_topbar_clock" id="sa_blog_topbar_clock" value="1"
                                                   {{ ($tenantBlogTopbarClock ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="sa_blog_topbar_clock">
                                                <strong>Reloj en el topbar</strong>
                                            </label>
                                            <small class="text-muted d-block">Muestra el reloj en la barra superior (junto al email y redes sociales).</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input sa-clock-toggle" type="checkbox" name="blog_ticker_clock" id="sa_blog_ticker_clock" value="1"
                                                   {{ ($tenantBlogTickerClock ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="sa_blog_ticker_clock">
                                                <strong>Reloj en el ticker</strong>
                                            </label>
                                            <small class="text-muted d-block">Muestra el reloj en la barra de ticker del blog (requiere ticker activado).</small>
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $anyClockActive = ($tenantBlogTopbarClock ?? false) || ($tenantBlogTickerClock ?? false);
                                @endphp
                                <div id="sa_clock_options_wrapper" style="display: {{ $anyClockActive ? 'block' : 'none' }};">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Formato del reloj</label>
                                            <select name="blog_header_clock_locale" class="form-select">
                                                @php $clockLocale = $tenantBlogHeaderClockLocale ?? 'es'; @endphp
                                                <option value="es" {{ $clockLocale === 'es' ? 'selected' : '' }}>Español (Mié. 4 Mar. 2026  14:26:11)</option>
                                                <option value="en" {{ $clockLocale === 'en' ? 'selected' : '' }}>English (Wed. Mar 4th, 2026  2:26:11 p.m.)</option>
                                                <option value="fr" {{ $clockLocale === 'fr' ? 'selected' : '' }}>Français (Mer. 4 Mars 2026  14:26:11)</option>
                                                <option value="de" {{ $clockLocale === 'de' ? 'selected' : '' }}>Deutsch (Mi. 4. März 2026  14:26:11)</option>
                                                <option value="pt" {{ $clockLocale === 'pt' ? 'selected' : '' }}>Português (Qua. 4 Mar. 2026  14:26:11)</option>
                                            </select>
                                            <small class="text-muted">Idioma y formato para la fecha y hora.</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Zona horaria del reloj</label>
                                            @php $clockTz = $tenantBlogHeaderClockTimezone ?? 'Europe/Madrid'; @endphp
                                            <select name="blog_header_clock_timezone" class="form-select">
                                                <option value="UTC" {{ $clockTz === 'UTC' ? 'selected' : '' }}>UTC</option>
                                                <option value="Europe/Madrid" {{ $clockTz === 'Europe/Madrid' ? 'selected' : '' }}>Europa / Madrid</option>
                                                <option value="Europe/London" {{ $clockTz === 'Europe/London' ? 'selected' : '' }}>Europa / Londres</option>
                                                <option value="Europe/Paris" {{ $clockTz === 'Europe/Paris' ? 'selected' : '' }}>Europa / Paris</option>
                                                <option value="America/New_York" {{ $clockTz === 'America/New_York' ? 'selected' : '' }}>America / Nueva York</option>
                                                <option value="America/Los_Angeles" {{ $clockTz === 'America/Los_Angeles' ? 'selected' : '' }}>America / Los Angeles</option>
                                                <option value="America/Mexico_City" {{ $clockTz === 'America/Mexico_City' ? 'selected' : '' }}>America / Ciudad de Mexico</option>
                                                <option value="America/Bogota" {{ $clockTz === 'America/Bogota' ? 'selected' : '' }}>America / Bogota</option>
                                                <option value="America/Argentina/Buenos_Aires" {{ $clockTz === 'America/Argentina/Buenos_Aires' ? 'selected' : '' }}>America / Buenos Aires</option>
                                                <option value="America/Santiago" {{ $clockTz === 'America/Santiago' ? 'selected' : '' }}>America / Santiago</option>
                                                <option value="America/Lima" {{ $clockTz === 'America/Lima' ? 'selected' : '' }}>America / Lima</option>
                                                <option value="America/Caracas" {{ $clockTz === 'America/Caracas' ? 'selected' : '' }}>America / Caracas</option>
                                                <option value="Asia/Tokyo" {{ $clockTz === 'Asia/Tokyo' ? 'selected' : '' }}>Asia / Tokio</option>
                                                <option value="Asia/Shanghai" {{ $clockTz === 'Asia/Shanghai' ? 'selected' : '' }}>Asia / Shanghai</option>
                                                <option value="Australia/Sydney" {{ $clockTz === 'Australia/Sydney' ? 'selected' : '' }}>Australia / Sidney</option>
                                            </select>
                                            <small class="text-muted">La hora mostrada correspondera a esta zona horaria.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-3">
                                <h6 class="text-primary mb-3"><i class="bi bi-layout-sidebar-inset"></i> Sidebar del post individual</h6>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="blog_sidebar_related_posts" id="sa_blog_sidebar_related_posts" value="1"
                                           {{ ($tenantBlogSidebarRelatedPosts ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sa_blog_sidebar_related_posts">
                                        <strong>Mostrar posts relacionados</strong>
                                    </label>
                                    <small class="text-muted d-block mt-1">Muestra tarjetas de posts relacionados por categoria/etiqueta en la barra lateral.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Cantidad de posts relacionados</label>
                                    @php $currentRelatedCount = $tenantBlogSidebarRelatedPostsCount ?? '4'; @endphp
                                    <select name="blog_sidebar_related_posts_count" class="form-select" style="max-width: 200px;">
                                        <option value="2" {{ $currentRelatedCount == '2' ? 'selected' : '' }}>2 posts</option>
                                        <option value="3" {{ $currentRelatedCount == '3' ? 'selected' : '' }}>3 posts</option>
                                        <option value="4" {{ $currentRelatedCount == '4' ? 'selected' : '' }}>4 posts</option>
                                        <option value="6" {{ $currentRelatedCount == '6' ? 'selected' : '' }}>6 posts</option>
                                    </select>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="blog_sidebar_tags" id="sa_blog_sidebar_tags" value="1"
                                           {{ ($tenantBlogSidebarTags ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sa_blog_sidebar_tags">
                                        <strong>Mostrar etiquetas populares</strong>
                                    </label>
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="blog_sidebar_categories" id="sa_blog_sidebar_categories" value="1"
                                           {{ ($tenantBlogSidebarCategories ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="sa_blog_sidebar_categories">
                                        <strong>Mostrar categorias populares</strong>
                                    </label>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success" id="btnSaveBlogSettings">
                                        <span class="btn-text"><i class="bi bi-check-lg"></i> Guardar Configuracion del Blog</span>
                                        <span class="btn-loading d-none">
                                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                            Guardando...
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ============================================ --}}
                {{-- AUTHOR PROFILE (Collapsible) --}}
                {{-- ============================================ --}}
                <div class="card mt-3">
                    <div class="card-header p-0">
                        <button class="btn btn-link text-decoration-none w-100 text-start p-3 d-flex justify-content-between align-items-center collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#authorProfileCollapse"
                                aria-expanded="false"
                                aria-controls="authorProfileCollapse">
                            <h5 class="mb-0"><i class="bi bi-person-badge"></i> Perfil de Autor</h5>
                            <i class="bi bi-chevron-down" id="authorProfileChevron" style="transition: transform 0.3s;"></i>
                        </button>
                    </div>
                    <div class="collapse" id="authorProfileCollapse">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Gestiona el perfil de autor del administrador principal de <strong>{{ $tenant->domain }}</strong>.
                            </p>

                            @if($tenantRootAdmin)
                            <form id="authorProfileForm">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                                <div class="mb-3">
                                    <label class="form-label">Administrador</label>
                                    <input type="text" class="form-control" value="{{ $tenantRootAdmin->name }} ({{ $tenantRootAdmin->email ?? '' }})" disabled>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="sa_author_page_enabled" name="author_page_enabled" value="1" {{ ($tenantRootAdmin->author_page_enabled ?? 0) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="sa_author_page_enabled">Activar pagina publica de autor</label>
                                    </div>
                                    <small class="text-muted">Si se activa, el nombre del autor sera un enlace en los posts del blog.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="sa_bio" class="form-label">Biografia</label>
                                    <textarea class="form-control" id="sa_bio" name="bio" rows="3" placeholder="Biografia del autor...">{{ $tenantRootAdmin->bio ?? '' }}</textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="bi bi-twitter-x"></i> Twitter / X</label>
                                        <input type="url" class="form-control" name="social_twitter" value="{{ $tenantRootAdmin->social_twitter ?? '' }}" placeholder="https://x.com/...">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="bi bi-linkedin"></i> LinkedIn</label>
                                        <input type="url" class="form-control" name="social_linkedin" value="{{ $tenantRootAdmin->social_linkedin ?? '' }}" placeholder="https://linkedin.com/in/...">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="bi bi-github"></i> GitHub</label>
                                        <input type="url" class="form-control" name="social_github" value="{{ $tenantRootAdmin->social_github ?? '' }}" placeholder="https://github.com/...">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="bi bi-globe"></i> Website</label>
                                        <input type="url" class="form-control" name="social_website" value="{{ $tenantRootAdmin->social_website ?? '' }}" placeholder="https://...">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">
                                        <span class="btn-text"><i class="bi bi-check-lg"></i> Guardar Perfil de Autor</span>
                                        <span class="btn-loading d-none">
                                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                            Guardando...
                                        </span>
                                    </button>
                                </div>
                            </form>
                            @else
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle"></i> No se encontro un administrador principal para este tenant.
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ============================================ --}}
                {{-- AUTOMATISMOS IA (Collapsible) --}}
                {{-- ============================================ --}}
                <div class="card mt-3">
                    <div class="card-header p-0">
                        <button class="btn btn-link text-decoration-none w-100 text-start p-3 d-flex justify-content-between align-items-center collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#automationsCollapse"
                                aria-expanded="false"
                                aria-controls="automationsCollapse">
                            <h5 class="mb-0"><i class="bi bi-robot"></i> Automatismos IA</h5>
                            <i class="bi bi-chevron-down" id="automationsChevron" style="transition: transform 0.3s;"></i>
                        </button>
                    </div>
                    <div class="collapse" id="automationsCollapse">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Herramientas de IA para enriquecer automaticamente el contenido de <strong>{{ $tenant->domain }}</strong>.
                                Usa el proveedor de IA por defecto del sistema.
                            </p>

                            {{-- Auto-Categorizar y Etiquetar --}}
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="mb-2"><i class="bi bi-tags"></i> Auto-Categorizar y Etiquetar Posts</h6>
                                    <p class="text-muted small mb-3">
                                        Analiza el contenido de los posts publicados y sugiere categorias y tags relevantes
                                        que falten o que enriquezcan la taxonomia del blog mediante IA.
                                    </p>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Alcance</label>
                                        <select class="form-select form-select-sm" id="autoTagScope">
                                            <option value="all">Todos los posts publicados</option>
                                            <option value="untagged">Solo posts con pocas categorias/tags</option>
                                        </select>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnPreviewAutoTag" onclick="runAutoTag(true)">
                                            <span class="btn-text"><i class="bi bi-eye"></i> Previsualizar</span>
                                            <span class="btn-loading d-none">
                                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                                Analizando...
                                            </span>
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnApplyAutoTag" onclick="runAutoTag(false)">
                                            <span class="btn-text"><i class="bi bi-magic"></i> Aplicar Directamente</span>
                                            <span class="btn-loading d-none">
                                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                                Aplicando...
                                            </span>
                                        </button>
                                    </div>

                                    {{-- Contenedor para resultados --}}
                                    <div id="autoTagResults" class="mt-3 d-none">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================================ --}}
                {{-- SCROLL TO TOP --}}
                {{-- ============================================ --}}
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="text-primary mb-3"><i class="bi bi-arrow-up-circle"></i> Boton "Volver arriba"</h6>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="scroll_to_top_enabled" name="scroll_to_top_enabled"
                                   {{ !empty($tenantScrollToTopEnabled) ? 'checked' : '' }}>
                            <label class="form-check-label" for="scroll_to_top_enabled">
                                Mostrar boton de scroll hacia arriba (esquina inferior derecha)
                            </label>
                        </div>
                        <small class="text-muted">Desactiva esta opcion si interfiere con un chatbot u otro widget flotante.</small>
                    </div>
                </div>

                {{-- ============================================ --}}
                {{-- CUSTOM CSS / JS (Collapsible) --}}
                {{-- ============================================ --}}
                @php
                    $themeSlug = $tenant->theme ?? 'default';
                    $customCssPath = APP_ROOT . "/public/assets/themes/tenant_{$tenant->id}/{$themeSlug}/css/custom.css";
                    $customJsPath = APP_ROOT . "/public/assets/themes/tenant_{$tenant->id}/{$themeSlug}/js/custom.js";

                    $existingCustomCss = '';
                    if (file_exists($customCssPath)) {
                        $fullCss = file_get_contents($customCssPath);
                        // Extract only the custom part after the marker
                        $marker = '/* --- CSS Personalizado --- */';
                        $pos = strpos($fullCss, $marker);
                        if ($pos !== false) {
                            $existingCustomCss = trim(substr($fullCss, $pos + strlen($marker)));
                        }
                    }

                    $existingCustomJs = '';
                    if (file_exists($customJsPath)) {
                        $existingCustomJs = trim(file_get_contents($customJsPath));
                    }
                @endphp
                <div class="card mt-3">
                    <div class="card-header p-0">
                        <button class="btn btn-link text-decoration-none w-100 text-start p-3 d-flex justify-content-between align-items-center collapsed"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#customCodeCollapse"
                                aria-expanded="false"
                                aria-controls="customCodeCollapse">
                            <h5 class="mb-0"><i class="bi bi-code-slash"></i> Codigo personalizado (CSS / JS)</h5>
                            <i class="bi bi-chevron-down" id="customCodeChevron" style="transition: transform 0.3s;"></i>
                        </button>
                    </div>
                    <div class="collapse" id="customCodeCollapse">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Inyecta CSS y JavaScript personalizado en el frontend de <strong>{{ $tenant->domain }}</strong>.
                                Estos estilos y scripts se cargan despues de los del tema.
                            </p>

                            <form id="customCodeForm">
                                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

                                <div class="mb-3">
                                    <label for="sa_custom_css" class="form-label fw-bold">
                                        <i class="bi bi-filetype-css text-primary"></i> CSS personalizado
                                    </label>
                                    <textarea class="form-control font-monospace" id="sa_custom_css" name="custom_css" rows="10"
                                              placeholder="/* Escribe tu CSS aqui */&#10;.header { background: #000; }"
                                              style="font-size: 0.85rem; tab-size: 2; background: #1e1e2e; color: #cdd6f4; border-color: #45475a;">{{ $existingCustomCss }}</textarea>
                                    <small class="text-muted">No incluyas etiquetas <code>&lt;style&gt;</code>.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="sa_custom_js" class="form-label fw-bold">
                                        <i class="bi bi-filetype-js text-warning"></i> JavaScript personalizado
                                    </label>
                                    <textarea class="form-control font-monospace" id="sa_custom_js" name="custom_js" rows="8"
                                              placeholder="// Escribe tu JavaScript aqui&#10;console.log('Hello');"
                                              style="font-size: 0.85rem; tab-size: 2; background: #1e1e2e; color: #cdd6f4; border-color: #45475a;">{{ $existingCustomJs }}</textarea>
                                    <small class="text-muted">Puedes pegar codigo con etiquetas <code>&lt;script src="..."&gt;</code> (ej: chatbots, analytics). Los dominios externos se añaden automaticamente a la CSP.</small>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success" id="btnSaveCustomCode">
                                        <span class="btn-text"><i class="bi bi-check-lg"></i> Guardar Codigo</span>
                                        <span class="btn-loading d-none">
                                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                            Guardando...
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Panel de estado -->
            <div class="col-md-4">
                <!-- Estado de Caddy -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-server"></i> Estado Caddy</h6>
                        @if($caddyApiAvailable)
                            <span class="badge bg-success">API Conectada</span>
                        @else
                            <span class="badge bg-danger">API No Disponible</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @php
                            $statusClass = match($tenant->caddy_status ?? 'not_configured') {
                                'active' => 'success',
                                'configuring' => 'info',
                                'pending_dns' => 'warning',
                                'error' => 'danger',
                                'suspended' => 'secondary',
                                default => 'light'
                            };
                        @endphp

                        <p class="mb-2">
                            <strong>Estado:</strong>
                            <span class="badge bg-{{ $statusClass }}">{{ $tenant->caddy_status ?? 'No Configurado' }}</span>
                        </p>

                        @if($tenant->caddy_route_id ?? false)
                            <p class="mb-2">
                                <strong>Route ID:</strong><br>
                                <code>{{ $tenant->caddy_route_id }}</code>
                            </p>
                        @endif

                        @if($tenant->caddy_configured_at ?? false)
                            <p class="mb-2">
                                <strong>Configurado:</strong><br>
                                {{ date('d/m/Y H:i', strtotime($tenant->caddy_configured_at)) }}
                            </p>
                        @endif

                        @if($tenant->caddy_error_log ?? false)
                            <div class="alert alert-danger small mb-2">
                                <strong>Último error:</strong><br>
                                {{ $tenant->caddy_error_log }}
                            </div>
                        @endif

                        @if($caddyApiAvailable)
                            <hr>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-info btn-sm" id="btnCheckStatus" onclick="checkStatus()">
                                    <span class="btn-text"><i class="bi bi-arrow-clockwise"></i> Verificar Estado</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                    </span>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnReconfigure" onclick="reconfigure()">
                                    <span class="btn-text"><i class="bi bi-gear"></i> Reconfigurar en Caddy</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Configurando...
                                    </span>
                                </button>
                                <div class="form-text mt-2 small text-muted">
                                    <i class="bi bi-shield-lock"></i> Regenera la ruta y solicita nuevo certificado SSL de Let's Encrypt.
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Info de Caddy Route -->
                @if($caddyRouteInfo ?? false)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-code-slash"></i> Configuración Caddy</h6>
                        </div>
                        <div class="card-body">
                            <pre class="small mb-0" style="max-height: 300px; overflow: auto;">{{ json_encode($caddyRouteInfo, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    </div>
                @endif

                <!-- Cloudflare & Email Routing -->
                @if(!empty($tenant->cloudflare_zone_id))
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-cloud"></i> Cloudflare</h6>
                            <span class="badge bg-success">Configurado</span>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Zone ID:</strong><br>
                                <small class="text-muted">{{ $tenant->cloudflare_zone_id }}</small>
                            </p>

                            @if($tenant->cloudflare_nameservers ?? false)
                                <p class="mb-2">
                                    <strong>Nameservers:</strong><br>
                                    @php
                                        $ns = json_decode($tenant->cloudflare_nameservers, true);
                                    @endphp
                                    @if(is_array($ns))
                                        @foreach($ns as $nameserver)
                                            <small class="text-muted">{{ $nameserver }}</small><br>
                                        @endforeach
                                    @endif
                                </p>
                            @endif

                            <hr>

                            <p class="mb-2">
                                <strong>Email Routing:</strong><br>
                                @if($tenant->email_routing_enabled ?? false)
                                    <span class="badge bg-success">Activo</span>
                                @else
                                    <span class="badge bg-secondary">Desactivado</span>
                                @endif
                            </p>

                            <div class="d-grid gap-2 mt-3">
                                <a href="/musedock/domain-manager/{{ $tenant->id }}/email-routing"
                                   class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-envelope-at"></i> Gestionar Email Routing
                                </a>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Alias de Dominio -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-link-45deg"></i> Alias de Dominio</h6>
                        <span class="badge bg-secondary" id="alias-count">{{ count($domainAliases ?? []) }}</span>
                    </div>
                    <div class="card-body">
                        {{-- Lista de aliases existentes --}}
                        <div id="alias-list">
                            @forelse($domainAliases ?? [] as $alias)
                            <div class="d-flex justify-content-between align-items-center mb-2 alias-row" id="alias-{{ $alias->id }}">
                                <div>
                                    <span class="fw-semibold">{{ $alias->domain }}</span>
                                    @if($alias->include_www)
                                        <small class="text-muted">+ www</small>
                                    @endif
                                    @if($alias->is_subdomain)
                                        <span class="badge bg-info badge-sm">sub</span>
                                    @else
                                        <span class="badge bg-warning text-dark badge-sm">custom</span>
                                    @endif
                                    @if($alias->status === 'active')
                                        <span class="badge bg-success badge-sm">activo</span>
                                    @elseif($alias->status === 'pending')
                                        <span class="badge bg-warning text-dark badge-sm">pendiente</span>
                                    @elseif($alias->status === 'error')
                                        <span class="badge bg-danger badge-sm" title="{{ $alias->error_log }}">error</span>
                                    @else
                                        <span class="badge bg-secondary badge-sm">{{ $alias->status }}</span>
                                    @endif
                                    @if(empty($alias->cloudflare_zone_id) && empty($alias->cloudflare_record_id) && !$alias->is_subdomain)
                                        <span class="badge bg-dark badge-sm">sin CF</span>
                                    @endif
                                    @if(!empty($alias->cloudflare_nameservers) && !$alias->is_subdomain)
                                        <br><small class="text-info"><i class="bi bi-exclamation-circle"></i> NS: {{ $alias->cloudflare_nameservers }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeAlias({{ $alias->id }}, '{{ $alias->domain }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            @empty
                            <p class="text-muted small mb-0" id="no-aliases-msg">No hay alias configurados.</p>
                            @endforelse
                        </div>

                        <hr class="my-2">

                        {{-- Formulario para añadir alias --}}
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="new-alias-domain" placeholder="dominio.com o sub.musedock.com">
                            <button type="button" class="btn btn-outline-primary" onclick="addAlias()" id="btnAddAlias">
                                <i class="bi bi-plus-lg"></i> Añadir
                            </button>
                        </div>
                        <div class="d-flex gap-3 mt-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="alias-include-www" checked>
                                <label class="form-check-label small" for="alias-include-www">Incluir www</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="alias-skip-cloudflare">
                                <label class="form-check-label small" for="alias-skip-cloudflare">No crear zona en Cloudflare</label>
                            </div>
                        </div>
                        <div class="form-text mt-1 small">
                            <i class="bi bi-info-circle text-info"></i>
                            <strong>Subdominios</strong> (*.musedock.com): DNS automático.
                            <strong>Custom</strong>: se crearán nameservers, o marca "No crear zona" si el dominio ya apunta a este servidor.
                        </div>
                    </div>
                </div>

                <!-- Gestión del Tenant -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-shield-lock"></i> Gestión del Tenant</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-warning btn-sm" id="btnRegeneratePermissions" onclick="regeneratePermissions()">
                                <span class="btn-text"><i class="bi bi-key"></i> Regenerar Permisos</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Regenerando...
                                </span>
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" id="btnRegenerateMenus" onclick="regenerateMenus()">
                                <span class="btn-text"><i class="bi bi-list-nested"></i> Regenerar Menús</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Regenerando...
                                </span>
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" id="btnRegenerateModules" onclick="regenerateModules()">
                                <span class="btn-text"><i class="bi bi-puzzle"></i> Regenerar Módulos</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Regenerando...
                                </span>
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnRegenerateLanguages" onclick="regenerateLanguages()">
                                <span class="btn-text"><i class="bi bi-translate"></i> Regenerar Idiomas</span>
                                <span class="btn-loading d-none">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Regenerando...
                                </span>
                            </button>
                        </div>
                        <div class="form-text mt-2 small">
                            Aplica la configuración de <a href="/musedock/settings/tenant-defaults">tenant-defaults</a> a este tenant.
                            <br><strong>No afecta contraseñas</strong> de usuarios existentes.
                    </div>
                </div>

                <!-- Plugins del Tenant -->
                @if(!empty($sharedPlugins))
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-puzzle"></i> Plugins del Tenant</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @foreach($sharedPlugins as $sp)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <i class="bi bi-{{ str_replace('fa-', '', $sp['icon'] ?? 'puzzle') }}"></i>
                                    <strong>{{ $sp['name'] }}</strong>
                                    <small class="text-muted">v{{ $sp['version'] }}</small>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input type="checkbox" class="form-check-input plugin-toggle"
                                           id="plugin-{{ $sp['slug'] }}"
                                           data-slug="{{ $sp['slug'] }}"
                                           {{ $sp['active'] ? 'checked' : '' }}
                                           onchange="toggleTenantPlugin('{{ $sp['slug'] }}', this.checked)">
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent">
                        <small class="text-muted">Activa plugins compartidos para este tenant.</small>
                    </div>
                </div>
                @endif

                <!-- Acciones rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-lightning"></i> Acciones Rápidas</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="https://{{ $tenant->domain }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-box-arrow-up-right"></i> Visitar Sitio
                            </a>
                            <a href="/musedock/tenants/{{ $tenant->id }}/edit" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-building"></i> Editar Tenant Completo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
// Persistir estado de acordeones en localStorage
(function() {
    const storageKey = 'dm-edit-{{ $tenant->id }}-accordions';

    // Restaurar estado guardado
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
        Object.keys(saved).forEach(function(id) {
            const el = document.getElementById(id);
            if (el && saved[id]) {
                el.classList.add('show');
                const btn = document.querySelector('[data-bs-target="#' + id + '"]');
                if (btn) {
                    btn.classList.remove('collapsed');
                    btn.setAttribute('aria-expanded', 'true');
                }
            }
        });
    } catch (e) {}

    // Escuchar cambios de estado
    document.querySelectorAll('.collapse').forEach(function(el) {
        el.addEventListener('shown.bs.collapse', function() {
            saveAccordionState();
        });
        el.addEventListener('hidden.bs.collapse', function() {
            saveAccordionState();
        });
    });

    function saveAccordionState() {
        var state = {};
        document.querySelectorAll('.collapse').forEach(function(el) {
            if (el.id) {
                state[el.id] = el.classList.contains('show');
            }
        });
        localStorage.setItem(storageKey, JSON.stringify(state));
    }
})();

// Token CSRF para peticiones AJAX
const csrfToken = '<?= csrf_token() ?>';

// Helper para toggle de spinner en botones
function toggleBtnSpinner(btn, loading) {
    const btnText = btn.querySelector('.btn-text');
    const btnLoading = btn.querySelector('.btn-loading');
    if (loading) {
        btnText.classList.add('d-none');
        btnLoading.classList.remove('d-none');
        btn.disabled = true;
    } else {
        btnText.classList.remove('d-none');
        btnLoading.classList.add('d-none');
        btn.disabled = false;
    }
}

// Spinner en el formulario principal de guardar
document.querySelector('form[action*="domain-manager"]').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnSubmit');
    toggleBtnSpinner(btn, true);
});

async function checkStatus() {
    const btn = document.getElementById('btnCheckStatus');
    toggleBtnSpinner(btn, true);

    // Mostrar modal de SweetAlert2 con loading
    Swal.fire({
        title: '<i class="bi bi-info-circle text-primary"></i> Estado del Dominio',
        html: `
            <div class="text-center py-3">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 mb-0">Verificando...</p>
            </div>
        `,
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        width: '500px'
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/status', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
            const sslStatus = data.ssl_valid
                ? '<span class="text-success"><i class="bi bi-shield-check"></i> Válido</span>'
                : '<span class="text-warning"><i class="bi bi-shield-exclamation"></i> No válido</span>';

            const domainStatus = data.domain_responds
                ? '<span class="text-success"><i class="bi bi-check-circle"></i> Responde</span>'
                : '<span class="text-danger"><i class="bi bi-x-circle"></i> No responde</span>';

            const routeStatus = data.route_exists
                ? '<span class="text-success"><i class="bi bi-check-circle"></i> Existe</span>'
                : '<span class="text-warning"><i class="bi bi-x-circle"></i> No existe</span>';

            Swal.fire({
                title: '<i class="bi bi-info-circle text-primary"></i> Estado del Dominio',
                html: `
                    <table class="table table-sm mb-0 text-start">
                        <tr><th>Dominio</th><td>${data.domain}</td></tr>
                        <tr><th>Estado Caddy</th><td><span class="badge bg-info">${data.caddy_status}</span></td></tr>
                        <tr><th>Ruta en Caddy</th><td>${routeStatus}</td></tr>
                        <tr><th>Respuesta HTTPS</th><td>${domainStatus}</td></tr>
                        <tr><th>Certificado SSL</th><td>${sslStatus}</td></tr>
                        ${data.http_code ? `<tr><th>Código HTTP</th><td>${data.http_code}</td></tr>` : ''}
                    </table>
                `,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#0d6efd',
                width: '500px'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo verificar el estado del dominio.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

async function reconfigure() {
    const result = await Swal.fire({
        title: '<i class="bi bi-gear text-primary"></i> Reconfigurar Dominio',
        html: `
            <div class="text-start">
                <p>¿Deseas reconfigurar este dominio en Caddy?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hara lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar la ruta actual de Caddy</li>
                        <li>Crear una nueva ruta con la configuracion actual (incluir www o no)</li>
                        <li>Solicitar un nuevo certificado SSL de Let's Encrypt</li>
                    </ul>
                </div>
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>El proceso puede tardar hasta 60 segundos mientras se genera el certificado.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-gear me-1"></i> Reconfigurar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnReconfigure');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Reconfigurando...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/reconfigure', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Dominio Reconfigurado',
                text: 'El dominio se ha reconfigurado correctamente en Caddy.',
                confirmButtonColor: '#0d6efd'
            }).then(() => window.location.reload());
        } else {
            toggleBtnSpinner(btn, false);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error || 'No se pudo reconfigurar el dominio.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        toggleBtnSpinner(btn, false);
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    }
}

async function regeneratePermissions() {
    const result = await Swal.fire({
        title: '<i class="bi bi-key text-warning"></i> Regenerar Permisos',
        html: `
            <div class="text-start">
                <p>¿Deseas regenerar los permisos de este tenant?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hará lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar los permisos actuales del tenant</li>
                        <li>Crear nuevos permisos según <a href="/musedock/settings/tenant-defaults" target="_blank">tenant-defaults</a></li>
                        <li>Asignar todos los permisos al rol Admin</li>
                    </ul>
                </div>
                <div class="alert alert-success py-2 mb-0">
                    <i class="bi bi-check-circle me-2"></i>
                    <small><strong>NO</strong> afecta las contraseñas de los usuarios existentes.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-key me-1"></i> Regenerar Permisos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnRegeneratePermissions');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Regenerando permisos...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/regenerate-permissions', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Permisos Regenerados',
                html: `<p>${data.message}</p>`,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudieron regenerar los permisos.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

async function regenerateMenus() {
    const result = await Swal.fire({
        title: '<i class="bi bi-list-nested text-info"></i> Regenerar Menús',
        html: `
            <div class="text-start">
                <p>¿Deseas regenerar los menús del sidebar de este tenant?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hará lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar todos los menús actuales del tenant</li>
                        <li>Copiar los menús desde <code>admin_menus</code> (panel principal)</li>
                        <li>Mantener la jerarquía de submenús</li>
                    </ul>
                </div>
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>Las personalizaciones de menús del tenant se perderán.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-list-nested me-1"></i> Regenerar Menús',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0dcaf0',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnRegenerateMenus');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Regenerando menús...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/regenerate-menus', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Menús Regenerados',
                html: `<p>${data.message}</p>`,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudieron regenerar los menús.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

async function regenerateModules() {
    const result = await Swal.fire({
        title: '<i class="bi bi-puzzle text-success"></i> Regenerar Módulos',
        html: `
            <div class="text-start">
                <p>¿Deseas regenerar la configuración de módulos de este tenant?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hará lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar la configuración de módulos actual del tenant</li>
                        <li>Aplicar los módulos activos según <a href="/musedock/settings/tenant-defaults" target="_blank">tenant-defaults</a></li>
                        <li>Solo los módulos marcados como activos aparecerán en el sidebar</li>
                    </ul>
                </div>
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>Los módulos que el tenant haya activado/desactivado manualmente se perderán.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-puzzle me-1"></i> Regenerar Módulos',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnRegenerateModules');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Regenerando módulos...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/regenerate-modules', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Módulos Regenerados',
                html: `<p>${data.message}</p>`,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudieron regenerar los módulos.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

async function regenerateLanguages() {
    const result = await Swal.fire({
        title: '<i class="bi bi-translate text-primary"></i> Regenerar Idiomas',
        html: `
            <div class="text-start">
                <p>¿Deseas regenerar los idiomas de este tenant?</p>
                <div class="alert alert-info py-2 mb-2">
                    <i class="bi bi-info-circle me-2"></i>
                    <small><strong>Esto hará lo siguiente:</strong></small>
                    <ul class="mb-0 small mt-1">
                        <li>Eliminar todos los idiomas actuales del tenant</li>
                        <li>Crear los idiomas por defecto: <strong>Español</strong> e <strong>Inglés</strong></li>
                        <li>Establecer Español como idioma predeterminado</li>
                    </ul>
                </div>
                <div class="alert alert-warning py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <small>Los idiomas adicionales que el tenant haya configurado se perderán.</small>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-translate me-1"></i> Regenerar Idiomas',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d',
        width: '500px'
    });

    if (!result.isConfirmed) return;

    const btn = document.getElementById('btnRegenerateLanguages');
    toggleBtnSpinner(btn, true);

    Swal.fire({
        title: 'Regenerando idiomas...',
        html: '<p class="mb-0">Por favor espera...</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/regenerate-languages', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Idiomas Regenerados',
                html: `<p>${data.message}</p>`,
                confirmButtonColor: '#0d6efd'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudieron regenerar los idiomas.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

// ============================================
// Toggle Plugin Compartido
// ============================================
async function toggleTenantPlugin(slug, active) {
    const checkbox = document.getElementById('plugin-' + slug);

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/toggle-plugin', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                _csrf: csrfToken,
                slug: slug,
                active: active
            })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: active ? 'Plugin Activado' : 'Plugin Desactivado',
                text: data.message,
                confirmButtonColor: '#0d6efd',
                timer: 2000,
                timerProgressBar: true
            });
        } else {
            // Revertir el switch
            checkbox.checked = !active;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'No se pudo cambiar el estado del plugin.',
                confirmButtonColor: '#0d6efd'
            });
        }
    } catch (error) {
        // Revertir el switch
        checkbox.checked = !active;
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            confirmButtonColor: '#0d6efd'
        });
    }
}

// ============================================
// JavaScript para opciones de Cloudflare en EDIT
// ============================================
@if(empty($tenant->cloudflare_zone_id))
// Mostrar/ocultar opciones de Cloudflare
const configureCloudflareEdit = document.getElementById('configure_cloudflare');
if (configureCloudflareEdit) {
    configureCloudflareEdit.addEventListener('change', function() {
        const cloudflareOptions = document.getElementById('cloudflare-options-edit');
        const emailRoutingCheckbox = document.getElementById('enable_email_routing_edit');

        if (this.checked) {
            cloudflareOptions.classList.remove('d-none');
        } else {
            cloudflareOptions.classList.add('d-none');
            emailRoutingCheckbox.checked = false;
            document.getElementById('email-routing-options-edit').classList.add('d-none');
        }
    });
}

// Mostrar/ocultar campo de email routing destination
const enableEmailRoutingEdit = document.getElementById('enable_email_routing_edit');
if (enableEmailRoutingEdit) {
    enableEmailRoutingEdit.addEventListener('change', function() {
        const emailRoutingOptions = document.getElementById('email-routing-options-edit');

        if (this.checked) {
            emailRoutingOptions.classList.remove('d-none');
        } else {
            emailRoutingOptions.classList.add('d-none');
        }
    });
}

// Vincular dominio existente de Cloudflare
const btnLinkCloudflare = document.getElementById('btnLinkCloudflare');
if (btnLinkCloudflare) {
    btnLinkCloudflare.addEventListener('click', async function() {
        const result = await Swal.fire({
            title: '<i class="bi bi-link-45deg text-primary"></i> Vincular Dominio Existente',
            html: `
                <div class="text-start">
                    <p>¿Deseas vincular este dominio con su configuración existente en Cloudflare?</p>
                    <div class="alert alert-info py-2 mb-2">
                        <i class="bi bi-info-circle me-2"></i>
                        <small><strong>Esto hará lo siguiente:</strong></small>
                        <ul class="mb-0 small mt-1">
                            <li>Buscar el dominio <strong>{{ $tenant->domain }}</strong> en Cloudflare</li>
                            <li>Importar su configuración (Zone ID, nameservers, Email Routing, etc.)</li>
                            <li>Guardar la información en la base de datos</li>
                            <li><strong>NO modificará nada en Cloudflare</strong> (solo lectura)</li>
                        </ul>
                    </div>
                    <div class="alert alert-success py-2 mb-0">
                        <i class="bi bi-shield-check me-2"></i>
                        <small>Tu configuración actual de Cloudflare (DNS, Email Routing, reglas) <strong>permanecerá intacta</strong>.</small>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-link-45deg me-1"></i> Vincular Ahora',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            width: '550px'
        });

        if (!result.isConfirmed) return;

        // Mostrar spinner en botón
        const originalHtml = btnLinkCloudflare.innerHTML;
        btnLinkCloudflare.disabled = true;
        btnLinkCloudflare.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Vinculando...';

        Swal.fire({
            title: 'Vinculando dominio...',
            html: '<p class="mb-0">Buscando configuración en Cloudflare...</p>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/link-cloudflare', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ _csrf: csrfToken })
            });

            const data = await response.json();

            if (data.success) {
                let emailInfo = '';
                if (data.email_routing_enabled && data.email_routing) {
                    emailInfo = `
                        <div class="alert alert-success py-2 mt-2 mb-0">
                            <i class="bi bi-envelope-check me-1"></i>
                            <small><strong>Email Routing detectado:</strong></small>
                            <ul class="mb-0 small mt-1">
                                ${data.email_routing.catch_all_destination ? `<li>Catch-All → ${data.email_routing.catch_all_destination}</li>` : ''}
                                <li>${data.email_routing.rules_count || 0} regla(s) de forwarding</li>
                                <li>${data.email_routing.verified_destinations_count || 0} destinatario(s) verificado(s)</li>
                            </ul>
                        </div>
                    `;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Dominio Vinculado',
                    html: `
                        <p>El dominio se ha vinculado correctamente con Cloudflare.</p>
                        <div class="text-start">
                            <p class="mb-1"><strong>Zone ID:</strong> <code class="small">${data.zone_id}</code></p>
                            <p class="mb-1"><strong>DNS Records:</strong> ${data.dns_records_count} registros encontrados</p>
                            <p class="mb-1"><strong>Email Routing:</strong> ${data.email_routing_enabled ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Desactivado</span>'}</p>
                            ${emailInfo}
                        </div>
                    `,
                    confirmButtonColor: '#0d6efd'
                }).then(() => window.location.reload());
            } else {
                btnLinkCloudflare.disabled = false;
                btnLinkCloudflare.innerHTML = originalHtml;

                Swal.fire({
                    icon: 'error',
                    title: 'Error al vincular',
                    html: `<p>${data.error || 'No se pudo vincular el dominio.'}</p>
                           <div class="alert alert-info py-2 mt-2 mb-0">
                               <small>Verifica que el dominio <strong>{{ $tenant->domain }}</strong> existe en la cuenta de Cloudflare configurada.</small>
                           </div>`,
                    confirmButtonColor: '#0d6efd'
                });
            }
        } catch (error) {
            btnLinkCloudflare.disabled = false;
            btnLinkCloudflare.innerHTML = originalHtml;

            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor. Inténtalo de nuevo.',
                confirmButtonColor: '#0d6efd'
            });
        }
    });
}
@endif

// ============================================
// Site Settings (AJAX form submission)
// ============================================

// Chevron rotation on collapse toggle
const siteSettingsCollapse = document.getElementById('siteSettingsCollapse');
if (siteSettingsCollapse) {
    siteSettingsCollapse.addEventListener('show.bs.collapse', function() {
        document.getElementById('siteSettingsChevron').style.transform = 'rotate(180deg)';
    });
    siteSettingsCollapse.addEventListener('hide.bs.collapse', function() {
        document.getElementById('siteSettingsChevron').style.transform = 'rotate(0deg)';
    });
}

// Submit site settings via AJAX with FormData
const siteSettingsForm = document.getElementById('siteSettingsForm');
if (siteSettingsForm) {
    siteSettingsForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('btnSaveSiteSettings');
        toggleBtnSpinner(btn, true);

        const formData = new FormData(this);

        try {
            const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/site-settings', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Ajustes Guardados',
                    text: data.message,
                    confirmButtonColor: '#0d6efd'
                }).then(() => window.location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudieron guardar los ajustes.',
                    confirmButtonColor: '#0d6efd'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexion',
                text: 'No se pudo conectar con el servidor.',
                confirmButtonColor: '#0d6efd'
            });
        } finally {
            toggleBtnSpinner(btn, false);
        }
    });
}

async function deleteTenantLogo() {
    const result = await Swal.fire({
        title: 'Eliminar Logo',
        text: '¿Estas seguro de eliminar el logo de este tenant?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/delete-logo', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Logo Eliminado',
                text: data.message,
                confirmButtonColor: '#0d6efd'
            }).then(() => window.location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0d6efd' });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error de conexion', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#0d6efd' });
    }
}

async function deleteTenantFavicon() {
    const result = await Swal.fire({
        title: 'Eliminar Favicon',
        text: '¿Estas seguro de eliminar el favicon de este tenant?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/delete-favicon', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Favicon Eliminado',
                text: data.message,
                confirmButtonColor: '#0d6efd'
            }).then(() => window.location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0d6efd' });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error de conexion', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#0d6efd' });
    }
}

// ============================================
// SEO & Social Settings (AJAX form submission)
// ============================================

// Chevron rotation on SEO collapse toggle
const seoSettingsCollapse = document.getElementById('seoSettingsCollapse');
if (seoSettingsCollapse) {
    seoSettingsCollapse.addEventListener('show.bs.collapse', function() {
        document.getElementById('seoSettingsChevron').style.transform = 'rotate(180deg)';
    });
    seoSettingsCollapse.addEventListener('hide.bs.collapse', function() {
        document.getElementById('seoSettingsChevron').style.transform = 'rotate(0deg)';
    });
}

// Submit SEO settings via AJAX with FormData
const seoSettingsForm = document.getElementById('seoSettingsForm');
if (seoSettingsForm) {
    seoSettingsForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('btnSaveSeoSettings');
        toggleBtnSpinner(btn, true);

        const formData = new FormData(this);

        try {
            const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/seo-settings', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Ajustes SEO Guardados',
                    text: data.message,
                    confirmButtonColor: '#0d6efd'
                }).then(() => window.location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudieron guardar los ajustes SEO.',
                    confirmButtonColor: '#0d6efd'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexion',
                text: 'No se pudo conectar con el servidor.',
                confirmButtonColor: '#0d6efd'
            });
        } finally {
            toggleBtnSpinner(btn, false);
        }
    });
}

async function deleteTenantOgImage() {
    const result = await Swal.fire({
        title: 'Eliminar Imagen OG',
        text: '¿Estas seguro de eliminar la imagen Open Graph de este tenant?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/delete-og-image', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ _csrf: csrfToken })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Imagen OG Eliminada',
                text: data.message,
                confirmButtonColor: '#0d6efd'
            }).then(() => window.location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message, confirmButtonColor: '#0d6efd' });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error de conexion', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#0d6efd' });
    }
}

// ============================================
// Blog Settings (AJAX form submission)
// ============================================

// Chevron rotation on Blog Settings collapse toggle
const blogSettingsCollapse = document.getElementById('blogSettingsCollapse');
if (blogSettingsCollapse) {
    blogSettingsCollapse.addEventListener('show.bs.collapse', function() {
        document.getElementById('blogSettingsChevron').style.transform = 'rotate(180deg)';
    });
    blogSettingsCollapse.addEventListener('hide.bs.collapse', function() {
        document.getElementById('blogSettingsChevron').style.transform = 'rotate(0deg)';
    });
}

// Blog URL prefix radio toggle
document.querySelectorAll('input[name="blog_url_mode"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const prefixInput = document.getElementById('sa-blog-prefix-input');
        const prefixPreview = document.getElementById('sa-prefix-preview');
        const inputField = document.getElementById('sa_blog_url_prefix_input');

        if (this.value === 'prefix') {
            prefixInput.style.display = 'block';
            const val = inputField.value.trim() || 'blog';
            prefixPreview.textContent = val + '/';
        } else {
            prefixInput.style.display = 'none';
            prefixPreview.textContent = '';
        }
    });
});

// Update preview while typing prefix
const saPrefixField = document.getElementById('sa_blog_url_prefix_input');
if (saPrefixField) {
    saPrefixField.addEventListener('input', function() {
        const val = this.value.replace(/[^a-z0-9\-]/g, '').trim();
        this.value = val;
        document.getElementById('sa-prefix-preview').textContent = val ? val + '/' : '';
    });
}

// Toggle clock options visibility (show when at least one clock is active)
document.querySelectorAll('.sa-clock-toggle').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var anyChecked = document.getElementById('sa_blog_topbar_clock').checked || document.getElementById('sa_blog_ticker_clock').checked;
        document.getElementById('sa_clock_options_wrapper').style.display = anyChecked ? 'block' : 'none';
    });
});

// Submit blog settings via AJAX
const blogSettingsForm = document.getElementById('blogSettingsForm');
if (blogSettingsForm) {
    blogSettingsForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('btnSaveBlogSettings');
        toggleBtnSpinner(btn, true);

        const formData = new FormData(this);

        try {
            const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/blog-settings', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Configuracion del Blog Guardada',
                    text: data.message,
                    confirmButtonColor: '#0d6efd'
                }).then(() => window.location.reload());
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudo guardar la configuracion del blog.',
                    confirmButtonColor: '#0d6efd'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexion',
                text: 'No se pudo conectar con el servidor.',
                confirmButtonColor: '#0d6efd'
            });
        } finally {
            toggleBtnSpinner(btn, false);
        }
    });
}
// ============================================
// Author Profile
// ============================================
const authorProfileForm = document.getElementById('authorProfileForm');
if (authorProfileForm) {
    authorProfileForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        toggleBtnSpinner(btn, true);

        const formData = new FormData(this);

        try {
            const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/author-profile', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Perfil de Autor Actualizado',
                    text: data.message,
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudo guardar el perfil de autor.',
                    confirmButtonColor: '#0d6efd'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexion',
                text: 'No se pudo conectar con el servidor.',
                confirmButtonColor: '#0d6efd'
            });
        } finally {
            toggleBtnSpinner(btn, false);
        }
    });
}

// ============================================
// Custom CSS / JS
// ============================================
const customCodeForm = document.getElementById('customCodeForm');
if (customCodeForm) {
    customCodeForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = this.querySelector('button[type="submit"]');
        toggleBtnSpinner(btn, true);

        const formData = new FormData(this);

        try {
            const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/custom-code', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Codigo guardado',
                    text: data.message,
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudo guardar el codigo.',
                    confirmButtonColor: '#0d6efd'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexion',
                text: 'No se pudo conectar con el servidor.',
                confirmButtonColor: '#0d6efd'
            });
        } finally {
            toggleBtnSpinner(btn, false);
        }
    });
}

// Chevron animation for custom code
const customCodeCollapse = document.getElementById('customCodeCollapse');
const customCodeChevron = document.getElementById('customCodeChevron');
if (customCodeCollapse && customCodeChevron) {
    customCodeCollapse.addEventListener('show.bs.collapse', () => customCodeChevron.style.transform = 'rotate(180deg)');
    customCodeCollapse.addEventListener('hide.bs.collapse', () => customCodeChevron.style.transform = 'rotate(0deg)');
}

// ============================================
// Alias de Dominio
// ============================================
async function addAlias() {
    const domainInput = document.getElementById('new-alias-domain');
    const includeWww = document.getElementById('alias-include-www').checked;
    const skipCloudflare = document.getElementById('alias-skip-cloudflare').checked;
    const domain = domainInput.value.trim().toLowerCase();
    const btn = document.getElementById('btnAddAlias');

    if (!domain) {
        Swal.fire({ icon: 'warning', title: 'Dominio requerido', text: 'Introduce un dominio para añadir como alias.', confirmButtonColor: '#0d6efd' });
        return;
    }

    // Validación básica de formato
    if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/.test(domain)) {
        Swal.fire({ icon: 'warning', title: 'Formato inválido', text: 'Introduce un dominio válido (ej: ejemplo.com o sub.musedock.com).', confirmButtonColor: '#0d6efd' });
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/add-alias', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: csrfToken, domain: domain, include_www: includeWww, skip_cloudflare: skipCloudflare })
        });
        const data = await response.json();

        if (data.success) {
            let msg = data.message || 'Alias añadido correctamente.';
            if (data.nameservers) {
                msg += '\n\nNameservers para configurar en tu registrador:\n' + data.nameservers;
            }
            await Swal.fire({
                icon: 'success',
                title: 'Alias Añadido',
                html: msg.replace(/\n/g, '<br>'),
                confirmButtonColor: '#0d6efd'
            });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo añadir el alias.', confirmButtonColor: '#0d6efd' });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#0d6efd' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Añadir';
    }
}

async function removeAlias(aliasId, domain) {
    const confirm = await Swal.fire({
        icon: 'warning',
        title: 'Eliminar Alias',
        html: `¿Seguro que deseas eliminar el alias <strong>${domain}</strong>?<br><small class="text-muted">Se eliminará de Caddy y Cloudflare.</small>`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
    });

    if (!confirm.isConfirmed) return;

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/remove-alias', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: csrfToken, alias_id: aliasId })
        });
        const data = await response.json();

        if (data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Alias Eliminado',
                text: data.message || 'Alias eliminado correctamente.',
                confirmButtonColor: '#0d6efd',
                timer: 2000,
                timerProgressBar: true
            });
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo eliminar el alias.', confirmButtonColor: '#0d6efd' });
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#0d6efd' });
    }
}

// Enter key en el input de alias
document.getElementById('new-alias-domain')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); addAlias(); }
});

// ============================================
// Automatismos IA - Auto-Categorizar y Etiquetar
// ============================================
const automationsCollapse = document.getElementById('automationsCollapse');
const automationsChevron = document.getElementById('automationsChevron');
if (automationsCollapse && automationsChevron) {
    automationsCollapse.addEventListener('show.bs.collapse', () => automationsChevron.style.transform = 'rotate(180deg)');
    automationsCollapse.addEventListener('hide.bs.collapse', () => automationsChevron.style.transform = 'rotate(0deg)');
}

// Store last suggestions for selective apply
let lastAutoTagSuggestions = null;

async function runAutoTag(dryRun) {
    const btn = dryRun ? document.getElementById('btnPreviewAutoTag') : document.getElementById('btnApplyAutoTag');
    const resultsContainer = document.getElementById('autoTagResults');
    const scope = document.getElementById('autoTagScope').value;

    toggleBtnSpinner(btn, true);

    if (!dryRun) {
        const confirm = await Swal.fire({
            icon: 'question',
            title: 'Aplicar auto-tagging',
            text: 'Se analizaran los posts y se aplicaran directamente TODAS las categorias y tags sugeridos por la IA (sin previsualizar). ¿Continuar?',
            showCancelButton: true,
            confirmButtonText: 'Si, aplicar todo',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d'
        });
        if (!confirm.isConfirmed) {
            toggleBtnSpinner(btn, false);
            return;
        }
    }

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/auto-tag', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                _csrf: csrfToken,
                dry_run: dryRun,
                scope: scope
            })
        });
        const data = await response.json();

        resultsContainer.classList.remove('d-none');

        if (data.success) {
            if (dryRun && data.suggestions) {
                lastAutoTagSuggestions = data.suggestions;
                renderPreview(data, resultsContainer);
            } else if (!dryRun && data.applied) {
                renderAppliedResults(data, resultsContainer);
            }
        } else {
            let errHtml = `<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle"></i> ${data.message || 'Error desconocido.'}`;
            if (data.raw) {
                errHtml += `<br><details class="mt-2"><summary>Ver respuesta de la IA</summary><pre class="mt-1 p-2 bg-dark text-light small" style="max-height:300px;overflow:auto;white-space:pre-wrap;">${data.raw.replace(/</g,'&lt;')}</pre></details>`;
            }
            errHtml += '</div>';
            resultsContainer.innerHTML = errHtml;
        }
    } catch (error) {
        resultsContainer.classList.remove('d-none');
        resultsContainer.innerHTML = '<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle"></i> Error de conexion con el servidor.</div>';
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

function renderPreview(data, container) {
    let html = '<div class="alert alert-info small mb-2"><i class="bi bi-info-circle"></i> <strong>Previsualizacion</strong> — Desmarca lo que no quieras aplicar y pulsa "Aplicar Seleccionados".</div>';
    html += `<p class="text-muted small mb-2">Modelo: <code>${data.model || 'N/A'}</code> | Tokens: ${data.tokens_used || 0}</p>`;

    const suggestions = data.suggestions;
    let hasAny = false;

    if (!suggestions || suggestions.length === 0) {
        html += '<div class="alert alert-success small"><i class="bi bi-check-circle"></i> Todos los posts ya tienen una taxonomia completa.</div>';
        container.innerHTML = html;
        return;
    }

    html += '<div class="small">';
    suggestions.forEach((s, si) => {
        const cats = s.add_categories || [];
        const tags = s.add_tags || [];
        if (cats.length === 0 && tags.length === 0) return;
        hasAny = true;

        html += `<div class="border rounded p-2 mb-2 bg-white">`;
        html += `<strong class="d-block mb-1">Post #${s.post_id}: ${s.post_title || ''}</strong>`;

        if (cats.length > 0) {
            html += '<div class="mb-1"><small class="text-muted fw-semibold">Categorias:</small><br>';
            cats.forEach((c, ci) => {
                const id = `cat_${si}_${ci}`;
                html += `<div class="form-check form-check-inline">
                    <input class="form-check-input at-check" type="checkbox" id="${id}" checked
                           data-post="${s.post_id}" data-type="category" data-name="${c.name}" data-slug="${c.slug}" data-new="${c.is_new}">
                    <label class="form-check-label" for="${id}">
                        <span class="badge bg-primary">${c.name}</span>${c.is_new ? ' <small class="text-warning">(nueva)</small>' : ''}
                    </label>
                </div>`;
            });
            html += '</div>';
        }

        if (tags.length > 0) {
            html += '<div><small class="text-muted fw-semibold">Tags:</small><br>';
            tags.forEach((t, ti) => {
                const id = `tag_${si}_${ti}`;
                html += `<div class="form-check form-check-inline">
                    <input class="form-check-input at-check" type="checkbox" id="${id}" checked
                           data-post="${s.post_id}" data-type="tag" data-name="${t.name}" data-slug="${t.slug}" data-new="${t.is_new}">
                    <label class="form-check-label" for="${id}">
                        <span class="badge bg-secondary">${t.name}</span>${t.is_new ? ' <small class="text-warning">(nuevo)</small>' : ''}
                    </label>
                </div>`;
            });
            html += '</div>';
        }
        html += '</div>';
    });

    if (!hasAny) {
        html += '<div class="alert alert-success small"><i class="bi bi-check-circle"></i> La IA no encontro sugerencias adicionales.</div>';
    } else {
        html += '</div>';
        html += `<div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllChecks(true)"><i class="bi bi-check-all"></i> Marcar todos</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllChecks(false)"><i class="bi bi-x-lg"></i> Desmarcar todos</button>
            <button type="button" class="btn btn-sm btn-success" id="btnApplySelected" onclick="applySelectedSuggestions()">
                <span class="btn-text"><i class="bi bi-check-circle"></i> Aplicar Seleccionados</span>
                <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span> Aplicando...</span>
            </button>
        </div>`;
    }

    container.innerHTML = html;
}

function toggleAllChecks(state) {
    document.querySelectorAll('.at-check').forEach(cb => cb.checked = state);
}

async function applySelectedSuggestions() {
    const checks = document.querySelectorAll('.at-check:checked');
    if (checks.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Nada seleccionado', text: 'Marca al menos una sugerencia para aplicar.', confirmButtonColor: '#0d6efd' });
        return;
    }

    // Build filtered suggestions from checked items
    const postMap = {};
    checks.forEach(cb => {
        const postId = parseInt(cb.dataset.post);
        if (!postMap[postId]) postMap[postId] = { post_id: postId, add_categories: [], add_tags: [] };
        const item = { name: cb.dataset.name, slug: cb.dataset.slug, is_new: cb.dataset.new === 'true' };
        if (cb.dataset.type === 'category') postMap[postId].add_categories.push(item);
        else postMap[postId].add_tags.push(item);
    });

    const filtered = Object.values(postMap);

    const btn = document.getElementById('btnApplySelected');
    toggleBtnSpinner(btn, true);

    try {
        const response = await fetch('/musedock/domain-manager/{{ $tenant->id }}/auto-tag', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                _csrf: csrfToken,
                dry_run: false,
                suggestions: filtered
            })
        });
        const data = await response.json();
        const resultsContainer = document.getElementById('autoTagResults');

        if (data.success && data.applied) {
            renderAppliedResults(data, resultsContainer);
        } else {
            resultsContainer.innerHTML = `<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle"></i> ${data.message || 'Error al aplicar.'}</div>`;
        }
    } catch (error) {
        document.getElementById('autoTagResults').innerHTML = '<div class="alert alert-danger small"><i class="bi bi-exclamation-triangle"></i> Error de conexion.</div>';
    } finally {
        toggleBtnSpinner(btn, false);
    }
}

function renderAppliedResults(data, container) {
    const a = data.applied;
    let html = '<div class="alert alert-success small mb-2"><i class="bi bi-check-circle"></i> <strong>Cambios aplicados correctamente</strong></div>';
    html += '<div class="small">';
    html += `<p>Categorias creadas: <strong>${a.categories_created}</strong> | Tags creados: <strong>${a.tags_created}</strong></p>`;
    html += `<p>Vinculos de categorias: <strong>${a.category_links}</strong> | Vinculos de tags: <strong>${a.tag_links}</strong></p>`;
    html += `<p class="text-muted">Modelo: <code>${data.model || 'N/A'}</code> | Tokens: ${data.tokens_used || 0}</p>`;
    if (a.details && a.details.length > 0) {
        a.details.forEach(d => {
            html += `<div class="border-bottom pb-1 mb-1"><strong>Post #${d.post_id}: ${d.post_title || ''}</strong> — `;
            if (d.categories_added.length) html += `+cats: ${d.categories_added.join(', ')} `;
            if (d.tags_added.length) html += `+tags: ${d.tags_added.join(', ')}`;
            html += `</div>`;
        });
    }
    html += '</div>';
    container.innerHTML = html;
}

</script>
@endpush

@endsection
