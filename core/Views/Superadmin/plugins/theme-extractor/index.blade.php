@extends('layouts.app')

@section('title', $title ?? 'CSS Auditor')

@section('content')
@include('partials.alerts-sweetalert2')

<div class="container-fluid" style="max-width: 1300px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-filetype-css me-2"></i>CSS Auditor</h2>
            <p class="text-muted mb-0">Analiza el CSS de cualquier web: descubre selectores no usados y genera un CSS limpio unificado</p>
        </div>
        <a href="/musedock/themes" class="btn btn-outline-secondary btn-sm text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Volver a Temas
        </a>
    </div>

    {{-- URL Input --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-lg-9 mb-3 mb-lg-0">
                    <label class="form-label fw-semibold">URL de la pagina a analizar</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="bi bi-globe"></i></span>
                        <input type="url" id="inputUrl" class="form-control" placeholder="https://ejemplo.com" required>
                    </div>
                    <small class="text-muted">Analizaremos los CSS de esta pagina, detectaremos selectores usados y generaremos un CSS limpio</small>
                </div>
                <div class="col-lg-3">
                    <button type="button" class="btn btn-primary btn-lg w-100" id="btnExtract">
                        <i class="bi bi-search me-1"></i> Analizar CSS
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Results (hidden initially) --}}
    <div id="resultsContainer" class="d-none">
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card text-center h-100"><div class="card-body py-3">
                    <div class="display-6 fw-bold text-primary" id="statFiles">0</div>
                    <small class="text-muted">Archivos CSS</small>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100"><div class="card-body py-3">
                    <div class="display-6 fw-bold text-info" id="statSelectors">0</div>
                    <small class="text-muted">Selectores totales</small>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100"><div class="card-body py-3">
                    <div class="display-6 fw-bold" id="statUsedPct">0%</div>
                    <small class="text-muted">Selectores usados</small>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center h-100"><div class="card-body py-3">
                    <div class="display-6 fw-bold text-success" id="statReduction">0%</div>
                    <small class="text-muted">Reduccion total</small>
                </div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold">CSS Original: <code id="sizeOriginal">0 KB</code></span>
                    <span class="fw-semibold text-success">CSS Limpio: <code id="sizeClean">0 KB</code></span>
                </div>
                <div class="progress" style="height: 24px;">
                    <div class="progress-bar bg-success" id="barUsed" style="width: 0%;">0%</div>
                    <div class="progress-bar bg-danger bg-opacity-50" id="barUnused" style="width: 100%;">100%</div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-code me-2 text-primary"></i>Analisis por archivo</h5>
                <span class="badge bg-primary" id="badgeFiles">0 archivos</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 300px;">Archivo CSS</th>
                                <th class="text-center" style="width: 100px;">Original</th>
                                <th class="text-center" style="width: 100px;">Limpio</th>
                                <th class="text-center" style="width: 100px;">Reduccion</th>
                                <th class="text-center" style="width: 120px;">Selectores</th>
                                <th style="width: 200px;">Uso</th>
                            </tr>
                        </thead>
                        <tbody id="resultsTable"></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- JavaScript Files --}}
        <div class="card mb-4 d-none" id="jsSection">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-filetype-js me-2 text-warning"></i>JavaScript Detectado</h5>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-warning text-dark" id="badgeJsFiles">0 archivos</span>
                    <span class="text-muted small" id="jsTotalSize"></span>
                    <a href="/musedock/theme-extractor/download-js" class="btn btn-warning btn-sm text-white">
                        <i class="bi bi-download me-1"></i> Descargar JS Unificado
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 300px;">Archivo JS</th>
                                <th class="text-center" style="width: 100px;">Tamano</th>
                            </tr>
                        </thead>
                        <tbody id="jsResultsTable"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-stars me-2 text-success"></i>CSS Limpio Unificado</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnTogglePreview">
                        <i class="bi bi-eye me-1"></i> Ver/Ocultar
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnCopyCss">
                        <i class="bi bi-clipboard me-1"></i> Copiar
                    </button>
                    <a href="/musedock/theme-extractor/download" class="btn btn-success btn-sm">
                        <i class="bi bi-download me-1"></i> Descargar CSS Limpio
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2" id="cleanSummary"></p>
                <div id="cssPreview" class="d-none">
                    <textarea class="form-control font-monospace" rows="20" readonly id="cssTextarea"
                        style="font-size: 12px; background: #1e1e2e; color: #cdd6f4; border: none;"></textarea>
                </div>
            </div>
        </div>

        {{-- Clone & Preview --}}
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-copy me-2 text-info"></i>Clonar y Previsualizar</h5>
                <div class="d-flex gap-2 align-items-center">
                    <div class="form-check form-switch me-2" title="Incluir JavaScript unificado en el clon">
                        <input class="form-check-input" type="checkbox" id="chkIncludeJs">
                        <label class="form-check-label small" for="chkIncludeJs">Incluir JS</label>
                    </div>
                    <button type="button" class="btn btn-info btn-sm text-white" id="btnClone">
                        <i class="bi bi-copy me-1"></i> Generar Clon
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="btnOpenPreview">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir en nueva pestana
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm d-none" id="btnDownloadClone">
                        <i class="bi bi-download me-1"></i> Descargar HTML
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Genera una copia de la pagina con solo el CSS utilizado integrado. Compara visualmente el original con el clon para verificar que se ve correctamente.</p>

                {{-- Comparison view --}}
                <div id="clonePreviewArea" class="d-none">
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary active" data-view="side" id="btnViewSide">
                            <i class="bi bi-layout-split me-1"></i> Lado a lado
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-view="clone" id="btnViewClone">
                            <i class="bi bi-file-earmark-code me-1"></i> Solo clon
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-view="original" id="btnViewOriginal">
                            <i class="bi bi-globe me-1"></i> Solo original
                        </button>
                    </div>

                    <div class="row g-2" id="framesContainer">
                        <div class="col-md-6" id="frameOriginalCol">
                            <div class="border rounded overflow-hidden">
                                <div class="bg-dark text-white px-3 py-1 d-flex justify-content-between align-items-center" style="font-size: 12px;">
                                    <span><i class="bi bi-globe me-1"></i>Original</span>
                                    <a id="originalUrlLink" href="#" target="_blank" class="text-white text-truncate text-decoration-none" style="max-width: 250px;">
                                        <i class="bi bi-box-arrow-up-right me-1"></i><span id="originalUrlLabel"></span>
                                    </a>
                                </div>
                                <iframe id="frameOriginal" style="width: 100%; height: 600px; border: none; background: #fff;"></iframe>
                                {{-- Fallback if iframe is blocked --}}
                                <div id="frameOriginalBlocked" class="d-none text-center py-5" style="height: 600px; background: #f8f9fa;">
                                    <i class="bi bi-shield-exclamation display-3 text-muted"></i>
                                    <p class="text-muted mt-3 mb-2">La web original bloquea ser cargada en iframe (CSP/X-Frame-Options)</p>
                                    <a id="originalOpenLink" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir original en nueva pestana
                                    </a>
                                    <p class="text-muted small mt-2">Abre el original y el clon en pestanas separadas para comparar</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" id="frameCloneCol">
                            <div class="border rounded overflow-hidden">
                                <div class="bg-info text-white px-3 py-1 d-flex justify-content-between align-items-center" style="font-size: 12px;">
                                    <span><i class="bi bi-copy me-1"></i>Clon (solo CSS utilizado, sin JS)</span>
                                    <span id="cloneSizeLabel" class="text-white-50"></span>
                                </div>
                                <iframe id="frameClone" style="width: 100%; height: 600px; border: none; background: #fff;"></iframe>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Clone loading state --}}
                <div id="cloneLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-info mb-2"></div>
                    <p class="text-muted">Generando clon de la pagina...</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Progress Modal --}}
