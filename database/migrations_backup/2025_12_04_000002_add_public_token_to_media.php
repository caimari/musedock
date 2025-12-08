<?php

/**
 * Migración: Añadir columna public_token a tabla media
 *
 * Añade un token único aleatorio para cada archivo de media.
 * Esto permite URLs seguras que no exponen el ID secuencial.
 * URL resultante: /media/t/{token} en lugar de /media/id/{id}
 */

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $driver = $this->getDriverName();
        $pdo = $this->getConnection();

        // Verificar si la tabla existe
        if (!$this->tableExists('media')) {
            echo "  → Tabla media no existe, saltando...\n";
            return;
        }

        // Verificar si la columna ya existe
        if ($this->columnExists('media', 'public_token')) {
            echo "  → Columna public_token ya existe en media, saltando...\n";
            return;
        }

        // Añadir columna public_token (16 caracteres alfanuméricos)
        if ($driver === 'pgsql') {
            $this->execute("
                ALTER TABLE media
                ADD COLUMN IF NOT EXISTS public_token VARCHAR(32) NULL UNIQUE
            ");
        } else {
            // MySQL / MariaDB
            $this->execute("
                ALTER TABLE media
                ADD COLUMN public_token VARCHAR(32) NULL AFTER path
            ");
        }

        echo "  ✓ Columna public_token añadida a media\n";

        // Generar tokens únicos para registros existentes
        $stmt = $pdo->query("SELECT id FROM media WHERE public_token IS NULL");
        $mediaIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        $updated = 0;
        $updateStmt = $pdo->prepare("UPDATE media SET public_token = ? WHERE id = ?");

        foreach ($mediaIds as $id) {
            $token = $this->generateUniqueToken($pdo);
            $updateStmt->execute([$token, $id]);
            $updated++;
        }

        if ($updated > 0) {
            echo "  ✓ Tokens generados para {$updated} archivos existentes\n";
        }

        // Crear índice único para el token
        try {
            $this->execute("CREATE UNIQUE INDEX idx_media_public_token ON media (public_token)");
            echo "  ✓ Índice único idx_media_public_token creado\n";
        } catch (\Exception $e) {
            echo "  → Índice idx_media_public_token ya existe o no se pudo crear\n";
        }

        // Hacer la columna NOT NULL después de generar tokens
        if ($driver === 'pgsql') {
            $this->execute("ALTER TABLE media ALTER COLUMN public_token SET NOT NULL");
        } else {
            $this->execute("ALTER TABLE media MODIFY COLUMN public_token VARCHAR(32) NOT NULL");
        }

        echo "  ✓ Columna public_token marcada como NOT NULL\n";
    }

    public function down(): void
    {
        if (!$this->tableExists('media')) {
            return;
        }

        if ($this->columnExists('media', 'public_token')) {
            // Eliminar índice
            try {
                $driver = $this->getDriverName();
                if ($driver === 'pgsql') {
                    $this->execute("DROP INDEX IF EXISTS idx_media_public_token");
                } else {
                    $this->execute("DROP INDEX idx_media_public_token ON media");
                }
            } catch (\Exception $e) {
                // Ignorar si no existe
            }

            // Eliminar columna
            $this->execute("ALTER TABLE media DROP COLUMN public_token");
            echo "  ✓ Columna public_token eliminada de media\n";
        }
    }

    /**
     * Genera un token único de 16 caracteres alfanuméricos
     * 62^16 = ~4.7 x 10^28 combinaciones posibles
     */
    private function generateUniqueToken(\PDO $pdo): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 10;
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM media WHERE public_token = ?");

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $token = '';
            for ($i = 0; $i < 16; $i++) {
                $token .= $chars[random_int(0, 61)];
            }

            // Verificar que no existe
            $checkStmt->execute([$token]);
            $exists = (int) $checkStmt->fetchColumn() > 0;

            if (!$exists) {
                return $token;
            }
        }

        // Fallback: usar más entropía
        return bin2hex(random_bytes(8));
    }

    /**
     * Obtener conexión PDO directamente
     */
    private function getConnection(): \PDO
    {
        return $this->db;
    }
};
