<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Security\SessionSecurity;

/**
 * Servicio de Traducción Multi-idioma
 * Soporta traducciones para paneles Superadmin y Tenant
 */
class TranslationService
{
    private static $translations = [];
    private static $currentLocale = null;
    private static $fallbackLocale = 'es';
    private static $context = 'superadmin'; // superadmin | tenant

    /**
     * Cargar traducciones del idioma especificado
     */
    public static function load(string $locale, string $context = 'superadmin'): void
    {
        self::$currentLocale = $locale;
        self::$context = $context;

        $translationFile = __DIR__ . "/../../lang/{$context}/{$locale}.json";

        if (file_exists($translationFile)) {
            $content = file_get_contents($translationFile);
            self::$translations[$locale] = json_decode($content, true) ?? [];
            error_log("TranslationService: Loaded {$locale} for {$context} - " . count(self::$translations[$locale]) . " keys");
        } else {
            // Solo loguear si parece un locale válido (2 caracteres)
            if (strlen($locale) === 2) {
                error_log("TranslationService: File not found: {$translationFile}");
            }
            // Fallback al español
            $fallbackFile = __DIR__ . "/../../lang/{$context}/" . self::$fallbackLocale . ".json";
            if (file_exists($fallbackFile) && !isset(self::$translations[self::$fallbackLocale])) {
                $content = file_get_contents($fallbackFile);
                self::$translations[self::$fallbackLocale] = json_decode($content, true) ?? [];
                error_log("TranslationService: Loaded fallback {$context}/" . self::$fallbackLocale);
            }
        }
    }

    /**
     * Obtener traducción por clave
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        // Si no se especifica locale, obtener el actual (respeta force_lang)
        if ($locale === null) {
            $locale = self::$currentLocale ?? self::getCurrentLocale();
        }

        // Si no está cargado, cargar
        if (!isset(self::$translations[$locale])) {
            self::load($locale, self::$context);
        }

        // Buscar en el idioma actual
        $translation = self::findTranslation($key, $locale);

        // Si no existe, buscar en fallback
        if ($translation === null && $locale !== self::$fallbackLocale) {
            if (!isset(self::$translations[self::$fallbackLocale])) {
                self::load(self::$fallbackLocale, self::$context);
            }
            $translation = self::findTranslation($key, self::$fallbackLocale);
        }

        // Si aún no existe, retornar la clave
        if ($translation === null) {
            return $key;
        }

        // Reemplazar placeholders
        foreach ($replace as $search => $value) {
            $translation = str_replace(":{$search}", $value, $translation);
        }

        return $translation;
    }

    /**
     * Buscar traducción en el array cargado (soporta dot notation)
     */
    private static function findTranslation(string $key, string $locale): ?string
    {
        $keys = explode('.', $key);
        $value = self::$translations[$locale] ?? [];

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Obtener idioma actual desde configuración, sesión o navegador
     */
    public static function getCurrentLocale(): string
    {
        // Usar detectLanguage() si está disponible, ya que maneja force_lang correctamente
        if (function_exists('detectLanguage')) {
            self::$currentLocale = detectLanguage();
            return self::$currentLocale;
        }

        // Fallback manual si detectLanguage() no existe
        SessionSecurity::startSession();

        // 1. Verificar force_lang en settings (MÁXIMA PRIORIDAD)
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'force_lang' LIMIT 1");
            $stmt->execute();
            $forceLang = $stmt->fetchColumn();

            if (!empty($forceLang)) {
                self::$currentLocale = $forceLang;
                return self::$currentLocale;
            }
        } catch (\Exception $e) {
            // Ignorar errores de DB
        }

        // 2. Verificar sesión
        if (isset($_SESSION['locale']) && in_array($_SESSION['locale'], ['es', 'en'])) {
            self::$currentLocale = $_SESSION['locale'];
            return self::$currentLocale;
        }

        // 3. Verificar cookie
        if (isset($_COOKIE['locale']) && in_array($_COOKIE['locale'], ['es', 'en'])) {
            self::$currentLocale = $_COOKIE['locale'];
            $_SESSION['locale'] = self::$currentLocale;
            return self::$currentLocale;
        }

        // 4. Detectar desde navegador
        $browserLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (strpos($browserLang, 'en') !== false) {
            self::$currentLocale = 'en';
        } else {
            self::$currentLocale = self::$fallbackLocale;
        }

        return self::$currentLocale;
    }

    /**
     * Establecer idioma actual (guardar en sesión y cookie)
     */
    public static function setLocale(string $locale): void
    {
        if (!in_array($locale, ['es', 'en'])) {
            $locale = self::$fallbackLocale;
        }

        self::$currentLocale = $locale;

        // Guardar en sesión
        SessionSecurity::startSession();
        $_SESSION['locale'] = $locale;

        // Guardar en cookie (30 días)
        setcookie('locale', $locale, time() + (30 * 24 * 60 * 60), '/', '', false, true);

        error_log("TranslationService: setLocale to {$locale} - Session: " . ($_SESSION['locale'] ?? 'NOT SET'));
    }

    /**
     * Establecer contexto (superadmin o tenant)
     */
    public static function setContext(string $context): void
    {
        self::$context = $context;
    }

    /**
     * Obtener todos los idiomas disponibles
     */
    public static function getAvailableLocales(): array
    {
        return [
            'es' => 'Español',
            'en' => 'English'
        ];
    }

    /**
     * Traducción con pluralización
     */
    public static function choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $translation = self::get($key, $replace, $locale);

        // Formato simple: "item|items"
        if (strpos($translation, '|') !== false) {
            $parts = explode('|', $translation);
            return $count === 1 ? $parts[0] : ($parts[1] ?? $parts[0]);
        }

        return $translation;
    }
}
