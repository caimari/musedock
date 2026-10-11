<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

final class Deployment
{
    public static function setEnabled(bool $enabled): void
    {
        $path = APP_ROOT . '/.env';
        $lock = fopen(dirname(APP_ROOT) . '/.musedock-cms/deployment.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('No se pudo bloquear la configuración del servicio.');
        $temp = null;
        try {
            $text = file_get_contents($path);
            if ($text === false) throw new \RuntimeException('No se pudo leer la configuración del CMS.');
            foreach (['MCP_ENABLED', 'MCP_CMS_DIRECT_ENABLED'] as $key) {
                $line = $key . '=' . ($enabled ? 'true' : 'false');
                $pattern = '/^' . $key . '\\s*=.*$/m';
                $text = preg_match($pattern, $text) ? preg_replace($pattern, $line, $text) : rtrim($text) . "\n" . $line . "\n";
            }
            $temp = tempnam(dirname($path), '.mcp-env-');
            if (!$temp || file_put_contents($temp, $text) === false) throw new \RuntimeException('No se pudo guardar la configuración del servicio.');
            chmod($temp, fileperms($path) & 0777);
            if (!rename($temp, $path)) throw new \RuntimeException('No se pudo actualizar la configuración del servicio.');
            $temp = null;
        } finally {
            if ($temp !== null) unlink($temp);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
