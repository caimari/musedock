<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * «Dar de alta todos»: cola de verificaciones de dominios propios que sobrevive a recargar la página.
 *
 * El estado vive en un fichero (storage/cache); lo avanza quien llame a step(): el navegador mientras la página
 * está abierta y, si se cierra, el cron (cli/cron-search-console.php). Un dominio cuyo TXT ya está puesto pero
 * que Google aún no ve vuelve a la cola hasta MAX_TRIES veces, espaciando los intentos (20 s, 45 s, 90 s, 3 min, 5 min).
 * Solo la plataforma usa esto: la cola nunca contiene dominios de clientes.
 */
class BulkVerifyService
{
    /** Google puede tardar varios minutos en ver un TXT recién publicado: los reintentos se van espaciando. */
    public const MAX_TRIES = 6;
    public const RETRY_DELAYS = [20, 45, 90, 180, 300];
    public const RETRY_AFTER = 20;
    /** Un trabajo en marcha sin avanzar durante tanto tiempo lo recoge el cron. */
    public const STALE_AFTER = 90;

    private string $file;

    public function __construct(private PDO $pdo, private PropertyService $props, ?string $file = null)
    {
        $this->file = $file ?? (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/storage/cache/search-console-bulk.json';
    }

    public function state(): array
    {
        $raw = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;

        return is_array($raw) ? $raw : ['status' => 'idle', 'items' => [], 'updated_at' => 0];
    }

    private function save(array $s): void
    {
        $s['updated_at'] = time();
        file_put_contents($this->file, json_encode($s, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** Prepara la cola con los dominios propios que se pueden dar de alta ahora (todos, o solo alias / solo principales). */
    public function start(int $credentialId, ?string $scope = null): array
    {
        $items = [];
        foreach ($this->props->ownDomainsWithoutProperty($credentialId) as $m) {
            if (!empty($m['disabled'])) {
                continue;
            }
            // scope: 'alias' = solo alias, 'main' = solo dominios principales, null = todos
            if (($scope === 'alias' && empty($m['alias_of'])) || ($scope === 'main' && !empty($m['alias_of']))) {
                continue;
            }
            $items[$m['domain']] = ['domain' => $m['domain'], 'state' => 'queued', 'message' => '', 'tries' => 0, 'next_at' => 0];
        }
        $s = ['status' => $items ? 'running' : 'done', 'credential_id' => $credentialId, 'started_at' => time(), 'items' => $items, 'current' => null];
        $this->save($s);

        return $this->view($s);
    }

    public function stop(): array
    {
        $s = $this->state();
        if ($s['status'] === 'running') {
            $s['status'] = 'stopped';
            $s['current'] = null;
            $this->save($s);
        }

        return $this->view($s);
    }

    public function resume(): array
    {
        $s = $this->state();
        if ($s['status'] === 'stopped') {
            $s['status'] = 'running';
            $this->save($s);
        }

        return $this->view($s);
    }

    /** ¿Hay un trabajo en marcha que nadie está avanzando? (lo recoge el cron) */
    public function isStale(): bool
    {
        $s = $this->state();

        return $s['status'] === 'running' && time() - (int) $s['updated_at'] > self::STALE_AFTER;
    }

    /**
     * Procesa como mucho un dominio. Devuelve el estado para pintar. Un cerrojo evita que dos pestañas
     * (o el cron y una pestaña) procesen a la vez.
     */
    public function step(CredentialStore $store): array
    {
        $lock = fopen($this->file . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->view($this->state(), true); // otro proceso (el cron u otra pestaña) está procesando
        }
        try {
            $s = $this->state();
            if ($s['status'] !== 'running') {
                return $this->view($s);
            }
            $now = time();
            $next = null;
            foreach ($s['items'] as $key => $it) {
                if (in_array($it['state'], ['queued', 'waiting'], true) && $it['next_at'] <= $now) {
                    $next = $key;
                    break;
                }
            }
            if ($next === null) {
                $open = array_filter($s['items'], fn ($i) => in_array($i['state'], ['queued', 'waiting'], true));
                if (!$open) {
                    $s['status'] = 'done';
                    $s['current'] = null;
                    $this->save($s);
                }

                return $this->view($s); // quedan reintentos que aún no tocan
            }

            $s['current'] = $next;
            $s['items'][$next]['state'] = 'working';
            $this->save($s);

            $item = &$s['items'][$next];
            $item['tries']++;
            $cred = $store->findById((int) $s['credential_id']);
            try {
                if (!$cred || !$this->allowed($item['domain'])) {
                    $item['state'] = 'error';
                    $item['message'] = 'Dominio no permitido.';
                } else {
                    $client = $store->client($cred);
                    $r = (new DomainVerifier($client))->verify($item['domain']);
                    $this->record($cred, $client, $item['domain'], $r);
                    $item['message'] = (string) ($r['message'] ?? '');
                    if ($r['status'] === 'verified') {
                        $item['state'] = 'verified';
                    } elseif ($r['status'] === 'pending' && $item['tries'] < self::MAX_TRIES) {
                        $item['state'] = 'waiting';
                        $item['next_at'] = time() + self::RETRY_DELAYS[min($item['tries'] - 1, count(self::RETRY_DELAYS) - 1)];
                    } elseif ($r['status'] === 'pending') {
                        $item['state'] = 'pending'; // se queda pendiente: lo sigue reintentando el cron
                    } else {
                        $item['state'] = $r['status'] === 'manual' ? 'manual' : 'error';
                    }
                }
            } catch (\Throwable $e) {
                $item['state'] = 'error';
                $item['message'] = mb_substr($e->getMessage(), 0, 300);
            }
            unset($item);
            $s['current'] = null;
            if (!array_filter($s['items'], fn ($i) => in_array($i['state'], ['queued', 'waiting'], true))) {
                $s['status'] = 'done';
            }
            $this->save($s);

            return $this->view($s);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Solo dominios propios (o alias activos de tenants propios): nunca de clientes. */
    private function allowed(string $domain): bool
    {
        $owner = $this->props->hostMap()[$domain] ?? null;

        return $owner !== null && empty($owner['client']) && ($owner['alias_status'] ?? 'active') === 'active';
    }

    private function record(array $cred, GoogleClient $client, string $domain, array $r): void
    {
        if ($r['status'] === 'verified') {
            try {
                $this->props->refreshFromGoogle($cred, $client);
            } catch (\Throwable $e) {
                // se enlazará en la próxima actualización
            }
            try {
                (new SitemapService())->submitForDomain($this->pdo, new CredentialStore($this->pdo), $domain); // Google rastrea antes el dominio nuevo
            } catch (\Throwable $e) {
                // no es crítico
            }

            return;
        }
        if (in_array($r['status'], ['pending', 'manual'], true)) {
            $owner = $this->props->hostMap()[$domain] ?? null;
            $this->props->markPending((int) $cred['id'], $domain, $owner['tenant_id'] ?? null, $owner === null, (string) $r['message']);
        }
    }

    /** Estado listo para el navegador. */
    public function view(array $s, bool $busy = false): array
    {
        $counts = ['queued' => 0, 'waiting' => 0, 'working' => 0, 'verified' => 0, 'pending' => 0, 'manual' => 0, 'error' => 0];
        $now = time();
        $pos = 0;
        $items = [];
        foreach ($s['items'] as $i) {
            $counts[$i['state']] = ($counts[$i['state']] ?? 0) + 1;
            $items[] = ['domain' => $i['domain'], 'state' => $i['state'], 'message' => $i['message'], 'tries' => $i['tries'],
                'position' => $i['state'] === 'queued' ? ++$pos : null,
                'next_in' => $i['state'] === 'waiting' ? max(0, (int) $i['next_at'] - $now) : null];
        }

        return [
            'status' => $s['status'],
            'total' => count($s['items']),
            'finished' => count($s['items']) - $counts['queued'] - $counts['waiting'] - $counts['working'],
            'counts' => $counts,
            'current' => $s['current'] ?? null,
            'busy' => $busy,
            'idle_for' => $now - (int) ($s['updated_at'] ?? $now),
            'items' => $items,
        ];
    }
}
