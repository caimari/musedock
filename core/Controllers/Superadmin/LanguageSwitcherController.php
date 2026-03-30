<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\Services\TranslationService;
use Screenart\Musedock\Security\SessionSecurity;

/**
 * Controlador para cambiar el idioma en el panel de superadmin
 * NOTA: El idioma del superadmin se guarda en $_SESSION['superadmin_locale']
 * y NO se ve afectado por force_lang (que es para el frontend público)
 */
class LanguageSwitcherController
{
    /**
     * Cambiar idioma de la aplicación
     */
    public function switch()
    {
        $locale = $_GET['locale'] ?? $_POST['locale'] ?? 'es';
        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? $_SERVER['HTTP_REFERER'] ?? '/musedock/dashboard';

        // Obtener idiomas válidos de la BD
        $validLocales = ['es', 'en']; // Por defecto
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            // En /musedock solo se permiten idiomas globales (tenant_id IS NULL)
            $stmt = $pdo->query("SELECT code FROM languages WHERE tenant_id IS NULL AND active = 1");
            $dbLocales = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($dbLocales)) {
                $validLocales = $dbLocales;
            }
        } catch (\Exception $e) {
            // Usar valores por defecto
        }

        // Validar idioma
        if (!in_array($locale, $validLocales)) {
            $locale = $validLocales[0] ?? 'es';
        }

        // Iniciar sesión si no está activa
        SessionSecurity::startSession();

        // Guardar idioma específico del superadmin (NO afectado por force_lang)
        $_SESSION['superadmin_locale'] = $locale;

        // También guardar en las claves estándar para compatibilidad
        $_SESSION['locale'] = $locale;
        $_SESSION['lang'] = $locale;

        // Cookie específica para el panel (30 días)
        if (!headers_sent()) {
            setcookie('superadmin_locale', $locale, time() + (30 * 24 * 60 * 60), '/musedock/', '', false, true);
        }

        // Redirigir de vuelta (evitar open redirect: solo paths internos)
        if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/') {
            $redirect = '/musedock/dashboard';
        }
        header('Location: ' . $redirect);
        exit;
    }
}
