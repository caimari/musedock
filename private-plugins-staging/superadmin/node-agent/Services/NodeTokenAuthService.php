<?php

namespace NodeAgent\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Env;
use PDO;

class NodeTokenAuthService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connect();
    }

    public function authenticate(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        if ($this->tableExists()) {
            $hash = hash('sha256', $token);
            $stmt = $this->pdo->prepare("
                SELECT id
                FROM node_agent_tokens
                WHERE token_hash = :hash
                  AND enabled = 1
                LIMIT 1
            ");
            $stmt->execute(['hash' => $hash]);
            $id = $stmt->fetchColumn();
            if ($id) {
                $this->touch((int) $id);
                return true;
            }
        }

        // Fallback legacy: token en .env
        $envToken = trim((string) Env::get('NODE_AGENT_MASTER_TOKEN', ''));
        return $envToken !== '' && hash_equals($envToken, $token);
    }

    public function bootstrapFromEnvIfNeeded(): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM node_agent_tokens")->fetchColumn();
        if ($count > 0) {
            return;
        }

        $envToken = trim((string) Env::get('NODE_AGENT_MASTER_TOKEN', ''));
        if ($envToken === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO node_agent_tokens (name, token_hash, enabled, created_at, updated_at)
            VALUES (:name, :hash, 1, NOW(), NOW())
        ");
        $stmt->execute([
            'name' => 'bootstrap-from-env',
            'hash' => hash('sha256', $envToken),
        ]);
    }

    private function touch(int $id): void
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE node_agent_tokens SET last_used_at = NOW(), updated_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $id]);
        } catch (\Throwable $e) {
        }
    }

    private function tableExists(): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'node_agent_tokens'");
            return (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        }

        $stmt = $this->pdo->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = 'node_agent_tokens'
            LIMIT 1
        ");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
