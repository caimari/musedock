<?php

namespace Screenart\Musedock\Middlewares;

use Screenart\Musedock\Database;

class LanguageMiddleware
{
    public function handle()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Cargar ajustes de configuración
        $settings = Database::table('settings')->pluck('value', 'key');

        // 1. Idioma forzado por configuración (force_lang)
        if (!empty($settings['force_lang'])) {
            $_SESSION['lang'] = $settings['force_lang'];
            return true;
        }

        // 2. URL ?lang=es
        if (!empty($_GET['lang'])) {
            $available = Database::table('languages')
                ->where('active', 1)
                ->pluck('code');
            if (in_array($_GET['lang'], $available)) {
                $_SESSION['lang'] = $_GET['lang'];
                return true;
            }
        }

        // 3. Sesión previa (usuario ya había seleccionado idioma)
        if (!empty($_SESSION['lang'])) {
            return true;
        }

        // 4. Detección por navegador
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2);
        $available = Database::table('languages')
            ->where('active', 1)
            ->pluck('code');

        if (in_array($browserLang, $available)) {
            $_SESSION['lang'] = $browserLang;
            return true;
        }

        // 5. Fallback: idioma configurado en settings
        $_SESSION['lang'] = $settings['language'] ?? 'en';

        return true; // Siempre continuar flujo
    }
}
