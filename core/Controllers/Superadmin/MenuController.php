<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Models\Menu;
use Screenart\Musedock\Models\MenuItem;
use Screenart\Musedock\Models\Page;
use Screenart\Musedock\Models\Post;
use Screenart\Musedock\Models\Category;
use Screenart\Musedock\Database;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class MenuController
{
    use RequiresPermission;

    /**
 * Muestra el listado de menús
 */
/**
 * Muestra el listado de menús
 */
public function index()
{
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM site_menus ORDER BY id DESC");
    $stmt->execute();
    $menus = array_map(fn($row) => new Menu((array) $row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

    // Obtener los datos de cada menú
    foreach ($menus as $menu) {
        // Obtener la traducción y el idioma
        $stmtTranslation = $pdo->prepare("
            SELECT mt.title, mt.locale
            FROM site_menu_translations mt
            WHERE mt.menu_id = ? 
            ORDER BY mt.id DESC 
            LIMIT 1
        ");
        $stmtTranslation->execute([$menu->id]);
        $translation = $stmtTranslation->fetch(\PDO::FETCH_ASSOC);
        
        $menu->title = $translation['title'] ?? 'Sin título';
        $menu->locale = $translation['locale'] ?? setting('language', 'es');

        // Contar elementos
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM site_menu_items 
            WHERE menu_id = ?
        ");
        $stmtCount->execute([$menu->id]);
        $count = $stmtCount->fetch(\PDO::FETCH_ASSOC);
        $menu->item_count = $count['count'] ?? 0;
    }

    return View::renderSuperadmin('menus.index', [
        'title' => 'Gestor de Menús',
        'menus' => $menus
    ]);
}
	
/**
 * Muestra el formulario para crear un nuevo menú
 */
public function createForm()
{
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT id, code, name FROM languages WHERE active = 1 ORDER BY id ASC");
    $stmt->execute();
    $languages = $stmt->fetchAll(\PDO::FETCH_OBJ);
    
    // Obtener el idioma por defecto
    $defaultLanguage = setting('language', 'es');
    
    // Cargar las áreas de menú dinámicamente desde el tema activo
    $menuAreas = $this->getMenuAreasFromTheme();
    
    return View::renderSuperadmin('menus.create', [
        'title' => 'Crear Nuevo Menú',
        'languages' => $languages,
        'defaultLanguage' => $defaultLanguage,
        'menuAreas' => $menuAreas
    ]);
}

/**
 * Procesa la creación de un nuevo menú
 */
public function store()
{
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

    // Comprobamos que la solicitud sea POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        flash('error', 'Método no permitido.');
        header('Location: ' . route('menus.index'));
        exit;
    }
    
    // Capturamos el título y ubicación del menú
    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $locale = trim($_POST['locale'] ?? setting('language', 'es'));
    $tenantId = null; // En el futuro, obtener de la sesión
    
    // Validamos que el título no esté vacío
    if (empty($title)) {
        flash('error', 'El título del menú no puede estar vacío.');
        header('Location: ' . route('menus.create'));
        exit;
    }
    
    $pdo = Database::connect();
    
    try {
        $pdo->beginTransaction();
        
        // Crear el menú
        $stmt = $pdo->prepare("
            INSERT INTO site_menus 
            (tenant_id, location, created_at, updated_at) 
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $location]);
        $menuId = $pdo->lastInsertId();
        
        // Crear la traducción del menú
        $stmt = $pdo->prepare("
            INSERT INTO site_menu_translations 
            (menu_id, locale, title, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$menuId, $locale, $title]);
        
        $pdo->commit();
        
        flash('success', 'Menú creado correctamente.');
        header('Location: ' . route('menus.edit', ['id' => $menuId]));
        exit;
    } catch (\Exception $e) {
        $pdo->rollBack();
        flash('error', 'Error al crear el menú: ' . $e->getMessage());
        header('Location: ' . route('menus.create'));
        exit;
    }
}
	
	
/**
 * Muestra la pantalla de edición de un menú
 */
public function edit($id)
{
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

    $menu = Menu::find($id);
    
    if (!$menu) {
        flash('error', 'Menú no encontrado.');
        header('Location: ' . route('menus.index'));
        exit;
    }

    // Obtener el tenant_id actual (null para el CMS principal)
    $tenantId = null; // En el futuro, obtener de la sesión

    // Obtener todos los elementos del menú
    $pdo = Database::connect();
    
    // Obtener los elementos del menú
    $stmt = $pdo->prepare("
        SELECT * FROM site_menu_items 
        WHERE menu_id = ? 
        ORDER BY depth ASC, sort ASC
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Obtener páginas recientes
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.status, s.slug, s.prefix
        FROM pages p
        LEFT JOIN slugs s ON s.reference_id = p.id AND s.module = 'pages'
        WHERE p.status = 'published'
          AND (p.tenant_id IS NULL OR p.tenant_id = ?)
          AND s.slug IS NOT NULL
        ORDER BY p.updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenantId]);
    $recentPages = $stmt->fetchAll(\PDO::FETCH_OBJ);

    // Obtener todas las páginas
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.status, s.slug, s.prefix
        FROM pages p
        LEFT JOIN slugs s ON s.reference_id = p.id AND s.module = 'pages'
        WHERE p.status = 'published'
          AND (p.tenant_id IS NULL OR p.tenant_id = ?)
          AND s.slug IS NOT NULL
        ORDER BY p.title ASC
    ");
    $stmt->execute([$tenantId]);
    $allPages = $stmt->fetchAll(\PDO::FETCH_OBJ);

    // Obtener idiomas disponibles
    $stmt = $pdo->prepare("
        SELECT id, code, name 
        FROM languages 
        WHERE active = 1
        ORDER BY id ASC
    ");
    $stmt->execute();
    $languages = $stmt->fetchAll(\PDO::FETCH_OBJ);

    // Obtener la traducción actual del menú
    $stmt = $pdo->prepare("
        SELECT locale, title 
        FROM site_menu_translations 
        WHERE menu_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $translation = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Obtener el idioma actual y el título
    $currentLocale = $translation['locale'] ?? setting('language', 'es');
    $menu->title = $translation['title'] ?? 'Sin título';
    
    // Cargar las áreas de menú dinámicamente desde el tema activo
    $menuAreas = $this->getMenuAreasFromTheme();

    // Cargar posts y categorías del blog si el módulo está activo
    $blogPosts = [];
    $blogCategories = [];

    // Verificar si existe la tabla blog_posts (módulo de blog activo)
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'blog_posts'");
        if ($stmt->rowCount() > 0) {
            // Obtener posts publicados
            $stmt = $pdo->prepare("
                SELECT bp.id, bpt.title, s.slug
                FROM blog_posts bp
                LEFT JOIN blog_post_translations bpt ON bp.id = bpt.post_id
                LEFT JOIN slugs s ON s.reference_id = bp.id AND s.module = 'blog'
                WHERE bp.status = 'published'
                  AND bpt.locale = ?
                  AND s.slug IS NOT NULL
                ORDER BY bp.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$currentLocale]);
            $blogPosts = $stmt->fetchAll(\PDO::FETCH_OBJ);

            // Obtener categorías
            $stmt = $pdo->prepare("
                SELECT bc.id, bc.name, bc.slug
                FROM blog_categories bc
                WHERE bc.active = 1
                ORDER BY bc.name ASC
            ");
            $stmt->execute();
            $blogCategories = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }
    } catch (\Exception $e) {
        // Módulo de blog no disponible
    }

    return View::renderSuperadmin('menus.edit', [
        'title'          => 'Editar Menú',
        'menu'           => $menu,
        'items'          => $items,
        'recentPages'    => $recentPages,
        'allPages'       => $allPages,
        'tenantId'       => $tenantId,
        'languages'      => $languages,
        'currentLocale'  => $currentLocale,
        'menuAreas'      => $menuAreas,
        'blogPosts'      => $blogPosts,
        'blogCategories' => $blogCategories
    ]);
}

/**
 * Obtiene las áreas de menú definidas en el tema activo
 *
 * @return array
 */
private function getMenuAreasFromTheme()
{
    // Obtener el tema activo desde la base de datos
    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT slug FROM themes WHERE active = 1 LIMIT 1");
    $stmt->execute();
    $activeTheme = $stmt->fetchColumn();

    // Si no hay tema activo, usar el predeterminado
    if (!$activeTheme) {
        $activeTheme = 'default';
    }

    // Áreas de menú predeterminadas en caso de que no se encuentren en el tema
    $defaultAreas = [
        ['id' => 'nav', 'name' => 'Navegación principal'],
        ['id' => 'footer', 'name' => 'Footer'],
        ['id' => 'sidebar', 'name' => 'Sidebar']
    ];

    // Usar APP_ROOT si está definido, sino calcular desde __DIR__
    if (defined('APP_ROOT')) {
        $themeJsonPath = APP_ROOT . '/themes/' . $activeTheme . '/theme.json';
    } else {
        // Calcular ruta base del proyecto (4 niveles arriba desde Controllers/Superadmin)
        $themeJsonPath = dirname(__DIR__, 3) . '/themes/' . $activeTheme . '/theme.json';
    }

    // Verificar si existe el archivo theme.json
    if (!file_exists($themeJsonPath)) {
        return $defaultAreas;
    }
    
    // Leer el archivo theme.json
    try {
        $themeJsonContent = file_get_contents($themeJsonPath);

        if (!$themeJsonContent) {
            return $defaultAreas;
        }

        $themeConfig = json_decode($themeJsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $defaultAreas;
        }

        // Verificar si existen áreas de menú definidas
        if (isset($themeConfig['menu_areas']) && is_array($themeConfig['menu_areas'])) {
            return $themeConfig['menu_areas'];
        }

        // También verificar si hay 'content_areas' que soporten menús
        if (isset($themeConfig['content_areas']) && is_array($themeConfig['content_areas'])) {
            $menuAreas = [];
            foreach ($themeConfig['content_areas'] as $area) {
                if (isset($area['supports']) && in_array('menu', $area['supports'])) {
                    $menuAreas[] = [
                        'id' => $area['id'],
                        'name' => $area['name'],
                        'description' => $area['description'] ?? ''
                    ];
                }
            }

            if (!empty($menuAreas)) {
                return $menuAreas;
            }
        }
    } catch (\Exception $e) {
        // En caso de error, devolver las áreas predeterminadas
    }

    return $defaultAreas;
}
	
	/**
     * Actualiza los datos básicos de un menú
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: ' . route('menus.index'));
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $locale = trim($_POST['locale'] ?? setting('language', 'es'));
        $showTitle = isset($_POST['show_title']) ? (int)$_POST['show_title'] : 1;

        if (empty($title)) {
            flash('error', 'El título del menú no puede estar vacío.');
            header('Location: ' . route('menus.edit', ['id' => $id]));
            exit;
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            // Actualizar el menú (ubicación y show_title)
            $stmt = $pdo->prepare("
                UPDATE site_menus
                SET location = ?, show_title = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$location, $showTitle, $id]);

            // Comprobar si existe una traducción para este idioma
            $stmt = $pdo->prepare("
                SELECT id FROM site_menu_translations 
                WHERE menu_id = ? AND locale = ?
            ");
            $stmt->execute([$id, $locale]);
            $translationId = $stmt->fetchColumn();

            if ($translationId) {
                // Actualizar la traducción existente
                $stmt = $pdo->prepare("
                    UPDATE site_menu_translations 
                    SET title = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $translationId]);
            } else {
                // Crear una nueva traducción
                $stmt = $pdo->prepare("
                    INSERT INTO site_menu_translations 
                    (menu_id, locale, title, created_at, updated_at) 
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$id, $locale, $title]);
            }

            $pdo->commit();
            flash('success', 'Menú actualizado correctamente.');
            header('Location: ' . route('menus.edit', ['id' => $id]));
            exit;
        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al actualizar el menú: ' . $e->getMessage());
            header('Location: ' . route('menus.edit', ['id' => $id]));
            exit;
        }
    }
	
	 /**
     * Elimina un menú y todos sus elementos
     */
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $pdo = Database::connect();
        
        try {
            $pdo->beginTransaction();
            
            // Eliminar los elementos del menú
            $stmt = $pdo->prepare("DELETE FROM site_menu_items WHERE menu_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar las traducciones del menú
            $stmt = $pdo->prepare("DELETE FROM site_menu_translations WHERE menu_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar el menú
            $stmt = $pdo->prepare("DELETE FROM site_menus WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            flash('success', 'Menú eliminado correctamente.');
        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al eliminar el menú: ' . $e->getMessage());
        }
        
        header('Location: ' . route('menus.index'));
        exit;
    }

	
    /**
     * Actualiza un elemento específico del menú
     */
    public function updateItem($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return json_encode(['error' => 'Método no permitido']);
        }

        $title = trim($_POST['title'] ?? '');
        $link = isset($_POST['link']) ? trim($_POST['link']) : null;

        if (empty($title)) {
            return json_encode(['error' => 'El título no puede estar vacío']);
        }

        $pdo = Database::connect();

        try {
            // Obtener el elemento del menú
            $stmt = $pdo->prepare("
                SELECT * FROM site_menu_items WHERE id = ?
            ");
            $stmt->execute([$id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                return json_encode(['error' => 'Elemento no encontrado']);
            }

            // Actualizar el elemento
            $stmt = $pdo->prepare("
                UPDATE site_menu_items 
                SET title = ?, link = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$title, $link, $id]);

            return json_encode(['success' => true]);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Error al actualizar el elemento: ' . $e->getMessage()]);
        }
    }

	
	
/**
 * Actualiza los items de un menú, manteniendo la jerarquía padre-hijo.
 * 
 * @param int $id ID del menú
 * @return string Respuesta JSON
 */
public function updateItems($id)
{
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return json_encode(['error' => 'Método no permitido']);
    }
    
    // Decodificar el JSON si viene como string
    $menuDataRaw = isset($_POST['menu']) ? $_POST['menu'] : '';
    $menuData = [];
    
    if (is_string($menuDataRaw)) {
        try {
            $menuData = json_decode($menuDataRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return json_encode(['error' => 'Error al decodificar JSON: ' . json_last_error_msg()]);
            }
        } catch (\Exception $e) {
            return json_encode(['error' => 'Error al procesar los datos: ' . $e->getMessage()]);
        }
    } else if (is_array($menuDataRaw)) {
        $menuData = $menuDataRaw;
    }
    
    if (empty($menuData)) {
        return json_encode(['error' => 'No se han recibido datos del menú']);
    }
    
    try {
        $pdo = \Screenart\Musedock\Database::connect();
        $pdo->beginTransaction();
        
        // 1. Recuperamos los IDs actuales para no borrar los elementos existentes
        $stmt = $pdo->prepare("SELECT id FROM site_menu_items WHERE menu_id = ?");
        $stmt->execute([$id]);
        $existingIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        // 2. Guardamos los IDs que vamos a actualizar para saber cuáles eliminar después
        $updatedIds = [];
        
        // 3. Actualizamos la estructura recursivamente
        $this->updateMenuItemsRecursively($menuData, $id, null, 0, $updatedIds);
        
        // 4. Eliminamos solo los elementos que ya no existen en la nueva estructura
        $idsToDelete = array_diff($existingIds, $updatedIds);
        
        if (!empty($idsToDelete)) {
            // Reindexar el array para asegurar que los índices sean secuenciales
            $idsToDelete = array_values($idsToDelete);
            
            $placeholders = rtrim(str_repeat('?,', count($idsToDelete)), ',');
            $stmt = $pdo->prepare("DELETE FROM site_menu_items WHERE id IN ($placeholders)");
            $stmt->execute($idsToDelete);
        }
        
        $pdo->commit();
        return json_encode(['success' => true]);
    } catch (\Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error en updateItems: ' . $e->getMessage());
        error_log('Traza: ' . $e->getTraceAsString());
        return json_encode(['error' => 'Error al actualizar el menú: ' . $e->getMessage()]);
    }
}
	
/**
 * Actualiza recursivamente los elementos del menú, manteniendo los IDs existentes.
 *
 * @param array $items Elementos a actualizar
 * @param int $menuId ID del menú
 * @param int|null $parentId ID del elemento padre
 * @param int $depth Nivel de profundidad
 * @param array &$updatedIds Array donde guardamos los IDs actualizados
 * @return void
 */
private function updateMenuItemsRecursively($items, $menuId, $parentId = null, $depth = 0, &$updatedIds = [])
{
    if (!is_array($items)) {
        return;
    }
    
    $pdo = \Screenart\Musedock\Database::connect();
    $tenantId = null; // En el futuro, obtener desde la sesión
    
    foreach ($items as $index => $item) {
        // Verificar si el ID es un ID existente o un ID temporal
        $isTemporaryId = isset($item['id']) && is_string($item['id']) && (strpos($item['id'], 'temp_') === 0);
        $hasExistingId = isset($item['id']) && !$isTemporaryId && is_numeric($item['id']);
        
        // Obtener valores necesarios con manejo de errores
        $title = isset($item['title']) ? trim($item['title']) : '';
        
        // Obtener el valor correcto para link
        $link = '';
        if (isset($item['url'])) {
            $link = trim($item['url']);
        } elseif (isset($item['link'])) {
            $link = trim($item['link']);
        } elseif (isset($item['slug'])) {
            $link = trim($item['slug']);
        }
        
        // Determinar el tipo del elemento
        $type = isset($item['type']) ? trim($item['type']) : 'custom';
        
        // Obtener el page_id si existe (asegurarse de que sea numérico o NULL)
        $pageId = null;
        if (isset($item['page_id']) && is_numeric($item['page_id'])) {
            $pageId = (int)$item['page_id'];
        }
        
        try {
            if ($hasExistingId) {
                // 1. ACTUALIZAR elemento existente
                $stmt = $pdo->prepare("
                    UPDATE site_menu_items 
                    SET parent = ?, 
                        title = ?, 
                        link = ?, 
                        sort = ?,
                        depth = ?,
                        type = ?,
                        page_id = ?,
                        tenant_id = ?,
                        updated_at = NOW()
                    WHERE id = ? AND menu_id = ?
                ");
                
                $stmt->execute([
                    $parentId,
                    $title,
                    $link,
                    $index,
                    $depth,
                    $type,
                    $pageId,
                    $tenantId,
                    $item['id'],
                    $menuId
                ]);
                
                // Si la actualización no afectó ninguna fila, puede que el ID ya no exista
                if ($stmt->rowCount() === 0) {
                    $newId = $this->insertNewMenuItem($pdo, $menuId, $tenantId, $parentId, $title, $link, $index, $depth, $type, $pageId);
                    $item['id'] = $newId; // Actualizar el ID para los hijos
                    $updatedIds[] = $newId;
                } else {
                    // Registrar como actualizado
                    $updatedIds[] = $item['id'];
                }
            } else {
                // 2. INSERTAR nuevo elemento (ID temporal o sin ID)
                $newId = $this->insertNewMenuItem($pdo, $menuId, $tenantId, $parentId, $title, $link, $index, $depth, $type, $pageId);
                $item['id'] = $newId; // Actualizar el ID para los hijos
                $updatedIds[] = $newId;
            }
            
            // Procesar elementos hijos recursivamente
            if (!empty($item['children'])) {
                $this->updateMenuItemsRecursively(
                    $item['children'], 
                    $menuId, 
                    $item['id'], // Usar el ID actual (podría ser uno nuevo)
                    $depth + 1,
                    $updatedIds
                );
            }
        } catch (\Exception $e) {
            error_log('Error en updateMenuItemsRecursively: ' . $e->getMessage());
            error_log('Item: ' . json_encode($item));
            // Continuar con el siguiente elemento
        }
    }
}

/**
 * Inserta un nuevo elemento de menú y devuelve su ID
 * 
 * @param \PDO $pdo Conexión a la base de datos
 * @param int $menuId ID del menú
 * @param int|null $tenantId ID del tenant
 * @param int|null $parentId ID del elemento padre
 * @param string $title Título del elemento
 * @param string $link Enlace del elemento
 * @param int $index Posición del elemento
 * @param int $depth Nivel de profundidad
 * @param string $type Tipo de elemento
 * @param int|null $pageId ID de la página relacionada
 * @return int ID del nuevo elemento insertado
 */
private function insertNewMenuItem($pdo, $menuId, $tenantId, $parentId, $title, $link, $index, $depth, $type, $pageId)
{
    $stmt = $pdo->prepare("
        INSERT INTO site_menu_items 
        (menu_id, tenant_id, parent, title, link, sort, depth, type, page_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $menuId,
        $tenantId,
        $parentId,
        $title,
        $link,
        $index,
        $depth,
        $type,
        $pageId
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Construir un árbol de menú a partir de una lista plana de elementos
 * 
 * @param array $items Lista de elementos del menú
 * @param mixed $parentId ID del padre (null para elementos raíz)
 * @return array Elementos organizados jerárquicamente
 */
// En tu controlador, asegúrate de que buildMenuTree funcione así:
private function buildMenuTree($items, $parentId = null) 
{
    $branch = [];
    
    foreach ($items as $item) {
        // Asegurarse de comparar correctamente (parent podría ser NULL o el ID numérico)
        if (($parentId === null && $item['parent'] === null) || 
            ($parentId !== null && $item['parent'] == $parentId)) {
            
            // Convertir a objeto para mantener compatibilidad
            $itemObj = (object) $item;
            
            // Buscar hijos para este elemento
            $children = $this->buildMenuTree($items, $item['id']);
            
            // Solo añadir la propiedad children si realmente hay hijos
            if (!empty($children)) {
                $itemObj->children = $children;
            }
            
            $branch[] = $itemObj;
        }
    }
    
    return $branch;
}
	

 
/**
 * Añade páginas seleccionadas al menú
 */
public function addPages()
{
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return json_encode(['error' => 'Método no permitido']);
    }

    $menuId = $_GET['menuid'] ?? null;
    
    // Aquí está el problema - $_GET['ids'] puede ser una cadena única
    // cuando solo se selecciona un elemento, o un array cuando 
    // se seleccionan múltiples elementos
    $idsParam = $_GET['ids'] ?? '';
    
    // Asegurarse de que $ids siempre sea un array
    $ids = [];
    if (is_array($idsParam)) {
        $ids = $idsParam;
    } else if (is_string($idsParam)) {
        // Si $idsParam es una cadena, puede ser un solo ID o una lista separada por comas
        if (strpos($idsParam, ',') !== false) {
            $ids = explode(',', $idsParam);
        } else if (!empty($idsParam)) {
            $ids = [$idsParam]; // Un solo ID como array
        }
    }
    
    $tenantId = null; // Para el futuro

    if (!$menuId || empty($ids)) {
        return json_encode(['error' => 'Parámetros inválidos']);
    }

    try {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM site_menu_items WHERE menu_id = ? ORDER BY sort DESC LIMIT 1");
        $stmt->execute([$menuId]);
        $lastItem = $stmt->fetch(\PDO::FETCH_ASSOC);
        $sort = $lastItem ? ($lastItem['sort'] + 1) : 0;

        foreach ($ids as $id) {
            // Obtener la página y su slug
            $stmt = $pdo->prepare("
                SELECT p.id, p.title, s.slug, s.prefix
                FROM pages p
                LEFT JOIN slugs s ON s.reference_id = p.id AND s.module = 'pages'
                WHERE p.id = ? AND s.slug IS NOT NULL
            ");
            $stmt->execute([$id]);
            $page = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($page) {
                $link = '/' . ($page['prefix'] ?? '') . '/' . $page['slug'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO site_menu_items 
                    (menu_id, tenant_id, parent, title, link, sort, depth, type, page_id, created_at, updated_at)
                    VALUES (?, ?, NULL, ?, ?, ?, 0, 'page', ?, NOW(), NOW())
                ");
                $stmt->execute([$menuId, $tenantId, $page['title'], $link, $sort, $page['id']]);
                $sort++;
            }
        }

        return json_encode(['success' => true]);
    } catch (\Exception $e) {
        error_log('Error en addPages: ' . $e->getMessage());
        return json_encode(['error' => 'Error al añadir páginas: ' . $e->getMessage()]);
    }
}

  /**
 * Añade un enlace personalizado al menú
 */
public function addCustomLink()
{
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return json_encode(['error' => 'Método no permitido']);
    }

    $menuId = $_GET['menuid'] ?? null;
    $url = $_GET['url'] ?? '';
    $linkText = $_GET['link'] ?? '';
    $tenantId = null; // Para el futuro

    if (!$menuId || empty($url) || empty($linkText)) {
        return json_encode(['error' => 'Parámetros inválidos']);
    }

    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT * FROM site_menu_items WHERE menu_id = ? ORDER BY sort DESC LIMIT 1");
    $stmt->execute([$menuId]);
    $lastItem = $stmt->fetch(\PDO::FETCH_ASSOC);
    $sort = $lastItem ? ($lastItem['sort'] + 1) : 0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO site_menu_items 
            (menu_id, tenant_id, parent, title, link, sort, depth, type, created_at, updated_at)
            VALUES (?, ?, NULL, ?, ?, ?, 0, 'custom', NOW(), NOW())
        ");
        $stmt->execute([$menuId, $tenantId, $linkText, $url, $sort]);
        
        return json_encode(['success' => true]);
    } catch (\Exception $e) {
        return json_encode(['error' => 'Error al añadir el enlace: ' . $e->getMessage()]);
    }
}


    private function getMenuTree($menuId)
    {
        $items = MenuItem::where('menu_id', $menuId)
            ->orderBy('parent')
            ->orderBy('sort')
            ->orderBy('depth')
            ->get();

        $tree = [];
        $refs = [];

        foreach ($items as $item) {
            $thisRef = &$refs[$item->id];
            $thisRef['id'] = $item->id;
            $thisRef['title'] = $item->title;
            $thisRef['slug'] = $item->slug;
            $thisRef['type'] = $item->type;
            $thisRef['name'] = $item->name;
            $thisRef['target'] = $item->target;
            $thisRef['parent'] = $item->parent;
            $thisRef['children'] = [];

            if ($item->parent == 0) {
                $tree[$item->id] = &$thisRef;
            } else {
                $refs[$item->parent]['children'][$item->id] = &$thisRef;
            }
        }

        return $tree;
    }
}
