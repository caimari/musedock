<?php

namespace Screenart\Musedock\Security;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Two-Factor Authentication (2FA) usando TOTP
 * Compatible con Google Authenticator, Authy, Microsoft Authenticator, etc.
 *
 * Implementación basada en RFC 6238 (TOTP) y RFC 4226 (HOTP)
 */
class TwoFactorAuth
{
    // Configuración TOTP
    private const DIGITS = 6;           // Número de dígitos del código
    private const PERIOD = 30;          // Segundos de validez del código
    private const ALGORITHM = 'sha1';   // Algoritmo HMAC
    private const SECRET_LENGTH = 20;   // Longitud del secreto en bytes
    private const ISSUER = 'MuseDock';  // Nombre que aparece en la app

    // Códigos de recuperación
    private const RECOVERY_CODES_COUNT = 10;
    private const RECOVERY_CODE_LENGTH = 8;

    /**
     * Generar un nuevo secreto para 2FA
     */
    public static function generateSecret(): string
    {
        $bytes = random_bytes(self::SECRET_LENGTH);
        return self::base32Encode($bytes);
    }

    /**
     * Generar URL para QR code (otpauth://)
     */
    public static function getQRCodeUrl(string $secret, string $email, string $issuer = null): string
    {
        $issuer = $issuer ?? self::ISSUER;
        $label = urlencode($issuer . ':' . $email);
        $issuerEncoded = urlencode($issuer);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            $label,
            $secret,
            $issuerEncoded,
            strtoupper(self::ALGORITHM),
            self::DIGITS,
            self::PERIOD
        );
    }

    /**
     * Generar URL otpauth:// para código QR
     * El QR se genera en el cliente con JavaScript (qrcode.js)
     */
    public static function getQRCodeDataUri(string $secret, string $email): string
    {
        // Retorna la URL otpauth:// directamente
        // El QR se genera en el frontend con JavaScript
        return self::getQRCodeUrl($secret, $email);
    }

    /**
     * Verificar código TOTP
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        // Limpiar código
        $code = preg_replace('/\s+/', '', $code);

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        if (!ctype_digit($code)) {
            return false;
        }

        $currentTimestamp = floor(time() / self::PERIOD);

        // Verificar con tolerancia (discrepancy)
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $expectedCode = self::generateTOTP($secret, $currentTimestamp + $i);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generar código TOTP para un timestamp
     */
    private static function generateTOTP(string $secret, int $counter): string
    {
        // Decodificar secreto Base32
        $key = self::base32Decode($secret);

        // Convertir counter a bytes (8 bytes, big-endian)
        $counterBytes = pack('N*', 0, $counter);

        // Calcular HMAC
        $hash = hash_hmac(self::ALGORITHM, $counterBytes, $key, true);

        // Dynamic truncation
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binary % pow(10, self::DIGITS);

        return str_pad((string)$otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Generar códigos de recuperación
     */
    public static function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            $code = '';
            for ($j = 0; $j < self::RECOVERY_CODE_LENGTH; $j++) {
                $code .= random_int(0, 9);
            }
            // Formato: XXXX-XXXX
            $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4);
        }

        return $codes;
    }

    /**
     * Verificar código de recuperación
     */
    public static function verifyRecoveryCode(int $userId, string $code, string $userType = 'user'): bool
    {
        $db = Database::connect();
        $table = self::getTableForUserType($userType);

        // Normalizar código
        $code = strtoupper(str_replace('-', '', $code));

        // Obtener códigos de recuperación
        $stmt = $db->prepare("SELECT two_factor_recovery_codes FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result || empty($result['two_factor_recovery_codes'])) {
            return false;
        }

        $storedCodes = json_decode($result['two_factor_recovery_codes'], true);

        if (!is_array($storedCodes)) {
            return false;
        }

        // Buscar código (hasheado)
        foreach ($storedCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                // Eliminar código usado
                unset($storedCodes[$index]);
                $storedCodes = array_values($storedCodes);

                // Actualizar en BD
                $stmt = $db->prepare("UPDATE {$table} SET two_factor_recovery_codes = ? WHERE id = ?");
                $stmt->execute([json_encode($storedCodes), $userId]);

                Logger::log("2FA recovery code used for {$userType} ID: {$userId}", 'INFO');

                return true;
            }
        }

        return false;
    }

    /**
     * Habilitar 2FA para un usuario
     */
    public static function enable(int $userId, string $secret, array $recoveryCodes, string $userType = 'user'): bool
    {
        $db = Database::connect();
        $table = self::getTableForUserType($userType);

        // Hashear códigos de recuperación
        $hashedCodes = array_map(function ($code) {
            return password_hash(str_replace('-', '', $code), PASSWORD_DEFAULT);
        }, $recoveryCodes);

        try {
            $stmt = $db->prepare("
                UPDATE {$table} SET
                    two_factor_enabled = 1,
                    two_factor_secret = ?,
                    two_factor_recovery_codes = ?,
                    two_factor_enabled_at = NOW()
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $secret,
                json_encode($hashedCodes),
                $userId
            ]);

            if ($result) {
                Logger::log("2FA enabled for {$userType} ID: {$userId}", 'INFO');
            }

            return $result;

        } catch (\Exception $e) {
            Logger::log("2FA enable error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Deshabilitar 2FA
     */
    public static function disable(int $userId, string $userType = 'user'): bool
    {
        $db = Database::connect();
        $table = self::getTableForUserType($userType);

        try {
            $stmt = $db->prepare("
                UPDATE {$table} SET
                    two_factor_enabled = 0,
                    two_factor_secret = NULL,
                    two_factor_recovery_codes = NULL,
                    two_factor_enabled_at = NULL
                WHERE id = ?
            ");

            $result = $stmt->execute([$userId]);

            if ($result) {
                Logger::log("2FA disabled for {$userType} ID: {$userId}", 'INFO');
            }

            return $result;

        } catch (\Exception $e) {
            Logger::log("2FA disable error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Verificar si 2FA está habilitado
     */
    public static function isEnabled(int $userId, string $userType = 'user'): bool
    {
        $db = Database::connect();
        $table = self::getTableForUserType($userType);

        $stmt = $db->prepare("SELECT two_factor_enabled FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result && (bool)$result['two_factor_enabled'];
    }

    /**
     * Obtener secreto del usuario
     */
    public static function getSecret(int $userId, string $userType = 'user'): ?string
    {
        $db = Database::connect();
        $table = self::getTableForUserType($userType);

        $stmt = $db->prepare("SELECT two_factor_secret FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result['two_factor_secret'] ?? null;
    }

    /**
     * Obtener cantidad de códigos de recuperación restantes
     */
    public static function getRemainingRecoveryCodes(int $userId, string $userType = 'user'): int
    {
        $db = Database::connect();
        $table = self::getTableForUserType($userType);

        $stmt = $db->prepare("SELECT two_factor_recovery_codes FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result || empty($result['two_factor_recovery_codes'])) {
            return 0;
        }

        $codes = json_decode($result['two_factor_recovery_codes'], true);

        return is_array($codes) ? count($codes) : 0;
    }

    /**
     * Obtener tabla según tipo de usuario
     */
    private static function getTableForUserType(string $userType): string
    {
        $tables = [
            'super_admin' => 'super_admins',
            'admin' => 'admins',
            'user' => 'users'
        ];

        return $tables[$userType] ?? 'users';
    }

    /**
     * Codificar a Base32
     */
    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';

        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($binary, 5);
        $lastChunk = end($chunks);

        if (strlen($lastChunk) < 5) {
            $chunks[count($chunks) - 1] = str_pad($lastChunk, 5, '0', STR_PAD_RIGHT);
        }

        $base32 = '';
        foreach ($chunks as $chunk) {
            $base32 .= $alphabet[bindec($chunk)];
        }

        return $base32;
    }

    /**
     * Decodificar Base32
     */
    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $data = str_replace('=', '', $data);

        $binary = '';
        foreach (str_split($data) as $char) {
            $index = strpos($alphabet, $char);
            if ($index !== false) {
                $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
            }
        }

        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
