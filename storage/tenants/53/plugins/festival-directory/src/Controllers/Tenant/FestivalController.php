<?php

namespace FestivalDirectory\Controllers\Tenant;

use FestivalDirectory\Models\Festival;
use FestivalDirectory\Models\FestivalCategory;
use FestivalDirectory\Models\FestivalTag;
use FestivalDirectory\Models\FestivalType;
use FestivalDirectory\Requests\FestivalRequest;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Services\AuditLogger;

class FestivalController
{
    private function checkPermission(string $permission): void
    {
        if (function_exists('userCan') && !userCan($permission)) {
            flash('error', 'No tienes permisos para esta acción.');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    private function getTenantId(): int
    {
        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', 'Tenant no identificado.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
        return $tenantId;
    }

    /**
     * List festivals with search, sort, pagination.
     */
    public function index()
    {
        $this->checkPermission('festivals.view');
        $tenantId = $this->getTenantId();

        $search      = isset($_GET['search']) ? trim(substr($_GET['search'], 0, 255)) : '';
        $perPage     = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $orderBy     = $_GET['orderby'] ?? 'created_at';
        $order       = (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') ? 'ASC' : 'DESC';
        $statusFilter = $_GET['status'] ?? '';
        $typeFilter   = $_GET['type'] ?? '';

        $allowedColumns = ['name', 'country', 'type', 'status', 'created_at', 'view_count', 'featured'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'created_at';
        }

        $query = Festival::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("(deleted_at IS NULL)")
            ->orderBy($orderBy, $order);

        if (!empty($search)) {
            $s = "%{$search}%";
            $query->whereRaw("(name ILIKE ? OR country ILIKE ? OR city ILIKE ? OR slug ILIKE ?)", [$s, $s, $s, $s]);
        }

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }
        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }

        $totalCount = $query->count();

        if ($perPage == -1) {
            $perPage = min($totalCount, 500);
        }

        $festivals = $query->limit($perPage)->offset(($currentPage - 1) * $perPage)->get();

        // Batch load categories
        $festivalCategories = [];
        if (!empty($festivals)) {
            $ids = array_map(fn($f) => $f->id, $festivals);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT fp.festival_id, c.id, c.name, c.slug, c.color
                FROM festival_category_pivot fp
                INNER JOIN festival_categories c ON c.id = fp.category_id
                WHERE fp.festival_id IN ({$placeholders})
            ");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $festivalCategories[$row['festival_id']][] = $row;
            }
        }

        // Pending claims count
        $pdo = Database::connect();
        $claimStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM festival_claims WHERE tenant_id = ? AND status = 'pending'");
        $claimStmt->execute([$tenantId]);
        $pendingClaims = (int)$claimStmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        $pagination = [
            'total'        => $totalCount,
            'per_page'     => $perPage,
            'current_page' => $currentPage,
            'last_page'    => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
            'from'         => ($currentPage - 1) * $perPage + 1,
            'to'           => min($currentPage * $perPage, $totalCount),
        ];

        echo festival_render_admin('tenant.festivals.index', [
            'title'              => 'Festivales',
            'festivals'          => $festivals,
            'festivalCategories' => $festivalCategories,
            'pagination'         => $pagination,
            'search'             => $search,
            'orderBy'            => $orderBy,
            'order'              => $order,
            'statusFilter'       => $statusFilter,
            'typeFilter'         => $typeFilter,
            'pendingClaims'      => $pendingClaims,
            'types'              => FestivalType::getTypesForTenant($tenantId),
            'statuses'           => Festival::getStatuses(),
        ]);
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $this->checkPermission('festivals.create');
        $tenantId = $this->getTenantId();

        $categories = FestivalCategory::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();
        $tags       = FestivalTag::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        echo festival_render_admin('tenant.festivals.create', [
            'title'              => 'Crear Festival',
            'festival'           => new Festival(),
            'categories'         => $categories,
            'tags'               => $tags,
            'types'              => FestivalType::getTypesForTenant($tenantId),
            'frequencies'        => Festival::getFrequencies(),
            'submissionStatuses' => Festival::getSubmissionStatuses(),
            'statuses'           => Festival::getStatuses(),
            'selectedCategories' => [],
            'selectedTags'       => [],
            'isNew'              => true,
        ]);
    }

    /**
     * Store a new festival.
     */
    public function store()
    {
        $this->checkPermission('festivals.create');
        $tenantId = $this->getTenantId();

        $data = $_POST;
        $data['tenant_id'] = $tenantId;

        // Auto-generate slug if empty
        if (empty(trim($data['slug'] ?? ''))) {
            $data['slug'] = festival_slugify($data['name'] ?? '');
        }

        // Validate
        $errors = FestivalRequest::validate($data);
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            header('Location: ' . festival_admin_url('create'));
            exit;
        }

