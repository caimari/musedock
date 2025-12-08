<?php

namespace Screenart\Musedock\Helpers;

class BladeProtector
{
    // Patrón -> marcador temporal -> restauración
    protected static array $directivas = [
        // Blade
        '/@(extends|section|yield|include|foreach|endforeach|if|endif|php|endphp|auth|endauth|guest|endguest|csrf|method|error|enderror)\b.*?$/m' => '__BLADE__%d__',
        // PHP puro
        '/<\?php(.*?)\?>/s' => '__PHP__%d__',
    ];

    public static function proteger(string $contenido): string
    {
        foreach (self::$directivas as $regex => $placeholder) {
            preg_match_all($regex, $contenido, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $i => $original) {
                    $marker = sprintf($placeholder, $i);
                    $contenido = str_replace($original, $marker, $contenido);
                    $_SESSION['blade_restore'][$marker] = $original;
                }
            }
        }
        return $contenido;
    }

    public static function restaurar(string $contenido): string
    {
        if (!isset($_SESSION['blade_restore'])) return $contenido;

        foreach ($_SESSION['blade_restore'] as $marker => $original) {
            $contenido = str_replace($marker, $original, $contenido);
        }

        // Limpiar para no acumular en memoria
        unset($_SESSION['blade_restore']);
        return $contenido;
    }
}
