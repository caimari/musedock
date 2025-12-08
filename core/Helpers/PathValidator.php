<?php

namespace Screenart\Musedock\Helpers;

/**
 * 🔒 SECURITY: Helper para validación de paths
 * Previene: Path traversal, directory escape
 */
class PathValidator
{
    /**
     * Validar que un path esté dentro de un directorio permitido
     *
     * @param string $path Path a validar
     * @param string $allowedDir Directorio base permitido
     * @return bool True si es válido
     */
    public static function isWithinDirectory(string $path, string $allowedDir): bool
    {
        $realPath = realpath($path);
        $realAllowedDir = realpath($allowedDir);

        if ($realPath === false || $realAllowedDir === false) {
            return false;
        }

        return strpos($realPath, $realAllowedDir) === 0;
    }

    /**
     * Validar nombre de archivo seguro
     *
     * @param string $filename Nombre de archivo
     * @param array $allowedExtensions Extensiones permitidas (opcional)
     * @return bool True si es válido
     */
    public static function isValidFilename(string $filename, array $allowedExtensions = []): bool
    {
        // Remover path si existe
        $filename = basename($filename);

        // Validar caracteres permitidos
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            return false;
        }

        // Validar extensión si se especifica
        if (!empty($allowedExtensions)) {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                return false;
            }
        }

        // Validar que no sea un nombre peligroso
        $dangerousNames = ['.htaccess', '.htpasswd', 'web.config', '.env', '.git'];
        if (in_array(strtolower($filename), $dangerousNames, true)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitizar path removiendo componentes peligrosos
     *
     * @param string $path Path a sanitizar
     * @return string Path sanitizado
     */
    public static function sanitizePath(string $path): string
    {
        // Remover componentes peligrosos
        $path = str_replace(['../', '..\\', '../', '..\\'], '', $path);

        // Normalizar separadores
        $path = str_replace('\\', '/', $path);

        // Remover múltiples slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remover trailing slash
        $path = rtrim($path, '/');

        return $path;
    }

    /**
     * Validar que un directorio no contiene path traversal
     *
     * @param string $dir Directorio a validar
     * @return bool True si es válido
     */
    public static function isValidDirectory(string $dir): bool
    {
        // No debe contener ..
        if (strpos($dir, '..') !== false) {
            return false;
        }

        // No debe ser absoluto
        if (strpos($dir, '/') === 0 || preg_match('/^[A-Z]:\\//', $dir)) {
            return false;
        }

        return true;
    }
}
