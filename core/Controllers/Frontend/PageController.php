<?php
namespace Screenart\Musedock\Controllers\Frontend;


use Screenart\Musedock\Models\Page;
use Screenart\Musedock\View;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Models\PageMeta;
use Screenart\Musedock\Widgets\WidgetManager;
use Screenart\Musedock\Theme;
use Screenart\Musedock\Services\DefaultLegalPagesService;

class PageController
{
    /**
     * Mostrar una página directamente por slug (si se usa sin sistema de slugs)
     */
    public function show($slug)
    {
        $tenantId = TenantManager::currentTenantId();

        // Consulta ESTRICTA por tenant - SIN fallback al master
        $query = Page::where('slug', $slug)
            ->where('status', 'published');

        // Filtrar SOLO por el tenant actual (sin fallback)
        if ($tenantId !== null) {
            // TENANT: Solo páginas de este tenant específico
            $query->where('tenant_id', $tenantId);
        } else {
            // MASTER: Solo páginas del master (tenant_id = NULL)
            $query->whereRaw('tenant_id IS NULL');
        }

        // NO hay ordenamiento especial - solo busca en su propio espacio
        $page = $query->first();

        if (is_array($page)) {
            $page = new Page($page);
        }

        if (!$page) {
            // === FALLBACK: Verificar si es una página legal por defecto ===
            if (DefaultLegalPagesService::isLegalPageSlug($slug)) {
                return $this->showDefaultLegalPage($slug);
            }

            http_response_code(404);
            echo "Página no encontrada.";
            return;
        }

        // Verificar visibilidad y permisos
        if (!$this->canUserViewPage($page)) {
            http_response_code(403);
            return View::renderTheme('403', [
                'page' => $page
            ]);
        }
        
        // Detectar idioma y cargar traducción
        $currentLang = detectLanguage();
        $translation = null;

        if ($page && $currentLang !== $page->base_locale) {
            $translation = \Screenart\Musedock\Database::table('page_translations')
                ->where('page_id', $page->id)
                ->where('locale', $currentLang)
                ->first();

            if ($translation) {
                $page->title = $translation->title;
                $page->content = $translation->content;
            }
        }

        // === PREPARAR DISPLAY DATA para la vista ===
        $displayData = new \stdClass();
        $displayData->title = $translation->title ?? $page->title;
        $rawContent = $translation->content ?? $page->content;
        $displayData->content = process_shortcodes($rawContent);
        $displayData->seo_title = $translation->seo_title ?? $page->seo_title;
        $displayData->seo_description = $translation->seo_description ?? $page->seo_description;
        $displayData->seo_keywords = $translation->seo_keywords ?? $page->seo_keywords;
        $displayData->seo_image = $translation->seo_image ?? $page->seo_image;
        $displayData->canonical_url = $translation->canonical_url ?? $page->canonical_url;
        $displayData->robots_directive = $translation->robots_directive ?? $page->robots_directive;
        $displayData->twitter_title = $translation->twitter_title ?? $page->twitter_title;
        $displayData->twitter_description = $translation->twitter_description ?? $page->twitter_description;
        $displayData->twitter_image = $translation->twitter_image ?? $page->twitter_image;

        // === NUEVO: Cargar metadatos para personalización de página ===
        $pageCustomizations = $this->loadPageCustomizations($page->id);
        
        // === Obtener la plantilla asignada ===
        $templateName = PageMeta::getMeta($page->id, 'page_template', 'page.blade.php');
        $templateName = str_replace('.blade.php', '', $templateName);

        // === NUEVO: Cargar áreas de widgets para este tema ===
        $themeWidgetAreas = $this->loadThemeWidgetAreas();
        
        // === NUEVO: Renderizar HTML de áreas de widgets ===
        $widgetAreaContent = $this->renderWidgetAreas($themeWidgetAreas, $tenantId);

        return View::renderTheme($templateName, [
            'page' => $page,
            'translation' => $displayData,
            'customizations' => $pageCustomizations,
            'widgetAreas' => $themeWidgetAreas,           // Definiciones de áreas
            'widgetContent' => $widgetAreaContent,        // HTML renderizado por área
        ]);
    }

