<?php

namespace Screenart\Musedock\Services\Dns;

/**
 * Cliente mínimo de la API de Cloudflare (v4) para el core: verificar token, buscar zona y
 * publicar TXT. El token nunca se escribe en logs ni mensajes de error.
 */
class CloudflareClient implements DnsZoneClient
{
    private const BASE = 'https://api.cloudflare.com/client/v4';
    /** @var callable fn(string $method, string $url, array $headers, ?string $body): array{status:int, body:string} */
    private $transport;

    public function __construct(private string $token, private ?string $accountId = null, ?callable $transport = null)
    {
        $this->transport = $transport ?? [self::class, 'curl'];
    }

    public function verify(): array
    {
        try {
            $r = $this->call('GET', '/user/tokens/verify');
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $status = $r['result']['status'] ?? '';

        return $status === 'active'
            ? ['ok' => true, 'message' => 'Token activo.']
            : ['ok' => false, 'message' => 'El token no está activo (' . ($status ?: 'desconocido') . ').'];
    }

    public function findZone(string $domain): ?array
    {
        $q = ['name' => $domain, 'per_page' => 1];
        if ($this->accountId) {
            $q['account.id'] = $this->accountId;
        }
        $r = $this->call('GET', '/zones?' . http_build_query($q));
        $z = $r['result'][0] ?? null;

        return $z ? ['id' => (string) $z['id'], 'name' => (string) $z['name']] : null;
    }

    /** Nº de zonas que ve el token (para mostrarlo en el panel). */
    public function zoneCount(): ?int
    {
        $r = $this->call('GET', '/zones?per_page=1');

        return isset($r['result_info']['total_count']) ? (int) $r['result_info']['total_count'] : null;
    }

    public function ensureTxt(string $zoneId, string $name, string $content): void
    {
        $r = $this->call('GET', "/zones/{$zoneId}/dns_records?" . http_build_query(['type' => 'TXT', 'name' => $name, 'per_page' => 100]));
        foreach ($r['result'] ?? [] as $rec) {
            if (trim((string) ($rec['content'] ?? ''), '"') === $content) {
                return;
            }
        }
        $this->call('POST', "/zones/{$zoneId}/dns_records", ['type' => 'TXT', 'name' => $name, 'content' => $content, 'ttl' => 1]);
    }

    private function call(string $method, string $path, ?array $json = null): array
    {
        $headers = ['Authorization: Bearer ' . $this->token, 'Content-Type: application/json', 'Accept: application/json'];
        $r = ($this->transport)($method, self::BASE . $path, $headers, $json === null ? null : json_encode($json));
        $data = json_decode((string) $r['body'], true);
        if ($r['status'] < 200 || $r['status'] >= 300 || !is_array($data) || ($data['success'] ?? true) === false) {
            $msg = is_array($data) ? ($data['errors'][0]['message'] ?? 'error') : 'respuesta no válida';
            throw new \RuntimeException("Cloudflare respondió {$r['status']}: {$msg}");
        }

        return $data;
    }

    public static function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) {
            throw new \RuntimeException('Error de red al llamar a Cloudflare: ' . $err);
        }

        return ['status' => $status, 'body' => (string) $out];
    }
}
