<?php

namespace Modules\SearchConsole\Services;

use RuntimeException;

/**
 * Cifrado AES-256-GCM de las claves JSON de las cuentas de servicio de Google.
 *
 * La subclave depende del ámbito (plataforma o tenant concreto): la clave de un
 * cliente solo se descifra en su propio ámbito, aunque alguien copiara la fila
 * cifrada a otro registro.
 */
class Crypto
{
    private const PREFIX = 'gsc1:';

    public static function encrypt(string $plain, string $scope): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key($scope), OPENSSL_RAW_DATA, $iv, $tag, $scope);
        if ($cipher === false) {
            throw new RuntimeException('No se pudo cifrar la clave.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $value, string $scope): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            throw new RuntimeException('Formato de clave cifrada desconocido.');
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Clave cifrada dañada.');
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key($scope), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), $scope);
        if ($plain === false) {
            throw new RuntimeException('No se pudo descifrar la clave en este ámbito.');
        }

        return $plain;
    }

    /** Ámbito de cifrado: 'platform' o 'tenant:<id>'. */
    public static function scope(?int $tenantId): string
    {
        return $tenantId === null ? 'platform' : 'tenant:' . $tenantId;
    }

    private static function key(string $scope): string
    {
        $key = (string) (\Screenart\Musedock\Env::get('APP_ENCRYPTION_KEY') ?: \Screenart\Musedock\Env::get('APP_KEY'));
        if ($key === '') {
            $key = (string) (getenv('APP_ENCRYPTION_KEY') ?: getenv('APP_KEY'));
        }
        if ($key === '') {
            throw new RuntimeException('Falta APP_ENCRYPTION_KEY o APP_KEY en .env: no se guardan claves sin cifrar.');
        }
        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7));
        }

        return hash_hmac('sha256', 'search-console|' . $scope, $key, true);
    }
}
