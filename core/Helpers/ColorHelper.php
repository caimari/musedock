<?php

namespace Screenart\Musedock\Helpers;

class ColorHelper
{
    /**
     * Calcula el color de contraste óptimo (blanco o negro) para un color de fondo dado
     *
     * @param string $hexColor Color en formato hexadecimal (#RRGGBB)
     * @return string '#ffffff' o '#000000'
     */
    public static function getContrastColor(string $hexColor): string
    {
        // Eliminar el # si existe
        $hexColor = ltrim($hexColor, '#');

        // Si el color es inválido, retornar negro por defecto
        if (strlen($hexColor) !== 6) {
            return '#000000';
        }

        // Convertir a RGB
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Calcular la luminancia relativa (ITU-R BT.709)
        // https://www.w3.org/TR/WCAG20/#relativeluminancedef
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        // Si la luminancia es mayor a 0.5, usar negro, sino blanco
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }

    /**
     * Determina si un color es claro u oscuro
     *
     * @param string $hexColor Color en formato hexadecimal
     * @return bool true si es claro, false si es oscuro
     */
    public static function isLightColor(string $hexColor): bool
    {
        return self::getContrastColor($hexColor) === '#000000';
    }
}
