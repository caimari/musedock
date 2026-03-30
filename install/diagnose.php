<?php
/**
 * Diagnóstico de instalación MuseDock
 * Accede a: http://tu-dominio.com/install/diagnose.php
 *
 * SOLO funciona si el sistema NO está instalado
 */

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');

// VERIFICAR SI YA ESTÁ INSTALADO
$is_installed = file_exists(ROOT_PATH . '/install.lock') || file_exists(ROOT_PATH . '/core/install.lock');

if ($is_installed) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                padding: 50px 40px;
                text-align: center;
            }
            .icon {
                font-size: 80px;
                margin-bottom: 20px;
            }
            h1 {
                color: #dc3545;
                margin-bottom: 20px;
                font-size: 2em;
            }
            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 15px;
            }
            .btn {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 12px 30px;
                text-decoration: none;
                border-radius: 5px;
                font-weight: 500;
                margin-top: 20px;
                transition: background 0.3s;
            }
            .btn:hover {
                background: #5568d3;
            }
            code {
                background: #f8f9fa;
                padding: 2px 8px;
                border-radius: 3px;
                color: #dc3545;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">🔒</div>
            <h1>Acceso Denegado</h1>
            <p>El sistema ya está instalado.</p>
            <p>Por razones de seguridad, el diagnóstico solo está disponible durante la instalación inicial.</p>
            <p>Si necesitas reinstalar el sistema, elimina el archivo <code>install.lock</code> del directorio raíz.</p>
            <a href="/" class="btn">Ir al Sitio Principal</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Deshabilitar redirecciones
define('DISABLE_REDIRECTS', true);

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico MuseDock</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        .section {
            margin: 25px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .check {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        .check-label {
            font-weight: 500;
            color: #333;
        }
        .check-value {
            font-family: monospace;
            font-size: 0.9em;
        }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-info { color: #17a2b8; font-weight: bold; }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 0.85em;
            margin: 10px 0;
        }
        .path-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
        }
        .action {
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .action h3 {
            color: #0066cc;
            margin-bottom: 10px;
        }
        .file-content {
            max-height: 300px;
            overflow-y: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            background: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico MuseDock - Detección de Problemas</h1>

        <!-- 1. INFORMACIÓN DEL SERVIDOR -->
        <div class="section">
            <h2>1. Información del Servidor</h2>
            <div class="check">
                <span class="check-label">PHP Version:</span>
                <span class="check-value status-<?php echo version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'error'; ?>">
                    <?php echo PHP_VERSION; ?>
                </span>
            </div>
            <div class="check">
                <span class="check-label">Document Root:</span>
                <span class="check-value"><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></span>
            </div>
            <div class="check">
                <span class="check-label">Script Filename:</span>
                <span class="check-value"><?php echo __FILE__; ?></span>
            </div>
            <div class="check">
                <span class="check-label">HTTP Host:</span>
                <span class="check-value"><?php echo $_SERVER['HTTP_HOST'] ?? 'N/A'; ?></span>
            </div>
            <div class="check">
                <span class="check-label">Request URI:</span>
                <span class="check-value"><?php echo $_SERVER['REQUEST_URI'] ?? 'N/A'; ?></span>
            </div>
        </div>

        <!-- 2. PATHS DETECTADOS -->
        <div class="section">
            <h2>2. Paths del Sistema</h2>
            <div class="check">
                <span class="check-label">ROOT_PATH:</span>
                <span class="check-value"><?php echo ROOT_PATH; ?></span>
            </div>
            <div class="check">
                <span class="check-label">PUBLIC_PATH:</span>
                <span class="check-value"><?php echo PUBLIC_PATH; ?></span>
            </div>
            <div class="check">
                <span class="check-label">Install Dir:</span>
                <span class="check-value"><?php echo __DIR__; ?></span>
            </div>
        </div>

        <!-- 3. VERIFICACIÓN DE ARCHIVOS CRÍTICOS -->
        <div class="section">
            <h2>3. Archivos Críticos</h2>
            <table>
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Estado</th>
                        <th>Permisos</th>
                        <th>Tamaño</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $critical_files = [
                        'install.lock (root)' => ROOT_PATH . '/install.lock',
                        'install.lock (core)' => ROOT_PATH . '/core/install.lock',
                        '.env' => ROOT_PATH . '/.env',
                        '.env.example' => ROOT_PATH . '/.env.example',
                        'composer.json' => ROOT_PATH . '/composer.json',
                        'vendor/autoload.php' => ROOT_PATH . '/vendor/autoload.php',
                        'core/helpers.php' => ROOT_PATH . '/core/helpers.php',
                        'core/Helpers/functions.php' => ROOT_PATH . '/core/Helpers/functions.php',
                        'public/index.php' => PUBLIC_PATH . '/index.php',
                        'install/index.php' => ROOT_PATH . '/install/index.php',
                    ];

                    foreach ($critical_files as $name => $path) {
                        $exists = file_exists($path);
                        $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';
                        $size = $exists ? filesize($path) . ' bytes' : 'N/A';
                        $status = $exists ? '<span class="status-ok">✓ Existe</span>' : '<span class="status-error">✗ No existe</span>';

                        echo "<tr>";
                        echo "<td><strong>$name</strong><br><small style='color:#666;'>$path</small></td>";
                        echo "<td>$status</td>";
                        echo "<td>$perms</td>";
                        echo "<td>$size</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- 4. VERIFICACIÓN DE .ENV -->
        <div class="section">
            <h2>4. Contenido del .env</h2>
            <?php
            $env_file = ROOT_PATH . '/.env';
            if (file_exists($env_file)) {
                $env_content = file_get_contents($env_file);
                $env_lines = explode("\n", $env_content);

                echo '<div class="check">';
                echo '<span class="check-label">Archivo .env:</span>';
                echo '<span class="check-value status-ok">✓ Existe (' . strlen($env_content) . ' bytes)</span>';
                echo '</div>';

                echo '<table>';
                echo '<thead><tr><th>Variable</th><th>Estado</th><th>Valor (oculto)</th></tr></thead>';
                echo '<tbody>';

                $important_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_KEY', 'APP_URL'];
                foreach ($important_vars as $var) {
                    $found = false;
                    $value = '';
                    foreach ($env_lines as $line) {
                        if (strpos(trim($line), $var . '=') === 0) {
                            $found = true;
                            $parts = explode('=', $line, 2);
                            $value = isset($parts[1]) ? (strlen(trim($parts[1])) > 0 ? '*** (configurado)' : '(vacío)') : '(vacío)';
                            break;
                        }
                    }

                    $status = $found ? '<span class="status-ok">✓ Configurado</span>' : '<span class="status-error">✗ No encontrado</span>';
                    echo "<tr><td><code>$var</code></td><td>$status</td><td>$value</td></tr>";
                }

                echo '</tbody></table>';
            } else {
                echo '<div class="check">';
                echo '<span class="check-label">Archivo .env:</span>';
                echo '<span class="check-value status-error">✗ No existe</span>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- 5. TEST DE AUTOLOAD -->
        <div class="section">
            <h2>5. Test de Autoload</h2>
            <?php
            $autoload_file = ROOT_PATH . '/vendor/autoload.php';
            if (file_exists($autoload_file)) {
                echo '<div class="check">';
                echo '<span class="check-label">vendor/autoload.php:</span>';
                echo '<span class="check-value status-ok">✓ Existe</span>';
                echo '</div>';

                try {
                    require_once $autoload_file;
                    echo '<div class="check">';
                    echo '<span class="check-label">Carga de autoload:</span>';
                    echo '<span class="check-value status-ok">✓ Cargado exitosamente</span>';
                    echo '</div>';

                    // Verificar si core/helpers.php se cargó
                    if (function_exists('env')) {
                        echo '<div class="check">';
                        echo '<span class="check-label">Función env():</span>';
                        echo '<span class="check-value status-ok">✓ Disponible (helpers.php cargado)</span>';
                        echo '</div>';
                    } else {
                        echo '<div class="check">';
                        echo '<span class="check-label">Función env():</span>';
                        echo '<span class="check-value status-error">✗ No disponible (helpers.php no cargado)</span>';
                        echo '</div>';
                    }

                } catch (Exception $e) {
                    echo '<div class="check">';
                    echo '<span class="check-label">Carga de autoload:</span>';
                    echo '<span class="check-value status-error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                    echo '</div>';
                }
            } else {
                echo '<div class="check">';
                echo '<span class="check-label">vendor/autoload.php:</span>';
                echo '<span class="check-value status-error">✗ No existe - Ejecuta: composer install</span>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- 6. ÚLTIMOS ERRORES DEL LOG -->
        <div class="section">
            <h2>6. Últimos Errores (error.log)</h2>
            <?php
            $error_log = ROOT_PATH . '/storage/logs/error.log';
            if (file_exists($error_log)) {
                $lines = file($error_log);
                $last_lines = array_slice($lines, -10);

                if (!empty($last_lines)) {
                    echo '<div class="check">';
                    echo '<span class="check-label">Últimas 10 líneas:</span>';
                    echo '<span class="check-value status-warning">⚠ Ver abajo</span>';
                    echo '</div>';
                    echo '<div class="file-content"><pre>';
                    foreach ($last_lines as $line) {
                        echo htmlspecialchars($line);
                    }
                    echo '</pre></div>';
                } else {
                    echo '<div class="check">';
                    echo '<span class="check-label">error.log:</span>';
                    echo '<span class="check-value status-ok">✓ Vacío (sin errores)</span>';
                    echo '</div>';
                }
            } else {
                echo '<div class="check">';
                echo '<span class="check-label">error.log:</span>';
                echo '<span class="check-value status-info">ℹ No existe (primera ejecución)</span>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- 7. VERIFICACIÓN DE INSTALACIÓN -->
        <div class="section">
            <h2>7. Estado de Instalación</h2>
            <?php
            $is_installed_root = file_exists(ROOT_PATH . '/install.lock');
            $is_installed_core = file_exists(ROOT_PATH . '/core/install.lock');
            $has_env = file_exists(ROOT_PATH . '/.env');

            echo '<div class="check">';
            echo '<span class="check-label">install.lock (root):</span>';
            echo '<span class="check-value status-' . ($is_installed_root ? 'ok' : 'error') . '">';
            echo $is_installed_root ? '✓ Instalado' : '✗ No instalado';
            echo '</span></div>';

            echo '<div class="check">';
            echo '<span class="check-label">install.lock (core - legacy):</span>';
            echo '<span class="check-value status-' . ($is_installed_core ? 'warning' : 'info') . '">';
            echo $is_installed_core ? '⚠ Existe (legacy)' : 'ℹ No existe';
            echo '</span></div>';

            echo '<div class="check">';
            echo '<span class="check-label">.env configurado:</span>';
            echo '<span class="check-value status-' . ($has_env ? 'ok' : 'error') . '">';
            echo $has_env ? '✓ Sí' : '✗ No';
            echo '</span></div>';

            if ($is_installed_root || $is_installed_core) {
                echo '<div class="action">';
                echo '<h3>⚠ Sistema Instalado</h3>';
                echo '<p>El sistema detecta que ya está instalado. Si quieres reinstalar:</p>';
                echo '<ol>';
                echo '<li>Elimina: <code>' . ROOT_PATH . '/install.lock</code></li>';
                if ($is_installed_core) {
                    echo '<li>Elimina: <code>' . ROOT_PATH . '/core/install.lock</code></li>';
                }
                echo '<li>Accede a: <strong>' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/install/</strong></li>';
                echo '</ol>';
                echo '</div>';
            } else {
                echo '<div class="action">';
                echo '<h3>✓ Listo para Instalar</h3>';
                echo '<p>El sistema NO está instalado. Puedes proceder con la instalación:</p>';
                echo '<p><strong>URL: <a href="/install/">' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/install/</a></strong></p>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- 8. VERIFICACIÓN DE PERMISOS -->
        <div class="section">
            <h2>8. Permisos de Directorios</h2>
            <table>
                <thead>
                    <tr>
                        <th>Directorio</th>
                        <th>Existe</th>
                        <th>Permisos</th>
                        <th>Escribible</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dirs = [
                        'storage' => ROOT_PATH . '/storage',
                        'storage/logs' => ROOT_PATH . '/storage/logs',
                        'storage/cache' => ROOT_PATH . '/storage/cache',
                        'storage/views' => ROOT_PATH . '/storage/views',
                        'public/uploads' => PUBLIC_PATH . '/uploads',
                    ];

                    foreach ($dirs as $name => $path) {
                        $exists = is_dir($path);
                        $writable = $exists ? is_writable($path) : false;
                        $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A';

                        echo '<tr>';
                        echo '<td><strong>' . $name . '</strong><br><small style="color:#666;">' . $path . '</small></td>';
                        echo '<td>' . ($exists ? '<span class="status-ok">✓</span>' : '<span class="status-error">✗</span>') . '</td>';
                        echo '<td>' . $perms . '</td>';
                        echo '<td>' . ($writable ? '<span class="status-ok">✓ Sí</span>' : '<span class="status-error">✗ No</span>') . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- 9. CONTENIDO COMPOSER.JSON -->
        <div class="section">
            <h2>9. Verificación composer.json</h2>
            <?php
            $composer_file = ROOT_PATH . '/composer.json';
            if (file_exists($composer_file)) {
                $composer_content = file_get_contents($composer_file);
                $composer_data = json_decode($composer_content, true);

                echo '<div class="check">';
                echo '<span class="check-label">Versión:</span>';
                echo '<span class="check-value">' . ($composer_data['version'] ?? 'N/A') . '</span>';
                echo '</div>';

                echo '<div class="check">';
                echo '<span class="check-label">Nombre del paquete:</span>';
                echo '<span class="check-value">' . ($composer_data['name'] ?? 'N/A') . '</span>';
                echo '</div>';

                if (isset($composer_data['autoload']['files'])) {
                    echo '<div class="check">';
                    echo '<span class="check-label">Archivos autoload:</span>';
                    echo '<span class="check-value">';
                    foreach ($composer_data['autoload']['files'] as $file) {
                        $full_path = ROOT_PATH . '/' . $file;
                        $exists = file_exists($full_path);
                        echo '<div style="margin:5px 0;">';
                        echo '<code>' . htmlspecialchars($file) . '</code> ';
                        echo $exists ? '<span class="status-ok">✓</span>' : '<span class="status-error">✗ NO EXISTE</span>';
                        if (!$exists) {
                            echo '<br><small style="color:#dc3545;">Esperado en: ' . $full_path . '</small>';
                        }
                        echo '</div>';
                    }
                    echo '</span></div>';
                }
            }
            ?>
        </div>

        <!-- 10. ACCIONES RECOMENDADAS -->
        <div class="section">
            <h2>10. Acciones Recomendadas</h2>
            <?php
            $actions = [];

            // Check autoload issue
            if (file_exists(ROOT_PATH . '/composer.json')) {
                $composer_data = json_decode(file_get_contents(ROOT_PATH . '/composer.json'), true);
                if (isset($composer_data['autoload']['files'])) {
                    foreach ($composer_data['autoload']['files'] as $file) {
                        if (!file_exists(ROOT_PATH . '/' . $file)) {
                            $actions[] = [
                                'priority' => 'HIGH',
                                'title' => 'Archivo autoload no encontrado',
                                'description' => "El archivo <code>$file</code> está en composer.json pero no existe.",
                                'solution' => 'Ejecuta: <code>composer dump-autoload</code> después de corregir la ruta en composer.json'
                            ];
                        }
                    }
                }
            }

            // Check vendor
            if (!file_exists(ROOT_PATH . '/vendor/autoload.php')) {
                $actions[] = [
                    'priority' => 'HIGH',
                    'title' => 'Dependencias no instaladas',
                    'description' => 'El directorio vendor/ no existe.',
                    'solution' => 'Ejecuta: <code>composer install --no-dev --optimize-autoloader</code>'
                ];
            }

            // Check .env
            if (!file_exists(ROOT_PATH . '/.env')) {
                $actions[] = [
                    'priority' => 'MEDIUM',
                    'title' => 'Archivo .env no existe',
                    'description' => 'No se encontró el archivo de configuración .env',
                    'solution' => 'Copia .env.example a .env y configúralo con tus datos'
                ];
            }

            // Check storage permissions
            $storage_writable = is_writable(ROOT_PATH . '/storage');
            if (!$storage_writable) {
                $actions[] = [
                    'priority' => 'MEDIUM',
                    'title' => 'Permisos de storage',
                    'description' => 'El directorio storage/ no es escribible',
                    'solution' => 'Ejecuta: <code>chmod -R 755 storage/</code>'
                ];
            }

            if (empty($actions)) {
                echo '<div class="action" style="border-left-color: #28a745; background: #d4edda;">';
                echo '<h3 style="color: #28a745;">✓ Todo parece estar bien</h3>';
                echo '<p>No se detectaron problemas críticos.</p>';
                echo '</div>';
            } else {
                foreach ($actions as $action) {
                    $color = $action['priority'] === 'HIGH' ? '#dc3545' : '#ffc107';
                    $bg = $action['priority'] === 'HIGH' ? '#f8d7da' : '#fff3cd';

                    echo '<div class="action" style="border-left-color: ' . $color . '; background: ' . $bg . ';">';
                    echo '<h3 style="color: ' . $color . ';">' . $action['priority'] . ': ' . $action['title'] . '</h3>';
                    echo '<p>' . $action['description'] . '</p>';
                    echo '<p><strong>Solución:</strong> ' . $action['solution'] . '</p>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center;">
            <p style="color: #666; margin: 10px 0;">
                <strong>MuseDock CMS</strong> - Sistema de diagnóstico
            </p>
            <p style="color: #999; font-size: 0.9em;">
                Generado: <?php echo date('Y-m-d H:i:s'); ?>
            </p>
            <p style="margin-top: 15px;">
                <a href="/install/" style="display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: 500;">
                    ← Volver al Instalador
                </a>
            </p>
        </div>
    </div>
</body>
</html>
