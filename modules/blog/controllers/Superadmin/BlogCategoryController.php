<?php

namespace Blog\Controllers\Superadmin;

use Screenart\Musedock\View;
use Blog\Models\BlogCategory;
use Blog\Requests\BlogCategoryRequest;
use Screenart\Musedock\Database;
use Screenart\Musedock\Helpers\FileUploadValidator;
use Screenart\Musedock\Traits\RequiresPermission;

class BlogCategoryController
{
    use RequiresPermission;

    public function index()
    {
        $this->checkPermission('blog.view');
        // Capturamos parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        // Obtener registros por página (10 por defecto, -1 para todos)
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Consulta de las categorías
        $query = BlogCategory::query()
            ->whereNull('tenant_id')
            ->orderBy('order', 'ASC')
            ->orderBy('name', 'ASC');

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (name LIKE ? OR slug LIKE ? OR description LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // Si queremos todos los registros
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
            // Paginamos con el número solicitado
            $pagination = $query->paginate($perPage, $currentPage);
            $categories = $pagination['items'] ?? [];
        }

        // Procesamos los objetos BlogCategory
        $processedCategories = array_map(fn($row) => ($row instanceof BlogCategory) ? $row : new BlogCategory((array) $row), $categories);

        // Renderizamos la vista
        return View::renderSuperadmin('blog.categories.index', [
            'title'       => 'Listado de categorías',
            'categories'  => $processedCategories,
            'search'      => $search,
            'pagination'  => $pagination,
        ]);
    }

    public function create()
    {
        $this->checkPermission('blog.create');
        // Obtener todas las categorías para el selector de categoría padre
        $categories = BlogCategory::whereNull('tenant_id')->orderBy('name', 'ASC')->get();

        return View::renderSuperadmin('blog.categories.create', [
            'title' => 'Crear Categoría',
            'category' => new BlogCategory(),
            'categories' => $categories,
            'isNew' => true,
        ]);
    }

    public function store()
    {
        $this->checkPermission('blog.create');
        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Eliminamos tenant_id del conjunto de datos inicial
        unset($data['tenant_id']);

        // Procesar la subida de imagen si existe
        if ($_FILES && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $uploadResult = $this->processCategoryImageUpload($_FILES['image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.category.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /musedock/blog/categories/create");
                exit;
            }
            $data['image'] = $uploadResult['path'];
        }

        $data = self::processFormData($data);

        $errors = BlogCategoryRequest::validate($data);

        if (!empty($errors)) {
            flash('error', __('blog.category.error_validation', ['errors' => implode('<br>', $errors)]));
            header("Location: /musedock/blog/categories/create");
            exit;
        }

        // Creamos la categoría
        $category = BlogCategory::create($data);

        // Actualizamos específicamente el tenant_id a NULL después de crear
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE blog_categories SET tenant_id = NULL WHERE id = ?");
            $stmt->execute([$category->id]);
        } catch (\Exception $e) {
            error_log("ERROR AL ACTUALIZAR TENANT_ID EN STORE: " . $e->getMessage());
        }

        flash('success', __('blog.category.success_created'));
        header("Location: /musedock/blog/categories/{$category->id}/edit");
        exit;
    }

    public function edit($id)
    {
        $this->checkPermission('blog.edit');
        // Limpiar datos 'old' al inicio
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar la categoría
        $category = BlogCategory::find($id);
        if (!$category) {
            flash('error', __('blog.category.error_not_found'));
            header('Location: /musedock/blog/categories');
            exit;
        }

        // Obtener todas las categorías para el selector de categoría padre (excluyendo la actual)
        $categories = BlogCategory::whereNull('tenant_id')
            ->where('id', '!=', $id)
            ->orderBy('name', 'ASC')
            ->get();

        // Obtener y formatear fechas
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
            error_log("Error al obtener/formatear fechas para categoría {$id}: " . $e->getMessage());
        }

        return View::renderSuperadmin('blog.categories.edit', [
            'title'      => 'Editar categoría: ' . e($category->name),
            'category'   => $category,
            'categories' => $categories,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission('blog.edit');
        // Iniciar sesión si es necesario
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $category = BlogCategory::find($id);
        if (!$category) {
            flash('error', __('blog.category.error_not_found'));
            header("Location: /musedock/blog/categories");
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
                header("Location: /musedock/blog/categories/{$id}/edit");
                exit;
            }
            $data['image'] = $uploadResult['path'];
        } elseif ($removeImage !== '1') {
            $data['image'] = $currentImage;
        }

        // Validación
        $errors = BlogCategoryRequest::validate($rawData, $id);
        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.category.error_validation', ['errors' => implode('<br>', $errors)]));
            header("Location: /musedock/blog/categories/{$id}/edit");
            exit;
        }
        unset($_SESSION['_old_input']);

        // Procesar datos
        $data = self::processFormData($data);
        unset($data['tenant_id']);

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Actualizar datos principales
            $category->update($data);

            // Actualizar tenant_id
            $updateStmt = $pdo->prepare("UPDATE blog_categories SET tenant_id = NULL WHERE id = ?");
            $updateStmt->execute([$id]);

            $pdo->commit();

        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("ERROR en transacción update categoría {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.category.error_update', ['error' => $e->getMessage()]));
            header("Location: /musedock/blog/categories/{$id}/edit");
            exit;
        }

        flash('success', __('blog.category.success_updated'));
        header("Location: /musedock/blog/categories/{$id}/edit");
        exit;
    }

    /**
     * Procesa los datos del formulario antes de guardarlos
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
     * Procesa la subida de imagen de categoría
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

    public function destroy($id)
    {
        $this->checkPermission('blog.delete');
        $category = BlogCategory::find($id);
        if (!$category) {
            flash('error', __('blog.category.error_not_found'));
            header('Location: /musedock/blog/categories');
            exit;
        }

        try {
            $pdo = Database::connect();

            // 1. Verificar si tiene posts asociados
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_post_categories WHERE category_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                flash('error', __('blog.category.error_has_posts'));
                header('Location: /musedock/blog/categories');
                exit;
            }

            // 2. Eliminar imagen si existe
            if (!empty($category->image)) {
                $fileName = basename($category->image);
                $fullPath = APP_ROOT . "/public/assets/uploads/blog/categories/{$fileName}";
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            // 3. Eliminar la categoría
            $category->delete();

            flash('success', __('blog.category.success_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar categoría: " . $e->getMessage());
            flash('error', __('blog.category.error_delete', ['error' => $e->getMessage()]));
        }

        header('Location: /musedock/blog/categories');
        exit;
    }

    public function bulk()
    {
        $this->checkPermission('blog.delete');
        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('blog.category.error_bulk_no_selection'));
            header('Location: /musedock/blog/categories');
            exit;
        }

        if ($action === 'delete') {
            $deletedCount = 0;

            foreach ($selected as $id) {
                $category = BlogCategory::find($id);

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
            header('Location: /musedock/blog/categories');
            exit;
        }

        flash('error', __('blog.category.error_bulk_invalid_action'));
        header('Location: /musedock/blog/categories');
        exit;
    }
}
