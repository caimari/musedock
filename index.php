<?php
/**
 * MuseDock CMS - Root Index (Security Warning)
 *
 * This file should NEVER be executed in production.
 * If you're seeing this, your web server is NOT properly configured.
 */

// Detect language from browser or cookie
$lang = $_COOKIE['musedock_lang'] ?? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
if (!in_array($lang, ['en', 'es'])) {
    $lang = 'en';
}

// Handle language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'es'])) {
    $lang = $_GET['lang'];
    setcookie('musedock_lang', $lang, time() + (365 * 24 * 60 * 60), '/');
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$isProduction = !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);

// Translations
$i18n = [
    'en' => [
        'title' => 'Document Root Configuration Required',
        'subtitle' => 'Your web server is not pointing to the correct directory',
        'security_risk' => 'Security Risk Detected',
        'security_desc' => 'Your web server\'s document root is pointing to the project root directory instead of the <code>public/</code> folder. This exposes sensitive files like <code>.env</code>, configuration files, and source code to the internet.',
        'whats_exposed' => 'What\'s Exposed Right Now:',
        'exposed_env' => 'Database credentials and API keys',
        'exposed_config' => 'System configuration files',
        'exposed_logs' => 'Application logs with sensitive data',
        'exposed_vendor' => 'Third-party libraries (potential vulnerabilities)',
        'exposed_core' => 'Application source code',
        'how_to_fix' => 'How to Fix',
        'apache_step' => 'Edit your Apache configuration or virtual host file:',
        'apache_restart' => 'Then restart Apache:',
        'nginx_step' => 'Edit your Nginx server block configuration:',
        'nginx_restart' => 'Then restart Nginx:',
        'cpanel_step' => 'In your hosting control panel:',
        'cpanel_1' => 'Go to "Addon Domains" or "Domains"',
        'cpanel_2' => 'Set document root to:',
        'cpanel_3' => 'Or create a subdomain pointing to the <code>public</code> folder',
        'cpanel_4' => 'Alternatively, move all files from <code>public/</code> to <code>public_html/</code> and move <code>core/</code>, <code>config/</code>, etc. one level up',
        'alternative' => 'Alternative: Move Files (Not Recommended)',
        'alternative_desc' => 'If you cannot change document root, you can restructure the files (but this is less secure):',
        'move_comment1' => '# Move public contents to root',
        'move_comment2' => '# Move sensitive directories outside web root (if possible)',
        'move_comment3' => '# Update paths in index.php accordingly',
        'dev_redirect' => 'Temporary Redirect (Development Only)',
        'dev_desc' => 'Since you\'re accessing from localhost, you can temporarily access the installer:',
        'goto_installer' => 'Go to Installer (Development)',
        'footer_help' => 'For more help, visit:',
        'documentation' => 'Documentation'
    ],
    'es' => [
        'title' => 'Configuración de Document Root Requerida',
        'subtitle' => 'Tu servidor web no está apuntando al directorio correcto',
        'security_risk' => 'Riesgo de Seguridad Detectado',
        'security_desc' => 'El document root de tu servidor web está apuntando al directorio raíz del proyecto en lugar de la carpeta <code>public/</code>. Esto expone archivos sensibles como <code>.env</code>, archivos de configuración y código fuente a internet.',
        'whats_exposed' => 'Lo Que Está Expuesto Ahora Mismo:',
        'exposed_env' => 'Credenciales de base de datos y claves API',
        'exposed_config' => 'Archivos de configuración del sistema',
        'exposed_logs' => 'Logs de la aplicación con datos sensibles',
        'exposed_vendor' => 'Bibliotecas de terceros (vulnerabilidades potenciales)',
        'exposed_core' => 'Código fuente de la aplicación',
        'how_to_fix' => 'Cómo Solucionarlo',
        'apache_step' => 'Edita tu configuración de Apache o archivo de virtual host:',
        'apache_restart' => 'Luego reinicia Apache:',
        'nginx_step' => 'Edita la configuración del bloque server de Nginx:',
        'nginx_restart' => 'Luego reinicia Nginx:',
        'cpanel_step' => 'En el panel de control de tu hosting:',
        'cpanel_1' => 'Ve a "Dominios Addon" o "Dominios"',
        'cpanel_2' => 'Configura el document root a:',
        'cpanel_3' => 'O crea un subdominio apuntando a la carpeta <code>public</code>',
        'cpanel_4' => 'Alternativamente, mueve todos los archivos de <code>public/</code> a <code>public_html/</code> y mueve <code>core/</code>, <code>config/</code>, etc. un nivel arriba',
        'alternative' => 'Alternativa: Mover Archivos (No Recomendado)',
        'alternative_desc' => 'Si no puedes cambiar el document root, puedes reestructurar los archivos (pero esto es menos seguro):',
        'move_comment1' => '# Mover contenido de public a raíz',
        'move_comment2' => '# Mover directorios sensibles fuera de la web root (si es posible)',
        'move_comment3' => '# Actualizar rutas en index.php en consecuencia',
        'dev_redirect' => 'Redirección Temporal (Solo Desarrollo)',
        'dev_desc' => 'Como estás accediendo desde localhost, puedes acceder temporalmente al instalador:',
        'goto_installer' => 'Ir al Instalador (Desarrollo)',
        'footer_help' => 'Para más ayuda, visita:',
        'documentation' => 'Documentación'
    ]
];

