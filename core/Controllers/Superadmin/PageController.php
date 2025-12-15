<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Models\Page;
use Screenart\Musedock\Models\PageMeta;
use Screenart\Musedock\Models\SuperAdmin;
use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Requests\PageRequest;
use Screenart\Musedock\Models\PageTranslation;
use Screenart\Musedock\Database;
use Carbon\Carbon;

class PageController
{
    /**
     * Verificar si el usuario actual tiene un permiso específico
     * Si no lo tiene, redirige con mensaje de error
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __('pages.error_no_permission'));
            header('Location: /musedock/dashboard');
            exit;
        }
    }

public function index()
{
    // Verificar permiso de visualización
    $this->checkPermission('pages.view');

    // Capturamos parámetros de búsqueda y paginación
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    // Limitar longitud de búsqueda a 255 caracteres
    if (strlen($search) > 255) {
        $search = substr($search, 0, 255);
    }

    // Obtener registros por página (10 por defecto, -1 para todos)
    $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
    $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    
    // Consulta de las páginas
    $query = Page::query()
        ->whereNull('tenant_id') // Asumiendo que las páginas del superadmin no tienen tenant
        ->whereRaw("(status != ? OR status IS NULL)", ['trash'])
        ->orderBy('updated_at', 'DESC');
    
    // Aplicar búsqueda si existe
    if (!empty($search)) {
        $searchTerm = "%{$search}%";
        $query->whereRaw("
            (title LIKE ? OR slug LIKE ? OR content LIKE ?)
        ", [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // Si queremos todos los registros (con límite de seguridad)
    $maxLimit = 1000;
    if ($perPage == -1) {
        $totalCount = $query->count();
        if ($totalCount > $maxLimit) {
            flash('warning', str_replace(['{totalCount}', '{maxLimit}'], [$totalCount, $maxLimit], __('pages.warning_limit_results')));
            $pages = $query->limit($maxLimit)->get();
        } else {
            $pages = $query->get();
        }
        $pagination = [
            'total' => count($pages),
            'per_page' => count($pages),
            'current_page' => 1,
            'last_page' => 1,
            'from' => 1,
            'to' => count($pages),
            'items' => $pages
        ];
    } else {
        // Paginamos con el número solicitado
        $pagination = $query->paginate($perPage, $currentPage);
        $pages = $pagination['items'] ?? [];
    }
    
    // Procesamos los objetos Page y cargamos datos adicionales
    $processedPages = [];
    
    // Cargar datos de visibilidad y meta
    try {
        $pdo = \Screenart\Musedock\Database::connect();
        
        foreach ($pages as $pageData) {
            // Crear el objeto Page (verificar si ya es instancia de Page)
            $page = ($pageData instanceof Page) ? $pageData : new Page((array) $pageData);
            
            // Cargar visibilidad
            $stmt = $pdo->prepare("SELECT visibility FROM pages WHERE id = ? LIMIT 1");
            $stmt->execute([$page->id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result && isset($result['visibility'])) {
                $page->visibility = $result['visibility'];
            }
            
            // Cargar meta (plantilla)
            $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM page_meta WHERE page_id = ? AND meta_key = 'page_template'");
            $stmt->execute([$page->id]);
            $metaResult = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($metaResult) {
                $page->meta = $page->meta ?? new \stdClass();
                $page->meta->page_template = $metaResult['meta_value'];
            }
            
            $processedPages[] = $page;
        }
    } catch (\Exception $e) {
        // Si falla la consulta, usar los datos originales
        error_log("Error al cargar datos adicionales: " . $e->getMessage());
        $processedPages = array_map(fn($row) => ($row instanceof Page) ? $row : new Page((array) $row), $pages);
    }
    
    // Precargamos autores
    $authors = [];
    foreach ($processedPages as $page) {
        $userId = $page->user_id ?? null;
        if ($userId && !isset($authors[$userId])) {
            // Asumiendo que el autor siempre es SuperAdmin en este contexto
            $authors[$userId] = SuperAdmin::find($userId);
        }
    }
    
    // Buscamos la página marcada como inicio
    $homepageId = Page::where('is_homepage', 1)
                      ->whereNull('tenant_id')
                      ->value('id');
    
    // Renderizamos la vista
    return View::renderSuperadmin('pages.index', [
        'title'       => 'Listado de páginas',
        'pages'       => $processedPages,
        'authors'     => $authors,
        'search'      => $search,
        'pagination'  => $pagination,
        'homepageId'  => $homepageId,
    ]);
}
public function create()
{
    $this->checkPermission('pages.create');
    // === NUEVO: Obtener plantillas disponibles ===
    $availableTemplates = get_page_templates(); // Helper que escanea las plantillas disponibles
    $currentPageTemplate = 'page.blade.php'; // Plantilla por defecto para nuevas páginas
    // ==============================================

    return View::renderSuperadmin('pages.create', [
        'title' => 'Crear Página',
        'Page'  => new Page(),
        'isNew' => true, // Para identificar que es una página nueva
        'baseUrl' => $_SERVER['HTTP_HOST'], // Para mostrar la URL base
        'availableTemplates' => $availableTemplates,    // Lista de plantillas disponibles
        'currentPageTemplate' => $currentPageTemplate,  // Plantilla por defecto
    ]);
}
public function store()
{
    $this->checkPermission('pages.create');
    $data = $_POST;
    unset($data['_token'], $data['_csrf']);
    
    // Eliminamos tenant_id del conjunto de datos inicial
    unset($data['tenant_id']);
    
    // Seteamos otros valores automáticos
    $data['user_id'] = $_SESSION['super_admin']['id'] ?? null;
    $data['user_type'] = 'superadmin';
    
    // Manejo de checkboxes
    $data['show_slider'] = isset($data['show_slider']) ? 1 : 0;
    $data['hide_title'] = isset($data['hide_title']) ? 1 : 0;
    
    // Manejo de visibilidad (con valor por defecto)
    $data['visibility'] = $data['visibility'] ?? 'public';
    
    // Validar que visibility sea uno de los valores permitidos
    if (!in_array($data['visibility'], ['public', 'private', 'members'])) {
        $data['visibility'] = 'public';
    }
    
    // Procesar la subida de imagen del slider si existe
    if ($_FILES && isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] == 0) {
        $uploadResult = $this->processSliderImageUpload($_FILES['slider_image']);
        if (isset($uploadResult['error'])) {
            flash('error', __('pages.error_image_upload') . ': ' . $uploadResult['error']);
            header("Location: /musedock/pages/create");
            exit;
        }
        $data['slider_image'] = $uploadResult['path'];
    }
    
    // Establecer el valor predeterminado de status a 'published' si no se especifica
    if (!isset($data['status']) || !in_array($data['status'], ['draft', 'published'])) {
        $data['status'] = 'published';
    }
    
    $data = self::processFormData($data);
    
    $errors = PageRequest::validate($data);
    
    if (!empty($errors)) {
        flash('error', implode('<br>', $errors));
        header("Location: /musedock/pages/create");
        exit;
    }
    
    // Verificar si es la primera página (sin homepage existente)
    $pdo = \Screenart\Musedock\Database::connect();
    $checkHomepage = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE is_homepage = 1 AND tenant_id IS NULL");
    $checkHomepage->execute();
    $hasHomepage = (int)$checkHomepage->fetchColumn() > 0;

    // Si no hay homepage, esta será la primera y se marcará automáticamente
    $isFirstPage = !$hasHomepage;

    // Creamos la página con los datos normales
    $page = Page::create($data);

    // Si es la primera página, marcarla como homepage automáticamente
    if ($isFirstPage) {
        try {
            $setHomepage = $pdo->prepare("UPDATE pages SET is_homepage = 1 WHERE id = ?");
            $setHomepage->execute([$page->id]);
        } catch (\Exception $e) {
            error_log("Error al establecer primera página como homepage: " . $e->getMessage());
        }
    }

    // Actualizamos específicamente el tenant_id a NULL después de crear
    try {
        $pdo = \Screenart\Musedock\Database::connect();
        $stmt = $pdo->prepare("UPDATE pages SET tenant_id = NULL WHERE id = ?");
        $stmt->execute([$page->id]);
    } catch (\Exception $e) {
        // Log error pero continuamos
        file_put_contents(__DIR__ . '/../../../storage/logs/debug.log', 
            "ERROR AL ACTUALIZAR TENANT_ID EN STORE: " . $e->getMessage() . "\n", 
            FILE_APPEND);
    }
    
    // Usamos SQL directo para crear el slug con tenant_id NULL
    try {
        // Primero eliminamos cualquier slug existente por si acaso
        $pdo = \Screenart\Musedock\Database::connect();
        $deleteStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'pages' AND reference_id = ?");
        $deleteStmt->execute([$page->id]);
        
        // Creamos el nuevo slug directamente con SQL para garantizar tenant_id NULL
        $prefix = $data['prefix'] ?? 'p';
        $insertStmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, NULL, ?)");
        $insertStmt->execute(['pages', $page->id, $data['slug'], $prefix]);
        
    } catch (\Exception $e) {
        // Log error pero continuamos
        file_put_contents(__DIR__ . '/../../../storage/logs/debug.log', 
            "ERROR AL CREAR SLUG CON TENANT_ID NULL: " . $e->getMessage() . "\n", 
            FILE_APPEND);
    }

    // ✅ Crear primera revisión de la página
    try {
        \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'initial', 'Versión inicial de la página');
    } catch (\Exception $e) {
        error_log("Error al crear revisión inicial: " . $e->getMessage());
    }

    flash('success', __('pages.success_created'));

    // Redirigir a la página de edición en lugar del listado
    header("Location: /musedock/pages/{$page->id}/edit");
    exit;
}
	
public function edit($id)
{
    $this->checkPermission('pages.edit');
    // Limpiar datos 'old' al inicio
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    unset($_SESSION['_old_input']);

    // Buscar la página
    $page = Page::find($id);
    if (!$page) {
        flash('error', __('pages.error_not_found'));
        header('Location: /musedock/pages');
        exit;
    }

    // --- Obtener el campo de visibility si existe o establecer valor predeterminado ---
    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT visibility FROM pages WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result && isset($result['visibility'])) {
            $page->visibility = $result['visibility'];
        } else {
            // Si el campo no existe todavía en la BD o es NULL, establecer el valor predeterminado
            $page->visibility = 'public';
        }
    } catch (\Exception $e) {
        error_log("Error al obtener visibility para página {$id}: " . $e->getMessage());
        $page->visibility = 'public'; // Valor predeterminado
    }

    // --- Obtener y formatear fechas ---
    $page->created_at_formatted = 'Desconocido';
    $page->updated_at_formatted = 'Desconocido';
    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT created_at, updated_at FROM pages WHERE id = ?");
        $stmt->execute([$id]);
        $dates = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($dates) {
            $dateFormat = setting('date_format', 'd/m/Y');
            $timeFormat = setting('time_format', 'H:i');
            $dateTimeFormat = $dateFormat . ' ' . $timeFormat;
            if (!empty($dates['created_at'])) {
                $timestamp_created = strtotime($dates['created_at']);
                if ($timestamp_created !== false) {
                    $page->created_at_formatted = date($dateTimeFormat, $timestamp_created);
                }
            }
            if (!empty($dates['updated_at'])) {
                $timestamp_updated = strtotime($dates['updated_at']);
                if ($timestamp_updated !== false) {
                    $page->updated_at_formatted = date($dateTimeFormat, $timestamp_updated);
                }
            }
        }
    } catch (\Exception $e) {
        error_log("Error al obtener/formatear fechas para página {$id}: " . $e->getMessage());
    }

    // --- Preparar published_at ---
    if ($page->published_at && !$page->published_at instanceof \DateTimeInterface) {
        try {
            $page->published_at = new \DateTime($page->published_at);
        } catch (\Exception $e) {
            $page->published_at = null;
        }
    }

    // --- Traducciones ---
    $locales = getAvailableLocales();
    $translatedLocales = [];
    $translations = PageTranslation::where('page_id', $id)->get();
    foreach ($translations as $t) {
        $translatedLocales[$t->locale] = true;
    }

    // === NUEVO: Plantillas ===
    $availableTemplates = get_page_templates(); // Helper que escanea las plantillas disponibles
    $currentPageTemplate = PageMeta::getMeta($id, 'page_template', 'page.blade.php'); // Plantilla actual o por defecto

    // --- Renderizar vista ---
    return View::renderSuperadmin('pages.edit', [
        'title'               => 'Editar página: ' . e($page->title),
        'Page'                => $page,
        'locales'             => $locales,
        'translatedLocales'   => $translatedLocales,
        'baseUrl'             => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'availableTemplates'  => $availableTemplates,    // Lista de plantillas disponibles
        'currentPageTemplate' => $currentPageTemplate,   // Plantilla actual seleccionada
    ]);
}

public function update($id)
{
    $this->checkPermission('pages.edit');
    // Iniciar sesión si es necesario
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    $page = Page::find($id);
    if (!$page) {
        flash('error', __('pages.error_not_found'));
        header("Location: /musedock/pages");
        exit;
    }

    // Guardar estado anterior para detectar cambios
    $oldTitle = $page->title;
    $oldContent = $page->content;
    $oldStatus = $page->status;

    $rawData = $_POST;
    $data = $rawData;
    unset($data['_token'], $data['_csrf'], $data['_method']);

    // Página de inicio
    $makeHomepage = isset($rawData['is_homepage']) && $rawData['is_homepage'] == '1';
    unset($data['is_homepage']);
    
    // Manejo de checkboxes para opciones de cabecera
    $data['show_slider'] = isset($data['show_slider']) ? 1 : 0;
    $data['hide_title'] = isset($data['hide_title']) ? 1 : 0;
    
    // Manejo de visibilidad (con valor por defecto)
    $data['visibility'] = $data['visibility'] ?? 'public';
    
    // Validar que visibility sea uno de los valores permitidos
    if (!in_array($data['visibility'], ['public', 'private', 'members'])) {
        $data['visibility'] = 'public';
    }
    
    // Procesar imagen del slider
    $currentSliderImage = $data['current_slider_image'] ?? null;
    $removeImage = $data['remove_slider_image'] ?? '0';
    
    // Eliminar estos campos para no guardarlos en la BD
    unset($data['current_slider_image'], $data['remove_slider_image']);
    
    // Si marca eliminar imagen, borramos la referencia y el archivo
    if ($removeImage === '1' && !empty($currentSliderImage)) {
        // Log para depuración
        error_log("Eliminando imagen: {$currentSliderImage}");
        
        // Obtener solo el nombre de archivo
        $fileName = basename($currentSliderImage);
        
        // Construir la ruta basada en APP_ROOT
        $fullPath = APP_ROOT . "/public/assets/uploads/headers/{$fileName}";
        
        if (file_exists($fullPath)) {
            error_log("Intentando eliminar archivo: {$fullPath}");
            if (@unlink($fullPath)) {
                error_log("Archivo eliminado con éxito: {$fullPath}");
            } else {
                error_log("Error al eliminar el archivo: " . error_get_last()['message'] ?? 'desconocido');
            }
        } else {
            error_log("Archivo no encontrado en la ruta: {$fullPath}");
        }
        
        // En cualquier caso, establecer el campo a NULL en la base de datos
        $data['slider_image'] = null;
    }
    
    // Procesar la nueva imagen si se sube una
    if ($_FILES && isset($_FILES['slider_image']) && $_FILES['slider_image']['error'] == 0) {
        $uploadResult = $this->processSliderImageUpload($_FILES['slider_image'], $currentSliderImage);
        if (isset($uploadResult['error'])) {
            flash('error', __('pages.error_image_upload') . ': ' . $uploadResult['error']);
            header("Location: /musedock/pages/{$id}/edit");
            exit;
        }
        $data['slider_image'] = $uploadResult['path'];
    } elseif ($removeImage !== '1') {
        // Mantener la imagen actual si no se marca eliminar y no se sube una nueva
        $data['slider_image'] = $currentSliderImage;
    }

    // Validación
    $errors = PageRequest::validate($rawData, $id);
    if (!empty($errors)) {
        $_SESSION['_old_input'] = $rawData;
        flash('error', implode('<br>', $errors));
        header("Location: /musedock/pages/{$id}/edit");
        exit;
    }
    unset($_SESSION['_old_input']);

    // Procesar datos
    $data = self::processFormData($data);
    unset($data['tenant_id']);

    $newSlug = $data['slug'];
    $prefix = $rawData['prefix'] ?? 'p';

    $pdo = null;

    try {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        // 1. Desmarcar otras home si es necesario
        if ($makeHomepage) {
            $updateOthersStmt = $pdo->prepare(
                "UPDATE pages SET is_homepage = 0 WHERE is_homepage = 1 AND id != ? AND tenant_id IS NULL"
            );
            $updateOthersStmt->execute([$id]);
        }

        // 2. Actualizar datos principales de la página
        unset($data['prefix']);
        $page->update($data);

        // 3. Actualizar is_homepage y tenant_id
        $updateCurrentStmt = $pdo->prepare(
            "UPDATE pages SET is_homepage = ?, tenant_id = NULL WHERE id = ?"
        );
        $updateCurrentStmt->execute([$makeHomepage ? 1 : 0, $id]);

        // === SINCRONIZACIÓN: Actualizar settings de lectura ===
        // Función helper para upsert compatible con MySQL y PostgreSQL
        $upsertSetting = function($pdo, $key, $value) {
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute([$key, $value, $value]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (\"key\", value) VALUES (?, ?) ON CONFLICT (\"key\") DO UPDATE SET value = EXCLUDED.value");
                $stmt->execute([$key, $value]);
            }
        };

        if ($makeHomepage) {
            // Si se marca como homepage, actualizar page_on_front
            $upsertSetting($pdo, 'page_on_front', $id);
            $upsertSetting($pdo, 'show_on_front', 'page');

            error_log("PageController: Sincronizados settings de lectura - page_on_front = {$id}, show_on_front = page");
        } else {
            // Si se desmarca como homepage, limpiar el setting si coincide con esta página
            $keyCol = \Screenart\Musedock\Database::qi('key');
            $checkSettingStmt = $pdo->prepare("SELECT value FROM settings WHERE {$keyCol} = 'page_on_front'");
            $checkSettingStmt->execute();
            $currentPageOnFront = $checkSettingStmt->fetchColumn();

            if ($currentPageOnFront == $id) {
                // Esta página era la homepage en settings, limpiarla y volver a posts
                $upsertSetting($pdo, 'page_on_front', '');
                $upsertSetting($pdo, 'show_on_front', 'posts');

                error_log("PageController: Desmarcada página {$id} como homepage en settings");
            }
        }
        // Limpiar caché de settings
        setting(null);
        // ======================================================

        // 4. Actualizar slug
        $deleteSlugStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'pages' AND reference_id = ?");
        $deleteSlugStmt->execute([$id]);

        $insertSlugStmt = $pdo->prepare(
            "INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, NULL, ?)"
        );
        $insertSlugStmt->execute(['pages', $id, $newSlug, $prefix]);

        // === NUEVO: Guardar plantilla seleccionada ===
        $selectedTemplate = $rawData['page_template'] ?? 'page.blade.php';
        PageMeta::updateOrInsertMeta($id, 'page_template', $selectedTemplate);
        // ==============================================

        $pdo->commit();

        // ✅ Crear revisión después de actualizar exitosamente
        try {
            // Detectar cambios
            $changes = [];
            if ($oldTitle !== $page->title) $changes[] = 'título';
            if ($oldContent !== $page->content) $changes[] = 'contenido';
            if ($oldStatus !== $data['status']) $changes[] = 'status';

            $summary = !empty($changes)
                ? 'Modificó: ' . implode(', ', $changes)
                : 'Actualización de metadatos';

            \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'manual', $summary);
        } catch (\Exception $revError) {
            error_log("Error al crear revisión: " . $revError->getMessage());
        }

    } catch (\Exception $e) {
        if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("ERROR en transacción update página {$id}: " . $e->getMessage());
        $_SESSION['_old_input'] = $rawData;
        flash('error', __('pages.error_update_failed') . ': ' . $e->getMessage());
        header("Location: /musedock/pages/{$id}/edit");
        exit;
    }

    flash('success', __('pages.success_updated'));
    header("Location: /musedock/pages/{$id}/edit");
    exit;
}
/**
 * Procesa los datos del formulario antes de guardarlos
 * 
 * @param array $data Los datos del formulario
 * @return array Los datos procesados
 */
/**
 * Procesa los datos del formulario antes de guardarlos
 * 
 * @param array $data Los datos del formulario
 * @return array Los datos procesados
 */
private static function processFormData($data)
{
    // No procesamos el slug aquí, se gestiona directamente en las consultas SQL
    // en los métodos store y update
    
    // Gestionar campos opcionales
    $optionalFields = [
        'content', 'excerpt', 'seo_title', 'seo_description', 
        'seo_keywords', 'canonical_url', 'robots_directive',
        'twitter_title', 'twitter_description'
    ];
    
    foreach ($optionalFields as $field) {
        if (isset($data[$field]) && $data[$field] === '') {
            $data[$field] = null;
        }
    }
    
    // Dar formato a la fecha de publicación si existe
    if (isset($data['published_at']) && !empty($data['published_at'])) {
        try {
            $date = new \DateTime($data['published_at']);
            $data['published_at'] = $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // Si hay un error en el formato de fecha, la dejamos como está
        }
    } else {
        $data['published_at'] = null;
    }
    
    // Establecer valores predeterminados
    $data['status'] = $data['status'] ?? 'published';
    $data['visibility'] = $data['visibility'] ?? 'public';
    
    // Validar que visibility sea uno de los valores permitidos
    if (!in_array($data['visibility'], ['public', 'private', 'members'])) {
        $data['visibility'] = 'public';
    }
    
    return $data;
}
	
/**
 * Procesa la subida de imágenes para el slider
 * 
 * @param array $file Información del archivo subido ($_FILES['slider_image'])
 * @param string|null $currentImage Ruta actual de la imagen (para eliminarla si se reemplaza)
 * @return array Resultado de la operación con 'path' o 'error'
 */
private function processSliderImageUpload($file, $currentImage = null)
{
    // Log al inicio para depuración
    error_log("Intentando procesar imagen: " . $file['name'] . " (tamaño: " . $file['size'] . ")");
    
    // Comprobar si el archivo es una imagen
    $fileInfo = getimagesize($file['tmp_name']);
    if ($fileInfo === false) {
        error_log("Error: El archivo no es una imagen válida");
        return ['error' => 'El archivo no es una imagen válida.'];
    }
    
    // Dimensiones deseadas
    $targetWidth = 1920;
    $targetHeight = 400;
    
    // Configuración - Ajustando las rutas según la estructura de tu proyecto
    $uploadDir = APP_ROOT . '/public/assets/uploads/headers/'; // Ruta absoluta 
    $relativePath = 'uploads/headers/'; // Ruta relativa DB
    
    error_log("Directorio de subida completo: " . $uploadDir);
    
    // Crear directorio si no existe
    if (!file_exists($uploadDir)) {
        error_log("El directorio no existe, intentando crearlo: " . $uploadDir);
        $mkdirResult = mkdir($uploadDir, 0755, true);
        
        if (!$mkdirResult) {
            $errorMsg = "Error al crear el directorio: " . $uploadDir;
            error_log($errorMsg);
            return ['error' => $errorMsg];
        } else {
            error_log("Directorio creado exitosamente: " . $uploadDir);
        }
    }
    
    // Generar nombre único para el archivo
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('header_') . '.' . $extension;
    $fullPath = $uploadDir . $filename;
    
    // Verificar si es un formato no compatible
    $isUnsupportedFormat = !in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    
    // Si es un formato no compatible, simplemente mover el archivo sin redimensionar
    if ($isUnsupportedFormat) {
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $errorMsg = "Error al mover el archivo subido";
            error_log($errorMsg);
            return ['error' => $errorMsg];
        }
        error_log("Archivo subido (sin redimensionar) a: " . $fullPath);
    } 
    // Si es un formato compatible, procesar y redimensionar la imagen
    else {
        try {
            // Crear una imagen temporal desde el archivo subido
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
            
            // Obtener dimensiones originales
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            // Crear imagen de destino
            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
            
            // Mantener transparencia para PNG
            if ($extension == 'png') {
                imagealphablending($targetImage, false);
                imagesavealpha($targetImage, true);
                $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
                imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
            }
            
            // Calcular proporciones y posición para recorte
            $sourceRatio = $sourceWidth / $sourceHeight;
            $targetRatio = $targetWidth / $targetHeight;
            
            if ($sourceRatio > $targetRatio) {
                // La imagen es más ancha que la proporción objetivo
                $newHeight = $sourceHeight;
                $newWidth = $sourceHeight * $targetRatio;
                $srcX = ($sourceWidth - $newWidth) / 2;
                $srcY = 0;
            } else {
                // La imagen es más alta que la proporción objetivo
                $newWidth = $sourceWidth;
                $newHeight = $sourceWidth / $targetRatio;
                $srcX = 0;
                $srcY = ($sourceHeight - $newHeight) / 2;
            }
            
            // Redimensionar y recortar
            imagecopyresampled(
                $targetImage, $sourceImage,
                0, 0, $srcX, $srcY,
                $targetWidth, $targetHeight, $newWidth, $newHeight
            );
            
            // Guardar la imagen redimensionada
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($targetImage, $fullPath, 90); // 90 es la calidad
                    break;
                case 'png':
                    imagepng($targetImage, $fullPath, 9); // 9 es el nivel de compresión (0-9)
                    break;
                case 'gif':
                    imagegif($targetImage, $fullPath);
                    break;
                case 'webp':
                    imagewebp($targetImage, $fullPath, 90);
                    break;
            }
            
            // Liberar memoria
            imagedestroy($sourceImage);
            imagedestroy($targetImage);
            
            error_log("Imagen redimensionada y guardada en: " . $fullPath);
            
        } catch (\Exception $e) {
            $errorMsg = "Error al procesar la imagen: " . $e->getMessage();
            error_log($errorMsg);
            return ['error' => $errorMsg];
        }
    }
    
    // Eliminar imagen anterior si existe
    if ($currentImage && !empty($currentImage) && strpos($currentImage, 'themes/default/img/hero') === false) {
        $oldPath = APP_ROOT . '/public/' . $currentImage;
        if (file_exists($oldPath)) {
            @unlink($oldPath);
            error_log("Imagen anterior eliminada: " . $oldPath);
        }
    }
    
    return ['path' => $relativePath . $filename];
}

	
public function destroy($id)
{
    $this->checkPermission('pages.delete');
    error_log("========================================");
    error_log("DESTROY LLAMADO PARA PAGE ID: {$id}");
    error_log("========================================");

    try {
        $pdo = Database::connect();
        $user = $_SESSION['super_admin'] ?? null;
        $pageId = (int)$id;

        // ✅ ESTRATEGIA NUEVA: UPDATE PRIMERO, antes de cargar el modelo
        error_log("PASO 1: Actualizar status DIRECTAMENTE en SQL, SIN cargar modelo primero");

        // UPDATE directo sin modelo
        $updateSql = "UPDATE pages SET status = 'trash', updated_at = NOW() WHERE id = {$pageId} AND (tenant_id IS NULL OR tenant_id = 0)";
        error_log("SQL: {$updateSql}");
        $affectedRows = $pdo->exec($updateSql);
        error_log("Rows affected: {$affectedRows}");

        // Verificar inmediatamente
        $checkSql = "SELECT id, title, status, LENGTH(status) as status_len FROM pages WHERE id = {$pageId}";
        $result = $pdo->query($checkSql)->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            error_log("ERROR: Página {$pageId} no encontrada en BD");
            flash('error', __('pages.error_not_found'));
            header('Location: /musedock/pages');
            exit;
        }

        $currentStatus = $result['status'] ?? '';
        $statusLen = $result['status_len'] ?? 0;
        error_log("VERIFICACIÓN: status='{$currentStatus}', length={$statusLen}");

        // Si el UPDATE no funcionó, abortar con mensaje claro
        if ($currentStatus !== 'trash') {
            error_log("⚠️ ERROR CRÍTICO: El UPDATE no funcionó. Status actual: '{$currentStatus}'");
            error_log("⚠️ Ejecutar manualmente: UPDATE pages SET status = 'trash' WHERE id = {$pageId};");
            error_log("⚠️ Luego verificar con: SELECT id, status, LENGTH(status) FROM pages WHERE id = {$pageId};");
            flash('error', __('pages.error_trash_status_update_failed'));
            header('Location: /musedock/pages');
            exit;
        }

        error_log("✓ UPDATE EXITOSO - Status cambiado a 'trash'");

        // AHORA sí, cargar el modelo para crear la revisión
        $page = Page::find($id);
        if ($page) {
            $page->status = 'trash'; // Actualizar en memoria
        }

        // PASO 2: Registrar en tabla pages_trash usando prepared statement
        error_log("PASO 2: Insertando en pages_trash...");

        $deletedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $scheduledPermanentDelete = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO pages_trash (page_id, tenant_id, deleted_by, deleted_by_name, deleted_by_type, deleted_at, scheduled_permanent_delete, ip_address) VALUES (?, NULL, ?, ?, 'superadmin', ?, ?, ?)");
        $stmt->execute([
            $pageId,
            $user['id'] ?? 0,
            $user['name'] ?? 'Sistema',
            $deletedAt,
            $scheduledPermanentDelete,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        error_log("✓ INSERT en pages_trash completado");

        // PASO 3: Crear revisión (si hay modelo cargado)
        if ($page) {
            try {
                error_log("PASO 3: Creando revisión...");
                \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'manual', 'Movido a papelera');
                error_log("✓ Revisión creada");
            } catch (\Exception $revError) {
                error_log("⚠️ Error al crear revisión: " . $revError->getMessage());
            }
        }

        error_log("========================================");
        error_log("✓✓✓ DESTROY COMPLETADO EXITOSAMENTE");
        error_log("========================================");
        flash('success', __('pages.success_trashed'));
    } catch (\Exception $e) {
        error_log("========================================");
        error_log("✗✗✗ EXCEPCIÓN en destroy");
        error_log("Error: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        error_log("========================================");
        flash('error', __('pages.error_delete_failed') . ': ' . $e->getMessage());
    }

    // Redireccionar al listado de páginas
    header('Location: /musedock/pages');
    exit;
}
	
public function bulk()
{
    $action = $_POST['action'] ?? null;
    $selected = $_POST['selected'] ?? [];

    if (empty($action) || empty($selected)) {
        flash('error', __('pages.error_bulk_no_selection'));
        header('Location: ' . route('pages.index'));
        exit;
    }

    // Verificar permisos según la acción
    if ($action === 'delete') {
        $this->checkPermission('pages.delete');
    } else {
        // edit, draft, published, public, private, members requieren permiso de editar
        $this->checkPermission('pages.edit');
    }

    // Límite de 100 elementos en acciones masivas
    if (count($selected) > 100) {
        flash('error', __('pages.error_bulk_limit_exceeded'));
        header('Location: ' . route('pages.index'));
        exit;
    }

    if ($action === 'delete') {
        $deletedCount = 0;
        $deletedImages = 0;
        
        foreach ($selected as $id) {
            $page = Page::find($id);
            
            if ($page) {
                // Debug log
                error_log("===== DEPURACIÓN ELIMINACIÓN DE PÁGINA EN BULK =====");
                error_log("ID de página: " . $id);
                
                // Verificar también directamente con SQL para estar seguros
                $pdo = \Screenart\Musedock\Database::connect();
                $stmt = $pdo->prepare("SELECT slider_image FROM pages WHERE id = ?");
                $stmt->execute([$id]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                error_log("Valor de slider_image desde SQL: " . ($result['slider_image'] ?? 'NULL'));
                
                // Usar el valor de SQL por si hay algún problema en el mapeo del modelo
                $imagePath = $result['slider_image'] ?? $page->slider_image ?? null;
                
                // 1. Si hay imagen, intentar eliminarla
                if (!empty($imagePath)) {
                    error_log("Intentando eliminar imagen: " . $imagePath);
                    
                    // Obtener solo el nombre de archivo
                    $fileName = basename($imagePath);
                    
                    // Construir la ruta basada en APP_ROOT
                    $fullPath = APP_ROOT . "/public/assets/uploads/headers/{$fileName}";
                    
                    error_log("Intentando borrar archivo en: {$fullPath}");
                    
                    if (file_exists($fullPath)) {
                        error_log("Archivo encontrado, intentando eliminar");
                        
                        if (@unlink($fullPath)) {
                            error_log("¡Archivo eliminado correctamente!");
                            $deletedImages++;
                        } else {
                            error_log("Error al eliminar el archivo: " . error_get_last()['message'] ?? 'Desconocido');
                        }
                    } else {
                        error_log("Archivo no encontrado en la ruta: {$fullPath}");
                    }
                } else {
                    error_log("No hay imagen de slider asociada a esta página.");
                }
            
                // 2. Eliminar traducciones asociadas usando SQL directo
                error_log("Eliminando traducciones para página ID: " . $id);
                try {
                    $deleteStmt = $pdo->prepare("DELETE FROM page_translations WHERE page_id = ?");
                    $deleteStmt->execute([$id]);
                } catch (\Exception $e) {
                    error_log("Error al eliminar traducciones para página #{$id}: " . $e->getMessage());
                }

                // 3. Eliminar slug usando SQL directo
                error_log("Eliminando slug para página ID: " . $id);
                try {
                    $deleteStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'pages' AND reference_id = ?");
                    $deleteStmt->execute([$id]);
                } catch (\Exception $e) {
                    error_log("Error al eliminar slug para página #{$id}: " . $e->getMessage());
                    // Continuar con la siguiente página en caso de error
                    continue;
                }

                // 4. Eliminar metadatos de la página usando SQL directo
                error_log("Eliminando metadatos para página ID: " . $id);
                try {
                    $deleteStmt = $pdo->prepare("DELETE FROM page_meta WHERE page_id = ?");
                    $deleteStmt->execute([$id]);
                } catch (\Exception $e) {
                    error_log("Error al eliminar metadatos para página #{$id}: " . $e->getMessage());
                }

                // 5. Eliminar página usando SQL directo
                error_log("Eliminando página ID: " . $id);
                try {
                    $deleteStmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
                    $deleteStmt->execute([$id]);
                    $deletedCount++;
                } catch (\Exception $e) {
                    error_log("Error al eliminar página #{$id}: " . $e->getMessage());
                    continue;
                }
            }
        }
        
        // Mensaje de éxito más informativo
        $message = str_replace('{count}', $deletedCount, __('pages.success_bulk_deleted'));
        if ($deletedImages > 0) {
            $message .= ' ' . str_replace('{count}', $deletedImages, __('pages.info_images_deleted'));
        }
        flash('success', $message);

        header('Location: ' . route('pages.index'));
        exit;
    }
    
    if ($action === 'edit') {
        // Guardamos los IDs en sesión para mostrarlos luego en bulk-edit
        $_SESSION['selected_bulk_ids'] = $selected;
        header('Location: ' . route('pages.bulk.edit'));
        exit;
    }
    
    if (in_array($action, ['draft', 'published'])) {
        foreach ($selected as $id) {
            $page = Page::find($id);
            if ($page) {
                $page->status = $action;
                $page->save();
            }
        }

        flash('success', __('pages.success_bulk_status_updated'));
        header('Location: ' . route('pages.index'));
        exit;
    }

    // NUEVO: Opciones para cambiar la visibilidad en masa
    if (in_array($action, ['public', 'private', 'members'])) {
        foreach ($selected as $id) {
            $page = Page::find($id);
            if ($page) {
                $page->visibility = $action;
                $page->save();
            }
        }

        flash('success', __('pages.success_bulk_visibility_updated'));
        header('Location: ' . route('pages.index'));
        exit;
    }

    flash('error', __('pages.error_invalid_action'));
    header('Location: ' . route('pages.index'));
    exit;
}
public function bulkEditForm()
{
    $this->checkPermission('pages.edit');
    $selectedIds = $_SESSION['selected_bulk_ids'] ?? [];

    if (empty($selectedIds)) {
        flash('error', __('pages.error_no_pages_selected'));
        header('Location: /musedock/pages');
        exit;
    }
    
    // Cargar todas las páginas seleccionadas con sus datos
    $selectedPages = [];
    foreach ($selectedIds as $id) {
        $page = Page::find($id);
        if ($page) {
            $selectedPages[] = $page;
        }
    }
    
    return View::renderSuperadmin('pages.bulk_edit', [
        'selectedIds' => $selectedIds,
        'selectedPages' => $selectedPages
    ]);
}

public function bulkUpdate()
{
    $this->checkPermission('pages.edit');
    $selected = $_POST['selected'] ?? [];
    $status = $_POST['status'] ?? '';
    $visibility = $_POST['visibility'] ?? '';
    $publishedAt = $_POST['published_at'] ?? '';

    if (empty($selected)) {
        flash('error', __('pages.error_no_pages_selected'));
        header('Location: /musedock/pages');
        exit;
    }

    // Límite de 100 elementos en acciones masivas
    if (count($selected) > 100) {
        flash('error', __('pages.error_bulk_limit_exceeded'));
        header('Location: /musedock/pages');
        exit;
    }
    
    $updatedCount = 0;
    
    foreach ($selected as $id) {
        $page = Page::find($id);
        if (!$page) continue;
        
        $updateData = [];
        
        // Solo actualizar si se seleccionó un valor
        if (!empty($status)) {
            $updateData['status'] = $status;
        }
        
        // Actualizar visibilidad si se seleccionó
        if (!empty($visibility)) {
            $updateData['visibility'] = $visibility;
        }
        
        if (!empty($publishedAt)) {
            $updateData['published_at'] = $publishedAt;
        }
        
        if (!empty($updateData)) {
            $page->update($updateData);
            
            // Si es necesario, actualizar el tenant_id a NULL explícitamente
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $stmt = $pdo->prepare("UPDATE pages SET tenant_id = NULL WHERE id = ?");
                $stmt->execute([$id]);
            } catch (\Exception $e) {
                // Continuar a pesar del error
            }
            
            $updatedCount++;
        }
    }

    flash('success', str_replace('{count}', $updatedCount, __('pages.success_bulk_updated')));
    header('Location: /musedock/pages');
    exit;
}
public function editTranslation($id, $locale)
    {
        $this->checkPermission('pages.edit');
        $page = Page::find($id);
        if (!$page) {
            flash('error', __('pages.error_base_page_not_found'));
            header('Location: /musedock/pages'); // Redirige al índice si la página no existe
            exit;
        }

        // Intentar encontrar la traducción existente
        $translation = PageTranslation::where('page_id', $id)
            ->where('locale', $locale)
            ->first();

        // Si no existe, creamos una instancia vacía para el formulario
        $isNewTranslation = false;
        if (!$translation) {
            $translation = new PageTranslation([
                'page_id' => $id,
                'locale' => $locale,
                'tenant_id' => $page->tenant_id, // Copiar tenant_id de la página base
                 // Podrías pre-rellenar el título si quieres
                 // 'title' => $page->title . ' [' . strtoupper($locale) . ']',
            ]);
            $isNewTranslation = true;
        }

        // Obtener el nombre del idioma para mostrarlo (asumiendo helper getAvailableLocales())
        $localeName = getAvailableLocales()[$locale] ?? strtoupper($locale);

        // Renderizar la vista del formulario de traducción
        return View::renderSuperadmin('pages.translation_edit', [
            'title'       => $isNewTranslation
                                ? "Crear Traducción ({$localeName}) para \"{$page->title}\""
                                : "Editar Traducción ({$localeName}) para \"{$page->title}\"",
            'Page'        => $page,          // La página base original
            'translation' => $translation,   // La traducción (existente o nueva instancia)
            'locale'      => $locale,         // El código del idioma a editar
            'localeName'  => $localeName     // El nombre del idioma
        ]);
    }

    /**
     * Guarda (crea o actualiza) una traducción específica.
     * Ahora maneja todos los campos del modelo PageTranslation (incluidos SEO).
     */
 public function updateTranslation($id, $locale)
{
    $this->checkPermission('pages.edit');
    $page = Page::find($id);
    if (!$page) {
        flash('error', __('pages.error_base_page_not_found'));
        header('Location: /musedock/pages');
        exit;
    }

    // Recoger todos los datos del POST
    $data = $_POST;
    
    // Eliminar campos que no deben ir a la base de datos
    unset($data['_token'], $data['_method'], $data['_csrf']);
    
    // Asegurarse que ciertos campos estén presentes
    $data['page_id'] = $id;
    $data['locale'] = $locale;
    
    // Limpiar campos opcionales vacíos para que se guarden como NULL
    $optionalFields = [
        'content',
        'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
        'canonical_url', 'robots_directive', 'twitter_title',
        'twitter_description', 'twitter_image'
    ];
    foreach ($optionalFields as $field) {
        if (isset($data[$field]) && $data[$field] === '') {
            $data[$field] = null;
        }
    }
    
    // Asegurar que robots_directive sea un valor válido o null
    if (isset($data['robots_directive']) && !in_array($data['robots_directive'], ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'])) {
        $data['robots_directive'] = null;
    }

    try {
        // Buscar si ya existe una traducción
        $pdo = \Screenart\Musedock\Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM page_translations WHERE page_id = ? AND locale = ? LIMIT 1");
        $stmt->execute([$id, $locale]);
        $existingTranslation = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($existingTranslation) {
            // Actualizar la traducción existente - usando consulta SQL directa
            // IMPORTANTE: Asegurarnos de que solo incluimos columnas que existen en la tabla
            $allowedColumns = [
                'title', 'content', 'seo_title', 'seo_description', 
                'seo_keywords', 'seo_image', 'canonical_url', 
                'robots_directive', 'twitter_title', 'twitter_description', 
                'twitter_image'
            ];
            
            $setClauses = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedColumns)) {
                    $setClauses[] = "{$key} = ?";
                    $params[] = $value;
                }
            }
            
            $setClauses[] = "updated_at = NOW()";
            $setString = implode(', ', $setClauses);
            
            $updateStmt = $pdo->prepare("UPDATE page_translations SET {$setString} WHERE page_id = ? AND locale = ?");
            $params[] = $id;
            $params[] = $locale;
            $updateStmt->execute($params);

            flash('success', __('pages.success_translation_updated'));
        } else {
            // Crear una nueva traducción
            // IMPORTANTE: Asegurarnos de que solo incluimos columnas que existen en la tabla
            $allowedColumns = [
                'page_id', 'locale', 'tenant_id', 'title', 'content', 
                'seo_title', 'seo_description', 'seo_keywords', 'seo_image', 
                'canonical_url', 'robots_directive', 'twitter_title', 
                'twitter_description', 'twitter_image', 'created_at', 'updated_at'
            ];
            
            $insertData = [
                'page_id' => $id,
                'locale' => $locale,
                'tenant_id' => $page->tenant_id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedColumns)) {
                    $insertData[$key] = $value;
                }
            }
            
            $columns = implode(', ', array_keys($insertData));
            $placeholders = implode(', ', array_fill(0, count($insertData), '?'));
            
            $insertStmt = $pdo->prepare("INSERT INTO page_translations ({$columns}) VALUES ({$placeholders})");
            $insertStmt->execute(array_values($insertData));

            flash('success', __('pages.success_translation_created'));
        }
    } catch (\Exception $e) {
        // Loggear el error sería ideal aquí
        error_log("Error al guardar traducción para página {$id}, locale {$locale}: " . $e->getMessage());
        flash('error', __('pages.error_translation_save_failed') . ': ' . $e->getMessage());
        // Redirigir de vuelta con error
        header("Location: /musedock/pages/{$id}/translations/{$locale}");
        exit;
    }

    // Redirigir de vuelta al formulario de edición de la traducción
    header("Location: /musedock/pages/{$id}/translations/{$locale}");
    exit;
}

