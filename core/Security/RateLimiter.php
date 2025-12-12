<?php

namespace Screenart\Musedock\Security;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Sistema de Rate Limiting para prevenir ataques de fuerza bruta
 */
class RateLimiter
{
    /**
     * Verifica si se excedió el límite de intentos
     *
     * @param string $identifier Identificador único (email, IP, etc)
     * @param int|null $maxAttempts Número máximo de intentos
     * @param int|null $decayMinutes Minutos hasta que expiren los intentos
     * @return bool True si puede continuar, False si está bloqueado
     */
    public static function check($identifier, $maxAttempts = null, $decayMinutes = null)
    {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $maxAttempts = $maxAttempts ?? ($config['security']['rate_limit_attempts'] ?? 5);
            $decayMinutes = $decayMinutes ?? ($config['security']['rate_limit_decay'] ?? 15);

            $db = Database::connect();
            $driver = Database::getDriver();

            // Limpiar intentos expirados
            $db->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()")->execute();

            // Verificar intentos actuales
            $minutesLeft = $driver->dateDiff('NOW()', 'expires_at', 'MINUTE');
            $stmt = $db->prepare("
                SELECT attempts, expires_at, {$minutesLeft} as minutes_left
                FROM rate_limits
                WHERE identifier = :id AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute(['id' => $identifier]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && $result['attempts'] >= $maxAttempts) {
                Logger::log("Rate limit exceeded for: {$identifier}. Attempts: {$result['attempts']}/{$maxAttempts}. Time left: {$result['minutes_left']} minutes", 'WARNING');
                return false; // Bloqueado
            }

            return true; // Puede continuar
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::check']);
            // En caso de error, permitir el acceso (fail open)
            return true;
        }
    }

    /**
     * Incrementa el contador de intentos
     *
     * @param string $identifier Identificador único
     * @param int|null $decayMinutes Minutos hasta que expiren los intentos
     * @return int Número actual de intentos
     */
    public static function increment($identifier, $decayMinutes = null)
    {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $decayMinutes = $decayMinutes ?? ($config['security']['rate_limit_decay'] ?? 15);

            $db = Database::connect();
            $driver = Database::getDriver();

            // Calcular fecha de expiración
            $expiresAt = $driver->dateAdd('NOW()', $decayMinutes, 'MINUTE');

            // Generar query de upsert según el driver
            $driverName = $driver->getDriverName();

            if ($driverName === 'pgsql') {
                // PostgreSQL: ON CONFLICT
                $stmt = $db->prepare("
                    INSERT INTO rate_limits (identifier, attempts, expires_at, created_at)
                    VALUES (:id, 1, {$expiresAt}, NOW())
                    ON CONFLICT (identifier) DO UPDATE SET
                        attempts = rate_limits.attempts + 1,
                        expires_at = {$expiresAt}
                ");
            } else {
                // MySQL: ON DUPLICATE KEY UPDATE
                $stmt = $db->prepare("
                    INSERT INTO rate_limits (identifier, attempts, expires_at, created_at)
                    VALUES (:id, 1, {$expiresAt}, NOW())
                    ON DUPLICATE KEY UPDATE
                        attempts = attempts + 1,
                        expires_at = {$expiresAt}
                ");
            }

            $stmt->execute(['id' => $identifier]);

            // Obtener número actual de intentos
            $stmt = $db->prepare("SELECT attempts FROM rate_limits WHERE identifier = :id");
            $stmt->execute(['id' => $identifier]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ? (int)$result['attempts'] : 1;
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::increment']);
            return 0;
        }
    }

    /**
     * Limpia los intentos de un identificador
     *
     * @param string $identifier Identificador único
     * @return bool
     */
    public static function clear($identifier)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM rate_limits WHERE identifier = :id");
            $stmt->execute(['id' => $identifier]);

            Logger::log("Rate limit cleared for: {$identifier}", 'INFO');
            return true;
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::clear']);
            return false;
        }
    }

    /**
     * Obtiene el número de intentos restantes
     *
     * @param string $identifier Identificador único
     * @param int|null $maxAttempts Número máximo de intentos
     * @return int Intentos restantes
     */
    public static function remaining($identifier, $maxAttempts = null)
    {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $maxAttempts = $maxAttempts ?? ($config['security']['rate_limit_attempts'] ?? 5);

            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT attempts FROM rate_limits
                WHERE identifier = :id AND expires_at > NOW()
            ");
            $stmt->execute(['id' => $identifier]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $currentAttempts = $result ? (int)$result['attempts'] : 0;
            return max(0, $maxAttempts - $currentAttempts);
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::remaining']);
            return 0;
        }
    }

    /**
     * Obtiene información sobre el límite de tasa
     *
     * @param string $identifier Identificador único
     * @return array|null ['attempts' => int, 'expires_at' => string, 'minutes_left' => int]
     */
    public static function info($identifier)
    {
        try {
            $db = Database::connect();
            $driver = Database::getDriver();

            $minutesLeft = $driver->dateDiff('NOW()', 'expires_at', 'MINUTE');
            $stmt = $db->prepare("
                SELECT
                    attempts,
                    expires_at,
                    {$minutesLeft} as minutes_left
                FROM rate_limits
                WHERE identifier = :id AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute(['id' => $identifier]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::info']);
            return null;
        }
    }

    /**
     * Limpia todos los intentos expirados (útil para tareas programadas)
     *
     * @return int Número de registros eliminados
     */
    public static function cleanup()
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()");
            $stmt->execute();
            $deleted = $stmt->rowCount();

            if ($deleted > 0) {
                Logger::log("Rate limits cleanup: {$deleted} expired records removed", 'INFO');
            }

            return $deleted;
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::cleanup']);
            return 0;
        }
    }

