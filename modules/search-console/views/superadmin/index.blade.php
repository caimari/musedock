@extends('layouts::app')

@section('title', 'Search Console')

@section('content')
@php
    // Las propiedades virtuales (subdominios) ya están dentro del total de su dominio: no se suman dos veces
    $virtualIds = array_map(fn ($p) => (int) $p['id'], array_filter($properties, fn ($p) => ($p['page_filter'] ?? '') !== ''));
    $realTotals = array_diff_key($totals, array_flip($virtualIds));
    $sumClicks = array_sum(array_column($realTotals, 'clicks'));
    $sumImpr = array_sum(array_column($realTotals, 'impressions'));
    $fmt = fn($n) => number_format((int) $n, 0, ',', '.');
    $statusLabels = ['active' => ['Activa', 'success'], 'no_access' => ['Sin acceso', 'warning'], 'pending' => ['Pendiente', 'secondary'], 'error' => ['Error', 'danger']];
@endphp
@php include APP_ROOT . '/modules/search-console/views/partials/sortable-css.php'; @endphp
<style>
.gsc-page .card { border:1px solid #e5eaf1;border-radius:12px;box-shadow:0 3px 14px rgba(30,50,80,.035); }
.gsc-page .stat-badge { display:flex;align-items:center;gap:.35rem;font-size:.85rem;padding:.4rem .75rem;border-radius:6px;background:#f8f9fa;border:1px solid #e9ecef; }
.gsc-page .table td,.gsc-page .table th { padding:12px 14px;vertical-align:middle; }
.gsc-page .num { text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap; }
.gsc-dot { width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;background:#ccd3dd; }
.gsc-dot-blue{background:#397de0}.gsc-dot-green{background:#279c65}.gsc-dot-orange{background:#e49a29}.gsc-dot-purple{background:#8a56c5}.gsc-dot-red{background:#d84c53}
.gsc-up { color:#1e7e34; } .gsc-down { color:#c62828; }
.gsc-steps li { margin-bottom:.35rem; }
</style>

<div class="app-content gsc-page">
<div class="container-fluid">

    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#1a73e8,#34a853);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-graph-up-arrow" style="font-size:1.35rem;color:#fff;"></i>
            </div>
            <div>
                <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;">Search Console de tus dominios</h3>
                <p class="text-muted mb-0" style="font-size:0.85rem;">Clics, impresiones y posición de todos tus sitios en un solo panel (últimos 28 días)</p>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <div class="stat-badge"><i class="bi bi-globe2"></i><span>{{ count($properties) }} dominios</span></div>
            <div class="stat-badge" style="background:rgba(26,115,232,.08);border-color:rgba(26,115,232,.2);color:#1a5fc4;"><i class="bi bi-cursor"></i><span>{{ $fmt($sumClicks) }} clics</span></div>
            <div class="stat-badge" style="background:rgba(52,168,83,.08);border-color:rgba(52,168,83,.2);color:#1e7e34;"><i class="bi bi-eye"></i><span>{{ $fmt($sumImpr) }} impresiones</span></div>
            <a href="/musedock/tenants" class="stat-badge" style="text-decoration:none;color:#495057;"><i class="bi bi-building"></i><span>Tenants</span></a>
        </div>
    </div>


    @if($clientCount > 0)
    <div class="alert alert-light border d-flex gap-2 align-items-start" style="font-size:.88rem;">
        <i class="bi bi-shield-lock" style="font-size:1.1rem;color:#5f6873;"></i>
        <div><strong>{{ $clientCount }} {{ $clientCount === 1 ? 'dominio de cliente excluido' : 'dominios de clientes excluidos' }}.</strong>
        Su SEO es privado: cada cliente conecta su propia cuenta de Google desde su panel y solo él ve sus datos. La cuenta de la plataforma nunca los lee.</div>
    </div>
    @endif

    {{-- ===== Cuenta de servicio de la plataforma ===== --}}
    @if(!$credential)
    <div class="card mb-4"><div class="card-body p-4">
        <h5 style="font-weight:700;">Conecta la cuenta de servicio de Google</h5>
        <ol class="gsc-steps" style="font-size:.9rem;">
            <li>En <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud Console</a> crea un proyecto (p. ej. «musedock-gsc»).</li>
            <li>Activa <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener">Google Search Console API</a> y <a href="https://console.cloud.google.com/apis/library/siteverification.googleapis.com" target="_blank" rel="noopener">Site Verification API</a>.</li>
            <li>En <em>IAM y administración → Cuentas de servicio</em> crea una cuenta (sin roles) y en <em>Claves → Añadir clave → JSON</em> descarga el fichero.</li>
            <li>Súbelo aquí. Se guarda cifrado y no se vuelve a mostrar.</li>
        </ol>
        <form method="post" action="{{ $baseUrl }}/credential" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
            {!! csrf_field() !!}
            <input type="file" name="key_file" accept=".json,application/json" class="form-control" style="max-width:420px;" required>
            <button class="btn btn-primary" type="submit"><i class="bi bi-plug"></i> Conectar</button>
        </form>
    </div></div>
    @else
    <div class="card mb-4"><div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div style="font-size:.9rem;">
            <i class="bi bi-person-badge"></i> <strong>{{ $credential['client_email'] }}</strong>
            <span class="text-muted">· proyecto {{ $credential['project_id'] ?: '—' }}</span>
            @if($credential['last_test_ok'] === false)
                <span class="badge bg-danger ms-1">Error: {{ $credential['last_error'] }}</span>
            @else
                <span class="badge bg-success ms-1">Conectada</span>
            @endif
            <div class="text-muted" style="font-size:.8rem;">Para dar acceso a una propiedad a mano: Search Console → Configuración → Usuarios y permisos → añade este email.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" id="gsc-sync-all" title="Descarga ahora clics, impresiones y posición de todas las propiedades activas (se hace sola cada pocas horas)"><i class="bi bi-cloud-arrow-down"></i> Descargar datos ahora</button>
            <form method="post" action="{{ $baseUrl }}/refresh">{!! csrf_field() !!}<button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat"></i> Releer propiedades</button></form>
            <form method="post" action="{{ $baseUrl }}/credential/delete" id="gsc-delete-form">{!! csrf_field() !!}<input type="hidden" name="confirm_password" value=""><button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i> Desconectar</button></form>
        </div>
    </div></div>

    {{-- ===== Dominios ===== --}}
    <div class="card mb-4"><div class="card mb-3" id="gsc-sync-card" style="display:none;"><div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div style="font-weight:700;"><span class="spinner-border spinner-border-sm me-2" id="gsc-sync-spin" aria-hidden="true"></span><span id="gsc-sync-title">Descargando datos de Google…</span></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" id="gsc-sync-stop"><i class="bi bi-stop-circle"></i> Parar</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="gsc-sync-resume" style="display:none;"><i class="bi bi-play-circle"></i> Reanudar</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="gsc-sync-close" style="display:none;">Cerrar</button>
            </div>
        </div>
        <div class="progress" style="height:8px;"><div class="progress-bar bg-success" id="gsc-sync-bar" role="progressbar" style="width:0%"></div></div>
        <div class="mt-2" id="gsc-sync-now" style="font-size:.9rem;"></div>
        <div class="text-muted mt-1" id="gsc-sync-sum" style="font-size:.82rem;"></div>
        <div class="mt-2" id="gsc-sync-list" style="max-height:170px;overflow:auto;font-size:.8rem;display:flex;flex-wrap:wrap;gap:4px 14px;"></div>
        <div class="text-muted mt-2" style="font-size:.76rem;">Se descarga un dominio cada vez, con prioridad baja, y se espera si el servidor está ocupado. Puedes cerrar la página: el servidor sigue solo.</div>
    </div></div>

    <div class="card-body p-0">
        <div class="d-flex gap-2 flex-wrap align-items-center p-3 border-bottom">
            <input type="search" id="gsc-filter-text" class="form-control form-control-sm" placeholder="Filtrar por dominio o tenant…" style="max-width:260px;" aria-label="Filtrar por dominio o tenant">
            <select id="gsc-filter-color" class="form-select form-select-sm" style="max-width:180px;" aria-label="Filtrar por color">
                <option value="">Todos los colores</option>
                @foreach(['blue' => 'Azul', 'green' => 'Verde', 'orange' => 'Naranja', 'purple' => 'Morado', 'red' => 'Rojo', 'none' => 'Sin color'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
            <select id="gsc-filter-status" class="form-select form-select-sm" style="max-width:180px;" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <option value="active">Activas</option>
                <option value="no_access">Sin acceso</option>
            </select>
            <select class="form-select form-select-sm" style="width:auto;" id="gsc-period" aria-label="Periodo" title="Periodo de los clics, impresiones y la comparación">
                @foreach($period['options'] as $pk => $pl)<option value="{{ $pk }}" @if($pk === $period['key'])selected @endif>{{ $pl }}</option>@endforeach
            </select>
            <div class="ms-auto text-muted" style="font-size:.85rem;" id="gsc-filter-sum"></div>
        </div>
        <div class="px-3 pb-2 d-flex align-items-center flex-wrap gap-2" style="font-size:.82rem;">
            <span class="text-muted"><i class="bi bi-calendar-range"></i> Periodo: <strong>{{ $period['range']['from'] }} – {{ $period['range']['to'] }}</strong> ({{ $period['range']['days'] }} días) · comparado con <strong>{{ $period['range']['prev_from'] }} – {{ $period['range']['prev_to'] }}</strong>@if($period['key'] === 0) <em>(no hay datos anteriores)</em>@endif</span>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 ms-auto" id="gsc-cols-info"><i class="bi bi-question-circle"></i> ¿Qué significa cada columna?</button>
        </div>
        <div class="table-responsive">
        <table class="table table-hover mb-0 gsc-sortable" id="gsc-table">
            <thead><tr>
                <th>Dominio</th><th>Tenant</th><th>Estado</th>
                <th class="num">Clics</th><th class="num">Impresiones</th><th class="num">CTR</th><th class="num">Posición</th><th class="num">{{ ($period['key'] ?? 28) === 0 ? 'vs. antes' : 'vs. ' . ($period['key'] ?? 28) . ' d antes' }}</th><th>Última sync.</th>
            </tr></thead>
            <tbody>
            @php
                // Los subdominios (propiedades virtuales) cuelgan de la propiedad de dominio que los cubre
                $gscIds = array_column($properties, 'id');
                $gscKids = [];
                $gscRows = [];
                foreach ($properties as $q) {
                    if (($q['page_filter'] ?? '') !== '' && in_array($q['parent_id'] ?? null, $gscIds)) { $gscKids[$q['parent_id']][] = $q; }
                }
                foreach ($properties as $q) {
                    if (($q['page_filter'] ?? '') !== '' && in_array($q['parent_id'] ?? null, $gscIds)) { continue; }
                    $gscRows[] = $q;
                    foreach ($gscKids[$q['id']] ?? [] as $k) { $gscRows[] = $k; }
                }
            @endphp
            @forelse($gscRows as $p)
                @php
                    $t = $totals[(int) $p['id']] ?? null;
                    $delta = ($t && $t['prev_clicks'] > 0) ? ($t['clicks'] - $t['prev_clicks']) / $t['prev_clicks'] : null;
                    [$sLabel, $sClass] = $statusLabels[$p['status']] ?? [$p['status'], 'secondary'];
                    $color = $p['label_color'] ?: 'none';
                    $isChild = ($p['page_filter'] ?? '') !== '' && in_array($p['parent_id'] ?? null, $gscIds);
                    $nKids = count($gscKids[$p['id']] ?? []);
                @endphp
                <tr data-id="{{ $p['id'] }}" @if($isChild)data-child="1" data-parent="{{ $p['parent_id'] }}" style="display:none;background:rgba(0,0,0,.015);"@endif data-virtual="{{ ($p['page_filter'] ?? '') !== '' ? 1 : 0 }}" data-text="{{ strtolower($p['domain'] . ' ' . ($p['tenant_name'] ?? 'sitio principal')) }}" data-color="{{ $color }}" data-status="{{ $p['status'] }}" data-clicks="{{ $t['clicks'] ?? 0 }}" data-impr="{{ $t['impressions'] ?? 0 }}" @if($focusTenant !== null && (int) $p['tenant_id'] === $focusTenant) class="table-primary" @endif>
                    <td>@if($nKids)<button type="button" class="btn btn-sm btn-light border py-0 px-1 me-1 gsc-toggle" data-id="{{ $p['id'] }}" aria-expanded="false" title="Mostrar los {{ $nKids }} subdominios"><i class="bi bi-chevron-right"></i> <span style="font-size:.72rem;">{{ $nKids }}</span></button>@endif @if($isChild)<span class="text-muted ms-3 me-1">↳</span>@endif<a href="{{ $baseUrl }}/domain/{{ $p['id'] }}" title="Ver el informe de SEO de {{ $p['domain'] }} · {{ ($p['page_filter'] ?? '') !== '' ? $p['page_filter'] . ' (en ' . $p['site_url'] . ')' : $p['site_url'] }}"><strong>{{ $p['domain'] }}</strong></a>@if(($p['page_filter'] ?? '') !== '') <span class="badge bg-light text-dark border" title="Subdominio: sus datos son los de la propiedad del dominio que lo cubre, filtrados por página. Ya están incluidos en el total de ese dominio.">subdominio</span>@endif @if(!empty($p['authorized']))<span class="badge bg-info text-dark" title="Dominio de cliente que has autorizado: el cliente ve su SEO.">cliente autorizado</span>@endif</td>
                    <td><span class="gsc-dot gsc-dot-{{ $color }}"></span>{{ $p['tenant_name'] ?? 'Sitio principal' }}</td>
                    <td><span class="badge bg-{{ $sClass }}">{{ $sLabel }}</span></td>
                    <td class="num">{{ $t ? $fmt($t['clicks']) : '—' }}</td>
                    <td class="num">{{ $t ? $fmt($t['impressions']) : '—' }}</td>
                    <td class="num">{{ $t ? number_format($t['ctr'] * 100, 1, ',', '.') . ' %' : '—' }}</td>
                    <td class="num">{{ ($t && $t['position'] !== null) ? number_format($t['position'], 1, ',', '.') : '—' }}</td>
                    <td class="num">
                        @if($delta !== null)
                            <span class="{{ $delta >= 0 ? 'gsc-up' : 'gsc-down' }}">{{ ($delta >= 0 ? '+' : '') . number_format($delta * 100, 0, ',', '.') }} %</span>
                        @else — @endif
                    </td>
                    <td style="font-size:.8rem;" data-sort="{{ $p['last_sync_at'] ?: '0000' }}">{{ $p['last_sync_at'] ? date('d/m H:i', strtotime($p['last_sync_at'])) : 'Pendiente' }}
                        @if(($p['page_filter'] ?? '') === '')<button type="button" class="btn btn-link btn-sm text-secondary p-0 ms-2 gsc-archive" data-property="{{ $p['id'] }}" data-domain="{{ $p['domain'] }}" title="Dar de baja sin borrar los datos"><i class="bi bi-archive"></i></button>@endif</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted p-4">La cuenta aún no ve ninguna propiedad de tus dominios. Dalas de alta abajo o añade su email en Search Console.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div></div>

    @if(!empty($redirectPairs))
    <div class="card mb-4"><div class="card-body p-0" data-gsc-filter>
        <div class="p-3 border-bottom">
            <h6 class="mb-0" style="font-weight:700;"><i class="bi bi-signpost-split"></i> Mover el SEO entre un dominio y su alias ({{ count($redirectPairs) }})</h6>
            <div class="text-muted" style="font-size:.82rem;">
                Si un sitio responde por dos nombres (por ejemplo <em>alexapost.musedock.com</em> y <em>alexapost.com</em>), Google puede indexar los dos y repartirse el SEO. Con
                <strong>«SEO aquí»</strong>, junto al nombre que quieres conservar, el otro pasa a redirigir (301, conservando la ruta) y el SEO se concentra en él. Pide tu contraseña, se verifica con una petición real y se deshace solo si falla.
                Solo funciona mientras el dominio siga siendo tuyo.
            </div>
        </div>
        <div id="gsc-rp-bar" class="px-3 py-2 border-bottom d-flex gap-2 align-items-center flex-wrap" style="background:rgba(26,115,232,.04);">
            <span class="text-muted" style="font-size:.85rem;"><strong id="gsc-rp-count">0</strong> seleccionados</span>
            <button type="button" class="btn btn-outline-primary btn-sm gsc-rp-bulk" data-mode="to_alias" disabled>SEO → los alias</button>
            <button type="button" class="btn btn-outline-primary btn-sm gsc-rp-bulk" data-mode="to_tenant" disabled>SEO → los dominios del tenant</button>
            <button type="button" class="btn btn-outline-danger btn-sm gsc-rp-bulk" data-mode="remove" disabled>Quitar redirecciones</button>
            <span class="text-muted ms-auto" style="font-size:.78rem;">Marca los sitios y aplica la acción a todos de una vez, con una sola contraseña.</span>
        </div>
        <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle gsc-sortable">
            <thead><tr><th style="width:1%;"><input type="checkbox" id="gsc-rp-all" class="form-check-input" title="Seleccionar todos los visibles" aria-label="Seleccionar todos"></th><th>Sitio</th><th>Dominio del tenant</th><th>Alias</th><th>Estado</th></tr></thead>
            <tbody>
            @foreach($redirectPairs as $rp)
                @php $rpOk = $rp['alias_status'] === 'active' && $rp['redirect'] === ''; @endphp
                <tr>
                    <td><input type="checkbox" class="form-check-input gsc-rp-check" value="{{ $rp['tenant_domain'] }}|{{ $rp['alias'] }}" data-redirect="{{ $rp['redirect'] }}" @if(!$rpOk && $rp['redirect'] === '')disabled title="El alias no está activo"@endif aria-label="Seleccionar {{ $rp['tenant_domain'] }} y {{ $rp['alias'] }}"></td>
                    <td>{{ $rp['name'] }}</td>
                    <td style="white-space:nowrap;"><strong>{{ $rp['tenant_domain'] }}</strong>
                        @if($rpOk)<button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 ms-2 gsc-redirect" style="font-size:.78rem;" data-from="{{ $rp['alias'] }}" data-to="{{ $rp['tenant_domain'] }}" title="El SEO se concentra en {{ $rp['tenant_domain'] }}; {{ $rp['alias'] }} pasará a redirigir aquí"><i class="bi bi-bullseye"></i> SEO aquí</button>@endif</td>
                    <td style="white-space:nowrap;"><strong>{{ $rp['alias'] }}</strong>@if($rp['alias_status'] !== 'active') <span class="badge bg-secondary">{{ $rp['alias_status'] }}</span>@endif
                        @if($rpOk)<button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 ms-2 gsc-redirect" style="font-size:.78rem;" data-from="{{ $rp['tenant_domain'] }}" data-to="{{ $rp['alias'] }}" title="El SEO se concentra en {{ $rp['alias'] }}; {{ $rp['tenant_domain'] }} pasará a redirigir aquí"><i class="bi bi-bullseye"></i> SEO aquí</button>@endif</td>
                    <td style="font-size:.85rem;white-space:nowrap;">
                        @if($rp['redirect'] === 'tenant')<span class="badge bg-success">{{ $rp['tenant_domain'] }} → {{ $rp['alias'] }}</span>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 ms-2 gsc-redirect-remove" style="font-size:.78rem;" data-from="{{ $rp['tenant_domain'] }}">Quitar redirección</button>
                        @elseif($rp['redirect'] === 'alias')<span class="badge bg-success">{{ $rp['alias'] }} → {{ $rp['tenant_domain'] }}</span>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 ms-2 gsc-redirect-remove" style="font-size:.78rem;" data-from="{{ $rp['alias'] }}">Quitar redirección</button>
                        @else<span class="badge bg-warning text-dark" title="Los dos nombres sirven el sitio: Google puede indexarlos por separado">Sirven los dos</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div></div>
    @endif

    @if(!empty($indexNow['hosts']))
    <div class="card mb-4"><div class="card-body p-0" data-gsc-filter>
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0" style="font-weight:700;"><i class="bi bi-lightning-charge"></i> IndexNow: aviso a Bing, Yandex y otros ({{ count($indexNow['hosts']) }} dominios)
                    <span class="badge bg-{{ $indexNow['enabled'] ? 'success' : 'secondary' }} ms-1">{{ $indexNow['enabled'] ? 'Activo' : 'Desactivado' }}</span></h6>
                <div class="text-muted" style="font-size:.82rem;">
                    Cada hora se lee el sitemap de cada dominio propio y se avisa de lo <strong>nuevo o modificado</strong> a los buscadores que usan IndexNow. <strong>Google no lo usa</strong>: para Google están «Enviar sitemap» e «Inspeccionar URL» en el informe de cada dominio.
                    La primera lectura de un dominio solo toma nota. Clave pública de verificación: <code>/{{ $indexNow['key'] }}.txt</code> en la raíz de cada dominio.
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="gsc-indexnow-toggle" data-enabled="{{ $indexNow['enabled'] ? 1 : 0 }}">{{ $indexNow['enabled'] ? 'Desactivar avisos' : 'Activar avisos' }}</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="gsc-indexnow-scan"><i class="bi bi-arrow-repeat"></i> Comprobar ahora</button>
            </div>
        </div>
        <div class="table-responsive">
        <table class="table table-sm mb-0 gsc-sortable" id="gsc-indexnow-table">
            <thead><tr><th>Dominio</th><th>Última lectura</th><th>Resultado</th><th class="num">Avisadas</th></tr></thead>
            <tbody>
            @foreach($indexNow['hosts'] as $h)
                <tr data-host="{{ $h['host'] }}">
                    <td><strong>{{ $h['host'] }}</strong></td>
                    <td data-sort="{{ $h['checked_at'] ?? '' }}" style="font-size:.85rem;">{{ $h['checked_at'] ? date('d/m H:i', strtotime($h['checked_at'])) : '—' }}</td>
                    <td class="text-muted" style="font-size:.82rem;">{{ $h['last_status'] ?? 'Sin leer todavía' }}</td>
                    <td class="num">{{ (int) $h['last_count'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div></div>
    @endif

    @if(!empty($expiry))
    @php
        $gscRisk = array_filter($expiry, fn ($r) => ($r['level'] ?? 0) > 0);
        $gscStatusLabel = ['ok' => ['Al día', 'success'], 'redemption' => ['En redención', 'danger'], 'pending_delete' => ['Pendiente de borrado', 'danger'], 'expired' => ['Caducado', 'danger'], 'not_registered' => ['NO registrado', 'danger'], 'unknown' => ['Sin datos', 'secondary'], 'pending' => ['Sin comprobar', 'secondary']];
    @endphp
    <div class="card mb-4 {{ $gscRisk ? 'border-danger' : '' }}"><div class="card-body p-0" data-gsc-filter>
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0" style="font-weight:700;"><i class="bi bi-calendar-x"></i> Caducidad de dominios ({{ count($expiry) }})@if($gscRisk) <span class="badge bg-danger ms-1">{{ count($gscRisk) }} en riesgo</span>@endif</h6>
                <div class="text-muted" style="font-size:.82rem;">Se comprueba cada día y te avisamos por correo a los 60, 30, 15 y 7 días, y si ya ha caducado. Un dominio que caduca del todo no se puede redirigir: su SEO se pierde.</div>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="gsc-expiry-check"><i class="bi bi-arrow-repeat"></i> Comprobar ahora</button>
        </div>
        <div class="table-responsive">
        <table class="table table-sm mb-0 gsc-sortable" id="gsc-expiry-table">
            <thead><tr><th>Dominio</th><th>Sitios</th><th>Caduca</th><th class="num">Días</th><th>Estado</th><th>Registrador</th></tr></thead>
            <tbody>
            @foreach($expiry as $x)
                @php
                    [$xl, $xc] = $gscStatusLabel[$x['status']] ?? [$x['status'], 'secondary'];
                    $xd = $x['days'];
                    $xwarn = ($x['level'] ?? 0) > 0 ? ($x['level'] <= 15 ? 'text-danger fw-bold' : 'text-warning fw-bold') : '';
                @endphp
                <tr data-domain="{{ $x['domain'] }}">
                    <td><strong>{{ $x['domain'] }}</strong></td>
                    <td class="text-muted" style="font-size:.82rem;">{{ implode(', ', $x['tenants']) }}</td>
                    <td data-sort="{{ $x['expires_at'] ?? '9999-12-31' }}">{{ $x['expires_at'] ? date('d/m/Y', strtotime($x['expires_at'])) : '—' }}</td>
                    <td class="num {{ $xwarn }}">{{ $xd !== null ? $xd : '—' }}</td>
                    <td><span class="badge bg-{{ $xc }}" @if($x['note'])title="{{ $x['note'] }}"@endif>{{ $xl }}</span></td>
                    <td class="text-muted" style="font-size:.82rem;">{{ $x['registrar'] ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div></div>
    @endif

    @if(!empty($archived))
    <div class="card mb-4"><div class="card-body p-3" data-gsc-filter>
        <h6 class="mb-1" style="font-weight:700;"><i class="bi bi-archive"></i> Dominios dados de baja ({{ count($archived) }})</h6>
        <div class="text-muted mb-2" style="font-size:.82rem;">Ya no se descargan ni se muestran, pero <strong>sus datos se conservan</strong>: si el dominio vuelve a ser tuyo, reactívalo y sigues donde lo dejaste.</div>
        <ul class="list-group list-group-flush">
        @foreach($archived as $a)
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <span><strong>{{ $a['domain'] }}</strong> <span class="text-muted" style="font-size:.82rem;">· {{ $a['tenant_name'] ?? 'Sitio principal' }}@if($a['data_until']) · datos hasta {{ date('d/m/Y', strtotime($a['data_until'])) }}@endif</span></span>
                <button type="button" class="btn btn-outline-primary btn-sm gsc-restore" data-property="{{ $a['id'] }}" data-domain="{{ $a['domain'] }}">Reactivar</button>
            </li>
        @endforeach
        </ul>
    </div></div>
    @endif

    @if(!empty($external))
    <div class="card mb-4"><div class="card-body p-0" data-gsc-filter>
        <div class="p-3 border-bottom">
            <h6 class="mb-0" style="font-weight:700;">Otros dominios de Search Console ({{ count($external) }})</h6>
            <div class="text-muted" style="font-size:.82rem;">Están en tu Search Console pero no son ningún sitio del CMS. Solo los ves tú; si alguno se da de alta como tenant, pasa a la tabla de arriba (o se borra, si es de un cliente).</div>
        </div>
        <div class="table-responsive">
        <table class="table table-hover mb-0 gsc-sortable" id="gsc-table-ext">
            <thead><tr><th>Dominio</th><th>Estado</th><th class="num">Clics</th><th class="num">Impresiones</th><th class="num">CTR</th><th class="num">Posición</th><th class="num">{{ ($period['key'] ?? 28) === 0 ? 'vs. antes' : 'vs. ' . ($period['key'] ?? 28) . ' d antes' }}</th><th>Última sync.</th></tr></thead>
            <tbody>
            @foreach($external as $p)
                @php
                    $t = $totals[(int) $p['id']] ?? null;
                    $delta = ($t && $t['prev_clicks'] > 0) ? ($t['clicks'] - $t['prev_clicks']) / $t['prev_clicks'] : null;
                    [$sLabel, $sClass] = $statusLabels[$p['status']] ?? [$p['status'], 'secondary'];
                @endphp
                <tr>
                    <td><a href="{{ $baseUrl }}/domain/{{ $p['id'] }}" title="Ver el informe de SEO de este dominio"><strong>{{ $p['domain'] }}</strong></a><div class="text-muted" style="font-size:.75rem;">{{ $p['site_url'] }}</div></td>
                    <td><span class="badge bg-{{ $sClass }}">{{ $sLabel }}</span></td>
                    <td class="num">{{ $t ? $fmt($t['clicks']) : '—' }}</td>
                    <td class="num">{{ $t ? $fmt($t['impressions']) : '—' }}</td>
                    <td class="num">{{ $t ? number_format($t['ctr'] * 100, 1, ',', '.') . ' %' : '—' }}</td>
                    <td class="num">{{ ($t && $t['position'] !== null) ? number_format($t['position'], 1, ',', '.') : '—' }}</td>
                    <td class="num">@if($delta !== null)<span class="{{ $delta >= 0 ? 'gsc-up' : 'gsc-down' }}">{{ ($delta >= 0 ? '+' : '') . number_format($delta * 100, 0, ',', '.') }} %</span>@else — @endif</td>
                    <td style="font-size:.8rem;" data-sort="{{ $p['last_sync_at'] ?: '0000' }}">{{ $p['last_sync_at'] ? date('d/m H:i', strtotime($p['last_sync_at'])) : 'Pendiente' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div></div>
    @endif

    @php
        $gscMainMissing = array_values(array_filter($missing ?? [], fn ($m) => empty($m['alias_of'])));
        $gscAliasMissing = array_values(array_filter($missing ?? [], fn ($m) => !empty($m['alias_of'])));
        $gscEnabledCount = count(array_filter($missing ?? [], fn ($m) => empty($m['disabled'])));
    @endphp
    @if(!empty($missing))
    {{-- Progreso de «Dar de alta todos en SEO»: la cola vive en el servidor y sobrevive a recargar --}}
    <div class="card mb-3" id="gsc-bulk-card" style="display:none;"><div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div style="font-weight:700;"><span class="spinner-border spinner-border-sm me-2" id="gsc-bulk-spin" aria-hidden="true"></span><span id="gsc-bulk-title">Dando de alta en SEO…</span></div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" id="gsc-bulk-stop"><i class="bi bi-stop-circle"></i> Parar</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="gsc-bulk-resume" style="display:none;"><i class="bi bi-play-circle"></i> Reanudar</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="gsc-bulk-close" style="display:none;">Cerrar</button>
            </div>
        </div>
        <div class="progress" style="height:8px;"><div class="progress-bar" id="gsc-bulk-bar" role="progressbar" style="width:0%"></div></div>
        <div class="mt-2" id="gsc-bulk-now" style="font-size:.9rem;"></div>
        <div class="text-muted mt-1" id="gsc-bulk-sum" style="font-size:.82rem;"></div>
    </div></div>

    @foreach([['main', $gscMainMissing, 'Dominios propios sin dar de alta en SEO', 'Dominio principal de cada tenant propio. «Dar de alta en SEO» da acceso a este dominio al robot (la cuenta del JSON): pone un TXT en Cloudflare para demostrar que es tuyo. Tu acceso no cambia.'], ['alias', $gscAliasMissing, 'Alias de dominio sin dar de alta en SEO', 'Otros nombres con los que responde un tenant (Domain Manager). Cada alias es una propiedad distinta para Google; si su DNS está en una cuenta de Cloudflare del panel, el TXT se publica solo; si no, te lo mostramos para ponerlo a mano. Los alias suspendidos esperan a estar activos.']] as [$gscKind, $gscList, $gscTitle, $gscHelp])
    @if(!empty($gscList))
    <div class="card mb-4" data-gsc-card="{{ $gscKind }}"><div class="card-body p-3" data-gsc-filter>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h6 class="mb-0" style="font-weight:700;"><i class="bi {{ $gscKind === 'alias' ? 'bi-link-45deg' : 'bi-globe2' }}"></i> {{ $gscTitle }} (<span class="gsc-card-count">{{ count($gscList) }}</span>)</h6>
                <div class="text-muted" style="font-size:.82rem;">{{ $gscHelp }}</div>
            </div>
            @php $gscCardEnabled = count(array_filter($gscList, fn ($m) => empty($m['disabled']))); @endphp
            @if($gscCardEnabled)
            <button type="button" class="btn btn-primary btn-sm gsc-bulk-start" data-scope="{{ $gscKind }}"><i class="bi bi-lightning"></i> Dar de alta todos en SEO ({{ $gscCardEnabled }})</button>
            @endif
        </div>
        <ul class="list-group list-group-flush">
            @foreach($gscList as $m)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap px-0" data-domain="{{ $m['domain'] }}">
                @php $pend = ($m['state'] ?? null) === 'pending'; @endphp
                <span><strong>{{ $m['domain'] }}</strong> <span class="text-muted" style="font-size:.82rem;">· @if(!empty($m['alias_of']))alias de <strong>{{ $m['alias_of_domain'] ?? $m['alias_of'] }}</strong> <span class="text-muted">({{ $m['alias_of'] }})</span>@else{{ $m['name'] }}@endif</span>
                    <span class="gsc-badge">@if($pend)<span class="badge bg-warning text-dark ms-1" title="El TXT ya está puesto; Google aún no lo reconoce. El servidor lo reintenta solo cada pocos minutos, aunque cierres esta página.">Pendiente de Google</span>@endif
                    @if(($m['state'] ?? null) === 'no_access')<span class="badge bg-secondary ms-1">Perdió el acceso</span>@endif</span>
                    @if(!empty($m['disabled']))<div class="text-muted" style="font-size:.78rem;">{{ $m['disabled'] }}</div>@endif
                </span>
                <span class="d-flex align-items-center gap-2">
                    <span class="gsc-verify-msg {{ $pend ? 'text-warning' : 'text-muted' }}" style="font-size:.8rem;max-width:520px;">@if($pend){{ $m['note'] }}@if(!empty($m['checked_at'])) (última comprobación: {{ date('d/m H:i', strtotime($m['checked_at'])) }})@endif @endif</span>
                    <button type="button" class="btn btn-outline-primary btn-sm gsc-verify" @if(!empty($m['disabled']))disabled title="{{ $m['disabled'] }}"@endif>{{ $pend ? 'Reintentar ahora' : 'Dar de alta en SEO' }}</button>
                </span>
            </li>
            @endforeach
        </ul>
    </div></div>
    @endif
    @endforeach
    @endif

    {{-- ===== Dominios de clientes: los autorizas tú ===== --}}
    @if(!empty($clientTenants))
    <div class="card mb-4"><div class="card-body p-3" data-gsc-filter>
        <h6 class="mb-1" style="font-weight:700;"><i class="bi bi-shield-lock"></i> Dominios de clientes ({{ count($clientTenants) }})</h6>
        <div class="text-muted mb-2" style="font-size:.82rem;">
            Los datos de un cliente son privados salvo que <strong>tú</strong> lo autorices: si das de alta su dominio o subdominio con tu cuenta de Google, el cliente ve su SEO sin hacer nada
            (solo el suyo, nunca el de otros), y tú también lo verás. Si no lo autorizas, el cliente puede conectar su propia cuenta. Autorizar y retirar piden tu contraseña.
        </div>
        <ul class="list-group list-group-flush">
        @foreach($clientTenants as $ct)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap px-0">
                <span><strong>{{ $ct['domain'] }}</strong> <span class="text-muted" style="font-size:.82rem;">· {{ $ct['name'] }}</span>
                    @if($ct['authorized'] && $ct['state'] === 'active')<span class="badge bg-success ms-1">Autorizado</span>@if($ct['virtual'])<span class="badge bg-light text-dark border ms-1">subdominio</span>@endif
                    @elseif($ct['authorized'])<span class="badge bg-warning text-dark ms-1">Autorizado · pendiente de Google</span>
                    @else<span class="badge bg-secondary ms-1">No autorizado · el cliente conecta su cuenta</span>@endif
                    @if($ct['authorized'] && $ct['state'] !== 'active' && $ct['note'])<div class="text-muted" style="font-size:.78rem;">{{ $ct['note'] }}</div>@endif
                </span>
                <span>
                    @if($ct['authorized'])
                        <button type="button" class="btn btn-outline-danger btn-sm gsc-client-revoke" data-property="{{ $ct['property_id'] }}" data-domain="{{ $ct['domain'] }}">Retirar autorización</button>
                    @else
                        <button type="button" class="btn btn-outline-primary btn-sm gsc-client-authorize" data-domain="{{ $ct['domain'] }}">Autorizar y dar de alta</button>
                    @endif
                </span>
            </li>
        @endforeach
        </ul>
    </div></div>
    @endif

    {{-- ===== Dominio externo (WordPress, otro servidor, otro panel…) ===== --}}
    <div class="card mb-4"><div class="card-body p-3">
        <h6 class="mb-1" style="font-weight:700;">Añadir un dominio que no está en el CMS</h6>
        <div class="text-muted mb-2" style="font-size:.82rem;">
            Sirve para WordPress u otros servidores. Si su DNS está en una cuenta de Cloudflare del panel, el registro TXT se publica solo.
            Si no, te mostramos el TXT para que lo pongas en su DNS; después vuelve a pulsar «Dar de alta». Los dominios de clientes no se pueden añadir.
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <input type="text" id="gsc-ext-domain" class="form-control form-control-sm" placeholder="ejemplo.com" style="max-width:260px;" autocomplete="off" aria-label="Dominio externo">
            <button type="button" class="btn btn-primary btn-sm" id="gsc-ext-btn"><i class="bi bi-plus-circle"></i> Dar de alta</button>
        </div>
        <div id="gsc-ext-msg" class="mt-2" style="font-size:.85rem;word-break:break-all;"></div>
        @if(!empty($pendingExternal))
        <div class="mt-3" style="font-size:.85rem;">
            <strong>Pendientes de verificar</strong> <span class="text-muted">(el servidor los reintenta solo cada pocos minutos, aunque cierres esta página)</span>
            @foreach($pendingExternal as $pe)
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap border-top py-2 mt-2">
                <span><strong>{{ $pe['domain'] }}</strong> <span class="badge bg-warning text-dark">Pendiente</span>
                    <span class="text-muted" style="word-break:break-all;">{{ $pe['verification_note'] }}@if(!empty($pe['updated_at'])) (última comprobación: {{ date('d/m H:i', strtotime($pe['updated_at'])) }})@endif</span></span>
                <button type="button" class="btn btn-outline-primary btn-sm gsc-ext-retry" data-domain="{{ $pe['domain'] }}">Reintentar ahora</button>
            </div>
            @endforeach
        </div>
        @endif
    </div></div>
    @endif

</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var table = document.getElementById('gsc-table');
    if (table) {
        var text = document.getElementById('gsc-filter-text'), color = document.getElementById('gsc-filter-color'),
            status = document.getElementById('gsc-filter-status'), sum = document.getElementById('gsc-filter-sum');
        var fmt = new Intl.NumberFormat('es-ES');
        function apply() {
            var q = text.value.trim().toLowerCase(), c = color.value, st = status.value, n = 0, clicks = 0, impr = 0;
            var rows = table.querySelectorAll('tbody tr[data-text]'), match = {}, parents = {};
            rows.forEach(function (tr) {
                match[tr.dataset.id] = (!q || tr.dataset.text.indexOf(q) !== -1) && (!c || tr.dataset.color === c) && (!st || tr.dataset.status === st);
                parents[tr.dataset.id] = tr;
            });
            rows.forEach(function (tr) {
                var show = match[tr.dataset.id];
                if (tr.dataset.child) {
                    var par = parents[tr.dataset.parent];
                    show = show && (q || (par && par.dataset.open === '1'));
                } else if (!show) {
                    // el dominio también se muestra si coincide alguno de sus subdominios
                    rows.forEach(function (k) { if (k.dataset.parent === tr.dataset.id && match[k.dataset.id]) { show = true; } });
                }
                tr.style.display = show ? '' : 'none';
                if (show && tr.dataset.virtual !== '1') { n++; clicks += +tr.dataset.clicks; impr += +tr.dataset.impr; }
            });
            sum.textContent = n + ' dominios · ' + fmt.format(clicks) + ' clics · ' + fmt.format(impr) + ' impresiones';
        }
        [text, color, status].forEach(function (el) { el.addEventListener('input', apply); });
        apply();
    }

    var token = @json(csrf_token());
    function verify(li) {
        var btn = li.querySelector('.gsc-verify'), msg = li.querySelector('.gsc-verify-msg');
        btn.disabled = true; msg.className = 'gsc-verify-msg text-muted'; msg.textContent = 'Verificando…';
        var body = new URLSearchParams({ domain: li.dataset.domain, _csrf: token });
        return fetch(@json($baseUrl . '/verify'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var cls = { verified: 'text-success', pending: 'text-warning', manual: 'text-secondary', error: 'text-danger' }[res.status] || 'text-danger';
                msg.className = 'gsc-verify-msg ' + cls; msg.textContent = res.message;
                btn.disabled = res.status === 'verified';
                if (res.status === 'verified') { btn.textContent = 'Hecho'; li._tries = 0; }
                // Google tarda unos segundos en ver el TXT recién publicado: se reintenta solo (hasta 5 veces)
                if (res.status === 'pending' && (li._tries = (li._tries || 0) + 1) <= 5) {
                    btn.disabled = true;
                    msg.textContent = res.message + ' Reintentando en 20 s (' + li._tries + '/5)…';
                    setTimeout(function () { verify(li); }, 20000);
                }
            })
            .catch(function () { msg.className = 'gsc-verify-msg text-danger'; msg.textContent = 'Error de red'; btn.disabled = false; });
    }
    document.querySelectorAll('.gsc-verify').forEach(function (b) { b.addEventListener('click', function () { verify(b.closest('li')); }); });
    document.querySelectorAll('form[action$="/refresh"]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            var b = e.submitter || f.querySelector('button');
            if (b) { b.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'); setTimeout(function () { b.disabled = true; }, 0); }
            if (window.Swal) { Swal.fire({ title: 'Leyendo las propiedades de Search Console…', text: 'Puede tardar unos segundos.', allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } }); }
        });
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted) { location.reload(); } });

    document.querySelectorAll('.gsc-ext-retry').forEach(function (b) {
        b.addEventListener('click', function () { document.getElementById('gsc-ext-domain').value = b.dataset.domain; document.getElementById('gsc-ext-btn').click(); });
    });

    function clientAction(url, extra, title, html, confirmText, danger) {
        Swal.fire({ title: title, html: html, icon: danger ? 'warning' : 'question', input: 'password', inputPlaceholder: 'Contraseña de administrador',
            inputAttributes: { autocomplete: 'current-password' }, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: 'Cancelar',
            confirmButtonColor: danger ? '#dc3545' : undefined, inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; } })
        .then(function (r) {
            if (!r.isConfirmed) { return; }
            Swal.fire({ title: 'Trabajando…', text: 'Puede tardar unos segundos.', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
            var body = new URLSearchParams(Object.assign({ confirm_password: r.value, _csrf: token }, extra));
            fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: body }).then(function (x) { return x.json(); }).then(function (res) {
                var ok = res.status === 'verified' || res.status === 'pending';
                Swal.fire({ icon: ok ? 'success' : 'error', title: ok ? 'Hecho' : 'No se pudo', text: res.message }).then(function () { if (ok) { location.reload(); } });
            }).catch(function () { Swal.fire({ icon: 'error', title: 'Error de red' }); });
        });
    }
    document.querySelectorAll('.gsc-client-authorize').forEach(function (b) {
        b.addEventListener('click', function () {
            clientAction(@json($baseUrl . '/client/authorize'), { domain: b.dataset.domain }, 'Autorizar el SEO de ' + b.dataset.domain,
                'Vas a dar de alta este dominio de cliente con tu cuenta de Google. <strong>El cliente verá su SEO sin hacer nada</strong> (solo el suyo) y tú también lo verás. Puedes retirarlo cuando quieras. Confirma con tu contraseña.', 'Autorizar', false);
        });
    });
    document.querySelectorAll('.gsc-client-revoke').forEach(function (b) {
        b.addEventListener('click', function () {
            clientAction(@json($baseUrl . '/client/revoke'), { property_id: b.dataset.property }, 'Retirar la autorización de ' + b.dataset.domain,
                'Se <strong>borrarán de la plataforma</strong> los datos de este cliente y dejará de ver su SEO desde tu cuenta (podrá conectar la suya). Confirma con tu contraseña.', 'Retirar y borrar', true);
        });
    });

    // ===== «Descargar datos ahora»: cola en el servidor con progreso, parar y reanudar =====
    var syncAll = document.getElementById('gsc-sync-all'), syncCard = document.getElementById('gsc-sync-card');
    if (syncAll && syncCard) {
        var sBar = document.getElementById('gsc-sync-bar'), sTitle = document.getElementById('gsc-sync-title'), sNow = document.getElementById('gsc-sync-now'), sSum = document.getElementById('gsc-sync-sum'),
            sList = document.getElementById('gsc-sync-list'), sSpin = document.getElementById('gsc-sync-spin'), sStop = document.getElementById('gsc-sync-stop'), sResume = document.getElementById('gsc-sync-resume'), sClose = document.getElementById('gsc-sync-close'), sDriving = false;
        var sUrl = @json($baseUrl . '/syncq/');
        function sPost(a) { return fetch(sUrl + a, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ _csrf: token }) }).then(function (r) { return r.json(); }); }
        function sPaint(st) {
            syncCard.style.display = '';
            var running = st.status === 'running', cur = null;
            sBar.style.width = (st.total ? Math.round(st.finished * 100 / st.total) : 100) + '%';
            sSpin.style.display = running ? '' : 'none'; sStop.style.display = running ? '' : 'none';
            sResume.style.display = st.status === 'stopped' ? '' : 'none'; sClose.style.display = running ? 'none' : '';
            sTitle.textContent = running ? 'Descargando datos de Google… ' + st.finished + ' de ' + st.total : (st.status === 'stopped' ? 'Detenido: ' + st.finished + ' de ' + st.total : 'Terminado: ' + st.finished + ' de ' + st.total);
            st.items.forEach(function (i) { if (i.state === 'working') { cur = i; } });
            if (!running) { sNow.innerHTML = ''; }
            else if (st.wait === 'load') { sNow.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>El servidor está ocupado (carga ' + st.load + ' con ' + st.cores + ' núcleos): espero a que baje para no añadirle trabajo.'; }
            else if (cur) { sNow.innerHTML = '<span class="spinner-border spinner-border-sm text-success me-2" aria-hidden="true"></span>Ahora: descargando <strong>' + cur.domain + '</strong>' + (st.wait === 'busy' ? ' <span class="text-muted">(lo procesa el servidor)</span>' : ''); }
            else { sNow.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary me-2" aria-hidden="true"></span>Preparando el siguiente dominio…'; }
            sSum.textContent = st.counts.ok + ' descargados · ' + st.counts.queued + ' en cola · ' + st.counts.error + ' con error · ' + st.rows.toLocaleString('es-ES') + ' filas guardadas';
            sList.innerHTML = st.items.map(function (i) {
                var ico = { ok: '✅', error: '❌', working: '⏳', queued: '·' }[i.state] || '·';
                return '<span title="' + (i.message || (i.state === 'ok' ? i.rows + ' filas en ' + i.secs + ' s' : '')).replace(/"/g, '') + '" style="' + (i.state === 'queued' ? 'opacity:.45;' : '') + (i.state === 'working' ? 'font-weight:700;' : '') + '">' + ico + ' ' + i.domain + '</span>';
            }).join('');
        }
        function sDrive() {
            if (sDriving) { return; }
            sDriving = true;
            (function loop() {
                sPost('step').then(function (st) {
                    sPaint(st);
                    if (st.status === 'running') { setTimeout(loop, st.wait === 'load' ? 5000 : (st.wait ? 1500 : 300)); }
                    else { sDriving = false; if (st.counts.ok) { var f = document.getElementById('gsc-filter-text'); fetch(location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) { var d = new DOMParser().parseFromString(html, 'text/html'), fresh = d.getElementById('gsc-table'), cur = document.getElementById('gsc-table'); if (fresh && cur) { cur.innerHTML = fresh.innerHTML; if (f) { f.dispatchEvent(new Event('input')); } } }); } }
                }).catch(function () { sDriving = false; sSum.textContent = 'Sin conexión con el servidor: la cola sigue allí; recarga para continuar.'; });
            })();
        }
        syncAll.addEventListener('click', function () { sPost('start').then(function (st) { sPaint(st); sDrive(); }); });
        sStop.addEventListener('click', function () { sPost('stop').then(sPaint); });
        sResume.addEventListener('click', function () { sPost('resume').then(function (st) { sPaint(st); sDrive(); }); });
        sClose.addEventListener('click', function () { syncCard.style.display = 'none'; });
        sPost('status').then(function (st) { if (st.total && (st.status === 'running' || st.status === 'stopped')) { sPaint(st); if (st.status === 'running') { sDrive(); } } });
    }

    var del = document.getElementById('gsc-delete-form');
    if (del) del.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
            title: 'Desconectar Search Console',
            html: 'Se desconectará la cuenta de servicio y se <strong>borrarán todos los datos</strong> guardados. Confirma con tu contraseña de administrador.',
            icon: 'warning', input: 'password', inputPlaceholder: 'Contraseña de administrador',
            inputAttributes: { autocomplete: 'current-password' },
            showCancelButton: true, confirmButtonText: 'Desconectar y borrar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545',
            inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; }
        }).then(function (r) {
            if (r.isConfirmed) { del.querySelector('[name=confirm_password]').value = r.value; del.submit(); }
        });
    });

    var extBtn = document.getElementById('gsc-ext-btn');
    if (extBtn) extBtn.addEventListener('click', function () {
        var input = document.getElementById('gsc-ext-domain'), msg = document.getElementById('gsc-ext-msg');
        if (!input.value.trim()) { return; }
        extBtn.disabled = true; msg.className = 'mt-2 text-muted'; msg.textContent = 'Verificando…';
        fetch(@json($baseUrl . '/external'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ domain: input.value.trim(), _csrf: token }) })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var cls = { verified: 'text-success', pending: 'text-warning', manual: 'text-secondary', error: 'text-danger' }[res.status] || 'text-danger';
                msg.className = 'mt-2 ' + cls; msg.textContent = res.message;
                if (res.status === 'verified') { input.value = ''; }
            })
            .catch(function () { msg.className = 'mt-2 text-danger'; msg.textContent = 'Error de red'; })
            .then(function () { extBtn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var b = e.target.closest('.gsc-toggle'); if (!b) { return; }
        var tr = b.closest('tr'), open = tr.dataset.open !== '1';
        tr.dataset.open = open ? '1' : '0';
        b.setAttribute('aria-expanded', open ? 'true' : 'false');
        b.querySelector('i').className = 'bi ' + (open ? 'bi-chevron-down' : 'bi-chevron-right');
        var f = document.getElementById('gsc-filter-text'); if (f) { f.dispatchEvent(new Event('input')); }
    });
    document.querySelectorAll('table.gsc-sortable thead th').forEach(function (th) { if (th.textContent.trim() && !th.title) { th.title = 'Clic para ordenar por esta columna'; } });
    // Ordenar tablas haciendo clic en la cabecera (vale también tras actualizar la tabla en vivo)
    document.addEventListener('click', function (e) {
        var th = e.target.closest('table.gsc-sortable thead th'); if (!th || e.target.closest('input,button,a')) { return; }
        var table = th.closest('table'), idx = Array.prototype.indexOf.call(th.parentNode.children, th), asc = th.dataset.dir !== 'asc';
        function val(tr) {
            var c = tr.children[idx]; if (!c) { return ''; }
            if (c.dataset.sort !== undefined) { return c.dataset.sort; }
            var t = c.textContent.trim().replace(/\s*vs\. anterior.*/, '');
            if (th.classList.contains('num')) { if (/^[—–-]$/.test(t) || t === '') { return null; } var n = parseFloat(t.replace(/[^0-9,+\-]/g, '').replace(',', '.')); return isNaN(n) ? null : n; }
            return t.toLowerCase();
        }
        var tbody = table.tBodies[0], rows = Array.prototype.filter.call(tbody.rows, function (r) { return !r.querySelector('td[colspan]') && !r.dataset.child; });
        rows.sort(function (a, b) {
            var x = val(a), y = val(b);
            if (x === null && y === null) { return 0; } if (x === null) { return 1; } if (y === null) { return -1; }
            return (typeof x === 'number' ? x - y : String(x).localeCompare(String(y), 'es')) * (asc ? 1 : -1);
        });
        var kids = {};
        Array.prototype.forEach.call(tbody.rows, function (r) { if (r.dataset.child) { (kids[r.dataset.parent] = kids[r.dataset.parent] || []).push(r); } });
        rows.forEach(function (r) { tbody.appendChild(r); (kids[r.dataset.id] || []).forEach(function (k) { tbody.appendChild(k); }); });
        table.querySelectorAll('thead th').forEach(function (h) { delete h.dataset.dir; h.classList.remove('gsc-asc', 'gsc-desc'); });
        th.dataset.dir = asc ? 'asc' : 'desc'; th.classList.add(asc ? 'gsc-asc' : 'gsc-desc');
    });

    var expBtn = document.getElementById('gsc-expiry-check');
    if (expBtn) expBtn.addEventListener('click', function () {
        var rows = Array.prototype.slice.call(document.querySelectorAll('#gsc-expiry-table tbody tr')), i = 0;
        expBtn.disabled = true;
        (function next() {
            if (i >= rows.length) { location.reload(); return; }
            expBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Comprobando ' + (i + 1) + ' de ' + rows.length + '…';
            fetch(@json($baseUrl . '/expiry/check'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ domain: rows[i].dataset.domain, _csrf: token }) })
                .catch(function () {}).then(function () { i++; next(); });
        })();
    });

    function redirectCall(action, extra, title, html, confirmText, danger) {
        Swal.fire({ title: title, html: html, icon: danger ? 'warning' : 'question', input: 'password', inputPlaceholder: 'Contraseña de administrador',
            inputAttributes: { autocomplete: 'current-password' }, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: 'Cancelar',
            confirmButtonColor: danger ? '#dc3545' : undefined, inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; } })
        .then(function (r) {
            if (!r.isConfirmed) { return; }
            Swal.fire({ title: 'Aplicando y verificando…', text: 'Unos segundos.', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
            fetch(@json($baseUrl . '/redirect/') + action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams(Object.assign({ confirm_password: r.value, _csrf: token }, extra)) })
                .then(function (x) { return x.json(); })
                .then(function (res) { Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Hecho' : 'No se pudo', text: res.message }).then(function () { if (res.ok) { location.reload(); } }); })
                .catch(function () { Swal.fire({ icon: 'error', title: 'Error de red' }); });
        });
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.gsc-redirect'), rm = e.target.closest('.gsc-redirect-remove');
        if (b) {
            redirectCall('apply', { from: b.dataset.from, to: b.dataset.to }, 'Mover el SEO a ' + b.dataset.to,
                '<div style="text-align:left;font-size:.92rem;">Vas a hacer que <strong>' + b.dataset.from + '</strong> redirija (301) a <strong>' + b.dataset.to + '</strong>, conservando la ruta: ' +
                '<code>' + b.dataset.from + '/blog</code> → <code>' + b.dataset.to + '/blog</code>.<ul class="mt-2"><li>Google traspasará poco a poco el SEO (semanas).</li><li>Se comprueba antes que ' + b.dataset.to + ' responde y se verifica después; si falla, se deshace solo.</li><li>Puedes quitar la redirección cuando quieras.</li></ul>Confirma con tu contraseña.</div>', 'Mover SEO', false);
        }
        if (rm) {
            redirectCall('remove', { from: rm.dataset.from }, 'Quitar la redirección de ' + rm.dataset.from, 'El dominio volverá a servir el sitio y ya no redirigirá. Confirma con tu contraseña.', 'Quitar redirección', true);
        }
    });

    // Buscador (y orden A–Z en las listas) en cada tarjeta marcada con data-gsc-filter
    document.querySelectorAll('[data-gsc-filter]').forEach(function (box) {
        var head = box.children[0]; if (!head) { return; }
        var isList = !!box.querySelector('ul.list-group'), asc = true;
        var bar = document.createElement('div');
        bar.className = (box.classList.contains('p-0') ? 'px-3 py-2 border-bottom' : 'py-2') + ' d-flex gap-2 align-items-center flex-wrap';
        bar.innerHTML = '<input type="search" class="form-control form-control-sm" style="max-width:280px;" placeholder="Buscar en esta lista…" aria-label="Buscar"><span class="text-muted gsc-filter-count" style="font-size:.8rem;"></span>'
            + (isList ? '<button type="button" class="btn btn-outline-secondary btn-sm ms-auto gsc-list-sort"><i class="bi bi-sort-alpha-down"></i> A–Z</button>' : '');
        head.after(bar);
        var input = bar.querySelector('input'), count = bar.querySelector('.gsc-filter-count');
        function rows() { return box.querySelectorAll('tbody tr:not([data-child]), ul.list-group > li'); }
        function run() {
            var q = input.value.trim().toLowerCase(), n = 0, total = 0;
            rows().forEach(function (r) {
                if (r.querySelector('td[colspan]')) { return; }
                total++;
                var show = !q || r.textContent.toLowerCase().indexOf(q) !== -1;
                r.style.display = show ? '' : 'none';
                if (show) { n++; }
            });
            count.textContent = q ? n + ' de ' + total : '';
        }
        input.addEventListener('input', run);
        var sortBtn = bar.querySelector('.gsc-list-sort');
        if (sortBtn) sortBtn.addEventListener('click', function () {
            var ul = box.querySelector('ul.list-group'), items = Array.prototype.slice.call(ul.children);
            items.sort(function (a, b) { return a.textContent.trim().localeCompare(b.textContent.trim(), 'es') * (asc ? 1 : -1); });
            items.forEach(function (li) { ul.appendChild(li); });
            sortBtn.innerHTML = '<i class="bi bi-sort-alpha-' + (asc ? 'up' : 'down') + '"></i> ' + (asc ? 'Z–A' : 'A–Z');
            asc = !asc;
        });
    });

    // ===== Acciones masivas sobre «Mover el SEO» =====
    var rpAll = document.getElementById('gsc-rp-all');
    function rpChecks() { return Array.prototype.slice.call(document.querySelectorAll('.gsc-rp-check:not(:disabled)')); }
    function rpSelected() { return rpChecks().filter(function (c) { return c.checked; }); }
    function rpRefresh() {
        var sel = rpSelected(), canMove = sel.filter(function (c) { return !c.dataset.redirect; }).length, canRemove = sel.filter(function (c) { return c.dataset.redirect; }).length;
        document.getElementById('gsc-rp-count').textContent = sel.length;
        document.querySelectorAll('.gsc-rp-bulk').forEach(function (b) {
            var n = b.dataset.mode === 'remove' ? canRemove : canMove;
            b.disabled = !n;
            b.dataset.n = n;
        });
    }
    if (rpAll) {
        rpAll.addEventListener('change', function () {
            rpChecks().forEach(function (c) { var tr = c.closest('tr'); c.checked = rpAll.checked && tr.style.display !== 'none'; });
            rpRefresh();
        });
        document.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('gsc-rp-check')) { rpRefresh(); } });
        document.addEventListener('click', function (e) {
            var b = e.target.closest('.gsc-rp-bulk'); if (!b || b.disabled) { return; }
            var mode = b.dataset.mode, remove = mode === 'remove';
            var chosen = rpSelected().filter(function (c) { return remove ? c.dataset.redirect : !c.dataset.redirect; }).map(function (c) { return c.value; });
            var what = remove ? 'quitar la redirección de' : (mode === 'to_alias' ? 'concentrar el SEO en el <strong>alias</strong> de' : 'concentrar el SEO en el <strong>dominio del tenant</strong> de');
            Swal.fire({ title: (remove ? 'Quitar redirecciones' : 'Mover el SEO') + ' (' + chosen.length + ')', icon: remove ? 'warning' : 'question', showCancelButton: true, confirmButtonText: remove ? 'Quitar' : 'Mover SEO', cancelButtonText: 'Cancelar',
                confirmButtonColor: remove ? '#dc3545' : undefined,
                html: '<div style="text-align:left;font-size:.92rem;">Vas a ' + what + ' <strong>' + chosen.length + '</strong> sitios:<div class="mt-2" style="max-height:140px;overflow:auto;font-size:.85rem;">' + chosen.map(function (k) { return k.replace('|', ' ⇄ '); }).join('<br>') + '</div>' +
                    (remove ? '' : '<div class="mt-2">Cada uno se comprueba antes, se verifica después y se deshace solo si falla; un fallo no impide los demás. Google traspasa el SEO en semanas.</div>') + '<div class="mt-2">Confirma con tu contraseña.</div></div>',
                input: 'password', inputPlaceholder: 'Contraseña de administrador', inputAttributes: { autocomplete: 'current-password' }, inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; } })
            .then(function (r) {
                if (!r.isConfirmed) { return; }
                Swal.fire({ title: 'Aplicando…', text: 'Una por una; puede tardar unos segundos por sitio.', allowOutsideClick: false, showConfirmButton: false, didOpen: function () { Swal.showLoading(); } });
                var body = new URLSearchParams({ confirm_password: r.value, _csrf: token, mode: mode });
                chosen.forEach(function (d) { body.append('domains[]', d); });
                fetch(@json($baseUrl . '/redirect/bulk'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: body })
                    .then(function (x) { return x.json(); })
                    .then(function (res) {
                        var list = (res.results || []).map(function (i) { return '<div>' + (i.ok ? '✅' : '❌') + ' <strong>' + i.pair + '</strong><span class="text-muted"> — ' + i.message + '</span></div>'; }).join('');
                        Swal.fire({ icon: res.failed ? 'warning' : 'success', title: res.message || 'Hecho', width: 760, html: '<div style="text-align:left;font-size:.85rem;max-height:320px;overflow:auto;">' + (list || res.message || '') + '</div>' }).then(function () { location.reload(); });
                    })
                    .catch(function () { Swal.fire({ icon: 'error', title: 'Error de red' }); });
            });
        });
    }

    var inBtn = document.getElementById('gsc-indexnow-scan');
    if (inBtn) inBtn.addEventListener('click', function () {
        var rows = Array.prototype.slice.call(document.querySelectorAll('#gsc-indexnow-table tbody tr')), i = 0;
        inBtn.disabled = true;
        (function next() {
            if (i >= rows.length) { location.reload(); return; }
            inBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Leyendo ' + (i + 1) + ' de ' + rows.length + '…';
            fetch(@json($baseUrl . '/indexnow/scan'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ host: rows[i].dataset.host, _csrf: token }) })
                .catch(function () {}).then(function () { i++; setTimeout(next, 400); });
        })();
    });
    var inTog = document.getElementById('gsc-indexnow-toggle');
    if (inTog) inTog.addEventListener('click', function () {
        var on = inTog.dataset.enabled !== '1';
        fetch(@json($baseUrl . '/indexnow/toggle'), { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ enabled: on ? '1' : '0', _csrf: token }) }).then(function () { location.reload(); });
    });

    var gscPeriod = document.getElementById('gsc-period');
    if (gscPeriod) gscPeriod.addEventListener('change', function () { location.href = '?days=' + gscPeriod.value; });

    // ===== Ayuda de las columnas: icono (i) en cada cabecera y un resumen de todas =====
    var colHelp = {
        'dominio': ['Dominio', 'El sitio, o el subdominio, del que se descargan los datos. Haz clic en su nombre para abrir su informe completo.'],
        'tenant': ['Tenant', 'El sitio del CMS al que pertenece, con su color de etiqueta (el mismo que en la lista de tenants).'],
        'estado': ['Estado', '<strong>Activa</strong>: el robot tiene acceso y se descargan datos. <strong>Sin acceso</strong>: lo tenía y lo perdió. <strong>Error</strong>: Google devolvió un fallo (el mensaje lo explica).'],
        'clics': ['Clics', 'Veces que alguien vio tu página en los resultados de Google <strong>y entró</strong>, en el periodo elegido.'],
        'impresiones': ['Impresiones', 'Veces que tu página <strong>apareció</strong> en los resultados de alguien, aunque no entrara. Es tu visibilidad en Google.'],
        'ctr': ['CTR (porcentaje de clics)', 'Clics ÷ impresiones. Un 3 % significa que 3 de cada 100 personas que te ven, entran. Depende mucho de la posición: en el puesto 1 lo normal es ~28 %, en el 10 ~2 %. Se mejora con un buen título y descripción.'],
        'posición': ['Posición media', 'El lugar medio en el que sales en los resultados. 1 es el primero; del 1 al 10 estás en la primera página; del 11 al 20, en la segunda. Es una media ponderada por impresiones: <strong>cuanto más bajo, mejor</strong>.'],
        'vs.': ['Comparación con el periodo anterior', 'Variación de los <strong>clics</strong> frente al periodo inmediatamente anterior de la misma duración (las fechas se ven arriba). En verde, más clics; en rojo, menos. Con «Todo el histórico» no hay periodo anterior.'],
        'última': ['Última sincronización', 'Cuándo se descargaron por última vez los datos de Google. Google publica con unos 2 días de retraso, y la descarga se repite sola cada pocas horas.']
    };
    function colKey(th) { var t = th.textContent.trim().toLowerCase(); return Object.keys(colHelp).filter(function (k) { return t.indexOf(k) === 0; })[0] || null; }
    document.querySelectorAll('#gsc-table thead th, #gsc-table-ext thead th').forEach(function (th) {
        var k = colKey(th); if (!k) { return; }
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'btn btn-link p-0 ms-1 gsc-colinfo'; b.dataset.key = k; b.title = 'Qué significa esta columna'; b.setAttribute('aria-label', 'Qué significa ' + colHelp[k][0]);
        b.innerHTML = '<i class="bi bi-info-circle" style="font-size:.8rem;"></i>';
        th.appendChild(b);
    });
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.gsc-colinfo'); if (!b) { return; }
        e.stopPropagation();
        var h = colHelp[b.dataset.key];
        Swal.fire({ title: h[0], html: '<div style="text-align:left;font-size:.95rem;">' + h[1] + '</div>', icon: 'info', confirmButtonText: 'Entendido' });
    });
    var colsAll = document.getElementById('gsc-cols-info');
    if (colsAll) colsAll.addEventListener('click', function () {
        var order = ['dominio', 'tenant', 'estado', 'clics', 'impresiones', 'ctr', 'posición', 'vs.', 'última'];
        Swal.fire({ title: 'Qué significa cada columna', width: 720, confirmButtonText: 'Entendido',
            html: '<ul style="text-align:left;font-size:.92rem;padding-left:1.1rem;margin:0;">' + order.map(function (k) { return '<li class="mb-2"><strong>' + colHelp[k][0] + '</strong>: ' + colHelp[k][1] + '</li>'; }).join('') + '</ul><div class="text-muted mt-2" style="font-size:.82rem;text-align:left;">Haz clic en el título de una columna para ordenar por ella.</div>' });
    });

    // ===== Dar de baja (conservando los datos) y reactivar =====
    function propertyAction(action, id, password) {
        return fetch(@json($baseUrl . '/property/') + action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ property_id: id, confirm_password: password || '', _csrf: token }) })
            .then(function (r) { return r.json(); });
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('.gsc-archive'), r = e.target.closest('.gsc-restore');
        if (a) {
            Swal.fire({ title: 'Dar de baja ' + a.dataset.domain, icon: 'question', showCancelButton: true, confirmButtonText: 'Dar de baja', cancelButtonText: 'Cancelar',
                html: 'Dejará de descargarse y de mostrarse (también al tenant), pero <strong>sus datos se conservan</strong>. Podrás reactivarlo cuando quieras, por ejemplo si el dominio vuelve a ser tuyo. Confirma con tu contraseña de administrador.',
                input: 'password', inputPlaceholder: 'Contraseña de administrador', inputAttributes: { autocomplete: 'current-password' },
                inputValidator: function (v) { return v ? undefined : 'Escribe tu contraseña'; } })
            .then(function (x) { if (x.isConfirmed) { propertyAction('archive', a.dataset.property, x.value).then(function (res) { if (res.ok) { location.reload(); } else { Swal.fire({ icon: 'error', title: 'No se pudo', text: res.message }); } }); } });
        }
        if (r) { propertyAction('restore', r.dataset.property).then(function (res) { if (res.ok) { location.reload(); } }); }
    });

    // ===== «Dar de alta todos en SEO»: cola en el servidor, con progreso en vivo, parar y reanudar =====
    var bulkUrl = @json($baseUrl . '/bulk/');
    var bulkCard = document.getElementById('gsc-bulk-card');
    if (bulkCard) {
        var bar = document.getElementById('gsc-bulk-bar'), title = document.getElementById('gsc-bulk-title'), sum = document.getElementById('gsc-bulk-sum'),
            spin = document.getElementById('gsc-bulk-spin'), stopB = document.getElementById('gsc-bulk-stop'), resumeB = document.getElementById('gsc-bulk-resume'),
            closeB = document.getElementById('gsc-bulk-close'), driving = false, anyVerified = false;
        var starters = document.querySelectorAll('.gsc-bulk-start');
        function post(action) {
            return fetch(bulkUrl + action, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ _csrf: token }) }).then(function (r) { return r.json(); });
        }
        function rowOf(d) { return document.querySelector('li[data-domain="' + d + '"]'); }
        function refreshCounts() {
            document.querySelectorAll('[data-gsc-card]').forEach(function (c) {
                var n = c.querySelectorAll('li[data-domain]').length;
                c.querySelector('.gsc-card-count').textContent = n;
                if (!n) { c.remove(); }
            });
        }
        var tick = null;
        function mmss(n) { n = Math.max(0, Math.round(n)); return Math.floor(n / 60) + ':' + ('0' + (n % 60)).slice(-2); }
        function paint(st) {
            bulkCard.style.display = '';
            var nowEl = document.getElementById('gsc-bulk-now'), cur = null, nextWait = null;
            st.items.forEach(function (it) {
                var li = rowOf(it.domain); if (!li) { return; }
                var msg = li.querySelector('.gsc-verify-msg'), btn = li.querySelector('.gsc-verify');
                li.style.background = '';
                li._wait = null;
                if (it.state === 'working') {
                    cur = it;
                    li.style.background = 'rgba(26,115,232,.08)';
                    msg.className = 'gsc-verify-msg text-primary fw-bold'; msg.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Verificando ahora…'; btn.disabled = true;
                } else if (it.state === 'queued') {
                    msg.className = 'gsc-verify-msg text-muted'; msg.textContent = st.status === 'running' ? 'En cola · puesto ' + it.position : 'En cola (detenido)'; btn.disabled = st.status === 'running';
                } else if (it.state === 'waiting') {
                    li._wait = { until: Date.now() + it.next_in * 1000, tries: it.tries };
                    if (nextWait === null || it.next_in < nextWait.in) { nextWait = { in: it.next_in, domain: it.domain }; }
                    msg.className = 'gsc-verify-msg text-warning'; btn.disabled = true;
                } else if (it.state === 'verified') {
                    anyVerified = true;
                    if (!li._gone) { li._gone = true; li.style.transition = 'opacity .6s'; msg.className = 'gsc-verify-msg text-success'; msg.textContent = '✓ Dado de alta en SEO'; btn.style.display = 'none'; li.style.opacity = .55;
                        setTimeout(function () { li.remove(); refreshCounts(); }, 1800); }
                } else {
                    var cls = { pending: 'text-warning', manual: 'text-secondary', error: 'text-danger' }[it.state] || 'text-muted';
                    msg.className = 'gsc-verify-msg ' + cls; msg.textContent = it.message; btn.disabled = false; btn.textContent = it.state === 'pending' ? 'Reintentar ahora' : 'Dar de alta en SEO';
                }
            });
            var running = st.status === 'running';
            bar.style.width = (st.total ? Math.round(st.finished * 100 / st.total) : 100) + '%';
            spin.style.display = running ? '' : 'none';
            stopB.style.display = running ? '' : 'none';
            resumeB.style.display = st.status === 'stopped' ? '' : 'none';
            closeB.style.display = running ? 'none' : '';
            title.textContent = running ? 'Dando de alta en SEO… ' + st.finished + ' de ' + st.total : (st.status === 'stopped' ? 'Detenido: ' + st.finished + ' de ' + st.total : 'Terminado');
            // Qué está haciendo ahora mismo
            if (!running) { nowEl.innerHTML = ''; }
            else if (cur) { nowEl.innerHTML = '<span class="spinner-border spinner-border-sm text-primary me-2" aria-hidden="true"></span>Ahora: verificando <strong>' + cur.domain + '</strong>' + (st.busy ? ' <span class="text-muted">(lo está procesando el servidor)</span>' : ''); }
            else if (nextWait) { nowEl.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Esperando a Google: próximo intento (<strong>' + nextWait.domain + '</strong>) en <span id="gsc-bulk-count">' + mmss(nextWait.in) + '</span>. Mientras tanto no hay nada que hacer.'; }
            else { nowEl.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary me-2" aria-hidden="true"></span>Preparando el siguiente dominio…'; }
            if (running && !tick) {
                tick = setInterval(function () {
                    document.querySelectorAll('li[data-domain]').forEach(function (li) {
                        if (li._wait) { li.querySelector('.gsc-verify-msg').textContent = 'Google aún no ve el TXT · reintento en ' + mmss((li._wait.until - Date.now()) / 1000) + ' (intento ' + li._wait.tries + '/6)'; }
                    });
                    var c = document.getElementById('gsc-bulk-count');
                    if (c && nextWait) { c.textContent = mmss(nextWait.in - (Date.now() - paintedAt) / 1000); }
                }, 1000);
            }
            if (!running && tick) { clearInterval(tick); tick = null; }
            paintedAt = Date.now();
            sum.textContent = st.counts.verified + ' dados de alta · ' + (st.counts.pending + st.counts.waiting) + ' pendientes de Google (el servidor los sigue reintentando) · ' + st.counts.manual + ' con TXT a mano · ' + st.counts.error + ' con error';
            starters.forEach(function (b) { b.disabled = running; });
        }
        var paintedAt = Date.now();
        function drive() {
            if (driving) { return; }
            driving = true;
            (function loop() {
                post('step').then(function (st) {
                    paint(st);
                    if (st.status === 'running') { setTimeout(loop, st.busy ? 1500 : (st.current === null && st.counts.waiting ? 3000 : 200)); }
                    else { driving = false; if (st.status === 'done' && anyVerified) { reloadTable(); } }
                }).catch(function () { driving = false; sum.textContent = 'Error de red: la cola sigue en el servidor; recarga para continuar.'; });
            })();
        }
        // Al terminar, la tabla de dominios se actualiza sin recargar la página
        function reloadTable() {
            fetch(location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html'), fresh = doc.getElementById('gsc-table'), cur = document.getElementById('gsc-table');
                if (fresh && cur) { cur.innerHTML = fresh.innerHTML; var evt = document.getElementById('gsc-filter-text'); if (evt) { evt.dispatchEvent(new Event('input')); } }
                else if (fresh && !cur) { location.reload(); }
            });
        }
        starters.forEach(function (b) { b.addEventListener('click', function () { anyVerified = false; fetch(bulkUrl + 'start', { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, body: new URLSearchParams({ _csrf: token, scope: b.dataset.scope || '' }) }).then(function (r) { return r.json(); }).then(function (st) { paint(st); drive(); }); }); });
        stopB.addEventListener('click', function () { post('stop').then(paint); });
        resumeB.addEventListener('click', function () { post('resume').then(function (st) { paint(st); drive(); }); });
        closeB.addEventListener('click', function () { bulkCard.style.display = 'none'; });
        // Si la cola estaba en marcha (o detenida) al abrir la página, se retoma el progreso
        post('status').then(function (st) { if (st.total && (st.status === 'running' || st.status === 'stopped')) { paint(st); if (st.status === 'running') { drive(); } } });
    }
})();
</script>
@endpush
