<?php

/**
 * Bootstrap: Pseudo-Cron
 *
 * Este archivo se incluye automáticamente en cada request cuando
 * CRON_MODE=pseudo está configurado en .env
 *
 * SEGURIDAD:
 * - NO expone endpoint público
 * - Throttle: Solo ejecuta cada X segundos (configurable)
 * - Lock: Evita ejecuciones concurrentes
 * - Timeout protection: Las tareas tienen límite de ejecución
 *
 * USO:
 * Se incluye desde: public/index.php o bootstrap/app.php
 *
 * @package Screenart\Musedock\Bootstrap
 */

use Screenart\Musedock\Services\CronService;
use Screenart\Musedock\Services\Tasks\CleanupTrashTask;
use Screenart\Musedock\Services\Tasks\CleanupRevisionsTask;

// Solo ejecutar si el sistema de cron está en modo pseudo
$cronMode = getenv('CRON_MODE') ?: 'pseudo';

if ($cronMode !== 'pseudo') {
    return; // En modo 'real' o 'disabled', no ejecutar aquí
}

try {
    // Registrar tareas programadas
    // Cada tarea tiene un intervalo independiente

    // Tarea 1: Limpieza de papelera
    // Intervalo por defecto: 1 hora (ajustable en cada tarea)
    CronService::register(
        'cleanup_trash',
        function() {
            return CleanupTrashTask::run();
        },
        3600 // 1 hora
    );

    // Tarea 2: Limpieza de revisiones
    // Intervalo por defecto: 24 horas
    CronService::register(
        'cleanup_revisions',
        function() {
            return CleanupRevisionsTask::run();
        },
        86400 // 24 horas
    );

    // Ejecutar tareas (si toca según throttle)
    // Este método verificará automáticamente:
    // 1. Si ha pasado el intervalo global (PSEUDO_CRON_INTERVAL)
    // 2. Si cada tarea individual debe ejecutarse
    // 3. Si hay locks activos
    CronService::run();

} catch (\Exception $e) {
    // Log silencioso: No afectar la experiencia del usuario
    error_log("Pseudo-Cron error: " . $e->getMessage());
    // No mostrar nada al usuario, continuar con el request normal
}