        // Check slug uniqueness
        $existing = Festival::where('tenant_id', $tenantId)->where('slug', $data['slug'])->first();
        if ($existing) {
            $data['slug'] = $data['slug'] . '-' . time();
        }

        // Checkboxes
        $data['featured'] = isset($data['featured']) ? 1 : 0;
        $data['noindex']  = isset($data['noindex']) ? 1 : 0;

        // Sanitize URL fields
        $urlFields = ['website_url', 'social_facebook', 'social_instagram', 'social_twitter',
            'social_youtube', 'social_vimeo', 'social_linkedin',
            'submission_filmfreeway_url', 'submission_festhome_url',
            'submission_festgate_url', 'submission_other_url',
            'seo_image', 'logo', 'cover_image', 'featured_image'];

        foreach ($urlFields as $field) {
            if (!empty($data[$field]) && !preg_match('#^(https?://|/)#i', $data[$field])) {
                $data[$field] = null;
            }
        }

        // Nullify empty dates
        foreach (['start_date', 'end_date', 'deadline_date'] as $dateField) {
            if (empty($data[$dateField])) {
                $data[$dateField] = null;
            }
        }

        // Nullify empty numeric fields
        foreach (['edition_number', 'edition_year', 'latitude', 'longitude'] as $numField) {
            if (isset($data[$numField]) && $data[$numField] === '') {
                $data[$numField] = null;
            }
        }

        // Generate claim token
        $data['claim_token'] = bin2hex(random_bytes(32));

        // Extract taxonomy before create
        $selectedCategories = $data['categories'] ?? [];
        $selectedTags       = $data['tags'] ?? [];
        unset($data['categories'], $data['tags'], $data['_token'], $data['_method']);

        $festival = Festival::create($data);

        if ($festival && $festival->id) {
            // Sync taxonomy
            if (!empty($selectedCategories)) {
                $festival->syncCategories($selectedCategories);
            }
            if (!empty($selectedTags)) {
                $festival->syncTags($selectedTags);
            }

            // Register slug
            try {
                $pdo = Database::connect();
                $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)")
                    ->execute(['festivals', $festival->id, $data['slug'], $tenantId, 'festivals']);
            } catch (\Exception $e) {
                error_log("Festival slug registration error: " . $e->getMessage());
            }

            // Audit
            if (class_exists(AuditLogger::class)) {
                AuditLogger::log('festival.created', 'festival', $festival->id, [
                    'name'      => $data['name'] ?? '',
                    'slug'      => $data['slug'] ?? '',
                    'status'    => $data['status'] ?? 'draft',
                    'tenant_id' => $tenantId,
                ]);
            }

