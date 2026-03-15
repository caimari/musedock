@extends('layouts.app')

@section('title', 'Relaciones - Cross-Publisher')

@section('content')
<div class="container-fluid p-4">
    <div class="mb-4">
        <h1 class="h3 mb-1"><i class="bi bi-diagram-3 me-2"></i>Relaciones y Sincronizacion</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/musedock/cross-publisher">Cross-Publisher</a></li>
                <li class="breadcrumb-item active">Relaciones</li>
            </ol>
        </nav>
    </div>

    @include('partials.alerts-sweetalert2')

    {{-- Filtros --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-center">
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="source_tenant_id">
                        <option value="">Origen: Todos</option>
                        @foreach($tenants as $t)
                        <option value="{{ $t->id }}" {{ ($_GET['source_tenant_id'] ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="sync_enabled">
                        <option value="">Sync: Todos</option>
                        <option value="1" {{ ($_GET['sync_enabled'] ?? '') === '1' ? 'selected' : '' }}>Sync activo</option>
                        <option value="0" {{ ($_GET['sync_enabled'] ?? '') === '0' ? 'selected' : '' }}>Sync inactivo</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if(empty($relations))
                <div class="text-center py-5"><p class="text-muted">No hay relaciones registradas.</p></div>
            @else

            {{-- Barra de acciones masivas --}}
            <form method="POST" action="/musedock/cross-publisher/relations/bulk-action" id="bulkForm">
                {!! csrf_field() !!}

                <div class="d-flex align-items-center gap-2 px-3 py-2 bg-light border-bottom" id="bulkBar">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="selectAll">
                        <label class="form-check-label small" for="selectAll">Todos</label>
                    </div>
                    <span class="text-muted small" id="selectedCount">0 seleccionados</span>
                    <select class="form-select form-select-sm" name="bulk_action" id="bulkAction" style="width: 200px;">
                        <option value="">-- Accion masiva --</option>
                        <option value="sync">Sincronizar (copiar del origen)</option>
                        <option value="readapt">Readaptar con IA</option>
                        <option value="delete">Eliminar relaciones</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" id="bulkSubmit" disabled>
                        <i class="bi bi-play-fill"></i> Ejecutar
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Post Fuente</th>
                                <th>Origen</th>
                                <th>Post Destino</th>
                                <th>Destino</th>
                                <th>Sync</th>
                                <th>Ultimo Sync</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($relations as $rel)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input row-check"
                                           name="relation_ids[]" value="{{ $rel->id }}">
                                </td>
                                <td>
                                    <strong>{{ $rel->source_title ?? 'Post #' . $rel->source_post_id }}</strong>
                                    @if($rel->source_status ?? false)
                                    <br><span class="badge bg-{{ $rel->source_status === 'published' ? 'success' : 'secondary' }} badge-sm">{{ $rel->source_status }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $rel->source_tenant_name ?? '' }}<br><span class="text-muted">{{ $rel->source_domain ?? '' }}</span></small></td>
                                <td>
                                    <strong>{{ $rel->target_title ?? 'Post #' . $rel->target_post_id }}</strong>
                                    @if($rel->target_status ?? false)
                                    <br><span class="badge bg-{{ $rel->target_status === 'published' ? 'success' : 'secondary' }} badge-sm">{{ $rel->target_status }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $rel->target_tenant_name ?? '' }}<br><span class="text-muted">{{ $rel->target_domain ?? '' }}</span></small></td>
                                <td>
                                    <form method="POST" action="/musedock/cross-publisher/relations/{{ $rel->id }}/toggle-sync" class="d-inline">
                                        {!! csrf_field() !!}
                                        <button type="submit" class="btn btn-sm {{ $rel->sync_enabled ? 'btn-success' : 'btn-outline-secondary' }}" title="Toggle sync">
                                            <i class="bi bi-{{ $rel->sync_enabled ? 'check-circle-fill' : 'circle' }}"></i>
                                            {{ $rel->sync_enabled ? 'Activo' : 'Inactivo' }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    @if($rel->last_synced_at)
                                        <small>{{ date('d/m/y H:i', strtotime($rel->last_synced_at)) }}</small>
                                        @php
                                            $needsSync = $rel->source_updated_at && $rel->last_synced_at && $rel->source_updated_at > $rel->last_synced_at;
                                        @endphp
                                        @if($needsSync)
                                            <br><span class="badge bg-warning text-dark">Desactualizado</span>
                                        @endif
                                    @else
                                        <small class="text-muted">Nunca</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        {{-- Sincronizar (copiar del origen) --}}
                                        <button type="button" class="btn btn-sm btn-outline-info btn-action-sync"
                                                data-id="{{ $rel->id }}"
                                                data-title="{{ $rel->source_title ?? 'Post #' . $rel->source_post_id }}"
                                                title="Sincronizar (copiar del origen)">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>

                                        {{-- Readaptar con IA --}}
                                        <button type="button" class="btn btn-sm btn-outline-success btn-action-readapt"
                                                data-id="{{ $rel->id }}"
                                                data-title="{{ $rel->source_title ?? 'Post #' . $rel->source_post_id }}"
                                                title="Readaptar con IA (reescribir para SEO)">
                                            <i class="bi bi-stars"></i>
                                        </button>

                                        {{-- Eliminar relacion --}}
                                        <form method="POST" action="/musedock/cross-publisher/relations/{{ $rel->id }}/delete" class="d-inline" onsubmit="return confirm('Eliminar relacion? El post destino NO se elimina.')">
                                            {!! csrf_field() !!}
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar relacion">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            @endif
        </div>
    </div>

    {{-- Leyenda --}}
    <div class="mt-3 small text-muted">
        <i class="bi bi-arrow-repeat text-info"></i> Sincronizar = copia literal del origen (titulo, contenido, SEO)
        &nbsp;|&nbsp;
        <i class="bi bi-stars text-success"></i> Readaptar IA = reescribe titulo, parrafos y SEO para indexacion independiente (consume tokens)
        &nbsp;|&nbsp;
        <i class="bi bi-trash text-danger"></i> Eliminar = quita la relacion, el post destino sigue existiendo
    </div>
</div>

@push('scripts')
<script>
(function() {
    var csrfEl = document.querySelector('input[name="_csrf"]') || document.querySelector('input[name="_token"]');
    var csrfToken = csrfEl ? csrfEl.value : '';

    // ═══════════════════════════════════════
    // Checkboxes y seleccion masiva
    // ═══════════════════════════════════════
    var selectAll = document.getElementById('selectAll');
    var rowChecks = document.querySelectorAll('.row-check');
    var selectedCount = document.getElementById('selectedCount');
    var bulkAction = document.getElementById('bulkAction');
    var bulkSubmit = document.getElementById('bulkSubmit');

    function updateCount() {
        var checked = document.querySelectorAll('.row-check:checked').length;
        selectedCount.textContent = checked + ' seleccionados';
        bulkSubmit.disabled = (checked === 0 || !bulkAction.value);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateCount();
        });
    }

    rowChecks.forEach(function(cb) {
        cb.addEventListener('change', function() {
            var allChecked = document.querySelectorAll('.row-check:checked').length === rowChecks.length;
            if (selectAll) selectAll.checked = allChecked;
            updateCount();
        });
    });

    if (bulkAction) {
        bulkAction.addEventListener('change', updateCount);
    }

    // ═══════════════════════════════════════
    // AJAX con SweetAlert2 - Accion individual
    // ═══════════════════════════════════════
    function doAction(url, actionLabel, confirmMsg, iconType) {
        Swal.fire({
            title: actionLabel,
            text: confirmMsg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Si, continuar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            // Mostrar modal de procesando con spinner CSS inline
            Swal.fire({
                title: actionLabel,
                html: '<style>'
                    + '@keyframes swal-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }'
                    + '</style>'
                    + '<div style="display:flex;flex-direction:column;align-items:center;padding:10px 0;">'
                    + '<div style="width:56px;height:56px;border:5px solid #e9ecef;border-top:5px solid #0d6efd;border-radius:50%;animation:swal-spin 1s linear infinite;margin-bottom:20px;"></div>'
                    + '<p style="margin:0 0 5px;font-size:16px;">Procesando, por favor espera...</p>'
                    + '<small style="color:#6c757d;">Esto puede tardar 10-30 segundos si usa IA</small>'
                    + '<div id="swal-timer" style="margin-top:10px;color:#6c757d;font-size:13px;">0s transcurridos</div>'
                    + '</div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function() {
                    var startTime = Date.now();
                    var interval = setInterval(function() {
                        var elapsed = Math.floor((Date.now() - startTime) / 1000);
                        var el = document.getElementById('swal-timer');
                        if (el) {
                            el.textContent = elapsed + 's transcurridos';
                        } else {
                            clearInterval(interval);
                        }
                    }, 1000);
                    Swal.getHtmlContainer().dataset.interval = interval;
                }
            });

            // Peticion AJAX
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: '_csrf=' + encodeURIComponent(csrfToken)
            })
            .then(function(response) {
                if (!response.ok && response.status === 419) {
                    throw new Error('Sesion expirada. Recarga la pagina.');
                }
                var contentType = response.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') === -1) {
                    throw new Error('Sesion expirada o error del servidor. Recarga la pagina.');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Completado',
                        text: data.message,
                        confirmButtonColor: '#198754'
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || data.error || 'Error desconocido',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(function(err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexion',
                    text: 'No se pudo completar la operacion: ' + err.message,
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    }

    // Boton Sincronizar individual
    document.querySelectorAll('.btn-action-sync').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            var title = this.dataset.title;
            doAction(
                '/musedock/cross-publisher/relations/' + id + '/resync',
                'Sincronizar',
                'Se copiara el contenido y SEO del origen al destino para: ' + title,
                'info'
            );
        });
    });

    // Boton Readaptar individual
    document.querySelectorAll('.btn-action-readapt').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            var title = this.dataset.title;
            doAction(
                '/musedock/cross-publisher/relations/' + id + '/readapt',
                'Readaptar con IA',
                'Se reescribira titulo, contenido y SEO del post destino usando IA. Se consumiran tokens. Post: ' + title,
                'warning'
            );
        });
    });

    // ═══════════════════════════════════════
    // AJAX con SweetAlert2 - Accion masiva
    // ═══════════════════════════════════════
    if (bulkSubmit) {
        bulkSubmit.addEventListener('click', function(e) {
            e.preventDefault();
            var action = bulkAction.value;
            var checkedBoxes = document.querySelectorAll('.row-check:checked');
            var count = checkedBoxes.length;

            if (!action || count === 0) return;

            var actionLabels = {
                'sync': 'Sincronizar',
                'readapt': 'Readaptar con IA',
                'delete': 'Eliminar'
            };
            var confirmMsgs = {
                'sync': 'Se copiara contenido y SEO del origen al destino en ' + count + ' relaciones.',
                'readapt': 'Se reescribira con IA titulo, contenido y SEO de ' + count + ' posts destino. Se consumiran tokens.',
                'delete': 'Se eliminaran ' + count + ' relaciones. Los posts destino NO se eliminan.'
            };
            var label = actionLabels[action] || action;
            var msg = confirmMsgs[action] || '';

            // Para delete usamos confirm simple y form submit normal
            if (action === 'delete') {
                Swal.fire({
                    title: 'Eliminar relaciones',
                    text: msg,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Si, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        document.getElementById('bulkForm').submit();
                    }
                });
                return;
            }

            // Para sync/readapt usamos AJAX
            Swal.fire({
                title: label + ' masivo',
                text: msg,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Si, continuar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754'
            }).then(function(result) {
                if (!result.isConfirmed) return;

                // Mostrar modal de procesando con spinner CSS inline
                Swal.fire({
                    title: label + ' masivo',
                    html: '<style>'
                        + '@keyframes swal-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }'
                        + '</style>'
                        + '<div style="display:flex;flex-direction:column;align-items:center;padding:10px 0;">'
                        + '<div style="width:56px;height:56px;border:5px solid #e9ecef;border-top:5px solid #0d6efd;border-radius:50%;animation:swal-spin 1s linear infinite;margin-bottom:20px;"></div>'
                        + '<p style="margin:0 0 5px;font-size:16px;">Procesando ' + count + ' relaciones...</p>'
                        + '<small style="color:#6c757d;">Esto puede tardar varios minutos si usa IA</small>'
                        + '<div id="swal-timer" style="margin-top:10px;color:#6c757d;font-size:13px;">0s transcurridos</div>'
                        + '</div>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function() {
                        var startTime = Date.now();
                        setInterval(function() {
                            var elapsed = Math.floor((Date.now() - startTime) / 1000);
                            var el = document.getElementById('swal-timer');
                            if (el) el.textContent = elapsed + 's transcurridos';
                        }, 1000);
                    }
                });

                // Construir body con los IDs seleccionados
                var params = '_csrf=' + encodeURIComponent(csrfToken);
                params += '&bulk_action=' + encodeURIComponent(action);
                checkedBoxes.forEach(function(cb) {
                    params += '&relation_ids[]=' + encodeURIComponent(cb.value);
                });

                fetch('/musedock/cross-publisher/relations/bulk-action', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: params
                })
                .then(function(response) {
                    if (!response.ok && response.status === 419) {
                        throw new Error('Sesion expirada. Recarga la pagina.');
                    }
                    var contentType = response.headers.get('content-type') || '';
                    if (contentType.indexOf('application/json') === -1) {
                        throw new Error('Sesion expirada o error del servidor. Recarga la pagina.');
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Completado',
                            text: data.message,
                            confirmButtonColor: '#198754'
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Resultado parcial',
                            text: data.message,
                            confirmButtonColor: '#ffc107'
                        }).then(function() {
                            location.reload();
                        });
                    }
                })
                .catch(function(err) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexion',
                        text: 'No se pudo completar la operacion: ' + err.message,
                        confirmButtonColor: '#dc3545'
                    });
                });
            });
        });
    }
})();
</script>
@endpush
@endsection
