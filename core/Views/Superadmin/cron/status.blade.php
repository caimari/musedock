@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">{{ $title }}</h1>

            <!-- Modo de Cron Actual -->
            <div class="alert alert-{{ $cronMode === 'disabled' ? 'danger' : ($cronMode === 'real' ? 'success' : 'info') }}">
                <h5 class="alert-heading">
                    <i class="fas fa-{{ $cronMode === 'disabled' ? 'times-circle' : ($cronMode === 'real' ? 'server' : 'sync') }}"></i>
                    Modo Actual: <strong>{{ strtoupper($cronMode) }}</strong>
                </h5>
                @if($cronMode === 'pseudo')
                    <p class="mb-0">Sistema Pseudo-Cron activo. Las tareas se ejecutan automáticamente con cada visita al sitio (throttle: {{ $pseudoInterval }} segundos).</p>
                    @if($nextRunEstimate)
                        <p class="mb-0"><small>Próxima ejecución estimada: <strong>{{ $nextRunEstimate }}</strong></small></p>
                    @endif
                @elseif($cronMode === 'real')
                    <p class="mb-0">Sistema Cron Real activo. Las tareas se ejecutan mediante crontab del sistema.</p>
                @else
                    <p class="mb-0">Sistema de tareas programadas DESACTIVADO.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <!-- Estadísticas de Papelera -->
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header" style="background-color: #fff3cd; color: #856404;">
                    <h5 class="mb-0"><i class="fas fa-trash"></i> Papelera (Trash)</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Items en papelera:</strong></td>
                            <td class="text-end">{{ $trashStats['total_in_trash'] }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Páginas:</td>
                            <td class="text-end">{{ $trashStats['pages_in_trash'] }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Blog Posts:</td>
                            <td class="text-end">{{ $trashStats['posts_in_trash'] }}</td>
                        </tr>
                        <tr class="table-danger">
                            <td><strong>Listos para eliminar:</strong></td>
                            <td class="text-end"><strong>{{ $trashStats['total_ready_delete'] }}</strong></td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Páginas:</td>
                            <td class="text-end">{{ $trashStats['pages_ready_delete'] }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Blog Posts:</td>
                            <td class="text-end">{{ $trashStats['posts_ready_delete'] }}</td>
                        </tr>
                    </table>
                    <hr>
                    <small class="text-muted">
                        <strong>Configuración:</strong><br>
                        • Limpieza: {{ $trashEnabled ? 'Activada' : 'Desactivada' }}<br>
                        • Retención: {{ $trashRetentionDays }} días
                    </small>
                </div>
            </div>
        </div>

        <!-- Estadísticas de Revisiones -->
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header" style="background-color: #e7f1ff; color: #0d6efd;">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Revisiones (Historial)</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Total de revisiones:</strong></td>
                            <td class="text-end">{{ $revisionStats['total_revisions'] }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Páginas:</td>
                            <td class="text-end">{{ $revisionStats['page_revisions'] }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Blog Posts:</td>
                            <td class="text-end">{{ $revisionStats['post_revisions'] }}</td>
                        </tr>
                        <tr>
                            <td><strong>Promedio por documento:</strong></td>
                            <td class="text-end">-</td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Páginas:</td>
                            <td class="text-end">{{ $revisionStats['avg_page_revisions'] }}</td>
                        </tr>
                        <tr>
                            <td>&nbsp;&nbsp;• Blog Posts:</td>
                            <td class="text-end">{{ $revisionStats['avg_post_revisions'] }}</td>
                        </tr>
                    </table>
                    <hr>
                    <small class="text-muted">
                        <strong>Política de retención:</strong><br>
                        • Limpieza: {{ $revisionEnabled ? 'Activada' : 'Desactivada' }}<br>
                        • Últimas {{ $revisionKeepRecent }} revisiones: SIEMPRE<br>
                        • 1 por mes: últimos {{ $revisionKeepMonthly }} meses<br>
                        • 1 por año: últimos {{ $revisionKeepYearly }} años
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Estado de Tareas -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #cfe2ff; color: #084298;">
                    <h5 class="mb-0"><i class="fas fa-tasks"></i> Estado de Tareas Programadas</h5>
                    <form method="POST" action="/musedock/cron/run-manual" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-light btn-sm">
                            <i class="fas fa-play"></i> Ejecutar Manualmente (Testing)
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    @if(empty($tasks))
                        <div class="alert alert-warning">
                            No hay tareas registradas. El sistema se inicializará en la próxima ejecución.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tarea</th>
                                        <th>Estado</th>
                                        <th>Última Ejecución</th>
                                        <th>Próxima Ejecución</th>
                                        <th>Duración</th>
                                        <th>Ejecuciones</th>
                                        <th>Éxitos / Fallos</th>
                                        <th>Último Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tasks as $task)
                                        <tr>
                                            <td>
                                                <strong>{{ $task['task_name'] }}</strong>
                                            </td>
                                            <td>
                                                @if($task['status'] === 'running')
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-spinner fa-spin"></i> Ejecutando
                                                    </span>
                                                @elseif($task['status'] === 'failed')
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> Fallida
                                                    </span>
                                                @else
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle"></i> Inactiva
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $task['last_run'] ? date('d/m/Y H:i:s', strtotime($task['last_run'])) : 'Nunca' }}
                                            </td>
                                            <td>
                                                {{ $task['next_run'] ? date('d/m/Y H:i:s', strtotime($task['next_run'])) : 'N/A' }}
                                            </td>
                                            <td>
                                                {{ $task['last_duration'] ? $task['last_duration'] . 's' : '-' }}
                                            </td>
                                            <td class="text-center">
                                                {{ $task['run_count'] }}
                                            </td>
                                            <td class="text-center">
                                                <span class="text-success">{{ $task['success_count'] }}</span> /
                                                <span class="text-danger">{{ $task['fail_count'] }}</span>
                                            </td>
                                            <td>
                                                @if($task['last_error'])
                                                    <small class="text-danger" title="{{ $task['last_error'] }}">
                                                        {{ strlen($task['last_error']) > 50 ? substr($task['last_error'], 0, 50) . '...' : $task['last_error'] }}
                                                    </small>
                                                @else
                                                    <small class="text-muted">-</small>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Instrucciones de Configuración -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background-color: #e2e3e5; color: #41464b;">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Instrucciones de Configuración</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $cronMode === 'pseudo' ? 'active' : '' }}" data-bs-toggle="tab" href="#pseudo-cron">
                                <i class="fas fa-sync"></i> Pseudo-Cron (Hosting Compartido)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $cronMode === 'real' ? 'active' : '' }}" data-bs-toggle="tab" href="#real-cron">
                                <i class="fas fa-server"></i> Cron Real (VPS/Dedicado)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#config">
                                <i class="fas fa-cog"></i> Configuración .env
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content mt-3">
                        <!-- Pseudo-Cron -->
                        <div class="tab-pane fade {{ $cronMode === 'pseudo' ? 'show active' : '' }}" id="pseudo-cron">
                            <h6><strong>¿Qué es Pseudo-Cron?</strong></h6>
                            <p>
                                Es un sistema que ejecuta tareas programadas <strong>sin necesidad de acceso al crontab del servidor</strong>.
                                Ideal para <strong>hosting compartido</strong> donde no tienes acceso a configurar cron jobs.
                            </p>

                            <h6><strong>¿Cómo funciona?</strong></h6>
                            <ul>
                                <li>Las tareas se ejecutan automáticamente con cada visita al sitio</li>
                                <li>Usa <strong>throttle basado en tiempo</strong> para evitar ejecuciones excesivas</li>
                                <li>Sistema de <strong>locks</strong> para prevenir ejecuciones concurrentes</li>
                                <li>Configuración del intervalo en <code>.env</code></li>
                            </ul>

                            <h6><strong>Configuración en .env:</strong></h6>
                            <pre class="bg-light p-3 border rounded"><code># Modo Pseudo-Cron
