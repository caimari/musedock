<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Mover el SEO entre un dominio de un tenant y su alias (por ejemplo alexapost.musedock.com → alexapost.com)
 * con una redirección 301 que conserva la ruta.
 *
 * La ruta de Caddy se inserta AL PRINCIPIO de la lista: la del tenant (y la del comodín *.musedock.com)
 * también casan con ese host y, si la redirección fuera detrás, nunca se aplicaría. Se comprueba que el
 * destino responde, se aplica, se verifica con una petición real y, si falla, se deshace.
 * Se guarda también en domain_redirects para que el Domain Manager la vea y repair-caddy-routes la reponga.
 *
 * Solo tenants PROPIOS: el dominio de un cliente nunca se toca.
 */
class RedirectService
{
    /** @var callable(string,string,?array):array{code:int,body:string} */
    private $api;
    /** @var callable(string):array{code:int,location:?string}|null  petición real al host (inyectable) */
    private $probe;
    /** @var callable(string):int  código HTTP de https://host/ */
    private $alive;

    public function __construct(private PDO $pdo, ?callable $api = null, ?callable $probe = null, ?callable $alive = null)
    {
        $this->api = $api ?? [$this, 'caddy'];
        $this->probe = $probe ?? [$this, 'probeHost'];
        $this->alive = $alive ?? [$this, 'httpCode'];
    }

    public static function routeId(string $host): string
    {
        return 'redirect-' . str_replace('.', '-', $host);
    }

    /**
     * Parejas dominio-del-tenant ⇄ alias de tenants propios, con el estado de la redirección.
     * Cada fila: tenant, tenant_domain, alias, alias_status, redirect ('' | 'tenant' | 'alias'), redirect_to.
     */
    public function pairs(): array
    {
        $redirects = [];
        foreach ($this->pdo->query("SELECT domain, redirect_to FROM domain_redirects WHERE status = 'active'") as $r) {
            $redirects[PropertyService::normalizeHost($r['domain'])] = $r['redirect_to'];
        }
        $out = [];
        $stmt = $this->pdo->query('SELECT a.domain AS alias, a.status AS alias_status, t.id AS tenant_id, t.name, t.domain AS tenant_domain FROM domain_aliases a JOIN tenants t ON t.id = a.tenant_id WHERE t.is_client_domain = 0 ORDER BY t.name, a.domain');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $td = PropertyService::normalizeHost($p['tenant_domain']);
            $al = PropertyService::normalizeHost($p['alias']);
            if ($td === $al) {
                continue;
            }
            $state = '';
            // La redirección pertenece a ESTA pareja solo si su destino es el otro nombre (un tenant puede tener varios alias)
            $dest = fn (?string $url) => PropertyService::normalizeHost((string) parse_url((string) $url, PHP_URL_HOST));
            if (isset($redirects[$td]) && $dest($redirects[$td]) === $al) {
                $state = 'tenant';
            } elseif (isset($redirects[$al]) && $dest($redirects[$al]) === $td) {
                $state = 'alias';
            }
            $out[] = ['tenant_id' => (int) $p['tenant_id'], 'name' => $p['name'], 'tenant_domain' => $td, 'alias' => $al, 'alias_status' => $p['alias_status'],
                'redirect' => $state, 'redirect_to' => $state === 'tenant' ? ($redirects[$td] ?? '') : ($state === 'alias' ? ($redirects[$al] ?? '') : '')];
        }

