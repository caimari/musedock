@extends('layouts.app')
@section('title', $title)
@section('content')
<style>
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
.mcp-settings .mcp-consents-heading { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5eaf1; }
@media (max-width: 576px) { .mcp-settings.container-fluid { padding: 16px !important; } .mcp-settings .card-body, .mcp-settings .card-header { padding: 18px; } .mcp-settings .nav-link { padding: 10px 12px; } }
</style>
@php
  $isPluginConnection = str_starts_with($resource['id'], 'plugin:');
  $groupNames = [];
  foreach ($groups as $editorialGroup) $groupNames[(int)$editorialGroup['id']] = $editorialGroup['name'];
  $clientSites = array_values(array_filter($sites, function ($site) use ($resource, $groupNames) {
      return in_array((int)$site['id'], $resource['owned_sites'], true) && isset($groupNames[(int)$site['group_id']]);
  }));
@endphp
<div class="container-fluid p-4 mcp-settings">
  <h1 class="h3"><i class="bi bi-robot me-2"></i>(MCP) Conexiones IA</h1>
  @if(!$tenantMode)
  <nav class="nav nav-pills mb-3">
    @foreach(['main'=>'Dominio principal','plugin:cross-publisher'=>'Cross Publisher','plugin:news-aggregator'=>'News Aggregator'] as $key=>$label)
      <a class="nav-link {{ $resource['id']===$key ? 'active' : '' }}" href="{{ $base }}?resource={{ urlencode($key) }}">{{ $label }}</a>
    @endforeach
  </nav>
  @else
    <p><a href="{{ '/' . admin_path() . '/mcp' }}">Volver a las conexiones del sitio</a></p>
  @endif
  @if($tenantMode)
    <p class="text-muted">Al activar esta modalidad, las conexiones anteriores de este sitio deberán reconectarse con sus nuevos clientes OAuth.</p>
  @endif
  <p>Conecta un asistente para consultar artículos y preparar borradores en <strong>{{ $resource['name'] }}</strong>.</p>
  @if($error)<div class="alert alert-danger">{{ $error }}</div>@endif
  @if(!$deploymentEnabled)<div class="mcp-activation-notice" role="status"><strong>La conexión pública está pendiente de activación por el operador del CMS.</strong><span>Puedes preparar sus clientes y permisos.</span></div>@endif
  @if(!$pluginInstalled)<div class="alert alert-warning">Este plugin debe estar instalado y activo para abrir su conexión MCP.</div>@endif
  <div class="card mb-3"><div class="card-body">
    <h2 class="h5">{{ $isPluginConnection ? 'Paso 1 · Configurar la conexión y sus sitios disponibles' : 'Conexión directa HTTPS' }}</h2>
    @if($isPluginConnection)<p class="text-muted">Primero define los sitios propios que este plugin podrá gestionar por MCP. Después podrás dar a cada asistente acceso a todos o solo a algunos de ellos.</p>@endif
    <p>Servicio: <span class="mcp-status {{ $deploymentEnabled && $resource['enabled'] && $pluginInstalled ? 'is-enabled' : '' }}">{{ $deploymentEnabled && $resource['enabled'] && $pluginInstalled ? 'Habilitado' : 'Desactivado' }}</span>. La autorización de cada cliente se muestra por separado.</p>
    <div class="input-group mb-3"><input id="mcp-url" class="form-control" readonly value="{{ $resource['url'] }}"><button class="btn btn-outline-primary" type="button" data-copy="mcp-url">Copiar URL</button></div>
    <form method="post" action="{{ $back }}">
      {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="action" value="configure">
      <label class="form-check"><input class="form-check-input" type="checkbox" name="enabled" {{ $resource['enabled'] ? 'checked' : '' }}> Habilitar esta conexión</label>
      <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="allow_drafts" {{ $resource['allow_drafts'] ? 'checked' : '' }}> Permitir solicitudes de borradores con aprobación en el CMS</label>
      @if(count($sites))
      <fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto h6 px-2">Sitios disponibles para conexiones MCP del plugin</legend>
        <p class="small text-muted">Marca únicamente sitios de tu organización y guarda esta sección. Esto define la lista disponible para el paso 2; todavía no concede acceso a ningún asistente. Los sitios sin grupo editorial no podrán asignarse a un cliente del plugin.</p>
        @foreach($sites as $site)
          <label class="form-check"><input class="form-check-input" type="checkbox" name="owned_sites[]" value="{{ $site['id'] }}" {{ in_array((int)$site['id'],$resource['owned_sites'],true) ? 'checked' : '' }}> {{ $site['name'] }} — {{ $site['domain'] }} <small class="d-block text-muted">Grupo: {{ $groupNames[(int)$site['group_id']] ?? 'Sin grupo editorial — no disponible para asistentes' }}</small></label>
        @endforeach
      </fieldset>
      @endif
      <p class="small text-muted">Guardar cambia la autorización del recurso: los clientes deberán volver a dar su consentimiento.</p>
      <button class="btn btn-primary">{{ $isPluginConnection ? 'Guardar conexión y sitios disponibles' : 'Guardar' }}</button>
    </form>
  </div></div>
  @if($newClient)
  <div id="new-credentials" class="card border-success mb-3"><div class="card-body">
    <h2 class="h5">Credenciales nuevas</h2><p>Cópialas ahora. El secreto se muestra una sola vez.</p>
    <label for="client-id">ID del cliente</label><div class="input-group mb-2"><input id="client-id" readonly class="form-control" value="{{ $newClient['id'] }}"><button type="button" class="btn btn-outline-primary" data-copy="client-id">Copiar</button></div>
    <label for="client-secret">Secreto</label><div class="input-group mb-2"><input type="password" id="client-secret" readonly autocomplete="off" class="form-control" value="{{ $newClient['secret'] }}"><button type="button" class="btn btn-outline-secondary" id="show-secret">Mostrar/ocultar</button><button type="button" class="btn btn-outline-primary" data-copy="client-secret">Copiar</button></div>
    <button type="button" class="btn btn-success" id="dismiss-credentials">Ya las he guardado</button>
  </div></div>
  @endif
  <details class="card mb-3"><summary class="card-header">{{ $isPluginConnection ? 'Paso 2 · Dar acceso a un asistente' : 'Crear cliente para ChatGPT, Claude u otro asistente' }}</summary><div class="card-body">
    @if($isPluginConnection)<p class="text-muted">Crea credenciales independientes para ChatGPT, Claude u otro asistente. Aquí decides qué puede hacer y a cuáles de los sitios guardados en el paso 1 podrá acceder.</p>@endif
    <form method="post" action="{{ $back }}">
      {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="action" value="create_client">
      <label class="form-label">Nombre</label><input name="name" class="form-control mb-2" required maxlength="100" placeholder="ChatGPT editorial">
      <label for="mcp-provider" class="form-label">Asistente</label>
      <select id="mcp-provider" name="provider" class="form-select mb-2"><option value="chatgpt">ChatGPT — dirección de retorno automática</option><option value="claude">Claude — dirección de retorno automática</option><option value="custom">Personalizado — otro asistente o callback distinto</option></select>
      <label for="mcp-callback" class="form-label">Dirección de retorno OAuth (callback)</label><input id="mcp-callback" name="callback" type="url" class="form-control mb-2" readonly maxlength="500" value="https://chatgpt.com/connector_platform_oauth_redirect" placeholder="Pega la URL HTTPS exacta del asistente">
      <p class="small text-muted mb-3">Es la dirección del asistente a la que volverás después de autorizar el acceso en este CMS. ChatGPT y Claude se rellenan automáticamente. Si el asistente muestra otra URL, elige Personalizado y copia esa dirección exacta; en ese modo no puede quedar vacía. Cada asistente necesita sus propias credenciales.</p>
      <fieldset class="mb-3"><legend class="h6">Permisos</legend>
      @foreach($scopes as $scope)
        <label class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" {{ $loop->first ? 'checked' : '' }}> {{ $scope }}</label>
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
            <label class="form-check"><input class="form-check-input" type="checkbox" name="sites[]" value="{{ $site['id'] }}" data-mcp-site-group="{{ $site['group_id'] }}"> {{ $site['domain'] }} <small class="d-block text-muted">Grupo: {{ $groupNames[(int)$site['group_id']] }}</small></label>
          @endforeach
        @else
          <div class="alert alert-info mb-0" role="status"><strong>Todavía no hay sitios disponibles para este asistente.</strong><br>En el paso 1, marca al menos un sitio propio con grupo editorial y pulsa «Guardar conexión y sitios disponibles». Después vuelve aquí para seleccionar el grupo y los sitios del asistente.</div>
        @endif
      </fieldset>
      @endif
      <details class="mt-3"><summary>Redes permitidas (opcional)</summary><label class="form-label">IPs o CIDR de salida fiables del cliente</label><textarea name="networks" class="form-control" rows="3"></textarea><p class="small text-muted">Por defecto, déjalo vacío: se exige OAuth válido desde cualquier IP. No desactiva el firewall ni abre puertos nuevos; esta conexión utiliza el HTTPS de tu web (443). Rellénalo únicamente si conoces las IPs o rangos de salida del asistente: una lista incorrecta impedirá conectar. Se aplica a MCP y al intercambio de tokens, no al consentimiento del navegador.</p></details>
      <button class="btn btn-primary mt-3" {{ $isPluginConnection && !count($clientSites) ? 'disabled' : '' }}>Crear credenciales del asistente</button>
    </form>
  </div></details>
  <div class="card mb-3"><div class="card-body"><h2 class="h5">Clientes y permisos</h2>
    @forelse($clients as $client)
    <div class="border rounded p-3 mb-3"><h3 class="h6">{{ $client['name'] }} — {{ $client['enabled'] ? 'Habilitado' : 'Revocado' }}</h3><p class="small">ID: <code>{{ $client['id'] }}</code><br>Callback: {{ $client['callback'] }}</p>
      <form method="post" action="{{ $back }}" class="mb-2">
        {!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="client" value="{{ $client['id'] }}"><input type="hidden" name="action" value="client_permissions">
        @foreach($scopes as $scope)<label class="form-check"><input class="form-check-input" type="checkbox" name="scopes[]" value="{{ $scope }}" {{ in_array($scope,$client['scopes'],true) ? 'checked' : '' }}> {{ $scope }}</label>@endforeach
        <button class="btn btn-sm btn-outline-primary mt-2">Guardar permisos y exigir nuevo consentimiento</button>
      </form>
      <form method="post" action="{{ $back }}">{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="client" value="{{ $client['id'] }}"><button class="btn btn-sm btn-outline-danger" name="action" value="revoke_client">Revocar cliente</button></form>
    </div>
    @empty <p class="text-muted">Aún no hay clientes registrados.</p>@endforelse
    <h3 class="h6 mcp-consents-heading">Consentimientos</h3>
    @forelse($grants as $grant)
      <form method="post" action="{{ $back }}" class="mb-2">{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="grant" value="{{ $grant['id'] }}">
        <span>{{ $grant['client'] }} — {{ $grant['active'] && $grant['resource_revision']===$resource['revision'] ? 'Concedido (sujeto a permisos vigentes)' : 'Revocado o pendiente de renovación' }}</span>
        <button class="btn btn-sm btn-outline-danger" name="action" value="revoke_grant">Revocar consentimiento</button>
      </form>
    @empty <p class="text-muted">Ningún asistente ha completado la autorización.</p>@endforelse
  </div></div>
  <div class="card mb-3"><div class="card-body"><h2 class="h5">Cambios pendientes</h2>
    @forelse($requests as $request)
      <div class="border rounded p-3 mb-3"><p>Solicitud {{ $request['id'] }} · caduca en {{ max(0,$request['expires']-time()) }} segundos</p>
      <details><summary>Revisar el plan y el contenido</summary><pre class="text-wrap border p-2">{{ json_encode($request['payload']['plan'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></details>
      <form method="post" action="{{ $back }}" class="mt-2">{!! csrf_field() !!}<input type="hidden" name="resource" value="{{ $resource['id'] }}"><input type="hidden" name="request" value="{{ $request['id'] }}"><button class="btn btn-success" name="action" value="approve">Aprobar borrador</button> <button class="btn btn-outline-danger" name="action" value="reject">Rechazar</button></form></div>
    @empty <p class="text-muted">No hay cambios pendientes.</p>@endforelse
  </div></div>
  <details class="card mb-3"><summary class="card-header">Instrucciones de conexión</summary><div class="card-body">
    <ol><li>En el asistente, añade una conexión MCP remota usando la URL de esta página.</li><li>Selecciona OAuth y tu propio cliente; copia su ID y secreto.</li><li>Usa <code>client_secret_basic</code> y los ámbitos que hayas habilitado, separados por espacios.</li><li>Inicia sesión en este CMS y revisa el consentimiento.</li><li>Prueba una consulta. Para escribir, solicita un borrador y apruébalo en esta página.</li></ol>
    <p>El asistente debe admitir Authorization Code con PKCE S256 y enviar el recurso MCP exacto. Cada asistente utiliza credenciales independientes.</p>
    <p><a href="https://developers.openai.com/plugins/build/auth" target="_blank" rel="noopener">Autenticación en ChatGPT</a> · <a href="https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp" target="_blank" rel="noopener">Conectores de Claude</a></p>
  </div></details>
  <details class="card mb-3"><summary class="card-header">Modalidad túnel</summary><div class="card-body"><p>Esta entrega utiliza HTTPS directo por 443. El túnel OpenAI no está configurado ni se reutiliza el del Panel. Su incorporación requiere una configuración independiente.</p></div></details>
  <details class="card mb-3"><summary class="card-header">Actividad reciente</summary><div class="card-body"><ul>@foreach($events as $event)<li>{{ gmdate('Y-m-d H:i:s',$event['created']) }} UTC — {{ $event['action'] }} — {{ $event['detail'] }}</li>@endforeach</ul></div></details>
</div>
@endsection
@push('scripts')
<script>
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
document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{const input=document.getElementById(button.dataset.copy);try{await navigator.clipboard.writeText(input.value);button.textContent='Copiado';}catch(e){input.focus();input.select();}}));
document.getElementById('show-secret')?.addEventListener('click',()=>{const input=document.getElementById('client-secret');input.type=input.type==='password'?'text':'password';});
document.getElementById('dismiss-credentials')?.addEventListener('click',()=>{document.getElementById('client-secret').value='';document.getElementById('new-credentials').remove();});
</script>
@endpush
