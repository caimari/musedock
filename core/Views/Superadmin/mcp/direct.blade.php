@extends('layouts.app')
@section('title', $title)
@section('content')
<style>
.mcp-access-sites { display:block;min-width:0;width:100%;box-sizing:border-box;padding:18px;border:1px solid #e2e8f0;border-radius:10px;background:#fafbfd; }
.mcp-access-sites > h4 { float:none!important;display:block;width:auto;margin:0 0 14px;text-align:left; }
.mcp-access-sites [data-mcp-sites-summary] { display:flex!important;flex-direction:row;flex-wrap:wrap;justify-content:flex-start;align-items:center;gap:8px;float:none!important;clear:both;width:100%;margin:0 0 14px; }
.mcp-access-sites .mcp-site-chip { display:inline-flex;align-items:center;gap:10px;max-width:100%;padding:7px 12px;border:1px solid #d8e2f0;border-radius:20px;background:#edf3fc;color:#32557d;line-height:1.4;text-align:left;overflow-wrap:anywhere; }
.mcp-site-chip .mcp-site-remove { font-size:18px;line-height:1; }
.mcp-access-sites [data-mcp-sites-open] { display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;border-radius:7px; }

.mcp-sites-dialog { width:min(600px,calc(100vw - 32px));max-height:85vh;overflow:auto;border:0;border-radius:14px;padding:24px;box-shadow:0 15px 60px #0003; }
.mcp-sites-dialog::backdrop { background:#14243b77; }
.mcp-sites-list { max-height:45vh;overflow:auto; }
.mcp-site-choice { display:flex!important;align-items:center;gap:12px;padding:10px 0;margin:0!important; }
.mcp-site-choice[hidden] { display:none!important; }
.mcp-site-choice input { position:static!important;float:none!important;margin:0!important;flex:0 0 18px;width:18px;height:18px; }
.mcp-site-choice span { overflow-wrap:anywhere; }

.mcp-settings { max-width: 1500px; }
.mcp-settings > h1 { margin-bottom: 18px; font-weight: 600; }
.mcp-settings > .nav { gap: 6px; margin-bottom: 22px !important; }
.mcp-settings .nav-link { border-radius: 9px; }
.mcp-settings > p { line-height: 1.6; margin-bottom: 22px; }
.mcp-settings > .card { border: 1px solid #e5eaf1; border-radius: 12px; margin-bottom: 22px !important; box-shadow: 0 3px 14px rgba(30, 50, 80, .035); overflow: hidden; }
.mcp-settings .card-body { padding: 24px; }
.mcp-settings .card-body > h2 { margin-bottom: 14px; font-weight: 600; }
.mcp-settings .card-header { padding: 18px 24px; background: #fff; cursor: pointer; font-weight: 500; }
.mcp-settings details[open] > .card-header { border-bottom: 1px solid #e5eaf1; }
.mcp-settings .form-check { line-height: 1.6; margin-bottom: 8px; }
.mcp-settings .input-group { margin: 18px 0 22px; }
.mcp-settings .input-group input[readonly] { background: #f6f8fb; }
.mcp-settings .mcp-activation-notice { padding: 18px 20px; margin: 0 0 26px; border: 1px solid #dce6f5; border-left: 4px solid #3b7ddd; border-radius: 10px; background: #edf3fc; color: #40536d; line-height: 1.6; }
.mcp-settings .mcp-activation-notice span { display: block; margin-top: 4px; }
.mcp-settings .mcp-status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .85em; font-weight: 600; background: #eef1f5; color: #596579; }
.mcp-settings .mcp-status.is-enabled { background: #e7f5ec; color: #247445; }
.mcp-info-dialog { border: 0; border-radius: 14px; padding: 26px; width: min(560px, calc(100vw - 32px)); color: #40536d; box-shadow: 0 16px 60px rgba(20,35,60,.25); }
.mcp-info-dialog::backdrop { background: rgba(20,35,60,.45); }
.mcp-info-dialog p { line-height: 1.65; }
.mcp-settings .mcp-consents-heading { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5eaf1; }
@media (max-width: 576px) { .mcp-settings.container-fluid { padding: 16px !important; } .mcp-settings .card-body, .mcp-settings .card-header { padding: 18px; } .mcp-settings .nav-link { padding: 10px 12px; } }
</style>
@php
  $revokedClients = array_values(array_filter($clients, fn($client) => empty($client["enabled"])));
  $clients = array_values(array_filter($clients, fn($client) => !empty($client["enabled"])));
  $isPluginConnection = str_starts_with($resource['id'], 'plugin:');
  $groupNames = [];
  foreach ($groups as $editorialGroup) $groupNames[(int)$editorialGroup['id']] = $editorialGroup['name'];
  $clientSites = array_values(array_filter($sites, function ($site) use ($resource, $groupNames) {
      return in_array((int)$site['id'], $resource['owned_sites'], true) && isset($groupNames[(int)$site['group_id']]);
  }));
@endphp
<div class="row g-4">
  <div class="col-12 col-xl-9">
<div class="mcp-settings">
  <h1 class="h3"><i class="bi bi-robot me-2"></i>(MCP) Conexiones IA</h1>
  @if(!$tenantMode)
  <nav class="nav nav-pills mb-3">
    @foreach($resourceOptions as $key=>$label)
      <div class="d-flex align-items-center"><a class="nav-link {{ $resource['id']===$key ? 'active' : '' }}" href="{{ $base }}?resource={{ urlencode($key) }}">{{ $label }}</a><button type="button" class="btn btn-link px-2" data-mcp-info="{{ str_replace(':','-',$key) }}" aria-label="Información sobre {{ $label }}" title="Qué permite esta conexión"><i class="bi bi-info-circle" aria-hidden="true"></i></button></div>
      <dialog id="mcp-info-{{ str_replace(':','-',$key) }}" class="mcp-info-dialog" aria-labelledby="mcp-info-title-{{ str_replace(':','-',$key) }}">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0" id="mcp-info-title-{{ str_replace(':','-',$key) }}">{{ $label }}</h2><button type="button" class="btn-close" data-mcp-close aria-label="Cerrar"></button></div>
        @if($key==='main')
          <p>Gestiona exclusivamente el blog del dominio principal: artículos, categorías, etiquetas, medios, comentarios y traducciones. No accede al contenido de otros tenants.</p>
        @elseif($key==='plugin:cross-publisher')
          <p>Es la conexión recomendada para vuestro grupo editorial. Gestiona el blog de los sitios propios que marques y que estén en los grupos autorizados para cada cliente.</p>
          <p>También copia artículos entre esos sitios como borradores. La copia no activa sincronización ni traducción automática; puedes preparar las traducciones desde el asistente y guardarlas con sus herramientas.</p>
        @else
          <p>Consulta las noticias originales, fuentes, agrupaciones y análisis IA que ya guarda News Aggregator en los sitios autorizados. Estas consultas no generan costes de IA. Convertir una noticia en borrador y publicar siguen sujetos a los permisos y aprobaciones configurados.</p>
          <p>La conexión no ejecuta la captura de fuentes ni la reescritura automática del plugin.</p>
        @endif
        <p>Las herramientas dependen de los permisos del cliente. Publicar y aprobar desde MCP requieren habilitación explícita. Sin aprobación remota, los cambios se revisan y aprueban en el CMS.</p>
        <button type="button" class="btn btn-primary" data-mcp-close>Entendido</button>
      </dialog>
    @endforeach
  </nav>
  @else
    <p><a href="{{ '/' . admin_path() . '/mcp' }}">Volver a las conexiones del sitio</a></p>
  @endif
  @if($tenantMode)
    <p class="text-muted">Al activar esta modalidad, las conexiones anteriores de este sitio deberán reconectarse con sus nuevos clientes OAuth.</p>
  @endif
  <div class="mb-3">
    <label for="mcp-url" class="form-label fw-semibold">URL del servidor MCP</label>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <input id="mcp-url" class="form-control" style="flex:1 1 18rem;min-width:0;width:auto" type="url" readonly spellcheck="false" value="{{ $resource['url'] }}" aria-describedby="mcp-url-help">
      <button class="btn btn-outline-primary" type="button" data-copy="mcp-url">Copiar URL</button>
    </div>
    <p id="mcp-url-help" class="small text-muted mt-2 mb-0">Pega esta dirección completa en ChatGPT, Claude u otro cliente MCP. Corresponde a la conexión seleccionada.</p>
  </div>
  <p>Conecta un asistente para gestionar el blog con los permisos que autorices en <strong>{{ $resource['name'] }}</strong>.</p>
  @if($error)<div class="alert alert-danger">{{ $error }}</div>@endif
  <p class="small text-muted">Servicio global: <strong>{{ $deploymentEnabled ? 'Activo' : 'Desactivado' }}</strong> · Esta conexión: <strong>{{ $resource['enabled'] ? 'Habilitada' : 'Desactivada' }}</strong>. Cada acceso conserva sus propios grupos, dominios y permisos.</p>
  @if(!$resource['enabled'])
    <div class="alert alert-warning" role="status"><strong>Esta conexión está desactivada: el proveedor no podrá detectar OAuth.</strong> Crear credenciales no habilita la conexión. Puedes activarla aquí sin salir ni perder el ID o el secreto.
      @if($deploymentEnabled)
      <form method="post" action="{{ $back }}" data-mcp-enable class="mt-2">{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="action" value="enable_connection"><button type="submit" class="btn btn-primary">Habilitar esta conexión sin salir</button><span class="d-block small mt-2" role="status" data-mcp-enable-status></span></form>
      @else
      <p class="mb-0">El administrador principal debe activar primero el servicio global en «Servicio y sitios».</p>
      @endif
    </div>
  @endif
  <nav class="nav nav-pills gap-2 mb-3" aria-label="Secciones de esta conexión">
    <button type="button" class="nav-link active" data-mcp-tab="access" aria-pressed="true">Accesos ({{ count($clients) }})</button>
    <button type="button" class="nav-link" data-mcp-tab="connection" aria-pressed="false">Servicio y sitios</button>
    <button type="button" class="nav-link" data-mcp-tab="requests" aria-pressed="false">Solicitudes ({{ count($requests) }})</button>
    <button type="button" class="nav-link" data-mcp-tab="images" aria-pressed="false">Imágenes</button>
    <button type="button" class="nav-link" data-mcp-tab="help" aria-pressed="false">Ayuda</button>
  </nav>
  <section data-mcp-panel="connection">
  <p class="small text-muted">Los accesos habilitados pueden recibir autorización OAuth. «Revocar acceso» deshabilita el cliente y todas sus autorizaciones y lo mueve al historial. Renovar el secreto conserva el acceso.</p>

  @if(!empty($canManageDeployment))
  <div class="card mb-3"><div class="card-body">
    <h2 class="h5">Servicio global MCP</h2>
    <p>Estado: <strong>{{ $deploymentEnabled ? 'Activo' : 'Desactivado' }}</strong>. Este interruptor permite las conexiones públicas HTTPS del CMS. Cada conexión mantiene sus propios sitios, clientes y permisos.</p>
    <form method="post" action="{{ $back }}">
      {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="action" value="deployment">
      <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="deployment_enabled" {{ $deploymentEnabled ? 'checked' : '' }}> Activar el servicio global MCP</label>
      <button class="btn btn-primary" type="submit">Guardar servicio global</button>
    </form>
  </div></div>
  @endif
  <div class="border rounded p-3 mb-3">
    <strong>Cómo se combinan los permisos</strong>
    <p class="mb-1">Estos interruptores son los límites de toda la conexión. Al guardar aprobaciones automáticas se activan aquí los permisos de conexión necesarios. Los demás accesos siguen necesitando sus propios permisos y aprobaciones.</p>
    <ul class="small mb-0"><li>«Permitir solicitudes de cambios» debe estar activo para escribir, incluso con aprobación automática.</li><li>«Permitir publicar» debe estar activo para publicar o programar, además del permiso del acceso.</li><li>«Permitir aprobar solicitudes desde MCP» permite al asistente aprobar explícitamente una solicitud. No es necesario para las aprobaciones automáticas configuradas por acceso.</li><li>El requisito de portada sigue bloqueando publicaciones sin imagen aunque exista aprobación automática.</li></ul>
  </div>
  @if(!$deploymentEnabled)<div class="mcp-activation-notice" role="status"><strong>El servicio global MCP está desactivado.</strong><span>Habilitar esta conexión guarda sus ajustes, pero no activa el servicio global. El administrador principal debe activarlo en esta página.</span></div>@endif
  @if(!$pluginInstalled)<div class="alert alert-warning">Este plugin debe estar instalado y activo para abrir su conexión MCP.</div>@endif
  <div class="card mb-3"><div class="card-body">
    <h2 class="h5">{{ $isPluginConnection ? 'Paso 1 · Configurar la conexión y sus sitios disponibles' : 'Conexión directa HTTPS' }}</h2>
    @if($isPluginConnection)<p class="text-muted">Primero define los sitios propios que este plugin podrá gestionar por MCP. Después podrás dar a cada asistente acceso a todos o solo a algunos de ellos.</p>@endif
    <p>Servicio: <span class="mcp-status {{ $deploymentEnabled && $resource['enabled'] && $pluginInstalled ? 'is-enabled' : '' }}">{{ $deploymentEnabled && $resource['enabled'] && $pluginInstalled ? 'Habilitado' : 'Desactivado' }}</span>. La autorización de cada cliente se muestra por separado.</p>

    <form method="post" action="{{ $back }}">
      {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="action" value="configure">
      <label class="form-check"><input class="form-check-input" type="checkbox" name="enabled" {{ $resource['enabled'] ? 'checked' : '' }}> Habilitar esta conexión</label>
      <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="allow_drafts" {{ $resource['allow_drafts'] ? 'checked' : '' }}> Permitir solicitudes de cambios (borradores, taxonomías, medios y otras operaciones)</label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="allow_publishing" {{ !empty($resource['allow_publishing']) ? 'checked' : '' }}> Permitir publicar, programar y editar artículos publicados</label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="require_featured_image" {{ ($resource['require_featured_image'] ?? true) ? 'checked' : '' }}> Exigir imagen destacada creada y asignada antes de publicar por MCP</label>
      <p class="small text-muted">Activado por defecto. Permite guardar borradores; bloquea publicar o programar hasta que tengan una imagen del gestor de medios de su sitio. No publica automáticamente al terminar la generación.</p>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="allow_mcp_approval" {{ !empty($resource['allow_mcp_approval']) ? 'checked' : '' }}> Permitir aprobar solicitudes desde MCP</label>
      <p class="small text-muted mb-3">La aprobación desde MCP permite ejecutar cambios desde el asistente. Cada cliente necesita además el permiso «Aprobar solicitudes propias desde MCP». Si está desmarcado, apruebas en esta pantalla.</p>
      @if(count($sites))
      <fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto h6 px-2">Sitios disponibles para conexiones MCP del plugin</legend>
        <p class="small text-muted">Aquí solo aparecen sitios activos que pertenecen a los grupos editoriales del plugin. Marca los sitios propios que quieres habilitar para MCP y guarda esta sección; después podrás asignarlos a cada asistente en el paso 2.</p>
        <p class="small"><a href="/musedock/cross-publisher/groups" target="_blank" rel="noopener noreferrer">Ver y gestionar los grupos editoriales <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></p>
        @foreach($sites as $site)
          <div class="d-flex align-items-start gap-2"><div class="flex-grow-1"><label class="form-check"><input class="form-check-input" type="checkbox" name="owned_sites[]" value="{{ $site['id'] }}" {{ in_array((int)$site['id'],$resource['owned_sites'],true) ? 'checked' : '' }}> {{ $site['name'] }} — {{ $site['domain'] }} <small class="d-block text-muted">Grupo: {{ $groupNames[(int)$site['group_id']] ?? 'Sin grupo editorial — no disponible para asistentes' }}</small></label></div><a class="btn btn-sm btn-outline-secondary" href="https://{{ $site['domain'] }}" target="_blank" rel="noopener noreferrer" title="Abrir {{ $site['domain'] }} en otra pestaña" aria-label="Abrir {{ $site['domain'] }} en otra pestaña"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></div>
        @endforeach
      </fieldset>
      @endif
      <p class="small text-muted">Guardar cambia la autorización del recurso: los clientes deberán volver a dar su consentimiento.</p>
      <button class="btn btn-primary">{{ $isPluginConnection ? 'Guardar conexión y sitios disponibles' : 'Guardar' }}</button>
    </form>
  </div></div>
  </section>
  <section data-mcp-panel="connection">
  @if(!empty($taxonomyPolicy))
  <details class="card mb-3"><summary class="card-header">Política editorial de categorías y etiquetas por sitio</summary><div class="card-body">
    <form method="get" action="{{ $base }}#mcp-connection" class="d-flex flex-wrap gap-2 mb-3"><input type="hidden" name="resource" value="{{ $resource['id'] }}"><label for="taxonomy-policy-site">Sitio</label><select id="taxonomy-policy-site" name="taxonomy_site" class="form-select">
      @foreach(($imageSites ?? []) as $site)
        <option value="{{ $site['id'] }}" {{ (int)$site['id'] === $taxonomySite ? 'selected' : '' }}>{{ $site['domain'] }}</option>
      @endforeach
    </select><button class="btn btn-outline-primary">Consultar política</button></form>
    <form method="post" action="{{ $back }}">{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="action" value="taxonomy_policy"><input type="hidden" name="policy_site" value="{{ $taxonomySite }}">
      <p class="small text-muted">Se reutilizan términos existentes. Estos límites afectan a términos nuevos por operación. La aprobación automática se activa por separado en cada acceso.</p>
      @foreach(['categories'=>'Categorías','tags'=>'Etiquetas'] as $kind=>$label)
        <div class="border rounded p-3 mb-3"><h4 class="h6">{{ $label }}</h4><label class="form-check"><input class="form-check-input" type="checkbox" name="allow_{{ $kind }}" {{ $taxonomyPolicy['allow_'.$kind] ? 'checked' : '' }}> Permitir crear términos nuevos por MCP</label>
        <label class="form-label">Máximo de términos nuevos por operación</label><input class="form-control mb-2" type="number" min="0" max="20" name="max_{{ $kind }}" value="{{ $taxonomyPolicy['max_'.$kind] }}">
        <label class="form-label">Nombres permitidos, uno por línea (vacío: sin lista cerrada)</label><textarea class="form-control" name="allowed_{{ $kind }}" rows="3">{{ implode("
",$taxonomyPolicy['allowed_'.$kind]) }}</textarea></div>
      @endforeach
      <label class="form-label">Equivalencias de nombres (JSON: nombre alternativo → nombre preferido)</label><textarea class="form-control mb-3" name="aliases" rows="3">{{ json_encode((object)$taxonomyPolicy['aliases'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</textarea>
      <button class="btn btn-primary">Guardar política editorial</button>
    </form>
  </div></details>
  @endif
  </section>
  <section data-mcp-panel="access">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Accesos independientes</h2><button type="button" class="btn btn-primary" id="mcp-new-access">+ Nuevo acceso MCP</button></div>
  @if($newClient)
    @if(!empty($newClient['rotated']))<div class="alert alert-info">Secreto renovado. Copia el nuevo valor y actualízalo en ChatGPT. El secreto anterior seguirá funcionando durante 24 horas o hasta que pulses «Finalizar renovación». Se conservan el ID, los permisos, los consentimientos y los tokens existentes.</div>@endif
  <div id="new-credentials" class="card border-success mb-3"><div class="card-body">
    <h2 class="h5">Credenciales nuevas</h2><p>Cópialas ahora. El secreto se muestra una sola vez.</p>
    <label for="client-id">ID del cliente</label><div class="input-group mb-2"><input id="client-id" readonly class="form-control" value="{{ $newClient['id'] }}"><button type="button" class="btn btn-outline-primary" data-copy="client-id">Copiar</button></div>
    <label for="client-secret">Secreto</label><div class="input-group mb-2"><input type="password" id="client-secret" readonly autocomplete="off" class="form-control" value="{{ $newClient['secret'] }}"><button type="button" class="btn btn-outline-secondary" id="show-secret">Mostrar/ocultar</button><button type="button" class="btn btn-outline-primary" data-copy="client-secret">Copiar</button></div>
    <div class="border rounded p-3 mb-3">
      <h3 class="h6">Dónde pegar estas credenciales en ChatGPT</h3>
      <ul class="mb-2">
        <li><strong>URL del servidor:</strong> <code>{{ $resource['url'] }}</code>.</li>
        <li><strong>Autenticación:</strong> OAuth. En «Método de registro», elige «Cliente OAuth definido por el usuario».</li>
        <li><strong>ID de cliente de OAuth:</strong> pega el «ID del cliente» de arriba.</li>
        <li><strong>Secreto del cliente OAuth:</strong> pega el «Secreto» de arriba.</li>
        <li><strong>Método de autenticación en el punto de acceso de tokens:</strong> selecciona <code>client_secret_basic</code> (Basic).</li>
        <li><strong>URL de devolución de la llamada:</strong> la proporciona ChatGPT. Debe coincidir exactamente con el callback del acceso en MuseDock; no es la URL del servidor MCP.</li>
      </ul>
      <p class="small mb-0">Si ChatGPT muestra un callback distinto al predefinido, al crear el acceso elige «Personalizado» y pega esa URL HTTPS completa. El secreto no es tu contraseña de MuseDock ni una API key de OpenAI.</p>
    </div>
    <button type="button" class="btn btn-success" id="dismiss-credentials">Ya las he guardado</button>
  </div></div>
  @endif
  <dialog id="mcp-create-access" class="mcp-info-dialog" aria-labelledby="mcp-create-title"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5" id="mcp-create-title">{{ $isPluginConnection ? 'Paso 2 · Crear un acceso independiente' : 'Crear cliente para ChatGPT, Claude u otro asistente' }}</h2><button type="button" class="btn-close" data-mcp-close aria-label="Cerrar"></button></div>

    @if($isPluginConnection)<p class="text-muted">Crea credenciales independientes para ChatGPT, Claude u otro asistente. Aquí decides qué puede hacer y a cuáles de los sitios guardados en el paso 1 podrá acceder.</p>@endif
    <form method="post" action="{{ $back }}">
      {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="action" value="create_client">
      <label class="form-label">Nombre del acceso</label><input name="name" class="form-control mb-2" required maxlength="100" placeholder="ChatGPT · Grupo A / Claude · Grupo B">
      <label for="mcp-provider" class="form-label">Asistente</label>
      <select id="mcp-provider" name="provider" class="form-select mb-2"><option value="chatgpt">ChatGPT — dirección de retorno automática</option><option value="claude">Claude — dirección de retorno automática</option><option value="custom">Personalizado — otro asistente o callback distinto</option></select>
      <label for="mcp-callback" class="form-label">Dirección de retorno OAuth (callback)</label><input id="mcp-callback" name="callback" type="url" class="form-control mb-2" readonly maxlength="500" value="https://chatgpt.com/connector_platform_oauth_redirect" placeholder="Pega la URL HTTPS exacta del asistente">
      <p class="small text-muted">Comprueba la «URL de devolución de la llamada» que muestra ChatGPT. Si no coincide con la automática, selecciona «Personalizado» y pega aquí la URL exacta antes de crear el acceso. En ChatGPT usa OAuth, cliente definido por el usuario y <code>client_secret_basic</code> en el selector de autenticación del token.</p>
      <p class="small text-muted mb-3">Es la dirección del asistente a la que volverás después de autorizar el acceso en este CMS. ChatGPT y Claude se rellenan automáticamente. Si el asistente muestra otra URL, elige Personalizado y copia esa dirección exacta; en ese modo no puede quedar vacía. Cada asistente necesita sus propias credenciales.</p>
      <fieldset class="mb-3"><legend class="h6">Permisos de este acceso</legend>
      <div class="d-flex flex-wrap gap-2 mb-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-mcp-permission-preset="read">Solo lectura</button><button type="button" class="btn btn-sm btn-outline-secondary" data-mcp-permission-preset="all">Todos los disponibles</button></div><p class="small text-muted">La selección solo se aplica al crear este acceso; el servicio mantiene sus límites y sitios autorizados.</p>
      @foreach($scopes as $scope)
        <label class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" {{ $loop->first ? 'checked' : '' }}> {{ \Screenart\Musedock\Services\Mcp\Direct\Policy::label($scope) }} <small class="text-muted">({{ $scope }})</small></label>
      @endforeach
      </fieldset>
      @if($isPluginConnection)
      <fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto h6 px-2">Acceso de este asistente</legend>
        @if(count($clientSites))
          <label class="form-label">1. Selecciona los grupos editoriales que puede utilizar</label>
          @foreach($groups as $group)<label class="form-check"><input class="form-check-input" type="checkbox" name="groups[]" value="{{ $group['id'] }}" data-mcp-group> {{ $group['name'] }}</label>@endforeach
          <label class="form-label mt-3">2. Marca los sitios concretos a los que tendrá acceso</label>
          <p class="small text-muted">Seleccionar un grupo no marca sus sitios automáticamente. Debes elegir también los sitios. Solo se admiten sitios del paso 1 que pertenezcan a un grupo seleccionado.</p>
          <p id="mcp-group-hint" class="small text-muted" role="status">Selecciona un grupo para poder marcar sus sitios.</p>
          @foreach($clientSites as $site)
            <div class="d-flex align-items-start gap-2"><div class="flex-grow-1"><label class="form-check"><input class="form-check-input" type="checkbox" name="sites[]" value="{{ $site['id'] }}" data-mcp-site-group="{{ $site['group_id'] }}"> {{ $site['domain'] }} <small class="d-block text-muted">Grupo: {{ $groupNames[(int)$site['group_id']] }}</small></label></div><a class="btn btn-sm btn-outline-secondary" href="https://{{ $site['domain'] }}" target="_blank" rel="noopener noreferrer" title="Abrir {{ $site['domain'] }} en otra pestaña" aria-label="Abrir {{ $site['domain'] }} en otra pestaña"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></div>
          @endforeach
        @else
          <div class="alert alert-info mb-0" role="status"><strong>Todavía no hay sitios disponibles para este asistente.</strong><br>En el paso 1, marca al menos un sitio propio de tus grupos editoriales y pulsa «Guardar conexión y sitios disponibles». Después vuelve aquí para seleccionar el grupo y los sitios del asistente.<br><button type="button" class="btn btn-outline-primary mt-3" data-mcp-go-connection>Ir a Servicio y sitios</button></div>
        @endif
      </fieldset>
      @endif
        <div class="border rounded p-3 mb-3">
          <h3 class="h6">Aprobación automática de este acceso</h3>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_posts" > Aprobar automáticamente artículos, publicación, programación y asignación de portada</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_images" > Aprobar automáticamente generaciones de imágenes</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_taxonomy" > Aprobar automáticamente creación y edición de categorías y etiquetas</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_translations" > Aprobar automáticamente creación y edición de traducciones</label>
          <p class="small text-muted">Las traducciones requieren su permiso específico; modificar traducciones de artículos publicados exige además permiso de publicación.</p>
          <p class="small text-muted mb-0">Solo para este acceso y sus sitios autorizados. Al guardar se habilitan esta conexión y las solicitudes de cambios; si autorizas publicación, también se habilita publicar en «Servicio y sitios». Se mantienen los permisos del resto de accesos, las cuotas y el requisito de portada. Generar imágenes puede consumir saldo. Se aplica a nuevas solicitudes; las pendientes conservan su aprobación manual.</p>
        </div>
      <details class="mt-3"><summary>Redes permitidas (opcional)</summary><label class="form-label">IPs o CIDR de salida fiables del cliente</label><textarea name="networks" class="form-control" rows="3"></textarea><p class="small text-muted">Por defecto, déjalo vacío: se exige OAuth válido desde cualquier IP. No desactiva el firewall ni abre puertos nuevos; esta conexión utiliza el HTTPS de tu web (443). Rellénalo únicamente si conoces las IPs o rangos de salida del asistente: una lista incorrecta impedirá conectar. Se aplica a MCP y al intercambio de tokens, no al consentimiento del navegador.</p></details>
      <button class="btn btn-primary mt-3" {{ $isPluginConnection && !count($clientSites) ? 'disabled' : '' }}>Crear credenciales del asistente</button>
    </form>
  </div></dialog>
  <div class="card mb-3"><div class="card-body"><h2 class="h5">Clientes y permisos</h2>
    @if($isPluginConnection)<p class="text-muted">Puedes crear varios accesos independientes con esta misma URL: por ejemplo, «ChatGPT · Grupo A» y «Claude · Grupo B». Selecciona los grupos y dominios de cada cliente al crearlo; sus credenciales solo permiten acceder a esa selección.</p>@endif
    @forelse($clients as $client)
    <div class="border rounded p-3 mb-3"><h3 class="h6">{{ $client['name'] }} — {{ $client['enabled'] ? 'Habilitado' : 'Revocado' }}</h3><p class="small">ID: <code>{{ $client['id'] }}</code><br>Callback: {{ $client['callback'] }}<br>Creado: {{ !empty($client['created']) ? (new DateTimeImmutable('@'.(int)$client['created']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i:s') . ' (hora peninsular española)' : 'Fecha no disponible' }}</p>
      @php($hasClientConsent = count(array_filter($grants, fn($grant) => $grant['client'] === $client['id'] && !empty($grant['active']))) > 0)
      <p class="small text-muted">OAuth: {{ $hasClientConsent ? 'Con consentimiento registrado; sujeto a permisos vigentes' : 'Sin consentimiento activo: todavía no está conectado' }}</p>
      @if($isPluginConnection)
      <p class="small"><strong>Grupos:</strong>
        @foreach(($client['groups'] ?? []) as $groupId)<span class="badge bg-light text-dark">{{ $groupNames[(int)$groupId] ?? 'Grupo no disponible' }}</span> @endforeach
        <br><strong>Dominios:</strong>
        @foreach($sites as $site)@if(in_array((int)$site['id'], $client['sites'] ?? [], true))<span class="badge bg-light text-dark">{{ $site['domain'] }}</span> @endif @endforeach
      </p>
      @endif
      @if($client['enabled'])
      <form method="post" action="{{ $back }}" class="mb-3">
        {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="client" value="{{ $client['id'] }}">
        @if(($client['previous_secret_expires'] ?? 0) > time())
          <p class="small text-muted">Renovación pendiente: el secreto anterior vence el {{ date('d/m/Y H:i', $client['previous_secret_expires']) }} (hora del servidor). Actualiza el secreto en ChatGPT antes de finalizar.</p>
          <button class="btn btn-sm btn-outline-primary" name="action" value="finish_secret_rotation">Finalizar renovación e invalidar secreto anterior</button>
        @else
          <button class="btn btn-sm btn-outline-primary" name="action" value="rotate_secret">Renovar secreto sin revocar la conexión</button>
          <p class="small text-muted mt-2 mb-0">Mantiene el mismo cliente. Tendrás 24 horas para actualizar el secreto en ChatGPT. Los tokens OAuth se renuevan automáticamente.</p>
        @endif
      </form>
      @endif
      <details class="mb-3"><summary>Editar permisos de este acceso</summary>
      <form method="post" action="{{ $back }}" class="mb-2">
        {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="client" value="{{ $client['id'] }}"><input type="hidden" name="action" value="client_permissions">
        @if($isPluginConnection)
        <div class="mcp-access-sites" data-mcp-sites-editor>
          <h4 class="h6">Sitios de este acceso</h4>
          <input type="hidden" name="edit_sites" value="1">
          <div data-mcp-sites-summary class="d-flex flex-wrap gap-2 mb-3"></div>
          <button type="button" class="btn btn-sm btn-outline-primary mb-2" data-mcp-sites-open><i class="bi bi-plus-lg"></i> Añadir sitios</button>
          <dialog class="mcp-sites-dialog" aria-label="Seleccionar sitios del acceso">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><h3 class="h5 mb-0">Sitios autorizados del acceso</h3><button type="button" class="btn btn-sm btn-outline-secondary" data-mcp-sites-close aria-label="Cerrar">Cerrar</button></div>
            <input type="search" class="form-control mb-3" placeholder="Buscar dominio…" aria-label="Buscar dominio" data-mcp-sites-search>
            <div class="mcp-sites-list">
            @foreach($sites as $site)
              @if(in_array((int)$site['id'], $resource['owned_sites'] ?? [], true) && !empty($site['group_id']))
                <label class="mcp-site-choice"><input type="checkbox" name="sites[]" value="{{ $site['id'] }}" data-domain="{{ $site['domain'] }}" {{ in_array((int)$site['id'], $client['sites'] ?? [], true) ? 'checked' : '' }}><span>{{ $site['domain'] }}</span></label>
              @endif
            @endforeach
            </div>
            <p class="small text-muted mt-3">Aparecen los sitios autorizados en «Servicio y sitios». Para disponer de otros dominios, añádelos primero allí.</p>
            <button type="button" class="btn btn-primary" data-mcp-sites-close>Usar selección</button>
          </dialog>
          <p class="small text-muted mb-0">Guarda los cambios del acceso para aplicar la selección. No cambia las credenciales ni desconecta. Los grupos se ajustan a los sitios seleccionados.</p>
        </div>
        @endif
        <div class="border rounded p-3 mb-3">
          <h4 class="h6">Aprobación automática de este acceso</h4>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_posts" {{ !empty($client['auto_approve_posts']) ? 'checked' : '' }}> Aprobar automáticamente artículos, publicación, programación y asignación de portada</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_images" {{ !empty($client['auto_approve_images']) ? 'checked' : '' }}> Aprobar automáticamente generaciones de imágenes</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_taxonomy" {{ !empty($client['auto_approve_taxonomy']) ? 'checked' : '' }}> Aprobar automáticamente creación y edición de categorías y etiquetas</label>
          <label class="form-check"><input class="form-check-input" type="checkbox" name="auto_approve_translations" {{ !empty($client['auto_approve_translations']) ? 'checked' : '' }}> Aprobar automáticamente creación y edición de traducciones</label>
          <p class="small text-muted">Las traducciones requieren su permiso específico; modificar traducciones de artículos publicados exige además permiso de publicación.</p>
          <p class="small text-muted mb-0">Solo para este acceso y sus sitios autorizados. Al guardar se habilitan esta conexión y las solicitudes de cambios; si autorizas publicación, también se habilita publicar en «Servicio y sitios». Se mantienen los permisos del resto de accesos, las cuotas y el requisito de portada. Generar imágenes puede consumir saldo. Se aplica a nuevas solicitudes; las pendientes conservan su aprobación manual.</p>
        </div>
        @foreach($scopes as $scope)<label class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" {{ in_array($scope,$client['scopes'],true) ? 'checked' : '' }}> {{ \Screenart\Musedock\Services\Mcp\Direct\Policy::label($scope) }} <small class="text-muted">({{ $scope }})</small></label>@endforeach
        <button class="btn btn-sm btn-outline-primary mt-2">Guardar cambios del acceso</button>
        <p class="small text-muted">Solo cambiar los permisos OAuth exige nuevo consentimiento. Cambiar sitios o aprobación automática conserva la conexión.</p>
      </form>
      </details>
      <details class="mb-3"><summary>Autorizaciones OAuth de este acceso</summary>
        <p class="small text-muted mt-2">Estas autorizaciones pertenecen al mismo acceso. «Revocar acceso» las invalida todas y conserva el registro en el historial.</p>
        @forelse(array_filter($grants, fn($grant) => $grant['client'] === $client['id']) as $grant)
          <div class="border rounded p-2 mb-2">
            <span class="small">{{ !empty($grant['active']) && $grant['resource_revision']===$resource['revision'] && $grant['revision']===$client['revision'] ? 'Concedida; sujeta a permisos vigentes' : 'Revocada o pendiente de renovación' }}</span>

          </div>
        @empty
          <p class="small text-muted">Todavía no hay autorizaciones OAuth para este acceso.</p>
        @endforelse
      </details>
      <form method="post" action="{{ $back }}">{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="client" value="{{ $client['id'] }}"><button class="btn btn-sm btn-outline-danger" name="action" value="revoke_client">Revocar acceso</button></form>
    </div>
    @empty <p class="text-muted">No hay accesos activos.</p>@endforelse
    @if(count($revokedClients))
    <details class="mt-3 mb-3"><summary>Historial de accesos revocados ({{ count($revokedClients) }})</summary>
      <p class="small text-muted mt-2">Estos accesos ya no pueden conectarse. Se conservan para consulta y auditoría.</p>
      @foreach($revokedClients as $client)
        <div class="border rounded p-3 mb-2"><h3 class="h6">{{ $client['name'] }} — Revocado</h3>
          <p class="small mb-2">ID: <code>{{ $client['id'] }}</code><br>Callback: {{ $client['callback'] }}<br>Creado: {{ !empty($client['created']) ? (new DateTimeImmutable('@'.(int)$client['created']))->setTimezone(new DateTimeZone('Europe/Paris'))->format('d/m/Y H:i:s') . ' (hora peninsular española)' : 'Fecha no disponible' }}</p>
          @if($isPluginConnection)<p class="small mb-2"><strong>Grupos:</strong> @foreach(($client['groups'] ?? []) as $groupId)<span class="badge bg-light text-dark">{{ $groupNames[(int)$groupId] ?? 'Grupo no disponible' }}</span> @endforeach<br><strong>Dominios:</strong> @foreach($sites as $site)@if(in_array((int)$site['id'], $client['sites'] ?? [], true))<span class="badge bg-light text-dark">{{ $site['domain'] }}</span> @endif @endforeach</p>@endif
          <details><summary>Consultar permisos anteriores</summary><ul class="small mt-2">@foreach($client['scopes'] as $scope)<li>{{ \Screenart\Musedock\Services\Mcp\Direct\Policy::label($scope) }}</li>@endforeach</ul></details>
        </div>
      @endforeach
    </details>
    @endif
  </div></div>
  </section>
  <section data-mcp-panel="requests">
  <div class="card mb-3"><div class="card-body"><h2 class="h5">Cambios pendientes</h2>
    @if(count($requests))<button type="button" class="btn btn-success mb-3" id="mcp-approve-all">Aprobar todas en cola ({{ count($requests) }})</button><p class="small text-muted">Las aprobaciones se guardan en una cola interna y se ejecutan en segundo plano. El procesamiento puede tardar hasta un minuto en empezar.</p>@endif
    @forelse($requests as $request)
      <div class="border rounded p-3 mb-3"><p>Solicitud {{ $request['id'] }} · caduca en {{ max(0,$request['expires']-time()) }} segundos</p>
      <details><summary>Revisar el plan y el contenido</summary><pre class="text-wrap border p-2">{{ json_encode($request['payload']['plan'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
      <form method="post" action="{{ $back }}" class="mt-2" data-mcp-request-form>{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="request" value="{{ $request['id'] }}"><button class="btn btn-success" name="action" value="approve">Aprobar cambios</button> <button class="btn btn-outline-danger" name="action" value="reject">Rechazar</button></form></div>
    @empty <p class="text-muted">No hay cambios pendientes.</p>@endforelse
  </div></div>
  @if(!empty($recentOperations))
  <div class="card mb-3"><div class="card-body"><h2 class="h5">Estado de operaciones recientes</h2>
    @foreach($recentOperations as $operation)
      <p class="small"><code>{{ $operation['id'] }}</code> · {{ $operation['tool'] }} · <strong>{{ ['queued'=>'En cola', 'executing'=>'En ejecución o pendiente de verificar', 'failed'=>'Fallida', 'completed'=>'Completada'][$operation['status']] }}</strong>@if(!empty($operation['error'])) · {{ $operation['error'] }}@endif</p>
    @endforeach
    <p class="small text-muted">Si una operación permanece en ejecución, comprueba el gestor de medios y el artículo antes de solicitar otra generación. No se reintenta automáticamente.</p>
  </div></div>
  @endif
  </section>
  <section data-mcp-panel="images">
  <div class="card mb-3"><div class="card-body"><h2 class="h5">Generador e historial por sitio</h2>
    <form method="get" action="{{ $base }}#mcp-images" class="d-flex flex-wrap gap-2 align-items-end mb-3">
      <input type="hidden" name="resource" value="{{ $resource['id'] }}">
      <div><label class="form-label" for="mcp-image-site">Sitio</label><select id="mcp-image-site" name="image_site" class="form-select">@foreach(($imageSites ?? []) as $site)<option value="{{ $site['id'] }}" {{ (int)$site['id']===($imageSite ?? -1) ? 'selected' : '' }}>{{ $site['domain'] }}</option>@endforeach</select></div>
      <button class="btn btn-outline-primary">Consultar imágenes</button>
    </form>
    @if(!empty($imageError))<p class="text-muted">{{ $imageError }}</p>@endif
    @if(!empty($imageOptions))
      <p><strong>{{ $imageOptions['provider_name'] }}</strong> · {{ $imageOptions['model'] }}</p>
      <p class="small">Calidades: <strong>{{ implode(', ', $imageOptions['qualities']) }}</strong>. Tamaños habituales: {{ implode(', ', $imageOptions['sizes']) }}.</p>
      @if(!empty($imageOptions['custom_size_constraints']))<p class="small">También admite dimensiones entre {{ $imageOptions['custom_size_constraints']['minimum'] }} y {{ $imageOptions['custom_size_constraints']['maximum'] }} px, múltiplos de {{ $imageOptions['custom_size_constraints']['multiple_of'] }}.</p>@endif
      <p class="small text-muted">En ChatGPT, actualiza la conexión para descubrir las herramientas nuevas y abre una conversación nueva. Consulta <code>list_image_options</code> antes de generar y <code>list_image_prompts</code> para revisar estilos anteriores.</p>
    @endif
    @forelse(($imagePrompts ?? []) as $image)
      <div class="border rounded p-3 mb-2"><a href="{{ $image['url'] }}" target="_blank" rel="noopener noreferrer"><img src="{{ $image['url'] }}" alt="{{ $image['alt_text'] ?? $image['filename'] }}" loading="lazy" style="max-width:240px;width:100%;max-height:160px;object-fit:contain" class="d-block mb-2"></a><a href="{{ $image['url'] }}" target="_blank" rel="noopener noreferrer">{{ $image['filename'] }}</a>
        <p class="small mb-2">{{ $image['generation']['provider_name'] ?? '' }} · {{ $image['generation']['model'] ?? '' }} · Solicitado: {{ $image['generation']['requested_size'] ?? 'Sin dato' }} · Resolución real: {{ $image['resolution']['width'] ?? '?' }} × {{ $image['resolution']['height'] ?? '?' }} px</p>
        <details><summary>Ver prompt original</summary><p class="text-wrap mt-2">{{ $image['generation']['prompt'] ?? 'No guardado' }}</p>@if(!empty($image['generation']['prompt_truncated']))<p class="small text-muted">Prompt truncado por la configuración de registros IA del sitio.</p>@endif</details>
      </div>
    @empty<p class="text-muted">No hay imágenes disponibles en el gestor de medios de este sitio.</p>@endforelse
  </div></div>
  <details class="card mb-3"><summary class="card-header">Diagnóstico de generación de imágenes</summary><div class="card-body">
    <p class="small text-muted">Registro privado desde esta actualización: proveedor, etapa, duración y códigos de respuesta. No guarda claves, secretos, prompts ni las URL del proveedor. Una generación interrumpida anterior no tiene un diagnóstico retroactivo.</p>
    @forelse(($imageDiagnostics ?? []) as $trace)<pre class="text-wrap border p-2 small">{{ json_encode($trace,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
    @empty<p class="text-muted">Todavía no hay llamadas registradas para esta conexión.</p>@endforelse
  </div></details>
  </section>
  <section data-mcp-panel="requests">
  @if(count($schedules))
  <div class="card mb-3"><div class="card-body"><h2 class="h5">Publicaciones programadas</h2>
    @foreach($schedules as $schedule)
      <form method="post" action="{{ $back }}" class="d-flex gap-3 align-items-center mb-2">{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="schedule" value="{{ $schedule['id'] }}"><span>Artículo #{{ $schedule['post_id'] }} · {{ gmdate('Y-m-d H:i',$schedule['due']) }} UTC</span><button class="btn btn-sm btn-outline-danger" name="action" value="cancel_schedule">Cancelar programación</button></form>
    @endforeach
  </div></div>
  @endif
  </section>
  <section data-mcp-panel="help">
  <details class="card mb-3"><summary class="card-header">Instrucciones de conexión</summary><div class="card-body">
    <ol><li>En el asistente, añade una conexión MCP remota usando la URL de esta página.</li><li>Selecciona OAuth y tu propio cliente; copia su ID y secreto.</li><li>Usa <code>client_secret_basic</code> y los ámbitos que hayas habilitado, separados por espacios.</li><li>Inicia sesión en este CMS y revisa el consentimiento.</li><li>Prueba una consulta. Para escribir, prepara una solicitud y apruébala en esta página o desde MCP si has habilitado ese permiso.</li></ol>
    <p>El asistente debe admitir Authorization Code con PKCE S256 y enviar el recurso MCP exacto. Cada asistente utiliza credenciales independientes.</p>
    <p><a href="https://developers.openai.com/plugins/build/auth" target="_blank" rel="noopener">Autenticación en ChatGPT</a> · <a href="https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp" target="_blank" rel="noopener">Conectores de Claude</a></p>
  </div></details>
  <details class="card mb-3"><summary class="card-header">Modalidad túnel</summary><div class="card-body"><p>Esta entrega utiliza HTTPS directo por 443. El túnel OpenAI no está configurado ni se reutiliza el del Panel. Su incorporación requiere una configuración independiente.</p></div></details>
  <details class="card mb-3"><summary class="card-header">Actividad reciente</summary><div class="card-body"><ul>@foreach($events as $event)<li>{{ gmdate('Y-m-d H:i:s',$event['created']) }} UTC — {{ $event['action'] }} — {{ $event['detail'] }}</li>@endforeach</ul></div></details>
  </section>
</div>
  </div>
  <aside class="col-xl-3 d-none d-xl-block" aria-label="Opciones de Ajustes">
    @include('settings.partials.sidebar', ['active' => 'mcp'])
  </aside>
</div>
<dialog id="mcp-progress" class="mcp-info-dialog" aria-labelledby="mcp-progress-title">
  <h2 id="mcp-progress-title" class="h5">Progreso de solicitudes MCP</h2>
  <p id="mcp-progress-summary" role="status" aria-live="polite"></p>
  <div id="mcp-progress-spinner" class="spinner-border text-primary" role="status"><span class="visually-hidden">Procesando</span></div>
  <ol id="mcp-progress-list" class="mt-3"></ol>
  <button type="button" id="mcp-progress-close" class="btn btn-primary">Cerrar y actualizar</button>
</dialog>
@endsection
@push('scripts')
<script src="/assets/superadmin/js/mcp-progress.js?v=5"></script>
<script src="/assets/superadmin/js/mcp-access-tabs.js?v=4"></script>
<script>
document.querySelectorAll('[data-mcp-info]').forEach(button=>button.addEventListener('click',()=>document.getElementById('mcp-info-'+button.dataset.mcpInfo).showModal()));
document.querySelectorAll('[data-mcp-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog').close()));
document.querySelectorAll('.mcp-info-dialog:not(#mcp-progress)').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}}));
const groupInputs=Array.from(document.querySelectorAll('[data-mcp-group]'));
function updateAssistantSites(){
  const selected=new Set(groupInputs.filter(input=>input.checked).map(input=>input.value));
  document.querySelectorAll('[data-mcp-site-group]').forEach(input=>{
    input.disabled=!selected.has(input.dataset.mcpSiteGroup);
    if(input.disabled) input.checked=false;
  });
  const hint=document.getElementById('mcp-group-hint');
  if(hint) hint.hidden=selected.size>0;
}
groupInputs.forEach(input=>input.addEventListener('change',updateAssistantSites));
updateAssistantSites();
const providerSelect=document.getElementById('mcp-provider');
const callbackInput=document.getElementById('mcp-callback');
let customCallback='';
providerSelect?.addEventListener('change',()=>{
  const callbacks={chatgpt:'https://chatgpt.com/connector_platform_oauth_redirect',claude:'https://claude.ai/api/mcp/auth_callback'};
  if (!callbackInput.readOnly) customCallback=callbackInput.value;
  const automatic=Object.prototype.hasOwnProperty.call(callbacks,providerSelect.value);
  callbackInput.readOnly=automatic;
  callbackInput.required=!automatic;
  callbackInput.value=automatic?callbacks[providerSelect.value]:customCallback;
});
document.querySelectorAll('[data-mcp-enable]').forEach(form=>form.addEventListener('submit',async event=>{
  event.preventDefault();const button=form.querySelector('button');const status=form.querySelector('[data-mcp-enable-status]');button.disabled=true;status.textContent='Habilitando conexión…';
  try { const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-MCP-Progress':'1'},credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'No se pudo habilitar la conexión.');button.textContent='Conexión habilitada';status.textContent='Ya puedes reintentar OAuth en el proveedor. Las credenciales siguen siendo las mismas.';const notice=form.closest('[role="status"]');notice.querySelector('strong').textContent='Esta conexión está habilitada.';document.querySelectorAll('input[name="enabled"]').forEach(input=>input.checked=true);
  } catch(error){button.disabled=false;status.textContent=error.message;}
}));
document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{const input=document.getElementById(button.dataset.copy);try{await navigator.clipboard.writeText(input.value);button.textContent='Copiado';}catch(e){input.focus();input.select();}}));
document.getElementById('show-secret')?.addEventListener('click',()=>{const input=document.getElementById('client-secret');input.type=input.type==='password'?'text':'password';});
document.getElementById('dismiss-credentials')?.addEventListener('click',()=>{document.getElementById('client-secret').value='';document.getElementById('new-credentials').remove();});
</script>
@endpush
