@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="app-content">
  <div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="bi bi-gear me-2"></i>{{ $title }}</h2>
      <a href="{{ festival_admin_url() }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Festivales</a>
    </div>

    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ icon:'success', title:'OK', text:{!! json_encode(session('success')) !!}, timer:3000 }); });</script>
    @endif

    <form method="POST" action="{{ festival_admin_url('settings') }}" id="settingsForm">
      @csrf

      {{-- Scraper Proxy --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-shield-lock me-2"></i><strong>Proxy para Scraper</strong>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">
            Configura un proxy para que las peticiones del scraper no salgan desde la IP del servidor.
            Esto evita posibles bloqueos por parte de las plataformas externas.
          </p>

          <div class="mb-3">
            <label class="form-label">URL del Proxy</label>
            <div class="input-group">
              <input type="text" class="form-control" name="scraper_proxy" id="scraper_proxy"
                     value="{{ $settings['scraper_proxy'] ?? '' }}"
                     placeholder="socks5://user:pass@host:1080 o http://host:3128">
              <button type="button" class="btn btn-outline-primary" id="btn-test-proxy">
                <i class="bi bi-wifi me-1"></i> Test
              </button>
            </div>
            <small class="text-muted">Formatos: <code>http://host:port</code> &middot; <code>socks5://user:pass@host:port</code></small>
          </div>

          {{-- Proxy presets --}}
          <div class="mb-3">
            <label class="form-label small fw-semibold">Proveedores de proxy recomendados</label>
            <div class="table-responsive">
              <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem">
                <thead class="table-light">
                  <tr><th>Proveedor</th><th>Tipo</th><th>Precio</th><th>Formato</th><th></th></tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>BrightData</strong> <small class="text-muted">(Luminati)</small></td>
                    <td><span class="badge bg-success">Residencial</span></td>
                    <td>~$10/GB</td>
                    <td><code>http://user:pass@brd.superproxy.io:22225</code></td>
                    <td><button type="button" class="btn btn-outline-secondary btn-sm proxy-preset" data-url="http://USER:PASS@brd.superproxy.io:22225"><i class="bi bi-clipboard"></i></button></td>
                  </tr>
                  <tr>
                    <td><strong>SmartProxy</strong></td>
                    <td><span class="badge bg-success">Residencial</span></td>
                    <td>~$7/GB</td>
                    <td><code>http://user:pass@gate.smartproxy.com:7000</code></td>
                    <td><button type="button" class="btn btn-outline-secondary btn-sm proxy-preset" data-url="http://USER:PASS@gate.smartproxy.com:7000"><i class="bi bi-clipboard"></i></button></td>
                  </tr>
                  <tr>
                    <td><strong>Oxylabs</strong></td>
                    <td><span class="badge bg-success">Residencial</span></td>
                    <td>~$8/GB</td>
                    <td><code>http://user:pass@pr.oxylabs.io:7777</code></td>
                    <td><button type="button" class="btn btn-outline-secondary btn-sm proxy-preset" data-url="http://USER:PASS@pr.oxylabs.io:7777"><i class="bi bi-clipboard"></i></button></td>
                  </tr>
                  <tr>
                    <td><strong>ScraperAPI</strong></td>
                    <td><span class="badge bg-primary">API</span></td>
                    <td>5000 req gratis/mes</td>
                    <td><code>http://scraperapi:APIKEY@proxy-server.scraperapi.com:8001</code></td>
                    <td><button type="button" class="btn btn-outline-secondary btn-sm proxy-preset" data-url="http://scraperapi:APIKEY@proxy-server.scraperapi.com:8001"><i class="bi bi-clipboard"></i></button></td>
                  </tr>
                  <tr>
                    <td><strong>Webshare</strong></td>
                    <td><span class="badge bg-warning text-dark">Datacenter</span></td>
                    <td>10 proxies gratis</td>
                    <td><code>socks5://user:pass@proxy.webshare.io:PORT</code></td>
                    <td><button type="button" class="btn btn-outline-secondary btn-sm proxy-preset" data-url="socks5://USER:PASS@proxy.webshare.io:PORT"><i class="bi bi-clipboard"></i></button></td>
                  </tr>
                  <tr>
                    <td><strong>ProxyScrape</strong> <small class="text-success">(gratis)</small></td>
                    <td><span class="badge bg-secondary">Público</span></td>
                    <td>Gratis</td>
                    <td><code>http://IP:PORT</code> <small>(rotativo, poco fiable)</small></td>
                    <td><button type="button" class="btn btn-outline-secondary btn-sm" id="btn-fetch-free-proxy" title="Obtener proxy público aleatorio"><i class="bi bi-arrow-repeat"></i></button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <small class="text-muted mt-1 d-block">
              <i class="bi bi-info-circle me-1"></i>
              Los proxies residenciales son los más fiables para scraping. Los públicos son gratuitos pero inestables.
              Pulsa <i class="bi bi-clipboard"></i> para copiar el formato al campo y rellena tus credenciales.
            </small>
          </div>

          {{-- Test results shown via SweetAlert2 --}}
        </div>
      </div>

      {{-- Import Settings --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-cloud-download me-2"></i><strong>Opciones de Importación</strong>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Delay entre peticiones (segundos)</label>
              <input type="number" class="form-control" name="scraper_delay" min="1" max="30"
                     value="{{ $settings['scraper_delay'] ?? 2 }}">
              <small class="text-muted">Tiempo de espera entre peticiones al scraper para no sobrecargar los servidores externos. Mínimo: 1s</small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Estado por defecto al importar</label>
              <select class="form-select" name="import_default_status">
                <option value="draft" {{ ($settings['import_default_status'] ?? 'draft') === 'draft' ? 'selected' : '' }}>Borrador</option>
                <option value="published" {{ ($settings['import_default_status'] ?? '') === 'published' ? 'selected' : '' }}>Publicado</option>
              </select>
              <small class="text-muted">Los festivales importados se crean con este estado</small>
            </div>
          </div>
        </div>
      </div>

      {{-- Server Info --}}
      <div class="card mb-4">
        <div class="card-header">
          <i class="bi bi-info-circle me-2"></i><strong>Información del servidor</strong>
        </div>
        <div class="card-body">
          <table class="table table-sm table-borderless mb-0">
            <tr><th style="width:200px">IP del servidor:</th><td><code id="server-ip">—</code></td></tr>
            <tr><th>cURL disponible:</th><td>{!! function_exists('curl_init') ? '<span class="text-success"><i class="bi bi-check-circle"></i> Sí</span>' : '<span class="text-danger"><i class="bi bi-x-circle"></i> No</span>' !!}</td></tr>
            <tr><th>PHP version:</th><td><code>{{ phpversion() }}</code></td></tr>
          </table>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i> Guardar configuración
      </button>
      <a href="{{ festival_admin_url() }}" class="btn btn-outline-secondary ms-2">Cancelar</a>

    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const csrfToken = document.querySelector('input[name=_token]')?.value || '';

  // Test proxy button — all results via SweetAlert2
  document.getElementById('btn-test-proxy').addEventListener('click', function() {
    const proxy = document.getElementById('scraper_proxy').value.trim();
    const btn = this;

    if (!proxy) {
      Swal.fire({ icon: 'info', title: 'Sin proxy', text: 'Introduce una URL de proxy para hacer el test.' });
      return;
    }

    // Show loading modal
    Swal.fire({
      title: 'Probando proxy...',
      html: '<div class="spinner-border text-primary mb-3"></div>' +
            '<p class="text-muted mb-0">Conectando a <code>' + proxy.replace(/\/\/([^:]+):([^@]+)@/, '//***:***@') + '</code></p>' +
            '<p class="text-muted small">Esto puede tardar hasta 10 segundos</p>',
      showConfirmButton: false,
      allowOutsideClick: false,
    });

    fetch('{{ festival_admin_url("settings/test-proxy") }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({ proxy: proxy, _token: csrfToken }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const maskedHtml = data.masked
          ? '<span class="text-success fw-bold">Sí — IP enmascarada correctamente</span>'
          : '<span class="text-warning fw-bold">No — la IP del proxy coincide con la del servidor</span>';

        Swal.fire({
          icon: 'success',
          title: 'Proxy funcionando',
          html: `<table class="table table-sm table-borderless text-start mx-auto" style="max-width:350px;font-size:0.9rem">
              <tr><th>IP del proxy:</th><td><code>${data.proxy_ip}</code></td></tr>
              <tr><th>IP del servidor:</th><td><code>${data.server_ip}</code></td></tr>
              <tr><th>Enmascarado:</th><td>${maskedHtml}</td></tr>
              <tr><th>Tiempo:</th><td>${data.time}</td></tr>
            </table>`,
          confirmButtonText: 'OK',
        });

        document.getElementById('server-ip').textContent = data.server_ip;
      } else {
        // Determine if it's a proxy issue or system issue
        const isTimeout = data.message && data.message.toLowerCase().includes('timeout');
        const isConnectionRefused = data.message && (data.message.includes('refused') || data.message.includes('unreachable'));

        let explanation = '';
        if (isTimeout) {
          explanation = '<p class="small text-muted mt-2 mb-0"><strong>Causa probable:</strong> El proxy no responde. Si es público/gratuito, probablemente esté caído o saturado. Prueba con otro.</p>';
        } else if (isConnectionRefused) {
          explanation = '<p class="small text-muted mt-2 mb-0"><strong>Causa probable:</strong> El proxy rechazó la conexión. Verifica host, puerto y credenciales.</p>';
        } else {
          explanation = '<p class="small text-muted mt-2 mb-0"><strong>Causa probable:</strong> Verifica el formato, credenciales y que el proxy esté activo.</p>';
        }

        Swal.fire({
          icon: 'error',
          title: 'Proxy no funciona',
          html: '<p>' + data.message + '</p>' + explanation,
          confirmButtonText: 'Entendido',
        });
      }
    })
    .catch(err => {
      Swal.fire({
        icon: 'error',
        title: 'Error de red',
        text: 'No se pudo conectar con el servidor: ' + err.message,
      });
    });
  });

  // Proxy presets — copy to input
  document.querySelectorAll('.proxy-preset').forEach(btn => {
    btn.addEventListener('click', function() {
      const url = this.dataset.url;
      document.getElementById('scraper_proxy').value = url;
      Swal.fire({
        icon: 'info',
        title: 'Formato copiado',
        html: 'Reemplaza <code>USER</code>, <code>PASS</code> y <code>APIKEY</code> con tus credenciales reales del proveedor.',
        confirmButtonText: 'Entendido',
      });
    });
  });

  // Fetch free public proxy
  const fetchFreeBtn = document.getElementById('btn-fetch-free-proxy');
  if (fetchFreeBtn) {
    fetchFreeBtn.addEventListener('click', function() {
      this.disabled = true;
      this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

      fetch('{{ festival_admin_url("settings/free-proxies") }}')
        .then(r => r.json())
        .then(data => {
          this.disabled = false;
          this.innerHTML = '<i class="bi bi-arrow-repeat"></i>';

          if (!data.success || !data.proxies) {
            Swal.fire({ icon: 'warning', title: 'Error', text: data.message || 'No se pudo obtener proxies.' });
            return;
          }

          const proxies = data.proxies;
          if (proxies.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin proxies', text: 'No se encontraron proxies públicos disponibles ahora mismo.' });
            return;
          }

          // Show list to pick
          let html = '<p class="small text-muted mb-2">Proxies públicos disponibles (pueden ser inestables):</p>';
          html += '<div class="list-group">';
          proxies.forEach(p => {
            html += `<button type="button" class="list-group-item list-group-item-action swal-proxy-pick" data-proxy="${p.trim()}" style="font-family:monospace;font-size:0.85rem">${p.trim()}</button>`;
          });
          html += '</div>';

          Swal.fire({
            title: 'Proxies públicos',
            html: html,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cerrar',
            didOpen: () => {
              document.querySelectorAll('.swal-proxy-pick').forEach(el => {
                el.addEventListener('click', function() {
                  document.getElementById('scraper_proxy').value = this.dataset.proxy;
                  Swal.close();
                });
              });
            }
          });
        })
        .catch(() => {
          this.disabled = false;
          this.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
          Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo obtener la lista de proxies.' });
        });
    });
  }
});
</script>
@endpush
