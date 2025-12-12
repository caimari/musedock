<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\RateLimiter;

class SecurityController
{
    /**
     * Dashboard de Seguridad - Muestra intentos fallidos y estadísticas
     */
    public function auditLogs()
    {
        SessionSecurity::startSession();

        // Verificar que esté autenticado como superadmin
        if (!isset($_SESSION['super_admin'])) {
            header('Location: /musedock/login');
            exit;
        }

        $db = Database::connect();

        // Obtener rate limits activos (bloqueados o con intentos)
        $stmt = $db->prepare("
            SELECT
                identifier,
                attempts,
                expires_at,
                created_at,
                CASE
                    WHEN expires_at > NOW() THEN 'active'
                    ELSE 'expired'
                END as status
            FROM rate_limits
            WHERE attempts > 0
            ORDER BY expires_at DESC, attempts DESC
            LIMIT 100
        ");
        $stmt->execute();
        $rateLimits = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Procesar y agrupar datos
        $processedLimits = [];
        $emailStats = [];
        $ipStats = [];

        foreach ($rateLimits as $limit) {
            $parts = explode('|', $limit['identifier']);

            // Identificar tipo de registro
            if (str_starts_with($limit['identifier'], 'global_email:')) {
                $email = str_replace('global_email:', '', $limit['identifier']);
                $type = 'global';
                $ip = 'N/A';
            } else {
                $email = $parts[0] ?? 'unknown';
                $ip = end($parts);
                $type = 'specific';
            }

            // Calcular minutos restantes
            $expiresAt = strtotime($limit['expires_at']);
            $now = time();
            $minutesLeft = max(0, ceil(($expiresAt - $now) / 60));

            $processedLimits[] = [
                'identifier' => $limit['identifier'],
                'type' => $type,
                'email' => $email,
                'ip' => $ip,
                'attempts' => $limit['attempts'],
                'status' => $limit['status'],
                'expires_at' => $limit['expires_at'],
                'minutes_left' => $minutesLeft,
                'created_at' => $limit['created_at']
            ];

            // Estadísticas por email
            if (!isset($emailStats[$email])) {
                $emailStats[$email] = ['total_attempts' => 0, 'ips' => []];
            }
            $emailStats[$email]['total_attempts'] += $limit['attempts'];
            if ($ip !== 'N/A') {
                $emailStats[$email]['ips'][$ip] = true;
            }

            // Estadísticas por IP
            if ($ip !== 'N/A') {
                if (!isset($ipStats[$ip])) {
                    $ipStats[$ip] = ['total_attempts' => 0, 'emails' => []];
                }
                $ipStats[$ip]['total_attempts'] += $limit['attempts'];
                $ipStats[$ip]['emails'][$email] = true;
            }
        }

        // Ordenar estadísticas
        uasort($emailStats, fn($a, $b) => $b['total_attempts'] <=> $a['total_attempts']);
        uasort($ipStats, fn($a, $b) => $b['total_attempts'] <=> $a['total_attempts']);

        // Convertir sets a contadores
        foreach ($emailStats as $email => &$stats) {
            $stats['unique_ips'] = count($stats['ips']);
            unset($stats['ips']);
        }
        foreach ($ipStats as $ip => &$stats) {
            $stats['unique_emails'] = count($stats['emails']);
            unset($stats['emails']);
        }

        // Obtener IPs de confianza del superadmin actual
        $trustedIPs = [];
        if (isset($_SESSION['super_admin']['id'])) {
            $trustedIPs = RateLimiter::getTrustedIPs($_SESSION['super_admin']['id']);
        }

        return View::renderSuperadmin('security.audit-logs', [
            'title' => 'Security Dashboard',
            'rateLimits' => $processedLimits,
            'emailStats' => array_slice($emailStats, 0, 10, true), // Top 10
            'ipStats' => array_slice($ipStats, 0, 10, true), // Top 10
            'totalRecords' => count($processedLimits),
            'trustedIPs' => $trustedIPs,
            'currentIP' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    }

    /**
     * Añadir IP a whitelist
     */
    public function addTrustedIP()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin'])) {
            header('Location: /musedock/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /musedock/audit-logs');
            exit;
        }

        $ip = trim($_POST['ip'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $superAdminId = $_SESSION['super_admin']['id'];

        if (empty($ip)) {
            flash('error', 'La dirección IP es requerida');
            header('Location: /musedock/audit-logs');
            exit;
        }

        // Validar formato IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            flash('error', 'Dirección IP inválida');
            header('Location: /musedock/audit-logs');
            exit;
        }

        if (RateLimiter::addTrustedIP($superAdminId, $ip, $description)) {
            flash('success', "IP {$ip} añadida a la whitelist");
        } else {
            flash('error', 'Error al añadir la IP a la whitelist');
        }

        header('Location: /musedock/audit-logs');
        exit;
    }

    /**
     * Eliminar IP de whitelist
     */
    public function removeTrustedIP()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin'])) {
            header('Location: /musedock/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /musedock/audit-logs');
            exit;
        }

        $trustedIpId = intval($_POST['trusted_ip_id'] ?? 0);

        if ($trustedIpId <= 0) {
            flash('error', 'ID de IP inválido');
            header('Location: /musedock/audit-logs');
            exit;
        }

        if (RateLimiter::removeTrustedIP($trustedIpId)) {
            flash('success', 'IP eliminada de la whitelist');
        } else {
            flash('error', 'Error al eliminar la IP de la whitelist');
        }

        header('Location: /musedock/audit-logs');
        exit;
    }
}
