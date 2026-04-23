<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Helpers\AdHelper;
use PDO;

class AdsController
{
    public function index()
    {
        SessionSecurity::startSession();
        $tenantId = tenant_id();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            SELECT au.*, asl.name as slot_name
            FROM ad_units au
            LEFT JOIN ad_slots asl ON asl.slug = au.slot_slug
            WHERE au.tenant_id = ?
            ORDER BY au.slot_slug, au.priority DESC
        ");
        $stmt->execute([$tenantId]);
        $ads = $stmt->fetchAll(PDO::FETCH_OBJ);

        $slots = AdHelper::getSlots();

        return View::renderTenantAdmin('ads.index', [
            'title' => 'Anuncios',
            'ads' => $ads,
            'slots' => $slots,
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();
        $slots = AdHelper::getSlots();

        return View::renderTenantAdmin('ads.form', [
            'title' => 'Crear anuncio',
            'ad' => null,
            'slots' => $slots,
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $tenantId = tenant_id();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("
            INSERT INTO ad_units (tenant_id, slot_slug, name, ad_type, image_url, link_url, link_target, alt_text, html_content, starts_at, ends_at, is_active, repeat_every, priority, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $startsAt = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
        $endsAt = !empty($_POST['ends_at']) ? $_POST['ends_at'] : null;
        $repeatEvery = !empty($_POST['repeat_every']) ? (int)$_POST['repeat_every'] : null;

        $stmt->execute([
            $tenantId,
            $_POST['slot_slug'],
            $_POST['name'],
            $_POST['ad_type'],
            $_POST['image_url'] ?? null,
            $_POST['link_url'] ?? null,
            $_POST['link_target'] ?? '_blank',
            $_POST['alt_text'] ?? null,
            $_POST['html_content'] ?? null,
            $startsAt,
            $endsAt,
            isset($_POST['is_active']) ? 1 : 0,
            $repeatEvery,
            (int)($_POST['priority'] ?? 0),
        ]);

        flash('success', 'Anuncio creado correctamente.');
        header('Location: /' . admin_path() . '/ads');
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $tenantId = tenant_id();
        $pdo = Database::connect();

        $stmt = $pdo->prepare("SELECT * FROM ad_units WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $ad = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$ad) {
            flash('error', 'Anuncio no encontrado.');
            header('Location: /' . admin_path() . '/ads');
            exit;
        }

        $slots = AdHelper::getSlots();

        return View::renderTenantAdmin('ads.form', [
            'title' => 'Editar anuncio',
            'ad' => $ad,
            'slots' => $slots,
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $tenantId = tenant_id();
        $pdo = Database::connect();

        $startsAt = !empty($_POST['starts_at']) ? $_POST['starts_at'] : null;
        $endsAt = !empty($_POST['ends_at']) ? $_POST['ends_at'] : null;
        $repeatEvery = !empty($_POST['repeat_every']) ? (int)$_POST['repeat_every'] : null;

        $stmt = $pdo->prepare("
            UPDATE ad_units SET
                slot_slug = ?, name = ?, ad_type = ?, image_url = ?, link_url = ?,
                link_target = ?, alt_text = ?, html_content = ?, starts_at = ?, ends_at = ?,
                is_active = ?, repeat_every = ?, priority = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $_POST['slot_slug'],
            $_POST['name'],
            $_POST['ad_type'],
            $_POST['image_url'] ?? null,
            $_POST['link_url'] ?? null,
            $_POST['link_target'] ?? '_blank',
            $_POST['alt_text'] ?? null,
            $_POST['html_content'] ?? null,
            $startsAt,
            $endsAt,
            isset($_POST['is_active']) ? 1 : 0,
            $repeatEvery,
            (int)($_POST['priority'] ?? 0),
            $id,
            $tenantId,
        ]);

        flash('success', 'Anuncio actualizado.');
        header('Location: /' . admin_path() . '/ads');
        exit;
    }

    public function toggle($id)
    {
        SessionSecurity::startSession();
        $tenantId = tenant_id();
        $pdo = Database::connect();

        $pdo->prepare("UPDATE ad_units SET is_active = NOT is_active, updated_at = NOW() WHERE id = ? AND tenant_id = ?")
            ->execute([$id, $tenantId]);

        flash('success', 'Estado del anuncio actualizado.');
        header('Location: /' . admin_path() . '/ads');
        exit;
    }

    public function delete($id)
    {
        SessionSecurity::startSession();
        $tenantId = tenant_id();
        $pdo = Database::connect();

        $pdo->prepare("DELETE FROM ad_units WHERE id = ? AND tenant_id = ?")
            ->execute([$id, $tenantId]);

        flash('success', 'Anuncio eliminado.');
        header('Location: /' . admin_path() . '/ads');
        exit;
    }

    public function saveAdsTxt()
    {
        SessionSecurity::startSession();
        $tenantId = tenant_id();
        $pdo = Database::connect();

        $adsTxt = trim($_POST['ads_txt'] ?? '');

        // Upsert tenant_settings
        $stmt = $pdo->prepare("SELECT id FROM tenant_settings WHERE tenant_id = ? AND key = 'ads_txt'");
        $stmt->execute([$tenantId]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare("UPDATE tenant_settings SET value = ? WHERE tenant_id = ? AND key = 'ads_txt'")
                ->execute([$adsTxt, $tenantId]);
        } else {
            $pdo->prepare("INSERT INTO tenant_settings (tenant_id, key, value) VALUES (?, 'ads_txt', ?)")
                ->execute([$tenantId, $adsTxt]);
        }

        flash('success', 'ads.txt guardado correctamente.');
        header('Location: /' . admin_path() . '/ads');
        exit;
    }
}
