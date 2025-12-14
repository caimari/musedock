<?php

namespace Blog\Controllers\Superadmin;

use Screenart\Musedock\View;
use Blog\Models\BlogPost;
use Blog\Models\BlogPostMeta;
use Blog\Models\BlogCategory;
use Blog\Models\BlogTag;
use Blog\Models\BlogPostTranslation;
use Screenart\Musedock\Models\SuperAdmin;
use Screenart\Musedock\Services\SlugService;
use Blog\Requests\BlogPostRequest;
use Screenart\Musedock\Database;
use Carbon\Carbon;
use Screenart\Musedock\Helpers\FileUploadValidator;

class BlogPostController
{
    /**
     * Verificar si el usuario actual tiene un permiso específico
     * Si no lo tiene, redirige con mensaje de error
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __('blog.post.error_no_permission'));
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission('blog.view');
        // Capturamos parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        // Obtener registros por página (10 por defecto, -1 para todos)
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Consulta de los posts
        $query = BlogPost::query()
            ->whereNull('tenant_id') // Posts del superadmin no tienen tenant
            ->whereRaw("(status != ? OR status IS NULL)", ['trash'])
            ->orderBy('updated_at', 'DESC');

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (title LIKE ? OR slug LIKE ? OR content LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // Si queremos todos los registros
        if ($perPage == -1) {
            $posts = $query->get();
            $pagination = [
                'total' => count($posts),
                'per_page' => count($posts),
                'current_page' => 1,
                'last_page' => 1,
                'from' => 1,
                'to' => count($posts),
                'items' => $posts
            ];
        } else {
            // Paginamos con el número solicitado
            $pagination = $query->paginate($perPage, $currentPage);
            $posts = $pagination['items'] ?? [];
        }

        // Procesamos los objetos BlogPost y cargamos datos adicionales
        $processedPosts = [];

        // Cargar datos de visibilidad y categorías
        try {
            $pdo = Database::connect();

            foreach ($posts as $postData) {
                // Si ya es instancia de BlogPost, no reconstruir desde (array)$obj (pierde atributos)
                $post = ($postData instanceof BlogPost) ? $postData : new BlogPost((array) $postData);

                // Cargar visibilidad
                $stmt = $pdo->prepare("SELECT visibility FROM blog_posts WHERE id = ? LIMIT 1");
                $stmt->execute([$post->id]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result && isset($result['visibility'])) {
                    $post->visibility = $result['visibility'];
                }

                $processedPosts[] = $post;
            }
        } catch (\Exception $e) {
            // Si falla la consulta, usar los datos originales
            error_log("Error al cargar datos adicionales: " . $e->getMessage());
            $processedPosts = array_map(fn($row) => ($row instanceof BlogPost) ? $row : new BlogPost((array) $row), $posts);
        }

        // Precargamos autores
        $authors = [];
        foreach ($processedPosts as $post) {
            $userId = $post->user_id ?? null;
            if ($userId && !isset($authors[$userId])) {
                // Asumiendo que el autor siempre es SuperAdmin en este contexto
                $authors[$userId] = SuperAdmin::find($userId);
            }
        }

        // Renderizamos la vista
        return View::renderSuperadmin('blog.posts.index', [
            'title'       => 'Listado de posts del blog',
            'posts'       => $processedPosts,
            'authors'     => $authors,
            'search'      => $search,
            'pagination'  => $pagination,
        ]);
    }

    public function create()
    {
        $this->checkPermission('blog.create');
        // Obtener todas las categorías disponibles
        $categories = BlogCategory::whereNull('tenant_id')->orderBy('name', 'ASC')->get();

        // Obtener todas las etiquetas disponibles
        $tags = BlogTag::whereNull('tenant_id')->orderBy('name', 'ASC')->get();

        // Obtener plantillas disponibles
        $availableTemplates = get_blog_templates();
        $currentTemplate = 'single'; // Plantilla por defecto

        return View::renderSuperadmin('blog.posts.create', [
            'title' => 'Crear Post',
            'post'  => new BlogPost(),
            'categories' => $categories,
            'tags' => $tags,
            'isNew' => true,
            'baseUrl' => $_SERVER['HTTP_HOST'],
            'availableTemplates' => $availableTemplates,
            'currentTemplate' => $currentTemplate,
        ]);
    }

    public function store()
    {
        $this->checkPermission('blog.create');
        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        // Eliminamos tenant_id del conjunto de datos inicial
        unset($data['tenant_id']);

        // Seteamos otros valores automáticos
        $data['user_id'] = $_SESSION['super_admin']['id'] ?? null;
        $data['user_type'] = 'superadmin';

        // Manejo de checkboxes
        $data['show_hero'] = isset($data['show_hero']) ? 1 : 0;
        $data['allow_comments'] = isset($data['allow_comments']) ? 1 : 0;
        $data['featured'] = isset($data['featured']) ? 1 : 0;
        $data['hide_featured_image'] = isset($data['hide_featured_image']) ? 1 : 0;

        // Manejo de visibilidad (con valor por defecto)
        $data['visibility'] = $data['visibility'] ?? 'public';

        // Validar que visibility sea uno de los valores permitidos
        if (!in_array($data['visibility'], ['public', 'private', 'password'])) {
            $data['visibility'] = 'public';
        }

        // Procesar la subida de imagen destacada si existe
        if ($_FILES && isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
            $uploadResult = $this->processFeaturedImageUpload($_FILES['featured_image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.post.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /musedock/blog/posts/create");
                exit;
            }
            $data['featured_image'] = $uploadResult['path'];
        } elseif (isset($data['featured_image']) && !empty($data['featured_image'])) {
            // Si no hay archivo subido pero hay una URL en el campo (del gestor de medios), mantenerla
            // El valor ya está en $data['featured_image'], no hacemos nada
        } else {
            // Si no hay archivo ni URL, establecer null
            $data['featured_image'] = null;
        }

        // Procesar la subida de imagen hero si existe
        if ($_FILES && isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] == 0) {
            $uploadResult = $this->processHeroImageUpload($_FILES['hero_image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.post.error_upload_hero_image', ['error' => $uploadResult['error']]));
                header("Location: /musedock/blog/posts/create");
                exit;
            }
            $data['hero_image'] = $uploadResult['path'];
        }

        // Establecer el valor predeterminado de status a 'published' si no se especifica
        if (!isset($data['status']) || !in_array($data['status'], ['draft', 'published'])) {
            $data['status'] = 'published';
        }

        // Guardar categorías y etiquetas seleccionadas
        $selectedCategories = $data['categories'] ?? [];
        $selectedTags = $data['tags'] ?? [];
        unset($data['categories'], $data['tags']);

        $data = self::processFormData($data);

        $errors = BlogPostRequest::validate($data);

        if (!empty($errors)) {
            flash('error', __('blog.post.error_validation', ['errors' => implode('<br>', $errors)]));
            header("Location: /musedock/blog/posts/create");
            exit;
        }

        // Creamos el post con los datos normales
        $post = BlogPost::create($data);

        // Actualizamos específicamente el tenant_id a NULL después de crear
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("UPDATE blog_posts SET tenant_id = NULL WHERE id = ?");
            $stmt->execute([$post->id]);
        } catch (\Exception $e) {
            error_log("ERROR AL ACTUALIZAR TENANT_ID EN STORE: " . $e->getMessage());
        }

        // Usamos SQL directo para crear el slug con tenant_id NULL
        try {
            // Primero eliminamos cualquier slug existente por si acaso
            $pdo = Database::connect();
            $deleteStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'blog' AND reference_id = ?");
            $deleteStmt->execute([$post->id]);

            // Creamos el nuevo slug directamente con SQL para garantizar tenant_id NULL
            $prefix = $data['prefix'] ?? 'blog';
            $insertStmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, NULL, ?)");
            $insertStmt->execute(['blog', $post->id, $data['slug'], $prefix]);

        } catch (\Exception $e) {
            error_log("ERROR AL CREAR SLUG CON TENANT_ID NULL: " . $e->getMessage());
        }

        // Sincronizar categorías
        if (!empty($selectedCategories)) {
            $post->syncCategories($selectedCategories);
        }

        // Sincronizar etiquetas
        if (!empty($selectedTags)) {
            $post->syncTags($selectedTags);
        }

        // ✅ Crear primera revisión del post
        try {
            \Blog\Models\BlogPostRevision::createFromPost($post, 'initial', 'Versión inicial del post');
        } catch (\Exception $e) {
            error_log("Error al crear revisión inicial: " . $e->getMessage());
        }

        flash('success', __('blog.post.success_created'));

        // Redirigir a la página de edición
        header("Location: /musedock/blog/posts/{$post->id}/edit");
        exit;
    }

    public function edit($id)
    {
        $this->checkPermission('blog.edit');
        // Limpiar datos 'old' al inicio
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar el post
        $post = BlogPost::find($id);
        if (!$post) {
            flash('error', __('blog.post.error_not_found'));
            header('Location: /musedock/blog/posts');
            exit;
        }

        // --- Obtener el campo de visibility si existe o establecer valor predeterminado ---
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT visibility FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['visibility'])) {
                $post->visibility = $result['visibility'];
            } else {
                $post->visibility = 'public';
            }
        } catch (\Exception $e) {
            error_log("Error al obtener visibility para post {$id}: " . $e->getMessage());
            $post->visibility = 'public';
        }

        // --- Obtener y formatear fechas ---
        $post->created_at_formatted = 'Desconocido';
        $post->updated_at_formatted = 'Desconocido';
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT created_at, updated_at FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $dates = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($dates) {
                $dateFormat = setting('date_format', 'd/m/Y');
                $timeFormat = setting('time_format', 'H:i');
                $dateTimeFormat = $dateFormat . ' ' . $timeFormat;
                if (!empty($dates['created_at'])) {
                    $timestamp_created = strtotime($dates['created_at']);
                    if ($timestamp_created !== false) {
                        $post->created_at_formatted = date($dateTimeFormat, $timestamp_created);
                    }
                }
                if (!empty($dates['updated_at'])) {
                    $timestamp_updated = strtotime($dates['updated_at']);
                    if ($timestamp_updated !== false) {
                        $post->updated_at_formatted = date($dateTimeFormat, $timestamp_updated);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error al obtener/formatear fechas para post {$id}: " . $e->getMessage());
        }

        // --- Preparar published_at ---
        if ($post->published_at && !$post->published_at instanceof \DateTimeInterface) {
            try {
                $post->published_at = new \DateTime($post->published_at);
            } catch (\Exception $e) {
                $post->published_at = null;
            }
        }

        // --- Traducciones ---
        $locales = getAvailableLocales();
        $translatedLocales = [];
        $translations = BlogPostTranslation::where('post_id', $id)->get();
        foreach ($translations as $t) {
            $translatedLocales[$t->locale] = true;
        }

        // Obtener todas las categorías disponibles
        $allCategories = BlogCategory::whereNull('tenant_id')->orderBy('name', 'ASC')->get();

        // Obtener categorías del post
        $postCategories = $post->categories();
        $postCategoryIds = array_map(fn($cat) => $cat->id, $postCategories);

        // Obtener todas las etiquetas disponibles
        $allTags = BlogTag::whereNull('tenant_id')->orderBy('name', 'ASC')->get();

        // Obtener etiquetas del post
        $postTags = $post->tags();
        $postTagIds = array_map(fn($tag) => $tag->id, $postTags);

        // --- Obtener plantillas disponibles ---
        $availableTemplates = get_blog_templates();
        $currentTemplate = $post->template ?? 'single';

        // --- Renderizar vista ---
        return View::renderSuperadmin('blog.posts.edit', [
            'title'               => 'Editar post: ' . e($post->title),
            'post'                => $post,
            'locales'             => $locales,
            'translatedLocales'   => $translatedLocales,
            'baseUrl'             => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'categories'          => $allCategories,  // Cambio: la vista espera 'categories'
            'allCategories'       => $allCategories,
            'postCategoryIds'     => $postCategoryIds,
            'availableTemplates'  => $availableTemplates,
            'currentTemplate'     => $currentTemplate,
            'tags'                => $allTags,         // Cambio: la vista espera 'tags'
            'allTags'             => $allTags,
            'postTagIds'          => $postTagIds,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission('blog.edit');
        // Iniciar sesión si es necesario
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $post = BlogPost::find($id);
        if (!$post) {
            flash('error', __('blog.post.error_not_found'));
            header("Location: /musedock/blog/posts");
            exit;
        }

        // Guardar estado anterior para detectar cambios
        $oldTitle = $post->title;
        $oldContent = $post->content;
        $oldStatus = $post->status;

        $rawData = $_POST;
        $data = $rawData;
        unset($data['_token'], $data['_csrf'], $data['_method']);

        // Manejo de checkboxes
        $data['show_hero'] = isset($data['show_hero']) ? 1 : 0;
        $data['allow_comments'] = isset($data['allow_comments']) ? 1 : 0;
        $data['featured'] = isset($data['featured']) ? 1 : 0;
        $data['hide_featured_image'] = isset($data['hide_featured_image']) ? 1 : 0;

        // Manejo de visibilidad
        $data['visibility'] = $data['visibility'] ?? 'public';

        // Validar que visibility sea uno de los valores permitidos
        if (!in_array($data['visibility'], ['public', 'private', 'password'])) {
            $data['visibility'] = 'public';
        }

        // Procesar imagen destacada
        $currentFeaturedImage = $data['current_featured_image'] ?? null;
        $removeImage = $data['remove_featured_image'] ?? '0';
        $featuredImageUrl = $data['featured_image'] ?? null; // URL del campo de texto (gestor de medios)

        // Eliminar estos campos para no guardarlos en la BD
        unset($data['current_featured_image'], $data['remove_featured_image']);

        // Si marca eliminar imagen, borramos la referencia y el archivo
        if ($removeImage === '1' && !empty($currentFeaturedImage)) {
            $fileName = basename($currentFeaturedImage);
            $fullPath = APP_ROOT . "/public/assets/uploads/blog/{$fileName}";

            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $data['featured_image'] = null;
        }

        // Procesar la nueva imagen si se sube una
        if ($_FILES && isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
            $uploadResult = $this->processFeaturedImageUpload($_FILES['featured_image'], $currentFeaturedImage);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.post.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /musedock/blog/posts/{$id}/edit");
                exit;
            }
            $data['featured_image'] = $uploadResult['path'];
        } elseif ($removeImage !== '1') {
            // Si hay una URL en el campo de texto (del gestor de medios), usarla
            // Si no, mantener la imagen actual
            $data['featured_image'] = !empty($featuredImageUrl) ? $featuredImageUrl : $currentFeaturedImage;
        }

        // Procesar imagen hero de manera similar
        $currentHeroImage = $data['current_hero_image'] ?? null;
        $removeHeroImage = $data['remove_hero_image'] ?? '0';

        unset($data['current_hero_image'], $data['remove_hero_image']);

        if ($removeHeroImage === '1' && !empty($currentHeroImage)) {
            $fileName = basename($currentHeroImage);
            $fullPath = APP_ROOT . "/public/assets/uploads/blog/hero/{$fileName}";

            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $data['hero_image'] = null;
        }

        if ($_FILES && isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] == 0) {
            $uploadResult = $this->processHeroImageUpload($_FILES['hero_image'], $currentHeroImage);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.post.error_upload_hero_image', ['error' => $uploadResult['error']]));
                header("Location: /musedock/blog/posts/{$id}/edit");
                exit;
            }
            $data['hero_image'] = $uploadResult['path'];
        } elseif ($removeHeroImage !== '1') {
            $data['hero_image'] = $currentHeroImage;
        }

        // Guardar categorías y etiquetas seleccionadas
        $selectedCategories = $data['categories'] ?? [];
        $selectedTags = $data['tags'] ?? [];
        unset($data['categories'], $data['tags']);

        // Validación
        $errors = BlogPostRequest::validate($rawData, $id);
        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.post.error_validation', ['errors' => implode('<br>', $errors)]));
            header("Location: /musedock/blog/posts/{$id}/edit");
            exit;
        }
        unset($_SESSION['_old_input']);

        // Procesar datos
        $data = self::processFormData($data);
        unset($data['tenant_id']);

        $newSlug = $data['slug'];
        $prefix = $rawData['prefix'] ?? 'blog';

        $pdo = null;

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // 1. Actualizar datos principales del post
            unset($data['prefix']);
            $post->update($data);

            // 2. Actualizar tenant_id
            $updateCurrentStmt = $pdo->prepare(
                "UPDATE blog_posts SET tenant_id = NULL WHERE id = ?"
            );
            $updateCurrentStmt->execute([$id]);

            // 3. Actualizar slug
            $deleteSlugStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'blog' AND reference_id = ?");
            $deleteSlugStmt->execute([$id]);

            $insertSlugStmt = $pdo->prepare(
                "INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, NULL, ?)"
            );
            $insertSlugStmt->execute(['blog', $id, $newSlug, $prefix]);

            // 4. Sincronizar categorías
            $post->syncCategories($selectedCategories);

            // 5. Sincronizar etiquetas
            $post->syncTags($selectedTags);

            $pdo->commit();

            // ✅ Crear revisión después de actualizar exitosamente
            try {
                // Detectar cambios
                $changes = [];
                if ($oldTitle !== $post->title) $changes[] = 'título';
                if ($oldContent !== $post->content) $changes[] = 'contenido';
                if ($oldStatus !== $data['status']) $changes[] = 'status';

                $summary = !empty($changes)
                    ? 'Modificó: ' . implode(', ', $changes)
                    : 'Actualización de metadatos';

                \Blog\Models\BlogPostRevision::createFromPost($post, 'manual', $summary);
            } catch (\Exception $revError) {
                error_log("Error al crear revisión: " . $revError->getMessage());
            }

        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("ERROR en transacción update post {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.post.error_update', ['error' => $e->getMessage()]));
            header("Location: /musedock/blog/posts/{$id}/edit");
            exit;
        }

        flash('success', __('blog.post.success_updated'));
        header("Location: /musedock/blog/posts/{$id}/edit");
        exit;
    }

    /**
     * Procesa los datos del formulario antes de guardarlos
     */
    private static function processFormData($data)
    {
        // Gestionar campos opcionales (content NO es opcional según la BD)
        $optionalFields = [
            'excerpt', 'seo_title', 'seo_description',
            'seo_keywords', 'canonical_url', 'robots_directive',
            'twitter_title', 'twitter_description', 'hero_title', 'hero_content'
        ];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // IMPORTANTE: content es NOT NULL en la BD, asegurar que siempre tenga valor
        if (!isset($data['content']) || $data['content'] === null || trim($data['content']) === '') {
            $data['content'] = '';  // String vacío en lugar de null
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
        if (!in_array($data['visibility'], ['public', 'private', 'password'])) {
            $data['visibility'] = 'public';
        }

        return $data;
    }

    /**
     * Procesa la subida de imagen destacada
     */
    private function processFeaturedImageUpload($file, $currentImage = null)
    {
        // 🔒 SECURITY: Validación robusta de archivos de imagen
        // Previene: Polyglot file upload, MIME spoofing, RCE
        $validation = FileUploadValidator::validateImage($file, 10 * 1024 * 1024);

        if (!$validation['valid']) {
            return ['error' => $validation['error']];
        }

        // Dimensiones deseadas para imagen destacada
        $targetWidth = 800;
        $targetHeight = 600;

        // Configuración
        $uploadDir = APP_ROOT . '/public/assets/uploads/blog/';
        $relativePath = 'uploads/blog/';

        // Crear directorio si no existe
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => "Error al crear el directorio: " . $uploadDir];
            }
        }

        // Generar nombre seguro usando el helper
        $extension = $validation['extension'];
        $filename = FileUploadValidator::generateSecureFilename($extension, 'featured');
        $fullPath = $uploadDir . $filename;

        // Verificar si es un formato compatible
        $isUnsupportedFormat = !in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        if ($isUnsupportedFormat) {
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                return ['error' => "Error al mover el archivo subido"];
            }
        } else {
            try {
                // Crear imagen temporal
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

                // Calcular proporciones
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

                // Redimensionar y recortar
                imagecopyresampled(
                    $targetImage, $sourceImage,
                    0, 0, $srcX, $srcY,
                    $targetWidth, $targetHeight, $newWidth, $newHeight
                );

                // Guardar la imagen
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

                // Liberar memoria
                imagedestroy($sourceImage);
                imagedestroy($targetImage);

            } catch (\Exception $e) {
                return ['error' => "Error al procesar la imagen: " . $e->getMessage()];
            }
        }

        // Eliminar imagen anterior si existe
        if ($currentImage && !empty($currentImage)) {
            $oldPath = APP_ROOT . '/public/' . $currentImage;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        return ['path' => $relativePath . $filename];
    }

    /**
     * Procesa la subida de imagen hero
     */
    private function processHeroImageUpload($file, $currentImage = null)
    {
        // 🔒 SECURITY: Validación robusta de archivos de imagen
        // Previene: Polyglot file upload, MIME spoofing, RCE
        $validation = FileUploadValidator::validateImage($file, 10 * 1024 * 1024);

        if (!$validation['valid']) {
            return ['error' => $validation['error']];
        }

        // Dimensiones para imagen hero
        $targetWidth = 1920;
        $targetHeight = 600;

        $uploadDir = APP_ROOT . '/public/assets/uploads/blog/hero/';
        $relativePath = 'uploads/blog/hero/';

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => "Error al crear el directorio: " . $uploadDir];
            }
        }

        // Generar nombre seguro usando el helper
        $extension = $validation['extension'];
        $filename = FileUploadValidator::generateSecureFilename($extension, 'hero');
        $fullPath = $uploadDir . $filename;

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
        // Verificar que el post existe
        $post = BlogPost::find($id);
        if (!$post) {
            flash('error', __('blog.post.error_not_found'));
            header('Location: /musedock/blog/posts');
            exit;
        }

        try {
            $pdo = Database::connect();
            $user = $_SESSION['super_admin'] ?? null;

            // ✅ NUEVO: Mover a papelera en lugar de eliminar permanentemente
            // 1. Cambiar status a 'trash' usando SQL directo (más confiable)
            $updateStmt = $pdo->prepare("
                UPDATE blog_posts
                SET status = 'trash', updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$post->id]);

            // 2. Registrar en tabla blog_posts_trash
            $insertStmt = $pdo->prepare("
                INSERT INTO blog_posts_trash
                (post_id, tenant_id, deleted_by, deleted_by_name, deleted_by_type, deleted_at, scheduled_permanent_delete, ip_address)
                VALUES (?, NULL, ?, ?, 'superadmin', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), ?)
            ");

            $insertStmt->execute([
                $post->id,
                $user['id'] ?? null,
                $user['name'] ?? 'Sistema',
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            // 3. Actualizar el objeto en memoria para la revisión
            $post->status = 'trash';

            // 4. Crear revisión
            try {
                \Blog\Models\BlogPostRevision::createFromPost($post, 'manual', 'Movido a papelera');
            } catch (\Exception $revError) {
                error_log("Error al crear revisión de papelera: " . $revError->getMessage());
            }

            // 4. Actualizar contadores de categorías y etiquetas
            $this->updateAllCategoryCounts();
            $this->updateAllTagCounts();

            flash('success', __('blog.post.success_moved_to_trash'));
        } catch (\Exception $e) {
            error_log("Error al mover post a papelera: " . $e->getMessage());
            flash('error', __('blog.post.error_delete', ['error' => $e->getMessage()]));
        }

        header('Location: /musedock/blog/posts');
        exit;
    }

    public function bulk()
    {
        $this->checkPermission('blog.edit');
        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('blog.post.error_bulk_no_selection'));
            header('Location: /musedock/blog/posts');
            exit;
        }

        // Verificar permisos según la acción
        if ($action === 'delete') {
            $this->checkPermission('blog.delete');
        } else {
            // edit, draft, published, etc. requieren permiso de editar
            $this->checkPermission('blog.edit.all');
        }

        if ($action === 'delete') {
            $deletedCount = 0;

            foreach ($selected as $id) {
                $post = BlogPost::find($id);

                if ($post) {
                    try {
                        $pdo = Database::connect();

                        // Eliminar imágenes
                        if (!empty($post->featured_image)) {
                            $fileName = basename($post->featured_image);
                            $fullPath = APP_ROOT . "/public/assets/uploads/blog/{$fileName}";
                            if (file_exists($fullPath)) {
                                @unlink($fullPath);
                            }
                        }

                        if (!empty($post->hero_image)) {
                            $fileName = basename($post->hero_image);
                            $fullPath = APP_ROOT . "/public/assets/uploads/blog/hero/{$fileName}";
                            if (file_exists($fullPath)) {
                                @unlink($fullPath);
                            }
                        }

                        // Eliminar relaciones
                        $stmt = $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?");
                        $stmt->execute([$id]);

                        $stmt = $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?");
                        $stmt->execute([$id]);

                        // Eliminar traducciones
                        BlogPostTranslation::where('post_id', $id)->delete();

                        // Eliminar slug
                        $stmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'blog' AND reference_id = ?");
                        $stmt->execute([$id]);

                        // Eliminar post
                        $post->delete();
                        $deletedCount++;
                    } catch (\Exception $e) {
                        error_log("Error al eliminar post #{$id}: " . $e->getMessage());
                        continue;
                    }
                }
            }

            // Actualizar contadores
            $this->updateAllCategoryCounts();
            $this->updateAllTagCounts();

            flash('success', __('blog.post.success_bulk_deleted', ['count' => $deletedCount]));
            header('Location: /musedock/blog/posts');
            exit;
        }

        if (in_array($action, ['draft', 'published'])) {
            foreach ($selected as $id) {
                $post = BlogPost::find($id);
                if ($post) {
                    $post->status = $action;
                    $post->save();
                }
            }

            flash('success', __('blog.post.success_bulk_status_updated'));
            header('Location: /musedock/blog/posts');
            exit;
        }

        if (in_array($action, ['public', 'private', 'password'])) {
            foreach ($selected as $id) {
                $post = BlogPost::find($id);
                if ($post) {
                    $post->visibility = $action;
                    $post->save();
                }
            }

            flash('success', __('blog.post.success_bulk_visibility_updated'));
            header('Location: /musedock/blog/posts');
            exit;
        }

        flash('error', __('blog.post.error_bulk_invalid_action'));
        header('Location: /musedock/blog/posts');
        exit;
    }

    public function editTranslation($id, $locale)
    {
        $this->checkPermission('blog.edit');
        $post = BlogPost::find($id);
        if (!$post) {
            flash('error', __('blog.post.error_base_post_not_found'));
            header('Location: /musedock/blog/posts');
            exit;
        }

        // Intentar encontrar la traducción existente
        $translation = BlogPostTranslation::where('post_id', $id)
            ->where('locale', $locale)
            ->first();

        // Si no existe, creamos una instancia vacía para el formulario
        $isNewTranslation = false;
        if (!$translation) {
            $translation = new BlogPostTranslation([
                'post_id' => $id,
                'locale' => $locale,
                'tenant_id' => $post->tenant_id,
            ]);
            $isNewTranslation = true;
        }

        // Obtener el nombre del idioma
        $localeName = getAvailableLocales()[$locale] ?? strtoupper($locale);

        return View::renderSuperadmin('blog.posts.translation_edit', [
            'title'       => $isNewTranslation
                                ? "Crear Traducción ({$localeName}) para \"{$post->title}\""
                                : "Editar Traducción ({$localeName}) para \"{$post->title}\"",
            'post'        => $post,
            'translation' => $translation,
            'locale'      => $locale,
            'localeName'  => $localeName
        ]);
    }

    public function updateTranslation($id, $locale)
    {
        $this->checkPermission('blog.edit');
        $post = BlogPost::find($id);
        if (!$post) {
            flash('error', __('blog.post.error_base_post_not_found'));
            header('Location: /musedock/blog/posts');
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_method'], $data['_csrf']);

        $data['post_id'] = $id;
        $data['locale'] = $locale;

        // Limpiar campos opcionales vacíos
        $optionalFields = [
            'content', 'excerpt',
            'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
            'canonical_url', 'robots_directive', 'twitter_title',
            'twitter_description', 'twitter_image'
        ];
        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM blog_post_translations WHERE post_id = ? AND locale = ? LIMIT 1");
            $stmt->execute([$id, $locale]);
            $existingTranslation = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existingTranslation) {
                // Actualizar traducción existente
                $allowedColumns = [
                    'title', 'excerpt', 'content', 'seo_title', 'seo_description',
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

                $updateStmt = $pdo->prepare("UPDATE blog_post_translations SET {$setString} WHERE post_id = ? AND locale = ?");
                $params[] = $id;
                $params[] = $locale;
                $updateStmt->execute($params);

                flash('success', __('blog.post.success_translation_updated'));
            } else {
                // Crear nueva traducción
                $allowedColumns = [
                    'post_id', 'locale', 'tenant_id', 'title', 'excerpt', 'content',
                    'seo_title', 'seo_description', 'seo_keywords', 'seo_image',
                    'canonical_url', 'robots_directive', 'twitter_title',
                    'twitter_description', 'twitter_image', 'created_at', 'updated_at'
                ];

                $insertData = [
                    'post_id' => $id,
                    'locale' => $locale,
                    'tenant_id' => $post->tenant_id,
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

                $insertStmt = $pdo->prepare("INSERT INTO blog_post_translations ({$columns}) VALUES ({$placeholders})");
                $insertStmt->execute(array_values($insertData));

                flash('success', __('blog.post.success_translation_created'));
            }
        } catch (\Exception $e) {
            error_log("Error al guardar traducción para post {$id}, locale {$locale}: " . $e->getMessage());
            flash('error', __('blog.post.error_translation_save', ['error' => $e->getMessage()]));
            header("Location: /musedock/blog/posts/{$id}/translations/{$locale}");
            exit;
        }

        header("Location: /musedock/blog/posts/{$id}/translations/{$locale}");
        exit;
    }

    /**
     * Actualiza los contadores de todas las categorías
     */
    private function updateAllCategoryCounts()
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
            ");
            $stmt->execute();
        } catch (\Exception $e) {
            error_log("Error al actualizar contadores de categorías: " . $e->getMessage());
        }
    }

    /**
     * Actualiza los contadores de todas las etiquetas
     */
    private function updateAllTagCounts()
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                UPDATE blog_tags t
                SET post_count = (
                    SELECT COUNT(*)
                    FROM blog_post_tags pt
                    WHERE pt.tag_id = t.id
                )
            ");
            $stmt->execute();
        } catch (\Exception $e) {
            error_log("Error al actualizar contadores de etiquetas: " . $e->getMessage());
        }
    }

    // ================================================================
    // 📚 SISTEMA DE VERSIONES/REVISIONES
    // ================================================================

    /**
     * Mostrar historial de revisiones de un post
     */
    public function revisions($id)
    {
        $this->checkPermission('blog.view');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $post = BlogPost::find($id);
        if (!$post) {
            flash('error', __('blog.post.error_not_found'));
            header("Location: /musedock/blog/posts");
            exit;
        }

        // Obtener revisiones
        $revisions = \Blog\Models\BlogPostRevision::getPostRevisions($id, 100);

        return View::renderSuperadmin('blog.posts.revisions', [
            'title' => 'Historial de revisiones: ' . e($post->title),
            'post' => $post,
            'revisions' => $revisions,
        ]);
    }

    /**
     * Restaurar una revisión específica
     */
    public function restoreRevision($postId, $revisionId)
    {
        $this->checkPermission('blog.edit');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $revision = \Blog\Models\BlogPostRevision::findWithTenant((int)$revisionId, null);

        if (!$revision || $revision->post_id != $postId) {
            flash('error', __('blog.post.error_revision_not_found'));
            header("Location: /musedock/blog/posts/{$postId}/revisions");
            exit;
        }

        if ($revision->restore()) {
            flash('success', __('blog.post.success_revision_restored', ['date' => $revision->created_at]));
        } else {
            flash('error', __('blog.post.error_revision_restore'));
        }

        header("Location: /musedock/blog/posts/{$postId}/edit");
        exit;
    }

    /**
     * Vista previa de una revisión
     */
    public function previewRevision($postId, $revisionId)
    {
        $this->checkPermission('blog.view');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $revision = \Blog\Models\BlogPostRevision::find((int)$revisionId);

        if (!$revision || $revision->post_id != $postId) {
            flash('error', __('blog.post.error_revision_not_found'));
            header("Location: /musedock/blog/posts/{$postId}/revisions");
            exit;
        }

        return View::renderSuperadmin('blog.posts.preview-revision', [
            'title' => 'Preview: ' . e($revision->title),
            'revision' => $revision,
            'post' => BlogPost::find($postId),
        ]);
    }

    /**
     * Comparar dos revisiones
     */
    public function compareRevisions($postId, $id1, $id2)
    {
        $this->checkPermission('blog.view');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $revision1 = \Blog\Models\BlogPostRevision::find((int)$id1);
        $revision2 = \Blog\Models\BlogPostRevision::find((int)$id2);

        if (!$revision1 || !$revision2 || $revision1->post_id != $postId || $revision2->post_id != $postId) {
            flash('error', __('blog.post.error_revisions_not_found'));
            header("Location: /musedock/blog/posts/{$postId}/revisions");
            exit;
        }

        $diff = $revision1->diffWith($revision2);

        return View::renderSuperadmin('blog.posts.compare-revisions', [
            'title' => 'Comparar revisiones',
            'post' => BlogPost::find($postId),
            'revision1' => $revision1,
            'revision2' => $revision2,
            'diff' => $diff,
        ]);
    }

    /**
     * Mostrar papelera de posts
     */
    public function trash()
    {
        $this->checkPermission('blog.view');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // Obtener posts en papelera
        $posts = BlogPost::where('status', 'trash')
                        ->whereNull('tenant_id')
                        ->orderBy('updated_at', 'DESC')
                        ->get();

        // Obtener info de papelera
        $pdo = Database::connect();
        $trashInfo = [];
        foreach ($posts as $post) {
            $stmt = $pdo->prepare("SELECT * FROM blog_posts_trash WHERE post_id = ? LIMIT 1");
            $stmt->execute([$post->id]);
            $trashInfo[$post->id] = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return View::renderSuperadmin('blog.posts.trash', [
            'title' => 'Papelera de posts',
            'posts' => $posts,
            'trashInfo' => $trashInfo,
        ]);
    }

    /**
     * Restaurar un post desde la papelera
     */
    public function restoreFromTrash($id)
    {
        $this->checkPermission('blog.edit');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $pdo = Database::connect();
        $postId = (int)$id;

        // Verificar que el post existe en papelera
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE id = ? AND status = 'trash' AND (tenant_id IS NULL OR tenant_id = 0)");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            flash('error', __('blog.post.error_not_found_in_trash'));
            header("Location: /musedock/blog/posts/trash");
            exit;
        }

        // Restaurar usando SQL directo
        $updateSql = "UPDATE blog_posts SET status = 'draft', updated_at = NOW() WHERE id = {$postId}";
        $pdo->exec($updateSql);

        // Eliminar de papelera
        $stmt = $pdo->prepare("DELETE FROM blog_posts_trash WHERE post_id = ?");
        $stmt->execute([$postId]);

        // Crear revisión - cargar el modelo BlogPost para esto
        $postModel = BlogPost::find($postId);
        if ($postModel) {
            \Blog\Models\BlogPostRevision::createFromPost($postModel, 'restored', 'Restaurado desde papelera');
        }

        flash('success', __('blog.post.success_restored'));
        header("Location: /musedock/blog/posts/{$postId}/edit");
        exit;
    }

    /**
     * Eliminar permanentemente un post
     */
    public function forceDelete($id)
    {
        $this->checkPermission('blog.delete');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $post = BlogPost::where('id', $id)
                        ->where('status', 'trash')
                        ->first();

        if (!$post) {
            flash('error', __('blog.post.error_not_found_in_trash'));
            header("Location: /musedock/blog/posts/trash");
            exit;
        }

        try {
            $pdo = Database::connect();

            // Eliminar revisiones
            $stmt = $pdo->prepare("DELETE FROM blog_post_revisions WHERE post_id = ?");
            $stmt->execute([$id]);

            // Eliminar de papelera
            $stmt = $pdo->prepare("DELETE FROM blog_posts_trash WHERE post_id = ?");
            $stmt->execute([$id]);

            // Eliminar relaciones
            $stmt = $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?");
            $stmt->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?");
            $stmt->execute([$id]);

            // Eliminar traducciones
            $stmt = $pdo->prepare("DELETE FROM blog_post_translations WHERE post_id = ?");
            $stmt->execute([$id]);

            // Eliminar post
            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);

            flash('success', __('blog.post.success_permanently_deleted'));
        } catch (\Exception $e) {
            error_log("Error al eliminar permanentemente el post: " . $e->getMessage());
            flash('error', __('blog.post.error_delete', ['error' => '']));
        }

        header("Location: /musedock/blog/posts/trash");
        exit;
    }

    /**
     * Autoguardar un post (AJAX endpoint)
     */
    public function autosave($id)
    {
        $this->checkPermission('blog.edit');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        header('Content-Type: application/json');

        $post = BlogPost::find($id);
        if (!$post) {
            echo json_encode(['success' => false, 'message' => 'Post no encontrado']);
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
            // Actualizar campos del post
            if (isset($data['title'])) $post->title = $data['title'];
            if (isset($data['content'])) $post->content = $data['content'];
            if (isset($data['excerpt'])) $post->excerpt = $data['excerpt'];

            $post->updated_at = date('Y-m-d H:i:s');
            $post->save();

            // Crear revisión de tipo autosave
            \Blog\Models\BlogPostRevision::createFromPost($post, 'autosave', 'Autoguardado');

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
