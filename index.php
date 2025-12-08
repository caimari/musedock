<?php
/**
 * MuseDock CMS - Root Index (Security Warning)
 *
 * This file should NEVER be executed in production.
 * If you're seeing this, your web server is NOT properly configured.
 */

// Security warning: accessing from wrong directory
$isProduction = !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MuseDock CMS - Configuration Required</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
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
            color: #1f2937;
            font-size: 32px;
            margin-bottom: 16px;
            text-align: center;
        }

        .subtitle {
            color: #6b7280;
            font-size: 18px;
            text-align: center;
            margin-bottom: 32px;
        }

        .alert {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 8px;
        }

        .alert h3 {
            color: #92400e;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .alert p {
            color: #78350f;
            line-height: 1.6;
        }

        .section {
            margin-bottom: 32px;
        }

        .section h2 {
            color: #1f2937;
            font-size: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step {
            background: #f9fafb;
            border-left: 4px solid #6366f1;
            padding: 16px;
            margin-bottom: 12px;
            border-radius: 8px;
        }

        .step strong {
            color: #4f46e5;
            display: block;
            margin-bottom: 8px;
        }

        code {
            background: #1f2937;
            color: #10b981;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .code-block {
            background: #1f2937;
            color: #e5e7eb;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 12px 0;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .code-block .comment {
            color: #9ca3af;
        }

        .code-block .keyword {
            color: #f59e0b;
        }

        .code-block .value {
            color: #10b981;
        }

        .danger {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .danger h3 {
            color: #991b1b;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .danger ul {
            color: #7f1d1d;
            margin-left: 24px;
            line-height: 1.8;
        }

        .success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 16px;
            border-radius: 8px;
        }

        .success h3 {
            color: #065f46;
            font-size: 18px;
            margin-bottom: 8px;
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
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning-icon">⚠️</div>

        <h1>Document Root Configuration Required</h1>
        <p class="subtitle">Your web server is not pointing to the correct directory</p>

        <div class="alert">
            <h3>🔒 Security Risk Detected</h3>
            <p>
                Your web server's document root is pointing to the project root directory instead of the
                <code>public/</code> folder. This exposes sensitive files like <code>.env</code>,
                configuration files, and source code to the internet.
            </p>
        </div>

        <div class="danger">
            <h3>🚨 What's Exposed Right Now:</h3>
            <ul>
                <li><code>.env</code> - Database credentials and API keys</li>
                <li><code>config/</code> - System configuration files</li>
                <li><code>storage/logs/</code> - Application logs with sensitive data</li>
                <li><code>vendor/</code> - Third-party libraries (potential vulnerabilities)</li>
                <li><code>core/</code> - Application source code</li>
            </ul>
        </div>

        <div class="section">
            <h2>🔧 How to Fix</h2>

            <div class="step">
                <strong>Apache (.htaccess)</strong>
                <p>Edit your Apache configuration or virtual host file:</p>
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
                <p>Then restart Apache: <code>sudo systemctl restart apache2</code></p>
            </div>

            <div class="step">
                <strong>Nginx</strong>
                <p>Edit your Nginx server block configuration:</p>
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
                <p>Then restart Nginx: <code>sudo systemctl restart nginx</code></p>
            </div>

            <div class="step">
                <strong>cPanel / Shared Hosting</strong>
                <p>In your hosting control panel:</p>
                <ol style="margin-left: 24px; margin-top: 12px; line-height: 1.8;">
                    <li>Go to "Addon Domains" or "Domains"</li>
                    <li>Set document root to: <code>public_html/musedock/public</code></li>
                    <li>Or create a subdomain pointing to the <code>public</code> folder</li>
                    <li>Alternatively, move all files from <code>public/</code> to <code>public_html/</code>
                        and move <code>core/</code>, <code>config/</code>, etc. one level up</li>
                </ol>
            </div>
        </div>

        <div class="section">
            <h2>📝 Alternative: Move Files (Not Recommended)</h2>
            <p style="margin-bottom: 12px; color: #6b7280;">
                If you cannot change document root, you can restructure the files (but this is less secure):
            </p>
            <div class="code-block">
<span class="comment"># Move public contents to root</span>
mv public/* ./
mv public/.htaccess ./

<span class="comment"># Move sensitive directories outside web root (if possible)</span>
mv core/ ../core/
mv config/ ../config/
mv storage/ ../storage/

<span class="comment"># Update paths in index.php accordingly</span>
            </div>
        </div>

        <?php if (!$isProduction): ?>
        <div class="success">
            <h3>🔄 Temporary Redirect (Development Only)</h3>
            <p>Since you're accessing from localhost, you can temporarily access the installer:</p>
            <a href="/public/install/" class="btn">Go to Installer (Development)</a>
        </div>
        <?php endif; ?>

        <div class="footer">
            <strong>MuseDock CMS</strong><br>
            For more help, visit: <a href="https://musedock.org/docs" target="_blank">Documentation</a>
        </div>
    </div>
</body>
</html>
