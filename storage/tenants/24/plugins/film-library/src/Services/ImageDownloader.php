<?php

namespace FilmLibrary\Services;

class ImageDownloader
{
    private string $basePath;
    private string $baseUrl;

    public function __construct(int $tenantId)
    {
        $root = dirname(__DIR__, 6); // httpdocs root
        $this->basePath = $root . '/storage/tenants/' . $tenantId . '/films/images';
        $this->baseUrl = '/storage/tenants/' . $tenantId . '/films/images';
    }

    /**
     * Download a TMDb image and store locally.
     * Returns local relative path or null on failure.
     *
     * @param string $tmdbPath  e.g. "/abc123.jpg"
     * @param string $size      e.g. "w500", "w185", "w1280"
     * @param string $type      e.g. "posters", "backdrops", "people"
     */
    public function download(string $tmdbPath, string $size = 'w500', string $type = 'posters'): ?string
    {
        if (empty($tmdbPath)) return null;

        $dir = $this->basePath . '/' . $type;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Filename: size + original TMDb filename
        $filename = $size . str_replace('/', '_', $tmdbPath);
        $localPath = $dir . '/' . $filename;

        // Skip if already downloaded
        if (file_exists($localPath) && filesize($localPath) > 0) {
            return $this->baseUrl . '/' . $type . '/' . $filename;
        }

        $url = 'https://image.tmdb.org/t/p/' . $size . $tmdbPath;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($data)) {
            error_log("ImageDownloader: Failed to download {$url} (HTTP {$httpCode})");
            return null;
        }

        if (file_put_contents($localPath, $data) === false) {
            error_log("ImageDownloader: Failed to write {$localPath}");
            return null;
        }

        return $this->baseUrl . '/' . $type . '/' . $filename;
    }

    /**
     * Download poster in multiple sizes.
     */
    public function downloadPoster(string $tmdbPath): array
    {
        $results = [];
        foreach (['w154', 'w342', 'w500', 'w780'] as $size) {
            $results[$size] = $this->download($tmdbPath, $size, 'posters');
        }
        return $results;
    }

    /**
     * Download backdrop.
     */
    public function downloadBackdrop(string $tmdbPath): ?string
    {
        return $this->download($tmdbPath, 'w1280', 'backdrops');
    }

    /**
     * Download person photo.
     */
    public function downloadPersonPhoto(string $tmdbPath): ?string
    {
        return $this->download($tmdbPath, 'w185', 'people');
    }

    /**
     * Get local URL for a TMDb path if it exists.
     */
    public function getLocalUrl(string $tmdbPath, string $size = 'w500', string $type = 'posters'): ?string
    {
        $filename = $size . str_replace('/', '_', $tmdbPath);
        $localPath = $this->basePath . '/' . $type . '/' . $filename;

        if (file_exists($localPath) && filesize($localPath) > 0) {
            return $this->baseUrl . '/' . $type . '/' . $filename;
        }

        return null;
    }
}
