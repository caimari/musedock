<?php

namespace Modules\InstagramGallery\Models;

use PDO;
use Exception;

class InstagramPost
{
    private static ?PDO $pdo = null;

    public int $id;
    public int $connection_id;
    public string $instagram_id;
    public string $media_type; // IMAGE, VIDEO, CAROUSEL_ALBUM
    public ?string $media_url;
    public ?string $thumbnail_url;
    public string $permalink;
    public ?string $caption;
    public ?string $timestamp;
    public ?int $like_count;
    public ?int $comments_count;
    public int $is_active;
    public ?string $cached_at;
    public ?string $created_at;
    public ?string $updated_at;

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    private static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            throw new Exception('PDO instance not set. Call setPdo() first.');
        }
        return self::$pdo;
    }

    /**
     * Find post by ID
     */
    public static function find(int $id): ?self
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM instagram_posts WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return self::hydrate($data);
    }

    /**
     * Find post by Instagram ID
     */
    public static function findByInstagramId(string $instagramId): ?self
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('SELECT * FROM instagram_posts WHERE instagram_id = ?');
        $stmt->execute([$instagramId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? self::hydrate($data) : null;
    }

    /**
     * Get posts by connection
     */
    public static function getByConnection(int $connectionId, ?int $limit = null, bool $activeOnly = false): array
    {
        $pdo = self::getPdo();

        $sql = 'SELECT * FROM instagram_posts WHERE connection_id = ?';

        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY timestamp DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$connectionId]);

        $posts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $posts[] = self::hydrate($row);
        }

        return $posts;
    }

    /**
     * Get posts by tenant (through connection)
     */
    public static function getByTenant(?int $tenantId = null, ?int $limit = null, bool $activeOnly = false): array
    {
        $pdo = self::getPdo();

        $sql = '
            SELECT p.* FROM instagram_posts p
            INNER JOIN instagram_connections c ON p.connection_id = c.id
            WHERE 1=1
        ';

        $params = [];

        if ($tenantId !== null) {
            $sql .= ' AND (c.tenant_id = ? OR c.tenant_id IS NULL OR c.tenant_id = 0)';
            $params[] = $tenantId;
        } else {
            $sql .= ' AND (c.tenant_id IS NULL OR c.tenant_id = 0)';
        }

        if ($activeOnly) {
            $sql .= ' AND p.is_active = 1 AND c.is_active = 1';
        }

        $sql .= ' ORDER BY p.timestamp DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $posts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $posts[] = self::hydrate($row);
        }

        return $posts;
    }

    /**
     * Create new post or update if exists
     */
    public static function createOrUpdate(array $data): self
    {
        $existing = self::findByInstagramId($data['instagram_id']);

        if ($existing) {
            $existing->update($data);
            return $existing;
        }

        return self::create($data);
    }

    /**
     * Create new post
     */
    public static function create(array $data): self
    {
        $pdo = self::getPdo();

        $stmt = $pdo->prepare('
            INSERT INTO instagram_posts (
                connection_id, instagram_id, media_type, media_url,
                thumbnail_url, permalink, caption, timestamp,
                like_count, comments_count, is_active, cached_at,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
        ');

        $stmt->execute([
            $data['connection_id'],
            $data['instagram_id'],
            $data['media_type'],
            $data['media_url'] ?? null,
            $data['thumbnail_url'] ?? null,
            $data['permalink'],
            $data['caption'] ?? null,
            $data['timestamp'] ?? null,
            $data['like_count'] ?? null,
            $data['comments_count'] ?? null,
            $data['is_active'] ?? 1
        ]);

        $id = (int) $pdo->lastInsertId();
        return self::find($id);
    }

    /**
     * Update post
     */
    public function update(array $data): bool
    {
        $pdo = self::getPdo();

        $fields = [];
        $values = [];

        $allowedFields = [
            'media_type', 'media_url', 'thumbnail_url', 'permalink',
            'caption', 'timestamp', 'like_count', 'comments_count', 'is_active'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'cached_at = NOW()';
        $fields[] = 'updated_at = NOW()';
        $values[] = $this->id;

        $sql = 'UPDATE instagram_posts SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);

        $result = $stmt->execute($values);

        if ($result) {
            // Reload data
            $updated = self::find($this->id);
            if ($updated) {
                foreach (get_object_vars($updated) as $key => $value) {
                    $this->$key = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Delete post
     */
    public function delete(): bool
    {
        $pdo = self::getPdo();
        $stmt = $pdo->prepare('DELETE FROM instagram_posts WHERE id = ?');
        return $stmt->execute([$this->id]);
    }

    /**
     * Delete old posts for a connection (keep most recent N)
     */
    public static function pruneOldPosts(int $connectionId, int $keepCount = 50): int
    {
        $pdo = self::getPdo();

        // Get IDs to keep
        $stmt = $pdo->prepare('
            SELECT id FROM instagram_posts
            WHERE connection_id = ?
            ORDER BY timestamp DESC
            LIMIT ?
        ');
        $stmt->execute([$connectionId, $keepCount]);
        $keepIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($keepIds)) {
            return 0;
        }

        // Delete posts not in the keep list
        $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
        $stmt = $pdo->prepare("
            DELETE FROM instagram_posts
            WHERE connection_id = ?
            AND id NOT IN ($placeholders)
        ");

        $params = array_merge([$connectionId], $keepIds);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Get connection
     */
    public function connection(): ?InstagramConnection
    {
        return InstagramConnection::find($this->connection_id);
    }

    /**
     * Check if post is video
     */
    public function isVideo(): bool
    {
        return $this->media_type === 'VIDEO';
    }

    /**
     * Check if post is carousel
     */
    public function isCarousel(): bool
    {
        return $this->media_type === 'CAROUSEL_ALBUM';
    }

    /**
     * Get display URL (thumbnail for videos, media_url for images)
     */
    public function getDisplayUrl(): ?string
    {
        if ($this->isVideo() && $this->thumbnail_url) {
            return $this->thumbnail_url;
        }
        return $this->media_url;
    }

    /**
     * Get formatted caption (truncated if needed)
     */
    public function getFormattedCaption(int $maxLength = 150): ?string
    {
        if (!$this->caption) {
            return null;
        }

        if (mb_strlen($this->caption) <= $maxLength) {
            return $this->caption;
        }

        return mb_substr($this->caption, 0, $maxLength) . '...';
    }

    /**
     * Get time ago string
     */
    public function getTimeAgo(): string
    {
        if (!$this->timestamp) {
            return '';
        }

        $time = strtotime($this->timestamp);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'hace un momento';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return "hace {$mins} minuto" . ($mins > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "hace {$hours} hora" . ($hours > 1 ? 's' : '');
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return "hace {$days} día" . ($days > 1 ? 's' : '');
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return "hace {$months} mes" . ($months > 1 ? 'es' : '');
        } else {
            $years = floor($diff / 31536000);
            return "hace {$years} año" . ($years > 1 ? 's' : '');
        }
    }

    /**
     * Generate HTML for display
     */
    public function toHtml(array $options = []): string
    {
        $url = $this->getDisplayUrl();
        $caption = $this->getFormattedCaption($options['caption_length'] ?? 150);
        $showCaption = $options['show_caption'] ?? true;
        $lazyLoad = $options['lazy_load'] ?? true;

        $html = '<div class="instagram-post" data-id="' . $this->id . '">';
        $html .= '<a href="' . htmlspecialchars($this->permalink) . '" target="_blank" rel="noopener noreferrer">';

        if ($url) {
            $html .= '<img src="' . htmlspecialchars($url) . '" ';
            $html .= 'alt="' . htmlspecialchars($caption ?? 'Instagram post') . '" ';
            if ($lazyLoad) {
                $html .= 'loading="lazy" ';
            }
            $html .= 'class="instagram-post-image">';
        }

        if ($this->isVideo()) {
            $html .= '<div class="instagram-video-indicator"><i class="bi bi-play-circle"></i></div>';
        }

        if ($this->isCarousel()) {
            $html .= '<div class="instagram-carousel-indicator"><i class="bi bi-collection"></i></div>';
        }

        $html .= '</a>';

        if ($showCaption && $caption) {
            $html .= '<div class="instagram-post-caption">' . htmlspecialchars($caption) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Hydrate object from database row
     */
    private static function hydrate(array $data): self
    {
        $instance = new self();
        $instance->id = (int) $data['id'];
        $instance->connection_id = (int) $data['connection_id'];
        $instance->instagram_id = $data['instagram_id'];
        $instance->media_type = $data['media_type'];
        $instance->media_url = $data['media_url'];
        $instance->thumbnail_url = $data['thumbnail_url'];
        $instance->permalink = $data['permalink'];
        $instance->caption = $data['caption'];
        $instance->timestamp = $data['timestamp'];
        $instance->like_count = $data['like_count'] !== null ? (int) $data['like_count'] : null;
        $instance->comments_count = $data['comments_count'] !== null ? (int) $data['comments_count'] : null;
        $instance->is_active = (int) $data['is_active'];
        $instance->cached_at = $data['cached_at'];
        $instance->created_at = $data['created_at'];
        $instance->updated_at = $data['updated_at'];

        return $instance;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'connection_id' => $this->connection_id,
            'instagram_id' => $this->instagram_id,
            'media_type' => $this->media_type,
            'media_url' => $this->media_url,
            'thumbnail_url' => $this->thumbnail_url,
            'permalink' => $this->permalink,
            'caption' => $this->caption,
            'timestamp' => $this->timestamp,
            'like_count' => $this->like_count,
            'comments_count' => $this->comments_count,
            'is_active' => $this->is_active,
            'is_video' => $this->isVideo(),
            'is_carousel' => $this->isCarousel(),
            'time_ago' => $this->getTimeAgo(),
            'cached_at' => $this->cached_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
