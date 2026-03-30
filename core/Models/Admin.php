<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\QueryBuilder;
use Screenart\Musedock\Database\Model;

class Admin extends Model
{
    protected static string $table = 'admins';

    protected array $fillable = [
        'tenant_id',
        'email',
        'password',
        'name',
        'avatar',
        'author_slug',
        'bio',
        'social_twitter',
        'social_linkedin',
        'social_github',
        'social_website',
        'author_page_enabled',
        'created_at',
        'updated_at',
    ];

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class, (new static)->getTable());
    }

    public static function where($column, $operator = null, $value = null, $boolean = 'and'): QueryBuilder
    {
        return static::query()->where($column, $operator, $value, $boolean);
    }

    public static function findByEmail(string $email, int $tenantId): ?self
    {
        return static::where('email', $email)
                     ->where('tenant_id', $tenantId)
                     ->first();
    }

    /**
     * Get public author page URL (null if disabled)
     */
    public function getAuthorUrl(): ?string
    {
        if (!$this->author_page_enabled || empty($this->author_slug)) {
            return null;
        }
        return function_exists('blog_url') ? blog_url($this->author_slug, 'author') : null;
    }

    /**
     * Generate a unique author slug from name within the tenant
     */
    public static function generateSlug(string $name, ?int $tenantId): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s-]/u', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'author';
        }

        $pdo = \Screenart\Musedock\Database::connect();
        $baseSlug = $slug;
        $counter = 0;

        while (true) {
            $candidate = $counter === 0 ? $baseSlug : $baseSlug . '-' . $counter;
            if ($tenantId !== null) {
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE author_slug = ? AND tenant_id = ?");
                $stmt->execute([$candidate, $tenantId]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM admins WHERE author_slug = ? AND tenant_id IS NULL");
                $stmt->execute([$candidate]);
            }
            if (!$stmt->fetch()) {
                return $candidate;
            }
            $counter++;
        }
    }
}
