<?php

namespace Screenart\Musedock\Mail;

/**
 * Cifrado de la contraseña SMTP que cada tenant guarda en tenant_settings (AES-256-GCM).
 * La clave sale de APP_ENCRYPTION_KEY (o APP_KEY), igual que las cuentas DNS, con un contexto propio.
 * Lo guardado antes en claro se sigue leyendo y se cifra la próxima vez que se guarden los ajustes.
 */
class SmtpSecret
{
    private const PREFIX = 'smtp1:';

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '' || self::isEncrypted($plain)) {
            return $plain;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('No se pudo cifrar la contraseña SMTP.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** Devuelve la contraseña en claro. Un valor guardado sin cifrar se devuelve tal cual; si no se puede descifrar, ''. */
    public static function decrypt(string $stored): string
    {
        if (!self::isEncrypted($stored)) {
            return $stored;
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plain === false ? '' : $plain;
    }

    private static function key(): string
    {
        $key = self::env('APP_ENCRYPTION_KEY');
        if ($key !== '') {
            $material = str_starts_with($key, 'base64:') ? (string) base64_decode(substr($key, 7)) : hash('sha256', $key, true);
        } else {
            $appKey = self::env('APP_KEY');
            if ($appKey === '') {
                throw new \RuntimeException('Falta APP_ENCRYPTION_KEY o APP_KEY para cifrar la contraseña SMTP.');
            }
            $material = hash('sha256', $appKey, true);
        }

        return hash('sha256', 'smtp-secret-gcm|' . $material, true);
    }

    private static function env(string $name): string
    {
        $v = getenv($name);
        if (($v === false || $v === '') && class_exists(\Screenart\Musedock\Env::class)) {
            $v = \Screenart\Musedock\Env::get($name);
        }

        return (string) ($v ?: '');
    }
}
