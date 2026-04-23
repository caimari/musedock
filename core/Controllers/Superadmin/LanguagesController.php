<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class LanguagesController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        // En /musedock solo deben mostrarse idiomas globales (tenant_id IS NULL)
        // Usamos query raw para ORDER BY múltiple ya que QueryBuilder solo soporta un orderBy
        $pdo = Database::connect();
        $stmt = $pdo->query("
            SELECT languages.*
            FROM languages
            WHERE languages.tenant_id IS NULL
            ORDER BY languages.order_position ASC, languages.id ASC
        ");
        $languages = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderSuperadmin('languages.index', [
            'title' => 'Gestión de Idiomas',
            'languages' => $languages
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        return View::renderSuperadmin('languages.create', [
            'title' => 'Añadir Idioma',
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // En /musedock los idiomas son globales; nunca asignar tenant_id desde este panel
        $data['tenant_id'] = null;

        Database::table('languages')->insert($data);
        flash('success', 'Idioma añadido correctamente.');
        header('Location: /musedock/languages');
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $language = Database::table('languages')
            ->where('id', $id)
            ->whereNull('tenant_id')
            ->first();

        if (!$language) {
            flash('error', 'Idioma no encontrado.');
            header('Location: /musedock/languages');
            exit;
        }

        return View::renderSuperadmin('languages.edit', [
            'title' => 'Editar idioma',
            'language' => $language,
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Manejar checkbox 'active'
        if (!isset($data['active'])) {
            $data['active'] = 0;
        }

        // En /musedock los idiomas son globales; nunca asignar tenant_id desde este panel
        $data['tenant_id'] = null;

        Database::table('languages')
            ->where('id', $id)
            ->whereNull('tenant_id')
            ->update($data);

        flash('success', 'Idioma actualizado.');
        header('Location: /musedock/languages');
        exit;
    }

    public function delete($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        // Verify password
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            flash('error', 'Debes confirmar con tu contraseña.');
            header('Location: /musedock/languages');
            exit;
        }

        // Get current user and verify password
        $auth = SessionSecurity::getAuthenticatedUser();
        $user = Database::table('super_admins')->where('id', $auth['id'])->first();

        if (!$user || !password_verify($password, $user->password)) {
            flash('error', 'Contraseña incorrecta.');
            header('Location: /musedock/languages');
            exit;
        }

        // Check if this is the last language
        $count = Database::table('languages')->whereNull('tenant_id')->count();
        if ($count <= 1) {
            flash('error', 'No se puede eliminar el último idioma.');
            header('Location: /musedock/languages');
            exit;
        }

        Database::table('languages')
            ->where('id', $id)
            ->whereNull('tenant_id')
            ->delete();
        flash('success', 'Idioma eliminado.');
        header('Location: /musedock/languages');
        exit;
    }

    public function toggle($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $lang = Database::table('languages')
            ->where('id', $id)
            ->whereNull('tenant_id')
            ->first();

        if (!$lang) {
            flash('error', 'Idioma no encontrado.');
            header('Location: /musedock/languages');
            exit;
        }

        $newStatus = ($lang->active ?? 0) ? 0 : 1;

        // Check if trying to deactivate the last active language
        if ($newStatus === 0) {
            $activeCount = Database::table('languages')
                ->where('active', 1)
                ->whereNull('tenant_id')
                ->count();

            if ($activeCount <= 1) {
                flash('error', 'No se puede desactivar el último idioma activo.');
                header('Location: /musedock/languages');
                exit;
            }
        }

        Database::table('languages')
            ->where('id', $id)
            ->whereNull('tenant_id')
            ->update(['active' => $newStatus]);

        flash('success', 'Idioma actualizado.');
        header('Location: /musedock/languages');
        exit;
    }

    /**
     * Update language order via AJAX (drag & drop)
     */
    public function updateOrder()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $order = $input['order'] ?? [];

        if (empty($order)) {
            echo json_encode(['success' => false, 'error' => 'No order data provided']);
            exit;
        }

        $pdo = Database::connect();

        foreach ($order as $position => $id) {
            $stmt = $pdo->prepare("UPDATE languages SET order_position = ? WHERE id = ? AND tenant_id IS NULL");
            $stmt->execute([$position, $id]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Set the default/forced language for the site
     */
    public function setDefault()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $forceLang = $_POST['force_lang'] ?? '';

        // Validar que el idioma exista si se ha seleccionado uno
        if (!empty($forceLang)) {
            $langExists = Database::table('languages')
                ->where('code', $forceLang)
                ->where('active', 1)
                ->whereNull('tenant_id')
                ->first();

            if (!$langExists) {
                flash('error', 'El idioma seleccionado no existe o no está activo.');
                header('Location: /musedock/languages');
                exit;
            }
        }

        // Guardar o actualizar el setting force_lang
        $existing = Database::table('settings')->where('key', 'force_lang')->first();

        if ($existing) {
            Database::table('settings')
                ->where('key', 'force_lang')
                ->update(['value' => $forceLang]);
        } else {
            Database::table('settings')->insert([
                'key' => 'force_lang',
                'value' => $forceLang
            ]);
        }

        // Limpiar caché de settings si existe
        if (function_exists('clear_settings_cache')) {
            clear_settings_cache();
        }

        if (empty($forceLang)) {
            flash('success', 'Detección automática de idioma activada.');
        } else {
            flash('success', 'Idioma del sitio forzado a: ' . strtoupper($forceLang));
        }

        header('Location: /musedock/languages');
        exit;
    }

    /**
     * Editor de traducciones (overrides en BD) para contexto superadmin/tenant.
     */
    public function translations()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $context = $this->sanitizeContext($_GET['context'] ?? 'tenant');
        $locale = $this->sanitizeLocale($_GET['locale'] ?? 'es');
        $search = trim((string) ($_GET['q'] ?? ''));
        $availableTenants = $this->getAvailableTenants();
        $selectedTenantId = $this->sanitizeTenantId($_GET['tenant_id'] ?? 0, $availableTenants);
        if ($context !== 'tenant') {
            $selectedTenantId = 0;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 100;

        $availableLocales = $this->getAvailableGlobalLocales();
        if (!isset($availableLocales[$locale])) {
            $firstLocale = array_key_first($availableLocales);
            $locale = $firstLocale ?: 'es';
        }

        $baseTranslations = $this->loadBaseTranslations($context, $locale);
        $baseFlat = $this->flattenTranslations($baseTranslations);

        $overridesError = null;
        try {
            $overrides = $this->getOverridesForScope($context, $locale, $selectedTenantId);
        } catch (\Throwable $e) {
            $overrides = [];
            $overridesError = __('languages.editor.table_missing');
        }

        $allKeys = array_values(array_unique(array_merge(
            array_keys($baseFlat),
            array_keys($overrides)
        )));
        sort($allKeys, SORT_STRING);

        $searchLower = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
        $rows = [];
        foreach ($allKeys as $key) {
            $baseValue = array_key_exists($key, $baseFlat) ? (string) $baseFlat[$key] : null;
            $hasOverride = array_key_exists($key, $overrides);
            $overrideValue = $hasOverride ? (string) $overrides[$key] : '';
            $effectiveValue = $hasOverride ? $overrideValue : ($baseValue ?? '');

            if ($searchLower !== '') {
                $haystackSource = $key . ' ' . $effectiveValue . ' ' . ($baseValue ?? '');
                $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystackSource) : strtolower($haystackSource);
                if (strpos($haystack, $searchLower) === false) {
                    continue;
                }
            }

            $rows[] = [
                'key' => $key,
                'base_value' => $baseValue,
                'override_value' => $overrideValue,
                'effective_value' => $effectiveValue,
                'is_overridden' => $hasOverride,
            ];
        }

        $totalFiltered = count($rows);
        $totalPages = max(1, (int) ceil($totalFiltered / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);

        return View::renderSuperadmin('languages.translations', [
            'title' => __('languages.editor.title'),
            'context' => $context,
            'locale' => $locale,
            'search' => $search,
            'selectedTenantId' => $selectedTenantId,
            'availableTenants' => $availableTenants,
            'availableLocales' => $availableLocales,
            'items' => $items,
            'totalKeys' => count($allKeys),
            'overriddenCount' => count($overrides),
            'filteredCount' => $totalFiltered,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'total' => $totalFiltered,
            ],
            'baseFileExists' => $this->baseTranslationFileExists($context, $locale),
            'overridesError' => $overridesError,
            'returnTo' => $this->sanitizeReturnTo($_SERVER['REQUEST_URI'] ?? '/musedock/languages/translations'),
        ]);
    }

    /**
     * Guardar/actualizar override por ámbito (global o tenant específico).
     */
    public function saveTranslationOverride()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $context = $this->sanitizeContext($_POST['context'] ?? 'tenant');
        $locale = $this->sanitizeLocale($_POST['locale'] ?? 'es');
        $availableTenants = $this->getAvailableTenants();
        $selectedTenantId = $this->sanitizeTenantId($_POST['tenant_id'] ?? 0, $availableTenants);
        if ($context !== 'tenant') {
            $selectedTenantId = 0;
        }
        $key = trim((string) ($_POST['translation_key'] ?? ''));
        $value = (string) ($_POST['translation_value'] ?? '');
        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? '/musedock/languages/translations');

        if (!$this->isValidTranslationKey($key)) {
            flash('error', __('languages.editor.invalid_key'));
            header('Location: ' . $returnTo);
            exit;
        }

        if (trim($value) === '') {
            $this->deleteOverride($context, $locale, $selectedTenantId, $key, $returnTo);
            return;
        }

        try {
            $pdo = Database::connect();
            $now = date('Y-m-d H:i:s');

            $selectStmt = $pdo->prepare("
                SELECT id
                FROM translation_overrides
                WHERE context = :context
                  AND locale = :locale
                  AND tenant_id = :tenant_id
                  AND translation_key = :translation_key
                LIMIT 1
            ");
            $selectStmt->execute([
                'context' => $context,
                'locale' => $locale,
                'tenant_id' => $selectedTenantId,
                'translation_key' => $key,
            ]);
            $existingId = (int) $selectStmt->fetchColumn();

            if ($existingId > 0) {
                $updateStmt = $pdo->prepare("
                    UPDATE translation_overrides
                    SET translation_value = :translation_value, updated_at = :updated_at
                    WHERE id = :id
                ");
                $updateStmt->execute([
                    'translation_value' => $value,
                    'updated_at' => $now,
                    'id' => $existingId,
                ]);
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO translation_overrides
                        (tenant_id, context, locale, translation_key, translation_value, created_at, updated_at)
                    VALUES
                        (:tenant_id, :context, :locale, :translation_key, :translation_value, :created_at, :updated_at)
                ");
                $insertStmt->execute([
                    'tenant_id' => $selectedTenantId,
                    'context' => $context,
                    'locale' => $locale,
                    'translation_key' => $key,
                    'translation_value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            flash('success', __('languages.editor.saved'));
        } catch (\Throwable $e) {
            flash('error', __('languages.editor.save_error'));
        }

        header('Location: ' . $returnTo);
        exit;
    }

    /**
     * Eliminar override del ámbito seleccionado para volver al valor base.
     */
    public function resetTranslationOverride()
    {
        SessionSecurity::startSession();
        $this->checkPermission('languages.manage');

        $context = $this->sanitizeContext($_POST['context'] ?? 'tenant');
        $locale = $this->sanitizeLocale($_POST['locale'] ?? 'es');
        $availableTenants = $this->getAvailableTenants();
        $selectedTenantId = $this->sanitizeTenantId($_POST['tenant_id'] ?? 0, $availableTenants);
        if ($context !== 'tenant') {
            $selectedTenantId = 0;
        }
        $key = trim((string) ($_POST['translation_key'] ?? ''));
        $returnTo = $this->sanitizeReturnTo($_POST['return_to'] ?? '/musedock/languages/translations');

        if (!$this->isValidTranslationKey($key)) {
            flash('error', __('languages.editor.invalid_key'));
            header('Location: ' . $returnTo);
            exit;
        }

        $this->deleteOverride($context, $locale, $selectedTenantId, $key, $returnTo);
    }

    private function deleteOverride(string $context, string $locale, int $tenantId, string $key, string $returnTo): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                DELETE FROM translation_overrides
                WHERE context = :context
                  AND locale = :locale
                  AND tenant_id = :tenant_id
                  AND translation_key = :translation_key
            ");
            $stmt->execute([
                'context' => $context,
                'locale' => $locale,
                'tenant_id' => $tenantId,
                'translation_key' => $key,
            ]);

            flash('success', __('languages.editor.reset_done'));
        } catch (\Throwable $e) {
            flash('error', __('languages.editor.reset_error'));
        }

        header('Location: ' . $returnTo);
        exit;
    }

    private function sanitizeContext($context): string
    {
        $context = is_string($context) ? trim(strtolower($context)) : 'tenant';
        if (!in_array($context, ['superadmin', 'tenant'], true)) {
            return 'tenant';
        }
        return $context;
    }

    private function sanitizeLocale($locale): string
    {
        $locale = is_string($locale) ? trim(strtolower($locale)) : 'es';
        if (!preg_match('/^[a-z]{2,10}([_-][a-z0-9]{2,10})?$/i', $locale)) {
            return 'es';
        }
        return $locale;
    }

    private function sanitizeReturnTo($returnTo): string
    {
        if (!is_string($returnTo) || $returnTo === '' || $returnTo[0] !== '/') {
            return '/musedock/languages/translations';
        }
        return $returnTo;
    }

    private function isValidTranslationKey(string $key): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9._-]{2,255}$/', $key);
    }

    private function sanitizeTenantId($tenantId, array $availableTenants): int
    {
        if (!is_numeric($tenantId)) {
            return 0;
        }

        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            return 0;
        }

        return isset($availableTenants[$tenantId]) ? $tenantId : 0;
    }

    private function getAvailableGlobalLocales(): array
    {
        $locales = [];

        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("
                SELECT code, name
                FROM languages
                WHERE tenant_id IS NULL
                ORDER BY order_position ASC, id ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $code = trim((string) ($row['code'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                if ($code !== '') {
                    $locales[$code] = $name !== '' ? $name : strtoupper($code);
                }
            }
        } catch (\Throwable $e) {}

        if (empty($locales)) {
            $locales = [
                'es' => 'Español',
                'en' => 'English',
            ];
        }

        return $locales;
    }

    private function getAvailableTenants(): array
    {
        $tenants = [];

        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("
                SELECT id, name, domain
                FROM tenants
                WHERE status = 'active'
                ORDER BY name ASC
            ");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $tenants[$id] = [
                    'id' => $id,
                    'name' => trim((string) ($row['name'] ?? 'Tenant ' . $id)),
                    'domain' => trim((string) ($row['domain'] ?? '')),
                ];
            }
        } catch (\Throwable $e) {}

        return $tenants;
    }

    private function baseTranslationFileExists(string $context, string $locale): bool
    {
        $path = __DIR__ . "/../../../lang/{$context}/{$locale}.json";
        return file_exists($path);
    }

    private function loadBaseTranslations(string $context, string $locale): array
    {
        $path = __DIR__ . "/../../../lang/{$context}/{$locale}.json";
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $flat = [];

        foreach ($translations as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $fullKey = $prefix === '' ? $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenTranslations($value, $fullKey));
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flat[$fullKey] = (string) $value;
            }
        }

        return $flat;
    }

    private function getOverridesForScope(string $context, string $locale, int $tenantId = 0): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT translation_key, translation_value
            FROM translation_overrides
            WHERE context = :context
              AND locale = :locale
              AND tenant_id = :tenant_id
            ORDER BY translation_key ASC
        ");
        $stmt->execute([
            'context' => $context,
            'locale' => $locale,
            'tenant_id' => $tenantId,
        ]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $overrides = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['translation_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $overrides[$key] = (string) ($row['translation_value'] ?? '');
        }

        return $overrides;
    }
}