$t = $i18n[$lang];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseDock CMS - <?= $t['title'] ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #e2e8f0;
        }

        .lang-selector {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 8px;
            z-index: 1000;
        }

        .lang-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 14px;
        }

        .lang-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .lang-btn.active {
            background: #6366f1;
            border-color: #6366f1;
            color: white;
        }

        .container {
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            max-width: 900px;
            width: 100%;
            padding: 40px;
        }

        .warning-icon {
            width: 80px;
            height: 80px;
            background: #fbbf24;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 48px;
        }

        h1 {
            color: #f1f5f9;
            font-size: 32px;
            margin-bottom: 16px;
            text-align: center;
        }

        .subtitle {
            color: #94a3b8;
            font-size: 18px;
            text-align: center;
            margin-bottom: 32px;
        }

        .alert {
            background: rgba(251, 191, 36, 0.15);
            border-left: 4px solid #fbbf24;
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 8px;
        }

        .alert h3 {
            color: #fbbf24;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .alert p {
            color: #cbd5e1;
            line-height: 1.6;
        }

        .section {
            margin-bottom: 32px;
        }

        .section h2 {
            color: #f1f5f9;
            font-size: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step {
            background: rgba(51, 65, 85, 0.6);
            border-left: 4px solid #6366f1;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
        }

        .step strong {
            color: #818cf8;
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .step p {
            color: #cbd5e1;
            margin-bottom: 8px;
        }

        .step ol {
            color: #cbd5e1;
        }

        code {
            background: rgba(0, 0, 0, 0.4);
            color: #10b981;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .code-block {
            background: #0f172a;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 12px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .code-block .comment {
            color: #64748b;
        }

        .code-block .keyword {
            color: #f59e0b;
        }

        .code-block .value {
            color: #10b981;
        }

        .danger {
            background: rgba(239, 68, 68, 0.15);
            border-left: 4px solid #ef4444;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .danger h3 {
            color: #fca5a5;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .danger ul {
            color: #cbd5e1;
            margin-left: 24px;
            line-height: 1.8;
        }

        .danger code {
            background: rgba(0, 0, 0, 0.3);
            color: #fca5a5;
        }

        .success {
            background: rgba(16, 185, 129, 0.15);
            border-left: 4px solid #10b981;
            padding: 16px;
            border-radius: 8px;
        }

        .success h3 {
            color: #6ee7b7;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .success p {
            color: #cbd5e1;
        }

        .btn {
            display: inline-block;
            background: #6366f1;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
            margin-top: 16px;
        }

        .btn:hover {
            background: #4f46e5;
        }

        .footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            font-size: 14px;
        }

        .footer a {
            color: #818cf8;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .container {
                padding: 24px;
            }

            h1 {
                font-size: 24px;
            }

            .code-block {
                font-size: 12px;
            }

            .lang-selector {
                top: 10px;
                right: 10px;
            }

            .lang-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="lang-selector">
        <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">English</a>
        <a href="?lang=es" class="lang-btn <?= $lang === 'es' ? 'active' : '' ?>">Español</a>
    </div>

    <div class="container">
        <div class="warning-icon">⚠️</div>

        <h1><?= $t['title'] ?></h1>
        <p class="subtitle"><?= $t['subtitle'] ?></p>

        <div class="alert">
            <h3>🔒 <?= $t['security_risk'] ?></h3>
            <p><?= $t['security_desc'] ?></p>
        </div>

        <div class="danger">
            <h3>🚨 <?= $t['whats_exposed'] ?></h3>
            <ul>
                <li><code>.env</code> - <?= $t['exposed_env'] ?></li>
                <li><code>config/</code> - <?= $t['exposed_config'] ?></li>
                <li><code>storage/logs/</code> - <?= $t['exposed_logs'] ?></li>
                <li><code>vendor/</code> - <?= $t['exposed_vendor'] ?></li>
                <li><code>core/</code> - <?= $t['exposed_core'] ?></li>
            </ul>
        </div>

        <div class="section">
            <h2>🔧 <?= $t['how_to_fix'] ?></h2>

            <div class="step">
                <strong>Apache (.htaccess)</strong>
                <p><?= $t['apache_step'] ?></p>
                <div class="code-block">
<span class="comment"># Apache VirtualHost Configuration</span>
&lt;VirtualHost *:80&gt;
    ServerName <span class="value">yourdomain.com</span>
    <span class="keyword">DocumentRoot</span> <span class="value">/var/www/html/musedock/public</span>

    &lt;Directory <span class="value">/var/www/html/musedock/public</span>&gt;
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
&lt;/VirtualHost&gt;
                </div>
                <p><?= $t['apache_restart'] ?> <code>sudo systemctl restart apache2</code></p>
            </div>

            <div class="step">
                <strong>Nginx</strong>
                <p><?= $t['nginx_step'] ?></p>
                <div class="code-block">
<span class="comment"># Nginx Server Block</span>
server {
    listen 80;
    server_name <span class="value">yourdomain.com</span>;
    <span class="keyword">root</span> <span class="value">/var/www/html/musedock/public</span>;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
                </div>
                <p><?= $t['nginx_restart'] ?> <code>sudo systemctl restart nginx</code></p>
            </div>

            <div class="step">
                <strong>cPanel / Shared Hosting</strong>
                <p><?= $t['cpanel_step'] ?></p>
                <ol style="margin-left: 24px; margin-top: 12px; line-height: 1.8;">
                    <li><?= $t['cpanel_1'] ?></li>
                    <li><?= $t['cpanel_2'] ?> <code>public_html/musedock/public</code></li>
                    <li><?= $t['cpanel_3'] ?></li>
                    <li><?= $t['cpanel_4'] ?></li>
                </ol>
            </div>
        </div>

        <div class="section">
            <h2>📝 <?= $t['alternative'] ?></h2>
            <p style="margin-bottom: 12px; color: #94a3b8;">
                <?= $t['alternative_desc'] ?>
            </p>
            <div class="code-block">
<span class="comment"><?= $t['move_comment1'] ?></span>
mv public/* ./
mv public/.htaccess ./

<span class="comment"><?= $t['move_comment2'] ?></span>
mv core/ ../core/
mv config/ ../config/
mv storage/ ../storage/

<span class="comment"><?= $t['move_comment3'] ?></span>
            </div>
        </div>

        <?php if (!$isProduction): ?>
        <div class="success">
            <h3>🔄 <?= $t['dev_redirect'] ?></h3>
            <p><?= $t['dev_desc'] ?></p>
            <a href="/public/install/" class="btn"><?= $t['goto_installer'] ?></a>
        </div>
        <?php endif; ?>

        <div class="footer">
            <strong>MuseDock CMS</strong><br>
            <?= $t['footer_help'] ?> <a href="https://musedock.org/docs" target="_blank"><?= $t['documentation'] ?></a>
        </div>
    </div>
</body>
</html>
