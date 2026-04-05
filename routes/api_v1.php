<?php
/**
 * MuseDock CMS — REST API v1 Routes
 *
 * All endpoints require Bearer API key authentication.
 * Prefix: /api/v1/
 *
 * CORE routes (always loaded): tenants, pages, stats, tools, openapi
 * MODULE routes (conditional): posts, categories, tags (require blog module)
 */

use Screenart\Musedock\Route;
use Screenart\Musedock\Middlewares\ApiKeyAuth;

// ============================================================================
// CORS preflight
// ============================================================================
Route::options('/api/v1/{path:.*}', function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
    http_response_code(204);
    exit;
});

// ============================================================================
// Helper: auth + per-tool rate limit + confirmation + logging
// ============================================================================
function api_v1_auth(string $toolName = ''): void
{
    $auth = new ApiKeyAuth();
    $auth->handle();

    if ($toolName === '') return;

    $key = ApiKeyAuth::key();
    if (!$key) return;

    // Per-tool rate limit
    if (!\Screenart\Musedock\Services\ApiToolLogger::checkToolRateLimit(
        (int)$key->id, $toolName, (int)$key->rate_limit
    )) {
        $limit = \Screenart\Musedock\Services\ApiToolLogger::getToolRateLimit($toolName, (int)$key->rate_limit);
        $retryAfter = 60 - (int)date('s');
        header("Retry-After: {$retryAfter}");
        http_response_code(429);
        echo json_encode([
            'success'     => false,
            'error'       => ['code' => 'TOOL_RATE_LIMITED', 'message' => "Rate limit for '{$toolName}': max {$limit}/min."],
            'retry_after' => $retryAfter,
            'tool'        => $toolName,
            'limit'       => $limit,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Confirmation for dangerous actions
    $confirmation = \Screenart\Musedock\Services\ApiToolLogger::requiresConfirmation($toolName);
    if ($confirmation !== null) {
        http_response_code(428);
        echo json_encode(array_merge(['success' => false], $confirmation), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Logging (via shutdown so it captures the final status code)
    $GLOBALS['_api_tool_start'] = microtime(true);
    register_shutdown_function(function () use ($key, $toolName) {
        $startTime = $GLOBALS['_api_tool_start'] ?? null;
        $statusCode = http_response_code() ?: 200;
        $success = $statusCode >= 200 && $statusCode < 400;
        $tenantId = ApiKeyAuth::resolveTenantId();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '';
        $input = ApiKeyAuth::getJsonInput();

        \Screenart\Musedock\Services\ApiToolLogger::log(
            (int)$key->id, $tenantId, $toolName, $method, $path,
            $input, $statusCode, $success, $startTime
        );
    });
}

// ============================================================================
// CORE ROUTES — Always available
// ============================================================================

// ---------- Tenants ----------
Route::get('/api/v1/tenants', function () {
    api_v1_auth('list_tenants');
    (new \Screenart\Musedock\Controllers\Api\V1\TenantController())->index();
});

// ---------- Pages (CRUD) — Core ----------
Route::get('/api/v1/pages', function () {
    api_v1_auth('list_pages');
    (new \Screenart\Musedock\Controllers\Api\V1\PageController())->index();
});

Route::get('/api/v1/pages/{id}', function ($id) {
    api_v1_auth('get_page');
    (new \Screenart\Musedock\Controllers\Api\V1\PageController())->show((int) $id);
});

Route::post('/api/v1/pages', function () {
    api_v1_auth('create_page');
    (new \Screenart\Musedock\Controllers\Api\V1\PageController())->store();
});

Route::put('/api/v1/pages/{id}', function ($id) {
    api_v1_auth('update_page');
    (new \Screenart\Musedock\Controllers\Api\V1\PageController())->update((int) $id);
});

Route::delete('/api/v1/pages/{id}', function ($id) {
    api_v1_auth('delete_page');
    (new \Screenart\Musedock\Controllers\Api\V1\PageController())->destroy((int) $id);
});

// ---------- Stats / Metrics — Core ----------
Route::get('/api/v1/stats', function () {
    api_v1_auth();
    $key = \Screenart\Musedock\Middlewares\ApiKeyAuth::key();

    if (!$key->isSuperadmin()) {
        ApiKeyAuth::respond(403, 'FORBIDDEN', 'Only superadmin keys can access stats.');
    }

    $pdo = \Screenart\Musedock\Database::connect();
    $tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;
    $days = min(90, max(1, (int)($_GET['days'] ?? 30)));

    $where = "atl.created_at > NOW() - INTERVAL '{$days} days'";
    $params = [];
    if ($tenantId) {
        $where .= " AND atl.tenant_id = ?";
        $params[] = $tenantId;
    }

    try {
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM api_tool_logs atl WHERE {$where}");
        $totalStmt->execute($params);
        $totalCalls = (int) $totalStmt->fetchColumn();

        $byToolStmt = $pdo->prepare("
            SELECT atl.tool_name, COUNT(*) as calls,
                   SUM(CASE WHEN atl.success = 1 THEN 1 ELSE 0 END) as success_count,
                   ROUND(AVG(atl.duration_ms)) as avg_ms
            FROM api_tool_logs atl WHERE {$where}
            GROUP BY atl.tool_name ORDER BY calls DESC
        ");
        $byToolStmt->execute($params);
        $byTool = $byToolStmt->fetchAll(\PDO::FETCH_ASSOC);

        $byTenantStmt = $pdo->prepare("
            SELECT atl.tenant_id, t.name as tenant_name, t.domain, COUNT(*) as calls
            FROM api_tool_logs atl
            LEFT JOIN tenants t ON t.id = atl.tenant_id
            WHERE {$where}
            GROUP BY atl.tenant_id, t.name, t.domain ORDER BY calls DESC
        ");
        $byTenantStmt->execute($params);
        $byTenant = $byTenantStmt->fetchAll(\PDO::FETCH_ASSOC);

        $byKeyStmt = $pdo->prepare("
            SELECT atl.api_key_id, ak.name as key_name, COUNT(*) as calls
            FROM api_tool_logs atl
            LEFT JOIN api_keys ak ON ak.id = atl.api_key_id
            WHERE {$where}
            GROUP BY atl.api_key_id, ak.name ORDER BY calls DESC
        ");
        $byKeyStmt->execute($params);
        $byKey = $byKeyStmt->fetchAll(\PDO::FETCH_ASSOC);

        $dailyStmt = $pdo->prepare("
            SELECT DATE(atl.created_at) as day, COUNT(*) as calls
            FROM api_tool_logs atl WHERE {$where}
            GROUP BY DATE(atl.created_at) ORDER BY day
        ");
        $dailyStmt->execute($params);
        $daily = $dailyStmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'     => true,
            'period'      => "{$days} days",
            'total_calls' => $totalCalls,
            'by_tool'     => $byTool,
            'by_tenant'   => $byTenant,
            'by_key'      => $byKey,
            'daily'       => $daily,
        ], JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => ['code' => 'STATS_ERROR', 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
    }
});

// ---------- Tools Discovery — Core ----------
Route::get('/api/v1/tools', function () {
    api_v1_auth();
    $key = \Screenart\Musedock\Middlewares\ApiKeyAuth::key();
    $permissions = $key ? $key->getPermissions() : [];
    $tools = \Screenart\Musedock\Services\ApiToolsRegistry::all($permissions);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'tools'   => $tools,
        'count'   => count($tools),
    ], JSON_UNESCAPED_UNICODE);
});

// ---------- OpenAPI Schema — Core ----------
Route::get('/api/v1/openapi.yaml', function () {
    header('Content-Type: text/yaml; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    readfile(APP_ROOT . '/core/Controllers/Api/V1/openapi.yaml');
    exit;
});

// ============================================================================
// BLOG MODULE ROUTES — Only loaded if blog module is active
// ============================================================================
if (function_exists('is_module_active') && is_module_active('blog')) {

    // ---------- Categories (CRUD) ----------
    Route::get('/api/v1/categories', function () {
        api_v1_auth('list_categories');
        (new \Screenart\Musedock\Controllers\Api\V1\CategoryController())->index();
    });

    Route::get('/api/v1/categories/{id}', function ($id) {
        api_v1_auth('get_category');
        (new \Screenart\Musedock\Controllers\Api\V1\CategoryController())->show((int) $id);
    });

    Route::post('/api/v1/categories', function () {
        api_v1_auth('create_category');
        (new \Screenart\Musedock\Controllers\Api\V1\CategoryController())->store();
    });

    Route::put('/api/v1/categories/{id}', function ($id) {
        api_v1_auth('update_category');
        (new \Screenart\Musedock\Controllers\Api\V1\CategoryController())->update((int) $id);
    });

    Route::delete('/api/v1/categories/{id}', function ($id) {
        api_v1_auth('delete_category');
        (new \Screenart\Musedock\Controllers\Api\V1\CategoryController())->destroy((int) $id);
    });

    // ---------- Tags (CRUD) ----------
    Route::get('/api/v1/tags', function () {
        api_v1_auth('list_tags');
        (new \Screenart\Musedock\Controllers\Api\V1\TagController())->index();
    });

    Route::get('/api/v1/tags/{id}', function ($id) {
        api_v1_auth('get_tag');
        (new \Screenart\Musedock\Controllers\Api\V1\TagController())->show((int) $id);
    });

    Route::post('/api/v1/tags', function () {
        api_v1_auth('create_tag');
        (new \Screenart\Musedock\Controllers\Api\V1\TagController())->store();
    });

    Route::put('/api/v1/tags/{id}', function ($id) {
        api_v1_auth('update_tag');
        (new \Screenart\Musedock\Controllers\Api\V1\TagController())->update((int) $id);
    });

    Route::delete('/api/v1/tags/{id}', function ($id) {
        api_v1_auth('delete_tag');
        (new \Screenart\Musedock\Controllers\Api\V1\TagController())->destroy((int) $id);
    });

    // ---------- Posts (CRUD) ----------
    Route::get('/api/v1/posts', function () {
        api_v1_auth('list_posts');
        (new \Screenart\Musedock\Controllers\Api\V1\PostController())->index();
    });

    Route::get('/api/v1/posts/{id}', function ($id) {
        api_v1_auth('get_post');
        (new \Screenart\Musedock\Controllers\Api\V1\PostController())->show((int) $id);
    });

    Route::post('/api/v1/posts', function () {
        api_v1_auth('create_post');
        (new \Screenart\Musedock\Controllers\Api\V1\PostController())->store();
    });

    Route::put('/api/v1/posts/{id}', function ($id) {
        api_v1_auth('update_post');
        (new \Screenart\Musedock\Controllers\Api\V1\PostController())->update((int) $id);
    });

    Route::delete('/api/v1/posts/{id}', function ($id) {
        api_v1_auth('delete_post');
        (new \Screenart\Musedock\Controllers\Api\V1\PostController())->destroy((int) $id);
    });

    // ---------- Cross-publish (requires blog + cross-publisher plugin) ----------
    Route::post('/api/v1/posts/{id}/cross-publish', function ($id) {
        api_v1_auth('cross_publish');
        (new \Screenart\Musedock\Controllers\Api\V1\PostController())->crossPublish((int) $id);
    });

} // end blog module check