// ================================================================
// 📚 SISTEMA DE VERSIONES/REVISIONES
// ================================================================

/**
 * Mostrar historial de revisiones de una página
 */
public function revisions($id)
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $this->checkPermission('pages.view');

    $page = Page::find($id);
    if (!$page) {
        flash('error', __('pages.error_not_found'));
        header("Location: /musedock/pages");
        exit;
    }

    // Obtener revisiones
    $revisions = \Screenart\Musedock\Models\PageRevision::getPageRevisions($id, 100);

    return View::renderSuperadmin('pages.revisions', [
        'title' => 'Historial de revisiones: ' . e($page->title),
        'page' => $page,
        'revisions' => $revisions,
    ]);
}

/**
 * Restaurar una revisión específica
 */
public function restoreRevision($pageId, $revisionId)
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $this->checkPermission('pages.edit');

    $revision = \Screenart\Musedock\Models\PageRevision::findWithTenant((int)$revisionId, null);

    if (!$revision || $revision->page_id != $pageId) {
        flash('error', __('pages.error_revision_not_found'));
        header("Location: /musedock/pages/{$pageId}/revisions");
        exit;
    }

    if ($revision->restore()) {
        flash('success', str_replace('{date}', $revision->created_at, __('pages.success_revision_restored')));
    } else {
        flash('error', __('pages.error_revision_restore_failed'));
    }

    header("Location: /musedock/pages/{$pageId}/edit");
    exit;
}

