<?php

namespace Modules\SearchConsole\Services;

use PDO;
use RuntimeException;
use Screenart\Musedock\Database;

/**
 * Cuentas de servicio de Google guardadas cifradas.
 *
 * - Plataforma: owner_type 'platform', tenant_id NULL (una sola).
 * - Tenant: owner_type 'tenant', tenant_id del dueño (una por tenant).
 * El JSON descifrado solo existe en memoria al crear el GoogleClient; nunca se
 * devuelve a una vista (las vistas reciben publicView()).
 */
class CredentialStore
{
    private const MAX_JSON_BYTES = 20000;

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connect();
    }

    public function find(?int $tenantId): ?array
    {
        if ($tenantId === null) {
            $stmt = $this->pdo->query("SELECT * FROM gsc_credentials WHERE owner_type = 'platform' AND tenant_id IS NULL ORDER BY id LIMIT 1");
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM gsc_credentials WHERE owner_type = 'tenant' AND tenant_id = ? ORDER BY id LIMIT 1");
            $stmt->execute([$tenantId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gsc_credentials WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Datos que sí se pueden enseñar en pantalla. */
    public static function publicView(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'client_email' => $row['client_email'],
            'project_id' => $row['project_id'],
            'last_test_at' => $row['last_test_at'],
            'last_test_ok' => $row['last_test_ok'] === null ? null : (int) $row['last_test_ok'] === 1,
            'last_error' => $row['last_error'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * Valida, prueba contra Google y guarda (cifrada) la clave JSON.
     * Si la prueba falla no se guarda nada.
     */
    public function save(?int $tenantId, string $json, string $savedBy): array
    {
        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new RuntimeException('El fichero es demasiado grande para ser una clave de cuenta de servicio.');
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('El fichero no es un JSON válido.');
        }
        GoogleClient::assertServiceAccount($data);

        $client = new GoogleClient($data);
        $client->request('GET', 'https://www.googleapis.com/webmasters/v3/sites'); // lanza si no autentica

        $owner = $tenantId === null ? 'platform' : 'tenant';
        $encrypted = Crypto::encrypt(json_encode($data, JSON_UNESCAPED_SLASHES), Crypto::scope($tenantId));
        $existing = $this->find($tenantId);

        if ($existing && $existing['client_email'] !== $data['client_email']) {
            // Otra cuenta de servicio: las propiedades de la anterior ya no valen
            $this->deleteProperties((int) $existing['id']);
        }
        if ($existing) {
            $this->pdo->prepare('UPDATE gsc_credentials SET client_email = ?, project_id = ?, key_encrypted = ?, last_test_at = NOW(), last_test_ok = 1, last_error = NULL, created_by = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$data['client_email'], $data['project_id'] ?? null, $encrypted, $savedBy, $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $this->pdo->prepare('INSERT INTO gsc_credentials (owner_type, tenant_id, client_email, project_id, key_encrypted, last_test_at, last_test_ok, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), 1, ?, NOW(), NOW())')
                ->execute([$owner, $tenantId, $data['client_email'], $data['project_id'] ?? null, $encrypted, $savedBy]);
            $id = (int) $this->pdo->lastInsertId();
        }

        return $this->findById($id);
    }

    public function client(array $row): GoogleClient
    {
        $tenantId = $row['owner_type'] === 'tenant' ? (int) $row['tenant_id'] : null;
        $json = Crypto::decrypt((string) $row['key_encrypted'], Crypto::scope($tenantId));

        return new GoogleClient(json_decode($json, true) ?? []);
    }

    public function markTest(int $id, bool $ok, ?string $error = null): void
    {
        $this->pdo->prepare('UPDATE gsc_credentials SET last_test_at = NOW(), last_test_ok = ?, last_error = ? WHERE id = ?')
            ->execute([$ok ? 1 : 0, $error === null ? null : mb_substr($error, 0, 500), $id]);
    }

    /** Borra la credencial, sus propiedades y todos sus datos. */
    public function delete(int $id): void
    {
        $this->deleteProperties($id);
        $this->pdo->prepare('DELETE FROM gsc_credentials WHERE id = ?')->execute([$id]);
    }

    private function deleteProperties(int $credentialId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM gsc_properties WHERE credential_id = ?');
        $stmt->execute([$credentialId]);
        $properties = new PropertyService($this->pdo);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $propertyId) {
            $properties->deleteProperty((int) $propertyId);
        }
    }
}
