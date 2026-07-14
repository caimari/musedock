<?php

namespace NodeOrchestrator\Services;

use Screenart\Musedock\Database;
use Exception;
use PDO;

class NodeCredentialService
{
    private PDO $pdo;
    private string $encryptionKey;
    private const CIPHER = 'aes-256-cbc';

    public function __construct()
    {
        $this->pdo = Database::connect();
        $this->encryptionKey = $this->resolveEncryptionKey();
    }

    public function resolveNodeApiKey(array $node): string
    {
        $encrypted = trim((string) ($node['api_key_encrypted'] ?? ''));
        if ($encrypted !== '') {
            return $this->decrypt($encrypted);
        }

        $plain = trim((string) ($node['api_key'] ?? ''));
        if ($plain !== '') {
            // Migración transparente: al primer uso, persistimos cifrado en DB.
            if (!empty($node['id']) && $this->hasEncryptedColumn()) {
                $this->storeEncryptedApiKey((int) $node['id'], $plain);
            }
            return $plain;
        }

        return '';
    }

    public function storeEncryptedApiKey(int $nodeId, string $plainToken): bool
    {
        if ($plainToken === '' || !$this->hasEncryptedColumn()) {
            return false;
        }

        $encrypted = $this->encrypt($plainToken);
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $clearSql = $driver === 'mysql' ? ", api_key = NULL" : ", api_key = NULL";

        $stmt = $this->pdo->prepare("
            UPDATE nodes
            SET api_key_encrypted = :enc {$clearSql}, updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            'enc' => $encrypted,
            'id' => $nodeId,
        ]);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Exception('Node credential encryption failed');
        }
        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $payload): string
    {
        $data = base64_decode($payload, true);
        if ($data === false) {
            throw new Exception('Invalid encrypted node token payload');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new Exception('Node credential decryption failed');
        }

        return $decrypted;
    }

    private function hasEncryptedColumn(): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM nodes LIKE 'api_key_encrypted'");
            return (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'nodes'
              AND column_name = 'api_key_encrypted'
            LIMIT 1
        ");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }

    private function resolveEncryptionKey(): string
    {
        $key = getenv('APP_ENCRYPTION_KEY') ?: '';
        if ($key === '') {
            $appKey = getenv('APP_KEY') ?: '';
            if ($appKey === '') {
                throw new Exception('APP_ENCRYPTION_KEY or APP_KEY is required for node credential encryption');
            }
            return hash('sha256', $appKey, true);
        }

        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return hash('sha256', $key, true);
    }
}