/**
 * Acciones masivas sobre revisiones de una página (CMS global)
 * Acciones soportadas:
 * - delete_selected: elimina revisiones seleccionadas
 * - delete_all: elimina todas las revisiones de la página
 */
public function bulkRevisions($pageId)
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $this->checkPermission('pages.edit');

    $page = Page::where('id', (int)$pageId)->whereNull('tenant_id')->first();
    if (!$page) {
        flash('error', __('pages.error_not_found'));
        header("Location: /musedock/pages");
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        $deletedCount = 0;

        if ($action === 'delete_selected') {
            $revisionIds = $_POST['revision_ids'] ?? [];
            if (!is_array($revisionIds)) {
                $revisionIds = [];
            }

            $revisionIds = array_values(array_unique(array_filter(array_map('intval', $revisionIds), fn($v) => $v > 0)));

            if (empty($revisionIds)) {
                $pdo->rollBack();
                flash('error', __('pages.error_bulk_revision_no_selection'));
                header("Location: /musedock/pages/{$pageId}/revisions");
                exit;
            }

            $placeholders = implode(',', array_fill(0, count($revisionIds), '?'));
            $params = array_merge($revisionIds, [(int)$pageId]);
            $deleteStmt = $pdo->prepare("
                DELETE FROM page_revisions
                WHERE id IN ({$placeholders}) AND page_id = ? AND (tenant_id IS NULL OR tenant_id = 0)
            ");
            $deleteStmt->execute($params);
            $deletedCount = (int)$deleteStmt->rowCount();
        } elseif ($action === 'delete_all') {
            $deleteStmt = $pdo->prepare("
                DELETE FROM page_revisions
                WHERE page_id = ? AND (tenant_id IS NULL OR tenant_id = 0)
            ");
            $deleteStmt->execute([(int)$pageId]);
            $deletedCount = (int)$deleteStmt->rowCount();
        } else {
            $pdo->rollBack();
            flash('error', __('pages.error_bulk_revision_invalid_action'));
            header("Location: /musedock/pages/{$pageId}/revisions");
            exit;
        }

        $updateCountStmt = $pdo->prepare("
            UPDATE pages
            SET revision_count = (
                SELECT COUNT(*)
                FROM page_revisions
                WHERE page_id = ? AND (tenant_id IS NULL OR tenant_id = 0)
            )
            WHERE id = ? AND (tenant_id IS NULL OR tenant_id = 0)
        ");
        $updateCountStmt->execute([(int)$pageId, (int)$pageId]);

        $pdo->commit();

        flash('success', __('pages.success_bulk_revisions_deleted', ['count' => $deletedCount]));
    } catch (\Throwable $e) {
        if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error en bulkRevisions pages CMS page {$pageId}: " . $e->getMessage());
        flash('error', __('pages.error_revision_delete_failed'));
    }

    header("Location: /musedock/pages/{$pageId}/revisions");
    exit;
}

/**
 * Vista previa de una revisión
 */
public function previewRevision($pageId, $revisionId)
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $this->checkPermission('pages.view');

    $revision = \Screenart\Musedock\Models\PageRevision::find((int)$revisionId);

    if (!$revision || $revision->page_id != $pageId) {
        flash('error', __('pages.error_revision_not_found'));
        header("Location: /musedock/pages/{$pageId}/revisions");
        exit;
    }

    return View::renderSuperadmin('pages.preview-revision', [
        'title' => 'Preview: ' . e($revision->title),
        'revision' => $revision,
        'page' => Page::find($pageId),
    ]);
}

/**
 * Comparar dos revisiones
 */
public function compareRevisions($pageId, $id1, $id2)
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $this->checkPermission('pages.view');

    $revision1 = \Screenart\Musedock\Models\PageRevision::find((int)$id1);
    $revision2 = \Screenart\Musedock\Models\PageRevision::find((int)$id2);

    if (!$revision1 || !$revision2 || $revision1->page_id != $pageId || $revision2->page_id != $pageId) {
        flash('error', __('pages.error_revisions_not_found'));
        header("Location: /musedock/pages/{$pageId}/revisions");
        exit;
    }

    $diff = $revision1->diffWith($revision2);

    return View::renderSuperadmin('pages.compare-revisions', [
        'title' => 'Comparar revisiones',
        'page' => Page::find($pageId),
        'revision1' => $revision1,
        'revision2' => $revision2,
        'diff' => $diff,
    ]);
}

