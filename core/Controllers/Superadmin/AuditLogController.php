<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

use Screenart\Musedock\Traits\RequiresPermission;
/**
 * SECURITY: Panel de Auditoría para Superadmin
 * GDPR Compliant - Permite al superadmin inspeccionar logs de auditoría
 */
class AuditLogController
{
    use RequiresPermission;

    /**
     * Mostrar panel de auditoría con logs
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('logs.view');

        // Parámetros de filtrado
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $action = isset($_GET['action']) ? trim($_GET['action']) : '';
        $resourceType = isset($_GET['resource_type']) ? trim($_GET['resource_type']) : '';
        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

        try {
            $pdo = Database::connect();

            // Construir query con filtros
            $where = ['1=1'];
            $params = [];

            if (!empty($search)) {
                $where[] = "(action LIKE ? OR resource_type LIKE ? OR data::text LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if (!empty($action)) {
                $where[] = "action = ?";
                $params[] = $action;
            }

            if (!empty($resourceType)) {
                $where[] = "resource_type = ?";
                $params[] = $resourceType;
            }

            if ($userId > 0) {
                $where[] = "user_id = ?";
                $params[] = $userId;
            }

            if (!empty($dateFrom)) {
                $where[] = "created_at >= ?";
                $params[] = $dateFrom . ' 00:00:00';
            }

            if (!empty($dateTo)) {
                $where[] = "created_at <= ?";
                $params[] = $dateTo . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $where);

            // Contar total de registros
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE {$whereClause}");
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();
            $totalPages = ceil($totalRecords / $perPage);

            // Obtener logs con información de usuario
            $logsStmt = $pdo->prepare("
                SELECT
                    al.*,
                    COALESCE(u.username, sa.email, 'Sistema') as user_name,
                    COALESCE(u.email, sa.email, 'N/A') as user_email
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id AND al.user_type = 'user'
                LEFT JOIN super_admins sa ON al.user_id = sa.id AND al.user_type = 'super_admin'
                WHERE {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ");

            $logsStmt->execute(array_merge($params, [$perPage, $offset]));
            $logs = $logsStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decodificar JSON data
            foreach ($logs as &$log) {
                $log['data'] = json_decode($log['data'] ?? '{}', true);
            }

            // Obtener listas para filtros
            $actionsStmt = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
            $actions = $actionsStmt->fetchAll(\PDO::FETCH_COLUMN);

            $resourceTypesStmt = $pdo->query("SELECT DISTINCT resource_type FROM audit_logs ORDER BY resource_type");
            $resourceTypes = $resourceTypesStmt->fetchAll(\PDO::FETCH_COLUMN);

            // Estadísticas
            $statsStmt = $pdo->query("
                SELECT
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT DATE(created_at)) as days_logged,
                    MAX(created_at) as last_log
                FROM audit_logs
            ");
            $stats = $statsStmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("Error al obtener audit logs: " . $e->getMessage());
            $logs = [];
            $stats = ['total_logs' => 0, 'unique_users' => 0, 'days_logged' => 0, 'last_log' => null];
            $actions = [];
            $resourceTypes = [];
            $totalPages = 1;
            $totalRecords = 0;
        }

        return View::renderSuperadmin('audit-logs.index', [
            'title' => 'Auditoría de Seguridad',
            'logs' => $logs,
            'stats' => $stats,
            'actions' => $actions,
            'resourceTypes' => $resourceTypes,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'perPage' => $perPage,
            'filters' => [
                'search' => $search,
                'action' => $action,
                'resource_type' => $resourceType,
                'user_id' => $userId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]
        ]);
    }

    /**
     * Exportar logs a CSV
     */
    public function export()
    {
        SessionSecurity::startSession();
        $this->checkPermission('logs.view');

        // Verificar que sea superadmin
        if (!isset($_SESSION['super_admin'])) {
            flash('error', 'Acceso denegado');
            header('Location: /musedock/dashboard');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Aplicar los mismos filtros que en index()
            $action = isset($_GET['action']) ? trim($_GET['action']) : '';
            $resourceType = isset($_GET['resource_type']) ? trim($_GET['resource_type']) : '';
            $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
            $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

            $where = ['1=1'];
            $params = [];

            if (!empty($action)) {
                $where[] = "action = ?";
                $params[] = $action;
            }

            if (!empty($resourceType)) {
                $where[] = "resource_type = ?";
                $params[] = $resourceType;
            }

            if (!empty($dateFrom)) {
                $where[] = "created_at >= ?";
                $params[] = $dateFrom . ' 00:00:00';
            }

            if (!empty($dateTo)) {
                $where[] = "created_at <= ?";
                $params[] = $dateTo . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT
                    al.id,
                    al.user_id,
                    al.user_type,
                    COALESCE(u.username, sa.email, 'Sistema') as user_name,
                    al.action,
                    al.resource_type,
                    al.resource_id,
                    al.ip_address,
                    al.user_agent,
                    al.data,
                    al.created_at
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id AND al.user_type = 'user'
                LEFT JOIN super_admins sa ON al.user_id = sa.id AND al.user_type = 'super_admin'
                WHERE {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT 10000
            ");

            $stmt->execute($params);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Generar CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="audit-logs-' . date('Y-m-d_H-i-s') . '.csv"');

            $output = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($output, [
                'ID',
                'Usuario',
                'Tipo Usuario',
                'Acción',
                'Tipo Recurso',
                'ID Recurso',
                'IP',
                'User Agent',
                'Datos',
                'Fecha'
            ]);

            // Datos
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['user_name'],
                    $log['user_type'],
                    $log['action'],
                    $log['resource_type'],
                    $log['resource_id'],
                    $log['ip_address'],
                    $log['user_agent'],
                    $log['data'],
                    $log['created_at']
                ]);
            }

            fclose($output);
            exit;

        } catch (\Exception $e) {
            error_log("Error al exportar audit logs: " . $e->getMessage());
            flash('error', 'Error al exportar logs');
            header('Location: /musedock/audit-logs');
            exit;
        }
    }

    /**
     * Ver detalle de un log específico
     */
    public function show($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('logs.view');

        try {
            $pdo = Database::connect();

            $stmt = $pdo->prepare("
                SELECT
                    al.*,
                    COALESCE(u.username, sa.email, 'Sistema') as user_name,
                    COALESCE(u.email, sa.email, 'N/A') as user_email
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id AND al.user_type = 'user'
                LEFT JOIN super_admins sa ON al.user_id = sa.id AND al.user_type = 'super_admin'
                WHERE al.id = ?
            ");

            $stmt->execute([$id]);
            $log = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$log) {
                flash('error', 'Log no encontrado');
                header('Location: /musedock/audit-logs');
                exit;
            }

            $log['data'] = json_decode($log['data'] ?? '{}', true);

            return View::renderSuperadmin('audit-logs.show', [
                'title' => 'Detalle de Log de Auditoría',
                'log' => $log
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener detalle de audit log: " . $e->getMessage());
            flash('error', 'Error al cargar el log');
            header('Location: /musedock/audit-logs');
            exit;
        }
    }
}
