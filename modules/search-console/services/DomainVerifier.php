<?php

namespace Modules\SearchConsole\Services;

use RuntimeException;

/**
 * Alta automática de un dominio en Search Console con la cuenta de servicio:
 *
 *  1. Site Verification API: pide el token DNS_TXT para el dominio.
 *  2. Cloudflare: publica el TXT en la zona del dominio (si no estaba ya).
 *  3. Site Verification API: confirma la verificación (la cuenta de servicio queda
 *     como propietaria verificada; los propietarios que ya hubiera no cambian).
 *  4. Search Console API: añade la propiedad de dominio sc-domain:<dominio>.
 *
 * Si el DNS aún no ha propagado, devuelve 'pending' para reintentar más tarde.
 */
class DomainVerifier
{
    private const VERIFY_API = 'https://www.googleapis.com/siteVerification/v1';

    public function __construct(private GoogleClient $client)
    {
    }

    /** @return array{status:string, message:string} status: verified|pending|manual|error */
    public function verify(string $domain): array
    {
        $domain = PropertyService::normalizeHost($domain);
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain)) {
            return ['status' => 'error', 'message' => 'Dominio no válido.'];
        }

        try {
            $token = $this->client->request('POST', self::VERIFY_API . '/token', [
                'site' => ['type' => 'INET_DOMAIN', 'identifier' => $domain],
                'verificationMethod' => 'DNS_TXT',
            ])['token'] ?? '';
            if ($token === '') {
                return ['status' => 'error', 'message' => 'Google no devolvió el token de verificación.'];
            }

            $zone = $this->findCloudflareZone($domain);
            if ($zone === null) {
                return ['status' => 'manual', 'message' => "El DNS de {$domain} no está en las cuentas de Cloudflare del panel. Añade a mano en Search Console el email de la cuenta de servicio como usuario, o publica este TXT en el dominio: {$token}"];
            }
            $this->ensureTxt($zone, $domain, $token);

            try {
                $this->client->request('POST', self::VERIFY_API . '/webResource?verificationMethod=DNS_TXT', [
                    'site' => ['type' => 'INET_DOMAIN', 'identifier' => $domain],
                ]);
            } catch (GoogleApiException $e) {
                if ($e->status === 400) {
                    return ['status' => 'pending', 'message' => 'TXT publicado en Cloudflare; Google aún no lo ve (propagación DNS). Se reintentará.'];
                }
                throw $e;
            }

            $this->client->request('PUT', 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode('sc-domain:' . $domain));

            return ['status' => 'verified', 'message' => "{$domain} verificado y añadido a Search Console."];
        } catch (GoogleApiException $e) {
            if ($e->status === 403) {
                return ['status' => 'error', 'message' => 'Google rechazó la petición: activa «Site Verification API» y «Google Search Console API» en el proyecto de Google Cloud. (' . $e->getMessage() . ')'];
            }

            return ['status' => 'error', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /**
     * Busca la zona del dominio (o de un dominio padre, para subdominios) en todas
     * las cuentas de Cloudflare del panel. Devuelve [service, zoneId] o null.
     */
    private function findCloudflareZone(string $domain): ?array
    {
        if (!self::loadCloudflare()) {
            return null;
        }
        $base = new \CaddyDomainManager\Services\CloudflareZoneService();
        $labels = explode('.', $domain);
        $candidates = [];
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidates[] = implode('.', array_slice($labels, $i));
        }

        foreach ($base->getAllAccounts() as $account) {
            $service = \CaddyDomainManager\Services\CloudflareZoneService::forAccount($account);
            foreach ($candidates as $candidate) {
                try {
                    $zone = $service->getZoneByDomain($candidate);
                } catch (\Throwable $e) {
                    $zone = null;
                }
                $zoneId = $zone['zone_id'] ?? ($zone['id'] ?? null);
                if ($zoneId) {
                    return [$service, (string) $zoneId];
                }
            }
        }

        return null;
    }

    private function ensureTxt(array $zone, string $domain, string $token): void
    {
        [$service, $zoneId] = $zone;
        foreach ($service->getDNSRecords($zoneId, 'TXT') as $record) {
            if (($record['name'] ?? '') === $domain && trim((string) ($record['content'] ?? ''), '"') === $token) {
                return;
            }
        }
        $service->createDNSRecord($zoneId, 'TXT', $domain, $token, false, 1);
    }

    private static function loadCloudflare(): bool
    {
        if (class_exists(\CaddyDomainManager\Services\CloudflareZoneService::class)) {
            return true;
        }
        $root = defined('PRIVATE_PLUGINS_PATH') ? PRIVATE_PLUGINS_PATH
            : (\Screenart\Musedock\Env::get('PRIVATE_PLUGINS_PATH') ?: dirname(APP_ROOT) . '/private-plugins');
        $dir = rtrim((string) $root, '/') . '/superadmin/caddy-domain-manager/Services';
        foreach (['CloudflareAccountService.php', 'CloudflareZoneService.php'] as $file) {
            if (!is_file($dir . '/' . $file)) {
                return false;
            }
            require_once $dir . '/' . $file;
        }

        return class_exists(\CaddyDomainManager\Services\CloudflareZoneService::class);
    }
}
