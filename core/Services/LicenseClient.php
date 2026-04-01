<?php

namespace Screenart\Musedock\Services;

/**
 * HTTP client for the MuseDock License Server API.
 * Used by Plugin Store to verify licenses, browse catalog, and download products.
 */
class LicenseClient
{
    private static function getBaseUrl(): string
    {
        return rtrim(
            defined('LICENSE_SERVER_URL') ? LICENSE_SERVER_URL
            : ($_ENV['LICENSE_SERVER_URL'] ?? 'https://license.musedock.com'),
            '/'
        );
    }

    private static function getStoragePath(): string
    {
        return APP_ROOT . '/storage/premium-licenses.json';
    }

    /**
     * Get the public product catalog from the License Server.
     * @return array List of products or empty array on failure
     */
    public static function getCatalog(): array
    {
        $response = self::request('GET', '/api/v1/products/catalog');
        return $response['products'] ?? [];
    }

    /**
     * Verify a license key for a specific product and domain.
     * @return array|null Response data or null on failure
     */
    public static function verifyLicense(string $key, string $domain, string $productSlug): ?array
    {
        return self::request('POST', '/api/v1/licenses/verify', [
            'key'          => $key,
            'domain'       => $domain,
            'product_slug' => $productSlug,
        ]);
    }

    /**
     * Activate (bind) a license key to a domain.
     * @return array|null Response data or null on failure
     */
    public static function activateLicense(string $key, string $domain): ?array
    {
        return self::request('POST', '/api/v1/licenses/activate-domain', [
            'key'    => $key,
            'domain' => $domain,
        ]);
    }

    /**
     * Download a product ZIP file from the License Server.
     * @return string|null Path to the downloaded temp file or null on failure
     */
    public static function downloadProduct(string $productSlug, string $key): ?string
    {
        $url = self::getBaseUrl() . '/api/v1/downloads/' . urlencode($productSlug) . '?key=' . urlencode($key);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 120,
                'header'  => "User-Agent: MuseDock-CMS\r\n",
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return null;
        }

        // Check if response is JSON error
        if (str_starts_with($data, '{')) {
            $json = json_decode($data, true);
            if (isset($json['error'])) {
                return null;
            }
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'mdk_') . '.zip';
        file_put_contents($tmpFile, $data);
        return $tmpFile;
    }

    // ---- Local license storage ----

    /**
     * Save a verified license to local storage.
     */
    public static function saveLicense(string $key, array $data): void
    {
        $licenses = self::getStoredLicenses();
        $licenses[$key] = array_merge($data, [
            'verified_at' => date('c'),
        ]);
        self::writeStoredLicenses($licenses);
    }

    /**
     * Remove a license from local storage.
     */
    public static function removeLicense(string $key): void
    {
        $licenses = self::getStoredLicenses();
        unset($licenses[$key]);
        self::writeStoredLicenses($licenses);
    }

    /**
     * Get all locally stored premium licenses.
     */
    public static function getStoredLicenses(): array
    {
        $path = self::getStoragePath();
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Find stored license by product slug.
     */
    public static function findLicenseByProduct(string $productSlug): ?array
    {
        foreach (self::getStoredLicenses() as $key => $license) {
            if (($license['product_slug'] ?? '') === $productSlug) {
                return array_merge($license, ['key' => $key]);
            }
        }
        return null;
    }

    private static function writeStoredLicenses(array $licenses): void
    {
        $path = self::getStoragePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($licenses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // ---- HTTP helper ----

    private static function request(string $method, string $endpoint, ?array $body = null): ?array
    {
        $url = self::getBaseUrl() . $endpoint;

        $headers = [
            "User-Agent: MuseDock-CMS",
            "Accept: application/json",
        ];

        $options = [
            'http' => [
                'method'          => $method,
                'timeout'         => 15,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ];

        if ($body !== null && $method === 'POST') {
            $jsonBody = json_encode($body);
            $headers[] = "Content-Type: application/json";
            $headers[] = "Content-Length: " . strlen($jsonBody);
            $options['http']['content'] = $jsonBody;
        }

        $options['http']['header'] = implode("\r\n", $headers);

        $ctx = stream_context_create($options);
        $response = @file_get_contents($url, false, $ctx);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}
