<?php
/**
 * Script de migración para mover archivos del almacenamiento público al seguro.
 *
 * Este script:
 * 1. Busca todos los archivos en disco 'local' (en /public/assets/uploads/)
 * 2. Los mueve a disco 'media' (en /storage/app/media/)
 * 3. Actualiza la base de datos con el nuevo disco
 *
 * Ejecutar desde la línea de comandos:
 * php modules/media-manager/cli/migrate-to-secure-storage.php [--dry-run] [--limit=N]
 *
 * Opciones:
 *   --dry-run   Solo mostrar lo que se haría, sin hacer cambios
 *   --limit=N   Procesar máximo N archivos (útil para pruebas)
 *   --verbose   Mostrar más detalles
 *
 * IMPORTANTE: Hacer backup antes de ejecutar!
 */

// Verificar que se ejecuta desde CLI
if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

// Cargar el bootstrap de la aplicación
require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

use MediaManager\Models\Media;
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

// Parsear argumentos
$args = getopt('', ['dry-run', 'limit:', 'verbose']);
$dryRun = isset($args['dry-run']);
$limit = isset($args['limit']) ? (int)$args['limit'] : 0;
$verbose = isset($args['verbose']);

// Directorios
$sourceRoot = APP_ROOT . '/public/assets/uploads';
$destRoot = APP_ROOT . '/storage/app/media';

// Colores para la terminal
$colorReset = "\033[0m";
$colorGreen = "\033[32m";
$colorYellow = "\033[33m";
$colorRed = "\033[31m";
$colorBlue = "\033[34m";

echo "\n";
echo "{$colorBlue}╔══════════════════════════════════════════════════════════════╗{$colorReset}\n";
echo "{$colorBlue}║  MIGRACIÓN DE ARCHIVOS A ALMACENAMIENTO SEGURO              ║{$colorReset}\n";
echo "{$colorBlue}╚══════════════════════════════════════════════════════════════╝{$colorReset}\n\n";

if ($dryRun) {
    echo "{$colorYellow}⚠ MODO DRY-RUN: No se realizarán cambios reales{$colorReset}\n\n";
}

echo "Directorio origen:  {$sourceRoot}\n";
echo "Directorio destino: {$destRoot}\n\n";

// Verificar que los directorios existen
if (!is_dir($sourceRoot)) {
    echo "{$colorRed}✗ El directorio origen no existe: {$sourceRoot}{$colorReset}\n";
    exit(1);
}

if (!is_dir($destRoot)) {
    echo "{$colorYellow}Creando directorio destino...{$colorReset}\n";
    if (!$dryRun) {
        mkdir($destRoot, 0755, true);
    }
}

// Obtener archivos a migrar
echo "Buscando archivos en disco 'local'...\n";

try {
    $query = Media::query()->where('disk', 'local');
    if ($limit > 0) {
        $query->limit($limit);
    }
    $mediaItems = $query->get();
} catch (\Exception $e) {
    echo "{$colorRed}✗ Error al consultar la base de datos: {$e->getMessage()}{$colorReset}\n";
    exit(1);
}

$total = count($mediaItems);
echo "Encontrados: {$total} archivos\n\n";

if ($total === 0) {
    echo "{$colorGreen}✓ No hay archivos para migrar{$colorReset}\n";
    exit(0);
}

// Contadores
$migrated = 0;
$skipped = 0;
$errors = 0;

// Procesar cada archivo
foreach ($mediaItems as $index => $media) {
    $progress = $index + 1;
    $percent = round(($progress / $total) * 100);

    $sourcePath = $sourceRoot . '/' . $media->path;
    $destPath = $destRoot . '/' . $media->path;

    if ($verbose) {
        echo "\n[{$progress}/{$total}] {$media->filename}\n";
        echo "  Origen:  {$sourcePath}\n";
        echo "  Destino: {$destPath}\n";
    } else {
        echo "\r[{$percent}%] Procesando {$progress}/{$total}: {$media->filename}...";
    }

    // Verificar si el archivo origen existe
    if (!file_exists($sourcePath)) {
        if ($verbose) {
            echo "  {$colorYellow}⚠ Archivo origen no existe, saltando{$colorReset}\n";
        }
        $skipped++;
        continue;
    }

    // Verificar si ya existe en destino
    if (file_exists($destPath)) {
        if ($verbose) {
            echo "  {$colorYellow}⚠ Ya existe en destino, saltando{$colorReset}\n";
        }
        $skipped++;
        continue;
    }

    if (!$dryRun) {
        try {
            // Crear directorio destino si no existe
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            // Copiar archivo (no mover, para seguridad)
            if (!copy($sourcePath, $destPath)) {
                throw new \Exception("No se pudo copiar el archivo");
            }

            // Establecer permisos
            chmod($destPath, 0644);

            // Actualizar base de datos
            $media->disk = 'media';
            $media->save();

            if ($verbose) {
                echo "  {$colorGreen}✓ Migrado correctamente{$colorReset}\n";
            }

            $migrated++;

        } catch (\Exception $e) {
            if ($verbose) {
                echo "  {$colorRed}✗ Error: {$e->getMessage()}{$colorReset}\n";
            }
            Logger::error("Error migrando media {$media->id}: " . $e->getMessage());
            $errors++;
        }
    } else {
        // Dry run
        if ($verbose) {
            echo "  {$colorBlue}→ Se migraría{$colorReset}\n";
        }
        $migrated++;
    }
}

// Resumen
echo "\n\n";
echo "{$colorBlue}════════════════════════════════════════════════════════════════{$colorReset}\n";
echo "                         RESUMEN\n";
echo "{$colorBlue}════════════════════════════════════════════════════════════════{$colorReset}\n\n";

if ($dryRun) {
    echo "{$colorYellow}(Modo dry-run - ningún cambio realizado){$colorReset}\n\n";
}

echo "Total procesados: {$total}\n";
echo "{$colorGreen}Migrados:         {$migrated}{$colorReset}\n";
echo "{$colorYellow}Saltados:         {$skipped}{$colorReset}\n";
echo "{$colorRed}Errores:          {$errors}{$colorReset}\n\n";

if (!$dryRun && $migrated > 0) {
    echo "{$colorYellow}IMPORTANTE: Los archivos originales en /public/assets/uploads/ NO fueron eliminados.{$colorReset}\n";
    echo "{$colorYellow}Una vez verificado que todo funciona correctamente, puedes eliminarlos manualmente.{$colorReset}\n\n";
}

if ($dryRun) {
    echo "Para ejecutar la migración real, ejecuta sin --dry-run:\n";
    echo "  php modules/media-manager/cli/migrate-to-secure-storage.php\n\n";
}

exit($errors > 0 ? 1 : 0);
