<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Enviar el sitemap de un dominio a Google (Search Console API, sitemaps.submit): la vía soportada para
 * avisar de contenido nuevo o movido. No es instantánea: Google decide cuándo rastrear.
 */
class SitemapService
{
    private const API = 'https://www.googleapis.com/webmasters/v3/sites/';

    /** @var callable(string):int  código HTTP de una URL (inyectable para pruebas) */
    private $httpCode;

    public function __construct(?callable $httpCode = null)
    {
        $this->httpCode = $httpCode ?? [self::class, 'fetchCode'];
    }

    public static function sitemapUrl(string $host): string
    {
        return 'https://' . PropertyService::normalizeHost($host) . '/sitemap.xml';
    }

    /**
     * @param string $siteUrl propiedad de Google (sc-domain:x.com o https://x.com/)
     * @return array{ok:bool,message:string,sitemap:string}
     */
    public function submit(GoogleClient $client, string $siteUrl, string $host): array
    {
        $sitemap = self::sitemapUrl($host);
        $code = ($this->httpCode)($sitemap);
        if ($code !== 200) {
            return ['ok' => false, 'sitemap' => $sitemap, 'message' => "{$sitemap} no responde bien (HTTP {$code}); no se ha enviado."];
        }
        try {
            $client->request('PUT', self::API . rawurlencode($siteUrl) . '/sitemaps/' . rawurlencode($sitemap));
        } catch (GoogleApiException $e) {
            return ['ok' => false, 'sitemap' => $sitemap, 'message' => 'Google rechazó el sitemap: ' . $e->getMessage()];
        }

        return ['ok' => true, 'sitemap' => $sitemap, 'message' => "Sitemap enviado a Google: {$sitemap}"];
    }

    /** Estado del sitemap en Google (última descarga, errores…), o null si Google no lo conoce. */
    public function status(GoogleClient $client, string $siteUrl, string $host): ?array
    {
        $sitemap = self::sitemapUrl($host);
        try {
            $r = $client->request('GET', self::API . rawurlencode($siteUrl) . '/sitemaps/' . rawurlencode($sitemap));
        } catch (GoogleApiException $e) {
            return null;
        }

        return ['sitemap' => $sitemap, 'last_submitted' => $r['lastSubmitted'] ?? null, 'last_downloaded' => $r['lastDownloaded'] ?? null,
            'pending' => (bool) ($r['isPending'] ?? false), 'errors' => (int) ($r['errors'] ?? 0), 'warnings' => (int) ($r['warnings'] ?? 0)];
    }

    /**
     * Envía el sitemap de un dominio usando su propiedad ACTIVA de la plataforma (si la hay).
     * Devuelve ok = null si el dominio aún no está dado de alta en SEO (no es un error).
     *
     * @return array{ok:?bool,message:string}
     */
    public function submitForDomain(PDO $pdo, CredentialStore $store, string $domain): array
    {
        $domain = PropertyService::normalizeHost($domain);
        $stmt = $pdo->prepare("SELECT p.* FROM gsc_properties p JOIN gsc_credentials c ON c.id = p.credential_id AND c.owner_type = 'platform' WHERE p.status = 'active' AND p.domain = ? ORDER BY p.page_filter = '' DESC LIMIT 1");
        $stmt->execute([$domain]);
        $prop = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prop) {
            return ['ok' => null, 'message' => "{$domain} aún no está dado de alta en SEO: da de alta el dominio y se enviará su sitemap."];
        }
        $cred = $store->findById((int) $prop['credential_id']);
        if (!$cred) {
            return ['ok' => false, 'message' => 'No hay cuenta de servicio conectada.'];
        }
        $r = $this->submit($store->client($cred), $prop['site_url'], $domain);

        return ['ok' => $r['ok'], 'message' => $r['message']];
    }

    public static function fetchCode(string $url): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code;
    }
}
