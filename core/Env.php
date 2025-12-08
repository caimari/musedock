<?php

namespace Screenart\Musedock;

/**
 * Clase para manejar variables de entorno
 */
class Env
{
    private static $loaded = false;
    private static $vars = [];

    /**
     * Carga el archivo .env
     */
    public static function load($path = null)
    {
        if (self::$loaded) {
            return;
        }

        $path = $path ?? __DIR__ . '/../.env';

        if (!file_exists($path)) {
            // Si no existe .env, intentar cargar desde variables de servidor
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parsear línea
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remover comillas
                $value = trim($value, '"\'');

                // Guardar en array estático
                self::$vars[$name] = $value;

                // También establecer en $_ENV y putenv
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtiene una variable de entorno
     *
     * @param string $key Nombre de la variable
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        // Cargar .env si no se ha cargado
        if (!self::$loaded) {
            self::load();
        }

        // Buscar en orden: vars estáticas, $_ENV, getenv()
        if (isset(self::$vars[$key])) {
            return self::parse(self::$vars[$key]);
        }

        if (isset($_ENV[$key])) {
            return self::parse($_ENV[$key]);
        }

        $value = getenv($key);
        if ($value !== false) {
            return self::parse($value);
        }

        return $default;
    }

    /**
     * Parsea valores booleanos
     *
     * @param mixed $value
     * @return mixed
     */
    private static function parse($value)
    {
        if (is_string($value)) {
            $lower = strtolower($value);

            if ($lower === 'true') {
                return true;
            }

            if ($lower === 'false') {
                return false;
            }

            if ($lower === 'null') {
                return null;
            }
        }

        return $value;
    }

    /**
     * Verifica si existe una variable
     *
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$vars[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }
}
