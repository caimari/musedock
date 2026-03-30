<?php
namespace Screenart\Musedock;

use Throwable; // Import Throwable para capturar Errors y Exceptions en PHP 7+

class Logger
{
    private static $logFilePath = null;
    private static $initialized = false;
    private static $defaultLevel = 'INFO'; // Nivel mínimo a loguear por defecto

    /**
     * Inicializa el Logger. Es OBLIGATORIO llamar a esto una vez.
     * Configura la ruta del archivo y verifica permisos.
     *
     * @param string|null $path Ruta absoluta al archivo de log. Si es null, intenta deducirla.
     * @param string $level Nivel mínimo de log a registrar (DEBUG, INFO, WARNING, ERROR).
     * @return bool True si la inicialización fue exitosa, False en caso contrario.
     */
    public static function init(string $path = null, string $level = 'DEBUG'): bool
    {
        if (self::$initialized) {
            return true; // Ya inicializado
        }

        if ($path === null) {
            // Ruta por defecto: un nivel arriba de 'core', luego a 'storage/logs'
            self::$logFilePath = dirname(__DIR__) . '/storage/logs/error.log';
        } else {
            self::$logFilePath = $path;
        }

        self::$defaultLevel = self::levelToInt(strtoupper($level)); // Guardar nivel mínimo

        // Verificar y crear directorio
        $logDir = dirname(self::$logFilePath);
        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0775, true)) { // Usar @ para suprimir error si ya existe (concurrencia)
                 error_log("ERROR Logger::init: No se pudo crear el directorio de logs: " . $logDir);
                 self::$logFilePath = null; // Deshabilitar logging
                 return false;
            }
        } elseif (!is_writable($logDir) || !is_writable(self::$logFilePath) && file_exists(self::$logFilePath)) {
             error_log("ERROR Logger::init: El directorio o archivo de logs no tiene permisos de escritura: " . self::$logFilePath);
             self::$logFilePath = null; // Deshabilitar logging
             return false;
        }

        self::$initialized = true;
        self::info("Logger inicializado.", ['path' => self::$logFilePath, 'minLevel' => $level]); // Loguear inicialización
        return true;
    }

    /**
     * Función central de logging. No usar directamente, usar error(), warning(), etc.
     *
     * @param string $message El mensaje.
     * @param string $level Nivel (DEBUG, INFO, WARNING, ERROR).
     * @param array $context Datos adicionales.
     */
    public static function log(string $message, string $level = 'INFO', array $context = [])
    {
        // No loguear si no está inicializado o si el nivel es menor al configurado
        $numericLevel = self::levelToInt($level);
        if (!self::$initialized || self::$logFilePath === null || $numericLevel < self::$defaultLevel) {
            return;
        }

        try {
            $level = strtoupper($level);
            $date = date('Y-m-d H:i:s'); // Formato de fecha
            $logMessage = "[$date] [$level] $message"; // Mensaje base

            // Añadir contexto si existe
            if (!empty($context)) {
                // Usar JSON_UNESCAPED_SLASHES y JSON_UNESCAPED_UNICODE para mejor legibilidad
                $contextString = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($contextString === false) { // Chequear error en json_encode
                     $contextString = "Error al codificar contexto: " . json_last_error_msg();
                }
                 $logMessage .= " | Context: " . $contextString;
            }

            $logMessage .= PHP_EOL; // Añadir nueva línea

            // Escribir en el archivo (append y lock)
            if (@file_put_contents(self::$logFilePath, $logMessage, FILE_APPEND | LOCK_EX) === false) {
                 // Fallback a error_log de PHP si falla la escritura directa
                 error_log("Fallback Logger: No se pudo escribir en " . self::$logFilePath . " - Mensaje: " . $logMessage);
            }
        } catch (\Exception $e) {
            // Error durante el propio logging
            error_log("ERROR CRITICO EN LOGGER: " . $e->getMessage() . " | Mensaje Original: " . ($message ?? 'N/A'));
        }
    }

    /**
     * Loguea una excepción/error de forma detallada.
     * Mantiene compatibilidad con tu firma original si solo pasas la excepción.
     *
     * @param Throwable $exception El objeto Throwable (Exception o Error).
     * @param string $level Nivel del log (usualmente ERROR o CRITICAL).
     * @param array $context Contexto adicional a añadir.
     */
    public static function exception(Throwable $exception, string $level = 'ERROR', array $context = [])
    {
        // Crear mensaje detallado
        $message = sprintf(
            "Excepción '%s': \"%s\" en %s:%d",
            get_class($exception), // Nombre de la clase de la excepción
            $exception->getMessage(), // Mensaje
            $exception->getFile(),    // Archivo
            $exception->getLine()     // Línea
        );

        // Añadir trace al contexto (puede ser muy largo)
        $logContext = array_merge($context, [
            'trace_hash' => md5($exception->getTraceAsString()), // Un hash para identificar trazas largas
            // Descomenta la siguiente línea si quieres la traza completa en el log (puede ser MUY largo)
            // 'trace' => $exception->getTraceAsString(),
        ]);

        // Usar el método log centralizado
        self::log($message, $level, $logContext);
    }

    // --- Métodos Helper por Nivel ---

    public static function debug(string $message, array $context = [])
    {
        self::log($message, 'DEBUG', $context);
    }

    public static function info(string $message, array $context = [])
    {
        self::log($message, 'INFO', $context);
    }

    public static function warning(string $message, array $context = [])
    {
        self::log($message, 'WARNING', $context);
    }

    public static function error(string $message, array $context = [])
    {
        self::log($message, 'ERROR', $context);
    }

    /**
     * Convierte el nivel de string a un entero para comparación.
     */
    private static function levelToInt(string $level): int
    {
        switch (strtoupper($level)) {
            case 'DEBUG': return 100;
            case 'INFO': return 200;
            case 'WARNING': return 300;
            case 'ERROR': return 400;
            default: return 200; // Default a INFO si es desconocido
        }
    }
}