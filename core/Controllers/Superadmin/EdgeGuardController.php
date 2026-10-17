<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Security\PasswordConfirmation;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\EdgeGuard\EdgeGuard;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\View;

/**
 * Ajustes → Bloqueos Cloudflare: modo del escudo, sondas, estado de las IPs y cambios hechos.
 */
class EdgeGuardController
{
    use RequiresPermission;

    private const BACK = '/musedock/settings/edge-guard';

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');
        $guard = new EdgeGuard();
        $newToken = $_SESSION['edge_guard_new_token'] ?? null;
        unset($_SESSION['edge_guard_new_token']);

        $records = $guard->records();
        $byIp = [];
        foreach ($records as $r) {
            if ((int) $r['proxied']) {
                foreach (array_filter(explode(',', (string) $r['edge_ips'])) as $ip) {
                    $byIp[$ip][] = $r['name'];
                }
            }
        }

        // Los que están sin proxy por el escudo siguen colgando de su IP de Cloudflare
        foreach ($guard->activeChanges() as $c) {
            if (!(int) $c['simulated']) {
                foreach (array_filter(explode(',', (string) $c['edge_ips'])) as $ip) {
                    $byIp[$ip][] = $c['name'] . ' (sin proxy)';
                }
            }
        }

        // Todos los registros de dominios del CMS: configuración original (antes de que el escudo tocara nada) y actual
        $changedNow = [];
        foreach ($guard->activeChanges() as $c) {
            if (!(int) $c['simulated']) {
                $changedNow[$c['record_id']] = $c;
            }
        }
        $domains = [];
        $statesForZones = $guard->edgeStates();
        $zoneBad = [];
        foreach ($records as $r) {
            $chg = $changedNow[$r['record_id']] ?? null;
            $zoneBad[$r['zone_id']] ??= $guard->zoneUnhealthyIps($r['zone_id'], $statesForZones);
            $domains[] = [
                'record_id' => $r['record_id'], 'zone_blocked' => $zoneBad[$r['zone_id']],
                'manual' => $chg && $chg['cause'] === 'manual',
                'name' => $r['name'], 'type' => $r['type'], 'content' => $r['content'],
                'original' => $chg ? true : (bool) (int) $r['proxied'],
                'now' => (bool) (int) $r['proxied'],
                'changed_at' => $chg['changed_at'] ?? null,
                'excluded' => $guard->isExcluded($r['name']),
            ];
        }

        // Primero lo bloqueado y lo que tiene fallos; después el resto
        $states = $guard->edgeStates();
        $rank = fn ($s) => $s['state'] === 'blocked' ? 0 : (in_array(false, $s['paths'], true) ? 1 : 2);
        uasort($states, fn ($a, $b) => $rank($a) <=> $rank($b));

        return View::renderSuperadmin('settings.edge-guard', [
            'title' => 'Bloqueos Cloudflare',
            'mode' => $guard->mode(),
            'restoreMinutes' => $guard->restoreMinutes(),
            'excluded' => (string) $guard->setting('excluded', ''),
            'probes' => $guard->probes(),
            'probePaths' => $guard->probePaths(),
            'livePaths' => $guard->livePaths(),
            'states' => $states,
            'domains' => $domains,
            'byIp' => $byIp,
            'records' => $records,
            'active' => $guard->activeChanges(),
            'history' => $guard->history(),
            'events' => $guard->events(),
            'inventoryAt' => (int) $guard->setting('inventory_at', '0'),
            'inventoryErrors' => (string) $guard->setting('inventory_errors', ''),
            'newToken' => $newToken,
            'apiBase' => rtrim((string) \Screenart\Musedock\Env::get('APP_URL', ''), '/'),
            'guard' => $guard,
        ]);
    }

    public function save()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $guard = new EdgeGuard();
        $mode = (string) ($_POST['mode'] ?? 'off');
        if (in_array($mode, EdgeGuard::MODES, true) && $mode !== $guard->mode()) {
            $guard->setSetting('mode', $mode);
            $guard->event('info', 'Modo cambiado a «' . ['off' => 'apagado', 'simulate' => 'simulación', 'auto' => 'automático'][$mode] . '».');
        }
        $guard->setSetting('restore_minutes', (string) max(10, min(240, (int) ($_POST['restore_minutes'] ?? 30))));
        $excluded = implode("\n", array_slice(array_filter(array_map(fn ($l) => strtolower(trim($l, " \t.")), preg_split('/[\r\n,]+/', (string) ($_POST['excluded'] ?? '')))), 0, 500));
        $guard->setSetting('excluded', $excluded !== '' ? $excluded : null);
        flash('success', 'Ajustes guardados.');

        return $this->back();
    }

    public function createProbe()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'Pon un nombre a la sonda (p. ej. b250).');

            return $this->back();
        }
        $_SESSION['edge_guard_new_token'] = ['name' => $name, 'token' => (new EdgeGuard())->createProbe($name)];

        return $this->back();
    }

    public function revokeProbe()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        (new EdgeGuard())->revokeProbe((string) ($_POST['name'] ?? ''));
        flash('success', 'Sonda revocada: su token deja de valer.');

        return $this->back();
    }

    /** Vacía el registro y el historial cerrado (los cambios activos se conservan: hacen falta para deshacerlos). */
    public function clearLog()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        (new EdgeGuard())->clearLog();
        flash('success', 'Registro vaciado. Los cambios activos se conservan para poder deshacerlos.');

        return $this->back();
    }

    /** Cambia a mano la configuración original (con / sin proxy) de un registro. Pide la contraseña. */
    public function setOriginal()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        if (!PasswordConfirmation::verify((string) ($_POST['confirm_password'] ?? ''))) {
            flash('error', 'Contraseña incorrecta (o demasiados intentos): no se ha cambiado nada.');

            return $this->back();
        }
        $who = (string) ($_SESSION['super_admin']['email'] ?? $_SESSION['super_admin']['name'] ?? 'superadmin');
        try {
            $msg = (new EdgeGuard())->setOriginal((string) ($_POST['record_id'] ?? ''), ($_POST['proxied'] ?? '') === '1', $who);
            flash('success', $msg);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo cambiar: ' . $e->getMessage());
        }

        return $this->back();
    }

    /** Descarga del cliente de sonda (para instalarlo en la máquina que vigila). */
    public function downloadProbe()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');
        header('Content-Type: text/x-python; charset=utf-8');
        header('Content-Disposition: attachment; filename="musedock-edge-probe.py"');
        header('Cache-Control: no-store');
        readfile(APP_ROOT . '/cli/edge-guard-probe.py');
        exit;
    }

    public function inventory()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');
        try {
            $r = (new EdgeGuard())->refreshInventory();
            flash($r['errors'] ? 'error' : 'success', "Leídos {$r['records']} registros de Cloudflare ({$r['proxied']} con proxy)." . ($r['errors'] ? ' Errores: ' . implode('; ', $r['errors']) : ''));
        } catch (\Throwable $e) {
            flash('error', 'No se pudo leer Cloudflare: ' . $e->getMessage());
        }

        return $this->back();
    }

    private function back()
    {
        header('Location: ' . self::BACK);
        exit;
    }
}
