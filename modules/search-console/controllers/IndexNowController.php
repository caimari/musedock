<?php

namespace Modules\SearchConsole\Controllers;

/** Sirve la clave de IndexNow (https://dominio/<clave>.txt): es pública por diseño, la verifica el buscador. */
class IndexNowController
{
    public function key()
    {
        $file = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/storage/indexnow.json';
        $c = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $key = is_array($c) ? (string) ($c['key'] ?? '') : '';
        if (!preg_match('/^[a-f0-9]{16,64}$/', $key) || ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/') !== $key . '.txt') {
            http_response_code(404);
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo $key;
        exit;
    }
}
