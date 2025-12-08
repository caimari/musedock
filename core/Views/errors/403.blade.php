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
    <title>403 - Acceso Denegado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            overflow-x: hidden;
            padding: 20px;
        }

        .container-403 {
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

        .error-icon {
            font-size: 6rem;
            margin-bottom: 1rem;
            animation: shake 2s ease-in-out infinite;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: rotate(-10deg); }
            20%, 40%, 60%, 80% { transform: rotate(10deg); }
        }

        .error-code {
            font-size: 8rem;
            font-weight: 900;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            line-height: 1;
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
            border-left: 4px solid #f5576c;
        }

        .debug-section h3 {
            color: #f5576c;
            margin-bottom: 15px;
            font-size: 1.1rem;
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

        .btn-container {
            text-align: center;
            margin-top: 2rem;
        }

        .btn-home {
            display: inline-block;
            padding: 1rem 2.5rem;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(245, 87, 108, 0.4);
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(245, 87, 108, 0.6);
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
    <div class="container-403">
        <?php if ($debug): ?>
        <div class="debug-badge">
            🔧 Modo DEBUG activado - Información detallada disponible
        </div>
        <?php endif; ?>

        <div class="error-box">
            <div class="error-header">
                <div class="error-icon">🔒</div>
                <div class="error-code">403</div>
                <h1 class="error-title">Acceso Denegado</h1>
                <p class="error-message">
                    No tienes permisos para acceder a este recurso.
                    <?php if ($debug): ?>
                    <br><br>
                    <strong>Posibles causas:</strong> Falta de permisos de usuario, token de sesión inválido, IP bloqueada o recurso restringido.
                    <?php else: ?>
                    <br>
                    Por favor, verifica tus credenciales o contacta al administrador.
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
                    <strong>IP del Cliente:</strong> <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'No disponible') ?><br>
                    <strong>Timestamp:</strong> <?= date('Y-m-d H:i:s') ?>
                </div>
            </div>

            <div class="debug-section">
                <h3>🔐 Información de Sesión</h3>
                <div class="debug-info">
                    <strong>Sesión iniciada:</strong> <?= session_status() === PHP_SESSION_ACTIVE ? 'Sí' : 'No' ?><br>
                    <strong>Usuario autenticado:</strong> <?= isset($_SESSION['user_id']) ? 'Sí (ID: ' . $_SESSION['user_id'] . ')' : 'No' ?><br>
                    <strong>Referer:</strong> <?= htmlspecialchars($referer) ?>
                </div>
            </div>

            <div class="debug-section">
                <h3>💡 Sugerencias para resolver</h3>
                <div class="debug-info" style="background: #fef5e7; color: #856404; border: 1px solid #ffc107;">
                    <strong>1.</strong> Verifica que el usuario tenga los permisos necesarios<br>
                    <strong>2.</strong> Comprueba que la sesión esté activa y válida<br>
                    <strong>3.</strong> Revisa los roles y capacidades del usuario<br>
                    <strong>4.</strong> Verifica si hay restricciones por IP o geolocalización<br>
                    <strong>5.</strong> Revisa los logs de seguridad en storage/logs/
                </div>
            </div>
            <?php endif; ?>

            <div class="btn-container">
                <a href="/" class="btn-home">
                    🏠 Volver al Inicio
                </a>
                <a href="javascript:history.back()" class="btn-back">← Volver Atrás</a>
            </div>
        </div>
    </div>
</body>
</html>
