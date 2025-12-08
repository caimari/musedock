<?php
// Obtener configuración de debug
$config = require __DIR__ . '/../../../config/config.php';
$debug = $config['debug'] ?? false;

// Obtener información de la request
$requestUri = $_SERVER['REQUEST_URI'] ?? 'desconocida';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$referer = $_SERVER['HTTP_REFERER'] ?? 'No disponible';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'No disponible';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada</title>
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
            color: #333;
            overflow-x: hidden;
            padding: 20px;
        }

        .container-404 {
            text-align: center;
            padding: 2rem;
            max-width: <?= $debug ? '1200px' : '600px' ?>;
            width: 100%;
        }

        .debug-badge {
            background: #48bb78;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            display: inline-block;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease-out;
            text-align: left;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .error-code {
            font-size: 8rem;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            line-height: 1;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .error-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .error-message {
            font-size: 1.1rem;
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
            text-align: center;
        }

        .debug-section {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
        }

        .debug-section h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }

        .debug-section h3::before {
            content: '';
            width: 4px;
            height: 20px;
            background: #667eea;
            margin-right: 10px;
            border-radius: 2px;
        }

        .debug-info {
            background: #1a202c;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
            margin-bottom: 10px;
        }

        .debug-info strong {
            color: #fbbf24;
        }

        .debug-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .debug-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .info-card h4 {
            color: #2d3748;
            font-size: 0.9rem;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-card p {
            color: #4a5568;
            font-size: 0.85rem;
            word-break: break-all;
        }

        .btn-container {
            text-align: center;
            margin-top: 2rem;
        }

        .btn-home {
            display: inline-block;
            padding: 1rem 2.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
        }

        .btn-back {
            display: inline-block;
            padding: 1rem 2.5rem;
            background: #edf2f7;
            color: #2d3748;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-left: 10px;
        }

        .btn-back:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }

        .shape:nth-child(1) {
            top: 10%;
            left: 10%;
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            top: 60%;
            right: 10%;
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            bottom: 10%;
            left: 20%;
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50% 50% 0 0;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        .search-box {
            margin-top: 2rem;
            display: flex;
            gap: 0.5rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-btn {
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .error-code {
                font-size: 5rem;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .error-message {
                font-size: 1rem;
            }

            .error-box {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container-404">
        <?php if ($debug): ?>
        <div class="debug-badge">
            🔧 Modo DEBUG activado - Información detallada disponible
        </div>
        <?php endif; ?>

        <div class="error-box">
            <div class="error-header">
                <div class="error-code">404</div>
                <h1 class="error-title">¡Oops! Página no encontrada</h1>
                <p class="error-message">
                    Lo sentimos, la página que estás buscando no existe o ha sido movida.
                    <?php if ($debug): ?>
                    <br><br>
                    <strong>Posibles causas:</strong> Módulo desconectado, ruta no definida, archivo eliminado o URL incorrecta.
                    <?php else: ?>
                    <br>
                    ¿Quizás quieras volver al inicio?
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($debug): ?>
            <!-- Información de Debug -->
            <div class="debug-section">
                <h3>📍 Información de la Request</h3>
                <div class="debug-info">
                    <strong>URL solicitada:</strong> <?= htmlspecialchars($requestUri) ?><br>
                    <strong>Método HTTP:</strong> <?= htmlspecialchars($requestMethod) ?><br>
                    <strong>Timestamp:</strong> <?= date('Y-m-d H:i:s') ?>
                </div>
            </div>

            <div class="debug-section">
                <h3>🔍 Detalles Adicionales</h3>
                <div class="debug-grid">
                    <div class="info-card">
                        <h4>Referencia</h4>
                        <p><?= htmlspecialchars($referer) ?></p>
                    </div>
                    <div class="info-card">
                        <h4>User Agent</h4>
                        <p><?= htmlspecialchars(substr($userAgent, 0, 80)) . (strlen($userAgent) > 80 ? '...' : '') ?></p>
                    </div>
                    <div class="info-card">
                        <h4>IP del Cliente</h4>
                        <p><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'No disponible') ?></p>
                    </div>
                    <div class="info-card">
                        <h4>Servidor</h4>
                        <p><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'No disponible') ?></p>
                    </div>
                </div>
            </div>

            <div class="debug-section">
                <h3>💡 Sugerencias para resolver</h3>
                <div class="debug-info" style="background: #fef5e7; color: #856404; border: 1px solid #ffc107;">
                    <strong>1.</strong> Verifica que el módulo esté activado en el panel de administración<br>
                    <strong>2.</strong> Asegúrate de que la ruta esté definida correctamente en routes.php<br>
                    <strong>3.</strong> Revisa que el controlador y método existan<br>
                    <strong>4.</strong> Comprueba los permisos de archivos y directorios<br>
                    <strong>5.</strong> Revisa los logs en storage/logs/ para más detalles
                </div>
            </div>
            <?php endif; ?>

            <div class="btn-container">
                <a href="/" class="btn-home">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    Volver al inicio
                </a>
                <a href="javascript:history.back()" class="btn-back">← Volver Atrás</a>
            </div>

            <?php if (!$debug): ?>
            <div class="search-box">
                <input type="text" class="search-input" placeholder="Buscar en el sitio..." id="searchInput">
                <button class="search-btn" onclick="searchSite()">Buscar</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function searchSite() {
            const query = document.getElementById('searchInput').value;
            if (query.trim()) {
                window.location.href = '/?s=' + encodeURIComponent(query);
            }
        }

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchSite();
                }
            });
        }
    </script>
</body>
</html>
