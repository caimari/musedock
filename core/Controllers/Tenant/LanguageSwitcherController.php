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

        if (!is_string($locale)) {
            $locale = 'es';
        }
        $locale = strtolower(trim($locale));
        if (!preg_match('/^[a-z]{2,10}([_-][a-z0-9]{2,10})?$/i', $locale)) {
            $locale = 'es';
        }

        // Establecer idioma (valida contra idiomas del tenant/global según tenant_id())
        TranslationService::setLocale($locale);

        // Redirigir de vuelta (evitar open redirect: solo paths internos)
        if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/') {
            $redirect = '/admin/dashboard';
        }
        header('Location: ' . $redirect);
        exit;
    }
}