            flash('success', 'Festival creado correctamente.');
        } else {
            flash('error', 'Error al crear el festival.');
        }

        header('Location: ' . festival_admin_url());
        exit;
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $this->checkPermission('festivals.edit');
        $tenantId = $this->getTenantId();

        $festival = Festival::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$festival) {
            flash('error', 'Festival no encontrado.');
            header('Location: ' . festival_admin_url());
            exit;
        }

        if (is_array($festival) || $festival instanceof \stdClass) {
            $festival = new Festival($festival);
        }

        $categories = FestivalCategory::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();
        $tags       = FestivalTag::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        echo festival_render_admin('tenant.festivals.edit', [
            'title'              => 'Editar Festival' . ': ' . ($festival->name ?? ''),
            'festival'           => $festival,
            'categories'         => $categories,
            'tags'               => $tags,
            'types'              => FestivalType::getTypesForTenant($tenantId),
            'frequencies'        => Festival::getFrequencies(),
            'submissionStatuses' => Festival::getSubmissionStatuses(),
            'statuses'           => Festival::getStatuses(),
            'selectedCategories' => $festival->getCategoryIds(),
            'selectedTags'       => $festival->getTagIds(),
            'isNew'              => false,
        ]);
    }

    /**
     * Update a festival.
     */
    public function update($id)
    {
        $this->checkPermission('festivals.edit');
        $tenantId = $this->getTenantId();

        $festival = Festival::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$festival) {
            flash('error', 'Festival no encontrado.');
            header('Location: ' . festival_admin_url());
            exit;
        }

        if (is_array($festival) || $festival instanceof \stdClass) {
            $festival = new Festival($festival);
        }

        $data = $_POST;

        // Validate
        $errors = FestivalRequest::validate($data);
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            header('Location: ' . festival_admin_url($id . '/edit'));
            exit;
        }

        // Checkboxes
        $data['featured'] = isset($data['featured']) ? 1 : 0;
        $data['noindex']  = isset($data['noindex']) ? 1 : 0;

        // Sanitize URL fields
        $urlFields = ['website_url', 'social_facebook', 'social_instagram', 'social_twitter',
            'social_youtube', 'social_vimeo', 'social_linkedin',
            'submission_filmfreeway_url', 'submission_festhome_url',
            'submission_festgate_url', 'submission_other_url',
            'seo_image', 'logo', 'cover_image', 'featured_image'];

        foreach ($urlFields as $field) {
            if (!empty($data[$field]) && !preg_match('#^(https?://|/)#i', $data[$field])) {
                $data[$field] = null;
            }
        }

        // Nullify empty dates
        foreach (['start_date', 'end_date', 'deadline_date'] as $dateField) {
            if (empty($data[$dateField])) {
                $data[$dateField] = null;
            }
        }

        // Nullify empty numeric fields
        foreach (['edition_number', 'edition_year', 'latitude', 'longitude'] as $numField) {
            if (isset($data[$numField]) && $data[$numField] === '') {
                $data[$numField] = null;
            }
        }

        // Extract taxonomy
        $selectedCategories = $data['categories'] ?? [];
        $selectedTags       = $data['tags'] ?? [];
        unset($data['categories'], $data['tags'], $data['_token'], $data['_method']);

        // Don't overwrite certain fields
        unset($data['tenant_id'], $data['claimed_by'], $data['claimed_at'], $data['claim_token']);

        // Check slug change
        $oldSlug = $festival->slug;
        if (isset($data['slug']) && $data['slug'] !== $oldSlug) {
            $slugExists = Festival::where('tenant_id', $tenantId)
                ->where('slug', $data['slug'])
                ->whereRaw("id != ?", [$id])
                ->first();
            if ($slugExists) {
                $data['slug'] = $data['slug'] . '-' . time();
            }

            // Update slugs table
            try {
                $pdo = Database::connect();
                $pdo->prepare("UPDATE slugs SET slug = ? WHERE module = 'festivals' AND reference_id = ? AND tenant_id = ?")
                    ->execute([$data['slug'], $id, $tenantId]);
            } catch (\Exception $e) {
                error_log("Festival slug update error: " . $e->getMessage());
            }
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        // Update festival
        $festival->fill($data);
        $festival->save();

        // Sync taxonomy
        $festival->syncCategories($selectedCategories);
        $festival->syncTags($selectedTags);

        // Audit
        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('festival.updated', 'festival', $id, [
                'name'   => $data['name'] ?? '',
                'status' => $data['status'] ?? '',
            ]);
        }

        flash('success', 'Festival actualizado correctamente.');
        header('Location: ' . festival_admin_url($id . '/edit'));
        exit;
    }

    /**
     * Soft delete a festival.
     */
    public function destroy($id)
    {
        $this->checkPermission('festivals.delete');
        $tenantId = $this->getTenantId();

        $festival = Festival::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$festival) {
            flash('error', 'Festival no encontrado.');
            header('Location: ' . festival_admin_url());
            exit;
        }

        // Soft delete
        $pdo = Database::connect();
        $pdo->prepare("UPDATE festivals SET deleted_at = NOW(), deleted_by = ? WHERE id = ? AND tenant_id = ?")
            ->execute([$_SESSION['admin']['id'] ?? null, $id, $tenantId]);

        if (class_exists(AuditLogger::class)) {
            AuditLogger::log('festival.deleted', 'festival', $id, ['tenant_id' => $tenantId]);
        }

        flash('success', 'Festival eliminado correctamente.');
        header('Location: ' . festival_admin_url());
        exit;
    }

    /**
     * Bulk actions.
     */
    public function bulk()
    {
        $this->checkPermission('festivals.edit');
        $tenantId = $this->getTenantId();

        $action   = $_POST['action'] ?? '';
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            header('Location: ' . festival_admin_url());
            exit;
        }

        $ids = array_map('intval', $selected);
        $pdo = Database::connect();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$tenantId]);

        switch ($action) {
            case 'published':
                $pdo->prepare("UPDATE festivals SET status = 'published', updated_at = NOW() WHERE id IN ({$placeholders}) AND tenant_id = ?")
                    ->execute($params);
                break;
            case 'draft':
                $pdo->prepare("UPDATE festivals SET status = 'draft', updated_at = NOW() WHERE id IN ({$placeholders}) AND tenant_id = ?")
                    ->execute($params);
                break;
            case 'delete':
                $this->checkPermission('festivals.delete');
                $adminId = $_SESSION['admin']['id'] ?? null;
                $pdo->prepare("UPDATE festivals SET deleted_at = NOW(), deleted_by = ? WHERE id IN ({$placeholders}) AND tenant_id = ?")
                    ->execute(array_merge([$adminId], $ids, [$tenantId]));
                break;
        }

        flash('success', 'Acción masiva completada.');
        header('Location: ' . festival_admin_url());
        exit;
    }
}
