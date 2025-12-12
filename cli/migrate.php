#!/usr/bin/env php
<?php
/**
 * MuseDock Migration & Seeder Runner
 * Compatible con MySQL/MariaDB y PostgreSQL
 *
 * MIGRACIONES:
 *   php cli/migrate.php                        - Ejecutar migraciones pendientes
 *   php cli/migrate.php --status               - Ver estado de migraciones
 *   php cli/migrate.php --run=FILENAME         - Ejecutar una migración específica
 *   php cli/migrate.php --rerun=FILENAME       - Re-ejecutar migración (rollback + run)
 *   php cli/migrate.php --rollback=FILENAME    - Revertir una migración específica
 *   php cli/migrate.php --rollback-last        - Revertir última batch
 *   php cli/migrate.php --fresh                - Revertir todas y ejecutar de nuevo
 *   php cli/migrate.php --module=nombre        - Solo migraciones de un módulo
 *
 * SEEDERS:
 *   php cli/migrate.php seed                   - Ejecutar todos los seeders
 *   php cli/migrate.php seed --status          - Ver seeders disponibles
 *   php cli/migrate.php seed --run=NOMBRE      - Ejecutar un seeder específico
 *   php cli/migrate.php seed --rerun=NOMBRE    - Re-ejecutar seeder (aunque ya ejecutado)
 *   php cli/migrate.php seed --rollback=NOMBRE - Marcar seeder como no ejecutado
 *
 * COMBINADO:
 *   php cli/migrate.php --seed                 - Ejecutar migraciones + seeders
 *   php cli/migrate.php --fresh --seed         - Fresh + seeders
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ROOT', dirname(__DIR__));
define('CLI_MODE', true);

require_once APP_ROOT . '/core/bootstrap.php';

use Screenart\Musedock\Database;

class MigrationRunner
{
    private \PDO $pdo;
    private string $driver;
    private string $migrationsPath;
    private string $seedersPath;
    private string $migrationsTable = 'migrations';
    private string $seedersTable = 'seeders';

    public function __construct()
    {
        $this->pdo = Database::connect();
        $this->driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->migrationsPath = APP_ROOT . '/database/migrations';
        $this->seedersPath = APP_ROOT . '/database/seeders';

        $this->ensureTables();
    }

    // ========================================
    // SETUP TABLES
    // ========================================

    private function ensureTables(): void
    {
        // Tabla de migraciones
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `migration` VARCHAR(255) NOT NULL UNIQUE,
                    `batch` INT NOT NULL,
                    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `{$this->seedersTable}` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `seeder` VARCHAR(255) NOT NULL UNIQUE,
                    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->seedersTable} (
                    id SERIAL PRIMARY KEY,
                    seeder VARCHAR(255) NOT NULL UNIQUE,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
    }

    // ========================================
    // MIGRATIONS
    // ========================================

    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable} ORDER BY migration");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(?string $modulePath = null): array
    {
        $path = $modulePath ?? $this->migrationsPath;
        $files = glob($path . '/*.php');
        $migrations = [];

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $migrations[$filename] = $file;
        }

        ksort($migrations);
        return $migrations;
    }

    private function getNextBatch(): int
    {
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM {$this->migrationsTable}");
        return (int) $stmt->fetchColumn();
    }

    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function runMigration(string $filename): bool
    {
        $files = $this->getMigrationFiles();
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

        $executed = $this->getExecutedMigrations();
        if (in_array($matchedName, $executed)) {
            $this->warning("La migración ya está ejecutada: {$matchedName}");
            $this->info("Usa --rollback={$filename} primero si quieres re-ejecutarla");
            return false;
        }

        return $this->executeMigration($matchedName, $matchedFile);
    }

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

    public function rollbackMigration(string $filename): bool
    {
        $files = $this->getMigrationFiles();
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

            $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
            $stmt->execute([$matchedName]);

            $this->success("Revertida: {$matchedName}");
            return true;

        } catch (\Exception $e) {
            $this->error("Error revirtiendo {$matchedName}: " . $e->getMessage());
            return false;
        }
    }

    public function rerunMigration(string $filename): bool
    {
        $files = $this->getMigrationFiles();
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

        $executed = $this->getExecutedMigrations();

        $this->info("Re-ejecutando migración: {$matchedName}\n");

        // Si está ejecutada, hacer rollback primero
        if (in_array($matchedName, $executed)) {
            $this->info("Paso 1/2: Revirtiendo migración...");
            if (!$this->rollbackMigrationDirect($matchedName, $matchedFile)) {
                $this->error("No se pudo revertir la migración");
                return false;
            }
            echo "\n";
        } else {
            $this->info("La migración no estaba ejecutada, ejecutando directamente...\n");
        }

        // Ejecutar la migración
        $this->info("Paso 2/2: Ejecutando migración...");
        if ($this->executeMigration($matchedName, $matchedFile)) {
            echo "\n";
            $this->success("✓ Migración re-ejecutada exitosamente: {$matchedName}");
            return true;
        } else {
            $this->error("No se pudo ejecutar la migración");
            return false;
        }
    }

    public function rollbackLastBatch(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->migrationsTable}");
        $lastBatch = (int) $stmt->fetchColumn();

        if ($lastBatch === 0) {
            $this->info("No hay migraciones para revertir.");
            return 0;
        }

        $stmt = $this->pdo->prepare("SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY migration DESC");
        $stmt->execute([$lastBatch]);
        $migrations = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->info("Revirtiendo batch {$lastBatch} (" . count($migrations) . " migraciones)...\n");

        $reverted = 0;
        $files = $this->getMigrationFiles();

        foreach ($migrations as $name) {
            if (isset($files[$name])) {
                if ($this->rollbackMigrationDirect($name, $files[$name])) {
                    $reverted++;
                }
            }
        }

        return $reverted;
    }

    private function rollbackMigrationDirect(string $name, string $path): bool
    {
        $this->info("Revirtiendo: {$name}");

        try {
            require_once $path;

            $className = $this->extractClassName($path);
            if (!$className) return false;

            $migration = new $className();

            if (method_exists($migration, 'down')) {
                $migration->down();
            }

            $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
            $stmt->execute([$name]);

            $this->success("Revertida: {$name}");
            return true;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return false;
        }
    }

    public function runAll(?string $modulePath = null): void
    {
        $files = $this->getMigrationFiles($modulePath);
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

    public function showMigrationStatus(?string $modulePath = null): void
    {
        $files = $this->getMigrationFiles($modulePath);
        $executed = $this->getExecutedMigrations();

        $this->info("Estado de migraciones ({$this->driver}):\n");

        echo str_pad("Estado", 14) . "Migración\n";
        echo str_repeat("-", 100) . "\n";

        foreach ($files as $name => $path) {
            $isExecuted = in_array($name, $executed);
            $status = $isExecuted ? "\033[32m✓ Ejecutada\033[0m" : "\033[33m○ Pendiente\033[0m";
            echo str_pad($status, 24) . $name . "\n";
        }

        echo "\n";
        $pendingCount = count(array_diff(array_keys($files), $executed));
        $executedCount = count(array_intersect(array_keys($files), $executed));

        $this->info("Total: " . count($files) . " | Ejecutadas: {$executedCount} | Pendientes: {$pendingCount}");
    }

    public function fresh(): void
    {
        $this->warning("¡ATENCIÓN! Esto revertirá TODAS las migraciones.");
        echo "¿Estás seguro? Escribe 'yes' para confirmar: ";

        $handle = fopen("php://stdin", "r");
        $confirm = trim(fgets($handle));
        fclose($handle);

        if ($confirm !== 'yes') {
            $this->info("Operación cancelada.");
            return;
        }

        // Revertir todas las migraciones en orden inverso
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable} ORDER BY migration DESC");
        $executed = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $files = $this->getMigrationFiles();

        $this->info("\nRevirtiendo " . count($executed) . " migraciones...\n");

        foreach ($executed as $name) {
            if (isset($files[$name])) {
                $this->rollbackMigrationDirect($name, $files[$name]);
            } else {
                // Migración no existe en archivos, solo borrar registro
                $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
                $stmt->execute([$name]);
                $this->warning("Registro eliminado (archivo no existe): {$name}");
            }
        }

        // Limpiar tabla de seeders también
        $this->pdo->exec("DELETE FROM {$this->seedersTable}");

        $this->info("\nEjecutando todas las migraciones...\n");
        $this->runAll();
    }

    // ========================================
    // SEEDERS
    // ========================================

    private function getExecutedSeeders(): array
    {
        $stmt = $this->pdo->query("SELECT seeder FROM {$this->seedersTable} ORDER BY seeder");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getSeederFiles(): array
    {
        $files = glob($this->seedersPath . '/*Seeder.php');
        $seeders = [];

        foreach ($files as $file) {
            $filename = basename($file, '.php');
            // Excluir DatabaseSeeder ya que es el orquestador
            if ($filename !== 'DatabaseSeeder') {
                $seeders[$filename] = $file;
            }
        }

        ksort($seeders);
        return $seeders;
    }

    private function getSeederOrder(): array
    {
        // Orden definido en DatabaseSeeder
        return [
            'RolesAndPermissionsSeeder',
            'ModulesSeeder',
            'ThemesSeeder',
            'LanguagesSeeder',
            'SuperadminMenuSeeder',
            'AdminMenuSeeder',
        ];
    }

    public function showSeederStatus(): void
    {
        $files = $this->getSeederFiles();
        $executed = $this->getExecutedSeeders();
        $order = $this->getSeederOrder();

        $this->info("Seeders disponibles:\n");

        echo str_pad("Estado", 14) . str_pad("Orden", 8) . "Seeder\n";
        echo str_repeat("-", 60) . "\n";

        // Mostrar en orden definido primero
        $shown = [];
        $pos = 1;
        foreach ($order as $name) {
            if (isset($files[$name])) {
                $isExecuted = in_array($name, $executed);
                $status = $isExecuted ? "\033[32m✓ Ejecutado\033[0m" : "\033[33m○ Pendiente\033[0m";
                echo str_pad($status, 24) . str_pad($pos, 8) . $name . "\n";
                $shown[$name] = true;
                $pos++;
            }
        }

        // Mostrar otros no ordenados
        foreach ($files as $name => $path) {
            if (!isset($shown[$name])) {
                $isExecuted = in_array($name, $executed);
                $status = $isExecuted ? "\033[32m✓ Ejecutado\033[0m" : "\033[33m○ Pendiente\033[0m";
                echo str_pad($status, 24) . str_pad("-", 8) . $name . "\n";
            }
        }

        echo "\n";
        $pendingCount = count(array_diff(array_keys($files), $executed));
        $executedCount = count(array_intersect(array_keys($files), $executed));

        $this->info("Total: " . count($files) . " | Ejecutados: {$executedCount} | Pendientes: {$pendingCount}");
    }

    public function runSeeder(string $name, bool $force = false): bool
    {
        $files = $this->getSeederFiles();
        $matchedFile = null;
        $matchedName = null;

        foreach ($files as $seederName => $path) {
            if ($seederName === $name || stripos($seederName, $name) !== false) {
                $matchedFile = $path;
                $matchedName = $seederName;
                break;
            }
        }

        if (!$matchedFile) {
            $this->error("Seeder no encontrado: {$name}");
            return false;
        }

        $executed = $this->getExecutedSeeders();
        if (in_array($matchedName, $executed) && !$force) {
            $this->warning("El seeder ya está ejecutado: {$matchedName}");
            $this->info("Usa seed --rerun={$name} para re-ejecutarlo");
            return false;
        }

        return $this->executeSeeder($matchedName, $matchedFile, $force);
    }

    private function executeSeeder(string $name, string $path, bool $isRerun = false): bool
    {
        $action = $isRerun ? "Re-ejecutando" : "Ejecutando";
        $this->info("{$action}: {$name}");

        try {
            require_once $path;

            $className = "Screenart\\Musedock\\Database\\Seeders\\{$name}";

            if (!class_exists($className)) {
                // Intentar sin namespace
                $className = $name;
            }

            if (!class_exists($className)) {
                $this->error("Clase no encontrada: {$name}");
                return false;
            }

            $seeder = new $className();

            if (!method_exists($seeder, 'run')) {
                $this->error("El seeder no tiene método run(): {$name}");
                return false;
            }

            $seeder->run();

            // Registrar si no es rerun o actualizar timestamp si es rerun
            if ($isRerun) {
                $stmt = $this->pdo->prepare("UPDATE {$this->seedersTable} SET executed_at = NOW() WHERE seeder = ?");
                $stmt->execute([$name]);
                if ($stmt->rowCount() === 0) {
                    $stmt = $this->pdo->prepare("INSERT INTO {$this->seedersTable} (seeder) VALUES (?)");
                    $stmt->execute([$name]);
                }
            } else {
                $stmt = $this->pdo->prepare("INSERT IGNORE INTO {$this->seedersTable} (seeder) VALUES (?)");
                if ($this->driver === 'pgsql') {
                    $stmt = $this->pdo->prepare("INSERT INTO {$this->seedersTable} (seeder) VALUES (?) ON CONFLICT (seeder) DO NOTHING");
                }
                $stmt->execute([$name]);
            }

            $this->success("Completado: {$name}");
            return true;

        } catch (\Exception $e) {
            $this->error("Error en {$name}: " . $e->getMessage());
            return false;
        }
    }

    public function runAllSeeders(): void
    {
        $files = $this->getSeederFiles();
        $executed = $this->getExecutedSeeders();
        $order = $this->getSeederOrder();

        // Obtener pendientes en orden correcto
        $pending = [];
        foreach ($order as $name) {
            if (isset($files[$name]) && !in_array($name, $executed)) {
                $pending[$name] = $files[$name];
            }
        }

        // Añadir otros no ordenados
        foreach ($files as $name => $path) {
            if (!in_array($name, $executed) && !isset($pending[$name])) {
                $pending[$name] = $path;
            }
        }

        if (empty($pending)) {
            $this->info("No hay seeders pendientes.");
            return;
        }

        $this->info("Ejecutando " . count($pending) . " seeders pendientes...\n");

        $success = 0;
        $failed = 0;

        foreach ($pending as $name => $path) {
            if ($this->executeSeeder($name, $path)) {
                $success++;
            } else {
                $failed++;
            }
        }

        echo "\n";
        $this->info("Completados: {$success}, Fallidos: {$failed}");
    }

    public function rollbackSeeder(string $name): bool
    {
        $files = $this->getSeederFiles();
        $matchedName = null;

        foreach ($files as $seederName => $path) {
            if ($seederName === $name || stripos($seederName, $name) !== false) {
                $matchedName = $seederName;
                break;
            }
        }

        if (!$matchedName) {
            $this->error("Seeder no encontrado: {$name}");
            return false;
        }

        $executed = $this->getExecutedSeeders();
        if (!in_array($matchedName, $executed)) {
            $this->warning("El seeder no está marcado como ejecutado: {$matchedName}");
            return false;
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->seedersTable} WHERE seeder = ?");
        $stmt->execute([$matchedName]);

        $this->success("Seeder marcado como no ejecutado: {$matchedName}");
        $this->info("Nota: Los datos insertados por el seeder NO se eliminan automáticamente.");
        return true;
    }

    // ========================================
    // HELPERS OUTPUT
    // ========================================

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

// ========================================
// HELP
// ========================================

function showHelp() {
    echo "\033[1;36m";
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║           MuseDock Migration & Seeder Runner                     ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n";
    echo "\033[0m\n";

    echo "\033[1mMIGRACIONES:\033[0m\n";
    echo "  php cli/migrate.php                        Ejecutar pendientes\n";
    echo "  php cli/migrate.php --status               Ver estado\n";
    echo "  php cli/migrate.php --run=NOMBRE           Ejecutar una específica\n";
    echo "  php cli/migrate.php --rerun=NOMBRE         Re-ejecutar (rollback + run)\n";
    echo "  php cli/migrate.php --rollback=NOMBRE      Revertir una específica\n";
    echo "  php cli/migrate.php --rollback-last        Revertir última batch\n";
    echo "  php cli/migrate.php --fresh                Revertir todas + ejecutar\n";
    echo "  php cli/migrate.php --module=media-manager Solo un módulo\n";
    echo "\n";

    echo "\033[1mSEEDERS:\033[0m\n";
    echo "  php cli/migrate.php seed                   Ejecutar pendientes\n";
    echo "  php cli/migrate.php seed --status          Ver estado\n";
    echo "  php cli/migrate.php seed --run=NOMBRE      Ejecutar uno específico\n";
    echo "  php cli/migrate.php seed --rerun=NOMBRE    Re-ejecutar (forzar)\n";
    echo "  php cli/migrate.php seed --rollback=NOMBRE Marcar como no ejecutado\n";
    echo "\n";

    echo "\033[1mCOMBINADO:\033[0m\n";
    echo "  php cli/migrate.php --seed                 Migraciones + seeders\n";
    echo "  php cli/migrate.php --fresh --seed         Fresh + seeders\n";
    echo "\n";

    echo "\033[1mEJEMPLOS:\033[0m\n";
    echo "  php cli/migrate.php --run=000240_tenant    (búsqueda parcial)\n";
    echo "  php cli/migrate.php seed --run=Language    (búsqueda parcial)\n";
    echo "  php cli/migrate.php seed --rerun=AdminMenu Re-ejecutar seeder\n";
    echo "\n";
}

// ========================================
// MAIN
// ========================================

echo "\n";
echo "\033[1;36m╔═══════════════════════════════════════════╗\033[0m\n";
echo "\033[1;36m║   MuseDock Migration & Seeder Runner      ║\033[0m\n";
echo "\033[1;36m╚═══════════════════════════════════════════╝\033[0m\n\n";

// Parsear argumentos manualmente para mejor control
$command = '';
$options = [];

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];

    if ($arg === 'seed' || $arg === 'help') {
        $command = $arg;
    } elseif (strpos($arg, '--') === 0) {
        $parts = explode('=', substr($arg, 2), 2);
        $key = $parts[0];
        $value = $parts[1] ?? true;
        $options[$key] = $value;
    }
}

// Detectar si es comando seed
$isSeedCommand = ($command === 'seed');

if (isset($options['help']) || $command === 'help') {
    showHelp();
    exit(0);
}

$runner = new MigrationRunner();

// ========================================
// SEED COMMANDS
// ========================================

if ($isSeedCommand) {
    if (isset($options['status'])) {
        $runner->showSeederStatus();
    } elseif (isset($options['run'])) {
        $runner->runSeeder($options['run']);
    } elseif (isset($options['rerun'])) {
        $runner->runSeeder($options['rerun'], true);
    } elseif (isset($options['rollback'])) {
        $runner->rollbackSeeder($options['rollback']);
    } else {
        $runner->runAllSeeders();
    }
    echo "\n";
    exit(0);
}

// ========================================
// MIGRATION COMMANDS
// ========================================

$modulePath = null;
if (isset($options['module'])) {
    $modulePath = APP_ROOT . '/modules/' . $options['module'] . '/migrations';
    if (!is_dir($modulePath)) {
        echo "\033[31m✗ Módulo no encontrado: {$options['module']}\033[0m\n";
        exit(1);
    }
    echo "\033[36mModo módulo: {$options['module']}\033[0m\n\n";
}

if (isset($options['status'])) {
    $runner->showMigrationStatus($modulePath);
} elseif (isset($options['run'])) {
    $runner->runMigration($options['run']);
} elseif (isset($options['rerun'])) {
    $runner->rerunMigration($options['rerun']);
} elseif (isset($options['rollback'])) {
    $runner->rollbackMigration($options['rollback']);
} elseif (isset($options['rollback-last'])) {
    $runner->rollbackLastBatch();
} elseif (isset($options['fresh'])) {
    $runner->fresh();
    if (isset($options['seed'])) {
        echo "\n";
        $runner->runAllSeeders();
    }
} else {
    // Default: ejecutar migraciones pendientes
    $runner->runAll($modulePath);

    // Si tiene --seed, ejecutar seeders después
    if (isset($options['seed'])) {
        echo "\n";
        $runner->runAllSeeders();
    }
}

echo "\n";