    /**
     * Verifica si un email está bajo ataque (intentos desde múltiples IPs)
     *
     * @param string $email Email a verificar
     * @param int $minIPs Número mínimo de IPs diferentes para considerar ataque (default: 3)
     * @return bool True si está bajo ataque
     */
    public static function isUnderAttack($email, $minIPs = 3)
    {
        try {
            $db = Database::connect();

            // Buscar todos los intentos activos que contengan este email
            $stmt = $db->prepare("
                SELECT identifier
                FROM rate_limits
                WHERE identifier LIKE :email_pattern
                AND expires_at > NOW()
                AND attempts > 0
            ");
            $stmt->execute(['email_pattern' => $email . '|%']);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Contar IPs diferentes
            $uniqueIPs = [];
            foreach ($results as $row) {
                // Extraer IP del identifier (formato: email|tenant|IP o email|IP)
                $parts = explode('|', $row['identifier']);
                $ip = end($parts); // Última parte siempre es la IP
                $uniqueIPs[$ip] = true;
            }

            $ipCount = count($uniqueIPs);

            if ($ipCount >= $minIPs) {
                Logger::log("Email under attack detected: {$email} ({$ipCount} different IPs)", 'WARNING');
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::isUnderAttack']);
            return false;
        }
    }

    /**
     * Verifica usando doble bloqueo: por identificador específico Y por email global
     * Evita ataques distribuidos desde múltiples IPs
     *
     * @param string $identifier Identificador específico (email|IP o email|tenant|IP)
     * @param string $email Email a verificar globalmente
     * @param int|null $maxAttempts Intentos máximos
     * @param int|null $decayMinutes Minutos de decay
     * @param int $globalMultiplier Multiplicador de intentos globales (default: 3x)
     * @return array ['allowed' => bool, 'reason' => string, 'info' => array]
     */
    public static function checkDual($identifier, $email, $maxAttempts = null, $decayMinutes = null, $globalMultiplier = 3)
    {
        $config = require __DIR__ . '/../../config/config.php';
        $maxAttempts = $maxAttempts ?? ($config['security']['rate_limit_attempts'] ?? 5);
        $globalMaxAttempts = $maxAttempts * $globalMultiplier; // 15 intentos globales por defecto

        // 1. Verificar bloqueo específico (email|IP)
        if (!self::check($identifier, $maxAttempts, $decayMinutes)) {
            $info = self::info($identifier);
            return [
                'allowed' => false,
                'reason' => 'specific_block',
                'info' => $info,
                'message' => __('auth.too_many_attempts', ['minutes' => $info['minutes_left'] ?? 15])
            ];
        }

        // 2. Verificar bloqueo global por email (suma de todos los intentos)
        $globalIdentifier = "global_email:{$email}";
        if (!self::check($globalIdentifier, $globalMaxAttempts, $decayMinutes)) {
            $info = self::info($globalIdentifier);

            // Log de posible ataque
            Logger::log("Global rate limit exceeded for email: {$email}. Possible distributed attack.", 'SECURITY');

            return [
                'allowed' => false,
                'reason' => 'global_block',
                'info' => $info,
                'message' => __('auth.account_temporarily_locked', ['minutes' => $info['minutes_left'] ?? 15])
            ];
        }

        // 3. Detectar ataques distribuidos
        if (self::isUnderAttack($email, 3)) {
            return [
                'allowed' => true, // Permitir pero alertar
                'reason' => 'under_attack',
                'info' => ['warning' => 'Account under distributed attack'],
                'message' => null
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'ok',
            'info' => null,
            'message' => null
        ];
    }

    /**
     * Incrementa contador usando doble tracking (específico + global)
     *
     * @param string $identifier Identificador específico
     * @param string $email Email para tracking global
     * @param int|null $decayMinutes Minutos de decay
     * @return array ['specific_attempts' => int, 'global_attempts' => int]
     */
    public static function incrementDual($identifier, $email, $decayMinutes = null)
    {
        $specificAttempts = self::increment($identifier, $decayMinutes);
        $globalIdentifier = "global_email:{$email}";
        $globalAttempts = self::increment($globalIdentifier, $decayMinutes);

        return [
            'specific_attempts' => $specificAttempts,
            'global_attempts' => $globalAttempts
        ];
    }

    /**
     * Limpia ambos contadores (específico + global)
     *
     * @param string $identifier Identificador específico
     * @param string $email Email para tracking global
     * @return bool
     */
    public static function clearDual($identifier, $email)
    {
        $result1 = self::clear($identifier);
        $globalIdentifier = "global_email:{$email}";
        $result2 = self::clear($globalIdentifier);

        return $result1 && $result2;
    }
}