/**
 * Mostrar papelera de páginas
 */
public function trash()
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $this->checkPermission('pages.view');

    // Obtener páginas en papelera
    $pages = Page::where('status', 'trash')
                    ->whereNull('tenant_id')
                    ->orderBy('updated_at', 'DESC')
                    ->get();

    // Obtener info de papelera
    $pdo = Database::connect();
    $trashInfo = [];
    foreach ($pages as $page) {
        $stmt = $pdo->prepare("SELECT * FROM pages_trash WHERE page_id = ? LIMIT 1");
        $stmt->execute([$page->id]);
        $trashInfo[$page->id] = $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    return View::renderSuperadmin('pages.trash', [
        'title' => 'Papelera de páginas',
        'pages' => $pages,
        'trashInfo' => $trashInfo,
    ]);
}

/**
 * Restaurar una página desde la papelera
 */
public function restoreFromTrash($id)
{
    $this->checkPermission('pages.edit');
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    $pdo = Database::connect();
    $pageId = (int)$id;

    // Verificar que la página existe en papelera
    $stmt = $pdo->prepare("SELECT id FROM pages WHERE id = ? AND status = 'trash' AND (tenant_id IS NULL OR tenant_id = 0)");
    $stmt->execute([$pageId]);
    $page = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$page) {
        flash('error', __('pages.error_not_found_in_trash'));
        header("Location: /musedock/pages/trash");
        exit;
    }

    // Restaurar usando SQL directo
    $updateSql = "UPDATE pages SET status = 'draft', updated_at = NOW() WHERE id = {$pageId}";
    $pdo->exec($updateSql);

    // Eliminar de papelera
    $stmt = $pdo->prepare("DELETE FROM pages_trash WHERE page_id = ?");
    $stmt->execute([$pageId]);

    // Crear revisión - cargar el modelo Page para esto
    $pageModel = Page::find($pageId);
    if ($pageModel) {
        \Screenart\Musedock\Models\PageRevision::createFromPage($pageModel, 'restored', 'Restaurado desde papelera');
    }

    flash('success', __('pages.success_restored'));
    header("Location: /musedock/pages/{$pageId}/edit");
    exit;
}

