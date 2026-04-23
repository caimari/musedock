<?php

namespace FestivalDirectory\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class Festival extends Model
{
    protected static string $table = 'festivals';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'short_description',
        'description',
        'logo',
        'cover_image',
        'featured_image',
        'type',
        'country',
        'city',
        'venue',
        'address',
        'latitude',
        'longitude',
        'edition_number',
        'edition_year',
        'start_date',
        'end_date',
        'deadline_date',
        'frequency',
        'website_url',
        'email',
        'phone',
        'social_facebook',
        'social_instagram',
        'social_twitter',
        'social_youtube',
        'social_vimeo',
        'social_linkedin',
        'submission_filmfreeway_url',
        'submission_festhome_url',
        'submission_festgate_url',
        'submission_other_url',
        'submission_status',
        'status',
        'claimed_by',
        'claimed_at',
        'claim_token',
        'contact_email',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_image',
        'noindex',
        'view_count',
        'featured',
        'sort_order',
        'base_locale',
    ];

    protected array $casts = [
        'id'              => 'int',
        'tenant_id'       => 'int',
        'edition_number'  => 'int',
        'edition_year'    => 'int',
        'view_count'      => 'int',
        'featured'        => 'boolean',
        'noindex'         => 'boolean',
        'sort_order'      => 'int',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * Sync categories (many-to-many via festival_category_pivot).
     */
    public function syncCategories(array $categoryIds): bool
    {
        try {
            $pdo = Database::connect();

            $pdo->prepare("DELETE FROM festival_category_pivot WHERE festival_id = ?")->execute([$this->id]);

            if (!empty($categoryIds)) {
                $stmt = $pdo->prepare("INSERT INTO festival_category_pivot (festival_id, category_id) VALUES (?, ?)");
                foreach ($categoryIds as $catId) {
                    $stmt->execute([$this->id, (int)$catId]);
                    self::updateCategoryCount((int)$catId);
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Festival syncCategories error (ID {$this->id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync tags (many-to-many via festival_tag_pivot).
     */
    public function syncTags(array $tagIds): bool
    {
        try {
            $pdo = Database::connect();

            $pdo->prepare("DELETE FROM festival_tag_pivot WHERE festival_id = ?")->execute([$this->id]);

            if (!empty($tagIds)) {
                $stmt = $pdo->prepare("INSERT INTO festival_tag_pivot (festival_id, tag_id) VALUES (?, ?)");
                foreach ($tagIds as $tagId) {
                    $stmt->execute([$this->id, (int)$tagId]);
                    self::updateTagCount((int)$tagId);
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log("Festival syncTags error (ID {$this->id}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get category IDs for this festival.
     */
    public function getCategoryIds(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT category_id FROM festival_category_pivot WHERE festival_id = ?");
        $stmt->execute([$this->id]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'category_id');
    }

    /**
     * Get tag IDs for this festival.
     */
    public function getTagIds(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT tag_id FROM festival_tag_pivot WHERE festival_id = ?");
        $stmt->execute([$this->id]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'tag_id');
    }

    /**
     * Increment view count (one per IP per session).
     */
    public function incrementViewCount(): void
    {
        $key = 'festival_viewed_' . $this->id;
        if (!empty($_SESSION[$key])) {
            return;
        }
        $_SESSION[$key] = true;

        $pdo = Database::connect();
        $pdo->prepare("UPDATE festivals SET view_count = view_count + 1 WHERE id = ?")->execute([$this->id]);
        $this->view_count = ($this->view_count ?? 0) + 1;
    }

    /**
     * Update festival_count on a category.
     */
    public static function updateCategoryCount(int $categoryId): void
    {
        try {
            $pdo = Database::connect();
            $pdo->prepare("
                UPDATE festival_categories
                SET festival_count = (
                    SELECT COUNT(*) FROM festival_category_pivot WHERE category_id = ?
                )
                WHERE id = ?
            ")->execute([$categoryId, $categoryId]);
        } catch (\Exception $e) {
            error_log("updateCategoryCount error: " . $e->getMessage());
        }
    }

    /**
     * Update festival_count on a tag.
     */
    public static function updateTagCount(int $tagId): void
    {
        try {
            $pdo = Database::connect();
            $pdo->prepare("
                UPDATE festival_tags
                SET festival_count = (
                    SELECT COUNT(*) FROM festival_tag_pivot WHERE tag_id = ?
                )
                WHERE id = ?
            ")->execute([$tagId, $tagId]);
        } catch (\Exception $e) {
            error_log("updateTagCount error: " . $e->getMessage());
        }
    }

    /**
     * Get available festival types.
     */
    public static function getTypes(): array
    {
        return [
            'film_festival'    => 'Festival de Cine',
            'music_festival'   => 'Festival de Música',
            'arts_festival'    => 'Festival de Artes',
            'multidisciplinary'=> 'Multidisciplinar',
            'theater_festival' => 'Festival de Teatro',
            'dance_festival'   => 'Festival de Danza',
            'literary_festival'=> 'Festival Literario',
            'food_festival'    => 'Festival Gastronómico',
            'tech_festival'    => 'Festival de Tecnología',
            'other'            => 'Otro',
        ];
    }

    /**
     * Get available frequencies.
     */
    public static function getFrequencies(): array
    {
        return [
            'annual'    => 'Anual',
            'biannual'  => 'Bianual',
            'quarterly' => 'Trimestral',
            'monthly'   => 'Mensual',
            'biennial'  => 'Bienal',
            'irregular' => 'Irregular',
            'one_time'  => 'Única edición',
        ];
    }

    /**
     * Get submission statuses.
     */
    public static function getSubmissionStatuses(): array
    {
        return [
            'open'     => 'Abierto',
            'closed'   => 'Cerrado',
            'upcoming' => 'Próximamente',
        ];
    }

    /**
     * Get entity statuses.
     */
    public static function getStatuses(): array
    {
        return [
            'draft'     => 'Borrador',
            'published' => 'Publicado',
            'verified'  => 'Verificado',
            'claimed'   => 'Reclamado',
            'suspended' => 'Suspendido',
        ];
    }
}
