<?php

namespace CrossPublisherAdmin\Models;

use Screenart\Musedock\Database;
use PDO;

class GlobalSettings
{
    private static array $defaults = [
        'ai_provider_id' => null,
        'auto_translate' => false,
        'default_target_status' => 'draft',
        'include_featured_image' => true,
        'include_categories' => true,
        'include_tags' => true,
        'add_canonical_link' => true,
        'add_source_credit' => true,
        'source_credit_template' => 'Publicado originalmente en <a href="{source_url}">{source_name}</a>',
        'sync_cron_interval' => 15,
        'sync_enabled' => true,
    ];

    public static function get(): array
    {
        $pdo = Database::connect();

        try {
            $stmt = $pdo->query("SELECT * FROM cross_publish_global_settings ORDER BY id ASC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return self::$defaults;
        }

        if (!$row) {
            return self::$defaults;
        }

        // Merge con defaults para campos que puedan ser NULL
        $settings = [];
        foreach (self::$defaults as $key => $default) {
            if (array_key_exists($key, $row)) {
                $value = $row[$key];
                // Convertir booleanos
                if (is_bool($default)) {
                    $settings[$key] = (bool) $value;
                } elseif (is_int($default) && $default !== null) {
                    $settings[$key] = $value !== null ? (int) $value : $default;
                } else {
                    $settings[$key] = $value ?? $default;
                }
            } else {
                $settings[$key] = $default;
            }
        }

        return $settings;
    }

    public static function save(array $data): bool
    {
        $pdo = Database::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Verificar si existe una fila
        $stmt = $pdo->query("SELECT id FROM cross_publish_global_settings LIMIT 1");
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $fields = [
            'ai_provider_id', 'auto_translate', 'default_target_status',
            'include_featured_image', 'include_categories', 'include_tags',
            'add_canonical_link', 'add_source_credit', 'source_credit_template',
            'sync_cron_interval', 'sync_enabled'
        ];

        $boolFields = [
            'auto_translate', 'include_featured_image', 'include_categories',
            'include_tags', 'add_canonical_link', 'add_source_credit', 'sync_enabled'
        ];

        $values = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                if (in_array($field, $boolFields)) {
                    $val = !empty($val) ? 1 : 0;
                }
                $values[$field] = $val;
            }
        }

        if ($existing) {
            $sets = [];
            $params = [];
            foreach ($values as $key => $val) {
                $sets[] = "{$key} = ?";
                $params[] = $val;
            }
            $params[] = $existing['id'];
            $sql = "UPDATE cross_publish_global_settings SET " . implode(', ', $sets) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } else {
            $cols = array_keys($values);
            $placeholders = array_fill(0, count($cols), '?');
            $sql = "INSERT INTO cross_publish_global_settings (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute(array_values($values));
        }
    }
}
