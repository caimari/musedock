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

    private ?\Screenart\Musedock\Services\Dns\DnsZones $zones = null;

    public function __construct(private GoogleClient $client, ?\Screenart\Musedock\Services\Dns\DnsZones $zones = null)
    {
        $this->zones = $zones;
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

            // Si el DNS está en una cuenta del registro DNS del CMS, el TXT se publica solo. Si no, se intenta
            // igualmente la verificación por si el TXT ya se puso a mano en el DNS del dominio.
            $zone = $this->findZone($domain);
            if ($zone !== null) {
                $zone['client']->ensureTxt($zone['zone']['id'], $domain, $token);
            }

            try {
                $this->client->request('POST', self::VERIFY_API . '/webResource?verificationMethod=DNS_TXT', [
                    'site' => ['type' => 'INET_DOMAIN', 'identifier' => $domain],
                ]);
            } catch (GoogleApiException $e) {
                if ($e->status === 400) {
                    if ($zone === null) {
                        return ['status' => 'manual', 'message' => "El DNS de {$domain} no está en ninguna cuenta DNS del CMS (Ajustes → Cuentas DNS). Publica este registro TXT en el DNS del dominio (nombre @) y vuelve a pulsar «Dar de alta»: {$token}  — o, alternativa, añade a mano el email de la cuenta de servicio como usuario en Search Console."];
                    }

                    return ['status' => 'pending', 'message' => 'TXT publicado en el DNS; Google aún no lo ve (propagación DNS). Se reintentará.'];
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
     * Busca la zona del dominio (o de un dominio padre) en el registro único de cuentas DNS del CMS
     * (Ajustes → Cuentas DNS). Devuelve ['client' => DnsZoneClient, 'zone' => ['id', 'name']] o null.
     */
    private function findZone(string $domain): ?array
    {
        try {
            return $this->zones()->locate($domain);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function zones(): \Screenart\Musedock\Services\Dns\DnsZones
    {
        return $this->zones ??= new \Screenart\Musedock\Services\Dns\DnsZones(\Screenart\Musedock\Services\DnsAccounts::make());
    }
}
