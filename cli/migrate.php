#!/usr/bin/env php
<?php
/**
 * MuseDock Migration Runner
 * Compatible con MySQL/MariaDB y PostgreSQL
 *
 * Uso:
 *   php cli/migrate.php                     - Ejecutar todas las migraciones pendientes
 *   php cli/migrate.php --status            - Ver estado de migraciones
 *   php cli/migrate.php --run=FILENAME      - Ejecutar una migración específica
 *   php cli/migrate.php --rollback=FILENAME - Revertir una migración específica
 *   php cli/migrate.php --fresh             - Revertir todas y ejecutar de nuevo (¡CUIDADO!)
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definir constantes
define('APP_ROOT', dirname(__DIR__));
define('CLI_MODE', true);

// Cargar bootstrap
require_once APP_ROOT . '/core/bootstrap.php';

use Screenart\Musedock\Database;

class MigrationRunner
{
    private \PDO $pdo;
    private string $driver;
    private string $migrationsPath;
    private string $migrationsTable = 'migrations';

    public function __construct()
    {
        $this->pdo = Database::connect();
        $this->driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->migrationsPath = APP_ROOT . '/database/migrations';

        $this->ensureMigrationsTable();
    }

    /**
     * Asegurar que existe la tabla de migraciones
     */
    private function ensureMigrationsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `migration` VARCHAR(255) NOT NULL UNIQUE,
                    `batch` INT NOT NULL,
                    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            // PostgreSQL
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
    }

    /**
     * Obtener migraciones ya ejecutadas
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable} ORDER BY migration");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Obtener archivos de migración
     */
    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        $migrations = [];

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $migrations[$filename] = $file;
        }

        ksort($migrations);
        return $migrations;
    }

    /**
     * Obtener el siguiente número de batch
     */
    private function getNextBatch(): int
    {
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM {$this->migrationsTable}");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Extraer nombre de clase de un archivo de migración
     */
    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        // Buscar patrón: class NombreClase
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Ejecutar una migración específica
     */
    public function runMigration(string $filename): bool
    {
        $files = $this->getMigrationFiles();

        // Buscar el archivo (puede ser nombre parcial)
        $matchedFile = null;
        $matchedName = null;

        foreach ($files as $name => $path) {
            if ($name === $filename || strpos($name, $filename) !== false) {
                $matchedFile = $path;
                $matchedName = $name;
                break;
            }
        }

        if (!$matchedFile) {
            $this->error("Migración no encontrada: {$filename}");
            return false;
        }

        // Verificar si ya está ejecutada
        $executed = $this->getExecutedMigrations();
        if (in_array($matchedName, $executed)) {
            $this->warning("La migración ya está ejecutada: {$matchedName}");
            return false;
        }

        return $this->executeMigration($matchedName, $matchedFile);
    }

    /**
     * Ejecutar método up() de una migración
     */
    private function executeMigration(string $name, string $path): bool
    {
        $this->info("Ejecutando: {$name}");

        try {
            require_once $path;

            $className = $this->extractClassName($path);
            if (!$className || !class_exists($className)) {
                $this->error("No se encontró clase válida en: {$name}");
                return false;
            }

            $migration = new $className();

            if (!method_exists($migration, 'up')) {
                $this->error("La migración no tiene método up(): {$name}");
                return false;
            }

            $migration->up();

            // Registrar migración
            $batch = $this->getNextBatch();
            $stmt = $this->pdo->prepare("INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)");
            $stmt->execute([$name, $batch]);

            $this->success("Completada: {$name}");
            return true;

        } catch (\Exception $e) {
            $this->error("Error en {$name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revertir una migración específica
     */
    public function rollbackMigration(string $filename): bool
    {
        $files = $this->getMigrationFiles();

        // Buscar el archivo
        $matchedFile = null;
        $matchedName = null;

        foreach ($files as $name => $path) {
            if ($name === $filename || strpos($name, $filename) !== false) {
                $matchedFile = $path;
                $matchedName = $name;
                break;
            }
        }

        if (!$matchedFile) {
            $this->error("Migración no encontrada: {$filename}");
            return false;
        }

        // Verificar si está ejecutada
        $executed = $this->getExecutedMigrations();
        if (!in_array($matchedName, $executed)) {
            $this->warning("La migración no está ejecutada: {$matchedName}");
            return false;
        }

        $this->info("Revirtiendo: {$matchedName}");

        try {
            require_once $matchedFile;

            $className = $this->extractClassName($matchedFile);
            if (!$className || !class_exists($className)) {
                $this->error("No se encontró clase válida en: {$matchedName}");
                return false;
            }

            $migration = new $className();

            if (!method_exists($migration, 'down')) {
                $this->error("La migración no tiene método down(): {$matchedName}");
                return false;
            }

            $migration->down();

            // Eliminar registro
            $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
            $stmt->execute([$matchedName]);

            $this->success("Revertida: {$matchedName}");
            return true;

        } catch (\Exception $e) {
            $this->error("Error revirtiendo {$matchedName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ejecutar todas las migraciones pendientes
     */
    public function runAll(): void
    {
        $files = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();

        $pending = array_diff(array_keys($files), $executed);

        if (empty($pending)) {
            $this->info("No hay migraciones pendientes.");
            return;
        }

        $this->info("Ejecutando " . count($pending) . " migraciones pendientes...\n");

        $success = 0;
        $failed = 0;

        foreach ($pending as $name) {
            if ($this->executeMigration($name, $files[$name])) {
                $success++;
            } else {
                $failed++;
            }
        }

        echo "\n";
        $this->info("Completadas: {$success}, Fallidas: {$failed}");
    }

    /**
     * Mostrar estado de migraciones
     */
    public function showStatus(): void
    {
        $files = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();

        $this->info("Estado de migraciones ({$this->driver}):\n");

        echo str_pad("Estado", 12) . str_pad("Migración", 80) . "\n";
        echo str_repeat("-", 92) . "\n";

        foreach ($files as $name => $path) {
            $status = in_array($name, $executed) ? "\033[32m✓ Ejecutada\033[0m" : "\033[33m○ Pendiente\033[0m";
            echo str_pad($status, 22) . $name . "\n";
        }

        echo "\n";
        $pendingCount = count(array_diff(array_keys($files), $executed));
        $executedCount = count($executed);

        $this->info("Total: " . count($files) . " | Ejecutadas: {$executedCount} | Pendientes: {$pendingCount}");
    }

    /**
     * Fresh: Revertir todas y ejecutar de nuevo
     */
    public function fresh(): void
    {
        $this->warning("¡ATENCIÓN! Esto eliminará todas las tablas y datos.");
        echo "¿Estás seguro? Escribe 'yes' para confirmar: ";

        $handle = fopen("php://stdin", "r");
        $confirm = trim(fgets($handle));
        fclose($handle);

        if ($confirm !== 'yes') {
            $this->info("Operación cancelada.");
            return;
        }

        // Obtener migraciones ejecutadas en orden inverso
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable} ORDER BY migration DESC");
        $executed = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $files = $this->getMigrationFiles();

        // Revertir todas
        $this->info("\nRevirtiendo migraciones...\n");
        foreach ($executed as $name) {
            if (isset($files[$name])) {
                $this->rollbackMigration($name);
            }
        }

        // Ejecutar todas
        $this->info("\nEjecutando migraciones...\n");
        $this->runAll();
    }

    // Helpers para output
    private function info(string $msg): void
    {
        echo "\033[36m{$msg}\033[0m\n";
    }

    private function success(string $msg): void
    {
        echo "\033[32m✓ {$msg}\033[0m\n";
    }

    private function warning(string $msg): void
    {
        echo "\033[33m⚠ {$msg}\033[0m\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m✗ {$msg}\033[0m\n";
    }
}

// ========== MAIN ==========

echo "\n";
echo "\033[1;36m╔═══════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║      MuseDock Migration Runner        ║\033[0m\n";
echo "\033[1;36m╚═══════════════════════════════════════╝\033[0m\n\n";

$runner = new MigrationRunner();

// Parsear argumentos
$options = getopt('', ['status', 'run:', 'rollback:', 'fresh', 'help']);

if (isset($options['help']) || (isset($argv[1]) && $argv[1] === '--help')) {
    echo "Uso:\n";
    echo "  php cli/migrate.php                     - Ejecutar migraciones pendientes\n";
    echo "  php cli/migrate.php --status            - Ver estado de migraciones\n";
    echo "  php cli/migrate.php --run=FILENAME      - Ejecutar una migración específica\n";
    echo "  php cli/migrate.php --rollback=FILENAME - Revertir una migración específica\n";
    echo "  php cli/migrate.php --fresh             - Revertir todas y ejecutar de nuevo\n";
    echo "  php cli/migrate.php --help              - Mostrar esta ayuda\n\n";
    echo "Ejemplos:\n";
    echo "  php cli/migrate.php --run=000240_create_tenant_default_settings\n";
    echo "  php cli/migrate.php --rollback=2025_01_01_000240\n";
    exit(0);
}

if (isset($options['status'])) {
    $runner->showStatus();
} elseif (isset($options['run'])) {
    $runner->runMigration($options['run']);
} elseif (isset($options['rollback'])) {
    $runner->rollbackMigration($options['rollback']);
} elseif (isset($options['fresh'])) {
    $runner->fresh();
} else {
    $runner->runAll();
}

echo "\n";
