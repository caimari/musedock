@extends('layouts::app')

@section('title', __instagram('settings.settings') . ' - Instagram Gallery')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-gear" style="font-size:1.35rem;color:#fff;"></i>
                </div>
                <div>
                    <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;"><?php echo __instagram('settings.settings'); ?></h3>
                    <p class="text-muted mb-0" style="font-size:0.85rem;"><?php echo __instagram('module.description'); ?></p>
                </div>
            </div>
            <a href="/{{ admin_path() }}/instagram" style="display:flex;align-items:center;gap:0.35rem;font-size:0.85rem;padding:0.4rem 0.75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef;color:#6c757d;text-decoration:none;">
                <i class="bi bi-arrow-left"></i>
                <span><?php echo __instagram('common.back'); ?></span>
            </a>
        </div>

        {{-- ESTADO DE CONEXIÓN --}}
        @php $__connections = $connections ?? []; @endphp
        @if(!empty($__connections))
            <div class="alert alert-success d-flex align-items-center justify-content-between mb-4" style="border-left:4px solid #198754;">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-check-circle-fill" style="font-size:1.5rem;"></i>
                    <div>
                        <strong>Conectado a Instagram</strong>
                        <div style="font-size:0.85rem;">
                            @foreach($__connections as $__c)
                                <span class="badge bg-light text-dark me-1">@{{ $__c->username }}</span>
                                @php
                                    $__exp = !empty($__c->token_expires_at) ? strtotime($__c->token_expires_at) : null;
                                    $__days = $__exp ? max(0, (int)floor(($__exp - time())/86400)) : null;
                                @endphp
                                @if($__days !== null)
                                    <small class="text-muted">— token caduca en {{ $__days }} días</small>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                <a href="/{{ admin_path() }}/instagram" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-diagram-3"></i> Gestionar conexiones
                </a>
            </div>
        @else
            <div class="alert alert-warning d-flex align-items-center justify-content-between mb-4" style="border-left:4px solid #ffc107;">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size:1.5rem;"></i>
                    <div>
                        <strong>Sin cuentas conectadas</strong>
                        <div style="font-size:0.85rem;">Tras guardar las credenciales, ve a "Gestionar conexiones" para autorizar tu cuenta de Instagram.</div>
                    </div>
                </div>
                <a href="/{{ admin_path() }}/instagram" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-plus-circle"></i> Conectar cuenta
                </a>
            </div>
        @endif

        <form method="POST" action="/{{ admin_path() }}/instagram/settings">
            {!! csrf_field() !!}
            <div class="row">
                <div class="col-lg-8">
                    <!-- Modo de funcionamiento -->
                    @php $currentMode = $settings['instagram_mode'] ?? 'both'; @endphp
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-toggles"></i> Modo de funcionamiento</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="instagram_mode" id="mode_both" value="both" {{ $currentMode === 'both' ? 'checked' : '' }}>
                                <label class="form-check-label" for="mode_both">
                                    <strong>Ambos (recomendado)</strong>
                                    <small class="d-block text-muted">Feed completo con Graph API + insertar posts individuales con oEmbed</small>
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="instagram_mode" id="mode_graph" value="graph" {{ $currentMode === 'graph' ? 'checked' : '' }}>
                                <label class="form-check-label" for="mode_graph">
                                    <strong>Solo Graph API</strong>
                                    <small class="d-block text-muted">Feed de Instagram con diseño personalizado. Requiere credenciales de API.</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="instagram_mode" id="mode_oembed" value="oembed" {{ $currentMode === 'oembed' ? 'checked' : '' }}>
                                <label class="form-check-label" for="mode_oembed">
                                    <strong>Solo oEmbed</strong>
                                    <small class="d-block text-muted">Insertar posts públicos de Instagram sin configurar API. Diseño nativo de Instagram.</small>
                                </label>
                            </div>

                            <div class="alert alert-light border mt-3 mb-0" style="font-size:0.85rem;">
                                <strong>Shortcodes disponibles:</strong>
                                <div class="mt-2">
                                    <code>[instagram connection=1 layout="grid" columns=3 limit=12]</code>
                                    <small class="text-muted ms-2">— Feed con Graph API</small>
                                </div>
                                <div class="mt-1">
                                    <code>[instagram-post url="https://instagram.com/p/ABC123"]</code>
                                    <small class="text-muted ms-2">— Post individual con oEmbed</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Credentials -->
                    @php
                        $hasCredentials = !empty($settings['instagram_app_id'] ?? '');
                        $defaultRedirectUri = url('/' . admin_path() . '/instagram/callback');
                        $redirectUriValue = !empty($settings['instagram_redirect_uri']) ? $settings['instagram_redirect_uri'] : $defaultRedirectUri;
                        $deauthUri = url('/' . admin_path() . '/instagram/deauthorize');
                        $deletionUri = url('/' . admin_path() . '/instagram/data-deletion');
                        $webhookVerifyToken = substr(hash('sha256', 'ig-webhook-' . ($tenantId ?? 0) . '-' . ($settings['instagram_app_id'] ?? 'na')), 0, 24);
                    @endphp
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-key"></i> <?php echo __instagram('settings.api_credentials'); ?></h5>
                            @if($hasCredentials)
                            <button type="button" class="btn btn-sm btn-outline-warning" id="btnUnlockApi">
                                <i class="bi bi-unlock me-1"></i> Desbloquear para editar
                            </button>
                            @endif
                        </div>
                        <div class="card-body">

                            {{-- INSTRUCCIONES PASO A PASO (minimalista) --}}
                            <style>
                                #igInstructions { border: 1px solid #e9ecef; border-radius: 6px; overflow: hidden; }
                                #igInstructions .accordion-item { border: 0; border-bottom: 1px solid #f1f3f5; background: transparent; }
                                #igInstructions .accordion-item:last-child { border-bottom: 0; }
                                #igInstructions .accordion-header { margin: 0; }
                                #igInstructions .accordion-button {
                                    padding: 8px 12px;
                                    font-size: 0.82rem;
                                    font-weight: 500;
                                    color: #495057;
                                    background: #fff;
                                    box-shadow: none !important;
                                    line-height: 1.3;
                                }
                                #igInstructions .accordion-button:not(.collapsed) {
                                    background: #f8f9fa;
                                    color: #0d6efd;
                                }
                                #igInstructions .accordion-button::after {
                                    width: 0.85rem;
                                    height: 0.85rem;
                                    background-size: 0.85rem;
                                }
                                #igInstructions .accordion-button strong { font-weight: 600; margin-right: 4px; }
                                #igInstructions .accordion-body { padding: 10px 14px 14px; font-size: 0.82rem; line-height: 1.45; background: #fcfcfd; }
                                #igInstructions .accordion-body ol,
                                #igInstructions .accordion-body ul { padding-left: 1.1rem; }
                                #igInstructions .accordion-body code { font-size: 0.78rem; padding: 1px 5px; }
                            </style>
                            <div class="alert alert-light border mb-3" style="font-size:0.82rem;">
                                <i class="bi bi-info-circle"></i>
                                Estas credenciales corresponden a <strong>una sola app de Meta</strong> y sirven para <strong>todas las cuentas de Instagram</strong> que vayas a conectar luego (puedes conectar varias en la pantalla de Conexiones). Para mostrar el feed sólo hacen falta los <strong>4 pasos</strong> de abajo. Los <strong>Webhooks</strong> y la <strong>Revisión de la aplicación</strong> son opcionales.
                            </div>
                            <div class="alert alert-warning mb-3" style="font-size:0.82rem;">
                                <i class="bi bi-shield-check"></i>
                                <strong>¿Ya tenías una cuenta conectada?</strong> Hemos añadido el permiso para publicar posts del blog en Instagram. Ve a <a href="/{{ admin_path() }}/instagram" class="alert-link">Conexiones</a> y pulsa el botón <strong>«Reautorizar»</strong> en tu cuenta para activar el nuevo permiso (mantiene la conexión, solo actualiza los permisos).
                            </div>

                            <div class="accordion mb-4" id="igInstructions">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#igStep1">
                                            <strong>Paso 1.</strong>&nbsp;Crear la app en Meta for Developers
                                        </button>
                                    </h2>
                                    <div id="igStep1" class="accordion-collapse collapse" data-bs-parent="#igInstructions">
                                        <div class="accordion-body">
                                            <ol class="mb-0">
                                                <li>Entra en <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers → Mis aplicaciones</a>.</li>
                                                <li>Pulsa <strong>«Crear aplicación»</strong>.</li>
                                                <li><strong>Tipo de aplicación:</strong> selecciona <code>Empresa</code>.</li>
                                                <li>Ponle un nombre (ej. <code>FreeNetPrivate-IG</code>).</li>
                                                <li>Deja <strong>Modo de la aplicación</strong> en <code>En desarrollo</code> (no hace falta pasar a producción para que funcione contigo y los testers).</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#igStep2">
                                            <strong>Paso 2.</strong>&nbsp;Activar «API con inicio de sesión para empresas de Instagram» y copiar credenciales
                                        </button>
                                    </h2>
                                    <div id="igStep2" class="accordion-collapse collapse" data-bs-parent="#igInstructions">
                                        <div class="accordion-body">
                                            <ol class="mb-2">
                                                <li>En el menú izquierdo abre <strong>«Configuración de la API con inicio de sesión para empresas de Instagram»</strong>.</li>
                                                <li>Copia los dos valores que te muestra Meta:
                                                    <ul>
                                                        <li><strong>Identificador de la aplicación de Instagram</strong> → pégalo abajo en <em>App ID</em>.</li>
                                                        <li><strong>Clave secreta de la aplicación de Instagram</strong> (pulsa «Mostrar») → pégala abajo en <em>App Secret</em>.</li>
                                                    </ul>
                                                </li>
                                                <li>En la sección <strong>«3. Configura el inicio de sesión como negocio de Instagram»</strong>, en <strong>«Valid OAuth Redirect URIs»</strong> pega la URL que te damos más abajo (la <em>Valid OAuth Redirect URI</em>).</li>
                                                <li>Guarda los cambios en Meta.</li>
                                            </ol>
                                            <div class="alert alert-warning mb-0" style="font-size:0.82rem;">
                                                Ojo: el <strong>«Identificador de la aplicación»</strong> general (el de la cabecera) NO es el mismo que el <strong>«Identificador de la aplicación de Instagram»</strong>. Usa siempre el de Instagram.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#igStep3">
                                            <strong>Paso 3.</strong>&nbsp;Añadir tu cuenta de Instagram como tester
                                        </button>
                                    </h2>
                                    <div id="igStep3" class="accordion-collapse collapse" data-bs-parent="#igInstructions">
                                        <div class="accordion-body">
                                            <ol class="mb-2">
                                                <li>Tu cuenta de Instagram tiene que ser <strong>Business</strong> o <strong>Creator</strong> (no personal) y estar vinculada a una Página de Facebook que administres.</li>
                                                <li>En Meta, pestaña <strong>«Roles»</strong> → <strong>«Probadores de Instagram»</strong>: añade tu cuenta de Instagram. Tendrás que aceptar la invitación desde la app de Instagram (Configuración → Sitios web y aplicaciones → Solicitudes de tester).</li>
                                                <li>Vuelve a <strong>«1. Generar identificadores de acceso»</strong> y pulsa <strong>«Añadir cuenta»</strong>: ahora ya te dejará seleccionar tu cuenta.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#igStep4">
                                            <strong>Paso 4.</strong>&nbsp;Pegar credenciales aquí, probar y conectar
                                        </button>
                                    </h2>
                                    <div id="igStep4" class="accordion-collapse collapse" data-bs-parent="#igInstructions">
                                        <div class="accordion-body">
                                            <ol class="mb-0">
                                                <li>Pega <em>App ID</em> y <em>App Secret</em> en los campos de abajo.</li>
                                                <li>Pulsa <strong>«Probar credenciales contra Meta»</strong> para verificar que son válidas.</li>
                                                <li>Pulsa <strong>«Guardar configuración»</strong>.</li>
                                                <li>Ve a <strong>Gestionar conexiones</strong> → <strong>«Conectar cuenta»</strong>. Se abrirá Instagram para autorizar y al volver verás tu feed.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#igStepOpt">
                                            <span class="text-muted">Opcional.</span>&nbsp;Webhooks, Deauthorize, Data Deletion y revisión de la app
                                        </button>
                                    </h2>
                                    <div id="igStepOpt" class="accordion-collapse collapse" data-bs-parent="#igInstructions">
                                        <div class="accordion-body">
                                            <p class="mb-2">Sólo necesitas estas cosas si:</p>
                                            <ul class="mb-2">
                                                <li><strong>Webhooks</strong>: quieres recibir notificaciones en tiempo real de comentarios/mensajes (no hace falta para mostrar el feed).</li>
                                                <li><strong>Deauthorize Callback URL / Data Deletion Request URL</strong>: las pide Meta sólo cuando vas a publicar la app fuera del modo desarrollo.</li>
                                                <li><strong>Revisión de la aplicación</strong>: necesaria si quieres que la app la pueda usar cualquier usuario público (no sólo los testers que añadas tú).</li>
                                            </ul>
                                            <p class="mb-0"><small class="text-muted">Para tu caso (mostrar el feed de tu propia cuenta en la web) no hace falta nada de esto. Más abajo tienes todas las URLs por si decides activarlo más adelante.</small></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- CAMPOS DE CREDENCIALES --}}
                            <div class="mb-3">
                                <label class="form-label">
                                    <strong>Identificador de la aplicación de Instagram</strong>
                                    <small class="text-muted d-block">Meta lo llama así. Es el del Paso 2, NO el «Identificador de la aplicación» general.</small>
                                </label>
                                <input type="text" class="form-control api-credential" name="instagram_app_id" id="igAppId"
                                       value="<?php echo htmlspecialchars($settings['instagram_app_id'] ?? ''); ?>"
                                       placeholder="Ej. 2105474446975015"
                                       {{ $hasCredentials ? 'disabled' : '' }}>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <strong>Clave secreta de la aplicación de Instagram</strong>
                                    <small class="text-muted d-block">En Meta pulsa «Mostrar» para verla y luego pégala aquí.</small>
                                </label>
                                <input type="password" class="form-control api-credential" name="instagram_app_secret" id="igAppSecret"
                                       value="<?php echo htmlspecialchars($settings['instagram_app_secret'] ?? ''); ?>"
                                       placeholder="Ej. b9189decefef2de45e373734314477bf"
                                       {{ $hasCredentials ? 'disabled' : '' }}>
                            </div>

                            {{-- BOTÓN DE TEST --}}
                            <div class="mb-4">
                                <button type="button" class="btn btn-outline-primary" id="btnTestCredentials">
                                    <i class="bi bi-check2-circle"></i> Probar credenciales contra Meta
                                </button>
                                <span id="testCredentialsResult" class="ms-2" style="font-size:0.9rem;"></span>
                            </div>

                            <hr>

                            {{-- URLS QUE EL USUARIO TIENE QUE PEGAR EN META --}}
                            <h6 class="mb-3"><i class="bi bi-link-45deg"></i> URLs para Meta</h6>

                            <div class="mb-3">
                                <label class="form-label">
                                    <strong>Valid OAuth Redirect URI</strong>
                                    <span class="badge bg-primary ms-1" style="font-size:0.65rem;">OBLIGATORIA</span>
                                    <small class="text-muted d-block">Pégala en el apartado «Valid OAuth Redirect URIs» del Paso 2.</small>
                                </label>
                                <div class="input-group">
                                    <input type="url" class="form-control api-credential" name="instagram_redirect_uri" id="redirectUriInput"
                                           value="{{ htmlspecialchars($redirectUriValue) }}"
                                           {{ $hasCredentials ? 'disabled' : '' }}>
                                    <button type="button" class="btn btn-outline-secondary copy-btn" data-target="redirectUriInput" title="Copiar">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>

                            <details class="mt-3">
                                <summary class="text-muted" style="cursor:pointer;font-size:0.85rem;">
                                    Mostrar URLs opcionales (Deauthorize, Data Deletion, Webhook)
                                </summary>
                                <div class="mt-3">
                                    <div class="mb-3">
                                        <label class="form-label" style="font-size:0.85rem;">
                                            <strong>Deauthorize Callback URL</strong>
                                            <small class="text-muted d-block">Sólo si vas a publicar la app fuera del modo desarrollo.</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="url" class="form-control" id="deauthUriInput" value="{{ htmlspecialchars($deauthUri) }}" readonly>
                                            <button type="button" class="btn btn-outline-secondary copy-btn" data-target="deauthUriInput" title="Copiar">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" style="font-size:0.85rem;">
                                            <strong>Data Deletion Request URL</strong>
                                            <small class="text-muted d-block">Sólo si vas a publicar la app fuera del modo desarrollo.</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="url" class="form-control" id="deletionUriInput" value="{{ htmlspecialchars($deletionUri) }}" readonly>
                                            <button type="button" class="btn btn-outline-secondary copy-btn" data-target="deletionUriInput" title="Copiar">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" style="font-size:0.85rem;">
                                            <strong>Webhook · URL de devolución de llamada</strong>
                                            <small class="text-muted d-block">Sólo si vas a configurar Webhooks (no hace falta para mostrar el feed).</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="url" class="form-control" id="webhookUriInput" value="{{ htmlspecialchars(url('/' . admin_path() . '/instagram/webhook')) }}" readonly>
                                            <button type="button" class="btn btn-outline-secondary copy-btn" data-target="webhookUriInput" title="Copiar">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label" style="font-size:0.85rem;">
                                            <strong>Webhook · Identificador de verificación</strong>
                                            <small class="text-muted d-block">Pégalo en el campo «Identificador de verificación» de Meta.</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="webhookTokenInput" value="{{ $webhookVerifyToken }}" readonly>
                                            <button type="button" class="btn btn-outline-secondary copy-btn" data-target="webhookTokenInput" title="Copiar">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>

                    <!-- Display Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-palette"></i> <?php echo __instagram('settings.display_settings'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.default_layout'); ?></label>
                                    <select class="form-select" name="default_layout">
                                        <?php foreach ($layouts as $key => $layout): ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo ($settings['default_layout'] ?? 'grid') === $key ? 'selected' : ''; ?>>
                                                <?php echo $layout['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.default_columns'); ?></label>
                                    <input type="number" class="form-control" name="default_columns" min="1" max="6"
                                           value="<?php echo $settings['default_columns'] ?? 3; ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.default_gap'); ?></label>
                                    <input type="number" class="form-control" name="default_gap" min="0" max="50"
                                           value="<?php echo $settings['default_gap'] ?? 10; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.max_posts_per_gallery'); ?></label>
                                    <input type="number" class="form-control" name="max_posts_per_gallery" min="1" max="100"
                                           value="<?php echo $settings['max_posts_per_gallery'] ?? 50; ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.caption_max_length'); ?></label>
                                    <input type="number" class="form-control" name="caption_max_length" min="50" max="500"
                                           value="<?php echo $settings['caption_max_length'] ?? 150; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="show_captions"
                                               <?php echo ($settings['show_captions'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo __instagram('settings.show_captions'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_lightbox"
                                               <?php echo ($settings['enable_lightbox'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo __instagram('settings.enable_lightbox'); ?></label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_lazy_loading"
                                               <?php echo ($settings['enable_lazy_loading'] ?? true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo __instagram('settings.enable_lazy_loading'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cache Settings -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-hdd"></i> <?php echo __instagram('settings.cache_settings'); ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.cache_duration_hours'); ?></label>
                                    <input type="number" class="form-control" name="cache_duration_hours" min="1" max="168"
                                           value="<?php echo $settings['cache_duration_hours'] ?? 6; ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo __instagram('settings.token_refresh_threshold_days'); ?></label>
                                    <input type="number" class="form-control" name="token_refresh_threshold_days" min="1" max="30"
                                           value="<?php echo $settings['token_refresh_threshold_days'] ?? 7; ?>">
                                </div>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_refresh_tokens"
                                       <?php echo ($settings['auto_refresh_tokens'] ?? true) ? 'checked' : ''; ?>>
                                <label class="form-check-label"><?php echo __instagram('settings.auto_refresh_tokens'); ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle"></i> <?php echo __instagram('common.save'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: '<?php echo __instagram('common.success'); ?>',
            text: '<?php echo addslashes($_SESSION['success']); ?>',
            confirmButtonColor: '#198754',
            timer: 3000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: '<?php echo __instagram('common.error'); ?>',
            html: '<?php echo addslashes($_SESSION['error']); ?>',
            confirmButtonColor: '#dc3545'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    // Copiar URLs (genérico para cualquier botón con .copy-btn data-target="...")
    document.querySelectorAll('.copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            const original = this.innerHTML;
            navigator.clipboard.writeText(input.value).then(() => {
                this.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                setTimeout(() => { this.innerHTML = original; }, 2000);
            });
        });
    });

    // Desbloquear campos de API
    const unlockBtn = document.getElementById('btnUnlockApi');
    if (unlockBtn) {
        unlockBtn.addEventListener('click', function() {
            document.querySelectorAll('.api-credential').forEach(input => {
                input.disabled = false;
            });
            this.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Desbloqueado';
            this.classList.remove('btn-outline-warning');
            this.classList.add('btn-outline-success');
            this.disabled = true;
        });
    }

    // Probar credenciales contra Meta
    const testBtn = document.getElementById('btnTestCredentials');
    if (testBtn) {
        testBtn.addEventListener('click', async function() {
            const result = document.getElementById('testCredentialsResult');
            const appIdEl = document.getElementById('igAppId');
            const appSecretEl = document.getElementById('igAppSecret');
            const appId = appIdEl ? appIdEl.value.trim() : '';
            const appSecret = appSecretEl ? appSecretEl.value.trim() : '';

            if (!appId || !appSecret) {
                result.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Rellena App ID y App Secret antes de probar.</span>';
                return;
            }

            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Probando...';
            result.innerHTML = '';

            try {
                const fd = new FormData();
                fd.append('instagram_app_id', appId);
                fd.append('instagram_app_secret', appSecret);
                const redirectEl = document.getElementById('redirectUriInput');
                if (redirectEl) fd.append('instagram_redirect_uri', redirectEl.value.trim());
                const csrf = document.querySelector('input[name="_csrf"], input[name="_token"], input[name="csrf_token"]');
                if (csrf) fd.append(csrf.name, csrf.value);

                const headers = { 'X-Requested-With': 'XMLHttpRequest' };
                if (csrf) headers['X-CSRF-Token'] = csrf.value;

                const res = await fetch('/{{ admin_path() }}/instagram/settings/test-credentials', {
                    method: 'POST',
                    body: fd,
                    headers: headers,
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (data.ok) {
                    result.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> ' + data.message + '</span>';
                } else {
                    result.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> ' + data.message + '</span>';
                }
            } catch (e) {
                result.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> No se pudo contactar con el servidor.</span>';
            } finally {
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    }
</script>
@endpush
