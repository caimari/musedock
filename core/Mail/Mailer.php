<?php

namespace Screenart\Musedock\Mail;

class Mailer
{
    /**
     * Envía un email usando mail() de PHP o SMTP según configuración en .env
     *
     * @param string $to Destinatario
     * @param string $subject Asunto
     * @param string $htmlBody Cuerpo del email en HTML
     * @param string $textBody Cuerpo del email en texto plano (opcional)
     * @param string $from Email remitente (opcional)
     * @param string $fromName Nombre del remitente (opcional)
     * @return bool
     */
    public static function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        ?string $from = null,
        ?string $fromName = null,
        ?int $tenantId = null
    ): bool {
        try {
            $tenantId = self::resolveTenantId($tenantId);
            $config = self::resolveMailConfig($tenantId);

            if (!($config['enabled'] ?? true)) {
                error_log("Mailer: envío bloqueado por configuración SMTP incompleta para tenant " . ($tenantId ?? 'global'));
                return false;
            }

            // Configuración por defecto (global o tenant)
            $from = $from ?? (string)($config['mail_from_address'] ?? '');
            $fromName = $fromName ?? (string)($config['mail_from_name'] ?? 'MuseDock CMS');

            // Si no hay texto plano, extraerlo del HTML
            if (empty($textBody)) {
                $textBody = strip_tags($htmlBody);
                $textBody = preg_replace('/\s+/', ' ', $textBody);
                $textBody = trim($textBody);
            }

            // Determinar método de envío (tenant/global)
            $driver = strtolower((string)($config['mail_driver'] ?? 'mail'));

            if ($driver === 'smtp') {
                return self::sendViaSMTP($to, $from, $fromName, $subject, $htmlBody, $textBody, $config);
            } else {
                return self::sendViaMail($to, $from, $fromName, $subject, $htmlBody, $textBody);
            }

        } catch (\Exception $e) {
            error_log("Excepción al enviar email: " . $e->getMessage());
            error_log("Traza: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Envía email usando la función mail() de PHP
     */
    private static function sendViaMail(
        string $to,
        string $from,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        // Boundary para separar partes del email
        $boundary = md5(uniqid(time()));

        // Headers
        $headers = [
            "From: {$fromName} <{$from}>",
            "Reply-To: {$from}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: MuseDock CMS Mailer"
        ];

        // Cuerpo del email (multipart: texto plano + HTML)
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        $body .= "--{$boundary}--";

        // Enviar email
        $sent = mail($to, $subject, $body, implode("\r\n", $headers));

        if ($sent) {
            error_log("Email enviado exitosamente (mail) a: {$to}");
        } else {
            error_log("Error al enviar email (mail) a: {$to}");
        }

        return $sent;
    }

    /**
     * Envía email usando SMTP configurado en .env
     */
    private static function sendViaSMTP(
        string $to,
        string $from,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody,
        array $config
    ): bool {
        // Obtener configuración SMTP resuelta (tenant o global)
        $host = (string)($config['smtp_host'] ?? '');
        $port = (int)($config['smtp_port'] ?? 587);
        $username = (string)($config['smtp_username'] ?? '');
        $password = (string)($config['smtp_password'] ?? '');
        $encryption = strtolower((string)($config['smtp_encryption'] ?? 'tls'));
        $ehloDomain = (string)($config['ehlo_domain'] ?? parse_url((string)(getenv('APP_URL') ?: 'https://musedock.net'), PHP_URL_HOST));

        if (empty($host) || empty($username) || empty($password)) {
            error_log("SMTP: Configuración incompleta. Verifica host/usuario/contraseña SMTP");
            return false;
        }

        error_log("SMTP: Intentando conectar a {$host}:{$port} con encriptación {$encryption}");

        try {
            // Crear conexión
            $socket = null;
            $errno = 0;
            $errstr = '';

            // Determinar si usar SSL desde el inicio
            $useSSL = ($encryption === 'ssl');
            $prefix = $useSSL ? 'ssl://' : '';

            // Conectar al servidor SMTP
            $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 30);

            if (!$socket) {
                error_log("SMTP: Error al conectar: [{$errno}] {$errstr}");
                return false;
            }

            stream_set_timeout($socket, 30);

            // Leer respuesta de bienvenida
            $response = self::readSMTPResponse($socket);
            error_log("SMTP: Respuesta inicial: {$response}");

            // EHLO
            self::sendSMTPCommand($socket, "EHLO " . $ehloDomain);
            $response = self::readSMTPResponse($socket);
            error_log("SMTP: Respuesta EHLO: " . substr($response, 0, 100));

            // Iniciar TLS si es necesario
            if ($encryption === 'tls' && !$useSSL) {
                self::sendSMTPCommand($socket, "STARTTLS");
                $response = self::readSMTPResponse($socket);
                error_log("SMTP: Respuesta STARTTLS: {$response}");

                if (strpos($response, '220') !== 0) {
                    error_log("SMTP: Error al iniciar TLS");
                    fclose($socket);
                    return false;
                }

                // Habilitar encriptación TLS
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    error_log("SMTP: Error al habilitar encriptación TLS");
                    fclose($socket);
                    return false;
                }

                // EHLO de nuevo después de TLS
                self::sendSMTPCommand($socket, "EHLO " . $ehloDomain);
                self::readSMTPResponse($socket);
            }

            // Autenticación LOGIN
            self::sendSMTPCommand($socket, "AUTH LOGIN");
            $response = self::readSMTPResponse($socket);

            if (strpos($response, '334') !== 0) {
                error_log("SMTP: Servidor no soporta AUTH LOGIN: {$response}");
                fclose($socket);
                return false;
            }

            // Enviar username (base64)
            fwrite($socket, base64_encode($username) . "\r\n");
            $response = self::readSMTPResponse($socket);

            // Enviar password (base64)
            fwrite($socket, base64_encode($password) . "\r\n");
            $response = self::readSMTPResponse($socket);

            if (strpos($response, '235') !== 0) {
                error_log("SMTP: Error de autenticación: {$response}");
                fclose($socket);
                return false;
            }

            error_log("SMTP: Autenticación exitosa");

            // MAIL FROM
            self::sendSMTPCommand($socket, "MAIL FROM:<{$from}>");
            $response = self::readSMTPResponse($socket);

            if (strpos($response, '250') !== 0) {
                error_log("SMTP: Error en MAIL FROM: {$response}");
                fclose($socket);
                return false;
            }

            // RCPT TO
            self::sendSMTPCommand($socket, "RCPT TO:<{$to}>");
            $response = self::readSMTPResponse($socket);

            if (strpos($response, '250') !== 0) {
                error_log("SMTP: Error en RCPT TO: {$response}");
                fclose($socket);
                return false;
            }

            // DATA
            self::sendSMTPCommand($socket, "DATA");
            $response = self::readSMTPResponse($socket);

            if (strpos($response, '354') !== 0) {
                error_log("SMTP: Error en DATA: {$response}");
                fclose($socket);
                return false;
            }

            // Construir mensaje completo
            $boundary = md5(uniqid(time()));
            $message = "From: {$fromName} <{$from}>\r\n";
            $message .= "To: <{$to}>\r\n";
            $message .= "Subject: {$subject}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $message .= "X-Mailer: MuseDock CMS SMTP Mailer\r\n";
            $message .= "\r\n";

            // Parte texto plano
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $textBody . "\r\n\r\n";

            // Parte HTML
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";

            $message .= "--{$boundary}--\r\n";
            $message .= ".\r\n";

            // Enviar mensaje
            fwrite($socket, $message);
            $response = self::readSMTPResponse($socket);

            if (strpos($response, '250') !== 0) {
                error_log("SMTP: Error al enviar mensaje: {$response}");
                fclose($socket);
                return false;
            }

            error_log("SMTP: Email enviado exitosamente a: {$to}");

            // QUIT
            self::sendSMTPCommand($socket, "QUIT");
            self::readSMTPResponse($socket);

            fclose($socket);
            return true;

        } catch (\Exception $e) {
            error_log("SMTP: Excepción: " . $e->getMessage());
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }

    /**
     * Resuelve tenant_id del contexto actual o del parámetro explícito.
     */
    private static function resolveTenantId(?int $tenantId): ?int
    {
        if ($tenantId !== null) {
            return $tenantId;
        }

        if (function_exists('tenant_id')) {
            $resolved = tenant_id();
            return $resolved !== null ? (int)$resolved : null;
        }

        return null;
    }

    /**
     * Determina si el sistema obliga SMTP propio por tenant.
     */
    private static function requiresTenantSmtp(): bool
    {
        $raw = null;
        if (function_exists('setting')) {
            $raw = setting('smtp_require_tenant_own', '0');
        } else {
            $raw = getenv('SMTP_REQUIRE_TENANT_OWN') ?: '0';
        }

        return in_array((string)$raw, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Carga configuración de correo (global o tenant) con política de herencia.
     */
    private static function resolveMailConfig(?int $tenantId): array
    {
        $appUrl = (string)(getenv('APP_URL') ?: 'https://musedock.net');
        $hostFromAppUrl = (string)(parse_url($appUrl, PHP_URL_HOST) ?: 'localhost');

        $global = [
            'enabled' => true,
            'source' => 'global',
            'mail_driver' => (string)(getenv('MAIL_DRIVER') ?: 'smtp'),
            'smtp_host' => (string)(getenv('SMTP_HOST') ?: ''),
            'smtp_port' => (int)(getenv('SMTP_PORT') ?: 587),
            'smtp_username' => (string)(getenv('SMTP_USERNAME') ?: ''),
            'smtp_password' => (string)(getenv('SMTP_PASSWORD') ?: ''),
            'smtp_encryption' => (string)(getenv('SMTP_ENCRYPTION') ?: 'tls'),
            'mail_from_address' => (string)(getenv('MAIL_FROM_ADDRESS') ?: ('noreply@' . $hostFromAppUrl)),
            'mail_from_name' => (string)(getenv('MAIL_FROM_NAME') ?: (getenv('APP_NAME') ?: 'MuseDock CMS')),
            'ehlo_domain' => $hostFromAppUrl,
        ];

        if ($tenantId === null) {
            // Validación mínima para smtp
            if (strtolower($global['mail_driver']) === 'smtp' &&
                (empty($global['smtp_host']) || empty($global['smtp_username']) || empty($global['smtp_password']))) {
                $global['enabled'] = false;
            }
            return $global;
        }

        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $keyCol = \Screenart\Musedock\Database::qi('key');
            $stmt = $pdo->prepare("
                SELECT {$keyCol} AS k, value
                FROM tenant_settings
                WHERE tenant_id = ?
                  AND {$keyCol} IN (
                    'smtp_use_global',
                    'mail_driver',
                    'smtp_host',
                    'smtp_port',
                    'smtp_username',
                    'smtp_password',
                    'smtp_encryption',
                    'mail_from_address',
                    'mail_from_name'
                  )
            ");
            $stmt->execute([$tenantId]);
            $tenantSettings = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $tenantSettings[(string)$row['k']] = (string)($row['value'] ?? '');
            }

            $forceOwn = self::requiresTenantSmtp();
            $useGlobal = (($tenantSettings['smtp_use_global'] ?? '1') === '1') && !$forceOwn;

            if ($useGlobal) {
                $global['source'] = 'global-fallback';
                return $global;
            }

            $tenantConfig = [
                'enabled' => true,
                'source' => $forceOwn ? 'tenant-required' : 'tenant',
                'mail_driver' => (string)($tenantSettings['mail_driver'] ?? $global['mail_driver']),
                'smtp_host' => (string)($tenantSettings['smtp_host'] ?? ''),
                'smtp_port' => (int)($tenantSettings['smtp_port'] ?? 587),
                'smtp_username' => (string)($tenantSettings['smtp_username'] ?? ''),
                'smtp_password' => (string)($tenantSettings['smtp_password'] ?? ''),
                'smtp_encryption' => (string)($tenantSettings['smtp_encryption'] ?? 'tls'),
                'mail_from_address' => (string)($tenantSettings['mail_from_address'] ?? ('noreply@' . $hostFromAppUrl)),
                'mail_from_name' => (string)($tenantSettings['mail_from_name'] ?? ($global['mail_from_name'] ?: 'MuseDock CMS')),
                'ehlo_domain' => $hostFromAppUrl,
            ];

            if (strtolower($tenantConfig['mail_driver']) === 'smtp' &&
                (empty($tenantConfig['smtp_host']) || empty($tenantConfig['smtp_username']) || empty($tenantConfig['smtp_password']))) {
                $tenantConfig['enabled'] = false;
            }

            // Si no es obligatorio tenant propio y está incompleto, fallback al global.
            if (!$forceOwn && !$tenantConfig['enabled']) {
                $global['source'] = 'global-fallback-incomplete-tenant';
                return $global;
            }

            return $tenantConfig;
        } catch (\Throwable $e) {
            error_log('Mailer::resolveMailConfig error: ' . $e->getMessage());
            return $global;
        }
    }

    /**
     * Envía un comando SMTP
     */
    private static function sendSMTPCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
        error_log("SMTP > {$command}");
    }

    /**
     * Lee respuesta del servidor SMTP
     */
    private static function readSMTPResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // Las respuestas multilinea tienen un guión en la 4ta posición
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        error_log("SMTP < " . trim($response));
        return trim($response);
    }

    /**
     * Genera el HTML para el email de recuperación de contraseña
     *
     * @param string $resetUrl URL de recuperación
     * @param string $userName Nombre del usuario
     * @param string $expiryTime Tiempo de expiración (ej: "1 hora")
     * @return string
     */
    public static function passwordResetTemplate(
        string $resetUrl,
        string $userName = 'Usuario',
        string $expiryTime = '1 hora'
    ): string {
        $appName = getenv('APP_NAME') ?: 'MuseDock CMS';
        $appUrl = getenv('APP_URL') ?: 'https://musedock.net';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperación de Contraseña</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; max-width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header con gradiente -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700;">
                                🔐 Recuperación de Contraseña
                            </h1>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
                                Hola <strong>{$userName}</strong>,
                            </p>

                            <p style="margin: 0 0 20px; font-size: 16px; line-height: 1.6; color: #374151;">
                                Has solicitado restablecer tu contraseña en <strong>{$appName}</strong>.
                                Haz clic en el botón de abajo para crear una nueva contraseña:
                            </p>

                            <!-- Botón -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{$resetUrl}"
                                           style="display: inline-block; padding: 16px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);">
                                            Restablecer Contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- URL alternativa -->
                            <p style="margin: 20px 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                Si el botón no funciona, copia y pega esta URL en tu navegador:
                            </p>
                            <p style="margin: 0 0 20px; font-size: 13px; line-height: 1.6; color: #6366f1; word-break: break-all; background-color: #f9fafb; padding: 12px; border-radius: 4px; border: 1px solid #e5e7eb;">
                                {$resetUrl}
                            </p>

                            <!-- Advertencia de expiración -->
                            <div style="margin: 30px 0; padding: 16px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
                                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #92400e;">
                                    <strong>⚠️ Importante:</strong> Este enlace expirará en <strong>{$expiryTime}</strong>.
                                </p>
                            </div>

                            <!-- Aviso de seguridad -->
                            <p style="margin: 20px 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                Si no solicitaste restablecer tu contraseña, puedes ignorar este correo con seguridad.
                                Tu contraseña no cambiará hasta que accedas al enlace y establezcas una nueva.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f9fafb; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                Este correo fue enviado por <strong>{$appName}</strong><br>
                                <a href="{$appUrl}" style="color: #6366f1; text-decoration: none;">{$appUrl}</a>
                            </p>
                            <p style="margin: 15px 0 0; font-size: 12px; line-height: 1.6; color: #9ca3af; text-align: center;">
                                © 2025 {$appName}. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el texto plano para el email de recuperación de contraseña
     *
     * @param string $resetUrl URL de recuperación
     * @param string $userName Nombre del usuario
     * @param string $expiryTime Tiempo de expiración
     * @return string
     */
    public static function passwordResetTextTemplate(
        string $resetUrl,
        string $userName = 'Usuario',
        string $expiryTime = '1 hora'
    ): string {
        $appName = getenv('APP_NAME') ?: 'MuseDock CMS';

        return <<<TEXT
Recuperación de Contraseña - {$appName}

Hola {$userName},

Has solicitado restablecer tu contraseña en {$appName}.

Para crear una nueva contraseña, accede al siguiente enlace:
{$resetUrl}

IMPORTANTE: Este enlace expirará en {$expiryTime}.

Si no solicitaste restablecer tu contraseña, puedes ignorar este correo con seguridad.

---
© 2025 {$appName}
TEXT;
    }
}
