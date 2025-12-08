<?php

use Screenart\Musedock\Database\Migration;

/**
 * Migration: Add disk field to gallery_images table
 * Allows images to be stored in different storage drives (local, media, r2, s3)
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('gallery_images', 'disk', [
            'type' => 'VARCHAR',
            'length' => 50,
            'null' => false,
            'default' => 'local',
            'after' => 'gallery_id'
        ]);

        // Update existing records to use 'local' disk
        $this->execute("UPDATE gallery_images SET disk = 'local' WHERE disk IS NULL OR disk = ''");
    }

    public function down(): void
    {
        $this->dropColumn('gallery_images', 'disk');
    }
};
