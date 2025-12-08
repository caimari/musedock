<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class AdminMenuController
{
    use RequiresPermission;

    /**
     * Muestra el listado de menús del administrador
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $pdo = Database::connect();

        // Obtener todos los menús con información de módulo (incluyendo children)
        $stmt = $pdo->prepare("
            SELECT am.*,
                   m.name as module_name,
                   (SELECT COUNT(*) FROM admin_menus WHERE parent_id = am.id) as children_count
            FROM admin_menus am
            LEFT JOIN modules m ON am.module_id = m.id
            ORDER BY am.parent_id IS NULL DESC, am.parent_id, am.order_position ASC
        ");
        $stmt->execute();
        $allMenus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Construir jerarquía
        $menus = $this->buildMenuTreeForList($allMenus);

        return View::renderSuperadmin('admin-menus.index', [
            'title' => 'Gestión de Menús del Administrador',
            'menus' => $menus
        ]);
    }

    /**
     * Construye un árbol jerárquico de menús para la lista
     */
    private function buildMenuTreeForList($items, $parentId = null)
    {
        $branch = [];

        foreach ($items as $item) {
            if ((is_null($parentId) && is_null($item['parent_id'])) ||
                ($item['parent_id'] == $parentId)) {

                $itemObj = (object) $item;
                $children = $this->buildMenuTreeForList($items, $item['id']);

                if (!empty($children)) {
                    $itemObj->children = $children;
                } else {
                    $itemObj->children = [];
                }

                $branch[] = $itemObj;
            }
        }

        return $branch;
    }

    /**
     * Muestra el formulario para crear un nuevo menú
     */
    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $pdo = Database::connect();

        // Obtener módulos activos
        $stmt = $pdo->prepare("SELECT id, name, slug FROM modules WHERE active = 1 ORDER BY name ASC");
        $stmt->execute();
        $modules = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Obtener menús padres disponibles
        $stmt = $pdo->prepare("
            SELECT id, title, slug
            FROM admin_menus
            WHERE parent_id IS NULL
            ORDER BY order_position ASC
        ");
        $stmt->execute();
        $parentMenus = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderSuperadmin('admin-menus.create', [
            'title' => 'Crear Menú del Administrador',
            'modules' => $modules,
            'parentMenus' => $parentMenus
        ]);
    }

    /**
     * Procesa la creación de un nuevo menú
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: ' . route('admin-menus.index'));
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $iconType = $_POST['icon_type'] ?? 'bi';
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $moduleId = !empty($_POST['module_id']) ? (int)$_POST['module_id'] : null;
        $orderPosition = (int)($_POST['order_position'] ?? 0);
        $permission = trim($_POST['permission'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title) || empty($slug) || empty($url)) {
            flash('error', 'Los campos título, slug y URL son obligatorios.');
            header('Location: ' . route('admin-menus.create'));
            exit;
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            // Verificar si el slug ya existe
            $stmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                throw new \Exception('El slug ya existe. Por favor usa uno diferente.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO admin_menus
                (parent_id, module_id, title, slug, url, icon, icon_type, order_position, permission, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $parentId,
                $moduleId,
                $title,
                $slug,
                $url,
                $icon,
                $iconType,
                $orderPosition,
                $permission,
                $isActive
            ]);

            $pdo->commit();
            flash('success', 'Menú creado correctamente.');
            header('Location: ' . route('admin-menus.index'));
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al crear el menú: ' . $e->getMessage());
            header('Location: ' . route('admin-menus.create'));
            exit;
        }
    }

    /**
     * Muestra el formulario para editar un menú
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM admin_menus WHERE id = ?");
        $stmt->execute([$id]);
        $menu = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$menu) {
            flash('error', 'Menú no encontrado.');
            header('Location: ' . route('admin-menus.index'));
            exit;
        }

        // Obtener módulos activos
        $stmt = $pdo->prepare("SELECT id, name, slug FROM modules WHERE active = 1 ORDER BY name ASC");
        $stmt->execute();
        $modules = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Obtener menús padres disponibles (excluyendo el actual)
        $stmt = $pdo->prepare("
            SELECT id, title, slug
            FROM admin_menus
            WHERE parent_id IS NULL AND id != ?
            ORDER BY order_position ASC
        ");
        $stmt->execute([$id]);
        $parentMenus = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderSuperadmin('admin-menus.edit', [
            'title' => 'Editar Menú del Administrador',
            'menu' => $menu,
            'modules' => $modules,
            'parentMenus' => $parentMenus
        ]);
    }

    /**
     * Actualiza un menú
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: ' . route('admin-menus.index'));
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        $iconType = $_POST['icon_type'] ?? 'bi';
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $moduleId = !empty($_POST['module_id']) ? (int)$_POST['module_id'] : null;
        $orderPosition = (int)($_POST['order_position'] ?? 0);
        $permission = trim($_POST['permission'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title) || empty($slug) || empty($url)) {
            flash('error', 'Los campos título, slug y URL son obligatorios.');
            header('Location: ' . route('admin-menus.edit', ['id' => $id]));
            exit;
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            // Verificar si el slug ya existe (excepto el actual)
            $stmt = $pdo->prepare("SELECT id FROM admin_menus WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                throw new \Exception('El slug ya existe. Por favor usa uno diferente.');
            }

            $stmt = $pdo->prepare("
                UPDATE admin_menus
                SET parent_id = ?,
                    module_id = ?,
                    title = ?,
                    slug = ?,
                    url = ?,
                    icon = ?,
                    icon_type = ?,
                    order_position = ?,
                    permission = ?,
                    is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $parentId,
                $moduleId,
                $title,
                $slug,
                $url,
                $icon,
                $iconType,
                $orderPosition,
                $permission,
                $isActive,
                $id
            ]);

            $pdo->commit();
            flash('success', 'Menú actualizado correctamente.');
            header('Location: ' . route('admin-menus.index'));
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al actualizar el menú: ' . $e->getMessage());
            header('Location: ' . route('admin-menus.edit', ['id' => $id]));
            exit;
        }
    }

    /**
     * Elimina un menú
     */
    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            // Verificar si el menú tiene hijos
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_menus WHERE parent_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                throw new \Exception('No se puede eliminar un menú que tiene submenús. Elimina primero los submenús.');
            }

            // Eliminar el menú
            $stmt = $pdo->prepare("DELETE FROM admin_menus WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->commit();
            flash('success', 'Menú eliminado correctamente.');

        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al eliminar el menú: ' . $e->getMessage());
        }

        header('Location: ' . route('admin-menus.index'));
        exit;
    }

    /**
     * Muestra la interfaz de ordenamiento de menús
     */
    public function reorder()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $pdo = Database::connect();

        // Obtener todos los menús con jerarquía
        $stmt = $pdo->prepare("
            SELECT am.*,
                   m.name as module_name
            FROM admin_menus am
            LEFT JOIN modules m ON am.module_id = m.id
            ORDER BY am.parent_id IS NULL DESC, am.parent_id, am.order_position ASC
        ");
        $stmt->execute();
        $allMenus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Construir jerarquía
        $menus = $this->buildMenuTree($allMenus);

        return View::renderSuperadmin('admin-menus.reorder', [
            'title' => 'Ordenar Menús del Administrador',
            'menus' => $menus
        ]);
    }

    /**
     * Guarda el nuevo orden de los menús
     */
    public function updateOrder()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $menuOrder = $_POST['menu_order'] ?? '';

        if (empty($menuOrder)) {
            echo json_encode(['success' => false, 'error' => 'No se recibieron datos']);
            exit;
        }

        $menuData = json_decode($menuOrder, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'error' => 'Error al decodificar JSON: ' . json_last_error_msg()]);
            exit;
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();
            $this->updateMenuOrderRecursive($menuData, null, 0);
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Actualiza recursivamente el orden de los menús
     */
    private function updateMenuOrderRecursive($items, $parentId = null, $startOrder = 0)
    {
        $pdo = Database::connect();
        $order = $startOrder;

        foreach ($items as $item) {
            $stmt = $pdo->prepare("
                UPDATE admin_menus
                SET order_position = ?, parent_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$order, $parentId, $item['id']]);

            if (!empty($item['children'])) {
                $this->updateMenuOrderRecursive($item['children'], $item['id'], 0);
            }

            $order++;
        }
    }

    /**
     * Construye un árbol jerárquico de menús
     */
    private function buildMenuTree($items, $parentId = null)
    {
        $branch = [];

        foreach ($items as $item) {
            if ((is_null($parentId) && is_null($item['parent_id'])) ||
                ($item['parent_id'] == $parentId)) {

                $itemObj = (object) $item;
                $children = $this->buildMenuTree($items, $item['id']);

                if (!empty($children)) {
                    $itemObj->children = $children;
                }

                $branch[] = $itemObj;
            }
        }

        return $branch;
    }

    /**
     * Alternar estado activo/inactivo de un menú (AJAX)
     */
    public function toggleActive($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        header('Content-Type: application/json');
        $pdo = Database::connect();

        try {
            $stmt = $pdo->prepare("SELECT is_active FROM admin_menus WHERE id = ?");
            $stmt->execute([$id]);
            $menu = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$menu) {
                echo json_encode(['success' => false, 'error' => 'Menú no encontrado']);
                exit;
            }

            $newStatus = $menu['is_active'] ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE admin_menus SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            echo json_encode(['success' => true, 'is_active' => $newStatus]);
            exit;

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}