<div class="modal fade" id="progressModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">
                    <i class="bi bi-gear-wide-connected me-2 text-primary css-spin"></i>Analizando CSS
                </h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted" id="progressLabel">Conectando...</small>
                        <small class="fw-bold" id="progressPct">0%</small>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 5%;"></div>
                    </div>
                </div>
                <div id="progressLog" class="bg-light rounded p-2 font-monospace mb-3"
                     style="max-height: 150px; overflow-y: auto; font-size: 12px;"></div>
                <div id="liveResults" style="max-height: 300px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes css-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.css-spin { display: inline-block; animation: css-spin 2s linear infinite; }
#progressLog .log-line { padding: 2px 0; border-bottom: 1px solid #e9ecef; }
#progressLog .log-line:last-child { border-bottom: none; }
.log-ok { color: #198754; } .log-info { color: #0d6efd; } .log-err { color: #dc3545; }
.live-row {
    display: flex; align-items: center; gap: 8px; padding: 6px 10px;
    border-radius: 6px; background: #f8f9fa; margin-bottom: 4px; font-size: 12px;
    animation: liveSlide 0.3s ease;
}
@keyframes liveSlide { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.live-row .pct-badge {
    min-width: 60px; text-align: center; padding: 2px 6px; border-radius: 4px;
    font-weight: 600; font-size: 11px; color: #fff;
}
</style>

@push('scripts')
<script>
(function() {
    const $ = id => document.getElementById(id);
    const esc = s => s ? s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';
    const shortUrl = u => !u ? '(inline)' : (u.length > 70 ? '...' + u.slice(-67) : u);
    const kb = b => (b / 1024).toFixed(1);

    const btnExtract = $('btnExtract');
    const inputUrl   = $('inputUrl');
    const modal      = new bootstrap.Modal($('progressModal'));
    let animTimer = null;
    let pollTimer = null;
    let currentJobId = null;
    let isRunning = false;

    function go() {
        const url = inputUrl.value.trim();
        if (!url || isRunning) return;
        if (!/^https?:\/\/.+/i.test(url)) {
            inputUrl.classList.add('is-invalid');
            setTimeout(() => inputUrl.classList.remove('is-invalid'), 2000);
            return;
        }

        isRunning = true;

        // Reset
        $('resultsTable').innerHTML = '';
        $('progressLog').innerHTML = '';
        $('liveResults').innerHTML = '';
        $('resultsContainer').classList.add('d-none');
        $('progressBar').style.width = '5%';
        $('progressBar').className = 'progress-bar progress-bar-striped progress-bar-animated';
        $('progressPct').textContent = '0%';
        $('progressLabel').textContent = 'Iniciando...';
        $('cssTextarea').value = '';

        modal.show();
        btnExtract.disabled = true;
        btnExtract.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analizando...';

        addLog('Enviando peticion a ' + url, 'info');
        startAnimatedPhases();

        // Use GET to avoid CSRF issues with AJAX
        const extractUrl = '/musedock/theme-extractor/extract?url=' + encodeURIComponent(url);

        fetch(extractUrl, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(async r => {
                const ct = r.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    const text = await r.text();
                    console.error('CSS Auditor: Non-JSON response', {status: r.status, contentType: ct, body: text.substring(0, 500)});
                    throw new Error('Respuesta no-JSON (status ' + r.status + ', type: ' + ct + '). Ver consola para detalles.');
                }
                return r.json();
            })
            .then(onSuccess)
            .catch(onError);
    }

    function onSuccess(data) {
        stopTimers();
        isRunning = false;

        if (data.error) {
            addLog(data.error, 'err');
            $('progressLabel').textContent = data.error;
            $('progressBar').className = 'progress-bar bg-danger';
            $('progressBar').style.width = '100%';
            setTimeout(() => { modal.hide(); resetBtn(); }, 2500);
            return;
        }

        // Show 100%
        $('progressBar').style.width = '100%';
        $('progressBar').className = 'progress-bar bg-success';
        $('progressPct').textContent = '100%';
        $('progressLabel').textContent = 'Analisis completado!';
        addLog('Completado! ' + (data.results||[]).length + ' archivos analizados.', 'ok');

        // Show file results in modal
        (data.results || []).forEach(r => addLiveResult(r));

        // Start polling progress for live updates if jobId exists
        if (data.jobId) currentJobId = data.jobId;

        setTimeout(() => {
            modal.hide();
            renderFinal(data);
            $('resultsContainer').classList.remove('d-none');
            $('resultsContainer').scrollIntoView({ behavior: 'smooth' });
            resetBtn();
        }, 1200);
    }

    function onError(err) {
        stopTimers();
        isRunning = false;
        addLog('Error de red: ' + err.message, 'err');
        $('progressLabel').textContent = 'Error de conexion';
        $('progressBar').className = 'progress-bar bg-danger';
        setTimeout(() => { modal.hide(); resetBtn(); }, 2500);
    }

    // Animated phases while waiting for the response
    const phases = [
        { msg: 'Descargando pagina HTML...', pct: 8, delay: 1500 },
        { msg: 'Analizando estructura HTML...', pct: 15, delay: 2000 },
        { msg: 'Buscando archivos CSS enlazados...', pct: 22, delay: 2500 },
        { msg: 'Descargando hojas de estilo...', pct: 35, delay: 4000 },
        { msg: 'Analizando selectores CSS...', pct: 50, delay: 5000 },
        { msg: 'Comparando selectores con HTML...', pct: 65, delay: 6000 },
        { msg: 'Identificando CSS no utilizado...', pct: 75, delay: 5000 },
        { msg: 'Generando CSS limpio unificado...', pct: 85, delay: 4000 },
        { msg: 'Finalizando...', pct: 92, delay: 3000 },
    ];
    let phaseIdx = 0;

    function startAnimatedPhases() {
        phaseIdx = 0;
        nextPhase();
    }

    function nextPhase() {
        if (phaseIdx >= phases.length || !isRunning) return;
        const p = phases[phaseIdx];
        $('progressLabel').textContent = p.msg;
        $('progressBar').style.width = p.pct + '%';
        $('progressPct').textContent = p.pct + '%';
        addLog(p.msg, 'info');
        phaseIdx++;
        animTimer = setTimeout(nextPhase, p.delay);
    }

    function stopTimers() {
        if (animTimer) { clearTimeout(animTimer); animTimer = null; }
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function resetBtn() {
        btnExtract.disabled = false;
        btnExtract.innerHTML = '<i class="bi bi-search me-1"></i> Analizar CSS';
    }

    function addLog(msg, type) {
        const d = document.createElement('div');
        d.className = 'log-line log-' + (type||'info');
        const t = new Date().toLocaleTimeString('es-ES', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
        d.innerHTML = '<span class="text-muted">[' + t + ']</span> ' + esc(msg);
        $('progressLog').appendChild(d);
        $('progressLog').scrollTop = $('progressLog').scrollHeight;
    }

    function addLiveResult(r) {
        const name = r.is_inline ? '<i class="bi bi-code-slash text-muted me-1"></i>&lt;style&gt; inline' : '<i class="bi bi-file-earmark-code text-primary me-1"></i>' + esc(shortUrl(r.url));
        const bg = r.reduction_pct >= 80 ? 'bg-danger' : (r.reduction_pct >= 50 ? 'bg-warning' : 'bg-success');
        const d = document.createElement('div');
        d.className = 'live-row';
        d.innerHTML = name +
            '<span class="flex-grow-1"></span>' +
            '<span class="pct-badge ' + bg + '">' + r.reduction_pct + '% menor</span>' +
            '<span class="badge bg-light text-dark border">' + r.used_pct + '% usado</span>' +
            '<span class="text-muted text-nowrap">' + kb(r.original_size) + ' &rarr; ' + kb(r.clean_size) + ' KB</span>';
        $('liveResults').appendChild(d);
        $('liveResults').scrollTop = $('liveResults').scrollHeight;
    }

    function renderFinal(d) {
        const r = d.results || [];
        $('statFiles').textContent = r.length;
        $('statSelectors').textContent = (d.totalSelectors||0).toLocaleString();

        const uPct = d.totalSelectors > 0 ? Math.round(d.totalUsed / d.totalSelectors * 100) : 0;
        $('statUsedPct').textContent = uPct + '%';
        $('statUsedPct').className = 'display-6 fw-bold ' + (uPct > 50 ? 'text-success' : 'text-warning');

        const rPct = d.totalOriginal > 0 ? Math.round((1 - d.totalClean / d.totalOriginal) * 100) : 0;
        $('statReduction').textContent = rPct + '%';
        $('sizeOriginal').textContent = kb(d.totalOriginal) + ' KB';
        $('sizeClean').textContent = kb(d.totalClean) + ' KB';

        const cPct = d.totalOriginal > 0 ? Math.round(d.totalClean / d.totalOriginal * 100) : 0;
        $('barUsed').style.width = cPct + '%'; $('barUsed').textContent = cPct + '% usado';
        $('barUnused').style.width = (100-cPct) + '%'; $('barUnused').textContent = (100-cPct) + '% eliminable';
        $('badgeFiles').textContent = r.length + ' archivos';

        const tbody = $('resultsTable');
        tbody.innerHTML = '';
        r.forEach((f, i) => {
            const rc = f.reduction_pct >= 80 ? 'text-bg-danger' : (f.reduction_pct >= 50 ? 'text-bg-warning' : 'text-bg-success');
            const bc = f.used_pct > 50 ? 'bg-success' : (f.used_pct > 20 ? 'bg-warning' : 'bg-danger');
            const nc = f.is_inline
                ? '<i class="bi bi-code-slash text-muted me-1"></i><span class="text-muted fst-italic">&lt;style&gt; inline #'+(i+1)+'</span>'
                : '<i class="bi bi-file-earmark-code text-primary me-1"></i><a href="'+esc(f.url)+'" target="_blank" class="text-decoration-none" title="'+esc(f.url)+'">'+esc(shortUrl(f.url))+'</a>';
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>'+nc+'</td>'+
                '<td class="text-center"><code>'+kb(f.original_size)+' KB</code></td>'+
                '<td class="text-center"><code class="text-success">'+kb(f.clean_size)+' KB</code></td>'+
                '<td class="text-center"><span class="badge '+rc+'">'+f.reduction_pct+'% menor</span></td>'+
                '<td class="text-center"><span class="text-success fw-bold">'+f.used_selectors+'</span><span class="text-muted">/</span>'+f.total_selectors+'</td>'+
                '<td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:8px"><div class="progress-bar '+bc+'" style="width:'+f.used_pct+'%"></div></div><small class="fw-bold" style="width:35px">'+f.used_pct+'%</small></div></td>';
            tbody.appendChild(tr);
        });

        $('cleanSummary').innerHTML = 'CSS unificado con solo los selectores usados. De <strong>'+kb(d.totalOriginal)+' KB</strong> a <strong class="text-success">'+kb(d.totalClean)+' KB</strong> ('+rPct+'% de reduccion).';

        // ─── JS Results ───
        const jsR = d.jsResults || [];
        const jsSection = $('jsSection');
        if (jsR.length > 0) {
            jsSection.classList.remove('d-none');
            $('badgeJsFiles').textContent = jsR.length + ' archivos';
            $('jsTotalSize').textContent = 'Total: ' + kb(d.totalJsSize) + ' KB → Unificado: ' + kb(d.unifiedJsSize) + ' KB';

            const jsTbody = $('jsResultsTable');
            jsTbody.innerHTML = '';
            jsR.forEach((f, i) => {
                const nc = f.is_inline
                    ? '<i class="bi bi-code-slash text-muted me-1"></i><span class="text-muted fst-italic">&lt;script&gt; inline #'+(i+1)+'</span>'
                    : '<i class="bi bi-filetype-js text-warning me-1"></i><a href="'+esc(f.url)+'" target="_blank" class="text-decoration-none" title="'+esc(f.url)+'">'+esc(shortUrl(f.url))+'</a>';
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>'+nc+'</td>'+
                    '<td class="text-center"><code>'+kb(f.size)+' KB</code></td>';
                jsTbody.appendChild(tr);
            });
        } else {
            jsSection.classList.add('d-none');
        }
    }

    // Preview toggle
    $('btnTogglePreview').addEventListener('click', function() {
        const p = $('cssPreview'), ta = $('cssTextarea');
        p.classList.toggle('d-none');
        if (!p.classList.contains('d-none') && !ta.value) {
            ta.value = 'Cargando...';
            fetch('/musedock/theme-extractor/clean-css', {credentials:'same-origin'}).then(r=>r.json()).then(d => { ta.value = d.css||'(vacio)'; });
        }
    });

    // Copy
    $('btnCopyCss').addEventListener('click', function() {
        const btn = this;
        fetch('/musedock/theme-extractor/clean-css', {credentials:'same-origin'}).then(r=>r.json()).then(d => {
            navigator.clipboard.writeText(d.css).then(() => {
                const o = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check me-1"></i> Copiado!';
                btn.classList.replace('btn-outline-primary','btn-success');
                setTimeout(() => { btn.innerHTML = o; btn.classList.replace('btn-success','btn-outline-primary'); }, 1500);
            });
        });
    });

    // ─── Clone & Preview ───
    let currentCloneId = null;
    let analyzedUrl = null;

    // Store the analyzed URL when results come in
    const origOnSuccess = onSuccess;
    function onSuccessWrapped(data) {
        if (data.success) analyzedUrl = inputUrl.value.trim();
        origOnSuccess(data);
    }
    // Patch: replace onSuccess reference used in fetch chain
    // (already bound inline, so we store analyzedUrl separately in renderFinal)

    $('btnClone').addEventListener('click', function() {
        const btn = this;
        const loading = $('cloneLoading');
        const area = $('clonePreviewArea');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando...';
        loading.classList.remove('d-none');
        area.classList.add('d-none');

        const includeJs = $('chkIncludeJs').checked ? '1' : '0';
        fetch('/musedock/theme-extractor/clone?include_js=' + includeJs, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                loading.classList.add('d-none');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-copy me-1"></i> Regenerar Clon';

                if (data.error) {
                    alert(data.error);
                    return;
                }

                currentCloneId = data.cloneId;
                const previewUrl = data.previewUrl;

                // Show buttons
                $('btnOpenPreview').classList.remove('d-none');
                $('btnDownloadClone').classList.remove('d-none');

                // Show comparison area
                area.classList.remove('d-none');
                $('cloneSizeLabel').textContent = kb(data.size) + ' KB';

                // Load iframes
                const srcUrl = inputUrl.value.trim();
                $('originalUrlLabel').textContent = srcUrl;
                $('originalUrlLink').href = srcUrl;
                $('originalOpenLink').href = srcUrl;

                // Original iframe: most sites block framing, show fallback directly
                $('frameOriginal').classList.add('d-none');
                $('frameOriginalBlocked').classList.remove('d-none');

                $('frameClone').src = previewUrl;
            })
            .catch(err => {
                loading.classList.add('d-none');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-copy me-1"></i> Generar Clon';
                alert('Error: ' + err.message);
            });
    });

    // Open in new tab
    $('btnOpenPreview').addEventListener('click', function() {
        if (currentCloneId) {
            window.open('/musedock/theme-extractor/preview?id=' + encodeURIComponent(currentCloneId), '_blank');
        }
    });

    // Download clone
    $('btnDownloadClone').addEventListener('click', function() {
        if (currentCloneId) {
            window.location.href = '/musedock/theme-extractor/download-clone?id=' + encodeURIComponent(currentCloneId);
        }
    });

    // View mode toggles
    ['btnViewSide', 'btnViewClone', 'btnViewOriginal'].forEach(id => {
        $(id).addEventListener('click', function() {
            ['btnViewSide', 'btnViewClone', 'btnViewOriginal'].forEach(b => $(b).classList.remove('active'));
            this.classList.add('active');
            const mode = this.dataset.view;
            const origCol = $('frameOriginalCol');
            const cloneCol = $('frameCloneCol');

            if (mode === 'side') {
                origCol.classList.remove('d-none'); origCol.className = 'col-md-6';
                cloneCol.classList.remove('d-none'); cloneCol.className = 'col-md-6';
            } else if (mode === 'clone') {
                origCol.classList.add('d-none');
                cloneCol.classList.remove('d-none'); cloneCol.className = 'col-12';
            } else {
                cloneCol.classList.add('d-none');
                origCol.classList.remove('d-none'); origCol.className = 'col-12';
            }
        });
    });

    btnExtract.addEventListener('click', go);
    inputUrl.addEventListener('keydown', e => { if (e.key === 'Enter') go(); });
})();
</script>
@endpush
@endsection
