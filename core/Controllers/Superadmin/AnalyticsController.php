<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

class AnalyticsController
{
    /**
     * Verificar permiso
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', 'No tienes permisos para acceder a esta sección.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    /**
     * Dashboard principal de Analytics
     */
    public function index()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin'])) {
            header('Location: /musedock/login');
            exit;
        }

        $this->checkPermission('analytics-view');

        // Período de tiempo (últimos 30 días por defecto)
        $period = $_GET['period'] ?? '30';
        $tenantId = $_GET['tenant_id'] ?? null;

        $db = Database::connect();

        // Estadísticas generales
        $stats = $this->getGeneralStats($db, $period, $tenantId);

        // Gráfico de visitas por día
        $visitsChart = $this->getVisitsByDay($db, $period, $tenantId);

        // Top páginas
        $topPages = $this->getTopPages($db, $period, $tenantId);

        // Top países
        $topCountries = $this->getTopCountries($db, $period, $tenantId);

        // Fuentes de tráfico
        $trafficSources = $this->getTrafficSources($db, $period, $tenantId);

        // Dispositivos
        $devices = $this->getDeviceStats($db, $period, $tenantId);

        // Navegadores
        $browsers = $this->getBrowserStats($db, $period, $tenantId);

        // Tiempo real (últimos 5 minutos)
        $realtime = $this->getRealtimeStats($db, $tenantId);

        // Obtener lista de tenants para el filtro
        $tenants = [];
        if (getenv('MULTI_TENANT_ENABLED') === 'true') {
            $stmt = $db->query("SELECT id, name, domain FROM tenants WHERE status = 'active' ORDER BY name");
            $tenants = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return View::renderSuperadmin('analytics.dashboard', [
            'title' => 'Web Analytics',
            'stats' => $stats,
            'visitsChart' => $visitsChart,
            'topPages' => $topPages,
            'topCountries' => $topCountries,
            'trafficSources' => $trafficSources,
            'devices' => $devices,
            'browsers' => $browsers,
            'realtime' => $realtime,
            'period' => $period,
            'tenantId' => $tenantId,
            'tenants' => $tenants
        ]);
    }

    /**
     * Estadísticas generales
     */
    private function getGeneralStats($db, $period, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        // Visitas totales
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors,
                   COUNT(DISTINCT session_id) as total_sessions,
                   AVG(session_duration) as avg_duration
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereTenant}
        ");
        $stmt->execute($params);
        $current = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Período anterior (para comparación)
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereTenant}
        ");
        $prevParams = [$period * 2, $period];
        if ($tenantId) $prevParams[] = $tenantId;
        $stmt->execute($prevParams);
        $previous = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Calcular cambios porcentuales
        $visitChange = $this->calculateChange($current['total_visits'], $previous['total_visits']);
        $visitorChange = $this->calculateChange($current['unique_visitors'], $previous['unique_visitors']);

        // Tasa de rebote
        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN bounce = 1 THEN 1 ELSE 0 END) as bounces,
                COUNT(*) as total
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereTenant}
        ");
        $stmt->execute($params);
        $bounceData = $stmt->fetch(\PDO::FETCH_ASSOC);
        $bounceRate = $bounceData['total'] > 0 ? ($bounceData['bounces'] / $bounceData['total']) * 100 : 0;

        return [
            'total_visits' => $current['total_visits'] ?? 0,
            'unique_visitors' => $current['unique_visitors'] ?? 0,
            'total_sessions' => $current['total_sessions'] ?? 0,
            'avg_duration' => round($current['avg_duration'] ?? 0),
            'bounce_rate' => round($bounceRate, 1),
            'visit_change' => $visitChange,
            'visitor_change' => $visitorChange,
        ];
    }

    /**
     * Visitas por día
     */
    private function getVisitsByDay($db, $period, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        $stmt = $db->prepare("
            SELECT DATE(created_at) as date,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereTenant}
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Top páginas más visitadas
     */
    private function getTopPages($db, $period, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        $stmt = $db->prepare("
            SELECT page_url,
                   page_title,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors,
                   AVG(session_duration) as avg_time
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereTenant}
            GROUP BY page_url, page_title
            ORDER BY visits DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Top países
     */
    private function getTopCountries($db, $period, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        $stmt = $db->prepare("
            SELECT country,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND country IS NOT NULL
            {$whereTenant}
            GROUP BY country
            ORDER BY visits DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fuentes de tráfico
     */
    private function getTrafficSources($db, $period, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        $stmt = $db->prepare("
            SELECT referrer_type,
                   search_engine,
                   referrer_domain,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereTenant}
            GROUP BY referrer_type, search_engine, referrer_domain
            ORDER BY visits DESC
            LIMIT 20
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas de dispositivos
     */
    private function getDeviceStats($db, $period, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        $stmt = $db->prepare("
            SELECT device_type,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND device_type IS NOT NULL
            {$whereTenant}
            GROUP BY device_type
            ORDER BY visits DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas de navegadores
     */
    private function getBrowserStats($db, $period, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        $stmt = $db->prepare("
            SELECT browser,
                   COUNT(*) as visits
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND browser IS NOT NULL
            {$whereTenant}
            GROUP BY browser
            ORDER BY visits DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas en tiempo real (últimos 5 minutos)
     */
    private function getRealtimeStats($db, $tenantId): array
    {
        $whereTenant = $tenantId ? "AND tenant_id = ?" : "";
        $params = [];
        if ($tenantId) $params[] = $tenantId;

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT visitor_id) as active_visitors,
                   COUNT(*) as recent_pageviews
            FROM web_analytics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            {$whereTenant}
        ");
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Calcular cambio porcentual
     */
    private function calculateChange($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * API endpoint para obtener datos en tiempo real
     */
    public function realtimeApi()
    {
        SessionSecurity::startSession();

        if (!isset($_SESSION['super_admin'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $this->checkPermission('analytics-view');

        $tenantId = $_GET['tenant_id'] ?? null;
        $db = Database::connect();

        $data = $this->getRealtimeStats($db, $tenantId);

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