CRON_MODE=pseudo
PSEUDO_CRON_INTERVAL=3600  # Ejecutar máximo 1 vez cada hora (3600 segundos)</code></pre>

                            <h6><strong>Ventajas:</strong></h6>
                            <ul>
                                <li>✅ No requiere acceso al servidor</li>
                                <li>✅ Funciona en cualquier hosting</li>
                                <li>✅ Fácil de configurar</li>
                            </ul>

                            <h6><strong>Desventajas:</strong></h6>
                            <ul>
                                <li>⚠️ Solo ejecuta si hay visitas al sitio</li>
                                <li>⚠️ Menos preciso que cron real</li>
                            </ul>
                        </div>

                        <!-- Cron Real -->
                        <div class="tab-pane fade {{ $cronMode === 'real' ? 'show active' : '' }}" id="real-cron">
                            <h6><strong>¿Qué es Cron Real?</strong></h6>
                            <p>
                                Sistema tradicional de tareas programadas del servidor. Requiere <strong>acceso SSH y crontab</strong>.
                                Ideal para <strong>VPS o servidores dedicados</strong>.
                            </p>

                            <h6><strong>Configuración en .env:</strong></h6>
                            <pre class="bg-light p-3 border rounded"><code># Modo Cron Real
CRON_MODE=real</code></pre>

                            <h6><strong>Configuración en crontab del servidor:</strong></h6>
                            <pre class="bg-light p-3 border rounded"><code># Editar crontab
crontab -e