/**
 * Eliminar permanentemente una página
 */
public function forceDelete($id)
{
    $this->checkPermission('pages.delete');
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    $page = Page::where('id', $id)
                    ->where('status', 'trash')
                    ->first();

    if (!$page) {
        flash('error', __('pages.error_not_found_in_trash'));
        header("Location: /musedock/pages/trash");
        exit;
    }

    try {
        $pdo = Database::connect();

        // Eliminar revisiones
        $stmt = $pdo->prepare("DELETE FROM page_revisions WHERE page_id = ?");
        $stmt->execute([$id]);

        // Eliminar de papelera
        $stmt = $pdo->prepare("DELETE FROM pages_trash WHERE page_id = ?");
        $stmt->execute([$id]);

        // Eliminar traducciones
        $stmt = $pdo->prepare("DELETE FROM page_translations WHERE page_id = ?");
        $stmt->execute([$id]);

        // Eliminar página
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->execute([$id]);

        flash('success', __('pages.success_permanently_deleted'));
    } catch (\Exception $e) {
        error_log("Error al eliminar permanentemente la página: " . $e->getMessage());
        flash('error', __('pages.error_delete_failed'));
    }

    header("Location: /musedock/pages/trash");
    exit;
}

/**
 * Autoguardar una página (AJAX endpoint)
 */
public function autosave($id)
{
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $this->checkPermission('pages.edit');

    header('Content-Type: application/json');

    $page = Page::find($id);
    if (!$page) {
        echo json_encode(['success' => false, 'message' => 'Página no encontrada']);
        exit;
    }

    // Obtener datos JSON del body
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    try {
        // Actualizar campos de la página
        if (isset($data['title'])) $page->title = $data['title'];
        if (isset($data['content'])) $page->content = $data['content'];
        if (isset($data['excerpt'])) $page->excerpt = $data['excerpt'];

        $page->updated_at = date('Y-m-d H:i:s');
        $page->save();

        // Crear revisión de tipo autosave
        \Screenart\Musedock\Models\PageRevision::createFromPage($page, 'autosave', 'Autoguardado');

        echo json_encode([
            'success' => true,
            'message' => 'Autoguardado exitoso',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (\Exception $e) {
        error_log("Error en autosave: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al autoguardar']);
    }

    exit;
}
}
