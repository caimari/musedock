<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * Registro ÚNICO de cuentas DNS del CMS (Cloudflare y otros proveedores).
 *
 * Es la fuente de verdad: plugins y módulos (Domain Manager, Cloud, Search Console…) leen
 * las credenciales de aquí y no guardan copias. Cualquiera puede añadir o sobrescribir una
 * cuenta desde Ajustes → Cuentas DNS y todos los consumidores la usan a la vez.
 *
 * Reglas:
 *  - Las credenciales se guardan SIEMPRE cifradas (AES-256-GCM, autenticado), una sola cadena
 *    por cuenta con todos sus campos. Los listados nunca devuelven secretos: solo versiones
 *    enmascaradas. Los secretos se piden de forma explícita con credentials().
 *  - Lee también el formato antiguo (AES-256-CBC de Domain Manager) y lo reescribe en GCM al guardar.
 *  - Cualquier proveedor cabe en la misma tabla: el catálogo PROVIDERS define sus campos.
 *  - Tabla: dns_provider_accounts (la misma que ya creaba Domain Manager).
 */
class DnsAccounts
{
    public const TABLE = 'dns_provider_accounts';
    private const PREFIX = 'dnsa1:';
    private const LEGACY_CIPHER = 'aes-256-cbc';

    /**
     * Catálogo de proveedores. fields: nombre => [etiqueta, secreto (se enmascara), obligatorio].
     * 'managed_dns': el CMS puede crear registros por su API. 'dns01': sirve para certificados DNS-01.
     */
    public const PROVIDERS = [
        'cloudflare' => ['label' => 'Cloudflare', 'managed_dns' => true, 'dns01' => true, 'fields' => [
            'api_token' => ['Token de API', true, true]]],
        'digitalocean' => ['label' => 'DigitalOcean DNS', 'managed_dns' => true, 'dns01' => true, 'fields' => [
            'api_token' => ['Token de API', true, true]]],
        'hetzner' => ['label' => 'Hetzner DNS', 'managed_dns' => true, 'dns01' => true, 'fields' => [
            'api_token' => ['Token de API', true, true]]],
        'vultr' => ['label' => 'Vultr DNS', 'managed_dns' => true, 'dns01' => true, 'fields' => [
            'api_token' => ['Token de API', true, true]]],
        'linode' => ['label' => 'Linode DNS', 'managed_dns' => true, 'dns01' => true, 'fields' => [
            'api_token' => ['Token de API', true, true]]],
        'gandi' => ['label' => 'Gandi', 'managed_dns' => false, 'dns01' => true, 'fields' => [
            'api_token' => ['Token de API', true, true]]],
        'porkbun' => ['label' => 'Porkbun', 'managed_dns' => true, 'dns01' => true, 'fields' => [
            'api_key' => ['API key', true, true], 'secret_api_key' => ['Secret API key', true, true]]],
        'powerdns' => ['label' => 'PowerDNS', 'managed_dns' => false, 'dns01' => true, 'fields' => [
            'api_url' => ['URL de la API', false, true], 'api_key' => ['API key', true, true], 'server_id' => ['ID del servidor', false, false]]],
        'route53' => ['label' => 'Amazon Route53', 'managed_dns' => false, 'dns01' => true, 'fields' => [
            'aws_access_key_id' => ['Access key ID', true, true], 'aws_secret_access_key' => ['Secret access key', true, true], 'aws_region' => ['Región', false, false]]],
        'ovh' => ['label' => 'OVH', 'managed_dns' => false, 'dns01' => true, 'fields' => [
            'endpoint' => ['Endpoint', false, true], 'application_key' => ['Application key', true, true],
            'application_secret' => ['Application secret', true, true], 'consumer_key' => ['Consumer key', true, true]]],
        'namecheap' => ['label' => 'Namecheap', 'managed_dns' => false, 'dns01' => true, 'fields' => [
            'api_user' => ['Usuario de API', false, true], 'api_key' => ['API key', true, true],
            'username' => ['Usuario', false, true], 'client_ip' => ['IP autorizada', false, true]]],
        'rfc2136' => ['label' => 'RFC2136 (BIND)', 'managed_dns' => false, 'dns01' => true, 'fields' => [
            'server' => ['Servidor', false, true], 'key_name' => ['Nombre de la clave', false, true],
            'key_secret' => ['Secreto de la clave', true, true], 'key_algorithm' => ['Algoritmo', false, false]]],
    ];

    /** Conexiones en las que ya se comprobó la tabla (se libera con la conexión). */
    private static ?\WeakMap $ready = null;

    private string $legacyKey;
    private string $gcmKey;

    /** @param string|null $keyMaterial Solo para pruebas: sustituye a APP_ENCRYPTION_KEY / APP_KEY */
    public function __construct(private \PDO $pdo, ?string $keyMaterial = null)
    {
        $this->legacyKey = self::deriveLegacyKey($keyMaterial);
        $this->gcmKey = hash('sha256', 'dns-accounts-gcm|' . $this->legacyKey, true);
    }

