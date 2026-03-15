<?php

namespace CrossPublisherAdmin\Controllers;

use CrossPublisherAdmin\Models\DomainGroup;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use PDO;

class GroupController
{
    public function index()
    {
        $groups = DomainGroup::allWithCounts();
        return View::renderSuperadmin('plugins.cross-publisher.groups.index', [
            'groups' => $groups,
        ]);
    }

    public function create()
    {
        return View::renderSuperadmin('plugins.cross-publisher.groups.create');
    }

    public function store()
    {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $defaultLanguage = trim($_POST['default_language'] ?? 'es');
        $autoSync = !empty($_POST['auto_sync_enabled']);

        if (empty($name)) {
            flash('error', 'El nombre del grupo es obligatorio.');
            header('Location: /musedock/cross-publisher/groups/create');
            exit;
        }

        DomainGroup::create([
            'name' => $name,
            'description' => $description,
            'default_language' => $defaultLanguage,
            'auto_sync_enabled' => $autoSync,
        ]);

        flash('success', 'Grupo creado correctamente.');
        header('Location: /musedock/cross-publisher/groups');
        exit;
    }

    public function edit($id)
    {
        $group = DomainGroup::find($id);
        if (!$group) {
            flash('error', 'Grupo no encontrado.');
            header('Location: /musedock/cross-publisher/groups');
            exit;
        }

        $members = DomainGroup::getMembers($id);

        // Tenants sin grupo asignado
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT id, name, domain FROM tenants WHERE (group_id IS NULL OR group_id = {$id}) AND status = 'active' ORDER BY name ASC");
        $availableTenants = $stmt->fetchAll(PDO::FETCH_OBJ);

        return View::renderSuperadmin('plugins.cross-publisher.groups.edit', [
            'group' => $group,
            'members' => $members,
            'availableTenants' => $availableTenants,
        ]);
    }

    public function update($id)
    {
        $group = DomainGroup::find($id);
        if (!$group) {
            flash('error', 'Grupo no encontrado.');
            header('Location: /musedock/cross-publisher/groups');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            flash('error', 'El nombre del grupo es obligatorio.');
            header("Location: /musedock/cross-publisher/groups/{$id}/edit");
            exit;
        }

        DomainGroup::update($id, [
            'name' => $name,
            'description' => trim($_POST['description'] ?? ''),
            'default_language' => trim($_POST['default_language'] ?? 'es'),
            'auto_sync_enabled' => !empty($_POST['auto_sync_enabled']),
        ]);

        // Actualizar miembros
        $memberIds = $_POST['members'] ?? [];
        $pdo = Database::connect();

        // Quitar todos los tenants del grupo
        $stmt = $pdo->prepare("UPDATE tenants SET group_id = NULL WHERE group_id = ?");
        $stmt->execute([$id]);

        // Asignar los seleccionados
        if (!empty($memberIds)) {
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            $params = array_merge([$id], $memberIds);
            $stmt = $pdo->prepare("UPDATE tenants SET group_id = ? WHERE id IN ({$placeholders})");
            $stmt->execute($params);
        }

        flash('success', 'Grupo actualizado correctamente.');
        header("Location: /musedock/cross-publisher/groups/{$id}/edit");
        exit;
    }

    public function destroy($id)
    {
        DomainGroup::delete($id);
        flash('success', 'Grupo eliminado.');
        header('Location: /musedock/cross-publisher/groups');
        exit;
    }

    public function assignTenant()
    {
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $groupId = $_POST['group_id'] ?? null;
        $groupId = $groupId === '' ? null : (int) $groupId;

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'tenant_id requerido']);
            return;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE tenants SET group_id = ? WHERE id = ?");
        $stmt->execute([$groupId, $tenantId]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
}
