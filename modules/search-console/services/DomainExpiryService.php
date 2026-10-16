<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Caducidad de los dominios propios (tenants propios y sus alias). Consulta RDAP (público, sin clave) y, solo
 * desde la línea de comandos, whois como alternativa para los TLD sin RDAP. Avisa a los superadmin a los 60,
 * 30, 15 y 7 días y cuando el dominio ya está caducado o en periodo de redención.
 *
 * Privacidad: solo se vigilan dominios de tenants PROPIOS; los de clientes no se consultan ni se muestran.
 */
class DomainExpiryService
{
    /** Escalones de aviso en días; el nivel 1 es «ya caducado / en redención». */
    public const LEVELS = [60, 30, 15, 7, 1];
    /** Segundo nivel de dominio con tres etiquetas (co.uk, com.au…). */
    private const SLD = ['co', 'com', 'org', 'net', 'gov', 'ac', 'edu'];

    /** @var callable(string):?string  GET → cuerpo de la respuesta (inyectable para pruebas) */
    private $http;

    /** Código HTTP de la última consulta real (null con un http inyectado). */
    public static ?int $lastCode = null;

    public function __construct(private PDO $pdo, ?callable $http = null)
    {
        $this->http = $http ?? [self::class, 'fetch'];
    }

    /** Dominio registrable: sub.alexapost.musedock.com → musedock.com; x.co.uk → x.co.uk */
    public static function registrable(string $host): string
    {
        $labels = explode('.', PropertyService::normalizeHost($host));
        $n = count($labels);
        if ($n <= 2) {
            return implode('.', $labels);
        }
        $take = (strlen($labels[$n - 1]) === 2 && in_array($labels[$n - 2], self::SLD, true)) ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }

    /** Dominios registrables de tenants propios (y sus alias) → nombres de tenant que los usan. */
    public function ownDomains(): array
    {
        $out = [];
        $add = function (string $host, string $tenant) use (&$out) {
            $d = self::registrable($host);
            if ($d !== '' && str_contains($d, '.')) {
                $out[$d][$tenant] = true;
            }
        };
        foreach ($this->pdo->query('SELECT id, name, domain FROM tenants WHERE is_client_domain = 0') as $t) {
            $add((string) $t['domain'], (string) $t['name']);
        }
        try {
            foreach ($this->pdo->query('SELECT a.domain, t.name FROM domain_aliases a JOIN tenants t ON t.id = a.tenant_id WHERE t.is_client_domain = 0') as $a) {
                $add((string) $a['domain'], (string) $a['name']);
            }
        } catch (\Throwable $e) {
            // alias opcional
        }
        $main = PropertyService::mainDomain();
        if ($main !== '') {
            $add($main, 'Sitio principal');
        }
        ksort($out);

        return array_map(fn ($t) => array_keys($t), $out);
    }

    /** Consulta la caducidad de un dominio y la guarda. */
    public function check(string $domain): array
    {
        $domain = PropertyService::normalizeHost($domain);
        self::$lastCode = null;
        $res = $this->fromRdap($domain);
        // Para .com/.net el registro responde 404 solo si el dominio NO existe (en otros TLD 404 = sin RDAP)
        if ($res === null && self::$lastCode === 404 && preg_match('/\.(com|net)$/', $domain)) {
            $res = ['expires_at' => null, 'status' => 'not_registered', 'registrar' => null, 'note' => 'El registro dice que el dominio NO está registrado: ya se perdió (o nunca fue tuyo).'];
        }
        if ($res === null && PHP_SAPI === 'cli') {
            $res = $this->fromWhois($domain);
        }
        $res ??= ['expires_at' => null, 'status' => 'unknown', 'registrar' => null, 'note' => 'Sin datos de caducidad (el registro de este TLD no publica RDAP).'];
        $this->store($domain, $res);

        return $res;
    }

    public function fromRdap(string $domain): ?array
    {
        $body = ($this->http)('https://rdap.org/domain/' . rawurlencode($domain)) ?: ($this->http)('https://rdap.org/domain/' . rawurlencode($domain));
        $json = $body ? json_decode($body, true) : null;

        return is_array($json) ? self::parseRdap($json) : null;
    }

    public static function parseRdap(array $j): ?array
    {
        $expires = null;
        foreach ($j['events'] ?? [] as $ev) {
            if (in_array($ev['eventAction'] ?? '', ['expiration', 'registrar expiration'], true) && !empty($ev['eventDate'])) {
                $expires = substr((string) $ev['eventDate'], 0, 10);
                break;
            }
        }
        $statuses = array_map('strtolower', array_map('strval', $j['status'] ?? []));
        if ($expires === null && !$statuses) {
            return null;
        }
        $registrar = null;
        foreach ($j['entities'] ?? [] as $ent) {
            if (in_array('registrar', $ent['roles'] ?? [], true)) {
                foreach ($ent['vcardArray'][1] ?? [] as $prop) {
                    if (($prop[0] ?? '') === 'fn') {
                        $registrar = (string) ($prop[3] ?? '');
                    }
                }
            }
        }

        return ['expires_at' => $expires, 'status' => self::classify($expires, $statuses), 'registrar' => $registrar, 'note' => null];
    }

    public static function classify(?string $expires, array $statuses): string
    {
        $joined = ' ' . implode(' ', $statuses) . ' ';
        if (str_contains($joined, 'pending delete')) {
            return 'pending_delete';
        }
        if (str_contains($joined, 'redemption')) {
            return 'redemption';
        }
        if ($expires !== null && $expires < date('Y-m-d')) {
            return 'expired';
        }

        return $expires === null ? 'unknown' : 'ok';
    }

