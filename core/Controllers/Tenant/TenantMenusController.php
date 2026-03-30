<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;

class TenantMenusController
{
    use RequiresPermission;

    /**
     * Muestra el listado de menús del tenant
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $tenantData = tenant();
        $tenantId = $tenantData['id'];
        $pdo = Database::connect();

        // Obtener todos los menús del tenant
        $stmt = $pdo->prepare("
            SELECT tm.*,
                   m.name as module_name,
                   (SELECT COUNT(*) FROM tenant_menus WHERE parent_id = tm.id AND tenant_id = ?) as children_count
            FROM tenant_menus tm
            LEFT JOIN modules m ON tm.module_id = m.id
            WHERE tm.tenant_id = ?
            ORDER BY tm.parent_id IS NOT NULL, tm.parent_id, tm.order_position ASC
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $allMenus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Construir jerarquía
        $menus = $this->buildMenuTreeForList($allMenus);

        return View::renderTenantAdmin('tenant-menus.index', [
            'title' => 'Gestión de Menús del Panel',
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
     * Muestra el formulario para editar un menú
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        $tenantData = tenant();
        $tenantId = $tenantData['id'];
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM tenant_menus WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $menu = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$menu) {
            flash('error', 'Menú no encontrado.');
            header('Location: ' . route('tenant-menus.index'));
            exit;
        }

        // Obtener módulos activos para este tenant
        $stmt = $pdo->prepare("
            SELECT m.id, m.name, m.slug
            FROM modules m
            INNER JOIN tenant_modules tm ON tm.module_id = m.id
            WHERE m.active = 1 AND tm.tenant_id = ? AND tm.enabled = 1
            ORDER BY m.name ASC
        ");
        $stmt->execute([$tenantId]);
        $modules = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Obtener menús padres disponibles (excluyendo el actual)
        $stmt = $pdo->prepare("
            SELECT id, title, slug
            FROM tenant_menus
            WHERE parent_id IS NULL AND id != ? AND tenant_id = ?
            ORDER BY order_position ASC
        ");
        $stmt->execute([$id, $tenantId]);
        $parentMenus = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return View::renderTenantAdmin('tenant-menus.edit', [
            'title' => 'Editar Menú',
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
            header('Location: ' . route('tenant-menus.index'));
            exit;
        }

        // 🔒 SECURITY: Verificar CSRF token
        if (!isset($_POST['_csrf']) || !verify_csrf_token($_POST['_csrf'])) {
            http_response_code(403);
            flash('error', 'Token CSRF inválido');
            header('Location: ' . route('tenant-menus.index'));
            exit;
        }

        $tenantData = tenant();
        $tenantId = $tenantData['id'];

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
            header('Location: ' . route('tenant-menus.edit', ['id' => $id]));
            exit;
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            // Verificar que el menú pertenece al tenant
            $stmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenantId]);
            if (!$stmt->fetch()) {
                throw new \Exception('Menú no encontrado o sin acceso.');
            }

            // Verificar si el slug ya existe (excepto el actual)
            $stmt = $pdo->prepare("SELECT id FROM tenant_menus WHERE slug = ? AND id != ? AND tenant_id = ?");
            $stmt->execute([$slug, $id, $tenantId]);
            if ($stmt->fetch()) {
                throw new \Exception('El slug ya existe. Por favor usa uno diferente.');
            }

            $stmt = $pdo->prepare("
                UPDATE tenant_menus
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
                WHERE id = ? AND tenant_id = ?
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
                $id,
                $tenantId
            ]);

            $pdo->commit();
            flash('success', 'Menú actualizado correctamente.');
            header('Location: ' . route('tenant-menus.index'));
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al actualizar el menú: ' . $e->getMessage());
            header('Location: ' . route('tenant-menus.edit', ['id' => $id]));
            exit;
        }
    }

    /**
     * Alternar estado activo/inactivo de un menú (AJAX)
     */
    public function toggleActive($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.menus');

        header('Content-Type: application/json');

        $tenantData = tenant();
        $tenantId = $tenantData['id'];
        $pdo = Database::connect();

        try {
            $stmt = $pdo->prepare("SELECT is_active FROM tenant_menus WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenantId]);
            $menu = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$menu) {
                echo json_encode(['success' => false, 'error' => 'Menú no encontrado']);
                exit;
            }

            $newStatus = $menu['is_active'] ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE tenant_menus SET is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$newStatus, $id, $tenantId]);

            echo json_encode(['success' => true, 'is_active' => $newStatus]);
            exit;

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}
