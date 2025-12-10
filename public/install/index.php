<?php
/**
 * MuseDock CMS - Web Installer
 *
 * Installation wizard for setting up MuseDock CMS.
 * Supports both manual composer install and automatic installation.
 */

// Start output buffering to catch any unexpected output
ob_start();

// Error reporting for installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Constants
define('INSTALL_PATH', __DIR__);
define('ROOT_PATH', dirname(dirname(__DIR__))); // public/install -> public -> root
define('MIN_PHP_VERSION', '8.0.0');
define('INSTALL_LOG', INSTALL_PATH . '/install.log');

// Load Composer autoloader if available (needed for Database classes)
$autoloadPath = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Install logger function
function logInstall($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

    // Also log POST data for debugging (excluding sensitive info)
    if ($level === 'ERROR' && !empty($_POST)) {
        $sanitized = $_POST;
        if (isset($sanitized['admin_password'])) $sanitized['admin_password'] = '***HIDDEN***';
        if (isset($sanitized['db_pass'])) $sanitized['db_pass'] = '***HIDDEN***';
        $logMessage .= "POST Data: " . json_encode($sanitized, JSON_PRETTY_PRINT) . "\n";
    }

    file_put_contents(INSTALL_LOG, $logMessage, FILE_APPEND);
}

// Clear old log at start
if (file_exists(INSTALL_LOG)) {
    @unlink(INSTALL_LOG);
}
logInstall('Installation wizard started');
logInstall('PHP Version: ' . PHP_VERSION);
logInstall('Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'));

// Session for wizard steps
session_start();

// Load translations
require_once INSTALL_PATH . '/i18n.php';
$lang = $_COOKIE['installer_lang'] ?? (substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2) === 'es' ? 'es' : 'en');
if (!in_array($lang, ['en', 'es'])) {
    $lang = 'en';
}
function __($key) {
    global $lang, $translations;
    return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
}

// Initialize step
if (!isset($_SESSION['install_step'])) {
    $_SESSION['install_step'] = 1;
}

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if already installed (verify lock file + env + database)
$installLockExists = file_exists(ROOT_PATH . '/install.lock') || file_exists(ROOT_PATH . '/core/install.lock');
$envExists = file_exists(ROOT_PATH . '/.env');
$databaseConfigured = false;

