<?php

namespace Blog\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Database;

class BlogComment extends Model
{
    public const MODERATION_MANUAL = 'manual';
    public const MODERATION_TRUSTED_AUTHORS = 'trusted_authors';
    public const MODERATION_AUTO_APPROVE = 'auto_approve';

    protected static string $table = 'blog_comments';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'post_id',
        'author_name',
        'author_email',
        'author_url',
        'content',
        'status',
        'ip_address',
        'user_agent',
        'legal_consent',
        'legal_consent_at',
        'approved_at',
        'approved_by',
        'approved_by_type',
    ];

    protected array $casts = [
        'id' => 'int',
        'tenant_id' => 'nullable',
        'post_id' => 'int',
        'approved_by' => 'int',
        'legal_consent' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'legal_consent_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public static function approvedForPost(int $postId, ?int $tenantId = null, int $limit = 200): array
    {
        $query = self::query()
            ->where('post_id', $postId)
            ->where('status', 'approved')
            ->orderBy('created_at', 'ASC')
            ->limit($limit);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = ?)', [0]);
        }

        return $query->get();
    }

    public static function approvedCountForPost(int $postId): int
    {
        return (int) self::query()
            ->where('post_id', $postId)
            ->where('status', 'approved')
            ->count();
    }

    /**
     * Sistema de comentarios del sitio (ajuste blog_comments_mode):
     *  - on          (por defecto) comentarios activos; las entradas nuevas los traen activados.
     *  - off_default los comentarios siguen disponibles, pero las entradas nuevas los traen apagados; se activan entrada a entrada.
     *  - off         sistema apagado: no se muestran ni se aceptan comentarios en ninguna entrada.
     * Sin argumento usa el sitio actual (tenant o CMS principal); con $tenantId lee el de ese sitio (null/0 = CMS principal).
     */
    public const COMMENTS_ON = 'on';
    public const COMMENTS_OFF_DEFAULT = 'off_default';
    public const COMMENTS_OFF = 'off';

    public static function commentsMode(?int $tenantId = null): string
    {
        try {
            if (func_num_args() === 0) {
                $mode = (string) site_setting('blog_comments_mode', self::COMMENTS_ON);
            } else {
                $pdo = Database::connect();
                $keyCol = Database::qi('key');
                if ($tenantId) {
                    $st = $pdo->prepare("SELECT value FROM tenant_settings WHERE tenant_id = ? AND {$keyCol} = 'blog_comments_mode'");
                    $st->execute([$tenantId]);
                } else {
                    $st = $pdo->prepare("SELECT value FROM settings WHERE {$keyCol} = 'blog_comments_mode'");
                    $st->execute();
                }
                $mode = (string) ($st->fetchColumn() ?: self::COMMENTS_ON);
            }
        } catch (\Throwable $e) {
            return self::COMMENTS_ON;
        }

        return in_array($mode, [self::COMMENTS_ON, self::COMMENTS_OFF_DEFAULT, self::COMMENTS_OFF], true) ? $mode : self::COMMENTS_ON;
    }

    /** ¿El sistema de comentarios del sitio está encendido? (apagado = ninguna entrada los muestra ni los acepta) */
    public static function commentsSystemEnabled(): bool
    {
        return self::commentsMode() !== self::COMMENTS_OFF;
    }

    /** Valor de «Permitir comentarios» para una entrada nueva (1/0) según el ajuste del sitio. */
    public static function defaultAllowForNewPosts(?int $tenantId = null): int
    {
        $mode = func_num_args() === 0 ? self::commentsMode() : self::commentsMode($tenantId);

        return $mode === self::COMMENTS_ON ? 1 : 0;
    }

    public static function moderationMode(): string
    {
        $mode = (string) site_setting('blog_comments_approval_mode', self::MODERATION_TRUSTED_AUTHORS);
        $allowed = [
            self::MODERATION_MANUAL,
            self::MODERATION_TRUSTED_AUTHORS,
            self::MODERATION_AUTO_APPROVE,
        ];

        if (!in_array($mode, $allowed, true)) {
            return self::MODERATION_TRUSTED_AUTHORS;
        }

        return $mode;
    }

    public static function spamLinksThreshold(): int
    {
        $threshold = (int) site_setting('blog_comments_spam_links_threshold', '3');
        if ($threshold < 1) {
            $threshold = 1;
        }
        if ($threshold > 20) {
            $threshold = 20;
        }
        return $threshold;
    }

    public static function hasApprovedCommentFromEmail(string $email, ?int $tenantId = null): bool
    {
        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '') {
            return false;
        }

        $query = self::query()
            ->whereRaw('LOWER(author_email) = LOWER(?)', [$normalizedEmail])
            ->where('status', 'approved');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = ?)', [0]);
        }

        return (int) $query->count() > 0;
    }

    public static function shouldAutoApprove(string $email, ?int $tenantId = null): bool
    {
        $mode = self::moderationMode();

        if ($mode === self::MODERATION_AUTO_APPROVE) {
            return true;
        }

        if ($mode === self::MODERATION_TRUSTED_AUTHORS) {
            return self::hasApprovedCommentFromEmail($email, $tenantId);
        }

        return false;
    }

    public static function recalculatePostCommentCount(int $postId): void
    {
        try {
            $count = self::approvedCountForPost($postId);
            Database::table('blog_posts')
                ->where('id', $postId)
                ->update(['comment_count' => $count]);
        } catch (\Throwable $e) {
            error_log('BlogComment::recalculatePostCommentCount error: ' . $e->getMessage());
        }
    }

    public static function recentSpamCount(?int $tenantId = null, int $hours = 24): int
    {
        $hours = max(1, $hours);
        $since = date('Y-m-d H:i:s', time() - ($hours * 3600));

        $query = self::query()
            ->where('status', 'spam')
            ->where('created_at', '>=', $since);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('(tenant_id IS NULL OR tenant_id = ?)', [0]);
        }

        return (int) $query->count();
    }

    /**
     * CAPTCHA adaptativo:
     * - se activa solo si está habilitado en settings
     * - entra en juego al superar umbral de spam (últimas 24h)
     */
    public static function shouldRequireCaptcha(?int $tenantId = null): bool
    {
        $enabled = (string) site_setting('blog_comments_captcha_enabled', '0');
        if ($enabled !== '1') {
            return false;
        }

        $threshold = (int) site_setting('blog_comments_captcha_spam_threshold', '5');
        if ($threshold < 1) {
            $threshold = 5;
        }

        return self::recentSpamCount($tenantId, 24) >= $threshold;
    }
}
