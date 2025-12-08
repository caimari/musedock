<?php

namespace Screenart\Musedock;

/**
 * Manejador de errores con interfaz estética
 *
 * Muestra errores de forma elegante con detalles completos en modo debug
 * y mensajes genéricos en producción
 *
 * @package Screenart\Musedock
 */
class ErrorHandler
{
    /**
     * Renderiza una página de error elegante
     *
     * @param \Throwable $exception Excepción capturada
     * @param int $httpCode Código HTTP (default: 500)
     * @param string $title Título del error
     * @return void
     */
    public static function render(\Throwable $exception, int $httpCode = 500, string $title = 'Error en la Aplicación')
    {
        http_response_code($httpCode);

        $config = require __DIR__ . '/../config/config.php';
        $debug = $config['debug'] ?? false;

        // Log del error siempre
        Logger::exception($exception, 'ERROR', [
            'http_code' => $httpCode,
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        ]);

        // Renderizar según modo
        if ($debug) {
            self::renderDebug($exception, $httpCode, $title);
        } else {
            self::renderProduction($httpCode, $title);
        }

        exit;
    }

    /**
     * Renderiza error en modo DEBUG (con todos los detalles)
     */
    private static function renderDebug(\Throwable $exception, int $httpCode, string $title)
    {
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();
        $type = get_class($exception);

        // Obtener contexto del archivo (líneas alrededor del error)
        $context = self::getFileContext($file, $line);

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?> - MuseDock</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    padding: 20px;
                    color: #333;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                }
                .error-box {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    overflow: hidden;
                    margin-bottom: 20px;
                }
                .header {
                    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                    padding: 30px;
                    color: white;
                }
                .header h1 {
                    font-size: 28px;
                    margin-bottom: 10px;
                    font-weight: 600;
                }
                .header .code {
                    background: rgba(255,255,255,0.2);
                    display: inline-block;
                    padding: 5px 15px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 500;
                }
                .content {
                    padding: 30px;
                }
                .section {
                    margin-bottom: 25px;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: #667eea;
                    margin-bottom: 10px;
                    display: flex;
                    align-items: center;
                }
                .section-title::before {
                    content: '';
                    width: 4px;
                    height: 20px;
                    background: #667eea;
                    margin-right: 10px;
                    border-radius: 2px;
                }
                .error-message {
                    background: #fff5f5;
                    border-left: 4px solid #f56565;
                    padding: 15px 20px;
                    border-radius: 6px;
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    font-size: 14px;
                    color: #c53030;
                    line-height: 1.6;
                }
                .file-location {
                    background: #f7fafc;
                    padding: 12px 20px;
                    border-radius: 6px;
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    font-size: 13px;
                    color: #2d3748;
                    margin-top: 10px;
                }
                .file-location strong {
                    color: #667eea;
                }
                .code-context {
                    background: #1a202c;
                    border-radius: 8px;
                    overflow: hidden;
                }
                .code-context pre {
                    padding: 20px;
                    overflow-x: auto;
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    font-size: 13px;
                    line-height: 1.6;
                    color: #a0aec0;
                }
                .code-line {
                    display: block;
                    padding: 2px 10px;
                    margin: 0 -10px;
                }
                .code-line.error-line {
                    background: rgba(245, 101, 101, 0.2);
                    border-left: 4px solid #f56565;
                    padding-left: 6px;
                }
                .code-line .line-number {
                    display: inline-block;
                    width: 50px;
                    color: #4a5568;
                    text-align: right;
                    margin-right: 15px;
                    user-select: none;
                }
                .code-line.error-line .line-number {
                    color: #f56565;
                    font-weight: bold;
                }
                .stack-trace {
                    background: #f7fafc;
                    padding: 20px;
                    border-radius: 8px;
                    max-height: 400px;
                    overflow-y: auto;
                }
                .stack-trace pre {
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    font-size: 12px;
                    line-height: 1.8;
                    color: #2d3748;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
                .badge {
                    display: inline-block;
                    background: #edf2f7;
                    color: #2d3748;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                    margin-left: 10px;
                }
                .debug-mode {
                    background: #c6f6d5;
                    color: #22543d;
                    padding: 10px 20px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    font-size: 14px;
                    text-align: center;
                    font-weight: 500;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    color: white;
                    font-size: 14px;
                    opacity: 0.9;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="debug-mode">
                    🔧 Modo DEBUG activado - Se muestran detalles completos del error
                </div>

                <div class="error-box">
                    <div class="header">
                        <h1>⚠️ <?= htmlspecialchars($title) ?></h1>
                        <div class="code">HTTP <?= $httpCode ?></div>
                        <span class="badge"><?= htmlspecialchars($type) ?></span>
                    </div>

                    <div class="content">
                        <div class="section">
                            <div class="section-title">Mensaje de Error</div>
                            <div class="error-message">
                                <?= htmlspecialchars($message) ?>
                            </div>
                            <div class="file-location">
                                <strong>Archivo:</strong> <?= htmlspecialchars($file) ?><br>
                                <strong>Línea:</strong> <?= $line ?>
                            </div>
                        </div>

                        <?php if ($context): ?>
                        <div class="section">
                            <div class="section-title">Código Fuente (contexto)</div>
                            <div class="code-context">
                                <pre><?php foreach ($context as $lineNum => $lineContent): ?><span class="code-line <?= $lineNum === $line ? 'error-line' : '' ?>"><span class="line-number"><?= $lineNum ?></span><?= htmlspecialchars($lineContent) ?></span>
<?php endforeach; ?></pre>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="section">
                            <div class="section-title">Stack Trace</div>
                            <div class="stack-trace">
                                <pre><?= htmlspecialchars($trace) ?></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    MuseDock CMS v2.0 - Para desactivar este modo, establece APP_DEBUG=false en .env
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Renderiza error en modo PRODUCCIÓN (mensaje genérico con estética)
     */
    private static function renderProduction(int $httpCode, string $title)
    {
        $messages = [
            500 => [
                'title' => 'Error Interno del Servidor',
                'description' => 'Lo sentimos, ocurrió un error inesperado. Nuestro equipo ha sido notificado.',
                'icon' => '🔧'
            ],
            404 => [
                'title' => 'Página No Encontrada',
                'description' => 'La página que buscas no existe o ha sido movida.',
                'icon' => '🔍'
            ],
            403 => [
                'title' => 'Acceso Denegado',
                'description' => 'No tienes permisos para acceder a este recurso.',
                'icon' => '🔒'
            ],
            401 => [
                'title' => 'No Autorizado',
                'description' => 'Debes iniciar sesión para acceder a esta página.',
                'icon' => '👤'
            ],
        ];

        $errorInfo = $messages[$httpCode] ?? $messages[500];

        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($errorInfo['title']) ?> - MuseDock</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 20px;
                    padding: 60px 40px;
                    max-width: 600px;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .icon {
                    font-size: 80px;
                    margin-bottom: 20px;
                    animation: bounce 2s infinite;
                }
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-20px); }
                    60% { transform: translateY(-10px); }
                }
                h1 {
                    font-size: 32px;
                    color: #2d3748;
                    margin-bottom: 15px;
                    font-weight: 700;
                }
                .code {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 8px 20px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 600;
                    margin-bottom: 20px;
                }
                .description {
                    font-size: 18px;
                    color: #4a5568;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .actions {
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 30px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 15px;
                    transition: all 0.3s ease;
                }
                .btn-primary {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
                }
                .btn-secondary {
                    background: #edf2f7;
                    color: #2d3748;
                }
                .btn-secondary:hover {
                    background: #e2e8f0;
                }
                .footer {
                    margin-top: 40px;
                    font-size: 14px;
                    color: #a0aec0;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="icon"><?= $errorInfo['icon'] ?></div>
                <h1><?= htmlspecialchars($errorInfo['title']) ?></h1>
                <div class="code">Error <?= $httpCode ?></div>
                <div class="description">
                    <?= htmlspecialchars($errorInfo['description']) ?>
                </div>
                <div class="actions">
                    <a href="/" class="btn btn-primary">🏠 Volver al Inicio</a>
                    <a href="javascript:history.back()" class="btn btn-secondary">← Volver Atrás</a>
                </div>
                <div class="footer">
                    MuseDock CMS - Si el problema persiste, contacta con el administrador
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Obtiene contexto del archivo (líneas alrededor del error)
     *
     * @param string $file Ruta del archivo
     * @param int $errorLine Línea del error
     * @param int $contextLines Número de líneas de contexto (antes y después)
     * @return array
     */
    private static function getFileContext(string $file, int $errorLine, int $contextLines = 10): array
    {
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        $lines = file($file);
        $start = max(0, $errorLine - $contextLines - 1);
        $end = min(count($lines), $errorLine + $contextLines);

        $context = [];
        for ($i = $start; $i < $end; $i++) {
            $lineNumber = $i + 1;
            $context[$lineNumber] = $lines[$i];
        }

        return $context;
    }

    /**
     * Renderiza un error HTTP simple (404, 403, etc.)
     *
     * @param int $code Código HTTP
     * @param string|null $message Mensaje opcional
     */
    public static function http(int $code, ?string $message = null)
    {
        $exception = new \Exception($message ?? "HTTP Error $code");

        $titles = [
            404 => 'Página No Encontrada',
            403 => 'Acceso Denegado',
            401 => 'No Autorizado',
            500 => 'Error Interno',
        ];

        self::render($exception, $code, $titles[$code] ?? 'Error');
    }
}