if ($installLockExists && $envExists) {
    // Also verify database is configured and accessible
    try {
        require_once ROOT_PATH . '/core/Env.php';
        \Screenart\Musedock\Env::load(ROOT_PATH . '/.env');

        $dbHost = \Screenart\Musedock\Env::get('DB_HOST');
        $dbName = \Screenart\Musedock\Env::get('DB_NAME');
        $dbUser = \Screenart\Musedock\Env::get('DB_USER');
        $dbPass = \Screenart\Musedock\Env::get('DB_PASS');
        $dbDriver = \Screenart\Musedock\Env::get('DB_DRIVER', 'mysql');
        $dbPort = \Screenart\Musedock\Env::get('DB_PORT', '3306');

        if (!empty($dbHost) && !empty($dbName) && !empty($dbUser)) {
            // Try to connect to database
            $dsn = "{$dbDriver}:host={$dbHost};port={$dbPort};dbname={$dbName}";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            // Check if migrations table exists (indicates DB is initialized)
            $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
            $databaseConfigured = $stmt->rowCount() > 0;
        }
    } catch (Throwable $e) {
        // Database not accessible, continue to installer
        $databaseConfigured = false;
    }

    // Only block access if everything is properly configured
    if ($databaseConfigured) {
        // Simply redirect to home - no information disclosure
        header('Location: /');
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean any previous output and disable HTML error display for JSON responses
    ob_end_clean();
    ob_start();
    ini_set('display_errors', 0);
    header('Content-Type: application/json');

    try {
        // Verify CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            logInstall('CSRF token validation failed', 'ERROR');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        $action = $_POST['action'];
        logInstall("AJAX action: {$action}");

        switch ($action) {
            case 'check_requirements':
                $result = checkRequirements();
                echo json_encode($result);
                break;

            case 'test_database':
                $result = testDatabaseConnection($_POST);
                logInstall("Database test: " . ($result['success'] ? 'SUCCESS' : 'FAILED - ' . ($result['error'] ?? 'Unknown error')));
                echo json_encode($result);
                break;

            case 'run_installation':
                logInstall("Starting installation...");
                $result = runInstallation($_POST);
                logInstall("Installation result: " . ($result['success'] ? 'SUCCESS' : 'FAILED - ' . ($result['error'] ?? 'Unknown error')));
                echo json_encode($result);
                break;

            case 'check_composer':
                echo json_encode(checkComposerStatus());
                break;

            case 'run_composer':
                echo json_encode(runComposerInstall());
                break;

            default:
                logInstall("Unknown action: {$action}", 'ERROR');
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
    } catch (Throwable $e) {
        // Catch ALL errors including fatal errors
        $errorMsg = 'PHP Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        logInstall($errorMsg, 'ERROR');
        logInstall('Stack trace: ' . $e->getTraceAsString(), 'ERROR');

        echo json_encode([
            'success' => false,
            'error' => 'PHP Error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    exit;
}

/**
 * Check system requirements
 */
function checkRequirements() {
    $requirements = [];

    // PHP Version
    $requirements['php_version'] = [
        'name' => 'PHP Version',
        'required' => '>= ' . MIN_PHP_VERSION,
        'current' => PHP_VERSION,
        'passed' => version_compare(PHP_VERSION, MIN_PHP_VERSION, '>=')
    ];

    // Required extensions
    $extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl', 'fileinfo', 'gd'];
    foreach ($extensions as $ext) {
        $requirements["ext_{$ext}"] = [
            'name' => "Extension: {$ext}",
            'required' => 'Enabled',
            'current' => extension_loaded($ext) ? 'Enabled' : 'Not installed',
            'passed' => extension_loaded($ext)
        ];
    }

    // Optional extensions
    $optionalExts = ['redis', 'zip'];
    foreach ($optionalExts as $ext) {
        $requirements["ext_{$ext}"] = [
            'name' => "Extension: {$ext} (optional)",
            'required' => 'Recommended',
            'current' => extension_loaded($ext) ? 'Enabled' : 'Not installed',
            'passed' => true, // Optional, always passes
            'optional' => true
        ];
    }

    // Writable directories
    $writableDirs = [
        'storage' => ROOT_PATH . '/storage',
        'storage/logs' => ROOT_PATH . '/storage/logs',
        'storage/cache' => ROOT_PATH . '/storage/cache',
        'public/uploads' => ROOT_PATH . '/public/uploads',
        'config' => ROOT_PATH . '/config'
    ];

    foreach ($writableDirs as $name => $path) {
        $writable = is_dir($path) ? is_writable($path) : is_writable(dirname($path));
        $requirements["dir_{$name}"] = [
            'name' => "Directory: {$name}",
            'required' => 'Writable',
            'current' => $writable ? 'Writable' : 'Not writable',
            'passed' => $writable
        ];
    }

    // Check if .env.example exists
    $requirements['env_example'] = [
        'name' => '.env.example file',
        'required' => 'Exists',
        'current' => file_exists(ROOT_PATH . '/.env.example') ? 'Found' : 'Missing',
        'passed' => file_exists(ROOT_PATH . '/.env.example')
    ];

    // Check if vendor exists (composer installed)
    $vendorExists = file_exists(ROOT_PATH . '/vendor/autoload.php');
    $requirements['vendor'] = [
        'name' => 'Composer dependencies',
        'required' => 'Installed',
        'current' => $vendorExists ? 'Installed' : 'Not installed',
        'passed' => $vendorExists,
        'critical' => !$vendorExists
    ];

    $allPassed = true;
    $criticalFailed = false;

    foreach ($requirements as $req) {
        if (!$req['passed'] && empty($req['optional'])) {
            $allPassed = false;
        }
        if (!$req['passed'] && isset($req['critical']) && $req['critical']) {
            $criticalFailed = true;
        }
    }

    return [
        'success' => true,
        'requirements' => $requirements,
        'all_passed' => $allPassed,
        'composer_needed' => !$vendorExists,
        'can_proceed' => $allPassed || (!$criticalFailed && $vendorExists)
    ];
}

/**
 * Check composer status
 */
function checkComposerStatus() {
    $composerExists = file_exists(ROOT_PATH . '/composer.json');
    $vendorExists = file_exists(ROOT_PATH . '/vendor/autoload.php');

    // Check if composer command is available
    $composerCommand = null;
    $output = [];
    $returnCode = 0;

    // Try different composer locations
    $composerPaths = ['composer', 'composer.phar', '/usr/local/bin/composer', '/usr/bin/composer'];
    foreach ($composerPaths as $path) {
        exec("{$path} --version 2>&1", $output, $returnCode);
        if ($returnCode === 0) {
            $composerCommand = $path;
            break;
        }
        $output = [];
    }

    return [
        'success' => true,
        'composer_json_exists' => $composerExists,
        'vendor_exists' => $vendorExists,
        'composer_available' => $composerCommand !== null,
        'composer_command' => $composerCommand,
        'composer_version' => $composerCommand ? trim(implode("\n", $output)) : null
    ];
}

/**
 * Run composer install
 */
function runComposerInstall() {
    $status = checkComposerStatus();

    if (!$status['composer_available']) {
        return [
            'success' => false,
            'error' => 'Composer is not available on this server. Please run "composer install" manually via SSH.',
            'manual_required' => true
        ];
    }

    $composerCommand = $status['composer_command'];
    $output = [];
    $returnCode = 0;

    // Change to root directory and run composer
    $cmd = "cd " . escapeshellarg(ROOT_PATH) . " && {$composerCommand} install --no-dev --optimize-autoloader 2>&1";
    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        return [
            'success' => false,
            'error' => 'Composer install failed',
            'output' => implode("\n", $output),
            'manual_required' => true
        ];
    }

    return [
        'success' => true,
        'message' => 'Composer dependencies installed successfully',
        'output' => implode("\n", $output)
    ];
}

/**
 * Test database connection
 */
function testDatabaseConnection($data) {
    $driver = $data['db_driver'] ?? 'mysql';
    $host = $data['db_host'] ?? 'localhost';
    $port = $data['db_port'] ?? '3306';
    $name = $data['db_name'] ?? '';
    $user = $data['db_user'] ?? '';
    $pass = $data['db_pass'] ?? '';

    if (empty($name) || empty($user)) {
        return ['success' => false, 'error' => 'Database name and user are required'];
    }

    try {
        // First try to connect without database (to check credentials)
        $dsn = "{$driver}:host={$host};port={$port}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Check if database exists
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($name));
        $dbExists = $stmt->fetch() !== false;

        // Try to connect to specific database if it exists
        if ($dbExists) {
            $dsn = "{$driver}:host={$host};port={$port};dbname={$name}";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        }

        return [
            'success' => true,
            'message' => $dbExists
                ? 'Connection successful! Database exists.'
                : 'Connection successful! Database will be created.',
            'db_exists' => $dbExists
        ];

    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Connection failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Run full installation
 */
function runInstallation($data) {
    $steps = [];

    try {
        // Validate required data
        $required = ['db_name', 'db_user', 'app_url', 'admin_email', 'admin_password'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return [
                'success' => false,
                'error' => 'Missing required fields: ' . implode(', ', $missing) . '. Please go back and fill all required fields.',
                'steps' => $steps,
                'missing_fields' => $missing
            ];
        }

        // Step 1: Create .env file
        $steps[] = ['step' => 'Creating .env file', 'status' => 'running'];
        logInstall('Creating .env file...');
        $envResult = createEnvFile($data);
        if (!$envResult['success']) {
            logInstall('Failed to create .env: ' . $envResult['error'], 'ERROR');
            return ['success' => false, 'error' => $envResult['error'], 'steps' => $steps];
        }
        logInstall('.env file created successfully');
        $steps[count($steps) - 1]['status'] = 'completed';

        // Step 2: Create database if not exists
        $steps[] = ['step' => 'Setting up database', 'status' => 'running'];
        logInstall("Setting up database: {$data['db_name']} on {$data['db_host']}");
        $dbResult = setupDatabase($data);
        if (!$dbResult['success']) {
            logInstall('Database setup failed: ' . $dbResult['error'], 'ERROR');
            return ['success' => false, 'error' => $dbResult['error'], 'steps' => $steps];
        }
        logInstall('Database setup completed');
        $steps[count($steps) - 1]['status'] = 'completed';

        // Step 3: Run migrations
        $steps[] = ['step' => 'Running migrations', 'status' => 'running'];
        $migrationResult = runMigrations();
        if (!$migrationResult['success']) {
            return ['success' => false, 'error' => $migrationResult['error'], 'steps' => $steps];
        }
        $steps[count($steps) - 1]['status'] = 'completed';

        // Step 4: Run seeders
        $steps[] = ['step' => 'Seeding database', 'status' => 'running'];
        $seederResult = runSeeders();
        if (!$seederResult['success']) {
            // Seeders failing is not critical, just log it
            $steps[count($steps) - 1]['status'] = 'warning';
            $steps[count($steps) - 1]['message'] = $seederResult['error'];
        } else {
            $steps[count($steps) - 1]['status'] = 'completed';
        }

        // Step 5: Create admin user
        $steps[] = ['step' => 'Creating admin user', 'status' => 'running'];
        $adminResult = createAdminUser($data);
        if (!$adminResult['success']) {
            return ['success' => false, 'error' => $adminResult['error'], 'steps' => $steps];
        }
        $steps[count($steps) - 1]['status'] = 'completed';

        // Step 6: Create install.lock
        $steps[] = ['step' => 'Finalizing installation', 'status' => 'running'];
        file_put_contents(ROOT_PATH . '/install.lock', date('Y-m-d H:i:s') . "\nInstalled by: " . ($data['admin_email'] ?? 'Unknown'));
        $steps[count($steps) - 1]['status'] = 'completed';

        // Log success and delete install.log
        logInstall('Installation completed successfully!');
        logInstall('Deleting install log...');
        if (file_exists(INSTALL_LOG)) {
            @unlink(INSTALL_LOG);
        }

        // Clear session
        session_destroy();

        return [
            'success' => true,
            'message' => 'MuseDock CMS installed successfully!',
            'steps' => $steps,
            'redirect' => '/' . ($data['admin_path'] ?? 'musedock') . '/login'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'steps' => $steps
        ];
    }
}

/**
 * Create .env file from .env.example
 */
function createEnvFile($data) {
    $examplePath = ROOT_PATH . '/.env.example';
    $envPath = ROOT_PATH . '/.env';

    if (!file_exists($examplePath)) {
        return ['success' => false, 'error' => '.env.example file not found'];
    }

    $content = file_get_contents($examplePath);

    // Generate APP_KEY
    $appKey = bin2hex(random_bytes(32));

    // Replace values
    $replacements = [
        'APP_ENV=production' => 'APP_ENV=' . ($data['app_env'] ?? 'production'),
        'APP_DEBUG=false' => 'APP_DEBUG=' . ($data['app_debug'] ?? 'false'),
        'APP_NAME="MuseDock CMS"' => 'APP_NAME="' . ($data['app_name'] ?? 'MuseDock CMS') . '"',
        'APP_URL=https://your-domain.com' => 'APP_URL=' . rtrim($data['app_url'] ?? 'http://localhost', '/'),
        'APP_KEY=' => 'APP_KEY=' . $appKey,
        'DB_DRIVER=mysql' => 'DB_DRIVER=' . ($data['db_driver'] ?? 'mysql'),
        'DB_HOST=localhost' => 'DB_HOST=' . ($data['db_host'] ?? 'localhost'),
        'DB_PORT=3306' => 'DB_PORT=' . ($data['db_port'] ?? '3306'),
        'DB_NAME=' => 'DB_NAME=' . ($data['db_name'] ?? ''),
        'DB_USER=' => 'DB_USER=' . ($data['db_user'] ?? ''),
        'DB_PASS=' => 'DB_PASS=' . ($data['db_pass'] ?? ''),
        'MAIN_DOMAIN=localhost' => 'MAIN_DOMAIN=' . parse_url($data['app_url'] ?? 'http://localhost', PHP_URL_HOST),
        'DEFAULT_LANG=en' => 'DEFAULT_LANG=' . ($data['default_lang'] ?? 'en'),
        'MAIL_FROM_ADDRESS=noreply@your-domain.com' => 'MAIL_FROM_ADDRESS=' . ($data['mail_from_address'] ?? 'noreply@' . parse_url($data['app_url'] ?? 'http://localhost', PHP_URL_HOST)),
        'MAIL_FROM_NAME="MuseDock CMS"' => 'MAIL_FROM_NAME="' . ($data['mail_from_name'] ?? 'MuseDock CMS') . '"',
    ];

    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }

    if (file_put_contents($envPath, $content) === false) {
        return ['success' => false, 'error' => 'Could not write .env file. Check permissions.'];
    }

    return ['success' => true];
}

/**
 * Setup database
 */
function setupDatabase($data) {
    $driver = $data['db_driver'] ?? 'mysql';
    $host = $data['db_host'] ?? 'localhost';
    $port = $data['db_port'] ?? '3306';
    $name = $data['db_name'] ?? '';
    $user = $data['db_user'] ?? '';
    $pass = $data['db_pass'] ?? '';

    // Validate required fields
    if (empty($name)) {
        return ['success' => false, 'error' => 'Database name is required'];
    }
    if (empty($user)) {
        return ['success' => false, 'error' => 'Database user is required'];
    }

    try {
        // Connect without database
        $dsn = "{$driver}:host={$host};port={$port}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Create database if not exists
        if ($driver === 'mysql') {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } else {
            // PostgreSQL
            $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($name));
            if (!$stmt->fetch()) {
                $pdo->exec("CREATE DATABASE \"{$name}\" ENCODING 'UTF8'");
            }
        }

        return ['success' => true];

    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database setup failed: ' . $e->getMessage()];
    }
}

/**
 * Run migrations
 */
function runMigrations() {
    try {
        // Load the .env we just created
        ob_start();
        require_once ROOT_PATH . '/core/Env.php';
        ob_end_clean();

        \Screenart\Musedock\Env::load(ROOT_PATH . '/.env');

        // Load database class
        ob_start();
        require_once ROOT_PATH . '/core/Database.php';
        ob_end_clean();

        $pdo = \Screenart\Musedock\Database::connect();

        // Create migrations table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `batch` INT NOT NULL,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_batch (batch)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Get already run migrations
        $stmt = $pdo->query("SELECT migration FROM migrations");
        $ranMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get migration files
        $migrationPath = ROOT_PATH . '/database/migrations';
        $files = glob($migrationPath . '/*.php');
        sort($files);

        $batch = 1;
        $stmtBatch = $pdo->query("SELECT MAX(batch) FROM migrations");
        $maxBatch = $stmtBatch->fetchColumn();
        if ($maxBatch) {
            $batch = $maxBatch + 1;
        }

        $migrated = 0;

        foreach ($files as $file) {
            $filename = basename($file, '.php');

            if (in_array($filename, $ranMigrations)) {
                continue;
            }

            // Start output buffering for this migration to catch any errors
            ob_start();
            try {
                // Extract class name from file content
                $content = file_get_contents($file);
                if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                    $className = $matches[1];
                } else {
                    throw new Exception("Could not extract class name from {$filename}");
                }

                require_once $file;

                if (class_exists($className)) {
                    $migration = new $className();
                    if (method_exists($migration, 'up')) {
                        $migration->up();
                    }

                    // Record migration
                    $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                    $stmt->execute([$filename, $batch]);

                    $migrated++;
                } else {
                    throw new Exception("Class {$className} not found in {$filename}");
                }

                // Clear the buffer if successful
                ob_end_clean();
            } catch (Exception $e) {
                // Clean buffer and re-throw
                ob_end_clean();
                throw new Exception("Migration {$filename} failed: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'message' => "Migrated {$migrated} files"
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Migration failed: ' . $e->getMessage()];
    }
}

/**
 * Run seeders
 */
function runSeeders() {
    try {
        $seederPath = ROOT_PATH . '/database/seeders/DatabaseSeeder.php';

        if (!file_exists($seederPath)) {
            return ['success' => true, 'message' => 'No seeders found'];
        }

        // Cargar todos los seeders necesarios
        $seedersDir = ROOT_PATH . '/database/seeders/';
        $seederFiles = [
            'RolesAndPermissionsSeeder.php',
            'ModulesSeeder.php',
            'ThemesSeeder.php',
            'SuperadminMenuSeeder.php',
            'AdminMenuSeeder.php',
            'DatabaseSeeder.php'
        ];

        foreach ($seederFiles as $file) {
            $filePath = $seedersDir . $file;
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }

        // Usar el namespace correcto
        $seederClass = 'Screenart\\Musedock\\Database\\Seeders\\DatabaseSeeder';

        if (class_exists($seederClass)) {
            $seeder = new $seederClass();
            if (method_exists($seeder, 'run')) {
                ob_start();
                $seeder->run();
                ob_end_clean();
            }
        }

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Seeder failed: ' . $e->getMessage()];
    }
}

/**
 * Create admin user
 */
function createAdminUser($data) {
    try {
        $pdo = \Screenart\Musedock\Database::connect();

        $email = $data['admin_email'] ?? '';
        $password = $data['admin_password'] ?? '';
        $name = $data['admin_name'] ?? 'Administrator';

        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Admin email and password are required'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert into super_admins with role 'superadmin'
        $stmt = $pdo->prepare("
            INSERT INTO super_admins (name, email, password, role, is_root, created_at)
            VALUES (?, ?, ?, 'superadmin', 1, NOW())
            ON DUPLICATE KEY UPDATE password = VALUES(password), role = 'superadmin'
        ");
        $stmt->execute([$name, $email, $hashedPassword]);

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Could not create admin user: ' . $e->getMessage()];
    }
}

// Get current step from query
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(5, $step));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install MuseDock CMS</title>
    <link href="/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --bg-dark: #1a1a2e;
            --bg-card: #16213e;
            --text-light: #e2e8f0;
            --text-muted: #94a3b8;
        }

        body {
            background: linear-gradient(135deg, var(--bg-dark) 0%, #0f172a 100%);
            min-height: 100vh;
            color: var(--text-light);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .installer-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .brand {
            text-align: center;
            margin-bottom: 40px;
        }

        .brand h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .brand p {
            color: #e5e7eb; /* Gris claro legible */
            font-size: 1.1rem;
        }

        .step-indicators {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            gap: 10px;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            background: var(--bg-card);
            border: 2px solid #374151;
            color: var(--text-muted);
            transition: all 0.3s;
        }

        .step-indicator.active .step-circle {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .step-indicator.completed .step-circle {
            background: var(--success-color);
            border-color: var(--success-color);
            color: white;
        }

        .step-line {
            width: 40px;
            height: 2px;
            background: #374151;
        }

        .step-indicator.completed + .step-indicator .step-line,
        .step-indicator.active + .step-indicator .step-line {
            background: var(--primary-color);
        }

        .card {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-light); /* Blanco */
        }

        .card-body {
            padding: 24px;
        }

        .form-label {
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            border-radius: 8px;
            padding: 12px 16px;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(0, 0, 0, 0.4);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            color: var(--text-light);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        /* Override Bootstrap text-muted for better visibility */
        .text-muted, small {
            color: #9ca3af !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            padding: 12px 32px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.2);
            color: var(--text-light);
        }

        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            margin-bottom: 8px;
            color: var(--text-light); /* Texto blanco */
        }

        .requirement-item.passed {
            border-left: 3px solid var(--success-color);
        }

        .requirement-item.failed {
            border-left: 3px solid var(--danger-color);
        }

        .requirement-item.warning {
            border-left: 3px solid var(--warning-color);
        }

        .requirement-status {
            font-size: 1.2rem;
        }

        .requirement-status.passed { color: var(--success-color); }
        .requirement-status.failed { color: var(--danger-color); }
        .requirement-status.warning { color: var(--warning-color); }

        .alert {
            border-radius: 12px;
            border: none;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
        }

        .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
        }

        .install-progress {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .install-progress li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            background: rgba(0, 0, 0, 0.2);
        }

        .install-progress li.running {
            border-left: 3px solid var(--primary-color);
        }

        .install-progress li.completed {
            border-left: 3px solid var(--success-color);
        }

        .install-progress li.failed {
            border-left: 3px solid var(--danger-color);
        }

        .input-group-text {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
        }

        .password-toggle {
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .step-indicators {
                flex-wrap: wrap;
            }

            .step-line {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <!-- Language Selector -->
        <div class="text-end mb-3">
            <select class="form-select form-select-sm d-inline-block w-auto" id="languageSelector" style="background: rgba(0,0,0,0.3); color: white; border-color: rgba(255,255,255,0.3);">
                <option value="en" <?= ($_COOKIE['installer_lang'] ?? 'en') === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
                <option value="es" <?= ($_COOKIE['installer_lang'] ?? 'en') === 'es' ? 'selected' : '' ?>>🇪🇸 Español</option>
            </select>
        </div>

        <div class="brand">
            <h1><i class="bi bi-box-seam-fill"></i> MuseDock CMS</h1>
            <p><?= __('installation_wizard') ?></p>
        </div>

        <!-- Step Indicators -->
        <div class="step-indicators">
            <div class="step-indicator <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">
                <div class="step-circle">
                    <?= $step > 1 ? '<i class="bi bi-check"></i>' : '1' ?>
                </div>
                <span class="d-none d-md-inline">Requirements</span>
            </div>
            <div class="step-line"></div>
            <div class="step-indicator <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">
                <div class="step-circle">
                    <?= $step > 2 ? '<i class="bi bi-check"></i>' : '2' ?>
                </div>
                <span class="d-none d-md-inline">Database</span>
            </div>
            <div class="step-line"></div>
            <div class="step-indicator <?= $step >= 3 ? ($step > 3 ? 'completed' : 'active') : '' ?>">
                <div class="step-circle">
                    <?= $step > 3 ? '<i class="bi bi-check"></i>' : '3' ?>
                </div>
                <span class="d-none d-md-inline">Site Setup</span>
            </div>
            <div class="step-line"></div>
            <div class="step-indicator <?= $step >= 4 ? ($step > 4 ? 'completed' : 'active') : '' ?>">
                <div class="step-circle">
                    <?= $step > 4 ? '<i class="bi bi-check"></i>' : '4' ?>
                </div>
                <span class="d-none d-md-inline">Admin</span>
            </div>
            <div class="step-line"></div>
            <div class="step-indicator <?= $step >= 5 ? 'active' : '' ?>">
                <div class="step-circle">5</div>
                <span class="d-none d-md-inline">Install</span>
            </div>
        </div>

        <!-- Step Content -->
        <div class="card">
            <!-- Step 1: Requirements -->
            <div id="step-1" class="step-content" style="<?= $step !== 1 ? 'display:none' : '' ?>">
                <div class="card-header">
                    <h3><i class="bi bi-clipboard-check me-2"></i>System Requirements</h3>
                </div>
                <div class="card-body">
                    <div id="requirements-list">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-3 text-muted">Checking requirements...</p>
                        </div>
                    </div>

                    <div id="composer-section" class="mt-4" style="display:none">
                        <div class="alert alert-warning">
                            <h5><i class="bi bi-exclamation-triangle me-2"></i>Composer Dependencies Required</h5>
                            <p class="mb-3">Composer dependencies are not installed. You have two options:</p>

                            <div class="d-flex gap-3 flex-wrap">
                                <button type="button" class="btn btn-primary" id="btn-auto-composer">
                                    <i class="bi bi-magic me-2"></i>Auto Install (if available)
                                </button>
                                <button type="button" class="btn btn-outline-light" data-bs-toggle="collapse" data-bs-target="#manual-composer">
                                    <i class="bi bi-terminal me-2"></i>Manual Instructions
                                </button>
                            </div>

                            <div class="collapse mt-3" id="manual-composer">
                                <div class="bg-dark p-3 rounded">
                                    <p class="mb-2">Connect via SSH and run:</p>
                                    <code class="text-warning">cd <?= ROOT_PATH ?> && composer install --no-dev</code>
                                    <p class="mt-2 mb-0 small text-muted">Then refresh this page.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-outline-light" id="btn-recheck">
                            <i class="bi bi-arrow-clockwise me-2"></i>Re-check
                        </button>
                        <button type="button" class="btn btn-primary" id="btn-next-1" disabled>
                            Continue <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Database -->
            <div id="step-2" class="step-content" style="<?= $step !== 2 ? 'display:none' : '' ?>">
                <div class="card-header">
                    <h3><i class="bi bi-database me-2"></i>Database Configuration</h3>
                </div>
                <div class="card-body">
                    <form id="database-form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Database Driver</label>
                                <select class="form-select" name="db_driver" id="db_driver">
                                    <option value="mysql">MySQL / MariaDB</option>
                                    <option value="pgsql">PostgreSQL</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Database Host</label>
                                <input type="text" class="form-control" name="db_host" value="localhost" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Database Port</label>
                                <input type="text" class="form-control" name="db_port" id="db_port" value="3306" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Database Name</label>
                                <input type="text" class="form-control" name="db_name" placeholder="musedock" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Database User</label>
                                <input type="text" class="form-control" name="db_user" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Database Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="db_pass" id="db_pass">
                                    <span class="input-group-text password-toggle" data-target="db_pass">
                                        <i class="bi bi-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div id="db-test-result" class="mb-3"></div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-light" onclick="goToStep(1)">
                                <i class="bi bi-arrow-left me-2"></i>Back
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-light" id="btn-test-db">
                                    <i class="bi bi-plug me-2"></i>Test Connection
                                </button>
                                <button type="button" class="btn btn-primary" id="btn-next-2" disabled>
                                    Continue <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 3: Site Setup -->
            <div id="step-3" class="step-content" style="<?= $step !== 3 ? 'display:none' : '' ?>">
                <div class="card-header">
                    <h3><i class="bi bi-globe me-2"></i>Site Configuration</h3>
                </div>
                <div class="card-body">
                    <form id="site-form">
                        <h5 class="mb-3" style="color: var(--text-light);">Basic Information</h5>

                        <div class="mb-3">
                            <label class="form-label">Site Name</label>
                            <input type="text" class="form-control" name="app_name"
                                   value="MuseDock CMS"
                                   required>
                            <small class="text-muted">The name of your website (appears in title, emails, etc.)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Site URL</label>
                            <input type="url" class="form-control" name="app_url"
                                   value="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>"
                                   required>
                            <small class="text-muted">Full URL including http:// or https:// (without trailing slash)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Language</label>
                                <select class="form-select" name="default_lang">
                                    <option value="en">English</option>
                                    <option value="es" selected>Español</option>
                                    <option value="fr">Français</option>
                                    <option value="de">Deutsch</option>
                                    <option value="it">Italiano</option>
                                    <option value="pt">Português</option>
                                </select>
                                <small class="text-muted">Primary language for the admin panel</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Environment</label>
                                <select class="form-select" name="app_env">
                                    <option value="production" selected>Production</option>
                                    <option value="development">Development</option>
                                </select>
                                <small class="text-muted">Use "Development" only for testing</small>
                            </div>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.1); margin: 24px 0;">

                        <h5 class="mb-3" style="color: var(--text-light);">Email Configuration</h5>

                        <div class="mb-3">
                            <label class="form-label">Email From Address</label>
                            <input type="email" class="form-control" name="mail_from_address"
                                   value="noreply@<?= $_SERVER['HTTP_HOST'] ?? 'your-domain.com' ?>"
                                   required>
                            <small class="text-muted">Email address used for system notifications (password resets, alerts, etc.)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email From Name</label>
                            <input type="text" class="form-control" name="mail_from_name"
                                   value="MuseDock CMS"
                                   required>
                            <small class="text-muted">Display name that appears in outgoing emails</small>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-light" onclick="goToStep(2)">
                                <i class="bi bi-arrow-left me-2"></i>Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="goToStep(4)">
                                Continue <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 4: Admin User -->
            <div id="step-4" class="step-content" style="<?= $step !== 4 ? 'display:none' : '' ?>">
                <div class="card-header">
                    <h3><i class="bi bi-person-badge me-2"></i>Administrator Account</h3>
                </div>
                <div class="card-body">
                    <form id="admin-form">
                        <div class="mb-3">
                            <label class="form-label">Admin Name</label>
                            <input type="text" class="form-control" name="admin_name" value="Administrator" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Admin Email</label>
                            <input type="email" class="form-control" name="admin_email" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Admin Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="admin_password" id="admin_password"
                                       minlength="8" required>
                                <span class="input-group-text password-toggle" data-target="admin_password">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="admin_password_confirm"
                                   id="admin_password_confirm" minlength="8" required>
                        </div>

                        <div id="password-error" class="alert alert-danger" style="display:none"></div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-light" onclick="goToStep(3)">
                                <i class="bi bi-arrow-left me-2"></i>Back
                            </button>
                            <button type="button" class="btn btn-primary" id="btn-next-4">
                                Continue <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 5: Install -->
            <div id="step-5" class="step-content" style="<?= $step !== 5 ? 'display:none' : '' ?>">
                <div class="card-header">
                    <h3><i class="bi bi-rocket-takeoff me-2"></i>Install MuseDock CMS</h3>
                </div>
                <div class="card-body">
                    <div id="install-summary">
                        <h5 class="mb-3" style="color: var(--text-light);">Installation Summary</h5>
                        <div class="p-3 rounded mb-4" style="background: rgba(0,0,0,0.2); color: #e5e7eb;" id="summary-content">
                            <!-- Filled by JS -->
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-light" onclick="goToStep(4)">
                                <i class="bi bi-arrow-left me-2"></i>Back
                            </button>
                            <button type="button" class="btn btn-primary btn-lg" id="btn-install">
                                <i class="bi bi-download me-2"></i>Install Now
                            </button>
                        </div>
                    </div>

                    <div id="install-progress" style="display:none">
                        <h5 class="mb-3">Installing...</h5>
                        <ul class="install-progress" id="progress-list">
                            <!-- Filled by JS -->
                        </ul>
                    </div>

                    <div id="install-complete" style="display:none">
                        <div class="text-center py-4">
                            <div class="display-1 text-success mb-3">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <h3>Installation Complete!</h3>
                            <p class="text-muted mb-4">MuseDock CMS has been installed successfully.</p>
                            <a href="/musedock/login" class="btn btn-primary btn-lg" id="btn-go-admin">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Admin Panel
                            </a>
                        </div>
                    </div>

                    <div id="install-error" style="display:none">
                        <div class="alert alert-danger">
                            <h5><i class="bi bi-exclamation-octagon me-2"></i>Installation Failed</h5>
                            <p id="error-message"></p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-light" id="btn-retry-install">
                                <i class="bi bi-arrow-clockwise me-2"></i>Retry Installation
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                                <i class="bi bi-arrow-left me-2"></i>Back to Database
                            </button>
                            <a href="install.log" download="musedock-install.log" class="btn btn-info" id="btn-download-log" style="display:none">
                                <i class="bi bi-download me-2"></i>Download Error Log
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-center mt-4" style="color: #9ca3af;">
            <small>MuseDock CMS &copy; <?= date('Y') ?> | <a href="https://musedock.org" target="_blank" style="color: #d1d5db; text-decoration: none;">Documentation</a></small>
        </p>
    </div>

    <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
        let installData = {};

        // Load saved data from sessionStorage
        try {
            const savedData = sessionStorage.getItem('musedock_install_data');
            if (savedData) {
                installData = JSON.parse(savedData);
                console.log('Loaded saved installation data');
            }
        } catch (e) {
            console.warn('Could not load saved data:', e);
        }

        // Save data to sessionStorage
        function saveInstallData() {
            try {
                sessionStorage.setItem('musedock_install_data', JSON.stringify(installData));
            } catch (e) {
                console.warn('Could not save data:', e);
            }
        }

        // Password toggle
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });
        });

        // Database driver port change
        document.getElementById('db_driver').addEventListener('change', function() {
            document.getElementById('db_port').value = this.value === 'pgsql' ? '5432' : '3306';
        });

        // Navigation
        function goToStep(step) {
            document.querySelectorAll('.step-content').forEach(el => el.style.display = 'none');
            document.getElementById('step-' + step).style.display = 'block';

            // Update URL
            history.pushState({}, '', '?step=' + step);

            // Update indicators
            document.querySelectorAll('.step-indicator').forEach((el, i) => {
                el.classList.remove('active', 'completed');
                if (i + 1 < step) el.classList.add('completed');
                if (i + 1 === step) el.classList.add('active');
            });
        }

        // Step 1: Check requirements
        async function checkRequirements() {
            const container = document.getElementById('requirements-list');

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=check_requirements&csrf_token=${csrfToken}`
                });

                const data = await response.json();

                if (data.success) {
                    let html = '';

                    for (const [key, req] of Object.entries(data.requirements)) {
                        const statusClass = req.passed ? 'passed' : (req.optional ? 'warning' : 'failed');
                        const icon = req.passed ? 'bi-check-circle-fill' : (req.optional ? 'bi-exclamation-circle-fill' : 'bi-x-circle-fill');

                        html += `
                            <div class="requirement-item ${statusClass}">
                                <div>
                                    <strong>${req.name}</strong>
                                    <div class="small text-muted">Required: ${req.required} | Current: ${req.current}</div>
                                </div>
                                <span class="requirement-status ${statusClass}">
                                    <i class="bi ${icon}"></i>
                                </span>
                            </div>
                        `;
                    }

                    container.innerHTML = html;

                    // Show composer section if needed
                    if (data.composer_needed) {
                        document.getElementById('composer-section').style.display = 'block';
                    }

                    // Enable continue button
                    document.getElementById('btn-next-1').disabled = !data.can_proceed;
                }
            } catch (error) {
                container.innerHTML = `<div class="alert alert-danger">Error checking requirements: ${error.message}</div>`;
            }
        }

        // Auto composer install
        document.getElementById('btn-auto-composer').addEventListener('click', async function() {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Installing...';

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=run_composer&csrf_token=${csrfToken}`
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Composer install failed. Please install manually.');
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-magic me-2"></i>Auto Install';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-magic me-2"></i>Auto Install';
            }
        });

        // Recheck requirements
        document.getElementById('btn-recheck').addEventListener('click', checkRequirements);

        // Next from step 1
        document.getElementById('btn-next-1').addEventListener('click', () => goToStep(2));

        // Test database
        document.getElementById('btn-test-db').addEventListener('click', async function() {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing...';

            const formData = new FormData(document.getElementById('database-form'));
            formData.append('action', 'test_database');
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });

                const data = await response.json();
                const resultDiv = document.getElementById('db-test-result');

                if (data.success) {
                    resultDiv.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>${data.message}</div>`;
                    document.getElementById('btn-next-2').disabled = false;

                    // Save to installData
                    for (const [key, value] of formData.entries()) {
                        if (key !== 'action' && key !== 'csrf_token') {
                            installData[key] = value;
                        }
                    }

                    // Save to sessionStorage
                    saveInstallData();
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>${data.error}</div>`;
                    document.getElementById('btn-next-2').disabled = true;
                }
            } catch (error) {
                document.getElementById('db-test-result').innerHTML =
                    `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }

            this.disabled = false;
            this.innerHTML = '<i class="bi bi-plug me-2"></i>Test Connection';
        });

        // Next from step 2
        document.getElementById('btn-next-2').addEventListener('click', () => {
            const formData = new FormData(document.getElementById('database-form'));
            for (const [key, value] of formData.entries()) {
                installData[key] = value;
            }
            saveInstallData();
            goToStep(3);
        });

        // Next from step 4
        document.getElementById('btn-next-4').addEventListener('click', function() {
            const password = document.getElementById('admin_password').value;
            const confirm = document.getElementById('admin_password_confirm').value;
            const errorDiv = document.getElementById('password-error');

            if (password.length < 8) {
                errorDiv.textContent = 'Password must be at least 8 characters.';
                errorDiv.style.display = 'block';
                return;
            }

            if (password !== confirm) {
                errorDiv.textContent = 'Passwords do not match.';
                errorDiv.style.display = 'block';
                return;
            }

            errorDiv.style.display = 'none';

            // Save site and admin data
            const siteForm = new FormData(document.getElementById('site-form'));
            const adminForm = new FormData(document.getElementById('admin-form'));

            for (const [key, value] of siteForm.entries()) {
                installData[key] = value;
            }
            for (const [key, value] of adminForm.entries()) {
                if (key !== 'admin_password_confirm') {
                    installData[key] = value;
                }
            }

            // Save to sessionStorage
            saveInstallData();

            // Show summary
            updateSummary();
            goToStep(5);
        });

        // Update summary
        function updateSummary() {
            const summary = document.getElementById('summary-content');
            summary.innerHTML = `
                <div class="row" style="color: #e5e7eb;">
                    <div class="col-md-6">
                        <p><strong>Site URL:</strong> ${installData.app_url || 'Not set'}</p>
                        <p><strong>Language:</strong> ${installData.default_lang || 'en'}</p>
                        <p><strong>Environment:</strong> ${installData.app_env || 'production'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Database:</strong> ${installData.db_name || 'Not set'}</p>
                        <p><strong>Admin Email:</strong> ${installData.admin_email || 'Not set'}</p>
                    </div>
                </div>
            `;
        }

        // Install
        document.getElementById('btn-install').addEventListener('click', async function() {
            this.disabled = true;

            document.getElementById('install-summary').style.display = 'none';
            document.getElementById('install-progress').style.display = 'block';

            const progressList = document.getElementById('progress-list');
            const steps = [
                'Creating .env file',
                'Setting up database',
                'Running migrations',
                'Seeding database',
                'Creating admin user',
                'Finalizing installation'
            ];

            progressList.innerHTML = steps.map(step =>
                `<li><span class="spinner-border spinner-border-sm text-muted"></span> ${step}</li>`
            ).join('');

            try {
                installData.action = 'run_installation';
                installData.csrf_token = csrfToken;

                console.log('Sending installation data:', installData);

                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(installData)
                });

                // Get response text first
                const responseText = await response.text();

                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    // JSON parse failed - show raw response
                    throw new Error(`Invalid JSON response. Server returned:\n${responseText.substring(0, 500)}`);
                }

                if (data.success) {
                    // Update progress
                    progressList.querySelectorAll('li').forEach((li, i) => {
                        li.classList.add('completed');
                        li.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> ${steps[i]}`;
                    });

                    setTimeout(() => {
                        document.getElementById('install-progress').style.display = 'none';
                        document.getElementById('install-complete').style.display = 'block';

                        if (data.redirect) {
                            document.getElementById('btn-go-admin').href = data.redirect;
                        }
                    }, 1000);
                } else {
                    // Show detailed error with steps
                    let errorHtml = `<strong>Installation Error:</strong><br>${data.error || 'Unknown error'}`;

                    if (data.steps && data.steps.length > 0) {
                        errorHtml += '<br><br><strong>Steps completed:</strong><ul style="text-align: left; margin-top: 10px;">';
                        data.steps.forEach(step => {
                            const icon = step.status === 'completed' ? '✓' :
                                        step.status === 'warning' ? '⚠' : '✗';
                            const color = step.status === 'completed' ? '#4ade80' :
                                         step.status === 'warning' ? '#fbbf24' : '#ef4444';
                            errorHtml += `<li><span style="color: ${color}">${icon}</span> ${step.step}`;
                            if (step.message) {
                                errorHtml += `<br><small style="color: #9ca3af">${step.message}</small>`;
                            }
                            errorHtml += '</li>';
                        });
                        errorHtml += '</ul>';
                    }

                    document.getElementById('install-progress').style.display = 'none';
                    document.getElementById('install-error').style.display = 'block';
                    document.getElementById('error-message').innerHTML = errorHtml;

                    // Show download log button
                    checkLogFile();
                }
            } catch (error) {
                document.getElementById('install-progress').style.display = 'none';
                document.getElementById('install-error').style.display = 'block';
                document.getElementById('error-message').innerHTML = `<strong>Connection Error:</strong><br>${error.message}`;

                // Show download log button
                checkLogFile();
            }
        });

        // Check if install.log exists and show download button
        async function checkLogFile() {
            try {
                const response = await fetch('install.log', { method: 'HEAD' });
                if (response.ok) {
                    document.getElementById('btn-download-log').style.display = 'inline-block';
                }
            } catch (e) {
                // Log doesn't exist, that's ok
            }
        }

        // Retry installation button
        document.getElementById('btn-retry-install').addEventListener('click', function() {
            // Hide error, show summary again
            document.getElementById('install-error').style.display = 'none';
            document.getElementById('install-summary').style.display = 'block';

            // Re-enable install button
            document.getElementById('btn-install').disabled = false;
        });

        // Restore form data from sessionStorage
        function restoreFormData() {
            if (Object.keys(installData).length === 0) return;

            // Restore database form
            const dbForm = document.getElementById('database-form');
            if (dbForm) {
                for (const [key, value] of Object.entries(installData)) {
                    const input = dbForm.querySelector(`[name="${key}"]`);
                    if (input && value) {
                        input.value = value;
                    }
                }
            }

            // Restore site form
            const siteForm = document.getElementById('site-form');
            if (siteForm) {
                for (const [key, value] of Object.entries(installData)) {
                    const input = siteForm.querySelector(`[name="${key}"]`);
                    if (input && value) {
                        input.value = value;
                    }
                }
            }

            // Restore admin form (except password)
            const adminForm = document.getElementById('admin-form');
            if (adminForm) {
                for (const [key, value] of Object.entries(installData)) {
                    if (key.includes('password')) continue; // Skip passwords for security
                    const input = adminForm.querySelector(`[name="${key}"]`);
                    if (input && value) {
                        input.value = value;
                    }
                }
            }

            console.log('Restored form data from sessionStorage');
        }

        // Initial check
        checkRequirements();

        // Restore saved form data
        setTimeout(restoreFormData, 100);

        // Language Selector Handler
        document.getElementById('languageSelector').addEventListener('change', function() {
            const lang = this.value;
            // Set cookie for 1 year
            document.cookie = `installer_lang=${lang}; path=/; max-age=31536000`;
            // Reload page to apply language
            window.location.reload();
        });
    </script>
</body>
</html>