    /** whois solo en CLI (en PHP-FPM exec está desactivado). */
    private function fromWhois(string $domain): ?array
    {
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return null;
        }
        $out = [];
        @exec('timeout 20 whois ' . escapeshellarg($domain) . ' 2>/dev/null', $out);
        $text = implode("\n", $out);
        if (preg_match('/^\s*(?:no match for|domain not found|not found|no data found|no entries found)|status:\s*free|domain is free/im', $text)) {
            return ['expires_at' => null, 'status' => 'not_registered', 'registrar' => null, 'note' => 'El registro dice que el dominio NO está registrado: ya se perdió (o nunca fue tuyo).'];
        }
        if (!preg_match('/(?:Registry Expiry Date|Expiration Date|Expiry date|paid-till|Renewal date|expire)[^:\n]*:\s*([0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}\/[0-9]{2}\/[0-9]{4})/i', $text, $m)) {
            return null;
        }
        $d = $m[1];
        if (str_contains($d, '/')) {
            [$dd, $mm, $yy] = explode('/', $d);
            $d = "$yy-$mm-$dd";
        }
        $status = [];
        if (stripos($text, 'redemptionPeriod') !== false || stripos($text, 'redemption period') !== false) {
            $status[] = 'redemption period';
        }
        if (stripos($text, 'pendingDelete') !== false) {
            $status[] = 'pending delete';
        }

        return ['expires_at' => $d, 'status' => self::classify($d, $status), 'registrar' => null, 'note' => 'Obtenido por whois.'];
    }

    private function store(string $domain, array $r): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('SELECT id, last_alert_level FROM gsc_domain_expiry WHERE domain = ?');
        $stmt->execute([$domain]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Si ya no está en riesgo (renovado), los avisos vuelven a empezar
            $reset = self::levelFor($r['expires_at'], $r['status']) === 0 ? ', last_alert_level = 0' : '';
            $this->pdo->prepare("UPDATE gsc_domain_expiry SET expires_at = ?, status = ?, registrar = ?, note = ?, checked_at = ?{$reset} WHERE id = ?")
                ->execute([$r['expires_at'], $r['status'], $r['registrar'], $r['note'], $now, $row['id']]);
        } else {
            $this->pdo->prepare('INSERT INTO gsc_domain_expiry (domain, expires_at, status, registrar, note, checked_at, last_alert_level) VALUES (?, ?, ?, ?, ?, ?, 0)')
                ->execute([$domain, $r['expires_at'], $r['status'], $r['registrar'], $r['note'], $now]);
        }
    }

    /** Nivel de aviso (60, 30, 15, 7, 1) o 0 si no hay riesgo. */
    public static function levelFor(?string $expires, string $status): int
    {
        if (in_array($status, ['redemption', 'pending_delete', 'expired', 'not_registered'], true)) {
            return 1;
        }
        if ($expires === null) {
            return 0;
        }
        $days = (int) floor((strtotime($expires) - strtotime(date('Y-m-d'))) / 86400);
        foreach ([7, 15, 30, 60] as $l) {
            if ($days <= $l) {
                return $l;
            }
        }

        return 0;
    }

    public static function daysLeft(?string $expires): ?int
    {
        return $expires === null ? null : (int) floor((strtotime($expires) - strtotime(date('Y-m-d'))) / 86400);
    }

    /** Lista guardada, solo de dominios propios actuales (un dominio que ya no es de un tenant propio no se muestra). */
    public function listing(): array
    {
        $own = $this->ownDomains();
        $rows = [];
        foreach ($this->pdo->query('SELECT * FROM gsc_domain_expiry') as $r) {
            if (isset($own[$r['domain']])) {
                $r['tenants'] = $own[$r['domain']];
                $r['days'] = self::daysLeft($r['expires_at']);
                $r['level'] = self::levelFor($r['expires_at'], $r['status']);
                $rows[$r['domain']] = $r;
            }
        }
        foreach ($own as $d => $tenants) {
            $rows[$d] ??= ['domain' => $d, 'tenants' => $tenants, 'expires_at' => null, 'status' => 'pending', 'registrar' => null, 'note' => null, 'checked_at' => null, 'days' => null, 'level' => 0];
        }
        uasort($rows, fn ($a, $b) => [$a['expires_at'] === null, $a['expires_at']] <=> [$b['expires_at'] === null, $b['expires_at']]);

        return array_values($rows);
    }

    /** Dominios que hay que avisar ahora (el nivel ha empeorado desde el último aviso). */
    public function dueAlerts(): array
    {
        $due = [];
        foreach ($this->listing() as $r) {
            $last = (int) ($r['last_alert_level'] ?? 0);
            if ($r['level'] > 0 && ($last === 0 || $r['level'] < $last)) {
                $due[] = $r;
            }
        }

        return $due;
    }

    public function markAlerted(string $domain, int $level): void
    {
        $this->pdo->prepare('UPDATE gsc_domain_expiry SET last_alert_level = ? WHERE domain = ?')->execute([$level, $domain]);
    }

    /** Dominios que toca consultar (no consultados en las últimas $hours horas). */
    public function dueChecks(int $hours = 20): array
    {
        $cut = date('Y-m-d H:i:s', time() - $hours * 3600);
        $out = [];
        foreach ($this->listing() as $r) {
            if (empty($r['checked_at']) || $r['checked_at'] < $cut) {
                $out[] = $r['domain'];
            }
        }

        return $out;
    }

    public static function fetch(string $url): ?string
    {
        self::$lastCode = null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'], CURLOPT_USERAGENT => 'MuseDock-CMS-DomainExpiry/1.0']);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        self::$lastCode = $code;
        curl_close($ch);

        return ($body !== false && $code === 200) ? (string) $body : null;
    }
}
