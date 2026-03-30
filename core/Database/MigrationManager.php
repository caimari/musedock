<?php

namespace Screenart\Musedock\Database;

use PDO;
use PDOException;

/**
 * Sistema de Migraciones estilo Laravel
 * Gestiona la ejecución automática de migraciones SQL
 */
class MigrationManager
{
    private PDO $db;
    private string $migrationsPath;
    private string $driver; // 'mysql' o 'pgsql'
    private array $output = [];
    private bool $verbose = true;

    /**
     * Constructor
     * @param PDO $db Conexión a la base de datos
     * @param string $migrationsPath Ruta donde están las migraciones
     * @param string $driver Tipo de base de datos (mysql o pgsql)
     */
    public function __construct(PDO $db, string $migrationsPath, string $driver = 'mysql')
    {
        $this->db = $db;
        $this->migrationsPath = rtrim($migrationsPath, '/');
        $this->driver = strtolower($driver);
    }

    /**
     * Activa o desactiva output verbose
     */
    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * Crea la tabla de control de migraciones si no existe
     */
    public function createMigrationsTable(): bool
    {
        try {
            if ($this->driver === 'mysql') {
                $sql = "CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_batch (batch)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            } else {
                $sql = "CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";

                $this->db->exec($sql);
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_batch ON migrations(batch)");
                return true;
            }

            $this->db->exec($sql);
            $this->log('✓ Tabla de migraciones verificada/creada', 'success');
            return true;

        } catch (PDOException $e) {
            $this->log('✗ Error creando tabla de migraciones: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Ejecuta todas las migraciones pendientes
     * @param string|null $modulePath Ruta específica de un módulo (opcional)
     * @return array Resultado de la ejecución
     */
    public function migrate(?string $modulePath = null): array
    {
        $this->createMigrationsTable();

        $pendingMigrations = $this->getPendingMigrations($modulePath);

        if (empty($pendingMigrations)) {
            $this->log('✓ No hay migraciones pendientes', 'info');
            return [
                'success' => true,
                'executed' => 0,
                'message' => 'No hay migraciones pendientes'
            ];
        }

        $this->log("\n" . count($pendingMigrations) . " migración(es) pendiente(s):\n", 'info');

        $batch = $this->getNextBatchNumber();
        $executed = 0;
        $errors = [];

        foreach ($pendingMigrations as $migration) {
            $this->log("→ Ejecutando: {$migration['name']}", 'info');

            try {
                $type = $migration['type'] ?? 'sql';
                $result = $this->executeMigration($migration['path'], $migration['name'], $type);

                if ($result) {
                    $this->recordMigration($migration['name'], $batch);
                    $executed++;
                    $this->log("  ✓ Completada", 'success');
                } else {
                    $errors[] = $migration['name'];
                    $this->log("  ✗ Error ejecutando migración", 'error');
                }

            } catch (\Exception $e) {
                $errors[] = $migration['name'] . ': ' . $e->getMessage();
                $this->log("  ✗ Error: " . $e->getMessage(), 'error');
            }
        }

        $summary = "\n" . str_repeat('=', 50) . "\n";
        $summary .= "✓ Migraciones ejecutadas: {$executed}\n";

        if (!empty($errors)) {
            $summary .= "✗ Errores: " . count($errors) . "\n";
            foreach ($errors as $error) {
                $summary .= "  - {$error}\n";
            }
        }

        $summary .= str_repeat('=', 50) . "\n";
        $this->log($summary, empty($errors) ? 'success' : 'warning');

        return [
            'success' => empty($errors),
            'executed' => $executed,
            'errors' => $errors,
            'message' => empty($errors)
                ? "Se ejecutaron {$executed} migración(es) exitosamente"
                : "Se ejecutaron {$executed} migración(es) con " . count($errors) . " error(es)"
        ];
    }

    /**
     * Obtiene las migraciones pendientes
     */
    private function getPendingMigrations(?string $modulePath = null): array
    {
        $executedMigrations = $this->getExecutedMigrations();
        $allMigrations = $this->scanMigrationFiles($modulePath);

        return array_filter($allMigrations, function($migration) use ($executedMigrations) {
            return !in_array($migration['name'], $executedMigrations);
        });
    }

    /**
     * Escanea los archivos de migración
     */
    private function scanMigrationFiles(?string $modulePath = null): array
    {
        $migrations = [];
        $paths = [];

        if ($modulePath) {
            // Escanear solo un módulo específico
            $paths[] = $modulePath;
        } else {
            // Escanear todas las rutas de migraciones
            $paths[] = $this->migrationsPath;

            // Buscar migraciones en módulos
            $modulesPath = APP_ROOT . '/modules';
            if (is_dir($modulesPath)) {
                $modules = glob($modulesPath . '/*', GLOB_ONLYDIR);
                foreach ($modules as $module) {
                    $migrationDir = $module . '/migrations';
                    if (is_dir($migrationDir)) {
                        $paths[] = $migrationDir;
                    }
                }
            }
        }

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            // Buscar archivos SQL
            $sqlFiles = glob($path . '/*.sql');

            foreach ($sqlFiles as $file) {
                $basename = basename($file);

                // Solo archivos que empiecen con número (formato: 001_nombre.sql)
                // EXCLUIR archivos _down.sql (son para rollback)
                if (preg_match('/^\d+_.*\.sql$/', $basename) && !preg_match('/_down\.sql$/', $basename)) {
                    $migrations[] = [
                        'name' => $basename,
                        'path' => $file,
                        'type' => 'sql'
                    ];
                }
            }

            // Buscar archivos PHP (nuevo soporte)
            $phpFiles = glob($path . '/*.php');

            foreach ($phpFiles as $file) {
                $basename = basename($file);

                // Formato: YYYY_MM_DD_HHMMSS_nombre.php o similar
                // Debe tener un timestamp o número al inicio
                if (preg_match('/^\d{4}_\d{2}_\d{2}_\d+_.*\.php$/', $basename)) {
                    $migrations[] = [
                        'name' => $basename,
                        'path' => $file,
                        'type' => 'php'
                    ];
                }
            }
        }

        // Ordenar por nombre de archivo (orden numérico/alfabético)
        usort($migrations, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $migrations;
    }

    /**
     * Obtiene las migraciones ya ejecutadas
     */
    private function getExecutedMigrations(): array
    {
        try {
            $stmt = $this->db->query("SELECT migration FROM migrations ORDER BY id");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Ejecuta un archivo de migración
     */
    private function executeMigration(string $filePath, string $filename, string $type = 'sql'): bool
    {
        // Si es una migración PHP, ejecutarla de manera diferente
        if ($type === 'php') {
            return $this->executePHPMigration($filePath, $filename);
        }

        // Migración SQL tradicional
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \Exception("No se pudo leer el archivo: {$filePath}");
        }

        // Detectar la sección correcta según el driver
        $section = $this->extractDriverSection($content);

        if (empty($section)) {
            // Si no hay secciones específicas, usar todo el contenido
            $section = $content;
        }

        // Limpiar comentarios
        $section = $this->cleanSqlComments($section);

        // Dividir en statements individuales
        $statements = $this->splitSqlStatements($section);

        // Verificar si hay DDL statements que causan commit implícito en MySQL
        $hasDDL = $this->containsDDLStatements($statements);

        try {
            // Solo usar transacción si no hay DDL o no es MySQL
            $useTransaction = !($hasDDL && $this->driver === 'mysql');

            if ($useTransaction) {
                $this->db->beginTransaction();
            }

            foreach ($statements as $statement) {
                $statement = trim($statement);

                if (empty($statement)) {
                    continue;
                }

                try {
                    $this->db->exec($statement);
                } catch (PDOException $e) {
                    // Si falla un statement, lanzar excepción
                    throw $e;
                }
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            return true;

        } catch (PDOException $e) {
            if ($useTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \Exception("Error SQL: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta una migración PHP (clase con métodos up() y down())
     * Soporta tanto clases nombradas como clases anónimas (return new class extends Migration)
     */
    private function executePHPMigration(string $filePath, string $filename): bool
    {
        if (!file_exists($filePath)) {
            throw new \Exception("No se encontró el archivo: {$filePath}");
        }

        // Incluir el archivo y capturar el retorno (para clases anónimas)
        $migration = require $filePath;

        // Si el archivo retorna un objeto (clase anónima)
        if (is_object($migration)) {
            // Es una clase anónima, verificar que tenga el método up()
            if (!method_exists($migration, 'up')) {
                throw new \Exception("La migración anónima no tiene el método up()");
            }

            // Inyectar la conexión a la base de datos si tiene el método setConnection
            if (method_exists($migration, 'setConnection')) {
                $migration->setConnection($this->db, $this->driver);
            }
        } else {
            // Es una clase nombrada tradicional
            $className = $this->extractClassNameFromFile($filename);

            if (!class_exists($className)) {
                throw new \Exception("No se encontró la clase de migración: {$className} en {$filePath}");
            }

            // Instanciar la clase
            $migration = new $className();

            // Verificar que tenga el método up()
            if (!method_exists($migration, 'up')) {
                throw new \Exception("La clase {$className} no tiene el método up()");
            }

            // Inyectar la conexión si tiene el método
            if (method_exists($migration, 'setConnection')) {
                $migration->setConnection($this->db, $this->driver);
            }
        }

        // Ejecutar la migración
        try {
            // Capturar el output del método up()
            ob_start();
            $migration->up();
            $output = ob_get_clean();

            // Mostrar el output si existe
            if (!empty($output)) {
                $this->log($output, 'info');
            }

            return true;

        } catch (\Exception $e) {
            ob_end_clean();
            throw new \Exception("Error ejecutando migración PHP: " . $e->getMessage());
        }
    }

    /**
     * Extrae el nombre de la clase desde el nombre del archivo de migración
     * Formato archivo: 2025_11_12_120000_create_admin_tenant_menus_table.php
     * Formato clase: CreateAdminTenantMenusTable_2025_11_12_120000
     */
    private function extractClassNameFromFile(string $filename): string
    {
        // Remover la extensión .php
        $name = str_replace('.php', '', $filename);

        // Dividir por guion bajo
        $parts = explode('_', $name);

        // Los primeros 4 elementos son la fecha (YYYY_MM_DD_HHMMSS)
        $timestamp = array_slice($parts, 0, 4);
        $nameParts = array_slice($parts, 4);

        // Convertir el nombre a PascalCase
        $className = implode('', array_map('ucfirst', $nameParts));

        // Agregar el timestamp al final
        $className .= '_' . implode('_', $timestamp);

        return $className;
    }

    /**
     * Ejecuta el rollback de una migración PHP (método down())
     * Soporta tanto clases nombradas como clases anónimas
     */
    private function executePHPMigrationDown(string $filePath, string $filename): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // Incluir el archivo y capturar el retorno (para clases anónimas)
        $migration = require $filePath;

        // Si el archivo retorna un objeto (clase anónima)
        if (is_object($migration)) {
            // Verificar que tenga el método down()
            if (!method_exists($migration, 'down')) {
                return false;
            }

            // Inyectar la conexión a la base de datos si tiene el método setConnection
            if (method_exists($migration, 'setConnection')) {
                $migration->setConnection($this->db, $this->driver);
            }
        } else {
            // Es una clase nombrada tradicional
            $className = $this->extractClassNameFromFile($filename);

            if (!class_exists($className)) {
                return false;
            }

            // Instanciar la clase
            $migration = new $className();

            // Verificar que tenga el método down()
            if (!method_exists($migration, 'down')) {
                return false;
            }

            // Inyectar la conexión si tiene el método
            if (method_exists($migration, 'setConnection')) {
                $migration->setConnection($this->db, $this->driver);
            }
        }

        // Ejecutar el rollback
        try {
            ob_start();
            $migration->down();
            $output = ob_get_clean();

            // Mostrar el output si existe
            if (!empty($output)) {
                $this->log($output, 'info');
            }

            return true;

        } catch (\Exception $e) {
            ob_end_clean();
            throw new \Exception("Error ejecutando rollback PHP: " . $e->getMessage());
        }
    }

    /**
     * Verifica si hay DDL statements que causan commit implícito
     */
    private function containsDDLStatements(array $statements): bool
    {
        $ddlKeywords = ['CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME'];

        foreach ($statements as $statement) {
            $statement = strtoupper(trim($statement));
            foreach ($ddlKeywords as $keyword) {
                if (strpos($statement, $keyword) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extrae la sección específica del driver del SQL
     */
    private function extractDriverSection(string $content): string
    {
        $patterns = [
            'mysql' => '/-- MYSQL START.*?-- MYSQL END/is',
            'pgsql' => '/-- POSTGRESQL START.*?-- POSTGRESQL END/is'
        ];

        $pattern = $patterns[$this->driver] ?? null;

        if (!$pattern) {
            return $content;
        }

        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }

        // Buscar patrones alternativos
        if ($this->driver === 'mysql' && preg_match('/-- MySQL.*?\n(.*?)(?=-- PostgreSQL|$)/is', $content, $matches)) {
            return $matches[1];
        }

        if ($this->driver === 'pgsql' && preg_match('/-- PostgreSQL.*?\n(.*?)$/is', $content, $matches)) {
            return $matches[1];
        }

        return $content;
    }

    /**
     * Limpia comentarios SQL
     */
    private function cleanSqlComments(string $sql): string
    {
        // Remover comentarios de línea (-- comentario)
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);

        // Remover comentarios de bloque (/* comentario */)
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        return $sql;
    }

    /**
     * Divide el SQL en statements individuales
     */
    private function splitSqlStatements(string $sql): array
    {
        // Dividir por punto y coma, pero no dentro de strings o funciones
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $inFunction = false;

        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Detectar inicio de funciones/procedimientos
            if (preg_match('/CREATE\s+(OR\s+REPLACE\s+)?(FUNCTION|PROCEDURE)/i', $trimmed)) {
                $inFunction = true;
            }

            // Detectar fin de funciones ($$, END;, etc)
            if ($inFunction && (
                preg_match('/\$\$\s*;?\s*$/', $trimmed) ||
                preg_match('/END\s*;?\s*$/i', $trimmed)
            )) {
                $current .= $line . "\n";
                $statements[] = $current;
                $current = '';
                $inFunction = false;
                continue;
            }

            // Si estamos dentro de una función, añadir toda la línea
            if ($inFunction) {
                $current .= $line . "\n";
                continue;
            }

            // Para statements normales, dividir por ;
            if (strpos($trimmed, ';') !== false && !$inString) {
                $current .= $line;
                $statements[] = $current;
                $current = '';
            } else {
                $current .= $line . "\n";
            }
        }

        // Añadir el último statement si existe
        if (!empty(trim($current))) {
            $statements[] = $current;
        }

        return array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
    }

    /**
     * Registra una migración como ejecutada
     */
    private function recordMigration(string $migration, int $batch): bool
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
            return $stmt->execute([$migration, $batch]);
        } catch (PDOException $e) {
            $this->log("Error registrando migración: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Obtiene el siguiente número de batch
     */
    private function getNextBatchNumber(): int
    {
        try {
            $stmt = $this->db->query("SELECT MAX(batch) as max_batch FROM migrations");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['max_batch'] ?? 0) + 1;
        } catch (PDOException $e) {
            return 1;
        }
    }

    /**
     * Muestra el estado de las migraciones
     */
    public function status(): array
    {
        $this->createMigrationsTable();

        $executed = $this->getExecutedMigrations();
        $all = $this->scanMigrationFiles();
        $pending = array_filter($all, function($m) use ($executed) {
            return !in_array($m['name'], $executed);
        });

        $status = [
            'total' => count($all),
            'executed' => count($executed),
            'pending' => count($pending),
            'migrations' => []
        ];

        foreach ($all as $migration) {
            $isExecuted = in_array($migration['name'], $executed);
            $status['migrations'][] = [
                'name' => $migration['name'],
                'status' => $isExecuted ? 'executed' : 'pending',
                'path' => $migration['path']
            ];
        }

        return $status;
    }

    /**
     * Rollback de la última batch de migraciones
     * NOTA: Requiere archivos _down.sql correspondientes
     */
    public function rollback(): array
    {
        $lastBatch = $this->getLastBatch();

        if (empty($lastBatch)) {
            $this->log('✓ No hay migraciones para revertir', 'info');
            return [
                'success' => true,
                'rolled_back' => 0,
                'message' => 'No hay migraciones para revertir'
            ];
        }

        $this->log("\n" . count($lastBatch) . " migración(es) para revertir:\n", 'info');

        $rolled = 0;
        $errors = [];

        foreach (array_reverse($lastBatch) as $migration) {
            $this->log("→ Revirtiendo: {$migration}", 'info');

            try {
                // Buscar el archivo en todas las rutas posibles
                $found = false;

                foreach ($this->scanMigrationFiles() as $m) {
                    if ($m['name'] === $migration) {
                        $type = $m['type'] ?? 'sql';

                        if ($type === 'php') {
                            // Para migraciones PHP, ejecutar el método down()
                            if ($this->executePHPMigrationDown($m['path'], $migration)) {
                                $this->removeMigrationRecord($migration);
                                $rolled++;
                                $found = true;
                                $this->log("  ✓ Revertida", 'success');
                            } else {
                                $this->log("  ⚠ No se pudo revertir, solo se eliminará el registro", 'warning');
                                $this->removeMigrationRecord($migration);
                                $rolled++;
                                $found = true;
                            }
                        } else {
                            // Para migraciones SQL, buscar archivo _down.sql
                            $downPath = str_replace('.sql', '_down.sql', $m['path']);
                            if (file_exists($downPath)) {
                                $downFile = str_replace('.sql', '_down.sql', $migration);
                                $this->executeMigration($downPath, $downFile, 'sql');
                                $this->removeMigrationRecord($migration);
                                $rolled++;
                                $found = true;
                                $this->log("  ✓ Revertida", 'success');
                            }
                        }
                        break;
                    }
                }

                if (!$found) {
                    $this->log("  ⚠ No se encontró archivo de rollback, solo se eliminará el registro", 'warning');
                    $this->removeMigrationRecord($migration);
                    $rolled++;
                }

            } catch (\Exception $e) {
                $errors[] = $migration . ': ' . $e->getMessage();
                $this->log("  ✗ Error: " . $e->getMessage(), 'error');
            }
        }

        return [
            'success' => empty($errors),
            'rolled_back' => $rolled,
            'errors' => $errors,
            'message' => "Se revirtieron {$rolled} migración(es)"
        ];
    }

    /**
     * Obtiene las migraciones del último batch
     */
    private function getLastBatch(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT migration FROM migrations
                 WHERE batch = (SELECT MAX(batch) FROM migrations)
                 ORDER BY id DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Elimina el registro de una migración
     */
    private function removeMigrationRecord(string $migration): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM migrations WHERE migration = ?");
            return $stmt->execute([$migration]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Registra un mensaje
     */
    private function log(string $message, string $type = 'info'): void
    {
        $this->output[] = ['message' => $message, 'type' => $type];

        if ($this->verbose) {
            $colors = [
                'success' => "\033[32m",
                'error' => "\033[31m",
                'warning' => "\033[33m",
                'info' => "\033[36m",
            ];

            $reset = "\033[0m";
            $color = $colors[$type] ?? '';

            echo $color . $message . $reset . "\n";
        }
    }

    /**
     * Obtiene los mensajes de log
     */
    public function getOutput(): array
    {
        return $this->output;
    }
}
