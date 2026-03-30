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

        // 0. Verificar si la IP está en la whitelist (bypass rate limiting)
        $parts = explode('|', $identifier);
        $ip = end($parts); // Última parte es siempre la IP
        if (self::isIPWhitelisted($email, $ip)) {
            Logger::log("Whitelisted IP bypassed rate limiting: {$ip} for {$email}", 'INFO');
            return [
                'allowed' => true,
                'reason' => 'whitelisted',
                'info' => ['message' => 'IP is whitelisted'],
                'message' => null
            ];
        }

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

        // Enviar notificación por email si se detecta posible ataque
        if ($globalAttempts >= 10) {
            self::sendAttackNotification($email, $globalAttempts, $identifier);
        }

        return [
            'specific_attempts' => $specificAttempts,
            'global_attempts' => $globalAttempts
        ];
    }

    /**
     * Envía notificación por email cuando se detecta un posible ataque
     *
     * @param string $targetEmail Email que está siendo atacado
     * @param int $attempts Número de intentos globales
     * @param string $lastIdentifier Último identificador (contiene IP)
     * @return bool
     */
    private static function sendAttackNotification($targetEmail, $attempts, $lastIdentifier)
    {
        try {
            // Verificar si ya se envió notificación recientemente (evitar spam)
            $cacheKey = "attack_notification_sent:{$targetEmail}";
            $lastSent = apcu_exists($cacheKey) ? apcu_fetch($cacheKey) : null;

            if ($lastSent && (time() - $lastSent) < 1800) { // 30 minutos
                return false; // Ya se envió notificación recientemente
            }

            // Extraer IP del identifier
            $parts = explode('|', $lastIdentifier);
            $ip = end($parts);

            // Obtener información de IPs únicas
            $uniqueIPs = self::getUniqueIPsForEmail($targetEmail);
            $ipCount = count($uniqueIPs);

            // Buscar al superadmin para notificar
            $db = Database::connect();
            $stmt = $db->prepare("SELECT email, name FROM super_admins WHERE is_root = 1 LIMIT 1");
            $stmt->execute();
            $superadmin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$superadmin) {
                return false;
            }

            // Preparar email HTML
            $subject = "⚠️ Alerta de Seguridad - Posible Ataque de Fuerza Bruta";
            $htmlMessage = "
                <!DOCTYPE html>
                <html lang='es'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Alerta de Seguridad</title>
                </head>
                <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Arial, sans-serif; background-color: #f3f4f6;'>
                    <table role='presentation' style='width: 100%; border-collapse: collapse; background-color: #f3f4f6;'>
                        <tr>
                            <td align='center' style='padding: 40px 0;'>
                                <table role='presentation' style='width: 600px; max-width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                                    <!-- Header -->
                                    <tr>
                                        <td style='padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); border-radius: 8px 8px 0 0;'>
                                            <h1 style='margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;'>
                                                ⚠️ Alerta de Seguridad
                                            </h1>
                                        </td>
                                    </tr>

                                    <!-- Contenido -->
                                    <tr>
                                        <td style='padding: 40px;'>
                                            <p style='margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;'>
                                                Se ha detectado un <strong>posible ataque de fuerza bruta</strong> contra la cuenta:
                                            </p>

                                            <div style='background: #fee2e2; padding: 20px; border-left: 4px solid #dc2626; margin: 20px 0; border-radius: 4px;'>
                                                <p style='margin: 0 0 10px; font-size: 15px; color: #991b1b;'><strong>Email Objetivo:</strong> {$targetEmail}</p>
                                                <p style='margin: 0 0 10px; font-size: 15px; color: #991b1b;'><strong>Intentos Fallidos:</strong> {$attempts}</p>
                                                <p style='margin: 0 0 10px; font-size: 15px; color: #991b1b;'><strong>IPs Diferentes:</strong> {$ipCount}</p>
                                                <p style='margin: 0 0 10px; font-size: 15px; color: #991b1b;'><strong>Última IP:</strong> {$ip}</p>
                                                <p style='margin: 0; font-size: 15px; color: #991b1b;'><strong>Estado:</strong> Cuenta bloqueada temporalmente</p>
                                            </div>

                                            <h3 style='color: #374151; font-size: 18px; margin: 30px 0 15px;'>IPs Detectadas:</h3>
                                            <ul style='margin: 0 0 30px; padding-left: 25px; color: #6b7280;'>
                                                " . implode('', array_map(fn($ip) => "<li style='margin: 5px 0;'>{$ip}</li>", $uniqueIPs)) . "
                                            </ul>

                                            <h3 style='color: #374151; font-size: 18px; margin: 30px 0 15px;'>Acciones Recomendadas:</h3>
                                            <ol style='margin: 0; padding-left: 25px; color: #6b7280; line-height: 1.8;'>
                                                <li>Verifica que el usuario legítimo no esté teniendo problemas con su contraseña</li>
                                                <li>Considera contactar al usuario para cambiar su contraseña</li>
                                                <li>Revisa los logs de seguridad en <strong>/musedock/audit-logs</strong></li>
                                                <li>Si es un ataque confirmado, considera bloquear las IPs en el firewall</li>
                                            </ol>
                                        </td>
                                    </tr>

                                    <!-- Footer -->
                                    <tr>
                                        <td style='padding: 30px 40px; background-color: #f9fafb; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;'>
                                            <p style='margin: 0; font-size: 13px; line-height: 1.6; color: #9ca3af; text-align: center;'>
                                                Este email fue generado automáticamente por el sistema de seguridad de <strong>MuseDock</strong><br>
                                                Fecha: " . date('Y-m-d H:i:s') . "
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </body>
                </html>
            ";

            // Preparar texto plano alternativo
            $textMessage = "ALERTA DE SEGURIDAD\n\n";
            $textMessage .= "Se ha detectado un posible ataque de fuerza bruta:\n\n";
            $textMessage .= "Email Objetivo: {$targetEmail}\n";
            $textMessage .= "Intentos Fallidos: {$attempts}\n";
            $textMessage .= "IPs Diferentes: {$ipCount}\n";
            $textMessage .= "Última IP: {$ip}\n";
            $textMessage .= "Estado: Cuenta bloqueada temporalmente\n\n";
            $textMessage .= "IPs Detectadas:\n";
            foreach ($uniqueIPs as $attackIP) {
                $textMessage .= "  - {$attackIP}\n";
            }
            $textMessage .= "\nAcciones Recomendadas:\n";
            $textMessage .= "1. Verifica que el usuario legítimo no esté teniendo problemas con su contraseña\n";
            $textMessage .= "2. Considera contactar al usuario para cambiar su contraseña\n";
            $textMessage .= "3. Revisa los logs de seguridad en /musedock/audit-logs\n";
            $textMessage .= "4. Si es un ataque confirmado, considera bloquear las IPs en el firewall\n\n";
            $textMessage .= "---\n";
            $textMessage .= "Este email fue generado automáticamente por el sistema de seguridad de MuseDock\n";
            $textMessage .= "Fecha: " . date('Y-m-d H:i:s');

            // Enviar email usando Mailer con configuración SMTP desde .env
            $sent = \Screenart\Musedock\Mail\Mailer::send(
                $superadmin['email'],
                $subject,
                $htmlMessage,
                $textMessage,
                null, // from (usará .env)
                'MuseDock Security' // fromName
            );

            if ($sent) {
                // Guardar en cache que se envió notificación
                apcu_store($cacheKey, time(), 1800); // 30 minutos
                Logger::log("Security notification sent to {$superadmin['email']} for attack on {$targetEmail}", 'SECURITY');
            }

            return $sent;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::sendAttackNotification']);
            return false;
        }
    }

    /**
     * Obtiene lista de IPs únicas que han intentado acceder a un email
     *
     * @param string $email Email a verificar
     * @return array Lista de IPs
     */
    private static function getUniqueIPsForEmail($email)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT identifier
                FROM rate_limits
                WHERE identifier LIKE :email_pattern
                AND expires_at > NOW()
                AND attempts > 0
            ");
            $stmt->execute(['email_pattern' => $email . '|%']);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $uniqueIPs = [];
            foreach ($results as $row) {
                $parts = explode('|', $row['identifier']);
                $ip = end($parts);
                $uniqueIPs[$ip] = $ip;
            }

            return array_values($uniqueIPs);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::getUniqueIPsForEmail']);
            return [];
        }
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

    /**
     * Verifica si una IP está en la whitelist de un superadmin
     *
     * @param string $email Email del superadmin
     * @param string $ip Dirección IP a verificar
     * @return bool True si la IP está en la whitelist
     */
    public static function isIPWhitelisted($email, $ip)
    {
        try {
            $db = Database::connect();

            // Primero obtener el ID del superadmin
            $stmt = $db->prepare("SELECT id FROM super_admins WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $superadmin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$superadmin) {
                return false;
            }

            // Verificar si la IP está en la whitelist
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM superadmin_trusted_ips
                WHERE super_admin_id = :super_admin_id
                AND ip_address = :ip
            ");
            $stmt->execute([
                'super_admin_id' => $superadmin['id'],
                'ip' => $ip
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['count'] > 0;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::isIPWhitelisted']);
            return false;
        }
    }

    /**
     * Añade una IP a la whitelist de un superadmin
     *
     * @param int $superAdminId ID del superadmin
     * @param string $ip Dirección IP
     * @param string|null $description Descripción opcional
     * @return bool
     */
    public static function addTrustedIP($superAdminId, $ip, $description = null)
    {
        try {
            $db = Database::connect();
            $driver = Database::getDriver();

            if ($driver === 'mysql') {
                $stmt = $db->prepare("
                    INSERT INTO superadmin_trusted_ips (super_admin_id, ip_address, description)
                    VALUES (:super_admin_id, :ip, :description)
                    ON DUPLICATE KEY UPDATE
                        description = VALUES(description),
                        updated_at = CURRENT_TIMESTAMP
                ");
            } else { // PostgreSQL
                $stmt = $db->prepare("
                    INSERT INTO superadmin_trusted_ips (super_admin_id, ip_address, description)
                    VALUES (:super_admin_id, :ip, :description)
                    ON CONFLICT (super_admin_id, ip_address)
                    DO UPDATE SET
                        description = EXCLUDED.description,
                        updated_at = CURRENT_TIMESTAMP
                ");
            }

            $stmt->execute([
                'super_admin_id' => $superAdminId,
                'ip' => $ip,
                'description' => $description
            ]);

            Logger::log("Trusted IP added: {$ip} for superadmin ID {$superAdminId}", 'SECURITY');
            return true;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::addTrustedIP']);
            return false;
        }
    }

    /**
     * Elimina una IP de la whitelist
     *
     * @param int $trustedIpId ID del registro de IP confiable
     * @return bool
     */
    public static function removeTrustedIP($trustedIpId)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM superadmin_trusted_ips WHERE id = :id");
            $stmt->execute(['id' => $trustedIpId]);

            Logger::log("Trusted IP removed: ID {$trustedIpId}", 'SECURITY');
            return true;

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::removeTrustedIP']);
            return false;
        }
    }

    /**
     * Obtiene todas las IPs confiables de un superadmin
     *
     * @param int $superAdminId ID del superadmin
     * @return array
     */
    public static function getTrustedIPs($superAdminId)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT id, ip_address, description, created_at, updated_at
                FROM superadmin_trusted_ips
                WHERE super_admin_id = :super_admin_id
                ORDER BY created_at DESC
            ");
            $stmt->execute(['super_admin_id' => $superAdminId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            Logger::exception($e, 'ERROR', ['source' => 'RateLimiter::getTrustedIPs']);
            return [];
        }
    }
}
