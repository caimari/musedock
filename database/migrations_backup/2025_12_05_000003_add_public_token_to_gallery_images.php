<?php

use Screenart\Musedock\Database\Migration;

/**
 * Migration: Add public_token field to gallery_images table
 * Permite generar URLs públicas seguras para las imágenes
 */
return new class extends Migration
{
    public function up(): void
    {
        // Agregar columna public_token
        $this->execute("ALTER TABLE gallery_images ADD COLUMN public_token VARCHAR(64) NULL AFTER disk");

        // Generar tokens para imágenes existentes
        $this->execute("UPDATE gallery_images SET public_token = SHA2(CONCAT(id, file_path, RAND()), 256) WHERE public_token IS NULL");

        // Crear índice para búsquedas rápidas
        $this->execute("CREATE INDEX idx_public_token ON gallery_images(public_token)");
    }

    public function down(): void
    {
        $this->execute("DROP INDEX idx_public_token ON gallery_images");
        $this->execute("ALTER TABLE gallery_images DROP COLUMN public_token");
    }
};
