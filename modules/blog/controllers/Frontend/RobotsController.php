<?php

namespace Blog\Controllers\Frontend;

/**
 * Controlador para generar robots.txt dinámico por tenant
 *
 * Genera un robots.txt estándar con las reglas de acceso del sitio
 * y la referencia al sitemap.xml del tenant actual.
 *
 * Rutas:
 * - /robots.txt
 */
class RobotsController
{
    /**
     * Genera el robots.txt del sitio
     */
    public function index()
    {
        $siteUrl = $this->getSiteUrl();

        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            '# Admin and system paths',
            'Disallow: /admin/',
            'Disallow: /musedock/',
            'Disallow: /install/',
            'Disallow: /api/',
            '',
            '# Internal directories',
            'Disallow: /storage/',
            'Disallow: /config/',
            'Disallow: /core/',
            'Disallow: /database/',
            'Disallow: /modules/',
            'Disallow: /vendor/',
            'Disallow: /cli/',
            'Disallow: /cron/',
            'Disallow: /routes/',
            'Disallow: /plugins/',
            'Disallow: /tools/',
            'Disallow: /themes/',
            'Disallow: /lang/',
            '',
            '# Sitemap',
            'Sitemap: ' . $siteUrl . '/sitemap.xml',
        ];

        $content = implode("\n", $lines) . "\n";

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $content;
        exit;
    }

    /**
     * Obtiene la URL completa del sitio
     */
    private function getSiteUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
}