        return $out;
    }

    /**
     * Añade a cada pareja el tráfico de Google de cada nombre (clics e impresiones de todo el histórico descargado)
     * y si es subdominio de la plataforma o dominio propio, para que la pantalla deje claro hacia dónde va el SEO.
     */
    public function withTraffic(array $pairs): array
    {
        $traffic = [];
        try {
            $rows = $this->pdo->query('SELECT p.domain, COALESCE(SUM(d.clicks), 0) AS clicks, COALESCE(SUM(d.impressions), 0) AS impressions
                FROM gsc_properties p LEFT JOIN gsc_site_daily d ON d.property_id = p.id GROUP BY p.domain')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $h = PropertyService::normalizeHost((string) $r['domain']);
                $traffic[$h] = ['clicks' => ($traffic[$h]['clicks'] ?? 0) + (int) $r['clicks'], 'impressions' => ($traffic[$h]['impressions'] ?? 0) + (int) $r['impressions']];
            }
        } catch (\Throwable $e) {
            // sin datos de Search Console: la pantalla funciona igual, sin cifras
        }
        $platform = PropertyService::normalizeHost((string) parse_url((string) \Screenart\Musedock\Env::get('APP_URL', ''), PHP_URL_HOST));
        $kind = fn (string $h) => ($platform !== '' && str_ends_with($h, '.' . $platform)) ? 'subdominio de la plataforma' : 'dominio propio';
        foreach ($pairs as &$p) {
            foreach (['tenant_domain' => 'tenant', 'alias' => 'alias'] as $field => $side) {
                $t = $traffic[$p[$field]] ?? null;
                $p[$side . '_clicks'] = $t ? $t['clicks'] : null;
                $p[$side . '_impressions'] = $t ? $t['impressions'] : null;
                $p[$side . '_kind'] = $kind($p[$field]);
            }
            // Hacia dónde debería ir el SEO según Google: el nombre con más impresiones (null si no hay datos o empatan)
            $ti = (int) ($p['tenant_impressions'] ?? 0);
            $ai = (int) ($p['alias_impressions'] ?? 0);
            $p['seo_side'] = $ti === $ai ? null : ($ti > $ai ? 'tenant' : 'alias');
        }
        unset($p);

        return $pairs;
    }

    /** ¿Es un movimiento permitido? Devuelve null si sí, o el motivo. */
    public function validate(string $from, string $to): ?string
    {
        $from = PropertyService::normalizeHost($from);
        $to = PropertyService::normalizeHost($to);
        if ($from === '' || $to === '' || $from === $to) {
            return 'Origen y destino no válidos.';
        }
        $pair = null;
        foreach ($this->pairs() as $p) {
            if (($p['tenant_domain'] === $from && $p['alias'] === $to) || ($p['alias'] === $from && $p['tenant_domain'] === $to)) {
                $pair = $p;
            }
        }
        if ($pair === null) {
            return 'Solo se puede mover el SEO entre el dominio de un tenant propio y uno de sus alias.';
        }
        if ($pair['alias_status'] !== 'active') {
            return 'El alias no está activo: su DNS puede no apuntar aquí.';
        }
        if ($pair['redirect'] !== '') {
            return 'Ya hay una redirección activa entre estos dominios: quítala antes de crear otra.';
        }
        // El destino no puede ser él mismo una redirección (bucle)
        $stmt = $this->pdo->prepare("SELECT 1 FROM domain_redirects WHERE domain = ? AND status = 'active'");
        $stmt->execute([$to]);
        if ($stmt->fetchColumn()) {
            return 'El destino ya redirige a otro sitio.';
        }

        return null;
    }

    /** Aplica el 301 origen → destino. @return array{ok:bool,message:string} */
    public function apply(string $from, string $to): array
    {
        $from = PropertyService::normalizeHost($from);
        $to = PropertyService::normalizeHost($to);
        if ($why = $this->validate($from, $to)) {
            return ['ok' => false, 'message' => $why];
        }
        $code = ($this->alive)($to);
        if ($code < 200 || $code >= 400) {
            return ['ok' => false, 'message' => "El destino https://{$to}/ no responde bien (HTTP {$code}). No se ha cambiado nada."];
        }

        $id = self::routeId($from);
        $route = ['@id' => $id, 'match' => [['host' => [$from]]], 'terminal' => true,
            'handle' => [['handler' => 'static_response', 'status_code' => '301', 'headers' => ['Location' => ['https://' . $to . '{http.request.uri}']]]]];

        $this->removeRoute($id);
        $r = ($this->api)('PUT', '/config/apps/http/servers/srv0/routes/0', $route);
        if ($r['code'] < 200 || $r['code'] >= 300) {
            return ['ok' => false, 'message' => "Caddy rechazó la ruta (HTTP {$r['code']}). No se ha cambiado nada."];
        }

        // Verificación real: el host debe contestar 301 al destino
        $p = ($this->probe)($from);
        if ($p['code'] !== 301 || stripos((string) $p['location'], 'https://' . $to) !== 0) {
            $this->removeRoute($id);

            return ['ok' => false, 'message' => 'La verificación falló (' . $p['code'] . ' ' . ($p['location'] ?? '') . '): se ha deshecho la redirección.'];
        }

        $this->saveRow($from, $to);

        return ['ok' => true, 'message' => "{$from} → {$to}: redirección 301 aplicada y verificada (conserva la ruta)."];
    }

    /** Quita la redirección de un dominio. */
    public function remove(string $from): array
    {
        $from = PropertyService::normalizeHost($from);
        $isPair = false;
        foreach ($this->pairs() as $p) {
            if (($p['redirect'] === 'tenant' && $p['tenant_domain'] === $from) || ($p['redirect'] === 'alias' && $p['alias'] === $from)) {
                $isPair = true;
            }
        }
        if (!$isPair) {
            return ['ok' => false, 'message' => 'Esa redirección no es un movimiento de SEO de un tenant propio.'];
        }
        $this->removeRoute(self::routeId($from));
        $this->pdo->prepare('DELETE FROM domain_redirects WHERE domain = ?')->execute([$from]);

        return ['ok' => true, 'message' => "Redirección de {$from} eliminada: vuelve a servirse el sitio."];
    }

    /**
     * Acción masiva sobre parejas dominio-del-tenant ⇄ alias, identificadas como «dominio-del-tenant|alias».
     * mode: 'to_alias' (el SEO va al alias), 'to_tenant' (el SEO va al dominio del tenant) o 'remove' (quita las redirecciones).
     * Cada pareja se aplica y verifica por separado: un fallo no impide las demás.
     *
     * @param string[] $pairKeys «tenant_domain|alias»
     * @return array{ok:int,failed:int,results:array<int,array{pair:string,ok:bool,message:string}>}
     */
    public function bulk(string $mode, array $pairKeys): array
    {
        $wanted = [];
        foreach ($pairKeys as $k) {
            [$t, $a] = array_pad(explode('|', (string) $k, 2), 2, '');
            $wanted[PropertyService::normalizeHost($t) . '|' . PropertyService::normalizeHost($a)] = true;
        }
        $results = [];
        foreach ($this->pairs() as $p) {
            if (!isset($wanted[$p['tenant_domain'] . '|' . $p['alias']])) {
                continue;
            }
            $label = $p['tenant_domain'] . ' ⇄ ' . $p['alias'];
            if ($mode === 'remove') {
                if ($p['redirect'] === '') {
                    $r = ['ok' => false, 'message' => 'No tiene redirección.'];
                } else {
                    $r = $this->remove($p['redirect'] === 'tenant' ? $p['tenant_domain'] : $p['alias']);
                }
            } elseif ($mode === 'to_alias') {
                $r = $this->apply($p['tenant_domain'], $p['alias']);
            } elseif ($mode === 'to_tenant') {
                $r = $this->apply($p['alias'], $p['tenant_domain']);
            } else {
                $r = ['ok' => false, 'message' => 'Acción no válida.'];
            }
            $dest = $mode === 'to_alias' ? $p['alias'] : ($mode === 'to_tenant' ? $p['tenant_domain'] : null);
            $results[] = ['pair' => $label, 'ok' => $r['ok'], 'message' => $r['message'], 'to' => $dest];
        }
        $ok = count(array_filter($results, fn ($r) => $r['ok']));

        return ['ok' => $ok, 'failed' => count($results) - $ok, 'results' => $results];
    }

    private function saveRow(string $from, string $to): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM domain_redirects WHERE domain = ?');
        $stmt->execute([$from]);
        if ($id = $stmt->fetchColumn()) {
            $this->pdo->prepare("UPDATE domain_redirects SET redirect_to = ?, redirect_type = 301, include_www = 0, preserve_path = 1, caddy_configured = 1, status = 'active', error_log = NULL WHERE id = ?")
                ->execute(['https://' . $to, $id]);
        } else {
            $this->pdo->prepare("INSERT INTO domain_redirects (domain, redirect_to, redirect_type, include_www, is_subdomain, preserve_path, caddy_configured, status, created_at) VALUES (?, ?, 301, 0, 0, 1, 1, 'active', NOW())")
                ->execute([$from, 'https://' . $to]);
        }
    }

    private function removeRoute(string $id): void
    {
        $routes = json_decode(($this->api)('GET', '/config/apps/http/servers/srv0/routes', null)['body'], true) ?: [];
        $idx = [];
        foreach ($routes as $i => $r) {
            if (is_array($r) && ($r['@id'] ?? null) === $id) {
                $idx[] = (int) $i;
            }
        }
        rsort($idx);
        foreach ($idx as $i) {
            ($this->api)('DELETE', "/config/apps/http/servers/srv0/routes/{$i}", null);
        }
    }

    // ---- red real ----

    private function caddy(string $method, string $path, ?array $body): array
    {
        $base = rtrim((string) (\Screenart\Musedock\Env::get('CADDY_API_URL', 'http://localhost:2019')), '/');
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json']]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $code, 'body' => $out === false ? '' : (string) $out];
    }

    private function probeHost(string $host): array
    {
        usleep(600000); // Caddy tarda un instante en aplicar la config
        $ch = curl_init('https://' . $host . '/ruta-de-prueba?x=1');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10, CURLOPT_RESOLVE => [$host . ':443:127.0.0.1']]);
        $head = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $loc = preg_match('/^location:\s*(\S+)/im', $head, $m) ? $m[1] : null;

        return ['code' => $code, 'location' => $loc];
    }

    private function httpCode(string $host): int
    {
        $ch = curl_init('https://' . $host . '/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code;
    }
}