    /**
     * Mostrar una página por ID (usado por el sistema SlugRouter)
     */
    public function showById($id)
    {
        $tenantId = TenantManager::currentTenantId();
        $multiTenant = setting('multi_tenant_enabled', false);

        // Log inicial
        $logPath = __DIR__.'/../../../storage/logs/page-debug.log';
        file_put_contents($logPath, 
            "Buscando página ID: $id - TenantId: " . json_encode($tenantId) . 
            " - MultiTenant: " . json_encode($multiTenant) . "\n", 
            FILE_APPEND);

        $query = Page::where('id', $id)
            ->where('status', 'published');

        if ($multiTenant) {
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->whereRaw('tenant_id IS NULL');
            }
        }

        $page = $query->first();

        file_put_contents($logPath, 
            "Página encontrada: " . ($page ? "SÍ" : "NO") . "\n", 
            FILE_APPEND);

        if (is_array($page)) {
            $page = new Page($page);
        }

        if (!$page) {
            // Log de página no encontrada
            file_put_contents($logPath, 
                "Página NO encontrada. Comprobando con consulta directa...\n", 
                FILE_APPEND);

            // Intentar una consulta directa para diagnóstico
            try {
                $sql = "SELECT * FROM pages WHERE id = :id AND status = :status";
                $params = [':id' => $id, ':status' => 'published'];

                if ($multiTenant) {
                    if ($tenantId !== null) {
                        $sql .= " AND tenant_id = :tenant_id";
                        $params[':tenant_id'] = $tenantId;
                    } else {
                        $sql .= " AND tenant_id IS NULL";
                    }
                }

                $result = \Screenart\Musedock\Database::query($sql, $params);
                $directResult = $result->fetch(\PDO::FETCH_ASSOC);

                file_put_contents($logPath, 
                    "Resultado consulta directa: " . json_encode($directResult) . "\n", 
                    FILE_APPEND);
            } catch (\Exception $e) {
                file_put_contents($logPath, 
                    "Error en consulta directa: " . $e->getMessage() . "\n", 
                    FILE_APPEND);
            }

            http_response_code(404);
            echo "Página no encontrada.";
            return;
        }
        
        // Verificar visibilidad y permisos
        if (!$this->canUserViewPage($page)) {
            http_response_code(403);
            return View::renderTheme('403', [
                'page' => $page
            ]);
        }

        // Detectar idioma y cargar traducción
        $currentLang = detectLanguage();
        $translation = null;

        if ($page && $currentLang !== $page->base_locale) {
            $translation = \Screenart\Musedock\Database::table('page_translations')
                ->where('page_id', $page->id)
                ->where('locale', $currentLang)
                ->first();

            if ($translation) {
                $page->title = $translation->title;
                $page->content = $translation->content;
            }
        }

        // === PREPARAR DISPLAY DATA para la vista ===
        $displayData = new \stdClass();
        $displayData->title = $translation->title ?? $page->title;
        $rawContent = $translation->content ?? $page->content;
        $displayData->content = process_shortcodes($rawContent);
        $displayData->seo_title = $translation->seo_title ?? $page->seo_title;
        $displayData->seo_description = $translation->seo_description ?? $page->seo_description;
        $displayData->seo_keywords = $translation->seo_keywords ?? $page->seo_keywords;
        $displayData->seo_image = $translation->seo_image ?? $page->seo_image;
        $displayData->canonical_url = $translation->canonical_url ?? $page->canonical_url;
        $displayData->robots_directive = $translation->robots_directive ?? $page->robots_directive;
        $displayData->twitter_title = $translation->twitter_title ?? $page->twitter_title;
        $displayData->twitter_description = $translation->twitter_description ?? $page->twitter_description;
        $displayData->twitter_image = $translation->twitter_image ?? $page->twitter_image;

