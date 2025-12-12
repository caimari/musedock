<?php

namespace Screenart\Musedock\Security;

/**
 * Generador de CAPTCHA propio con PHP GD
 */
class Captcha
{
    /**
     * Genera una imagen CAPTCHA y la envía al navegador
     */
    public static function generate()
    {
        // Generar código aleatorio (5 caracteres)
        $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5);

        // Guardar en sesión
        $_SESSION['captcha_code'] = $code;
        $_SESSION['captcha_time'] = time();

        // Configuración de la imagen
        $width = 180;
        $height = 60;

        // Crear imagen
        $image = imagecreatetruecolor($width, $height);

        // Colores
        $bgColor = imagecolorallocate($image, 245, 245, 245);
        $textColor = imagecolorallocate($image, 33, 33, 33);
        $lineColor = imagecolorallocate($image, 100, 100, 100);
        $dotColor = imagecolorallocate($image, 150, 150, 150);

        // Fondo
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // Líneas de ruido (fondo)
        for ($i = 0; $i < 6; $i++) {
            imageline(
                $image,
                rand(0, $width),
                rand(0, $height),
                rand(0, $width),
                rand(0, $height),
                $lineColor
            );
        }

        // Puntos de ruido
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($image, rand(0, $width), rand(0, $height), $dotColor);
        }

        // Dibujar cada letra con posición y ángulo aleatorios
        $x = 20;
        for ($i = 0; $i < strlen($code); $i++) {
            $fontSize = rand(20, 24);
            $angle = rand(-15, 15);
            $y = rand(35, 45);

            // Usar fuente incorporada si no hay TTF
            imagestring($image, 5, $x, 18, $code[$i], $textColor);
            $x += 30;
        }

        // Líneas de ruido (frente)
        for ($i = 0; $i < 3; $i++) {
            imageline(
                $image,
                rand(0, $width),
                rand(0, $height),
                rand(0, $width),
                rand(0, $height),
                $lineColor
            );
        }

        // Enviar headers
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

        // Generar imagen
        imagepng($image);
        imagedestroy($image);
        exit;
    }

    /**
     * Verifica el código CAPTCHA ingresado
     *
     * @param string $input Código ingresado por el usuario
     * @return bool True si es válido
     */
    public static function verify($input)
    {
        if (!isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }

        // Expirar después de 5 minutos
        if (time() - $_SESSION['captcha_time'] > 300) {
            self::clear();
            return false;
        }

        // Verificar código (case insensitive)
        $valid = strtoupper(trim($input)) === $_SESSION['captcha_code'];

        // Limpiar después de verificar
        if ($valid) {
            self::clear();
        }

        return $valid;
    }

    /**
     * Limpia el CAPTCHA de la sesión
     */
    public static function clear()
    {
        unset($_SESSION['captcha_code']);
        unset($_SESSION['captcha_time']);
        unset($_SESSION['show_captcha']);
    }

    /**
     * Verifica si se debe mostrar el CAPTCHA
     *
     * @return bool
     */
    public static function shouldShow()
    {
        return isset($_SESSION['show_captcha']) && $_SESSION['show_captcha'] === true;
    }

    /**
     * Marca que se debe mostrar el CAPTCHA
     */
    public static function enable()
    {
        $_SESSION['show_captcha'] = true;
    }
}
