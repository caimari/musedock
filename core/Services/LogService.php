<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Env;
use Exception;

/**
 * LogService - Sistema de logs con niveles según entorno
 *
 * En desarrollo (APP_DEBUG=true): Registra todo (debug, info, warning, error, critical)
 * En producción (APP_DEBUG=false): Solo registra warning, error, critical
 */
class LogService
{
    private const LOG_DIR = APP_ROOT . '/storage/logs';
    private const MAX_LOG_SIZE = 10 * 1024 * 1024; // 10MB
    private const LOG_FILE = 'musedock.log';

    // Niveles de log (compatible con PSR-3)
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';
    const CRITICAL = 'critical';

    private static array $levels = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4,
    ];

    /**
     * Verifica si debe registrar según el nivel y el entorno
     */
    private static function shouldLog(string $level): bool
    {
        $isDebug = Env::get('APP_DEBUG', false);
        $isDevelopment = Env::get('APP_ENV', 'production') === 'development';

        // En desarrollo, registrar todo
        if ($isDebug || $isDevelopment) {
            return true;
        }

        // En producción, solo warning, error y critical
        $productionLevels = [self::WARNING, self::ERROR, self::CRITICAL];
        return in_array($level, $productionLevels);
    }

    /**
     * Registra un mensaje de debug
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Registra un mensaje informativo
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * Registra una advertencia
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * Registra un error
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * Registra un error crítico
     */
    public static function critical(string $message, array $context = []): void
    {
        self::log(self::CRITICAL, $message, $context);
    }

    /**
     * Registra un mensaje con nivel específico
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        // Verificar si debe registrar según el entorno
        if (!self::shouldLog($level)) {
            return;
        }

        try {
            // Asegurar que el directorio existe
            self::ensureLogDirectory();

            $logFile = self::LOG_DIR . '/' . self::LOG_FILE;

            // Rotar log si es muy grande
            self::rotateLogIfNeeded($logFile);

            // Formatear el mensaje
            $formattedMessage = self::formatLogMessage($level, $message, $context);

            // Escribir al archivo
            file_put_contents($logFile, $formattedMessage . PHP_EOL, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            // Si falla el log, usar error_log nativo de PHP
            error_log("LogService failed: " . $e->getMessage());
            error_log("Original message: [{$level}] {$message}");
        }
    }

    /**
     * Formatea el mensaje de log
     */
    private static function formatLogMessage(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);

        // Obtener información del usuario si está disponible
        $user = self::getCurrentUser();
        $userInfo = $user ? " [User: {$user}]" : '';

        // Obtener IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

        // Formatear contexto si existe
        $contextStr = '';
        if (!empty($context)) {
            // Filtrar datos sensibles
            $context = self::filterSensitiveData($context);
            $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        return "[{$timestamp}] [{$levelUpper}] [{$ip}]{$userInfo} {$message}{$contextStr}";
    }

    /**
     * Filtra datos sensibles del contexto (passwords, tokens, etc.)
     */
    private static function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'private_key', 'access_token'];

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = self::filterSensitiveData($value);
            } elseif (is_string($key)) {
                foreach ($sensitiveKeys as $sensitiveKey) {
                    if (stripos($key, $sensitiveKey) !== false) {
                        $value = '***FILTERED***';
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Obtiene el usuario actual
     */
    private static function getCurrentUser(): ?string
    {
        if (isset($_SESSION['super_admin']['email'])) {
            return 'superadmin:' . $_SESSION['super_admin']['email'];
        }

        if (isset($_SESSION['admin']['email'])) {
            return 'admin:' . $_SESSION['admin']['email'];
        }

        if (isset($_SESSION['user']['email'])) {
            return 'user:' . $_SESSION['user']['email'];
        }

        return null;
    }

    /**
     * Asegura que el directorio de logs existe
     */
    private static function ensureLogDirectory(): void
    {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }

        // Crear .htaccess para proteger los logs
        $htaccessFile = self::LOG_DIR . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all");
        }
    }

    /**
     * Rota el archivo de log si excede el tamaño máximo
     */
    private static function rotateLogIfNeeded(string $logFile): void
    {
        if (!file_exists($logFile)) {
            return;
        }

        $fileSize = filesize($logFile);

        if ($fileSize > self::MAX_LOG_SIZE) {
            $timestamp = date('Y-m-d_H-i-s');
            $rotatedFile = self::LOG_DIR . '/musedock-' . $timestamp . '.log';
            rename($logFile, $rotatedFile);

            // Comprimir el archivo rotado si está disponible gzip
            if (function_exists('gzopen')) {
                self::compressLog($rotatedFile);
            }

            // Limpiar logs antiguos (mantener solo los últimos 10)
            self::cleanOldLogs();
        }
    }

    /**
     * Comprime un archivo de log
     */
    private static function compressLog(string $file): void
    {
        try {
            $gzFile = $file . '.gz';
            $fp = fopen($file, 'rb');
            $gz = gzopen($gzFile, 'wb9');

            while (!feof($fp)) {
                gzwrite($gz, fread($fp, 1024 * 512));
            }

            fclose($fp);
            gzclose($gz);

            // Eliminar el archivo original
            unlink($file);
        } catch (Exception $e) {
            error_log("Failed to compress log: " . $e->getMessage());
        }
    }

    /**
     * Limpia logs antiguos
     */
    private static function cleanOldLogs(): void
    {
        try {
            $files = glob(self::LOG_DIR . '/musedock-*.log*');

            if (count($files) > 10) {
                // Ordenar por fecha (más antiguos primero)
                usort($files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });

                // Eliminar los más antiguos
                $toDelete = array_slice($files, 0, count($files) - 10);
                foreach ($toDelete as $file) {
                    unlink($file);
                }
            }
        } catch (Exception $e) {
            error_log("Failed to clean old logs: " . $e->getMessage());
        }
    }

    /**
     * Obtiene las últimas líneas del log
     */
    public static function getRecentLogs(int $lines = 100): array
    {
        $logFile = self::LOG_DIR . '/' . self::LOG_FILE;

        if (!file_exists($logFile)) {
            return [];
        }

        try {
            $logs = [];
            $file = new \SplFileObject($logFile, 'r');
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key();

            $startLine = max(0, $totalLines - $lines);
            $file->seek($startLine);

            while (!$file->eof()) {
                $line = trim($file->current());
                if (!empty($line)) {
                    $logs[] = self::parseLogLine($line);
                }
                $file->next();
            }

            return array_reverse($logs);

        } catch (Exception $e) {
            error_log("Failed to read logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parsea una línea de log
     */
    private static function parseLogLine(string $line): array
    {
        // Formato: [2025-11-11 20:00:00] [ERROR] [IP] [User: ...] Message Context
        $pattern = '/^\[([^\]]+)\]\s+\[([^\]]+)\]\s+\[([^\]]+)\](?:\s+\[User:\s+([^\]]+)\])?\s+(.+)$/';

        if (preg_match($pattern, $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => strtolower($matches[2]),
                'ip' => $matches[3],
                'user' => $matches[4] ?? null,
                'message' => $matches[5] ?? $line,
                'raw' => $line,
            ];
        }

        // Si no coincide con el patrón, devolver como raw
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'info',
            'ip' => '-',
            'user' => null,
            'message' => $line,
            'raw' => $line,
        ];
    }

    /**
     * Busca en los logs
     */
    public static function searchLogs(string $query, int $maxResults = 100): array
    {
        $logFile = self::LOG_DIR . '/' . self::LOG_FILE;

        if (!file_exists($logFile)) {
            return [];
        }

        try {
            $results = [];
            $file = new \SplFileObject($logFile, 'r');

            while (!$file->eof() && count($results) < $maxResults) {
                $line = trim($file->current());

                if (!empty($line) && stripos($line, $query) !== false) {
                    $results[] = self::parseLogLine($line);
                }

                $file->next();
            }

            return array_reverse($results);

        } catch (Exception $e) {
            error_log("Failed to search logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpia todos los logs
     */
    public static function clearLogs(): bool
    {
        try {
            $logFile = self::LOG_DIR . '/' . self::LOG_FILE;

            if (file_exists($logFile)) {
                unlink($logFile);
            }

            // Limpiar logs rotados
            $files = glob(self::LOG_DIR . '/musedock-*.log*');
            foreach ($files as $file) {
                unlink($file);
            }

            self::info('Logs cleared by user');

            return true;

        } catch (Exception $e) {
            error_log("Failed to clear logs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene estadísticas de logs
     */
    public static function getLogStats(): array
    {
        $logFile = self::LOG_DIR . '/' . self::LOG_FILE;

        if (!file_exists($logFile)) {
            return [
                'total_lines' => 0,
                'file_size' => 0,
                'file_size_formatted' => '0 B',
                'last_modified' => null,
            ];
        }

        try {
            $file = new \SplFileObject($logFile, 'r');
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key();

            $fileSize = filesize($logFile);

            return [
                'total_lines' => $totalLines,
                'file_size' => $fileSize,
                'file_size_formatted' => self::formatBytes($fileSize),
                'last_modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            ];

        } catch (Exception $e) {
            error_log("Failed to get log stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Formatea bytes a formato legible
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
