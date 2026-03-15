<?php

namespace CrossPublisherAdmin\Controllers;

use CrossPublisherAdmin\Models\GlobalSettings;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use PDO;

class SettingsController
{
    public function index()
    {
        $settings = GlobalSettings::get();

        // Obtener proveedores de IA disponibles
        $pdo = Database::connect();
        $providers = [];
        try {
            $stmt = $pdo->query("SELECT id, name, provider_type, active FROM ai_providers WHERE active = 1 ORDER BY name ASC");
            $providers = $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Table may not exist
        }

        return View::renderSuperadmin('plugins.cross-publisher.settings.index', [
            'settings' => $settings,
            'providers' => $providers,
        ]);
    }

    public function update()
    {
        GlobalSettings::save([
            'ai_provider_id' => !empty($_POST['ai_provider_id']) ? (int) $_POST['ai_provider_id'] : null,
            'auto_translate' => !empty($_POST['auto_translate']),
            'default_target_status' => $_POST['default_target_status'] ?? 'draft',
            'include_featured_image' => !empty($_POST['include_featured_image']),
            'include_categories' => !empty($_POST['include_categories']),
            'include_tags' => !empty($_POST['include_tags']),
            'add_canonical_link' => !empty($_POST['add_canonical_link']),
            'add_source_credit' => !empty($_POST['add_source_credit']),
            'source_credit_template' => trim($_POST['source_credit_template'] ?? ''),
            'sync_cron_interval' => max(5, (int) ($_POST['sync_cron_interval'] ?? 15)),
            'sync_enabled' => !empty($_POST['sync_enabled']),
        ]);

        flash('success', 'Configuración guardada correctamente.');
        header('Location: /musedock/cross-publisher/settings');
        exit;
    }
}
