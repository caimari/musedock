<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Env;
use Screenart\Musedock\Security\SessionSecurity;

class AnalyticsController
{
    /**
     * Get database driver
     */
    private function getDriver(): string
    {
        return Env::get('DB_DRIVER', 'mysql');
    }

    /**
     * Get date interval expression compatible with current DB driver
     * @param string $interval The interval value placeholder (? for days, or literal like '5 MINUTE')
     * @param bool $isLiteral If true, interval is a literal value, not a placeholder
     */
    private function dateInterval(string $interval = '?', string $unit = 'DAY', bool $isLiteral = false): string
    {
        $driver = $this->getDriver();

        if ($driver === 'pgsql') {
            if ($isLiteral) {
                // For literal values like '5 MINUTE'
                return "NOW() - INTERVAL '{$interval} {$unit}'";
            }
            // For placeholder values - PostgreSQL needs cast
            $unitLower = strtolower($unit);
            return "NOW() - (? || ' {$unitLower}')::INTERVAL";
        }

        // MySQL syntax
        if ($isLiteral) {
            return "DATE_SUB(NOW(), INTERVAL {$interval} {$unit})";
        }
        return "DATE_SUB(NOW(), INTERVAL ? {$unit})";
    }

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
     * API: Páginas visitadas con paginación
     */
    public function pagesApi()
    {
        SessionSecurity::startSession();
        $this->checkPermission('analytics.view');

        // Limpiar output previo para garantizar JSON limpio
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        try {
            $period = $_GET['period'] ?? '30';
            $tenantId = $_GET['tenant_id'] ?? null;
            $offset = (int)($_GET['offset'] ?? 0);
            $limit = min((int)($_GET['limit'] ?? 20), 100);

            $db = Database::connect();

            $whereTenant = $tenantId ? "AND w.tenant_id = ?" : "";
            $params = [$period];
            if ($tenantId) $params[] = $tenantId;

            $dateExpr = $this->dateInterval('?', 'DAY');
            $stmt = $db->prepare("
                SELECT w.page_url,
                       w.page_title,
                       t.domain as tenant_domain,
                       COUNT(*) as visits,
                       COUNT(DISTINCT w.visitor_id) as unique_visitors,
                       AVG(w.session_duration) as avg_time
                FROM web_analytics w
                LEFT JOIN tenants t ON t.id = w.tenant_id
                WHERE w.created_at >= {$dateExpr} AND w.is_bot = FALSE
                {$whereTenant}
                GROUP BY w.page_url, w.page_title, t.domain
                ORDER BY visits DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['data' => $rows, 'offset' => $offset, 'limit' => $limit, 'count' => count($rows)]);
        } catch (\Throwable $e) {
            echo json_encode(['data' => [], 'error' => $e->getMessage()]);
        }
        exit;
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

        // Período de tiempo (últimos 30 días por defecto): solo 7, 30 o 90
        $period = (string) (in_array((int) ($_GET['period'] ?? 30), [7, 30, 90], true) ? (int) $_GET['period'] : 30);
        $tenantId = !empty($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;

        $db = Database::connect();

        // Las estadísticas recorren cientos de miles de filas (varios segundos): se guardan 10 minutos
        // por periodo y sitio. «?refresh=1» fuerza el recálculo. Tiempo real va siempre en directo.
        $cacheFile = APP_ROOT . '/storage/cache/analytics/' . md5('v2|' . $period . '|' . ($tenantId ?? 'all')) . '.json';
        $cached = null;
        if (empty($_GET['refresh']) && is_file($cacheFile) && filemtime($cacheFile) > time() - 600) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
        }
        if (!is_array($cached) || !isset($cached['stats'])) {
            $cached = [
                'generated_at' => time(),
                'stats' => $this->getGeneralStats($db, $period, $tenantId),
                'visitsChart' => $this->getVisitsByDay($db, $period, $tenantId),
                'topPages' => $this->getTopPages($db, $period, $tenantId),
                'topCountries' => $this->getTopCountries($db, $period, $tenantId),
                'trafficSources' => $this->getTrafficSources($db, $period, $tenantId),
                'devices' => $this->getDeviceStats($db, $period, $tenantId),
                'browsers' => $this->getBrowserStats($db, $period, $tenantId),
                'bots' => $this->getBotStats($db, $period, $tenantId),
            ];
            if (!is_dir(dirname($cacheFile))) {
                @mkdir(dirname($cacheFile), 0775, true);
            }
            @file_put_contents($cacheFile, json_encode($cached), LOCK_EX);
        }
        $stats = $cached['stats'];
        $visitsChart = $cached['visitsChart'];
        $topPages = $cached['topPages'];
        $topCountries = $cached['topCountries'];
        $trafficSources = $cached['trafficSources'];
        $devices = $cached['devices'];
        $browsers = $cached['browsers'];
        $bots = $cached['bots'];

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
            'bots' => $bots,
            'botKinds' => \Screenart\Musedock\Services\BotClassifier::KINDS,
            'realtime' => $realtime,
            'period' => $period,
            'generatedAt' => $cached['generated_at'],
            'tenantId' => $tenantId,
            'tenants' => $tenants
        ]);
    }

    /**
     * Visitas de robots del periodo, por tipo y por nombre (se guardan, pero no cuentan como visitas reales).
     */
    private function getBotStats($db, $period, $tenantId): array
    {
        $days = (int) $period;
        $d1 = $this->dateInterval((string) $days, 'DAY', true);
        $whereTenant = $tenantId ? 'AND tenant_id = ?' : '';
        $params = $tenantId ? [$tenantId] : [];

        $stmt = $db->prepare("SELECT COALESCE(bot_kind, 'other') AS kind, COALESCE(bot_name, 'Sin clasificar') AS name, COUNT(*) AS visits
            FROM web_analytics WHERE created_at >= {$d1} AND is_bot = TRUE {$whereTenant}
            GROUP BY 1, 2 ORDER BY visits DESC");
        $stmt->execute($params);
        $kinds = [];
        $total = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $kinds[$r['kind']]['visits'] = ($kinds[$r['kind']]['visits'] ?? 0) + (int) $r['visits'];
            if (count($kinds[$r['kind']]['top'] ?? []) < 5) {
                $kinds[$r['kind']]['top'][] = ['name' => $r['name'], 'visits' => (int) $r['visits']];
            }
            $total += (int) $r['visits'];
        }
        uasort($kinds, fn ($a, $b) => $b['visits'] <=> $a['visits']);

        return ['total' => $total, 'kinds' => $kinds];
    }

    /**
     * Estadísticas generales
     */
    private function getGeneralStats($db, $period, $tenantId): array
    {
        // Dos consultas con rangos simples (periodo actual con el rebote incluido, y periodo anterior).
        // Se probó mezclarlas en una con CASE y resultó 3 veces más lenta (varios COUNT DISTINCT a la vez).
        // $period es siempre un entero validado, por eso va como literal en el INTERVAL.
        $days = (int) $period;
        $d1 = $this->dateInterval((string) $days, 'DAY', true);
        $d2 = $this->dateInterval((string) ($days * 2), 'DAY', true);
        $bounce = $this->getDriver() === 'pgsql' ? 'bounce = TRUE' : 'bounce = 1';
        $whereTenant = $tenantId ? 'AND tenant_id = ?' : '';
        $params = $tenantId ? [$tenantId] : [];

        $stmt = $db->prepare("
            SELECT COUNT(*) AS total_visits,
                   COUNT(DISTINCT visitor_id) AS unique_visitors,
                   COUNT(DISTINCT session_id) AS total_sessions,
                   AVG(session_duration) AS avg_duration,
                   SUM(CASE WHEN {$bounce} THEN 1 ELSE 0 END) AS bounces
            FROM web_analytics
            WHERE created_at >= {$d1} AND is_bot = FALSE
            {$whereTenant}
        ");
        $stmt->execute($params);
        $current = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT COUNT(*) AS total_visits,
                   COUNT(DISTINCT visitor_id) AS unique_visitors
            FROM web_analytics
            WHERE created_at >= {$d2} AND created_at < {$d1} AND is_bot = FALSE
            {$whereTenant}
        ");
        $stmt->execute($params);
        $previous = $stmt->fetch(\PDO::FETCH_ASSOC);

        $visitChange = $this->calculateChange($current['total_visits'], $previous['total_visits']);
        $visitorChange = $this->calculateChange($current['unique_visitors'], $previous['unique_visitors']);
        $bounceRate = $current['total_visits'] > 0 ? ($current['bounces'] / $current['total_visits']) * 100 : 0;

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

        $dateExpr = $this->dateInterval('?', 'DAY');
        $stmt = $db->prepare("
            SELECT DATE(created_at) as date,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= {$dateExpr} AND is_bot = FALSE
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
        $whereTenant = $tenantId ? "AND w.tenant_id = ?" : "";
        $params = [$period];
        if ($tenantId) $params[] = $tenantId;

        $dateExpr = $this->dateInterval('?', 'DAY');
        $stmt = $db->prepare("
            SELECT w.page_url,
                   w.page_title,
                   t.domain as tenant_domain,
                   COUNT(*) as visits,
                   COUNT(DISTINCT w.visitor_id) as unique_visitors,
                   AVG(w.session_duration) as avg_time
            FROM web_analytics w
            LEFT JOIN tenants t ON t.id = w.tenant_id
            WHERE w.created_at >= {$dateExpr} AND w.is_bot = FALSE
            {$whereTenant}
            GROUP BY w.page_url, w.page_title, t.domain
            ORDER BY visits DESC
            LIMIT 500
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

        $dateExpr = $this->dateInterval('?', 'DAY');
        $stmt = $db->prepare("
            SELECT country,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= {$dateExpr} AND is_bot = FALSE
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

        $dateExpr = $this->dateInterval('?', 'DAY');
        $stmt = $db->prepare("
            SELECT referrer_type,
                   search_engine,
                   referrer_domain,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= {$dateExpr} AND is_bot = FALSE
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

        $dateExpr = $this->dateInterval('?', 'DAY');
        $stmt = $db->prepare("
            SELECT device_type,
                   COUNT(*) as visits,
                   COUNT(DISTINCT visitor_id) as unique_visitors
            FROM web_analytics
            WHERE created_at >= {$dateExpr} AND is_bot = FALSE
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

        $dateExpr = $this->dateInterval('?', 'DAY');
        $stmt = $db->prepare("
            SELECT browser,
                   COUNT(*) as visits
            FROM web_analytics
            WHERE created_at >= {$dateExpr} AND is_bot = FALSE
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

        $dateExpr = $this->dateInterval('5', 'MINUTE', true);
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT visitor_id) as active_visitors,
                   COUNT(*) as recent_pageviews
            FROM web_analytics
            WHERE created_at >= {$dateExpr} AND is_bot = FALSE
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
