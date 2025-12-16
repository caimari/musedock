#!/usr/bin/env php
<?php
/**
 * Script para sincronizar la tabla de migraciones
 * Marca como ejecutadas las migraciones cuyas tablas ya existen
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ROOT', dirname(__DIR__));
define('CLI_MODE', true);

require_once APP_ROOT . '/core/bootstrap.php';

use Screenart\Musedock\Database;

echo "\n";
echo "\033[1;36m╔═══════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║   Sincronizador de Migraciones            ║\033[0m\n";
echo "\033[1;36m╚═══════════════════════════════════════════╝\033[0m\n\n";

try {
    $pdo = Database::connect();

    // Obtener todas las tablas de la base de datos
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "\033[36mTablas encontradas en la base de datos: " . count($existingTables) . "\033[0m\n";

    // Obtener migraciones actuales en la tabla
    $stmt = $pdo->query("SELECT migration FROM migrations");
    $registeredMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "\033[36mMigraciones registradas: " . count($registeredMigrations) . "\033[0m\n\n";

    // Obtener archivos de migración
    $migrationFiles = glob(APP_ROOT . '/database/migrations/*.php');

    echo "\033[36mArchivos de migración encontrados: " . count($migrationFiles) . "\033[0m\n\n";

    // Procesar cada archivo de migración
    $toSync = [];

    foreach ($migrationFiles as $file) {
        $filename = basename($file, '.php');

        // Si ya está registrada, skip
        if (in_array($filename, $registeredMigrations)) {
            continue;
        }

        // Extraer el nombre de la tabla del nombre del archivo
        // Patrón: 2025_01_01_XXXXXX_create_TABLENAME_table.php
        if (preg_match('/create_(.+)_table$/', $filename, $matches)) {
            $tableName = $matches[1];

            // Verificar si la tabla existe
            if (in_array($tableName, $existingTables)) {
                $toSync[] = [
                    'migration' => $filename,
                    'table' => $tableName
                ];
            }
        }
    }

    if (empty($toSync)) {
        echo "\033[32m✓ No hay migraciones para sincronizar. Todo está actualizado.\033[0m\n\n";
        exit(0);
    }

    echo "\033[33mSe encontraron " . count($toSync) . " migraciones para sincronizar:\033[0m\n\n";

    foreach ($toSync as $item) {
        echo "  • {$item['migration']}\n";
        echo "    → Tabla '{$item['table']}' existe\n";
    }

    echo "\n\033[33m¿Deseas marcar estas migraciones como ejecutadas? (yes/no): \033[0m";

    $handle = fopen("php://stdin", "r");
    $confirm = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirm) !== 'yes') {
        echo "\n\033[36mOperación cancelada.\033[0m\n\n";
        exit(0);
    }

    // Obtener el siguiente batch
    $stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations");
    $nextBatch = (int) $stmt->fetchColumn();

    echo "\n\033[36mSincronizando migraciones (batch {$nextBatch})...\033[0m\n\n";

    $synced = 0;
    $failed = 0;

    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");

    foreach ($toSync as $item) {
        try {
            $stmt->execute([$item['migration'], $nextBatch]);
            echo "\033[32m✓\033[0m {$item['migration']}\n";
            $synced++;
        } catch (Exception $e) {
            echo "\033[31m✗\033[0m {$item['migration']}: " . $e->getMessage() . "\n";
            $failed++;
        }
    }

    echo "\n";
    echo "\033[36mResultados:\033[0m\n";
    echo "  Sincronizadas: \033[32m{$synced}\033[0m\n";
    echo "  Fallidas: \033[31m{$failed}\033[0m\n\n";

    if ($synced > 0) {
        echo "\033[32m✓ Sincronización completada exitosamente.\033[0m\n";
        echo "\033[36mAhora puedes ejecutar 'php cli/migrate.php' para aplicar las nuevas migraciones.\033[0m\n\n";
    }

} catch (Exception $e) {
    echo "\033[31m✗ Error: " . $e->getMessage() . "\033[0m\n\n";
    exit(1);
}