    public static function make(): self
    {
        return new self(Database::connect());
    }

    /** Misma derivación que usaban Domain Manager y Cloudflare (para poder leer lo ya guardado). */
    private static function deriveLegacyKey(?string $override): string
    {
        $key = $override ?? self::env('APP_ENCRYPTION_KEY');
        if ($key !== '') {
            return str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7)) : hash('sha256', $key, true);
        }
        $appKey = self::env('APP_KEY');
        if ($appKey === '') {
            throw new \RuntimeException('Falta APP_ENCRYPTION_KEY o APP_KEY para cifrar las cuentas DNS.');
        }

        return hash('sha256', $appKey, true);
    }

    private static function env(string $name): string
    {
        $v = getenv($name);
        if (($v === false || $v === '') && class_exists(\Screenart\Musedock\Env::class)) {
            $v = \Screenart\Musedock\Env::get($name);
        }

        return (string) ($v ?: '');
    }

    // ------------------------------------------------------------------ esquema

    public function ensureTable(): void
    {
        self::$ready ??= new \WeakMap();
        if (isset(self::$ready[$this->pdo])) {
            return;
        }
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $pk = $driver === 'pgsql' ? 'SERIAL PRIMARY KEY' : ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY');
        $small = $driver === 'pgsql' ? 'SMALLINT' : ($driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)');
        $ts = $driver === 'mysql' ? 'DATETIME' : 'TIMESTAMP';
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . " (
            id $pk,
            provider VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            credentials TEXT NOT NULL,
            account_ref VARCHAR(255) NULL,
            is_default $small NOT NULL DEFAULT 0,
            enabled $small NOT NULL DEFAULT 1,
            last_test_status VARCHAR(20) NULL,
            last_test_message TEXT NULL,
            last_test_at $ts NULL,
            metadata TEXT NULL,
            created_at $ts NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at $ts NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        self::$ready[$this->pdo] = true;
    }

    // ------------------------------------------------------------------ cifrado

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->gcmKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('No se pudo cifrar la credencial.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** Descifra el formato nuevo (GCM) y el antiguo (CBC de Domain Manager). */
    public function decrypt(string $payload): string
    {
        if (str_starts_with($payload, self::PREFIX)) {
            $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
            if ($raw === false || strlen($raw) < 29) {
                throw new \RuntimeException('Credencial cifrada no válida.');
            }
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->gcmKey, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            if ($plain === false) {
                throw new \RuntimeException('No se pudo descifrar la credencial (¿cambió APP_KEY?).');
            }

            return $plain;
        }

        $data = base64_decode($payload, true);
        $ivLen = openssl_cipher_iv_length(self::LEGACY_CIPHER);
        if ($data === false || strlen($data) <= $ivLen) {
            throw new \RuntimeException('Credencial cifrada no válida.');
        }
        $plain = openssl_decrypt(substr($data, $ivLen), self::LEGACY_CIPHER, $this->legacyKey, OPENSSL_RAW_DATA, substr($data, 0, $ivLen));
        if ($plain === false) {
            throw new \RuntimeException('No se pudo descifrar la credencial (¿cambió APP_KEY?).');
        }

        return $plain;
    }

    // ------------------------------------------------------------------ lectura

    /** Cuentas SIN secretos (solo enmascarados). */
    public function all(?string $provider = null, bool $onlyEnabled = false): array
    {
        $this->ensureTable();
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE 1=1';
        $args = [];
        if ($provider !== null) {
            $sql .= ' AND provider = ?';
            $args[] = $provider;
        }
        if ($onlyEnabled) {
            $sql .= ' AND enabled = 1';
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY provider, is_default DESC, id');
        $stmt->execute($args);

        return array_map([$this, 'present'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->present($row) : null;
    }

    public function default(string $provider): ?array
    {
        $list = $this->all($provider, true);
        foreach ($list as $a) {
            if ($a['is_default']) {
                return $a;
            }
        }

        return $list[0] ?? null;
    }

    /** Secretos de una cuenta, descifrados. Llamada explícita: no se mezclan con los listados. */
    public function credentials(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT credentials FROM ' . self::TABLE . ' WHERE id = ?');
        $stmt->execute([$id]);
        $blob = $stmt->fetchColumn();
        if ($blob === false || $blob === '') {
            return [];
        }
        $data = json_decode($this->decrypt((string) $blob), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Cuentas de Cloudflare con la forma que esperaban los servicios antiguos
     * (id, name, api_token_decrypted, account_id, role, is_default_for_new_domains, ssl_mode, enabled).
     */
    public function cloudflareAccounts(bool $onlyEnabled = true): array
    {
        $out = [];
        foreach ($this->all('cloudflare', $onlyEnabled) as $a) {
            try {
                $creds = $this->credentials($a['id']);
            } catch (\Throwable $e) {
                continue; // cuenta con credencial ilegible: no se ofrece a los consumidores
            }
            $out[] = [
                'id' => $a['id'],
                'name' => $a['name'],
                'api_token' => '',
                'api_token_decrypted' => (string) ($creds['api_token'] ?? ''),
                'account_id' => (string) $a['account_ref'],
                'role' => $a['metadata']['role'] ?? 'primary',
                'is_default_for_new_domains' => (int) $a['is_default'],
                'ssl_mode' => $a['metadata']['ssl_mode'] ?? 'full',
                'enabled' => (int) $a['enabled'],
                'last_test_status' => $a['last_test_status'],
                'last_test_message' => $a['last_test_message'],
                'last_test_at' => $a['last_test_at'],
            ];
        }

        return $out;
    }

    /** Primer token activo de una cuenta Cloudflare por rol (primary|domains), o ''. Para código antiguo que leía del .env. */
    public function cloudflareToken(string $role = 'primary'): string
    {
        foreach ($this->cloudflareAccounts() as $a) {
            if ($a['role'] === $role && $a['api_token_decrypted'] !== '') {
                return $a['api_token_decrypted'];
            }
        }

        return '';
    }

    private function present(array $row): array
    {
        $masked = [];
        try {
            foreach ($this->credentials((int) $row['id']) as $k => $v) {
                $masked[$k] = self::mask($row['provider'], (string) $k, (string) $v);
            }
            $readable = true;
        } catch (\Throwable $e) {
            $readable = false;
        }

        return [
            'id' => (int) $row['id'],
            'provider' => $row['provider'],
            'provider_label' => self::PROVIDERS[$row['provider']]['label'] ?? $row['provider'],
            'name' => $row['name'],
            'account_ref' => $row['account_ref'],
            'is_default' => (int) $row['is_default'],
            'enabled' => (int) $row['enabled'],
            'last_test_status' => $row['last_test_status'],
            'last_test_message' => $row['last_test_message'],
            'last_test_at' => $row['last_test_at'],
            'metadata' => json_decode((string) ($row['metadata'] ?? ''), true) ?: [],
            'credentials_masked' => $masked,
            'readable' => $readable,
        ];
    }

    private static function mask(string $provider, string $field, string $value): string
    {
        $secret = self::PROVIDERS[$provider]['fields'][$field][1] ?? true;
        if ($value === '') {
            return '';
        }
        if (!$secret) {
            return $value;
        }

        return strlen($value) <= 8 ? '••••' : substr($value, 0, 4) . '••••••' . substr($value, -4);
    }

    // ------------------------------------------------------------------ escritura

    /**
     * Crea ($id = null) o actualiza una cuenta. En una actualización los campos secretos vacíos
     * conservan el valor guardado (el formulario nunca muestra los secretos).
     */
    public function save(?int $id, array $data): int
    {
        $this->ensureTable();
        $existing = null;
        if ($id !== null) {
            $existing = $this->find($id);
            if (!$existing) {
                throw new \InvalidArgumentException('Cuenta DNS no encontrada.');
            }
        }
        $provider = $existing['provider'] ?? strtolower(trim((string) ($data['provider'] ?? '')));
        if (!isset(self::PROVIDERS[$provider])) {
            throw new \InvalidArgumentException('Proveedor DNS no soportado.');
        }
        $name = trim((string) ($data['name'] ?? ($existing['name'] ?? '')));
        if ($name === '') {
            throw new \InvalidArgumentException('La cuenta necesita un nombre.');
        }

        $creds = $existing ? $this->credentials($id) : [];
        foreach (array_keys(self::PROVIDERS[$provider]['fields']) as $field) {
            $v = trim((string) ($data['credentials'][$field] ?? ''));
            if ($v !== '') {
                $creds[$field] = $v;
            }
        }
        foreach (self::PROVIDERS[$provider]['fields'] as $field => [, , $required]) {
            if ($required && trim((string) ($creds[$field] ?? '')) === '') {
                throw new \InvalidArgumentException('Falta el campo obligatorio: ' . self::PROVIDERS[$provider]['fields'][$field][0] . '.');
            }
        }

        $metadata = array_merge($existing['metadata'] ?? [], (array) ($data['metadata'] ?? []));
        $ref = array_key_exists('account_ref', $data) ? (trim((string) $data['account_ref']) ?: null) : ($existing['account_ref'] ?? null);
        $enabled = array_key_exists('enabled', $data) ? (int) !empty($data['enabled']) : (int) ($existing['enabled'] ?? 1);
        $default = array_key_exists('is_default', $data) ? (int) !empty($data['is_default']) : (int) ($existing['is_default'] ?? 0);
        $blob = $this->encrypt(json_encode($creds, JSON_UNESCAPED_SLASHES));
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET name=?, credentials=?, account_ref=?, enabled=?, metadata=?, updated_at=? WHERE id=?')
                ->execute([$name, $blob, $ref, $enabled, json_encode($metadata, JSON_UNESCAPED_UNICODE), $now, $id]);
        } else {
            $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (provider, name, credentials, account_ref, is_default, enabled, metadata, created_at, updated_at) VALUES (?,?,?,?,0,?,?,?,?)')
                ->execute([$provider, $name, $blob, $ref, $enabled, json_encode($metadata, JSON_UNESCAPED_UNICODE), $now, $now]);
            $id = (int) $this->pdo->lastInsertId();
        }
        if ($default) {
            $this->setDefault($id);
        }

        return $id;
    }

    public function setDefault(int $id): void
    {
        $a = $this->find($id);
        if (!$a) {
            throw new \InvalidArgumentException('Cuenta DNS no encontrada.');
        }
        $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET is_default = 0 WHERE provider = ?')->execute([$a['provider']]);
        $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET is_default = 1 WHERE id = ?')->execute([$id]);
    }

    public function delete(int $id): void
    {
        $this->ensureTable();
        $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = ?')->execute([$id]);
    }

    public function recordTest(int $id, string $status, string $message): void
    {
        $this->pdo->prepare('UPDATE ' . self::TABLE . ' SET last_test_status=?, last_test_message=?, last_test_at=? WHERE id=?')
            ->execute([substr($status, 0, 20), mb_substr($message, 0, 500), date('Y-m-d H:i:s'), $id]);
    }

    // ------------------------------------------------------------------ importación de lo antiguo

    /**
     * Copia al registro lo que había en otros sitios (idempotente, no borra nada del origen):
     *  1. tabla antigua cloudflare_accounts de Domain Manager,
     *  2. tokens de .env (CLOUDFLARE_API_TOKEN, CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN) que aún no estén.
     * Si se pasa $isValid(token): ?bool, los tokens que devuelvan false (Cloudflare los rechaza) NO se
     * importan y se listan en 'invalid'; null/true se importan.
     * @return array{legacy:int, env:int, skipped:int, invalid:string[]}
     */
    public function importLegacy(?callable $isValid = null): array
    {
        $this->ensureTable();
        $res = ['legacy' => 0, 'env' => 0, 'skipped' => 0, 'invalid' => []];
        $known = [];
        foreach ($this->cloudflareAccounts(false) as $a) {
            $known[$a['api_token_decrypted']] = true;
        }

        $legacyRows = [];
        try {
            $legacyRows = $this->pdo->query('SELECT * FROM cloudflare_accounts ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // sin tabla antigua: nada que importar
        }
        foreach ($legacyRows as $r) {
            try {
                $token = $this->decrypt((string) $r['api_token']);
            } catch (\Throwable $e) {
                $res['skipped']++;
                continue;
            }
            if ($token === '' || isset($known[$token])) {
                $res['skipped']++;
                continue;
            }
            if ($isValid && $isValid($token) === false) {
                $res['invalid'][] = (string) $r['name'];
                continue;
            }
            $this->save(null, [
                'provider' => 'cloudflare', 'name' => (string) $r['name'], 'account_ref' => (string) $r['account_id'],
                'enabled' => (int) $r['enabled'], 'is_default' => (int) ($r['is_default_for_new_domains'] ?? 0),
                'credentials' => ['api_token' => $token],
                'metadata' => ['role' => $r['role'] ?: 'primary', 'ssl_mode' => $r['ssl_mode'] ?: 'full', 'source' => 'domain-manager', 'legacy_id' => (int) $r['id']],
            ]);
            $known[$token] = true;
            $res['legacy']++;
        }

        foreach ([
            ['Primary (.env)', 'CLOUDFLARE_API_TOKEN', 'CLOUDFLARE_ACCOUNT_ID', 'primary', 0],
            ['Custom Domains (.env)', 'CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN', 'CLOUDFLARE_CUSTOM_DOMAINS_ACCOUNT_ID', 'domains', 0],
        ] as [$label, $tokenVar, $accVar, $role, $def]) {
            $token = self::env($tokenVar);
            if ($token === '' || isset($known[$token])) {
                continue;
            }
            if ($isValid && $isValid($token) === false) {
                $res['invalid'][] = $label;
                continue;
            }
            $this->save(null, [
                'provider' => 'cloudflare', 'name' => $label, 'account_ref' => self::env($accVar), 'is_default' => $def,
                'credentials' => ['api_token' => $token], 'metadata' => ['role' => $role, 'ssl_mode' => 'full', 'source' => 'env'],
            ]);
            $known[$token] = true;
            $res['env']++;
        }

        return $res;
    }
}
