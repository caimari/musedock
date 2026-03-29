<?php

namespace CrossPublisherAdmin\Controllers;

use CrossPublisherAdmin\Models\Queue;
use CrossPublisherAdmin\Models\DomainGroup;
use CrossPublisherAdmin\Models\GlobalSettings;
use CrossPublisherAdmin\Models\Relation;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use PDO;

class PostBrowserController
{
    public function index()
    {
        $groups = DomainGroup::allWithCounts();

        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT t.id, t.name, t.domain, t.group_id,
                   COALESCE(ts.value, 'es') as default_lang
            FROM tenants t
            LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id AND ts.key = 'default_lang'
            WHERE t.status = 'active' AND t.group_id IS NOT NULL
            ORDER BY t.name ASC
        ");
        $tenants = $stmt->fetchAll(PDO::FETCH_OBJ);

        $settings = GlobalSettings::get();

        return View::renderSuperadmin('plugins.cross-publisher.posts.browse', [
            'groups' => $groups,
            'tenants' => $tenants,
            'autoTranslate' => !empty($settings['auto_translate']),
            'defaultTargetStatus' => $settings['default_target_status'] ?? 'draft',
        ]);
    }

    public function fetch()
    {
        $pdo = Database::connect();

        $tenantId = !empty($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
        $groupId = !empty($_GET['group_id']) ? (int) $_GET['group_id'] : null;
        $status = $_GET['status'] ?? 'published';
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $where = ["bp.deleted_at IS NULL", "t.group_id IS NOT NULL"];
        $params = [];

        if ($tenantId) {
            $where[] = "bp.tenant_id = ?";
            $params[] = $tenantId;
        } elseif ($groupId) {
            $where[] = "t.group_id = ?";
            $params[] = $groupId;
        }

        if ($status && $status !== 'all') {
            $where[] = "bp.status = ?";
            $params[] = $status;
        }

        if ($search) {
            $where[] = "(bp.title ILIKE ? OR bp.slug ILIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countSql = "SELECT COUNT(*) FROM blog_posts bp JOIN tenants t ON bp.tenant_id = t.id WHERE {$whereClause}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Fetch posts
        $sql = "
            SELECT bp.id, bp.tenant_id, bp.title, bp.slug, bp.status, bp.featured_image,
                   bp.published_at, bp.base_locale, bp.view_count,
                   t.name as tenant_name, t.domain as tenant_domain,
                   dg.name as group_name,
                   COALESCE(bp.base_locale, ts_lang.value, 'es') as source_lang
            FROM blog_posts bp
            JOIN tenants t ON bp.tenant_id = t.id
            LEFT JOIN domain_groups dg ON t.group_id = dg.id
            LEFT JOIN tenant_settings ts_lang ON ts_lang.tenant_id = t.id AND ts_lang.key = 'default_lang'
            WHERE {$whereClause}
            ORDER BY bp.published_at DESC NULLS LAST, bp.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(PDO::FETCH_OBJ);

        header('Content-Type: application/json');
        echo json_encode([
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage),
        ]);
        exit;
    }

    public function addToQueue()
    {
        $sourcePostId = (int) ($_POST['source_post_id'] ?? 0);
        $sourceTenantId = (int) ($_POST['source_tenant_id'] ?? 0);
        $targets = $_POST['targets'] ?? [];

        if ($sourcePostId <= 0 || $sourceTenantId <= 0 || empty($targets)) {
            flash('error', 'Datos incompletos. Selecciona post y destinos.');
            header('Location: /musedock/cross-publisher/posts');
            exit;
        }

        $settings = GlobalSettings::get();
        $added = 0;
        $skipped = 0;

        foreach ($targets as $target) {
            $targetTenantId = (int) ($target['tenant_id'] ?? 0);
            if ($targetTenantId <= 0 || $targetTenantId === $sourceTenantId) {
                continue;
            }

            // Check if already queued or relation exists
            if (Queue::isQueued($sourcePostId, $sourceTenantId, $targetTenantId) ||
                Relation::exists($sourcePostId, $sourceTenantId, $targetTenantId)) {
                $skipped++;
                continue;
            }

            Queue::add([
                'source_post_id' => $sourcePostId,
                'source_tenant_id' => $sourceTenantId,
                'target_tenant_id' => $targetTenantId,
                'translate' => !empty($target['translate']),
                'source_language' => $target['source_language'] ?? null,
                'target_language' => $target['target_language'] ?? null,
                'target_status' => $target['target_status'] ?? $settings['default_target_status'] ?? 'draft',
                'ai_provider_id' => !empty($target['ai_provider_id']) ? (int) $target['ai_provider_id'] : ($settings['ai_provider_id'] ?? null),
                'created_by' => $_SESSION['superadmin']['id'] ?? null,
            ]);
            $added++;
        }

        if ($added > 0) {
            flash('success', "{$added} item(s) añadidos a la cola." . ($skipped > 0 ? " {$skipped} omitidos (ya existentes)." : ''));
        } else {
            flash('error', 'No se añadieron items. Todos ya estaban en cola o publicados.');
        }

        header('Location: /musedock/cross-publisher/queue');
        exit;
    }
}
