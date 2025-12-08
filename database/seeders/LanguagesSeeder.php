<?php

namespace Screenart\Musedock\Database\Seeders;

use Screenart\Musedock\Database;

/**
 * Seeder para idiomas del sistema
 */
class LanguagesSeeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function run(): void
    {
        $languages = [
            [
                'code' => 'es',
                'name' => 'Español',
                'active' => 1,
                'tenant_id' => null
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'active' => 1,
                'tenant_id' => null
            ],
        ];

        foreach ($languages as $language) {
            $this->insertIfNotExists($language);
        }
    }

    private function insertIfNotExists(array $data): void
    {
        // Check if language with this code exists (for global languages where tenant_id is null)
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM languages WHERE code = ? AND tenant_id IS NULL");
        $stmt->execute([$data['code']]);

        if ($stmt->fetchColumn() == 0) {
            $columns = implode(', ', array_keys($data));
            $placeholders = [];
            $values = [];

            foreach ($data as $key => $value) {
                if ($value === null) {
                    $placeholders[] = 'NULL';
                } else {
                    $placeholders[] = '?';
                    $values[] = $value;
                }
            }

            $placeholdersStr = implode(', ', $placeholders);
            $stmt = $this->db->prepare("INSERT INTO languages ({$columns}) VALUES ({$placeholdersStr})");
            $stmt->execute($values);
            echo "    + Idioma '{$data['name']}' ({$data['code']}) creado\n";
        } else {
            echo "    = Idioma '{$data['name']}' ({$data['code']}) ya existe\n";
        }
    }
}
