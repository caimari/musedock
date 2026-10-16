<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Security\PasswordConfirmation;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\Dns\CloudflareClient;
use Screenart\Musedock\Services\Dns\DnsZones;
use Screenart\Musedock\Services\DnsAccounts;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;

/**
 * Ajustes → Cuentas DNS: gestiona el registro único de cuentas DNS (Cloudflare y otros proveedores).
 * Lo que se guarda aquí lo usan Domain Manager, Cloud, Search Console y cualquier plugin o módulo.
 */
class DnsAccountsController
{
    use RequiresPermission;

    private const BACK = '/musedock/settings/dns-accounts';

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');
        $registry = DnsAccounts::make();

        return View::renderSuperadmin('settings.dns-accounts', [
            'title' => 'Cuentas DNS',
            'accounts' => $registry->all(),
            'providers' => DnsAccounts::PROVIDERS,
        ]);
    }

    /** Crea o actualiza una cuenta. Los secretos vacíos conservan el valor guardado. */
    public function save()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $registry = DnsAccounts::make();
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $provider = strtolower(trim((string) ($_POST['provider'] ?? '')));
        $meta = [];
        if ($provider === 'cloudflare' || ($id && ($registry->find($id)['provider'] ?? '') === 'cloudflare')) {
            $role = (string) ($_POST['role'] ?? 'primary');
            $meta = [
                'role' => in_array($role, ['primary', 'domains', 'readonly'], true) ? $role : 'primary',
                'ssl_mode' => in_array($_POST['ssl_mode'] ?? '', ['flexible', 'full', 'strict'], true) ? $_POST['ssl_mode'] : 'full',
            ];
        }
        try {
            $newId = $registry->save($id, [
                'provider' => $provider,
                'name' => (string) ($_POST['name'] ?? ''),
                'account_ref' => (string) ($_POST['account_ref'] ?? ''),
                'enabled' => !empty($_POST['enabled']) ? 1 : 0,
                'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                'credentials' => is_array($_POST['credentials'] ?? null) ? $_POST['credentials'] : [],
                'metadata' => $meta,
            ]);
            $res = (new DnsZones($registry))->test($newId);
            flash($res['ok'] ? 'success' : 'warning', 'Cuenta guardada. Prueba: ' . $res['message']);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            flash('error', 'No se pudo guardar la cuenta.');
            error_log('[dns-accounts] ' . $e->getMessage());
        }

        return $this->back();
    }

    public function test($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $res = (new DnsZones(DnsAccounts::make()))->test((int) $id);
        flash($res['ok'] ? 'success' : 'error', $res['message']);

        return $this->back();
    }

    public function makeDefault($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        try {
            DnsAccounts::make()->setDefault((int) $id);
            flash('success', 'Cuenta marcada como predeterminada de su proveedor.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        return $this->back();
    }

    public function toggle($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $registry = DnsAccounts::make();
        $a = $registry->find((int) $id);
        if ($a) {
            $registry->save((int) $id, ['enabled' => $a['enabled'] ? 0 : 1]);
            flash('success', $a['enabled'] ? 'Cuenta deshabilitada: los consumidores dejan de usarla.' : 'Cuenta habilitada.');
        }

        return $this->back();
    }

    /** Borrar exige la contraseña del administrador: otros plugins pueden depender de la cuenta. */
    public function delete($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        if (!PasswordConfirmation::verify((string) ($_POST['confirm_password'] ?? ''))) {
            flash('error', 'Contraseña incorrecta (o demasiados intentos): no se ha borrado nada.');

            return $this->back();
        }
        DnsAccounts::make()->delete((int) $id);
        flash('success', 'Cuenta DNS borrada.');

        return $this->back();
    }

    /** Copia al registro lo que hubiera en Domain Manager (tabla antigua) y en el .env, sin copiar tokens rechazados. */
    public function import()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $r = DnsAccounts::make()->importLegacy(function (string $token): ?bool {
            $v = (new CloudflareClient($token))->verify();
            if ($v['ok']) {
                return true;
            }

            return str_contains($v['message'], 'Cloudflare respondió 4') || str_contains($v['message'], 'no está activo') ? false : null;
        });
        $msg = sprintf('Importadas: %d de Domain Manager y %d del .env.', $r['legacy'], $r['env']);
        if ($r['invalid']) {
            $msg .= ' No se importaron por token rechazado por Cloudflare: ' . implode(', ', $r['invalid']) . '.';
        }
        flash('success', $msg);

        return $this->back();
    }

    private function back()
    {
        header('Location: ' . self::BACK);
        exit;
    }
}
