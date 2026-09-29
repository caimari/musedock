<?php

namespace Screenart\Musedock\Services\Mcp;

use Screenart\Musedock\Database;
use Screenart\Musedock\Mail\Mailer;

/**
 * Avisos por email de conexiones de IA (MCP) nuevas o actualizadas.
 *
 * Destinatarios: administradores root del sitio y la persona que autorizó.
 * El envío se hace al terminar la petición (tras enviar la respuesta), para
 * no retrasar el flujo OAuth ni el panel si el SMTP es lento.
 */
class McpNotifier
{
    /**
     * @param string      $event       'created' | 'updated'
     * @param string      $via         'oauth' | 'token'
     * @param array       $permissions lista de permisos concedidos
     * @param string|null $byEmail     email de quien autorizó
     */
    public static function connectionChanged(int $tenantId, string $event, string $name, string $via, array $permissions, ?string $byName, ?string $byEmail, ?string $clientHost = null): void
    {
        $tenant = $GLOBALS['tenant'] ?? [];
        $siteName = (string) ($tenant['name'] ?? $tenant['domain'] ?? 'tu sitio');
        $domain = (string) ($tenant['domain'] ?? ($_SERVER['HTTP_HOST'] ?? ''));
        $panelUrl = 'https://' . $domain . '/' . admin_path() . '/mcp';
        $ip = \Screenart\Musedock\Security\IPHelper::getRealIP();
        $when = date('d/m/Y H:i');

        $recipients = [];
        try {
            $stmt = Database::connect()->prepare("SELECT email FROM admins WHERE tenant_id = ? AND is_root_admin = 1 AND email IS NOT NULL");
            $stmt->execute([$tenantId]);
            $recipients = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            error_log('McpNotifier recipients: ' . $e->getMessage());
        }
        if ($byEmail) {
            $recipients[] = $byEmail;
        }
        $recipients = array_values(array_unique(array_filter(array_map('strtolower', $recipients), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        if (!$recipients) {
            return;
        }

        $levels = [];
        foreach (McpPermissions::summarize($permissions) as $section => $sectionLevels) {
            $levels[] = McpPermissions::sectionLabel($section) . ': ' . implode(', ', array_map(fn($l) => McpPermissions::LEVELS[$l] ?? $l, $sectionLevels));
        }

        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $verb = $event === 'updated' ? 'ha actualizado los permisos de' : 'ha conectado';
        $subject = ($event === 'updated' ? 'Permisos actualizados' : 'Nueva conexión de IA') . " en {$siteName}: {$name}";
        $viaLabel = $via === 'oauth' ? 'Inicio de sesión OAuth' . ($clientHost ? " (vuelve a {$clientHost})" : '') : 'Token de acceso creado en el panel';

        $rows = [
            'Aplicación' => $name,
            'Método'     => $viaLabel,
            'Autorizada por' => trim(($byName ?? '') . ($byEmail ? " <{$byEmail}>" : '')) ?: '—',
            'Fecha'      => $when,
            'IP'         => $ip,
        ];
        $rowsHtml = '';
        foreach ($rows as $label => $value) {
            $rowsHtml .= '<tr><td style="padding:4px 12px 4px 0;color:#6c757d;">' . $e($label) . '</td><td style="padding:4px 0;"><strong>' . $e($value) . '</strong></td></tr>';
        }
        $permsHtml = $levels ? '<ul style="margin:8px 0 0;padding-left:18px;">' . implode('', array_map(fn($l) => '<li>' . $e($l) . '</li>', $levels)) . '</ul>' : '';

        $html = '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:560px;margin:0 auto;color:#212529;">'
            . '<h2 style="font-size:18px;margin:0 0 12px;">' . $e($byName ?: 'Alguien') . ' ' . $e($verb) . ' un asistente de IA a ' . $e($siteName) . '</h2>'
            . '<table style="font-size:14px;border-collapse:collapse;">' . $rowsHtml . '</table>'
            . '<p style="font-size:14px;margin:16px 0 0;"><strong>Permisos concedidos:</strong></p>' . $permsHtml
            . '<p style="font-size:14px;margin:20px 0;">Si no reconoces esta conexión, revócala ahora:</p>'
            . '<p><a href="' . $e($panelUrl) . '" style="display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-size:14px;">Revisar conexiones de IA</a></p>'
            . '<p style="font-size:12px;color:#6c757d;margin-top:24px;">Aviso automático de seguridad de MuseDock · ' . $e($domain) . '</p>'
            . '</div>';

        $text = strip_tags(str_replace(['</tr>', '</li>', '</p>', '</h2>'], "\n", $html)) . "\nRevisar: {$panelUrl}\n";

        $send = function () use ($recipients, $subject, $html, $text, $tenantId) {
            foreach ($recipients as $to) {
                try {
                    Mailer::send($to, $subject, $html, $text, null, 'MuseDock Seguridad', $tenantId);
                } catch (\Throwable $ex) {
                    error_log('McpNotifier send: ' . $ex->getMessage());
                }
            }
        };

        // Tras enviar la respuesta al cliente (PHP-FPM); en CLI se envía directamente
        register_shutdown_function(function () use ($send) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $send();
        });
    }
}
