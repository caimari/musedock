<?php
namespace Screenart\Musedock\Services\Mcp;

use Screenart\Musedock\Models\ApiKey;
use Screenart\Musedock\Database;

/** Revalidate legacy API-origin jobs at execution, not merely at enqueue time. */
final class CrossPublisherQueueGuard
{
    public static function assertAllowed(object $item): void
    {
        if (empty($item->origin_api_key_id)) return; // Existing private-panel jobs have no API principal.
        $key=ApiKey::find((int)$item->origin_api_key_id);
        if (!$key || !$key->is_active || $key->isExpired() || !$key->hasPermission('cross-publish')
            || !$key->canAccessTenant((int)$item->source_tenant_id) || !$key->canAccessTenant((int)$item->target_tenant_id)) {
            throw new \RuntimeException('API authorization revoked or sites outside its current scope.');
        }
        if (($item->target_status??'draft')==='published' && !$key->hasPermission('posts.publish')) throw new \RuntimeException('Publication permission revoked.');
        $s=Database::connect()->prepare('SELECT id FROM blog_posts WHERE id=? AND tenant_id=? AND deleted_at IS NULL');
        $s->execute([(int)$item->source_post_id,(int)$item->source_tenant_id]);
        if (!$s->fetchColumn()) throw new \RuntimeException('Source post no longer belongs to the authorized site.');
    }
}
