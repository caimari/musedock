<?php

namespace Screenart\Musedock\Controllers\Frontend;


use Screenart\Musedock\View;
use Screenart\Musedock\Models\Page;
use Screenart\Musedock\Models\PageTranslation;
use Screenart\Musedock\Database;
use Screenart\Musedock\Models\PageMeta; // AÑADIDO para poder usar PageMeta

class HomeController
{
    /**
     * Muestra la página de inicio configurada o un contenido por defecto.
     */
    public function index()
    {
        error_log("HomeController: Accediendo a index()");

        // Obtener tenant actual para usar la función de settings correcta
        $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();

        // Verificar configuración de lectura (usar tenant_setting si hay tenant activo)
        if ($tenantId !== null) {
            $showOnFront = tenant_setting('show_on_front', 'posts');
            $pageOnFront = tenant_setting('page_on_front', '');
            $postOnFront = tenant_setting('post_on_front', '');
        } else {
            $showOnFront = setting('show_on_front', 'posts');
            $pageOnFront = setting('page_on_front', '');
            $postOnFront = setting('post_on_front', '');
        }

        error_log("HomeController: show_on_front = {$showOnFront}, page_on_front = {$pageOnFront}, post_on_front = {$postOnFront}");

        // Si está configurado para mostrar posts, redirigir al blog
        if ($showOnFront === 'posts') {
            error_log("HomeController: Mostrando últimas entradas del blog");
            return $this->showLatestPosts();
        }

        // Si está configurado para mostrar una página estática
        if ($showOnFront === 'page' && !empty($pageOnFront)) {
            error_log("HomeController: Mostrando página estática ID: {$pageOnFront}");
            return $this->showStaticPage($pageOnFront, true);
        }

        // Si está configurado para mostrar un post estático
        if ($showOnFront === 'post' && !empty($postOnFront)) {
            error_log("HomeController: Mostrando post estático ID: {$postOnFront}");
            return $this->showStaticPost($postOnFront);
        }

        // Fallback: buscar página marcada como homepage (comportamiento legacy)
        $homepage = null;
        $homepageData = null;

        try {
            error_log("HomeController: Intentando buscar homepage para tenant_id: " . ($tenantId ?? 'NULL (master)'));

            // Query con filtro correcto por tenant
            if ($tenantId !== null) {
                $result = Database::query(
                    "SELECT * FROM pages WHERE is_homepage = :is_home AND status = :status AND tenant_id = :tenant_id LIMIT 1",
                    [':is_home' => 1, ':status' => 'published', ':tenant_id' => $tenantId]
                );
            } else {
                $result = Database::query(
                    "SELECT * FROM pages WHERE is_homepage = :is_home AND status = :status AND tenant_id IS NULL LIMIT 1",
                    [':is_home' => 1, ':status' => 'published']
                );
            }

            $homepageData = $result->fetch(\PDO::FETCH_ASSOC);
            error_log("HomeController: Datos crudos encontrados: " . ($homepageData ? json_encode(['id' => $homepageData['id']]) : 'NO'));

            if ($homepageData) {
                $homepage = new Page($homepageData);
                error_log("HomeController: Objeto Page instanciado, ID: {$homepage->id}");
            }

        } catch (\Throwable $e) {
            error_log("HomeController: ¡ERROR FATAL AL BUSCAR/INSTANCIAR HOMEPAGE!: " . $e->getMessage());
            $homepage = null;
        }

        if ($homepage instanceof Page) {
            $currentLocale = detectLanguage();
            $translation = $homepage->translation($currentLocale);

            $displayData = new \stdClass();
            $displayData->title = $translation->title ?? $homepage->title;

            // NO procesar shortcodes aquí, dejar que las vistas lo hagan con apply_filters('the_content')
            $displayData->content = $translation->content ?? $homepage->content ?? '';

            $displayData->seo_title = $translation->seo_title ?? $homepage->seo_title;
            $displayData->seo_description = $translation->seo_description ?? $homepage->seo_description;
            $displayData->seo_keywords = $translation->seo_keywords ?? $homepage->seo_keywords;
            $displayData->seo_image = $translation->seo_image ?? $homepage->seo_image;
            $displayData->canonical_url = $translation->canonical_url ?? $homepage->canonical_url;
            $displayData->robots_directive = $translation->robots_directive ?? $homepage->robots_directive;
            $displayData->twitter_title = $translation->twitter_title ?? $homepage->twitter_title;
            $displayData->twitter_description = $translation->twitter_description ?? $homepage->twitter_description;
            $displayData->twitter_image = $translation->twitter_image ?? $homepage->twitter_image;

            // === NUEVO: Obtener plantilla asignada ===
            $templateName = PageMeta::getMeta($homepage->id, 'page_template', 'page.blade.php');
            $templateName = str_replace('.blade.php', '', $templateName);
            error_log("HomeController: Usando plantilla '{$templateName}' para renderizar homepage.");
            // ========================================

            // === NUEVO: Cargar personalizaciones de página ===
            $pageCustomizations = $this->loadPageCustomizations($homepage->id);
            // ================================================

            try {
                return View::renderTheme($templateName, [
                    'page' => $homepage,
                    'translation' => $displayData,
                    'customizations' => $pageCustomizations
                ]);
            } catch (\Exception $e) {
                error_log("HomeController: Error al renderizar vista '{$templateName}': " . $e->getMessage());
                http_response_code(500);
                return "Error al cargar página (template '{$templateName}' ausente).";
            }
        } else {
            error_log("HomeController: No se encontró homepage para este dominio.");
            http_response_code(404);

            // Obtener favicon configurado
            $favicon = setting('site_favicon', '/assets/superadmin/img/favicon.ico');

            // Mostrar mensaje simple sin fallback de contenido
            return "<!DOCTYPE html>
<html lang=\"es\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <link rel=\"icon\" type=\"image/x-icon\" href=\"" . htmlspecialchars($favicon) . "\">
    <title>Sitio en construcción - " . setting('site_name', 'MuseDock') . "</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .container { text-align: center; padding: 3rem; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; margin: 1rem; }
        h1 { color: #333; margin-bottom: 1rem; font-size: 1.75rem; }
        p { color: #666; margin: 0.5rem 0; line-height: 1.6; }
        .logo { font-size: 4rem; margin-bottom: 1.5rem; }
        .hint { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; margin-top: 1.5rem; }
        .hint p { color: #856404; font-size: 0.9rem; margin: 0; }
        .hint strong { color: #664d03; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"logo\">🚧</div>
        <h1>Sitio en construcción</h1>
        <p>Este dominio aún no tiene una página de inicio configurada.</p>
        <div class=\"hint\">
            <p><strong>¿Eres el administrador?</strong></p>
            <p>Accede al panel de administración, crea una página y márcala como <strong>\"Página de inicio\"</strong> en la configuración de la página.</p>
        </div>
    </div>
</body>
</html>";
        }
    }

    /**
     * Carga todas las personalizaciones de página desde la tabla page_meta y la tabla pages
     *
     * @param int $pageId ID de la página
     * @return object Objeto con todas las personalizaciones
     */
    private function loadPageCustomizations($pageId)
    {
        // Crear un objeto para almacenar las personalizaciones
        $customizations = new \stdClass();

        // Obtener la página para acceder a los datos de la tabla directamente
        $page = Page::find($pageId);

        // === Opciones del slider ===
        // Para show_slider, verificar primero en page_meta y luego en la tabla pages
        $show_slider_meta = PageMeta::getMeta($pageId, 'show_slider', null);
        $show_slider_value = ($show_slider_meta !== null) ? $show_slider_meta : ($page->show_slider ?? 0);

        // Forzar a booleano estricto para asegurar comparación correcta en la vista
        $customizations->show_slider = ($show_slider_value == 1 || $show_slider_value === true || $show_slider_value === "1") ? true : false;

        // Para hide_title, similar a show_slider
        $hide_title_meta = PageMeta::getMeta($pageId, 'hide_title', null);
        $hide_title_value = ($hide_title_meta !== null) ? $hide_title_meta : ($page->hide_title ?? 0);
        $customizations->hide_title = ($hide_title_value == 1 || $hide_title_value === true || $hide_title_value === "1") ? true : false;

        // Otras opciones
        $customizations->slider_image = PageMeta::getMeta($pageId, 'slider_image', null);
        if (empty($customizations->slider_image)) {
            $customizations->slider_image = $page->slider_image ?? 'themes/default/img/hero/contact_hero.jpg';
        }

        $customizations->slider_title = PageMeta::getMeta($pageId, 'slider_title', null);
        if (empty($customizations->slider_title)) {
            $customizations->slider_title = $page->slider_title;
        }

        $customizations->slider_content = PageMeta::getMeta($pageId, 'slider_content', null);
        if (empty($customizations->slider_content)) {
            $customizations->slider_content = $page->slider_content;
        }

        $customizations->slider_button_text = PageMeta::getMeta($pageId, 'slider_button_text', null);
        $customizations->slider_button_url = PageMeta::getMeta($pageId, 'slider_button_url', '#');

        $customizations->container_class = PageMeta::getMeta($pageId, 'container_class', 'container py-4');
        $customizations->content_class = PageMeta::getMeta($pageId, 'content_class', 'page-content-wrapper');

        return $customizations;
    }

    /**
     * Muestra las últimas entradas del blog en la página de inicio
     */
    private function showLatestPosts()
    {
        // Obtener número de posts por página desde settings
        $postsPerPage = (int) setting('posts_per_page', 10);

        // Obtener página actual
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($currentPage - 1) * $postsPerPage;

        // Obtener tenant actual
        $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();

        try {
            $pdo = Database::connect();

            // Contar total de posts
            if ($tenantId !== null) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE status = 'published' AND tenant_id = ?");
                $countStmt->execute([$tenantId]);
            } else {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE status = 'published' AND tenant_id IS NULL");
                $countStmt->execute([]);
            }
            $totalPosts = $countStmt->fetchColumn();
            $totalPages = ceil($totalPosts / $postsPerPage);

            // Query para obtener posts publicados con paginación
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("
                    SELECT * FROM blog_posts
                    WHERE status = 'published' AND tenant_id = ?
                    ORDER BY published_at DESC
                    LIMIT {$postsPerPage} OFFSET {$offset}
                ");
                $stmt->execute([$tenantId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM blog_posts
                    WHERE status = 'published' AND tenant_id IS NULL
                    ORDER BY published_at DESC
                    LIMIT {$postsPerPage} OFFSET {$offset}
                ");
                $stmt->execute([]);
            }

            $posts = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Obtener categorías para el sidebar
            $categoriesStmt = $pdo->query("SELECT * FROM blog_categories ORDER BY name ASC");
            $categories = $categoriesStmt->fetchAll(\PDO::FETCH_OBJ);

            // Preparar datos de paginación
            $pagination = [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'total_posts' => $totalPosts,
                'per_page' => $postsPerPage,
            ];

            return View::renderTheme('blog.index', [
                'posts' => $posts,
                'categories' => $categories,
                'pagination' => $pagination,
            ]);

        } catch (\Exception $e) {
            error_log("HomeController: Error al cargar posts: " . $e->getMessage());
            return "Error al cargar el blog.";
        }
    }

    /**
     * Muestra una página estática específica como página de inicio
     */
    private function showStaticPage($pageId, bool $isFrontPage = false)
    {
        try {
            $page = Page::find($pageId);

            if (!$page || $page->status !== 'published') {
                error_log("HomeController: Página ID {$pageId} no encontrada o no publicada");
                http_response_code(404);
                return "Página no encontrada";
            }

            $currentLocale = detectLanguage();
            $translation = $page->translation($currentLocale);

            $displayData = new \stdClass();
            $displayData->title = $translation->title ?? $page->title;
            $displayData->content = $translation->content ?? $page->content ?? '';
            $displayData->seo_title = $translation->seo_title ?? $page->seo_title;
            $displayData->seo_description = $translation->seo_description ?? $page->seo_description;
            $displayData->seo_keywords = $translation->seo_keywords ?? $page->seo_keywords;
            $displayData->seo_image = $translation->seo_image ?? $page->seo_image;
            $displayData->canonical_url = $translation->canonical_url ?? $page->canonical_url;
            $displayData->robots_directive = $translation->robots_directive ?? $page->robots_directive;
            $displayData->twitter_title = $translation->twitter_title ?? $page->twitter_title;
            $displayData->twitter_description = $translation->twitter_description ?? $page->twitter_description;
            $displayData->twitter_image = $translation->twitter_image ?? $page->twitter_image;

            // Si esta página es la homepage configurada, usar la plantilla "home" del tema
            if ($isFrontPage) {
                $templateName = 'home';
            } else {
                // Obtener plantilla asignada
                $templateName = PageMeta::getMeta($page->id, 'page_template', 'page.blade.php');
                $templateName = str_replace('.blade.php', '', $templateName);
            }

            // Cargar personalizaciones de página
            $pageCustomizations = $this->loadPageCustomizations($page->id);

            return View::renderTheme($templateName, [
                'page' => $page,
                'translation' => $displayData,
                'customizations' => $pageCustomizations,
                'isFrontPage' => $isFrontPage,
            ]);

        } catch (\Exception $e) {
            error_log("HomeController: Error al cargar página estática: " . $e->getMessage());
            return "Error al cargar la página.";
        }
    }

    /**
     * Muestra un post de blog específico como página de inicio
     */
    private function showStaticPost($postId)
    {
        try {
            $pdo = Database::connect();
            $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();

            // Obtener el post
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? AND status = 'published'");
            $stmt->execute([$postId]);
            $post = $stmt->fetch(\PDO::FETCH_OBJ);

            if (!$post) {
                error_log("HomeController: Post ID {$postId} no encontrado o no publicado");
                http_response_code(404);
                return "Post no encontrado";
            }

            // Detectar idioma y cargar traducción si existe
            $currentLocale = detectLanguage();
            $translationStmt = $pdo->prepare("
                SELECT * FROM blog_post_translations
                WHERE post_id = ? AND locale = ?
            ");
            $translationStmt->execute([$post->id, $currentLocale]);
            $translation = $translationStmt->fetch(\PDO::FETCH_OBJ);

            // Preparar datos de visualización
            $displayData = new \stdClass();
            $displayData->title = $translation->title ?? $post->title;
            $displayData->content = $translation->content ?? $post->content;
            $displayData->excerpt = $translation->excerpt ?? $post->excerpt;
            $displayData->seo_title = $translation->seo_title ?? $post->seo_title ?? $post->title;
            $displayData->seo_description = $translation->seo_description ?? $post->seo_description ?? $post->excerpt;
            $displayData->seo_keywords = $translation->seo_keywords ?? $post->seo_keywords;
            $displayData->seo_image = $translation->seo_image ?? $post->seo_image ?? $post->featured_image;
            $displayData->hide_featured_image = $post->hide_featured_image;
            $displayData->featured_image = $post->featured_image;
            $displayData->published_at = $post->published_at;

            // Obtener categorías del post
            $stmt = $pdo->prepare("
                SELECT c.* FROM blog_categories c
                INNER JOIN blog_post_categories pc ON c.id = pc.category_id
                WHERE pc.post_id = ?
            ");
            $stmt->execute([$postId]);
            $categories = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Obtener la plantilla seleccionada o usar la predeterminada (page = ancho completo)
            $template = $post->template ?? 'page';

            // Determinar la ruta de la plantilla (igual lógica que BlogController)
            if (strpos($template, 'template-sidebar-') === 0 || $template === 'page') {
                $templatePath = $template;
            } else {
                $templatePath = 'blog/' . $template;
            }

            // Renderizar vista usando la misma lógica que el BlogController
            return View::renderTheme($templatePath, [
                'post' => $post,
                'translation' => $displayData,
                'categories' => $categories,
            ]);

        } catch (\Exception $e) {
            error_log("HomeController: Error al cargar post estático: " . $e->getMessage());
            return "Error al cargar el post.";
        }
    }
}
