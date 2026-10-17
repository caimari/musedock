<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\Notifications\NotificationCenter;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;

/**
 * Ajustes → Avisos: a quién van los avisos de la plataforma, en qué idioma y cuáles están silenciados.
 */
class NotificationSettingsController
{
    use RequiresPermission;

    private const BACK = '/musedock/settings/notifications';

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');
        NotificationCenter::importLegacy();
        $types = [];
        foreach (NotificationCenter::types() as $key => $def) {
            $types[$key] = $def + NotificationCenter::typeSettings($key);
        }
        $admins = \Screenart\Musedock\Database::connect()->query("SELECT name, email, locale FROM super_admins WHERE email IS NOT NULL AND email <> '' ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);

        return View::renderSuperadmin('settings.notifications', [
            'title' => 'Avisos',
            'recipients' => NotificationCenter::recipients(),
            'types' => $types,
            'admins' => $admins,
            'locales' => NotificationCenter::LOCALES,
            'log' => NotificationCenter::log(40),
        ]);
    }

    public function addRecipient()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        try {
            NotificationCenter::addRecipient((string) ($_POST['email'] ?? ''), (string) ($_POST['name'] ?? ''), (string) ($_POST['locale'] ?? 'es'));
            flash('success', 'Destinatario guardado.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        return $this->back();
    }

    public function updateRecipient()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'delete') {
            NotificationCenter::deleteRecipient($id);
            flash('success', 'Destinatario quitado.');
        } else {
            NotificationCenter::updateRecipient($id, (string) ($_POST['locale'] ?? 'es'), !empty($_POST['enabled']));
            flash('success', 'Destinatario actualizado.');
        }

        return $this->back();
    }

    public function saveTypes()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $posted = (array) ($_POST['types'] ?? []);
        foreach (array_keys(NotificationCenter::types()) as $key) {
            $t = (array) ($posted[$key] ?? []);
            $only = ($t['scope'] ?? 'all') === 'some';
            $ids = $only ? array_values(array_map('intval', (array) ($t['recipients'] ?? []))) : null;
            NotificationCenter::saveType($key, !empty($t['enabled']), $ids);
        }
        flash('success', 'Tipos de aviso guardados.');

        return $this->back();
    }

    /** Envía un aviso de prueba de un tipo, a sus destinatarios actuales y en el idioma de cada uno. */
    public function test()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $type = (string) ($_POST['type'] ?? '');
        $def = NotificationCenter::types()[$type] ?? null;
        if (!$def) {
            flash('error', 'Tipo de aviso desconocido.');

            return $this->back();
        }
        $n = NotificationCenter::send($type, [
            'es' => ['subject' => '[Prueba] ' . $def['es'], 'html' => '<p>Esto es un aviso de prueba de <strong>' . htmlspecialchars($def['es']) . '</strong>. Si lo recibes, los avisos de este tipo te llegarán así.</p><p>Ajustes → Avisos</p>'],
            'en' => ['subject' => '[Test] ' . $def['en'], 'html' => '<p>This is a test notice for <strong>' . htmlspecialchars($def['en']) . '</strong>. If you get it, notices of this type will reach you like this.</p><p>Settings → Notifications</p>'],
        ]);
        flash($n ? 'success' : 'error', $n ? "Prueba enviada a {$n} destinatario(s)." : 'No se envió nada: el tipo está silenciado, no tiene destinatarios o falló el correo (mira el registro).');

        return $this->back();
    }

    private function back()
    {
        header('Location: ' . self::BACK);
        exit;
    }
}