# Ejecutar cada hora
0 * * * * /usr/bin/php /ruta/completa/a/musedock/cli/cron.php >> /var/log/musedock-cron.log 2>&1

# Ejecutar cada 6 horas
0 */6 * * * /usr/bin/php /ruta/completa/a/musedock/cli/cron.php >> /var/log/musedock-cron.log 2>&1

# Ejecutar diariamente a las 3 AM
0 3 * * * /usr/bin/php /ruta/completa/a/musedock/cli/cron.php >> /var/log/musedock-cron.log 2>&1</code></pre>

                            <h6><strong>Verificar que funciona (testing cada 5 minutos):</strong></h6>
                            <pre class="bg-light p-3 border rounded"><code>*/5 * * * * /usr/bin/php /ruta/completa/a/musedock/cli/cron.php >> /tmp/musedock-cron.log 2>&1

# Luego ver el log:
tail -f /tmp/musedock-cron.log</code></pre>

                            <h6><strong>Prueba manual desde SSH:</strong></h6>
                            <pre class="bg-light p-3 border rounded"><code>php /ruta/completa/a/musedock/cli/cron.php</code></pre>

                            <h6><strong>Ventajas:</strong></h6>
                            <ul>
                                <li>✅ Ejecución precisa y programada</li>
                                <li>✅ No depende de visitas al sitio</li>
                                <li>✅ Mejor rendimiento</li>
                            </ul>

                            <h6><strong>Desventajas:</strong></h6>
                            <ul>
                                <li>⚠️ Requiere acceso SSH al servidor</li>
                                <li>⚠️ Solo disponible en VPS/dedicados</li>
                            </ul>
                        </div>

                        <!-- Configuración .env -->
                        <div class="tab-pane fade" id="config">
                            <h6><strong>Configuración completa en .env:</strong></h6>
                            <pre class="bg-light p-3 border rounded"><code># ================================================
# SISTEMA DE TAREAS PROGRAMADAS (CRON/PSEUDO-CRON)
# ================================================

# Modo de ejecución: pseudo | real | disabled
# - pseudo: Ejecuta en cada request con throttle (hosting compartido)
# - real: Ejecuta vía crontab (VPS/dedicado)
# - disabled: Desactiva todas las tareas programadas
CRON_MODE=pseudo

# Throttle para Pseudo-Cron (segundos)
# Solo ejecuta si han pasado X segundos desde última ejecución
# 3600 = 1 hora, 7200 = 2 horas, 86400 = 24 horas
PSEUDO_CRON_INTERVAL=3600

# ================================================
# LIMPIEZA AUTOMÁTICA DE PAPELERA (TRASH)
# ================================================

# Habilitar limpieza automática de items en papelera
TRASH_AUTO_DELETE_ENABLED=true

# Días de retención antes de eliminación permanente
TRASH_RETENTION_DAYS=30

# Días antes de eliminación para notificar (futuro)
TRASH_NOTIFY_BEFORE_DAYS=7

# ================================================
# LIMPIEZA AUTOMÁTICA DE REVISIONES (HISTORIAL)
# ================================================

# Habilitar limpieza automática de revisiones antiguas
REVISION_CLEANUP_ENABLED=true

# Política Mixta de Retención:
# 1. Últimas N revisiones: SIEMPRE se guardan
REVISION_KEEP_RECENT=5

# 2. Revisiones antiguas: 1 por mes (últimos X meses)
REVISION_KEEP_MONTHLY=12

# 3. Revisiones muy antiguas: 1 por año (últimos X años)
REVISION_KEEP_YEARLY=3

# Resto de revisiones: Se eliminan automáticamente</code></pre>

                            <h6 class="mt-4"><strong>Recomendaciones por tipo de hosting:</strong></h6>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Tipo de Hosting</th>
                                        <th>CRON_MODE</th>
                                        <th>PSEUDO_CRON_INTERVAL</th>
                                        <th>Recomendación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Hosting Compartido</td>
                                        <td><code>pseudo</code></td>
                                        <td><code>3600</code> (1 hora)</td>
                                        <td>Balance entre rendimiento y limpieza frecuente</td>
                                    </tr>
                                    <tr>
                                        <td>Hosting con poco tráfico</td>
                                        <td><code>pseudo</code></td>
                                        <td><code>7200</code> (2 horas)</td>
                                        <td>Menos carga en el servidor</td>
                                    </tr>
                                    <tr>
                                        <td>VPS / Dedicado</td>
                                        <td><code>real</code></td>
                                        <td><code>N/A</code></td>
                                        <td>Usar crontab del sistema (más eficiente)</td>
                                    </tr>
                                    <tr>
                                        <td>Desarrollo / Testing</td>
                                        <td><code>pseudo</code></td>
                                        <td><code>60</code> (1 minuto)</td>
                                        <td>Para probar rápidamente</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
