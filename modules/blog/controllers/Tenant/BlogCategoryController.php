<?php

namespace Blog\Controllers\Tenant;

use Screenart\Musedock\View;
use Blog\Models\BlogCategory;
use Blog\Requests\BlogCategoryRequest;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;
use Screenart\Musedock\Helpers\FileUploadValidator;
use Screenart\Musedock\Cache\HtmlCache;
use Screenart\Musedock\Traits\RequiresPermission;

class BlogCategoryController
{
    use RequiresPermission;

    private function updateAllCategoryCounts(int $tenantId): void
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                UPDATE blog_categories c
                SET post_count = (
                    SELECT COUNT(*)
                    FROM blog_post_categories pc
                    WHERE pc.category_id = c.id
                )
                WHERE c.tenant_id = ?
            ");
            $stmt->execute([$tenantId]);
        } catch (\Exception $e) {
            error_log("Error al actualizar contadores de categorías: " . $e->getMessage());
        }
    }

    private function flashValidationErrors(array $errors): void
    {
        $errors = array_values(array_filter(array_map('trim', $errors)));
        $message = !empty($errors) ? implode("\n", $errors) : 'Error de validación.';
        flash('error', $message);
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        $sqlState = method_exists($e, 'getCode') ? (string) $e->getCode() : '';
        if ($sqlState === '23505' || $sqlState === '23000') {
            return true;
        }
        $message = $e->getMessage();
        return stripos($message, 'duplicate key value') !== false
            || stripos($message, 'unique constraint') !== false
            || stripos($message, 'Duplicate entry') !== false;
    }

    private function flashDuplicateSlug(): void
    {
        flash('error', 'El slug ya está en uso.');
    }

    /**
     * Verificar si el usuario actual tiene un permiso específico
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __('blog.category.error_no_permission'));
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    /**
     * Listado de categorías del tenant
     */
    public function index()
    {
        $this->checkPermission('blog.categories.view');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.category.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        // Mantener contadores consistentes aunque se hayan cambiado posts desde otro flujo
        $this->updateAllCategoryCounts($tenantId);

        // Capturar parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Consulta de categorías SOLO del tenant actual
        $query = BlogCategory::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('order', 'ASC')
            ->orderBy('name', 'ASC');

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (name LIKE ? OR slug LIKE ? OR description LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // Paginación
        if ($perPage == -1) {
            $categories = $query->get();
            $pagination = [
                'total' => count($categories),
                'per_page' => count($categories),
                'current_page' => 1,
                'last_page' => 1,
                'from' => 1,
                'to' => count($categories),
                'items' => $categories
            ];
        } else {
            $pagination = $query->paginate($perPage, $currentPage);
            $categories = $pagination['items'] ?? [];
        }

        // Procesar categorías
        $processedCategories = array_map(fn($row) => ($row instanceof BlogCategory) ? $row : new BlogCategory((array) $row), $categories);

        return View::renderTenantAdmin('blog.categories.index', [
            'title'       => 'Listado de categorías',
            'categories'  => $processedCategories,
            'search'      => $search,
            'pagination'  => $pagination,
        ]);
    }

    /**
     * Formulario para crear nueva categoría
     */
    public function create()
    {
        $this->checkPermission('blog.categories.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.category.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        // Obtener todas las categorías del tenant para el selector de categoría padre
        $categories = BlogCategory::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        return View::renderTenantAdmin('blog.categories.create', [
            'title' => 'Crear Categoría',
            'category' => new BlogCategory(),
            'categories' => $categories,
            'isNew' => true,
        ]);
    }

    /**
     * Guardar nueva categoría
     */
    public function store()
    {
        $this->checkPermission('blog.categories.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.category.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Asignar tenant_id actual
        $data['tenant_id'] = $tenantId;

        // Procesar la subida de imagen si existe
        if ($_FILES && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadResult = $this->processCategoryImageUpload($_FILES['image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.category.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /" . admin_path() . "/blog/categories/create");
                exit;
            }
            $data['image'] = $uploadResult['path'];
        }

        $data = self::processFormData($data);

        $errors = BlogCategoryRequest::validate($data);

        if (!empty($errors)) {
            $this->flashValidationErrors($errors);
            header("Location: /" . admin_path() . "/blog/categories/create");
            exit;
        }

        // Crear la categoría
        try {
            $category = BlogCategory::create($data);
        } catch (\Throwable $e) {
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /" . admin_path() . "/blog/categories/create");
                exit;
            }
            throw $e;
        }

        HtmlCache::onTaxonomySaved($tenantId);

        flash('success', __('blog.category.success_created'));
        header("Location: /" . admin_path() . "/blog/categories/{$category->id}/edit");
        exit;
    }

    /**
     * Formulario para editar categoría
     */
    public function edit($id)
    {
        $this->checkPermission('blog.categories.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.category.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        // Limpiar datos 'old'
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar categoría SOLO del tenant actual
        $category = BlogCategory::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$category) {
            flash('error', __('blog.category.error_not_found_or_no_permission'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        // Obtener todas las categorías del tenant para el selector de categoría padre (excluyendo la actual)
        $categories = BlogCategory::where('tenant_id', $tenantId)
            ->where('id', '!=', $id)
            ->orderBy('name', 'ASC')
            ->get();

        // Formatear fechas
        $category->created_at_formatted = 'Desconocido';
        $category->updated_at_formatted = 'Desconocido';

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT created_at, updated_at FROM blog_categories WHERE id = ?");
            $stmt->execute([$id]);
            $dates = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($dates) {
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;

                if (!empty($dates['created_at'])) {
                    $timestamp_created = strtotime($dates['created_at']);
                    if ($timestamp_created !== false) {
                        $category->created_at_formatted = date($dateTimeFormat, $timestamp_created);
                    }
                }

                if (!empty($dates['updated_at'])) {
                    $timestamp_updated = strtotime($dates['updated_at']);
                    if ($timestamp_updated !== false) {
                        $category->updated_at_formatted = date($dateTimeFormat, $timestamp_updated);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error al formatear fechas: " . $e->getMessage());
        }

        return View::renderTenantAdmin('blog.categories.edit', [
            'title'      => 'Editar categoría: ' . e($category->name),
            'category'   => $category,
            'categories' => $categories,
        ]);
    }

    /**
     * Actualizar categoría
     */
    public function update($id)
    {
        $this->checkPermission('blog.categories.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.category.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // Buscar categoría SOLO del tenant actual
        $category = BlogCategory::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$category) {
            flash('error', __('blog.category.error_not_found_or_no_permission'));
            header("Location: /" . admin_path() . "/blog/categories");
            exit;
        }

        $rawData = $_POST;
        $data = $rawData;
        unset($data['_token'], $data['_csrf'], $data['_method']);

        // Procesar imagen
        $currentImage = $data['current_image'] ?? null;
        $removeImage = $data['remove_image'] ?? '0';
        unset($data['current_image'], $data['remove_image']);

        // Si marca eliminar imagen
        if ($removeImage === '1' && !empty($currentImage)) {
            $fileName = basename($currentImage);
            $fullPath = APP_ROOT . "/public/assets/uploads/blog/categories/{$fileName}";

            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $data['image'] = null;
        }

        // Procesar la nueva imagen si se sube una
        if ($_FILES && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadResult = $this->processCategoryImageUpload($_FILES['image'], $currentImage);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.category.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /" . admin_path() . "/blog/categories/{$id}/edit");
                exit;
            }
            $data['image'] = $uploadResult['path'];
        } elseif ($removeImage !== '1') {
            $data['image'] = $currentImage;
        }

        // Asegurar que tenant_id no cambie
        $data['tenant_id'] = $tenantId;

        // Validación (usar datos procesados para validar el slug real)
        $validationData = self::processFormData($data);
        $errors = BlogCategoryRequest::validate($validationData, $id);
        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            $this->flashValidationErrors($errors);
            header("Location: /" . admin_path() . "/blog/categories/{$id}/edit");
            exit;
        }
        unset($_SESSION['_old_input']);

        // Procesar datos
        $data = $validationData;

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Actualizar datos principales
            $category->update($data);

            $pdo->commit();

        } catch (\Throwable $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            if ($this->isUniqueViolation($e)) {
                $this->flashDuplicateSlug();
                header("Location: /" . admin_path() . "/blog/categories/{$id}/edit");
                exit;
            }
            error_log("ERROR en transacción update categoría {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.category.error_update', ['error' => $e->getMessage()]));
            header("Location: /" . admin_path() . "/blog/categories/{$id}/edit");
            exit;
        }

        HtmlCache::onTaxonomySaved($tenantId);

        flash('success', __('blog.category.success_updated'));
        header("Location: /" . admin_path() . "/blog/categories/{$id}/edit");
        exit;
    }

    /**
     * Eliminar categoría
     */
    public function destroy($id)
    {
        $this->checkPermission('blog.categories.delete');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.category.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        // Buscar categoría SOLO del tenant actual
        $category = BlogCategory::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$category) {
            flash('error', __('blog.category.error_not_found_or_no_permission'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        try {
            $pdo = Database::connect();

            // Verificar si tiene posts asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_categories WHERE category_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                flash('error', __('blog.category.error_has_posts'));
                header('Location: /' . admin_path() . '/blog/categories');
                exit;
            }

            // Eliminar imagen si existe
            if (!empty($category->image)) {
                $fileName = basename($category->image);
                $fullPath = APP_ROOT . "/public/assets/uploads/blog/categories/{$fileName}";
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // Eliminar la categoría
            $category->delete();

            HtmlCache::onTaxonomySaved($tenantId);

            flash('success', __('blog.category.success_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar categoría: " . $e->getMessage());
            flash('error', __('blog.category.error_delete', ['error' => $e->getMessage()]));
        }

        header('Location: /' . admin_path() . '/blog/categories');
        exit;
    }

    /**
     * Acciones masivas
     */
    public function bulk()
    {
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.category.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('blog.category.error_bulk_no_selection'));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        // Verificar permisos según la acción
        if ($action === 'delete') {
            $this->checkPermission('blog.categories.delete');
        }

        if ($action === 'delete') {
            $deletedCount = 0;

            foreach ($selected as $id) {
                // Verificar que la categoría pertenezca al tenant
                $category = BlogCategory::where('id', $id)
                            ->where('tenant_id', $tenantId)
                            ->first();

                if ($category) {
                    try {
                        $pdo = Database::connect();

                        // Verificar si tiene posts asociados
                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_categories WHERE category_id = ?");
                        $stmt->execute([$id]);
                        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($result['count'] > 0) {
                            continue; // Saltar esta categoría
                        }

                        // Eliminar imagen
                        if (!empty($category->image)) {
                            $fileName = basename($category->image);
                            $fullPath = APP_ROOT . "/public/assets/uploads/blog/categories/{$fileName}";
                            if (file_exists($fullPath)) {
                                @unlink($fullPath);
                            }
                        }

                        // Eliminar categoría
                        $category->delete();
                        $deletedCount++;
                    } catch (\Exception $e) {
                        error_log("Error al eliminar categoría #{$id}: " . $e->getMessage());
                        continue;
                    }
                }
            }

            flash('success', __('blog.category.success_bulk_deleted', ['count' => $deletedCount]));
            header('Location: /' . admin_path() . '/blog/categories');
            exit;
        }

        flash('error', __('blog.category.error_bulk_invalid_action'));
        header('Location: /' . admin_path() . '/blog/categories');
        exit;
    }

    /**
     * Procesar datos del formulario
     */
    private static function processFormData($data)
    {
        // Gestionar campos opcionales
        $optionalFields = ['description', 'seo_title', 'seo_description', 'seo_keywords', 'parent_id'];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // Asegurar que order sea un número
        if (!isset($data['order']) || $data['order'] === '') {
            $data['order'] = 0;
        }

        return $data;
    }

    /**
     * Procesar subida de imagen de categoría
     */
    private function processCategoryImageUpload($file, $currentImage = null)
    {
        // 🔒 SECURITY: Validación completa de archivos subidos
        $validation = FileUploadValidator::validateImage($file);
        if (!$validation['valid']) {
            return ['error' => $validation['error']];
        }

        $fileInfo = getimagesize($file['tmp_name']);
        if ($fileInfo === false) {
            return ['error' => 'El archivo no es una imagen válida.'];
        }

        $targetWidth = 400;
        $targetHeight = 400;

        $uploadDir = APP_ROOT . '/public/assets/uploads/blog/categories/';
        $relativePath = 'uploads/blog/categories/';

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => "Error al crear el directorio: " . $uploadDir];
            }
        }

        // Generar nombre seguro usando el helper
        $filename = FileUploadValidator::generateSecureFilename($validation['extension'], 'category');
        $fullPath = $uploadDir . $filename;
        $extension = $validation['extension'];

        $isUnsupportedFormat = !in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        if ($isUnsupportedFormat) {
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                return ['error' => "Error al mover el archivo subido"];
            }
        } else {
            try {
                switch ($extension) {
                    case 'jpg':
                    case 'jpeg':
                        $sourceImage = imagecreatefromjpeg($file['tmp_name']);
                        break;
                    case 'png':
                        $sourceImage = imagecreatefrompng($file['tmp_name']);
                        break;
                    case 'gif':
                        $sourceImage = imagecreatefromgif($file['tmp_name']);
                        break;
                    case 'webp':
                        $sourceImage = imagecreatefromwebp($file['tmp_name']);
                        break;
                }

                $sourceWidth = imagesx($sourceImage);
                $sourceHeight = imagesy($sourceImage);

                $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

                if ($extension == 'png') {
                    imagealphablending($targetImage, false);
                    imagesavealpha($targetImage, true);
                    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                    imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
                }

                $sourceRatio = $sourceWidth / $sourceHeight;
                $targetRatio = $targetWidth / $targetHeight;

                if ($sourceRatio > $targetRatio) {
                    $newHeight = $sourceHeight;
                    $newWidth = $sourceHeight * $targetRatio;
                    $srcX = ($sourceWidth - $newWidth) / 2;
                    $srcY = 0;
                } else {
                    $newWidth = $sourceWidth;
                    $newHeight = $sourceWidth / $targetRatio;
                    $srcX = 0;
                    $srcY = ($sourceHeight - $newHeight) / 2;
                }

                imagecopyresampled(
                    $targetImage, $sourceImage,
                    0, 0, $srcX, $srcY,
                    $targetWidth, $targetHeight, $newWidth, $newHeight
                );

                switch ($extension) {
                    case 'jpg':
                    case 'jpeg':
                        imagejpeg($targetImage, $fullPath, 90);
                        break;
                    case 'png':
                        imagepng($targetImage, $fullPath, 9);
                        break;
                    case 'gif':
                        imagegif($targetImage, $fullPath);
                        break;
                    case 'webp':
                        imagewebp($targetImage, $fullPath, 90);
                        break;
                }

                imagedestroy($sourceImage);
                imagedestroy($targetImage);

            } catch (\Exception $e) {
                return ['error' => "Error al procesar la imagen: " . $e->getMessage()];
            }
        }

        if ($currentImage && !empty($currentImage)) {
            $oldPath = APP_ROOT . '/public/' . $currentImage;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        return ['path' => $relativePath . $filename];
    }
}
