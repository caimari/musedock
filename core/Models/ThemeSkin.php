<?php
namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;

class ThemeSkin extends Model
{
    protected static string $table = 'theme_skins';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'slug',
        'name',
        'description',
        'author',
        'version',
        'theme_slug',
        'screenshot',
        'options',
        'is_global',
        'tenant_id',
        'is_active',
        'install_count',
    ];

    protected array $casts = [
        'tenant_id' => 'nullable|integer',
        'options' => 'json',
        'is_global' => 'boolean',
        'is_active' => 'boolean',
        'install_count' => 'integer',
    ];

    /**
     * Get all available skins for a tenant (global + own uploaded skins).
     */
    public static function getAvailableSkins(string $themeSlug, int $tenantId): array
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $t = self::boolTrue($pdo);

        $stmt = $pdo->prepare("
            SELECT * FROM theme_skins
            WHERE theme_slug = :theme_slug
              AND is_active = {$t}
              AND (is_global = {$t} OR tenant_id = :tenant_id)
            ORDER BY is_global DESC, name ASC
        ");
        $stmt->execute([
            'theme_slug' => $themeSlug,
            'tenant_id' => $tenantId
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single skin by slug.
     */
    public static function getBySlug(string $slug, ?int $tenantId = null): ?array
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $boolTrue = self::boolTrue($pdo);

        if ($tenantId !== null) {
            $stmt = $pdo->prepare("
                SELECT * FROM theme_skins
                WHERE slug = :slug AND (tenant_id = :tenant_id OR (is_global = {$boolTrue} AND tenant_id IS NULL))
                ORDER BY tenant_id DESC NULLS LAST
                LIMIT 1
            ");
            $stmt->execute(['slug' => $slug, 'tenant_id' => $tenantId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM theme_skins WHERE slug = :slug AND tenant_id IS NULL LIMIT 1
            ");
            $stmt->execute(['slug' => $slug]);
        }

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create or update a skin.
     */
    public static function saveSkin(array $data): bool
    {
        $pdo = \Screenart\Musedock\Database::connect();

        // Check if exists
        if (isset($data['tenant_id']) && $data['tenant_id'] !== null) {
            $stmt = $pdo->prepare("SELECT id FROM theme_skins WHERE slug = :slug AND tenant_id = :tenant_id");
            $stmt->execute(['slug' => $data['slug'], 'tenant_id' => $data['tenant_id']]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM theme_skins WHERE slug = :slug AND tenant_id IS NULL");
            $stmt->execute(['slug' => $data['slug']]);
        }
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE theme_skins SET
                    name = :name,
                    description = :description,
                    author = :author,
                    version = :version,
                    theme_slug = :theme_slug,
                    screenshot = :screenshot,
                    options = :options,
                    is_global = :is_global,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'author' => $data['author'] ?? 'MuseDock',
                'version' => $data['version'] ?? '1.0',
                'theme_slug' => $data['theme_slug'] ?? 'default',
                'screenshot' => $data['screenshot'] ?? null,
                'options' => is_string($data['options']) ? $data['options'] : json_encode($data['options'], JSON_UNESCAPED_UNICODE),
                'is_global' => self::castBool($data['is_global'] ?? 1, $pdo),
                'is_active' => self::castBool($data['is_active'] ?? 1, $pdo),
                'id' => $existing['id']
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO theme_skins (slug, name, description, author, version, theme_slug, screenshot, options, is_global, tenant_id, is_active, created_at, updated_at)
            VALUES (:slug, :name, :description, :author, :version, :theme_slug, :screenshot, :options, :is_global, :tenant_id, :is_active, NOW(), NOW())
        ");
        return $stmt->execute([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'author' => $data['author'] ?? 'MuseDock',
            'version' => $data['version'] ?? '1.0',
            'theme_slug' => $data['theme_slug'] ?? 'default',
            'screenshot' => $data['screenshot'] ?? null,
            'options' => is_string($data['options']) ? $data['options'] : json_encode($data['options'], JSON_UNESCAPED_UNICODE),
            'is_global' => self::castBool($data['is_global'] ?? 1, $pdo),
            'tenant_id' => $data['tenant_id'] ?? null,
            'is_active' => self::castBool($data['is_active'] ?? 1, $pdo),
        ]);
    }

    /**
     * Increment the install counter.
     */
    public static function incrementInstallCount(int $skinId): void
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $pdo->prepare("UPDATE theme_skins SET install_count = install_count + 1 WHERE id = :id")
            ->execute(['id' => $skinId]);
    }

    /**
     * Delete a skin (only tenant-owned, not global).
     */
    public static function deleteSkin(string $slug, int $tenantId): bool
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $stmt = $pdo->prepare("DELETE FROM theme_skins WHERE slug = :slug AND tenant_id = :tenant_id");
        return $stmt->execute(['slug' => $slug, 'tenant_id' => $tenantId]);
    }

    /**
     * Validate a skin JSON structure.
     * Returns ['valid' => bool, 'errors' => array]
     */
    public static function validateSkinData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'El skin debe tener un nombre';
        }
        if (empty($data['options']) || !is_array($data['options'])) {
            $errors[] = 'El skin debe tener opciones de personalización';
        }

        // Validate that options only contain safe values (no scripts)
        if (!empty($data['options'])) {
            $optionsJson = json_encode($data['options']);
            $dangerousPatterns = [
                '/<script/i',
                '/javascript:/i',
                '/on\w+\s*=/i',
                '/eval\s*\(/i',
                '/document\.\w+/i',
                '/window\.\w+/i',
            ];
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $optionsJson)) {
                    $errors[] = 'El skin contiene código potencialmente peligroso';
                    break;
                }
            }

            // Sanitize custom CSS if present
            $customCss = $data['options']['custom_code']['custom_css'] ?? '';
            if (!empty($customCss)) {
                $cssPatterns = [
                    '/@import/i',
                    '/expression\s*\(/i',
                    '/javascript:/i',
                    '/behavior\s*:/i',
                    '/-moz-binding/i',
                ];
                foreach ($cssPatterns as $pattern) {
                    if (preg_match($pattern, $customCss)) {
                        $errors[] = 'El CSS del skin contiene patrones no permitidos';
                        break;
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate a unique slug from a name.
     */
    public static function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'skin-' . uniqid();
    }

    /**
     * Returns the appropriate boolean TRUE literal for the current DB driver.
     */
    private static function boolTrue(\PDO $pdo): string
    {
        return $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'TRUE' : '1';
    }

    /**
     * Cast a PHP boolean/int value for the current DB driver (for bind params).
     */
    private static function castBool($value, \PDO $pdo)
    {
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            return $value ? 't' : 'f';
        }
        return $value ? 1 : 0;
    }
}
