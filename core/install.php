<?php

// Ruta base del sistema
$basePath = dirname(__DIR__);

// Archivo lock para evitar reinstalación
$lockFile = $basePath . '/core/install.lock';

// Si ya está instalado, redirige
if (file_exists($lockFile)) {
    header("Location: /");
    exit;
}

$error = null;
$okToInstall = true;

// Verificaciones previas
$checks = [
    'php_version' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'config_writable' => is_writable($basePath . '/config/config.php'),
    'storage_writable' => is_writable($basePath . '/storage'),
];

// Si hay alguna que falla, no dejamos instalar
foreach ($checks as $key => $value) {
    if (!$value) $okToInstall = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $okToInstall) {
    $db_driver = $_POST['db_driver'] ?? 'mysql';
    $db_host = $_POST['db_host'];
    $db_port = $_POST['db_port'] ?? ($db_driver === 'pgsql' ? 5432 : 3306);
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $admin_email = trim($_POST['admin_email']);
    $admin_password = trim($_POST['admin_password']);

    // Validación básica
    if (strlen($admin_password) < 8) {
        $error = 'La contraseña del administrador debe tener al menos 8 caracteres.';
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email del administrador no es válido.';
    } else {
        try {
            // Generar DSN según el driver
            if ($db_driver === 'pgsql') {
                $dsn = "pgsql:host=$db_host;port=$db_port";
            } else {
                $dsn = "mysql:host=$db_host;port=$db_port";
            }

            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Crear base de datos si no existe (sintaxis específica por driver)
            if ($db_driver === 'pgsql') {
                // 🔒 SECURITY: PostgreSQL - usar prepared statement para prevenir SQL injection
                $stmt = $pdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
                $stmt->execute([$db_name]);
                if ($stmt->rowCount() === 0) {
                    // CREATE DATABASE no soporta prepared statements, validamos el nombre
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
                        throw new Exception('Nombre de base de datos inválido. Solo letras, números y guiones bajos.');
                    }
                    $pdo->exec("CREATE DATABASE \"$db_name\" ENCODING 'UTF8'");
                }
                // Reconectar a la nueva base de datos
                $pdo = null;
                $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            } else {
                // 🔒 SECURITY: MySQL - validar nombre de BD antes de usar en exec
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
                    throw new Exception('Nombre de base de datos inválido. Solo letras, números y guiones bajos.');
                }
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$db_name`");
            }

            // Crear tabla de usuarios (sintaxis específica por driver)
            if ($db_driver === 'pgsql') {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id SERIAL PRIMARY KEY,
                        email VARCHAR(255) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        role VARCHAR(50) DEFAULT 'admin',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");

                // Crear tabla de módulos
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS modules (
                        id SERIAL PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        status SMALLINT DEFAULT 1,
                        installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");

                // Crear tabla de temas
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS themes (
                        id SERIAL PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        active SMALLINT DEFAULT 0,
                        installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
            } else {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(255) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        role VARCHAR(50) DEFAULT 'admin',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB
                ");

                // Crear tabla de módulos
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS modules (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        status TINYINT(1) DEFAULT 1,
                        installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB
                ");

                // Crear tabla de temas
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS themes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        active TINYINT(1) DEFAULT 0,
                        installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB
                ");
            }

            // Insertar usuario administrador
            $hashed = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'superadmin')");
            $stmt->execute([$admin_email, $hashed]);

            // Guardar configuración de conexión en config.php
            $configPath = $basePath . '/config/config.php';

            $configArray = [
                'app_name' => 'MuseDock CMS',
                'default_lang' => 'es',
                'force_lang' => false,
                'enable_multilang' => true,
                'redirect_on_auth_fail' => '/',
                'admin_dashboard_redirect' => '/musedock/dashboard',
                'db' => [
                    'driver' => $db_driver,
                    'host' => $db_host,
                    'port' => (int)$db_port,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass,
                ],
            ];

            $configContent = "<?php\n\nreturn " . var_export($configArray, true) . ";\n";
            file_put_contents($configPath, $configContent);

            // Crear archivo de lock
            file_put_contents($lockFile, "INSTALLED AT: " . date('Y-m-d H:i:s'));

            // Redirigir al login
            header("Location: /musedock/login");
            exit;

        } catch (PDOException $e) {
            $error = "Error al instalar la base de datos: " . $e->getMessage();
        }
    }
}

// Mostrar el formulario HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación MuseDock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2 class="mb-4">Instalación de MuseDock</h2>

    <p class="lead">Bienvenido al instalador de <strong>MuseDock CMS</strong>, un sistema modular rápido y extensible.<br>
    Antes de continuar asegúrate de tener los siguientes permisos y dependencias:</p>

    <ul>
        <li>✔️ PHP >= 8.0 <?= $checks['php_version'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">Falta</span>' ?></li>
        <li>✔️ Extensión PDO: <?= $checks['pdo'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">Falta</span>' ?></li>
        <li>✔️ Extensión PDO MySQL: <?= $checks['pdo_mysql'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">Falta</span>' ?></li>
        <li>✔️ Permisos de escritura en <code>/config/config.php</code>: <?= $checks['config_writable'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">Falta</span>' ?></li>
        <li>✔️ Permisos de escritura en <code>/storage</code>: <?= $checks['storage_writable'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">Falta</span>' ?></li>
    </ul>

    <?php if (!$okToInstall): ?>
        <div class="alert alert-warning">Corrige los errores anteriores antes de continuar con la instalación.</div>
        </body></html>
        <?php exit; ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="mt-4">
        <h5>Configuración de la base de datos</h5>
        <div class="mb-3">
            <label>Motor de Base de Datos</label>
            <select name="db_driver" id="db_driver" class="form-control" required>
                <option value="mysql">MySQL / MariaDB</option>
                <option value="pgsql">PostgreSQL</option>
            </select>
            <div class="form-text">Selecciona el motor de base de datos que usarás</div>
        </div>
        <div class="mb-3">
            <label>Host BD</label>
            <input type="text" name="db_host" class="form-control" value="localhost" required>
        </div>
        <div class="mb-3">
            <label>Puerto BD</label>
            <input type="number" name="db_port" id="db_port" class="form-control" value="3306" required>
            <div class="form-text">MySQL: 3306 | PostgreSQL: 5432</div>
        </div>
        <div class="mb-3">
            <label>Nombre BD</label>
            <input type="text" name="db_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Usuario BD</label>
            <input type="text" name="db_user" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Contraseña BD</label>
            <input type="password" name="db_pass" class="form-control">
        </div>
        <hr>
        <h5>Administrador del sistema</h5>
        <div class="mb-3">
            <label>Email del administrador</label>
            <input type="email" name="admin_email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Contraseña del administrador</label>
            <input type="password" name="admin_password" class="form-control" required>
            <div class="form-text">Debe tener al menos 8 caracteres.</div>
        </div>
        <button class="btn btn-primary">Instalar CMS</button>
    </form>

    <hr class="my-4">
    <p>Una vez instalado, accede al panel desde <strong><?= $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] ?>/musedock</strong> con el correo y contraseña que has definido.</p>

    <script>
    // Cambiar puerto automáticamente según el driver seleccionado
    document.getElementById('db_driver').addEventListener('change', function() {
        const portInput = document.getElementById('db_port');
        if (this.value === 'pgsql') {
            portInput.value = '5432';
        } else {
            portInput.value = '3306';
        }
    });
    </script>
</body>
</html>
