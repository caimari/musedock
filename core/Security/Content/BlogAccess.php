<?php
namespace Screenart\Musedock\Security\Content;

use Screenart\Musedock\Database;

final class BlogAccess
{
    public static function authorize(string $permission, mixed $id = null, bool $write = false): void
    {
        $tenant = (int)(tenant_id() ?? 0);
        $actor = Access::actor($tenant);
        $allowed = userCan($permission) || ($permission === 'blog.edit' && (userCan('blog.edit.own') || userCan('blog.edit.all')));
        if (!$allowed) Access::deny();
        if ($write) Access::csrf();
        if ($id === null) return;
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) Access::deny(404);
        $q = Database::connect()->prepare('SELECT user_id,user_type FROM blog_posts WHERE id=? AND tenant_id=?');
        $q->execute([(int)$id, $tenant]);
        $row = $q->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (self::ownOnly() && ((int)$row['user_id'] !== (int)$actor['id'] || $row['user_type'] !== 'admin'))) Access::deny(404);
    }

    public static function scope($query): void
    {
        $actor = Access::actor((int)(tenant_id() ?? 0));
        if (self::ownOnly()) $query->where('user_id', (int)$actor['id'])->where('user_type', 'admin');
    }

    /** Legacy broad grants keep their scope; only the new explicit own grant narrows access. */
    public static function ownOnly(): bool
    {
        return userCan('blog.edit.own') && !userCan('blog.edit') && !userCan('blog.edit.all');
    }

    /** External contributors reuse the CMS sanitizer; trusted legacy editors retain their behavior. */
    public static function sanitize(array $data): array
    {
        if (!self::ownOnly()) return $data;
        foreach (['content','excerpt','hero_content'] as $key) {
            if (isset($data[$key])) {
                if(!is_string($data[$key]))Access::deny(422);
                $data[$key]=(string)new \Screenart\Musedock\SafeHtml($data[$key]);
            }
        }
        foreach (['featured_image','hero_image','seo_image','twitter_image','canonical_url'] as $key) {
            if(!isset($data[$key]) || $data[$key]==='')continue;
            if(!is_string($data[$key]) || (!preg_match('#^/(?!/)#',$data[$key]) && (!filter_var($data[$key],FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($data[$key],PHP_URL_SCHEME)??''),['http','https'],true))))Access::deny(422);
        }
        return $data;
    }

    /** Reject cross-tenant taxonomy references before a post or uploaded file is written. */
    public static function terms(): void
    {
        foreach (['categories'=>'blog_categories','tags'=>'blog_tags'] as $key=>$table) {
            $ids=$_POST[$key]??[];
            if(!is_array($ids)||count($ids)>100)Access::deny();
            foreach($ids as $id){
                if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)Access::deny();
                $q=Database::connect()->prepare('SELECT id FROM '.$table.' WHERE id=? AND tenant_id=?');$q->execute([(int)$id,(int)tenant_id()]);if(!$q->fetchColumn())Access::deny(404);
            }
        }
    }

    /** Validate the entire batch before its first mutation. */
    public static function bulk(): void
    {
        $action = $_POST['action'] ?? '';
        if (!is_string($action) || !in_array($action, ['delete','draft','published','public','private','password'], true)) Access::deny();
        $permission = $action === 'delete' ? 'blog.delete' : 'blog.edit';
        self::authorize($permission, null, true);
        $ids = $_POST['selected'] ?? [];
        if (!is_array($ids) || !$ids || count($ids) > 100) Access::deny();
        foreach ($ids as $id) self::authorize($permission, $id);
    }
}
