<?php

namespace Screenart\Musedock\Helpers;

/**
 * 🔒 SECURITY: Helper para prevenir Mass Assignment
 * Previene: Asignación masiva de campos no autorizados
 */
class InputValidator
{
    /**
     * Filtrar array de entrada usando whitelist
     *
     * @param array $input Datos de entrada ($_POST o similar)
     * @param array $allowedFields Lista de campos permitidos
     * @return array Array filtrado solo con campos permitidos
     */
    public static function filterAllowedFields(array $input, array $allowedFields): array
    {
        $filtered = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $filtered[$field] = $input[$field];
            }
        }

        return $filtered;
    }

    /**
     * Validar longitud de campos de texto
     *
     * @param array $data Datos a validar
     * @param array $rules Reglas de longitud ['campo' => maxLength]
     * @return array Errores encontrados
     */
    public static function validateLength(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $maxLength) {
            if (isset($data[$field])) {
                $value = is_string($data[$field]) ? $data[$field] : '';
                if (strlen($value) > $maxLength) {
                    $errors[] = "El campo '{$field}' no puede superar {$maxLength} caracteres.";
                }
            }
        }

        return $errors;
    }

    /**
     * Validar campos requeridos
     *
     * @param array $data Datos a validar
     * @param array $requiredFields Campos requeridos
     * @return array Errores encontrados
     */
    public static function validateRequired(array $data, array $requiredFields): array
    {
        $errors = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "El campo '{$field}' es requerido.";
            }
        }

        return $errors;
    }

    /**
     * Sanitizar HTML en campos de texto
     *
     * @param mixed $value Valor a sanitizar
     * @param bool $allowHtml Si se permite HTML (default: false)
     * @return mixed Valor sanitizado
     */
    public static function sanitize($value, bool $allowHtml = false)
    {
        if (is_string($value)) {
            if ($allowHtml) {
                // Permitir HTML pero limpiar scripts y atributos peligrosos
                $value = strip_tags($value, '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><h5><h6><img><blockquote><code><pre>');
            } else {
                // Escapar todo el HTML
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        } elseif (is_array($value)) {
            // Recursivo para arrays
            foreach ($value as $k => $v) {
                $value[$k] = self::sanitize($v, $allowHtml);
            }
        }

        return $value;
    }

    /**
     * Validar formato de email
     *
     * @param string $email Email a validar
     * @return bool True si es válido
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validar formato de URL
     *
     * @param string $url URL a validar
     * @return bool True si es válida
     */
    public static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Forzar campo específico (prevenir modificación)
     *
     * @param array $data Datos a modificar
     * @param string $field Campo a forzar
     * @param mixed $value Valor forzado
     * @return array Datos con campo forzado
     */
    public static function forceField(array $data, string $field, $value): array
    {
        $data[$field] = $value;
        return $data;
    }
}
