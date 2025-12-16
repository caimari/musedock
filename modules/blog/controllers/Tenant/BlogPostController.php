<?php

namespace Blog\Controllers\Tenant;

use Screenart\Musedock\View;
use Blog\Models\BlogPost;
use Blog\Models\BlogPostMeta;
use Blog\Models\BlogCategory;
use Blog\Models\BlogTag;
use Blog\Models\BlogPostTranslation;
use Screenart\Musedock\Models\User;
use Screenart\Musedock\Services\TenantManager;
use Blog\Requests\BlogPostRequest;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\AuditLogger;
use Screenart\Musedock\Helpers\FileUploadValidator;
use Screenart\Musedock\Traits\RequiresPermission;

class BlogPostController
{
    use RequiresPermission;

    /**
     * Verificar si el usuario actual tiene un permiso específico
     * Si no lo tiene, redirige con mensaje de error
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __('blog.post.error_no_permission'));
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    /**
     * Listado de posts del tenant
     */
    public function index()
    {
        $this->checkPermission('blog.view');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        // Capturar parámetros de búsqueda y paginación
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        // Limitar longitud de búsqueda a 255 caracteres
        if (strlen($search) > 255) {
            $search = substr($search, 0, 255);
        }
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 10;
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        // Consulta de posts SOLO del tenant actual (excluyendo papelera)
        $query = BlogPost::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw("(status != ? OR status IS NULL)", ['trash'])
            ->orderBy('updated_at', 'DESC');

        // Aplicar búsqueda si existe
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $query->whereRaw("
                (title LIKE ? OR slug LIKE ? OR content LIKE ?)
            ", [$searchTerm, $searchTerm, $searchTerm]);
        }

        // Paginación con límite máximo
        $maxLimit = 1000;
        if ($perPage == -1) {
            $totalCount = $query->count();
            if ($totalCount > $maxLimit) {
                flash('warning', __('blog.post.warning_max_limit_reached', ['total' => $totalCount, 'max' => $maxLimit]));
                $posts = $query->limit($maxLimit)->get();
            } else {
                $posts = $query->get();
            }
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
            $pagination = $query->paginate($perPage, $currentPage);
            $posts = $pagination['items'] ?? [];
        }

        // Procesar posts y cargar datos adicionales
        $processedPosts = [];

        try {
            $pdo = Database::connect();

            foreach ($posts as $postData) {
                $post = ($postData instanceof BlogPost) ? $postData : new BlogPost((array) $postData);

                // Cargar visibilidad
                $stmt = $pdo->prepare("SELECT visibility FROM blog_posts WHERE id = ? LIMIT 1");
                $stmt->execute([$post->id]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result && isset($result['visibility'])) {
                    $post->visibility = $result['visibility'];
                }

                // Precargar categorías para el index
                try {
                    $post->categories = $post->categories();
                } catch (\Throwable $e) {
                    $post->categories = [];
                }

                $processedPosts[] = $post;
            }
        } catch (\Exception $e) {
            error_log("Error al cargar datos adicionales: " . $e->getMessage());
            $processedPosts = array_map(fn($row) => ($row instanceof BlogPost) ? $row : new BlogPost((array) $row), $posts);
        }

        // Cargar autores (soporta admin/user)
        $authors = [];
        foreach ($processedPosts as $post) {
            $userId = $post->user_id ?? null;
            if ($userId && !isset($authors[$userId])) {
                try {
                    $authors[$userId] = method_exists($post, 'getAuthor') ? $post->getAuthor() : User::find($userId);
                } catch (\Throwable $e) {
                    $authors[$userId] = User::find($userId);
                }
            }
        }

        return View::renderTenantAdmin('blog.posts.index', [
            'title'       => 'Listado de posts del blog',
            'posts'       => $processedPosts,
            'authors'     => $authors,
            'search'      => $search,
            'pagination'  => $pagination,
        ]);
    }

