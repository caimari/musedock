<?php

namespace Screenart\Musedock\Helpers;

/**
 * Variantes optimizadas de imágenes locales (logos, iconos del tema...).
 *
 * Genera una vez una copia WebP redimensionada a la altura de pantalla (×2 para
 * pantallas retina) en public/assets/cache/img/ y devuelve su URL junto con
 * width/height, para que el <img> no provoque saltos de diseño (CLS) ni
 * descargue un PNG enorme para mostrarlo a 50 px.
 *
 * Si la imagen no es local, no es raster, o no se puede procesar, devuelve la
 * URL original sin tocar (nunca rompe la página).
 */
class ImageVariant
{
    private const CACHE_DIR = 'assets/cache/img';

    /**
     * @param string $url           URL pública de la imagen (absoluta del propio sitio o relativa)
     * @param int    $displayHeight altura a la que se muestra (CSS px)
     * @return array{url:string, width:?int, height:?int}
     */
    public static function forHeight(string $url, int $displayHeight): array
    {
        $original = ['url' => $url, 'width' => null, 'height' => null];

        $source = self::localPath($url);
        if ($source === null || !function_exists('imagewebp')) {
            return $original;
        }

        $info = @getimagesize($source);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return $original;
        }
        [$srcW, $srcH] = $info;

        // Con max-height en CSS la imagen nunca se amplía: no declarar más de lo real
        $displayHeight = min($displayHeight, $srcH);
        $displayWidth = (int) round($srcW * $displayHeight / $srcH);
        $sized = ['url' => $url, 'width' => $displayWidth, 'height' => $displayHeight];

        // Ya es pequeña: solo añadir dimensiones
        $targetH = min($srcH, $displayHeight * 2);
        if ($targetH >= $srcH && $info[2] === IMAGETYPE_WEBP) {
            return $sized;
        }

        $targetW = (int) round($srcW * $targetH / $srcH);
        $hash = substr(sha1($source . '|' . filemtime($source) . '|' . $targetH), 0, 16);
        $relative = self::CACHE_DIR . '/' . pathinfo($source, PATHINFO_FILENAME) . "-{$targetH}h-{$hash}.webp";
        $target = self::publicDir() . '/' . $relative;

        if (!is_file($target) && !self::render($source, $info[2], $target, $targetW, $targetH)) {
            return $sized;
        }

        $sized['url'] = '/' . $relative;
        return $sized;
    }

    /**
     * Igual que forHeight() pero fijando el ancho mostrado (imágenes de contenido).
     *
     * @return array{url:string, width:?int, height:?int}
     */
    public static function forWidth(string $url, int $displayWidth): array
    {
        $source = self::localPath($url);
        $info = $source ? @getimagesize($source) : false;
        if (!$info || empty($info[0]) || empty($info[1])) {
            return ['url' => $url, 'width' => null, 'height' => null];
        }

        $displayWidth = min($displayWidth, $info[0]);
        return self::forHeight($url, (int) round($info[1] * $displayWidth / $info[0]));
    }

    /**
     * Ruta en disco si la URL apunta a un fichero de public/ de este sitio.
     */
    private static function localPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $host = parse_url($url, PHP_URL_HOST);
        if (!$path || ($host && strcasecmp($host, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0
            && strcasecmp($host, (string) parse_url(url('/'), PHP_URL_HOST)) !== 0)) {
            return null;
        }

        $path = '/' . ltrim(preg_replace('#/+#', '/', $path), '/');
        if (str_contains($path, '..') || !preg_match('/\.(png|jpe?g|webp)$/i', $path)) {
            return null;
        }

        $file = self::publicDir() . $path;
        return is_file($file) ? $file : null;
    }

    /**
     * public/ del CMS (public_path() depende de DOCUMENT_ROOT, vacío en CLI).
     */
    private static function publicDir(): string
    {
        return defined('APP_ROOT') ? rtrim(APP_ROOT, '/') . '/public' : rtrim(public_path(), '/');
    }

    private static function render(string $source, int $type, string $target, int $width, int $height): bool
    {
        $image = match ($type) {
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default        => false,
        };
        if (!$image) {
            return false;
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = @imagewebp($canvas, $tmp, 85) && @rename($tmp, $target);
        @unlink($tmp);
        imagedestroy($canvas);
        imagedestroy($image);

        return $ok;
    }
}