        // === NUEVO: Cargar metadatos para personalización de página ===
        $pageCustomizations = $this->loadPageCustomizations($page->id);
        
        // === Obtener la plantilla asignada ===
        $templateName = PageMeta::getMeta($page->id, 'page_template', 'page.blade.php');
        $templateName = str_replace('.blade.php', '', $templateName);

        // === NUEVO: Cargar áreas de widgets para este tema ===
        $themeWidgetAreas = $this->loadThemeWidgetAreas();
        
        // === NUEVO: Renderizar HTML de áreas de widgets ===
        $widgetAreaContent = $this->renderWidgetAreas($themeWidgetAreas, $tenantId);

        return View::renderTheme($templateName, [
            'page' => $page,
            'translation' => $displayData,
            'customizations' => $pageCustomizations,
            'widgetAreas' => $themeWidgetAreas,           // Definiciones de áreas
            'widgetContent' => $widgetAreaContent,        // HTML renderizado por área
        ]);
    }

    /**
     * Muestra un listado de todas las páginas disponibles
     */
    public function listPages()
    {
        $tenantId = TenantManager::currentTenantId();
        $multiTenant = setting('multi_tenant_enabled', false);
        $currentLang = detectLanguage();
        
        // Log para debugging
        $logPath = __DIR__ . '/../../../storage/logs/debug.log';
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - LISTANDO PÁGINAS - TenantId: " . json_encode($tenantId) . "\n", FILE_APPEND);
        
        // Iniciar la consulta básica
        $query = Page::where('status', 'published');
        
        // Restricciones de tenant
        if ($multiTenant) {
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->whereRaw('tenant_id IS NULL');
            }
        }
        
        // Restricciones de visibilidad según usuario
        $isLoggedIn = isset($_SESSION['user']) || isset($_SESSION['super_admin']);

        if (!$isLoggedIn) {
            // Usuario no autenticado: solo páginas públicas
            // 🔒 SECURITY: Usar whereRaw con bindings seguros
            $query->whereRaw("(visibility = ? OR visibility IS NULL)", ['public']);
        } else {
            // Usuario autenticado - mostrar public, NULL, members, y private si es del usuario
            $userId = $_SESSION['user']['id'] ?? ($_SESSION['super_admin']['id'] ?? null);
            $userType = isset($_SESSION['super_admin']) ? 'superadmin' : (isset($_SESSION['user']) ? 'user' : null);

            // 🔒 SECURITY: Validar y sanitizar valores de sesión antes de usar en query
            $userId = is_numeric($userId) ? (int)$userId : 0;
            $allowedUserTypes = ['user', 'admin', 'superadmin'];
            $userType = in_array($userType, $allowedUserTypes, true) ? $userType : 'user';

            // Usar whereRaw con bindings seguros (QueryBuilder no soporta closures)
            if ($userId > 0) {
                // Páginas públicas, NULL, members, o privadas del usuario autenticado
                $query->whereRaw("(
                    visibility = ?
                    OR visibility IS NULL
                    OR visibility = ?
                    OR (visibility = ? AND user_id = ? AND user_type = ?)
                )", ['public', 'members', 'private', $userId, $userType]);
            } else {
                // Usuario autenticado sin ID válido: public, NULL, members
                $query->whereRaw("(
                    visibility = ?
                    OR visibility IS NULL
                    OR visibility = ?
                )", ['public', 'members']);
            }
        }
        
        // Ordenar por fecha de actualización, más recientes primero
        $pages = $query->orderBy('updated_at', 'DESC')->get();
        
        file_put_contents($logPath, date('Y-m-d H:i:s') . " - PÁGINAS ENCONTRADAS: " . count($pages) . "\n", FILE_APPEND);
        
        // === NUEVO: Cargar áreas de widgets para este tema ===
        $themeWidgetAreas = $this->loadThemeWidgetAreas();
        
        // === NUEVO: Renderizar HTML de áreas de widgets ===
        $widgetAreaContent = $this->renderWidgetAreas($themeWidgetAreas, $tenantId);
        
        // Intenta renderizar la vista de listado
        try {
            return View::renderTheme('pages.list', [
                'pages' => $pages,
                'title' => 'Listado de Páginas',
                'widgetAreas' => $themeWidgetAreas,          // Definiciones de áreas
                'widgetContent' => $widgetAreaContent        // HTML renderizado por área
            ]);
        } catch (\Exception $e) {
            // Log el error
            file_put_contents($logPath, date('Y-m-d H:i:s') . " - ERROR AL RENDERIZAR LISTA: " . $e->getMessage() . "\n", FILE_APPEND);
            
            // Si la plantilla no existe, mostrar listado HTML simple
            $output = "<!DOCTYPE html>
            <html>
            <head>
                <title>Listado de Páginas</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; color: #333; }
                    h1 { color: #444; border-bottom: 1px solid #eee; padding-bottom: 10px; }
                    .page-list { list-style: none; padding: 0; }
                    .page-item { margin-bottom: 15px; padding: 15px; border: 1px solid #eee; border-radius: 4px; }
                    .page-title { margin-top: 0; margin-bottom: 5px; }
                    .page-date { color: #888; font-size: 0.9em; }
                    .page-visibility { display: inline-block; font-size: 0.8em; padding: 2px 6px; border-radius: 3px; margin-left: 5px; }
                    .page-visibility.private { background-color: #f8d7da; color: #721c24; }
                    .page-visibility.members { background-color: #d1ecf1; color: #0c5460; }
                    a { color: #0066cc; text-decoration: none; }
                    a:hover { text-decoration: underline; }
                </style>
            </head>
            <body>
                <h1>Listado de Páginas</h1>";
            
            if (count($pages) > 0) {
                $output .= "<ul class='page-list'>";
                
                foreach ($pages as $page) {
                    $date = date('d/m/Y', strtotime($page->updated_at));
                    $visibility = '';
                    
                    // Solo mostrar indicadores de visibilidad a usuarios logueados
                    if ($isLoggedIn) {
                        if ($page->visibility === 'private') {
                            $visibility = "<span class='page-visibility private'>Privada</span>";
                        } elseif ($page->visibility === 'members') {
                            $visibility = "<span class='page-visibility members'>Miembros</span>";
                        }
                    }
                    
                    $output .= "
                        <li class='page-item'>
                            <h3 class='page-title'><a href='" . page_url($page->slug) . "'>{$page->title}</a> {$visibility}</h3>
                            <div class='page-date'>Actualizado: {$date}</div>
                        </li>";
                }
                
                $output .= "</ul>";
            } else {
                $output .= "<p>No hay páginas disponibles.</p>";
            }
            
            $output .= "
            </body>
            </html>";
            
            echo $output;
            return;
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
        
        // Log detallado para diagnóstico
        file_put_contents(__DIR__.'/../../../storage/logs/page-debug.log', 
            date('Y-m-d H:i:s') . " - DATOS DE PÁGINA $pageId:\n" .
            "show_slider [meta]: " . var_export($show_slider_meta, true) . "\n" .
            "show_slider [page]: " . var_export($page->show_slider ?? 'N/A', true) . "\n" .
            "show_slider [final]: " . var_export($customizations->show_slider, true) . "\n" .
            "hide_title [meta]: " . var_export($hide_title_meta, true) . "\n" .
            "hide_title [page]: " . var_export($page->hide_title ?? 'N/A', true) . "\n" .
            "hide_title [final]: " . var_export($customizations->hide_title, true) . "\n" .
            "slider_title: " . var_export($customizations->slider_title, true) . "\n" .
            "slider_content: " . var_export($customizations->slider_content, true) . "\n" .
            "slider_image: " . var_export($customizations->slider_image, true) . "\n",
            FILE_APPEND);
        
        return $customizations;
    }
    
    /**
     * Procesa la subida de imágenes para la cabecera
     * 
     * @param array $file Información del archivo subido ($_FILES['slider_image'])
     * @param string|null $currentImage Ruta actual de la imagen (para eliminarla si se reemplaza)
     * @return array Resultado de la operación con 'path' o 'error'
     */
    private function processSliderImageUpload($file, $currentImage = null)
    {
        // Log al inicio para depuración
        file_put_contents(__DIR__.'/../../../storage/logs/uploads.log', 
            date('Y-m-d H:i:s') . " - Intentando procesar imagen: " . 
            $file['name'] . " (tamaño: " . $file['size'] . ")\n", 
            FILE_APPEND);

        // Comprobar si el archivo es una imagen
        $fileInfo = getimagesize($file['tmp_name']);
        if ($fileInfo === false) {
            file_put_contents(__DIR__.'/../../../storage/logs/uploads.log', 
                date('Y-m-d H:i:s') . " - ERROR: El archivo no es una imagen válida.\n", 
                FILE_APPEND);
            return ['error' => 'El archivo no es una imagen válida.'];
        }
        
        // Configuración
        $uploadDir = 'assets/uploads/headers/'; // Ruta relativa a public
        $publicPath = $_SERVER['DOCUMENT_ROOT'] . '/';
        
        // Crear directorio si no existe
        if (!file_exists($publicPath . $uploadDir)) {
            $mkdirResult = mkdir($publicPath . $uploadDir, 0755, true);
            
            // Verificar si se creó el directorio
            if (!$mkdirResult) {
                file_put_contents(__DIR__.'/../../../storage/logs/uploads.log', 
                    date('Y-m-d H:i:s') . " - ERROR: No se pudo crear el directorio: " . $publicPath . $uploadDir . "\n" .
                    "Permisos del directorio padre: " . substr(sprintf('%o', fileperms(dirname($publicPath . $uploadDir))), -4) . "\n", 
                    FILE_APPEND);
                return ['error' => 'Error al crear el directorio para guardar la imagen.'];
            }
            
            file_put_contents(__DIR__.'/../../../storage/logs/uploads.log', 
                date('Y-m-d H:i:s') . " - Directorio creado exitosamente: " . $publicPath . $uploadDir . "\n", 
                FILE_APPEND);
        }
        
        // Generar nombre único para el archivo (para evitar sobrescrituras)
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('header_') . '.' . $extension;
        $fullPath = $publicPath . $uploadDir . $filename;
        
        // Intentar mover el archivo
        $moveResult = move_uploaded_file($file['tmp_name'], $fullPath);
        if (!$moveResult) {
            file_put_contents(__DIR__.'/../../../storage/logs/uploads.log', 
                date('Y-m-d H:i:s') . " - ERROR: No se pudo mover el archivo a: " . $fullPath . "\n" .
                "Origen: " . $file['tmp_name'] . "\n" .
                "Permisos del directorio de destino: " . substr(sprintf('%o', fileperms($publicPath . $uploadDir)), -4) . "\n", 
                FILE_APPEND);
            return ['error' => 'Error al guardar la imagen. Verifica los permisos del directorio.'];
        }
        
        // Eliminar imagen anterior si existe
        if ($currentImage && strpos($currentImage, 'themes/default/img/hero/') === false) {
            $oldPath = $publicPath . $currentImage;
            if (file_exists($oldPath)) {
                $unlinkResult = @unlink($oldPath);
                file_put_contents(__DIR__.'/../../../storage/logs/uploads.log', 
                    date('Y-m-d H:i:s') . " - Intento de eliminar imagen anterior: " . 
                    ($unlinkResult ? "ÉXITO" : "FALLO") . " - " . $oldPath . "\n", 
                    FILE_APPEND);
            }
        }
        
        file_put_contents(__DIR__.'/../../../storage/logs/uploads.log', 
            date('Y-m-d H:i:s') . " - Imagen guardada exitosamente en: " . $fullPath . "\n", 
            FILE_APPEND);
        
        return ['path' => $uploadDir . $filename];
    }
    
    /**
     * Determina si el usuario actual puede ver la página basado en la visibilidad
     */
    private function canUserViewPage($page)
    {
        // Si la página es pública, todos pueden verla
        if ($page->visibility === 'public' || !isset($page->visibility)) {
            return true;
        }

        // Verificar si el usuario está autenticado
        $isLoggedIn = isset($_SESSION['user']) || isset($_SESSION['super_admin']);
        
        // Si la página es para miembros, el usuario debe estar autenticado
        if ($page->visibility === 'members' && $isLoggedIn) {
            return true;
        }
        
        // Si la página es privada, verificar permisos
        if ($page->visibility === 'private') {
            // TODOS los super_admin pueden ver páginas privadas
            if (isset($_SESSION['super_admin'])) {
                return true;
            }

            // Si es usuario normal, solo puede ver si es el creador
            if (isset($_SESSION['user']) && $page->user_type === 'user' && $page->user_id == $_SESSION['user']['id']) {
                return true;
            }
        }
        
        // Por defecto, denegar acceso
        return false;
    }
    
    /**
     * NUEVO: Carga las áreas de widgets definidas en el tema actual
     * 
     * @return array Definiciones de las áreas de widgets
     */
    private function loadThemeWidgetAreas()
    {
        // Obtener el tema actual
        $themeSlug = setting('default_theme', 'default');
        
        // Cargar configuración del tema
        try {
            $themeConfig = Theme::getConfig($themeSlug);
            
            // Log para depuración
            file_put_contents(__DIR__.'/../../../storage/logs/widget-debug.log', 
                date('Y-m-d H:i:s') . " - Cargando áreas de widgets para tema: {$themeSlug}\n", 
                FILE_APPEND);
            
            // Obtener áreas de widgets
            $widgetAreas = [];
            
            // Probar primero content_areas (nuevo formato)
            if (isset($themeConfig['content_areas']) && is_array($themeConfig['content_areas'])) {
                foreach ($themeConfig['content_areas'] as $area) {
                    // Comprobar si soporta widgets
                    if (isset($area['supports']) && is_array($area['supports']) && in_array('widget', $area['supports'])) {
                        $areaId = $area['id'] ?? '';
                        if (!empty($areaId)) {
                            $widgetAreas[$areaId] = $area;
                        }
                    }
                }
            }
            
            // Si no hay áreas en content_areas, probar widget_areas (antiguo formato)
            if (empty($widgetAreas) && isset($themeConfig['widget_areas']) && is_array($themeConfig['widget_areas'])) {
                foreach ($themeConfig['widget_areas'] as $area) {
                    $areaSlug = $area['slug'] ?? '';
                    if (!empty($areaSlug)) {
                        $widgetAreas[$areaSlug] = $area;
                    }
                }
            }
            
            // Si no hay áreas definidas, buscar en las que usa el tema por defecto
            if (empty($widgetAreas)) {
                // Áreas de widgets predeterminadas para compatibilidad con temas antiguos
                $widgetAreas = [
                    'footer1' => ['name' => 'Footer Columna 1', 'description' => 'Primera columna del pie de página'],
                    'footer2' => ['name' => 'Footer Columna 2', 'description' => 'Segunda columna del pie de página'],
                    'footer3' => ['name' => 'Footer Columna 3', 'description' => 'Tercera columna del pie de página'],
                    'sidebar' => ['name' => 'Barra Lateral', 'description' => 'Área de widgets en la barra lateral']
                ];
            }
            
            // Log para depuración
            file_put_contents(__DIR__.'/../../../storage/logs/widget-debug.log', 
                date('Y-m-d H:i:s') . " - Áreas de widgets encontradas: " . implode(', ', array_keys($widgetAreas)) . "\n", 
                FILE_APPEND);
            
            return $widgetAreas;
            
        } catch (\Exception $e) {
            error_log("Error cargando áreas de widgets: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * NUEVO: Renderiza el contenido HTML de las áreas de widgets
     * 
     * @param array $areas Definiciones de áreas de widgets
     * @param int|null $tenantId ID del tenant actual
     * @return array Contenido HTML de cada área
     */
    private function renderWidgetAreas($areas, $tenantId = null)
    {
        // Obtener el tema actual
        $themeSlug = setting('default_theme', 'default');
        
        // Inicializar array para almacenar HTML por área
        $widgetContent = [];
        
        // Renderizar cada área
        foreach ($areas as $areaSlug => $areaInfo) {
            // Obtener y registrar widgets antes de renderizar
            WidgetManager::registerAvailableWidgets();
            
            // Renderizar el área usando WidgetManager
            $html = WidgetManager::renderArea($areaSlug, $tenantId, $themeSlug);
            
            // Guardar el HTML generado
            $widgetContent[$areaSlug] = $html;
            
            // Log para depuración
            file_put_contents(__DIR__.'/../../../storage/logs/widget-debug.log',
                date('Y-m-d H:i:s') . " - Área '{$areaSlug}' renderizada.\n",
                FILE_APPEND);
        }

        return $widgetContent;
    }

    /**
     * Renderiza una página legal por defecto usando el servicio DefaultLegalPagesService
     * Método público para ser llamado desde SlugRouter
     *
     * @param string $slug El slug de la página legal (cookie-policy, terms-and-conditions, privacy)
     * @return void
     */
    public function showDefaultLegalPage(string $slug)
    {
        // Asegurar que devuelve código 200 OK
        http_response_code(200);

        $currentLang = detectLanguage();
        $tenantId = TenantManager::currentTenantId();

        // Obtener contenido de la página legal por defecto
        $legalPage = DefaultLegalPagesService::getDefaultPage($slug, $currentLang);

        if (!$legalPage) {
            http_response_code(404);
            echo "Página no encontrada.";
            return;
        }

        // Crear un objeto page simulado para la vista
        $page = new \stdClass();
        $page->id = 0;
        $page->slug = $slug;
        $page->title = $legalPage['title'];
        $page->content = $legalPage['content'];
        $page->is_homepage = false;
        $page->status = 'published';
        $page->visibility = 'public';

        // Crear objeto translation/displayData
        $displayData = new \stdClass();
        $displayData->title = $legalPage['title'];
        $displayData->content = $legalPage['content'];
        $displayData->seo_title = $legalPage['title'];
        $displayData->seo_description = strip_tags(substr($legalPage['content'], 0, 160));
        $displayData->seo_keywords = '';
        $displayData->seo_image = '';
        $displayData->canonical_url = '';
        $displayData->robots_directive = 'index,follow';
        $displayData->twitter_title = '';
        $displayData->twitter_description = '';
        $displayData->twitter_image = '';

        // Crear customizations vacías (sin slider)
        $customizations = new \stdClass();
        $customizations->show_slider = false;
        $customizations->hide_title = false;
        $customizations->slider_image = '';
        $customizations->slider_title = '';
        $customizations->slider_content = '';
        $customizations->container_class = 'container py-4 page-container';
        $customizations->content_class = 'page-content-wrapper';

        // Cargar áreas de widgets
        $themeWidgetAreas = $this->loadThemeWidgetAreas();
        $widgetAreaContent = $this->renderWidgetAreas($themeWidgetAreas, $tenantId);

        // Renderizar usando la plantilla de página estándar
        return View::renderTheme('page', [
            'page' => $page,
            'translation' => $displayData,
            'customizations' => $customizations,
            'widgetAreas' => $themeWidgetAreas,
            'widgetContent' => $widgetAreaContent,
            'isDefaultLegalPage' => true,
        ]);
    }
}