    /**
     * Formulario para crear nuevo post
     */
    public function create()
    {
        $this->checkPermission('blog.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }

        // Obtener categorías del tenant
        $categories = BlogCategory::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        // Obtener etiquetas del tenant
        $tags = BlogTag::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        // Obtener plantillas disponibles
        $availableTemplates = get_blog_templates();
        $currentTemplate = 'page';

        return View::renderTenantAdmin('blog.posts.create', [
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

    /**
     * Guardar nuevo post
     */
    public function store()
    {
        $this->checkPermission('blog.create');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        $rawData = $_POST;
        $data = $rawData;
        unset($data['_token'], $data['_csrf']);

        // Asignar tenant_id actual
        $data['tenant_id'] = $tenantId;

        // Usuario actual del tenant (admin)
        $data['user_id'] = $_SESSION['admin']['id'] ?? null;
        $data['user_type'] = 'admin';

        // Manejo de checkboxes
        $data['show_hero'] = isset($data['show_hero']) ? 1 : 0;
        $data['allow_comments'] = isset($data['allow_comments']) ? 1 : 0;
        $data['featured'] = isset($data['featured']) ? 1 : 0;
        $data['hide_featured_image'] = isset($data['hide_featured_image']) ? 1 : 0;
        $data['hide_title'] = isset($data['hide_title']) ? 1 : 0;

        // Visibilidad
        $data['visibility'] = $data['visibility'] ?? 'public';
        if (!in_array($data['visibility'], ['public', 'private', 'password'])) {
            $data['visibility'] = 'public';
        }

        // Procesar imagen destacada
        if ($_FILES && isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
            $uploadResult = $this->processFeaturedImageUpload($_FILES['featured_image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.post.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /" . admin_path() . "/blog/posts/create");
                exit;
            }
            $data['featured_image'] = $uploadResult['path'];
        }

        // Procesar imagen hero
        if ($_FILES && isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] == 0) {
            $uploadResult = $this->processHeroImageUpload($_FILES['hero_image']);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.post.error_upload_hero_image', ['error' => $uploadResult['error']]));
                header("Location: /" . admin_path() . "/blog/posts/create");
                exit;
            }
            $data['hero_image'] = $uploadResult['path'];
        }

        // Estado por defecto
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
            header("Location: /" . admin_path() . "/blog/posts/create");
            exit;
        }

        // Crear el post
        $post = BlogPost::create($data);

        // 🔒 SECURITY: Audit log - registrar creación de post
        AuditLogger::log('blog_post.created', 'blog_post', $post->id, [
            'title' => $data['title'] ?? '',
            'slug' => $data['slug'] ?? '',
            'status' => $data['status'] ?? 'draft',
            'tenant_id' => $tenantId
        ]);

        // Crear slug con tenant_id
        try {
            $pdo = Database::connect();
            $prefix = $data['prefix'] ?? 'blog';

            $insertStmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute(['blog', $post->id, $data['slug'], $tenantId, $prefix]);

        } catch (\Exception $e) {
            error_log("ERROR AL CREAR SLUG: " . $e->getMessage());
        }

        // Sincronizar categorías
        if (!empty($selectedCategories)) {
            $post->syncCategories($selectedCategories);
        }

        // Sincronizar etiquetas
        if (!empty($selectedTags)) {
            $post->syncTags($selectedTags);
        }

        // Actualizar contadores (incluye categorías/tags removidos)
        $this->updateAllCategoryCounts($tenantId);
        $this->updateAllTagCounts($tenantId);

        // ✅ Crear primera revisión del post
        try {
            \Blog\Models\BlogPostRevision::createFromPost($post, 'initial', 'Versión inicial del post');
        } catch (\Exception $e) {
            error_log("Error al crear revisión inicial: " . $e->getMessage());
        }

        flash('success', __('blog.post.success_created'));
        header("Location: /" . admin_path() . "/blog/posts/{$post->id}/edit");
        exit;
    }

    /**
     * Formulario para editar post
     */
    public function edit($id)
    {
        $this->checkPermission('blog.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        // Limpiar datos 'old'
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        unset($_SESSION['_old_input']);

        // Buscar post SOLO del tenant actual
        $post = BlogPost::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$post) {
            flash('error', __('blog.post.error_not_found_or_no_permission'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        // Convertir a objeto BlogPost si es array o stdClass
        if (is_array($post) || $post instanceof \stdClass) {
            $post = new BlogPost($post);
        }

        // Cargar visibilidad
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
            error_log("Error al obtener visibility: " . $e->getMessage());
            $post->visibility = 'public';
        }

        // Formatear fechas
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
            error_log("Error al formatear fechas: " . $e->getMessage());
        }

        // Preparar published_at
        if ($post->published_at && !$post->published_at instanceof \DateTimeInterface) {
            try {
                $post->published_at = new \DateTime($post->published_at);
            } catch (\Exception $e) {
                $post->published_at = null;
            }
        }

        // Traducciones
        $locales = getAvailableLocales();
        $translatedLocales = [];
        $translations = BlogPostTranslation::where('post_id', $id)->get();

        foreach ($translations as $t) {
            $translatedLocales[$t->locale] = true;
        }

        // Obtener todas las categorías del tenant
        $allCategories = BlogCategory::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        // Obtener categorías del post
        $postCategories = $post->categories();
        $postCategoryIds = array_map(fn($cat) => $cat->id, $postCategories);

        // Obtener todas las etiquetas del tenant
        $allTags = BlogTag::where('tenant_id', $tenantId)->orderBy('name', 'ASC')->get();

        // Obtener etiquetas del post
        $postTags = $post->tags();
        $postTagIds = array_map(fn($tag) => $tag->id, $postTags);

        // Obtener plantillas disponibles
        $availableTemplates = get_blog_templates();
        $currentTemplate = $post->template ?? 'page';

        return View::renderTenantAdmin('blog.posts.edit', [
            'title'               => 'Editar post: ' . e($post->title),
            'post'                => $post,
            'locales'             => $locales,
            'translatedLocales'   => $translatedLocales,
            'baseUrl'             => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'categories'          => $allCategories,  // Cambio: la vista espera 'categories'
            'allCategories'       => $allCategories,
            'postCategoryIds'     => $postCategoryIds,
            'tags'                => $allTags,         // Cambio: la vista espera 'tags'
            'allTags'             => $allTags,
            'postTagIds'          => $postTagIds,
            'availableTemplates'  => $availableTemplates,
            'currentTemplate'     => $currentTemplate,
        ]);
    }

    /**
     * Actualizar post
     */
    public function update($id)
    {
        $this->checkPermission('blog.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // Buscar post SOLO del tenant actual
        $post = BlogPost::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$post) {
            flash('error', __('blog.post.error_not_found_or_no_permission'));
            header("Location: /" . admin_path() . "/blog/posts");
            exit;
        }

        // Convertir a objeto BlogPost si es array o stdClass
        if (is_array($post) || $post instanceof \stdClass) {
            $post = new BlogPost($post);
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
        $data['hide_title'] = isset($data['hide_title']) ? 1 : 0;

        // Visibilidad
        $data['visibility'] = $data['visibility'] ?? 'public';
        if (!in_array($data['visibility'], ['public', 'private', 'password'])) {
            $data['visibility'] = 'public';
        }

        // Procesar imagen destacada
        $currentFeaturedImage = $data['current_featured_image'] ?? null;
        $removeImage = $data['remove_featured_image'] ?? '0';
        unset($data['current_featured_image'], $data['remove_featured_image']);

        if ($removeImage === '1' && !empty($currentFeaturedImage)) {
            $fileName = basename($currentFeaturedImage);
            $fullPath = APP_ROOT . "/public/assets/uploads/blog/{$fileName}";

            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $data['featured_image'] = null;
        }

        // Nueva imagen destacada
        if ($_FILES && isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
            $uploadResult = $this->processFeaturedImageUpload($_FILES['featured_image'], $currentFeaturedImage);
            if (isset($uploadResult['error'])) {
                flash('error', __('blog.post.error_upload_image', ['error' => $uploadResult['error']]));
                header("Location: /" . admin_path() . "/blog/posts/{$id}/edit");
                exit;
            }
            $data['featured_image'] = $uploadResult['path'];
        } elseif ($removeImage !== '1') {
            $data['featured_image'] = $currentFeaturedImage;
        }

        // Procesar imagen hero
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
            error_log("HERO_IMAGE: Nueva imagen detectada - " . $_FILES['hero_image']['name']);
            $uploadResult = $this->processHeroImageUpload($_FILES['hero_image'], $currentHeroImage);
            if (isset($uploadResult['error'])) {
                error_log("HERO_IMAGE: Error en upload - " . $uploadResult['error']);
                flash('error', __('blog.post.error_upload_hero_image', ['error' => $uploadResult['error']]));
                header("Location: /" . admin_path() . "/blog/posts/{$id}/edit");
                exit;
            }
            $data['hero_image'] = $uploadResult['path'];
            error_log("HERO_IMAGE: Path guardado en data - " . $data['hero_image']);
        } elseif ($removeHeroImage !== '1') {
            $data['hero_image'] = $currentHeroImage;
            error_log("HERO_IMAGE: Manteniendo imagen actual - " . ($currentHeroImage ?? 'null'));
        } else {
            error_log("HERO_IMAGE: Imagen marcada para eliminación");
        }

        // Guardar categorías y etiquetas seleccionadas
        $selectedCategories = $data['categories'] ?? [];
        $selectedTags = $data['tags'] ?? [];
        unset($data['categories'], $data['tags']);

        // Asegurar que tenant_id no cambie
        $data['tenant_id'] = $tenantId;
        $data['content'] = $data['content'] ?? '';
        if ($data['content'] === null) {
            $data['content'] = '';
        }

        // Validación
        $errors = BlogPostRequest::validate($rawData, $id);
        if (!empty($errors)) {
            $_SESSION['_old_input'] = $rawData;
            flash('error', __('blog.post.error_validation', ['errors' => implode('<br>', $errors)]));
            header("Location: /" . admin_path() . "/blog/posts/{$id}/edit");
            exit;
        }
        unset($_SESSION['_old_input']);

        // Procesar datos
        $data = self::processFormData($data);
        if (!isset($data['content']) || $data['content'] === null) {
            $data['content'] = '';
        }

        $newSlug = $data['slug'];
        $prefix = $rawData['prefix'] ?? 'blog';

        $pdo = null;

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // Actualizar datos principales del post
            unset($data['prefix']);
            error_log("HERO_IMAGE: Antes de update - data[hero_image] = " . ($data['hero_image'] ?? 'NO SET'));
            error_log("HERO_IMAGE: Antes de update - data keys = " . implode(', ', array_keys($data)));
            $post->update($data);
            error_log("HERO_IMAGE: Después de update - post->hero_image = " . ($post->hero_image ?? 'null'));

            // Actualizar slug
            $deleteSlugStmt = $pdo->prepare("DELETE FROM slugs WHERE module = 'blog' AND reference_id = ?");
            $deleteSlugStmt->execute([$id]);

            $insertSlugStmt = $pdo->prepare(
                "INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)"
            );
            $insertSlugStmt->execute(['blog', $id, $newSlug, $tenantId, $prefix]);

            // Sincronizar categorías
            $post->syncCategories($selectedCategories);

            // Sincronizar etiquetas
            $post->syncTags($selectedTags);

            $pdo->commit();

            // Actualizar contadores (incluye categorías/tags removidos)
            $this->updateAllCategoryCounts($tenantId);
            $this->updateAllTagCounts($tenantId);

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

            // 🔒 SECURITY: Audit log - registrar actualización de post
            AuditLogger::log('blog_post.updated', 'blog_post', $id, [
                'title' => $data['title'] ?? '',
                'slug' => $newSlug,
                'status' => $data['status'] ?? '',
                'tenant_id' => $tenantId
            ]);

        } catch (\Throwable $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("ERROR en transacción update post {$id}: " . $e->getMessage());
            $_SESSION['_old_input'] = $rawData;
            $sqlState = (string) $e->getCode();
            $isUnique = in_array($sqlState, ['23505', '23000'], true)
                || stripos($e->getMessage(), 'duplicate') !== false
                || stripos($e->getMessage(), 'unique') !== false;

            if ($isUnique) {
                flash('error', 'El slug ya está en uso.');
            } else {
                $message = config('debug', false)
                    ? ('Error al actualizar el post: ' . $e->getMessage())
                    : __('blog.post.error_update');
                flash('error', $message);
            }
            header("Location: /" . admin_path() . "/blog/posts/{$id}/edit");
            exit;
        }

        flash('success', __('blog.post.success_updated'));
        header("Location: /" . admin_path() . "/blog/posts/{$id}/edit");
        exit;
    }

    /**
     * Eliminar post
     */
    public function destroy($id)
    {
        $this->checkPermission('blog.delete');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        // Buscar post SOLO del tenant actual
        $post = BlogPost::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$post) {
            flash('error', __('blog.post.error_not_found_or_no_permission'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        try {
            $pdo = Database::connect();
            $user = $_SESSION['admin'] ?? null;
            $postId = (int)$id;

            // ✅ UPDATE directo para cambiar status a trash
            $updateSql = "UPDATE blog_posts SET status = 'trash', updated_at = NOW() WHERE id = {$postId} AND tenant_id = {$tenantId}";
            $affectedRows = $pdo->exec($updateSql);

            if ($affectedRows === 0) {
                flash('error', __('blog.post.error_not_found_or_no_permission'));
                header('Location: /' . admin_path() . '/blog/posts');
                exit;
            }

            // Verificar que el UPDATE funcionó
            $checkSql = "SELECT id, status FROM blog_posts WHERE id = {$postId}";
            $result = $pdo->query($checkSql)->fetch(\PDO::FETCH_ASSOC);

            if (!$result || $result['status'] !== 'trash') {
                flash('error', __('blog.post.error_move_to_trash'));
                header('Location: /' . admin_path() . '/blog/posts');
                exit;
            }

            // Registrar en tabla blog_posts_trash (compatible MySQL/PostgreSQL)
            $deletedAt = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $scheduledPermanentDelete = (new \DateTimeImmutable('now'))->modify('+30 days')->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare("INSERT INTO blog_posts_trash (post_id, tenant_id, deleted_by, deleted_by_name, deleted_by_type, deleted_at, scheduled_permanent_delete, ip_address) VALUES (?, ?, ?, ?, 'admin', ?, ?, ?)");
            $stmt->execute([
                $postId,
                $tenantId,
                $user['id'] ?? 0,
                $user['name'] ?? 'Sistema',
                $deletedAt,
                $scheduledPermanentDelete,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // Cargar el modelo BlogPost para crear la revisión
            $post = BlogPost::find($postId);
            if ($post) {
                $post->status = 'trash';
                try {
                    \Blog\Models\BlogPostRevision::createFromPost($post, 'manual', 'Movido a papelera');
                } catch (\Exception $revError) {
                    error_log("Error al crear revisión de papelera: " . $revError->getMessage());
                }
            }

            // 🔒 SECURITY: Audit log - registrar eliminación de post
            AuditLogger::log('blog_post.deleted', 'blog_post', $id, [
                'title' => $post->title ?? '',
                'slug' => $post->slug ?? '',
                'tenant_id' => $tenantId
            ]);

            // Actualizar contadores de categorías y etiquetas
            $this->updateAllCategoryCounts($tenantId);
            $this->updateAllTagCounts($tenantId);

            flash('success', __('blog.post.success_moved_to_trash'));
        } catch (\Exception $e) {
            error_log("Error al eliminar post: " . $e->getMessage());
            flash('error', __('blog.post.error_delete', ['error' => $e->getMessage()]));
        }

        header('Location: /' . admin_path() . '/blog/posts');
        exit;
    }

    /**
     * Acciones masivas
     */
    public function bulk()
    {
        $action = $_POST['action'] ?? null;
        $selected = $_POST['selected'] ?? [];

        if (empty($action) || empty($selected)) {
            flash('error', __('blog.post.error_bulk_no_selection'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        // Verificar permisos según la acción
        if ($action === 'delete') {
            $this->checkPermission('blog.delete');
        } else {
            $this->checkPermission('blog.edit');
        }

        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        // Límite de 100 elementos en acciones masivas
        if (count($selected) > 100) {
            flash('error', __('blog.post.error_bulk_max_limit'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        if ($action === 'delete') {
            $deletedCount = 0;

            foreach ($selected as $id) {
                // Verificar que el post pertenezca al tenant
                $post = BlogPost::where('id', $id)
                            ->where('tenant_id', $tenantId)
                            ->first();

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
            $this->updateAllCategoryCounts($tenantId);
            $this->updateAllTagCounts($tenantId);

            flash('success', __('blog.post.success_bulk_deleted', ['count' => $deletedCount]));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        if (in_array($action, ['draft', 'published'])) {
            foreach ($selected as $id) {
                $post = BlogPost::where('id', $id)
                            ->where('tenant_id', $tenantId)
                            ->first();
                if ($post) {
                    $post->status = $action;
                    $post->save();
                }
            }

            flash('success', __('blog.post.success_bulk_status_updated'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        if (in_array($action, ['public', 'private', 'password'])) {
            foreach ($selected as $id) {
                $post = BlogPost::where('id', $id)
                            ->where('tenant_id', $tenantId)
                            ->first();
                if ($post) {
                    $post->visibility = $action;
                    $post->save();
                }
            }

            flash('success', __('blog.post.success_bulk_visibility_updated'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        flash('error', __('blog.post.error_bulk_invalid_action'));
        header('Location: /' . admin_path() . '/blog/posts');
        exit;
    }

    /**
     * Formulario para editar traducción
     */
    public function editTranslation($id, $locale)
    {
        $this->checkPermission('blog.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        // Buscar post SOLO del tenant actual
        $post = BlogPost::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$post) {
            flash('error', __('blog.post.error_base_post_not_found'));
            header('Location: /' . admin_path() . '/blog/posts');
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
                'tenant_id' => $tenantId,
            ]);
            $isNewTranslation = true;
        }

        // Obtener el nombre del idioma
        $localeName = getAvailableLocales()[$locale] ?? strtoupper($locale);

        return View::renderTenantAdmin('blog.posts.translation_edit', [
            'title'       => $isNewTranslation
                                ? "Crear Traducción ({$localeName}) para \"{$post->title}\""
                                : "Editar Traducción ({$localeName}) para \"{$post->title}\"",
            'post'        => $post,
            'translation' => $translation,
            'locale'      => $locale,
            'localeName'  => $localeName
        ]);
    }

    /**
     * Actualizar traducción
     */
    public function updateTranslation($id, $locale)
    {
        $this->checkPermission('blog.edit');
        $tenantId = TenantManager::currentTenantId();

        if ($tenantId === null) {
            flash('error', __('blog.post.error_tenant_not_identified'));
            header('Location: /' . admin_path() . '/blog/posts');
            exit;
        }

        // Buscar post SOLO del tenant actual
        $post = BlogPost::where('id', $id)
                    ->where('tenant_id', $tenantId)
                    ->first();

        if (!$post) {
            flash('error', __('blog.post.error_base_post_not_found'));
            header('Location: /' . admin_path() . '/blog/posts');
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
                    'tenant_id' => $tenantId,
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
            header("Location: /" . admin_path() . "/blog/posts/{$id}/translations/{$locale}");
            exit;
        }

        header("Location: /" . admin_path() . "/blog/posts/{$id}/translations/{$locale}");
        exit;
    }

    /**
     * Procesar datos del formulario
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
     * Procesar subida de imagen destacada
     */
    private function processFeaturedImageUpload($file, $currentImage = null)
    {
        // 🔒 SECURITY: Validación robusta de archivos de imagen
        // Previene: Polyglot file upload, MIME spoofing, RCE
        $validation = FileUploadValidator::validateImage($file, 10 * 1024 * 1024);

        if (!$validation['valid']) {
            return ['error' => $validation['error']];
        }

        $targetWidth = 800;
        $targetHeight = 600;

        $uploadDir = APP_ROOT . '/public/assets/uploads/blog/';
        $relativePath = 'uploads/blog/';

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['error' => "Error al crear el directorio: " . $uploadDir];
            }
        }

        // Generar nombre seguro usando el helper
        $extension = $validation['extension'];
        $filename = FileUploadValidator::generateSecureFilename($extension, 'featured');
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

    /**
     * Procesar subida de imagen hero
     */
    private function processHeroImageUpload($file, $currentImage = null)
    {
        // 🔒 SECURITY: Validación robusta de archivos de imagen
        // Previene: Polyglot file upload, MIME spoofing, RCE
        $validation = FileUploadValidator::validateImage($file, 10 * 1024 * 1024);

        if (!$validation['valid']) {
            return ['error' => $validation['error']];
        }

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

    /**
     * Actualiza los contadores de todas las categorías del tenant
     */
    private function updateAllCategoryCounts($tenantId)
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

    /**
     * Actualiza los contadores de todas las etiquetas del tenant
     */
    private function updateAllTagCounts($tenantId)
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
                WHERE t.tenant_id = ?
            ");
            $stmt->execute([$tenantId]);
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
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $post = BlogPost::where('id', $id)->where('tenant_id', $tenantId)->first();
        if (!$post) {
            flash('error', __('blog.post.error_not_found'));
            header("Location: /{$_SESSION['admin']['tenant_url']}/blog/posts");
            exit;
        }

        // Obtener revisiones
        $revisions = \Blog\Models\BlogPostRevision::getPostRevisions($id, 100);

        return View::renderTenant('blog.posts.revisions', [
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
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
        $revision = \Blog\Models\BlogPostRevision::findWithTenant((int)$revisionId, $tenantId);

        if (!$revision || $revision->post_id != $postId) {
            flash('error', __('blog.post.error_revision_not_found'));
            header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
            exit;
        }

        if ($revision->restore()) {
            flash('success', __('blog.post.success_revision_restored', ['date' => $revision->created_at]));
        } else {
            flash('error', __('blog.post.error_revision_restore'));
        }

        header("Location: /{$adminPath}/blog/posts/{$postId}/edit");
        exit;
    }

    /**
     * Eliminar permanentemente una revisión específica
     */
    public function deleteRevision($postId, $revisionId)
    {
        $this->checkPermission('blog.edit');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
        $revision = \Blog\Models\BlogPostRevision::findWithTenant((int)$revisionId, $tenantId);

        if (!$revision || (int)$revision->post_id !== (int)$postId) {
            flash('error', __('blog.post.error_revision_not_found'));
            header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
            exit;
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            $deleteStmt = $pdo->prepare("DELETE FROM blog_post_revisions WHERE id = ? AND post_id = ? AND tenant_id = ?");
            $deleteStmt->execute([(int)$revisionId, (int)$postId, (int)$tenantId]);

            $updateCountStmt = $pdo->prepare("
                UPDATE blog_posts
                SET revision_count = GREATEST(COALESCE(revision_count, 0) - 1, 0)
                WHERE id = ? AND tenant_id = ?
            ");
            $updateCountStmt->execute([(int)$postId, (int)$tenantId]);

            $pdo->commit();

            flash('success', __('blog.post.success_revision_deleted'));
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al eliminar revisión {$revisionId} del post {$postId}: " . $e->getMessage());
            flash('error', __('blog.post.error_revision_delete'));
        }

        header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
        exit;
    }

    /**
     * Acciones en lote sobre revisiones de un post
     * Acciones soportadas:
     * - delete_selected: elimina revisiones seleccionadas
     * - delete_all: elimina todas las revisiones del post
     */
    public function bulkRevisions($postId)
    {
        $this->checkPermission('blog.edit');
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';

        $post = BlogPost::where('id', (int)$postId)->where('tenant_id', (int)$tenantId)->first();
        if (!$post) {
            flash('error', __('blog.post.error_not_found'));
            header("Location: /{$adminPath}/blog/posts");
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
                    flash('error', __('blog.post.error_bulk_revision_no_selection'));
                    header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
                    exit;
                }

                $placeholders = implode(',', array_fill(0, count($revisionIds), '?'));
                $params = array_merge($revisionIds, [(int)$postId, (int)$tenantId]);
                $deleteStmt = $pdo->prepare("
                    DELETE FROM blog_post_revisions
                    WHERE id IN ({$placeholders}) AND post_id = ? AND tenant_id = ?
                ");
                $deleteStmt->execute($params);
                $deletedCount = (int)$deleteStmt->rowCount();

                flash('success', __('blog.post.success_bulk_revisions_deleted', ['count' => $deletedCount]));
            } elseif ($action === 'delete_all') {
                $deleteStmt = $pdo->prepare("DELETE FROM blog_post_revisions WHERE post_id = ? AND tenant_id = ?");
                $deleteStmt->execute([(int)$postId, (int)$tenantId]);
                $deletedCount = (int)$deleteStmt->rowCount();

                flash('success', __('blog.post.success_bulk_revisions_deleted', ['count' => $deletedCount]));
            } else {
                $pdo->rollBack();
                flash('error', __('blog.post.error_bulk_revision_invalid_action'));
                header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
                exit;
            }

            $updateCountStmt = $pdo->prepare("
                UPDATE blog_posts
                SET revision_count = (
                    SELECT COUNT(*)
                    FROM blog_post_revisions
                    WHERE post_id = ? AND tenant_id = ?
                )
                WHERE id = ? AND tenant_id = ?
            ");
            $updateCountStmt->execute([(int)$postId, (int)$tenantId, (int)$postId, (int)$tenantId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error en bulkRevisions post {$postId} tenant {$tenantId}: " . $e->getMessage());
            flash('error', __('blog.post.error_revision_delete'));
        }

        header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
        exit;
    }

    /**
     * Vista previa de una revisión
     */
    public function previewRevision($postId, $revisionId)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $revision = \Blog\Models\BlogPostRevision::findWithTenant((int)$revisionId, $tenantId);

        if (!$revision || $revision->post_id != $postId) {
            flash('error', __('blog.post.error_revision_not_found'));
            $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
            header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
            exit;
        }

        return View::renderTenant('blog.posts.preview-revision', [
            'title' => 'Preview: ' . e($revision->title),
            'revision' => $revision,
            'post' => BlogPost::where('id', $postId)->where('tenant_id', $tenantId)->first(),
        ]);
    }

    /**
     * Comparar dos revisiones
     */
    public function compareRevisions($postId, $id1, $id2)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $revision1 = \Blog\Models\BlogPostRevision::findWithTenant((int)$id1, $tenantId);
        $revision2 = \Blog\Models\BlogPostRevision::findWithTenant((int)$id2, $tenantId);

        if (!$revision1 || !$revision2 || $revision1->post_id != $postId || $revision2->post_id != $postId) {
            flash('error', __('blog.post.error_revisions_not_found'));
            $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
            header("Location: /{$adminPath}/blog/posts/{$postId}/revisions");
            exit;
        }

        $diff = $revision1->diffWith($revision2);

        return View::renderTenant('blog.posts.compare-revisions', [
            'title' => 'Comparar revisiones',
            'post' => BlogPost::where('id', $postId)->where('tenant_id', $tenantId)->first(),
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
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        // Obtener posts en papelera
        $posts = BlogPost::where('status', 'trash')
                        ->where('tenant_id', $tenantId)
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

        return View::renderTenant('blog.posts.trash', [
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
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $pdo = Database::connect();
        $postId = (int)$id;
        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';

        // Verificar que el post existe en papelera
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE id = ? AND tenant_id = ? AND status = 'trash'");
        $stmt->execute([$postId, $tenantId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            flash('error', __('blog.post.error_not_found_in_trash'));
            header("Location: /{$adminPath}/blog/posts/trash");
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
        header("Location: /{$adminPath}/blog/posts/{$postId}/edit");
        exit;
    }

    /**
     * Eliminar permanentemente un post
     */
    public function forceDelete($id)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $tenantId = getTenantId();
        if (!$tenantId) {
            flash('error', __('blog.post.error_invalid_session'));
            header('Location: ' . admin_url('login'));
            exit;
        }

        $adminPath = $_SESSION['admin']['tenant_url'] ?? 'admin';
        $post = BlogPost::where('id', $id)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'trash')
                        ->first();

        if (!$post) {
            flash('error', __('blog.post.error_not_found_in_trash'));
            header("Location: /{$adminPath}/blog/posts/trash");
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

        header("Location: /{$adminPath}/blog/posts/trash");
        exit;
    }

    /**
     * Autoguardar un post (AJAX endpoint)
     */
    public function autosave($id)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        header('Content-Type: application/json');

        $tenantId = getTenantId();
        if (!$tenantId) {
            echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
            exit;
        }

        $post = BlogPost::where('id', $id)->where('tenant_id', $tenantId)->first();
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

/**
 * Helper local para obtener tenant_id dentro del módulo Blog (tenant).
 * Se define aquí para mantener compatibilidad con llamadas legacy a getTenantId().
 */
function getTenantId(): ?int
{
    try {
        if (function_exists('\\tenant_id')) {
            $tenantId = \tenant_id();
            if (!empty($tenantId)) {
                return (int) $tenantId;
            }
        }

        if (class_exists(\Screenart\Musedock\Services\TenantManager::class)) {
            $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();
            if ($tenantId !== null) {
                return (int) $tenantId;
            }
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $tenantId = $_SESSION['admin']['tenant_id'] ?? $_SESSION['user']['tenant_id'] ?? null;
        return $tenantId !== null ? (int) $tenantId : null;
    } catch (\Throwable $e) {
        return null;
    }
}
