<?php
/**
 * Simple diagnostic check - Works anywhere
 * Upload to your root directory and access via browser
 * Works both in root and public/ folder
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auto-detect if we're in public/ folder
$current = __DIR__;
$is_in_public = basename($current) === 'public';
$root = $is_in_public ? dirname($current) : $current;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>MuseDock Quick Check</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .ok { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        h1 { color: #569cd6; }
        pre { background: #252526; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .box { background: #252526; padding: 15px; margin: 10px 0; border-left: 3px solid #569cd6; }
    </style>
</head>
<body>
    <h1>🔍 MuseDock Quick Check</h1>

    <div class="box">
        <strong>PHP Version:</strong>
        <span class="<?php echo version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'error'; ?>">
            <?php echo PHP_VERSION; ?>
        </span>
    </div>

    <div class="box">
        <strong>Current Folder:</strong> <?php echo $current; ?>
        <?php if ($is_in_public): ?>
            <span class="warning">⚠ Script is in public/</span>
        <?php else: ?>
            <span class="ok">✓ Script is in root</span>
        <?php endif; ?>
    </div>

    <div class="box">
        <strong>Detected Root:</strong> <?php echo $root; ?>
    </div>

    <div class="box">
        <strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?>
    </div>

    <div class="box">
        <strong>Script Path:</strong> <?php echo __FILE__; ?>
    </div>

    <h2>Critical Files:</h2>
    <pre><?php
    $files = [
        '.env' => $root . '/.env',
        '.env.example' => $root . '/.env.example',
        'composer.json' => $root . '/composer.json',
        'vendor/autoload.php' => $root . '/vendor/autoload.php',
        'core/helpers.php' => $root . '/core/helpers.php',
        'core/Helpers/functions.php' => $root . '/core/Helpers/functions.php',
        'install.lock (root)' => $root . '/install.lock',
        'install.lock (core)' => $root . '/core/install.lock',
    ];

    foreach ($files as $name => $path) {
        $exists = file_exists($path);
        $icon = $exists ? '✓' : '✗';
        $class = $exists ? 'ok' : 'error';
        echo sprintf('<span class="%s">%s</span> %s', $class, $icon, $name);
        if ($exists) {
            echo ' (' . filesize($path) . ' bytes)';
        }
        echo "\n";
    }
    ?></pre>

    <h2>Composer Autoload Check:</h2>
    <pre><?php
    $composer_file = $root . '/composer.json';
    if (file_exists($composer_file)) {
        $composer = json_decode(file_get_contents($composer_file), true);
        echo "Version: " . ($composer['version'] ?? 'N/A') . "\n";
        echo "Package: " . ($composer['name'] ?? 'N/A') . "\n\n";

        if (isset($composer['autoload']['files'])) {
            echo "Autoload Files:\n";
            foreach ($composer['autoload']['files'] as $file) {
                $full = $root . '/' . $file;
                $exists = file_exists($full);
                $icon = $exists ? '✓' : '✗';
                $class = $exists ? 'ok' : 'error';
                echo sprintf('  <span class="%s">%s</span> %s', $class, $icon, $file);
                if (!$exists) {
                    echo ' <span class="error">[MISSING!]</span>';
                }
                echo "\n";
            }
        }
    } else {
        echo '<span class="error">composer.json not found!</span>';
    }
    ?></pre>

    <h2>Try Loading Autoload:</h2>
    <pre><?php
    $autoload = $root . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        try {
            require $autoload;
            echo '<span class="ok">✓ Autoload loaded successfully</span>' . "\n";

            if (function_exists('env')) {
                echo '<span class="ok">✓ env() function available</span>' . "\n";
            } else {
                echo '<span class="error">✗ env() function NOT available</span>' . "\n";
            }
        } catch (Throwable $e) {
            echo '<span class="error">✗ Error loading autoload:</span>' . "\n";
            echo $e->getMessage() . "\n";
            echo "\nFile: " . $e->getFile() . ':' . $e->getLine() . "\n";
        }
    } else {
        echo '<span class="error">✗ vendor/autoload.php not found</span>';
    }
    ?></pre>

    <h2>Storage Permissions:</h2>
    <pre><?php
    $dirs = [
        'storage' => $root . '/storage',
        'storage/logs' => $root . '/storage/logs',
        'storage/cache' => $root . '/storage/cache',
    ];

    foreach ($dirs as $name => $path) {
        if (is_dir($path)) {
            $writable = is_writable($path);
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $icon = $writable ? '✓' : '✗';
            $class = $writable ? 'ok' : 'error';
            echo sprintf('<span class="%s">%s</span> %s: %s %s',
                $class, $icon, $name, $perms, $writable ? 'writable' : 'NOT writable'
            ) . "\n";
        } else {
            echo '<span class="error">✗</span> ' . $name . ': NOT EXISTS' . "\n";
        }
    }
    ?></pre>

    <h2>Last Error Log (if exists):</h2>
    <pre><?php
    $error_log = $root . '/storage/logs/error.log';
    if (file_exists($error_log)) {
        $lines = file($error_log);
        $last = array_slice($lines, -5);
        if (!empty($last)) {
            foreach ($last as $line) {
                echo htmlspecialchars($line);
            }
        } else {
            echo '<span class="ok">Log is empty</span>';
        }
    } else {
        echo '<span class="warning">error.log not found (might be OK for new install)</span>';
    }
    ?></pre>

    <h2>Environment Variables (.env):</h2>
    <pre><?php
    $env_file = $root . '/.env';
    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
        echo "File size: " . strlen($env_content) . " bytes\n\n";

        $vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_KEY'];
        foreach ($vars as $var) {
            $found = strpos($env_content, $var . '=') !== false;
            $icon = $found ? '✓' : '✗';
            $class = $found ? 'ok' : 'error';
            echo sprintf('<span class="%s">%s</span> %s', $class, $icon, $var) . "\n";
        }
    } else {
        echo '<span class="error">.env file not found</span>';
    }
    ?></pre>

    <div class="box" style="margin-top: 30px; border-left-color: #4ec9b0;">
        <strong>Next Steps:</strong><br>
        • If autoload fails, run: <code>composer dump-autoload</code><br>
        • If .env missing, copy from .env.example<br>
        • If permissions fail, run: <code>chmod -R 755 storage/</code><br>
        • Access installer: <a href="/install/" style="color: #4ec9b0;">/install/</a>
    </div>

    <p style="text-align: center; margin-top: 40px; color: #858585;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?>
    </p>
</body>
</html>
