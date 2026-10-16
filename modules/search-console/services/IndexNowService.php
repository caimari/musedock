<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * IndexNow: aviso instantáneo de URL nuevas o cambiadas a Bing, Yandex y otros buscadores (NO a Google, que no lo
 * usa; para Google están el sitemap y la inspección). No toca el núcleo del CMS: cada hora lee el sitemap de cada
 * dominio propio y avisa solo de lo nuevo o con otro lastmod. La primera lectura de un dominio solo toma nota
 * (no avisa de todo su contenido de golpe).
 *
 * La clave se guarda en storage/indexnow.json y se sirve en https://dominio/<clave>.txt (ruta del módulo).
 * Solo dominios de tenants PROPIOS; un dominio con redirección activa (origen) no se vigila.
 */
class IndexNowService
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';
    public const CHUNK = 500;

    /** @var callable(string):?string */
    private $get;
    /** @var callable(string,array):int */
    private $post;
    private string $file;

    public function __construct(private PDO $pdo, ?callable $get = null, ?callable $post = null, ?string $file = null)
    {
        $this->get = $get ?? [self::class, 'fetch'];
        $this->post = $post ?? [self::class, 'postJson'];
        $this->file = $file ?? (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/storage/indexnow.json';
    }

    // ---- configuración (clave + interruptor) ----

    public function config(): array
    {
        $c = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;

        return is_array($c) ? $c + ['enabled' => true, 'key' => ''] : ['enabled' => true, 'key' => ''];
    }

    /**
     * Crea la clave la primera vez y deja el fichero <clave>.txt en public/: todos los dominios comparten ese
     * docroot, así el buscador lo encuentra en la raíz de cada uno sin depender de que el módulo esté activo en el tenant.
     */
    public function ensureKey(): string
    {
        $c = $this->config();
        if (!preg_match('/^[a-f0-9]{16,64}$/', (string) $c['key'])) {
            $c['key'] = bin2hex(random_bytes(16));
            file_put_contents($this->file, json_encode($c), LOCK_EX);
        }
        $public = dirname($this->file, 2) . '/public/' . $c['key'] . '.txt';
        if (!is_file($public) && is_dir(dirname($public))) {
            @file_put_contents($public, $c['key']);
        }

        return $c['key'];
    }

    public function setEnabled(bool $on): void
    {
        $c = $this->config();
        $c['enabled'] = $on;
        $this->ensureKey();
        $c['key'] = $this->config()['key'];
        file_put_contents($this->file, json_encode($c), LOCK_EX);
    }

    public function keyUrl(string $host): string
    {
        return 'https://' . $host . '/' . $this->ensureKey() . '.txt';
    }

    // ---- qué dominios vigilar ----

    /** Hosts propios que sirven contenido: dominio de cada tenant propio y alias activos, sin los que redirigen. */
    public function ownHosts(): array
    {
        $hosts = [];
        foreach ($this->pdo->query('SELECT domain FROM tenants WHERE is_client_domain = 0') as $t) {
            $hosts[] = PropertyService::normalizeHost((string) $t['domain']);
        }
        try {
            foreach ($this->pdo->query("SELECT a.domain FROM domain_aliases a JOIN tenants t ON t.id = a.tenant_id WHERE t.is_client_domain = 0 AND a.status = 'active'") as $a) {
                $hosts[] = PropertyService::normalizeHost((string) $a['domain']);
            }
        } catch (\Throwable $e) {
            // alias opcional
        }
        $main = PropertyService::mainDomain();
        if ($main !== '') {
            $hosts[] = $main;
        }
        $redirecting = [];
        try {
            foreach ($this->pdo->query("SELECT domain FROM domain_redirects WHERE status = 'active'") as $r) {
                $redirecting[PropertyService::normalizeHost((string) $r['domain'])] = true;
            }
        } catch (\Throwable $e) {
            // tabla opcional
        }

        return array_values(array_filter(array_unique($hosts), fn ($h) => $h !== '' && str_contains($h, '.') && !isset($redirecting[$h])));
    }

    // ---- sitemap ----

    /** @return array<string,?string> url => lastmod (solo URL del propio host) */
    public static function parseSitemap(string $xml, string $host): array
    {
        $out = [];
        if (!preg_match_all('#<url>(.*?)</url>#s', $xml, $blocks)) {
            return $out;
        }
        foreach ($blocks[1] as $b) {
            if (!preg_match('#<loc>\s*([^<\s]+)\s*</loc>#', $b, $loc)) {
                continue;
            }
            $url = html_entity_decode($loc[1], ENT_QUOTES | ENT_XML1);
            $h = PropertyService::normalizeHost((string) parse_url($url, PHP_URL_HOST));
            if ($h !== PropertyService::normalizeHost($host)) {
                continue;
            }
            $out[$url] = preg_match('#<lastmod>\s*([^<\s]+)\s*</lastmod>#', $b, $lm) ? $lm[1] : null;
        }

        return $out;
    }

    /**
     * Lee el sitemap de un dominio y avisa de lo nuevo o cambiado. La primera vez solo toma nota.
     *
     * @return array{status:string,notified:int,total:int,baseline:bool}
     */
    public function scanHost(string $host): array
    {
        $host = PropertyService::normalizeHost($host);
        $now = date('Y-m-d H:i:s');
        $xml = ($this->get)('https://' . $host . '/sitemap.xml');
        if ($xml === null || $xml === '') {
            return $this->finish($host, ['status' => 'sin sitemap (no responde)', 'notified' => 0, 'total' => 0, 'baseline' => false], false);
        }
        $urls = self::parseSitemap($xml, $host);
        $hostRow = $this->pdo->prepare('SELECT baselined FROM gsc_indexnow_hosts WHERE host = ?');
        $hostRow->execute([$host]);
        $baselined = (int) $hostRow->fetchColumn() === 1;

        $known = [];
        $stmt = $this->pdo->prepare('SELECT url_hash, lastmod FROM gsc_indexnow_urls WHERE host = ?');
        $stmt->execute([$host]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $known[$r['url_hash']] = $r['lastmod'];
        }
        $changed = [];
        $upsert = $this->pdo->prepare('SELECT 1');
        foreach ($urls as $url => $lastmod) {
            $h = sha1($url);
            if (!array_key_exists($h, $known) || $known[$h] !== $lastmod) {
                $changed[$url] = $lastmod;
            }
        }

        $notified = 0;
        $status = 'sin cambios';
        if (!$baselined) {
            $status = 'primera lectura: ' . count($urls) . ' URL anotadas (no se avisa de lo que ya existía)';
        } elseif ($changed) {
            $cfg = $this->config();
            if (!$cfg['enabled']) {
                $status = count($changed) . ' cambios sin avisar (IndexNow desactivado)';
                $changed = []; // no se anotan: se avisarán al activarlo
            } else {
                $key = $this->ensureKey();
                $ok = true;
                foreach (array_chunk(array_keys($changed), self::CHUNK) as $chunk) {
                    $code = ($this->post)(self::ENDPOINT, ['host' => $host, 'key' => $key, 'keyLocation' => $this->keyUrl($host), 'urlList' => $chunk]);
                    if ($code < 200 || $code >= 300) {
                        $ok = false;
                        $status = "IndexNow respondió HTTP {$code}";
                        break;
                    }
                    $notified += count($chunk);
                }
                if ($ok) {
                    $status = "avisadas {$notified} URL a Bing/Yandex";
                } else {
                    $changed = []; // no se anotan: se reintentan en la próxima lectura
                    $notified = 0;
                }
            }
        }

        // Anotar el estado actual (lo avisado y, en la primera lectura, todo)
        $ins = $this->pdo->prepare('SELECT id FROM gsc_indexnow_urls WHERE host = ? AND url_hash = ?');
        $insert = $this->pdo->prepare('INSERT INTO gsc_indexnow_urls (host, url_hash, url, lastmod, notified_at) VALUES (?, ?, ?, ?, ?)');
        $update = $this->pdo->prepare('UPDATE gsc_indexnow_urls SET lastmod = ?, notified_at = ? WHERE host = ? AND url_hash = ?');
        foreach (($baselined ? $changed : $urls) as $url => $lastmod) {
            $h = sha1($url);
            if (array_key_exists($h, $known)) {
                $update->execute([$lastmod, $notified ? $now : null, $host, $h]);
            } else {
                $insert->execute([$host, $h, $url, $lastmod, $notified ? $now : null]);
            }
        }

        return $this->finish($host, ['status' => $status, 'notified' => $notified, 'total' => count($urls), 'baseline' => !$baselined], true);
    }

    private function finish(string $host, array $r, bool $baseline): array
    {
        $now = date('Y-m-d H:i:s');
        $row = $this->pdo->prepare('SELECT 1 FROM gsc_indexnow_hosts WHERE host = ?');
        $row->execute([$host]);
        $b = $baseline ? 1 : 0;
        if ($row->fetchColumn()) {
            $this->pdo->prepare('UPDATE gsc_indexnow_hosts SET checked_at = ?, baselined = GREATEST(baselined, ?), last_count = ?, last_status = ?' . ($r['notified'] ? ', last_notified_at = ?' : '') . ' WHERE host = ?')
                ->execute(array_merge([$now, $b, $r['notified'], $r['status']], $r['notified'] ? [$now] : [], [$host]));
        } else {
            $this->pdo->prepare('INSERT INTO gsc_indexnow_hosts (host, checked_at, baselined, last_notified_at, last_count, last_status) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$host, $now, $b, $r['notified'] ? $now : null, $r['notified'], $r['status']]);
        }

        return $r;
    }

    /** Hosts que toca leer (no leídos en la última hora). */
    public function dueHosts(int $minutes = 60): array
    {
        $cut = date('Y-m-d H:i:s', time() - $minutes * 60);
        $seen = [];
        foreach ($this->pdo->query('SELECT host, checked_at FROM gsc_indexnow_hosts') as $r) {
            $seen[$r['host']] = $r['checked_at'];
        }

        return array_values(array_filter($this->ownHosts(), fn ($h) => empty($seen[$h]) || $seen[$h] < $cut));
    }

    /** Estado para la pantalla. */
    public function listing(): array
    {
        $rows = [];
        foreach ($this->pdo->query('SELECT * FROM gsc_indexnow_hosts') as $r) {
            $rows[$r['host']] = $r;
        }
        $out = [];
        foreach ($this->ownHosts() as $h) {
            $out[] = ($rows[$h] ?? []) + ['host' => $h, 'checked_at' => null, 'last_status' => null, 'last_count' => 0, 'last_notified_at' => null, 'baselined' => 0];
        }

        return $out;
    }

    // ---- red real ----

    public static function fetch(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_MAXFILESIZE => 8 * 1024 * 1024]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($body !== false && $code === 200) ? (string) $body : null;
    }

    public static function postJson(string $url, array $data): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 20, CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8']]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code;
    }
}
