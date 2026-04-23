@extends('layouts::app')

@section('title', 'Instagram Gallery')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .ig-card { border: 1px solid #e9ecef; border-radius: 10px; background: #fff; overflow: hidden; margin-bottom: 1.25rem; }
    .ig-card-head { display: flex; align-items: center; gap: 14px; padding: 14px 18px; border-bottom: 1px solid #f1f3f5; }
    .ig-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); display: flex; align-items: center; justify-content: center; color: #fff; }
    .ig-status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; vertical-align: middle; margin-right: 4px; }
    .ig-status-active { background: #198754; }
    .ig-status-inactive { background: #ffc107; }
    .ig-status-expired { background: #dc3545; }
    .ig-card-body { padding: 18px; }
    .ig-accordion .accordion-button { padding: 10px 14px; font-size: 0.85rem; background: #fafbfc; box-shadow: none !important; }
    .ig-accordion .accordion-button:not(.collapsed) { background: #eef4ff; color: #0d6efd; }
    .ig-accordion .accordion-body { padding: 14px; font-size: 0.83rem; background: #fff; }
    .ig-field-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start; }
    .ig-field-row label { min-width: 200px; font-size: 0.8rem; padding-top: 6px; color: #495057; }
    .ig-btns { display: flex; flex-wrap: wrap; gap: 6px; }
    .ig-btns .btn { font-size: 0.78rem; padding: 4px 10px; }
    .ig-help-steps .accordion-button { font-size: 0.82rem; padding: 8px 12px; background: #fff; }
    .ig-help-steps code { font-size: 0.78rem; }
</style>
@endpush

@section('content')
<div class="app-content">
    <div class="container-fluid">

        {{-- CABECERA --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="ig-avatar" style="width:48px;height:48px;border-radius:12px;">
                    <i class="bi bi-megaphone" style="font-size:1.35rem;"></i>
                </div>
                <div>
                    <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">
                        Social Publisher
                        <i class="bi bi-instagram" style="color:#dc2743;font-size:1rem;"></i>
                        <i class="bi bi-facebook" style="color:#1877f2;font-size:1rem;"></i>
                    </h3>
                    <p class="text-muted mb-0" style="font-size:0.85rem;">Conecta cuentas de Instagram (y opcionalmente la Página de Facebook vinculada) para mostrar el feed en la web y publicar posts del blog en ambas redes.</p>
                </div>
            </div>
            <button type="button" class="btn btn-primary" id="btnAddAccount" style="background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);border:none;">
                <i class="bi bi-plus-circle"></i> Añadir cuenta
            </button>
        </div>

        {{-- FLASH MESSAGES --}}
        @if(!empty($_SESSION['success']))
            <div class="alert alert-success">{{ $_SESSION['success'] }}</div>
            @php unset($_SESSION['success']); @endphp
        @endif
        @if(!empty($_SESSION['error']))
            <div class="alert alert-danger">{{ $_SESSION['error'] }}</div>
            @php unset($_SESSION['error']); @endphp
        @endif

        {{-- CUENTAS CONECTADAS --}}
        @php $__connections = array_values(array_filter($connections ?? [], fn($c) => $c->instagram_user_id && !str_starts_with($c->instagram_user_id, 'draft-'))); @endphp
        @php $__drafts = array_values(array_filter($connections ?? [], fn($c) => str_starts_with($c->instagram_user_id, 'draft-'))); @endphp

        @if(empty($__connections) && empty($__drafts))
            <div class="ig-card text-center" style="padding: 3rem 1rem;">
                <div style="font-size:3rem;line-height:1;">
                    <i class="bi bi-instagram" style="color:#dc2743;"></i>
                    <span style="margin:0 6px;color:#ced4da;">+</span>
                    <i class="bi bi-facebook" style="color:#1877f2;"></i>
                </div>
                <h4 class="mt-3">No hay cuentas conectadas</h4>
                <p class="text-muted" style="max-width:560px;margin:0 auto;">
                    Pulsa «Añadir cuenta» para empezar. Primero conectas la cuenta de <strong>Instagram</strong> (2 pasos: pegar credenciales de la app de Meta → autorizar en Instagram). Una vez conectada, aparecerá un botón <i class="bi bi-facebook" style="color:#1877f2;"></i> <strong>Vincular FB</strong> en la tarjeta de la cuenta para enlazar también la Página de Facebook vinculada (opcional).
                </p>
            </div>
        @endif

        @foreach($__connections as $c)
            @php
                $__defaultRedirect = url('/' . $basePath . '/instagram/callback');
                $__expTs = !empty($c->token_expires_at) ? strtotime($c->token_expires_at) : 0;
                $__expired = $__expTs > 0 && $__expTs < time();
                $__expDays = $__expTs > 0 ? max(0, (int)floor(($__expTs - time()) / 86400)) : null;
                $__statusClass = $__expired ? 'ig-status-expired' : ($c->is_active ? 'ig-status-active' : 'ig-status-inactive');
                $__statusText = $__expired ? 'Token caducado' : ($c->is_active ? 'Activa' : 'Inactiva');
            @endphp
            @php
                $__fbLinked = !empty($c->facebook_page_id) && !empty($c->facebook_page_token);
                $__fbEnabled = $__fbLinked && !empty($c->facebook_enabled);
            @endphp
            <div class="ig-card" data-connection-id="{{ $c->id }}">
                <div class="ig-card-head">
                    <div class="ig-avatar"><i class="bi bi-instagram"></i></div>
                    <div class="flex-grow-1">
                        <div style="font-weight:700;">{{ '@' . $c->username }}</div>
                        <div style="font-size:0.78rem;color:#6c757d;">
                            <span class="ig-status-dot {{ $__statusClass }}"></span>{{ $__statusText }}
                            @if($__expDays !== null)
                                · token: {{ $__expDays }} días
                            @endif
                            @if($__fbEnabled)
                                · <i class="bi bi-facebook" style="color:#1877f2;"></i> <strong>{{ $c->facebook_page_name ?: 'Página FB' }}</strong>
                            @elseif($__fbLinked)
                                · <i class="bi bi-facebook" style="color:#6c757d;"></i> <em>Página FB vinculada (desactivada)</em>
                            @else
                                · <i class="bi bi-facebook" style="color:#ced4da;"></i> <em>Sin Facebook</em>
                            @endif
                        </div>
                    </div>
                    <div class="ig-btns">
                        <button type="button" class="btn btn-outline-primary" onclick="syncConnection({{ $c->id }})"><i class="bi bi-arrow-repeat"></i> Sincronizar</button>
                        <a href="/{{ $basePath }}/social-publisher/{{ $c->id }}/posts" class="btn btn-outline-secondary"><i class="bi bi-grid-3x3"></i> Posts</a>
                        <a href="/{{ $basePath }}/social-publisher/{{ $c->id }}/gallery" class="btn btn-outline-success"><i class="bi bi-code-square"></i> Shortcode</a>
                        <a href="/{{ $basePath }}/social-publisher/connect?connection_id={{ $c->id }}" class="btn btn-outline-warning" title="Reautorizar Instagram"><i class="bi bi-shield-check"></i> Reautorizar IG</a>
                        @if(!$__fbLinked)
                            <a href="/{{ $basePath }}/social-publisher/facebook/connect?connection_id={{ $c->id }}" class="btn btn-outline-primary" style="background:#1877f2;color:#fff;border-color:#1877f2;" title="Vincular Página de Facebook"><i class="bi bi-facebook"></i> Vincular FB</a>
                        @else
                            <button type="button" class="btn btn-outline-primary btn-disconnect-fb" data-connection-id="{{ $c->id }}" data-page-name="{{ htmlspecialchars($c->facebook_page_name ?? '') }}" style="background:#1877f2;color:#fff;border-color:#1877f2;" title="Desvincular Página de Facebook"><i class="bi bi-facebook"></i> Desvincular FB</button>
                        @endif
                        <button type="button" class="btn btn-outline-dark btn-edit-credentials" data-connection-id="{{ $c->id }}" title="Editar credenciales de la app"><i class="bi bi-key"></i> Credenciales</button>
                        <button type="button" class="btn btn-outline-info btn-edit-hashtags" data-connection-id="{{ $c->id }}" title="Hashtags predefinidos"><i class="bi bi-hash"></i> Hashtags</button>
                        <button type="button" class="btn btn-outline-danger" onclick="confirmDisconnect({{ $c->id }}, '{{ addslashes($c->username) }}')"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="ig-card-body" style="display:none;">
                    {{-- Datos de credenciales para recuperar en el modal de edición --}}
                    @php
                        $__credsData = [
                            'id' => $c->id,
                            'app_id' => $c->app_id ?? '',
                            'app_secret' => $c->app_secret ?? '',
                            'redirect_uri' => $c->redirect_uri ?: $__defaultRedirect,
                            'hashtags_preset' => $c->hashtags_preset ?? '',
                        ];
                    @endphp
                    <script type="application/json" class="ig-creds-data">{!! json_encode($__credsData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                </div>
            </div>
        @endforeach

        {{-- BORRADORES PENDIENTES DE OAUTH --}}
        @foreach($__drafts as $c)
            @php $__defaultRedirect = url('/' . $basePath . '/instagram/callback'); @endphp
            <div class="ig-card" data-connection-id="{{ $c->id }}" style="border-color:#ffc107;">
                <div class="ig-card-head" style="background:#fff9e6;">
                    <div class="ig-avatar" style="background:#ffc107;"><i class="bi bi-hourglass-split"></i></div>
                    <div class="flex-grow-1">
                        <div style="font-weight:700;">Cuenta pendiente de autorización</div>
                        <div style="font-size:0.78rem;color:#856404;">Ya has guardado credenciales. Falta completar el OAuth en Instagram.</div>
                    </div>
                    <div class="ig-btns">
                        <a href="/{{ $basePath }}/social-publisher/connect?connection_id={{ $c->id }}" class="btn btn-primary" style="background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);border:none;">
                            <i class="bi bi-instagram"></i> Conectar con Instagram
                        </a>
                        <button type="button" class="btn btn-outline-dark btn-edit-credentials" data-connection-id="{{ $c->id }}"><i class="bi bi-key"></i> Editar credenciales</button>
                        <button type="button" class="btn btn-outline-danger" onclick="confirmDisconnect({{ $c->id }}, 'borrador')"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div style="display:none;">
                    @php
                        $__credsData = [
                            'id' => $c->id,
                            'app_id' => $c->app_id ?? '',
                            'app_secret' => $c->app_secret ?? '',
                            'redirect_uri' => $c->redirect_uri ?: $__defaultRedirect,
                            'hashtags_preset' => $c->hashtags_preset ?? '',
                        ];
                    @endphp
                    <script type="application/json" class="ig-creds-data">{!! json_encode($__credsData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                </div>
            </div>
        @endforeach

        {{-- INSTRUCCIONES --}}
        <div class="ig-card mt-4">
            <div class="ig-card-head"><i class="bi bi-info-circle me-2"></i><strong>¿Cómo obtener las credenciales en Meta for Developers?</strong></div>
            <div class="ig-card-body">
                <div class="accordion ig-help-steps" id="igHelp">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#hlp1">
                                <strong>Paso 1.</strong>&nbsp;Crear la app en Meta for Developers
                            </button>
                        </h2>
                        <div id="hlp1" class="accordion-collapse collapse" data-bs-parent="#igHelp">
                            <div class="accordion-body">
                                <ol class="mb-0">
                                    <li>Entra en <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers → Mis aplicaciones</a>.</li>
                                    <li>Pulsa <strong>«Crear aplicación»</strong>.</li>
                                    <li><strong>Tipo de aplicación:</strong> <code>Empresa</code>.</li>
                                    <li>Ponle nombre. Deja <strong>Modo de la aplicación</strong> en <code>En desarrollo</code>.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#hlp2">
                                <strong>Paso 2.</strong>&nbsp;Activar API de Instagram y copiar credenciales
                            </button>
                        </h2>
                        <div id="hlp2" class="accordion-collapse collapse" data-bs-parent="#igHelp">
                            <div class="accordion-body">
                                <ol class="mb-0">
                                    <li>Menú izquierdo → <strong>«Configuración de la API con inicio de sesión para empresas de Instagram»</strong>.</li>
                                    <li>Copia <strong>«Identificador de la aplicación de Instagram»</strong> y <strong>«Clave secreta de la aplicación de Instagram»</strong>.</li>
                                    <li>Sección <strong>«3. Configura el inicio de sesión como negocio de Instagram»</strong>: en <strong>«Valid OAuth Redirect URIs»</strong> pega la URL que te muestra este panel.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#hlp3">
                                <strong>Paso 3.</strong>&nbsp;Añadir tu cuenta como evaluador de Instagram
                            </button>
                        </h2>
                        <div id="hlp3" class="accordion-collapse collapse" data-bs-parent="#igHelp">
                            <div class="accordion-body">
                                <ol class="mb-0">
                                    <li>Tu cuenta de IG debe ser <strong>Business</strong> o <strong>Creator</strong> (no personal).</li>
                                    <li>Meta → <strong>Roles → Más → Evaluadores de Instagram → Añadir personas</strong>. Pon el <strong>username de Instagram</strong>.</li>
                                    <li>En <a href="https://www.instagram.com/accounts/manage_access/" target="_blank" rel="noopener">instagram.com/accounts/manage_access</a> (sólo web, no hay en la app móvil) → <strong>Invitaciones de tester</strong> → Aceptar.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#hlp4">
                                <strong>Paso 4.</strong>&nbsp;Pegar aquí y conectar
                            </button>
                        </h2>
                        <div id="hlp4" class="accordion-collapse collapse" data-bs-parent="#igHelp">
                            <div class="accordion-body">
                                Pulsa <strong>«Añadir cuenta»</strong> arriba, pega las credenciales, prueba, guarda y pulsa <strong>«Conectar con Instagram»</strong>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
    const adminPath = @json($basePath);
    const defaultRedirect = `${window.location.origin}/${adminPath}/instagram/callback`;

    function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }

    /**
     * Modal SweetAlert2 de credenciales. Si isNew=true crea nueva cuenta y
     * al guardar redirige al OAuth. Si isNew=false edita una existente y
     * al guardar solo cierra y refresca.
     */
    function openCredentialsModal({ isNew, connectionId, appId, appSecret, redirectUri }) {
        const title = isNew ? '<i class="bi bi-plus-circle"></i> Añadir cuenta de Instagram'
                             : '<i class="bi bi-key"></i> Editar credenciales';
        const subtitle = isNew
            ? 'Pega las credenciales de la app de Meta. Al guardar, se redirigirá a Instagram para autorizar la cuenta.'
            : 'Cambia las credenciales de la app de Meta que usa esta cuenta. Después tendrás que pulsar «Reautorizar» para refrescar el token.';

        const html = `
            <div style="text-align:left;font-size:0.9rem;">
                <p style="color:#6c757d;">${subtitle}</p>
                <div class="mb-3">
                    <label class="form-label"><strong>Identificador de la aplicación de Instagram</strong></label>
                    <input type="text" class="form-control" id="swal-app-id" placeholder="Ej. 2105474446975015" value="${escapeAttr(appId || '')}">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Clave secreta de la aplicación de Instagram</strong></label>
                    <input type="password" class="form-control" id="swal-app-secret" placeholder="Pulsa «Mostrar» en Meta y pega aquí" value="${escapeAttr(appSecret || '')}">
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Valid OAuth Redirect URI</strong></label>
                    <div style="display:flex;gap:6px;">
                        <input type="url" class="form-control" id="swal-redirect" value="${escapeAttr(redirectUri || defaultRedirect)}" style="flex:1;">
                        <button type="button" class="btn btn-outline-secondary" id="swal-copy-redirect" title="Copiar"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <small class="text-muted">Pega exactamente esta URL en Meta → «Valid OAuth Redirect URIs».</small>
                </div>
                <div id="swal-message" style="min-height:24px;font-size:0.85rem;"></div>
            </div>
        `;

        Swal.fire({
            title: title,
            html: html,
            width: 640,
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: isNew ? '<i class="bi bi-save"></i> Guardar y conectar' : '<i class="bi bi-save"></i> Guardar',
            denyButtonText: '<i class="bi bi-check2-circle"></i> Probar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2743',
            denyButtonColor: '#6c757d',
            reverseButtons: true,
            focusConfirm: false,
            didOpen: () => {
                // Copiar URL
                document.getElementById('swal-copy-redirect').addEventListener('click', function() {
                    const input = document.getElementById('swal-redirect');
                    const original = this.innerHTML;
                    navigator.clipboard.writeText(input.value).then(() => {
                        this.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                        setTimeout(() => this.innerHTML = original, 1500);
                    });
                });
            },
            preConfirm: async () => {
                const appId = document.getElementById('swal-app-id').value.trim();
                const appSecret = document.getElementById('swal-app-secret').value.trim();
                const redirect = document.getElementById('swal-redirect').value.trim();
                if (!appId || !appSecret) {
                    Swal.showValidationMessage('Rellena App ID y App Secret.');
                    return false;
                }
                // Guardar
                const fd = new FormData();
                fd.append('app_id', appId);
                fd.append('app_secret', appSecret);
                fd.append('redirect_uri', redirect);
                fd.append('_csrf', csrf());
                if (!isNew) fd.append('connection_id', connectionId);

                try {
                    const res = await fetch(`/${adminPath}/social-publisher/credentials`, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
                        body: fd
                    });
                    const data = await res.json();
                    if (!data.ok) {
                        Swal.showValidationMessage(data.message || 'Error al guardar');
                        return false;
                    }
                    return data;
                } catch (err) {
                    Swal.showValidationMessage('Error de red: ' + err.message);
                    return false;
                }
            }
        }).then(result => {
            if (result.isConfirmed && result.value) {
                // Si es nueva, redirigir a OAuth. Si es edición, solo refrescar.
                if (isNew && result.value.connection_id) {
                    Swal.fire({
                        title: 'Redirigiendo a Instagram…',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading(),
                    });
                    window.location.href = `/${adminPath}/social-publisher/connect?connection_id=${result.value.connection_id}`;
                } else {
                    Swal.fire({ icon: 'success', title: 'Credenciales actualizadas', timer: 1500, showConfirmButton: false })
                        .then(() => window.location.reload());
                }
            } else if (result.isDenied) {
                // Probar — reabrimos el modal con el resultado en su sitio
                testCredentials(connectionId, isNew);
            }
        });
    }

    /**
     * Test standalone: se dispara desde el botón «Probar» del modal (isDenied
     * de SweetAlert). Lee los valores actuales, pregunta al backend y reabre
     * el modal con el resultado dentro de swal-message.
     */
    async function testCredentials(connectionId, isNew) {
        // Recuperar los valores que había en el modal antes de cerrar
        const appId = document.getElementById('swal-app-id')?.value?.trim() || '';
        const appSecret = document.getElementById('swal-app-secret')?.value?.trim() || '';
        const redirect = document.getElementById('swal-redirect')?.value?.trim() || '';

        Swal.fire({ title: 'Probando credenciales contra Instagram…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const fd = new FormData();
        fd.append('app_id', appId);
        fd.append('app_secret', appSecret);
        fd.append('redirect_uri', redirect);
        fd.append('_csrf', csrf());
        if (!isNew) fd.append('connection_id', connectionId);

        try {
            const res = await fetch(`/${adminPath}/social-publisher/credentials/test`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
                body: fd
            });
            const data = await res.json();
            await Swal.fire({
                icon: data.ok ? 'success' : 'error',
                title: data.ok ? 'Credenciales válidas' : 'Credenciales inválidas',
                text: data.message,
                confirmButtonText: 'Volver al formulario',
            });
        } catch (err) {
            await Swal.fire({ icon: 'error', title: 'Error de red', text: err.message });
        }
        // Reabrir el modal con los mismos valores
        openCredentialsModal({ isNew, connectionId, appId, appSecret, redirectUri: redirect });
    }

    function escapeAttr(s) {
        return String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // --- Botón principal: Añadir cuenta ---
    document.getElementById('btnAddAccount').addEventListener('click', () => {
        openCredentialsModal({ isNew: true, connectionId: null, appId: '', appSecret: '', redirectUri: defaultRedirect });
    });

    // --- Botón editar credenciales por cuenta ---
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-credentials');
        if (!btn) return;
        const id = btn.dataset.connectionId;
        const card = btn.closest('.ig-card');
        const dataEl = card.querySelector('.ig-creds-data');
        const data = dataEl ? JSON.parse(dataEl.textContent) : {};
        openCredentialsModal({
            isNew: false,
            connectionId: id,
            appId: data.app_id,
            appSecret: data.app_secret,
            redirectUri: data.redirect_uri,
        });
    });

    // --- Botón editar hashtags predefinidos por cuenta ---
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-hashtags');
        if (!btn) return;
        const id = btn.dataset.connectionId;
        const card = btn.closest('.ig-card');
        const dataEl = card.querySelector('.ig-creds-data');
        const data = dataEl ? JSON.parse(dataEl.textContent) : {};
        openHashtagsModal(id, data.hashtags_preset || '');
    });

    function openHashtagsModal(connectionId, current) {
        const html = `
            <div style="text-align:left;font-size:0.9rem;">
                <p style="color:#6c757d;margin-bottom:12px;">
                    Hashtags fijos que se añaden al caption al publicar cualquier post del blog en esta cuenta.
                    Tienen <strong>prioridad</strong> sobre los dinámicos (categorías/tags del post): si el total supera 30,
                    se recortan primero los dinámicos.
                </p>
                <label class="form-label" style="font-size:0.82rem;"><strong>Tus hashtags de marca</strong></label>
                <textarea class="form-control" id="swal-hashtags" rows="5"
                    placeholder="#freenetes #tecnologia #madrid"
                    style="font-family:monospace;font-size:0.85rem;">${escapeAttr(current)}</textarea>
                <small class="text-muted" style="display:block;margin-top:6px;font-size:0.78rem;">
                    Separa con espacios, comas o saltos de línea. El <code>#</code> inicial es opcional. Se normalizan: sin tildes, sin ñ, sin espacios, minúsculas. Se descartan los que empiezan por número (los rechaza Instagram).
                </small>
                <div id="swal-hashtags-preview" style="margin-top:10px;padding:10px;background:#f8f9fa;border-radius:6px;font-family:monospace;font-size:0.82rem;color:#0d6efd;word-break:break-word;min-height:38px;"></div>
            </div>
        `;

        Swal.fire({
            title: '<i class="bi bi-hash"></i> Hashtags predefinidos',
            html: html,
            width: 600,
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-save"></i> Guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd',
            didOpen: () => {
                const ta = document.getElementById('swal-hashtags');
                const preview = document.getElementById('swal-hashtags-preview');
                const renderPreview = () => {
                    const norm = normalizeHashtagsLocal(ta.value);
                    preview.textContent = norm.length
                        ? '#' + norm.join(' #') + '  (' + norm.length + ')'
                        : '(vacío)';
                };
                ta.addEventListener('input', renderPreview);
                renderPreview();
            },
            preConfirm: async () => {
                const raw = document.getElementById('swal-hashtags').value;
                const fd = new FormData();
                fd.append('connection_id', connectionId);
                fd.append('hashtags', raw);
                fd.append('_csrf', csrf());
                try {
                    const res = await fetch(`/${adminPath}/social-publisher/hashtags`, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
                        body: fd
                    });
                    const data = await res.json();
                    if (!data.ok) { Swal.showValidationMessage(data.message || 'Error'); return false; }
                    return data;
                } catch (err) {
                    Swal.showValidationMessage('Error de red: ' + err.message);
                    return false;
                }
            }
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({ icon: 'success', title: 'Hashtags guardados', timer: 1200, showConfirmButton: false })
                    .then(() => window.location.reload());
            }
        });
    }

    // Normaliza en cliente para previsualizar (misma lógica que el backend).
    function normalizeHashtagsLocal(raw) {
        const tokens = String(raw).split(/[\s,]+/);
        const seen = {};
        const out = [];
        for (let tok of tokens) {
            tok = tok.replace(/^#+/, '');
            if (!tok) continue;
            // Transliterar (quita tildes, ñ → n, etc.)
            let s = tok.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            s = s.replace(/[^a-z0-9_]+/g, '');
            if (!s || /^[0-9]/.test(s)) continue;
            if (seen[s]) continue;
            seen[s] = true;
            out.push(s);
            if (out.length >= 30) break;
        }
        return out;
    }

    // --- Sincronizar ---
    window.syncConnection = function(id) {
        Swal.fire({ title: 'Sincronizando…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('_csrf', csrf());
        fetch(`/${adminPath}/social-publisher/${id}/sync`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Sincronizado', text: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => window.location.reload());
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            }
        });
    };

    // --- Desvincular Facebook Page ---
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-disconnect-fb');
        if (!btn) return;
        const id = btn.dataset.connectionId;
        const pageName = btn.dataset.pageName || 'la Página';
        Swal.fire({
            title: 'Desvincular Facebook',
            html: `<p>Se va a desvincular la Página <strong>${pageName}</strong> de esta cuenta.</p><p>La conexión de Instagram y los posts se mantienen. Podrás volver a vincular otra página (o la misma) cuando quieras.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, desvincular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#1877f2',
        }).then(result => {
            if (!result.isConfirmed) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/${adminPath}/social-publisher/${id}/facebook/disconnect`;
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden'; csrfInput.name = '_csrf'; csrfInput.value = csrf();
            form.appendChild(csrfInput);
            document.body.appendChild(form); form.submit();
        });
    });

    // --- Picker de página de Facebook (si OAuth devolvió varias) ---
    // Detectamos `?fb_pick=N` en la URL y leemos las páginas candidatas de una etiqueta HTML inyectada.
    @if(!empty($_SESSION['fb_pending']) && !empty($_GET['fb_pick']))
    (function(){
        const pages = @json($_SESSION['fb_pending']['pages'] ?? []);
        const connectionId = {{ (int)($_SESSION['fb_pending']['connection_id'] ?? 0) }};
        if (!pages.length || !connectionId) return;

        const optionsHtml = pages.map(p => `
            <label style="display:block;padding:10px;border:1px solid #e9ecef;border-radius:6px;margin-bottom:6px;cursor:pointer;">
                <input type="radio" name="fb-page" value="${p.id}" style="margin-right:8px;">
                <strong>${p.name}</strong>
                ${p.category ? ` <small class="text-muted">(${p.category})</small>` : ''}
            </label>
        `).join('');

        Swal.fire({
            title: '<i class="bi bi-facebook" style="color:#1877f2;"></i> Elegir Página de Facebook',
            html: `
                <div style="text-align:left;font-size:0.9rem;">
                    <p class="text-muted">Facebook te ha autorizado varias páginas. Elige cuál quieres vincular a esta cuenta de Instagram:</p>
                    ${optionsHtml}
                </div>
            `,
            width: 600,
            showCancelButton: true,
            confirmButtonText: 'Vincular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#1877f2',
            preConfirm: async () => {
                const picked = document.querySelector('input[name="fb-page"]:checked')?.value;
                if (!picked) { Swal.showValidationMessage('Selecciona una página'); return false; }
                const fd = new FormData();
                fd.append('connection_id', connectionId);
                fd.append('page_id', picked);
                fd.append('_csrf', csrf());
                const res = await fetch(`/${adminPath}/social-publisher/facebook/select-page`, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf() },
                    body: fd
                });
                const data = await res.json();
                if (!data.ok) { Swal.showValidationMessage(data.message || 'Error'); return false; }
                return data;
            }
        }).then(r => {
            if (r.isConfirmed) {
                Swal.fire({ icon: 'success', title: 'Página vinculada', timer: 1500, showConfirmButton: false })
                    .then(() => window.location.href = `/${adminPath}/social-publisher`);
            } else {
                // Si cancela, limpiamos la sesión redirigiendo
                window.location.href = `/${adminPath}/social-publisher`;
            }
        });
    })();
    @endif

    // --- Desconectar ---
    window.confirmDisconnect = function(id, username) {
        Swal.fire({
            title: 'Desconectar cuenta',
            html: `<p>Se va a desconectar <strong>@${username}</strong> y se borrarán sus posts cacheados y credenciales.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, desconectar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(result => {
            if (!result.isConfirmed) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/${adminPath}/social-publisher/${id}/disconnect`;
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden'; csrfInput.name = '_csrf'; csrfInput.value = csrf();
            form.appendChild(csrfInput);
            document.body.appendChild(form); form.submit();
        });
    };
})();
</script>
@endpush
