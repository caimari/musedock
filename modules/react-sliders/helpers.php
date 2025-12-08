<?php

/**
 * Helper de traducción para el módulo React Sliders
 */

if (!function_exists('__rs')) {
    /**
     * Traducir texto del módulo React Sliders
     *
     * @param string $key Clave de traducción
     * @param array $replace Valores a reemplazar
     * @return string
     */
    function __rs(string $key, array $replace = []): string
    {
        static $translations = null;

        if ($translations === null) {
            $locale = app_locale(); // es o en
            $langFile = __DIR__ . "/lang/{$locale}.json";

            if (file_exists($langFile)) {
                $translations = json_decode(file_get_contents($langFile), true) ?? [];
            } else {
                // Fallback a español
                $langFile = __DIR__ . "/lang/es.json";
                $translations = json_decode(file_get_contents($langFile), true) ?? [];
            }
        }

        // Buscar la traducción usando dot notation
        $keys = explode('.', $key);
        $value = $translations;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $key; // Devolver la clave si no existe traducción
            }
            $value = $value[$k];
        }

        // Reemplazar placeholders
        if (!empty($replace)) {
            foreach ($replace as $search => $replaceValue) {
                $value = str_replace(":{$search}", $replaceValue, $value);
            }
        }

        return $value;
    }
}
