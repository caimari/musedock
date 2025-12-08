<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\Services\TranslationService;

/**
 * Controlador para cambiar el idioma en el panel de tenant
 */
class LanguageSwitcherController
{
    /**
     * Cambiar idioma de la aplicación
     */
    public function switch()
    {
        $locale = $_GET['locale'] ?? $_POST['locale'] ?? 'es';
        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/admin/dashboard';

        // Validar idioma
        if (!in_array($locale, ['es', 'en'])) {
            $locale = 'es';
        }

        // Establecer idioma
        TranslationService::setLocale($locale);

        // Redirigir de vuelta
        header('Location: ' . $redirect);
        exit;
    }
}
