<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * DatabaseSeeder - Main seeder that orchestrates all seeders
 *
 * Usage: php migrate fresh --seed
 */
class DatabaseSeeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Run all seeders in order
     */
    public function run(): void
    {
        echo "Ejecutando seeders...\n";

        $seeders = [
            RolesAndPermissionsSeeder::class,
            ModulesSeeder::class,
            ThemesSeeder::class,
            SuperadminMenuSeeder::class, // Menús del panel /musedock/
            AdminMenuSeeder::class,      // Menús del panel tenant /admin/
        ];

        foreach ($seeders as $seederClass) {
            $seeder = new $seederClass();
            $className = basename(str_replace('\\', '/', $seederClass));
            echo "  - Ejecutando {$className}...\n";
            $seeder->run();
        }

        echo "Seeders completados.\n";
    }
}
