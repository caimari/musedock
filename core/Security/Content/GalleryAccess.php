<?php
namespace Screenart\Musedock\Security\Content;
use Screenart\Musedock\Database;

final class GalleryAccess
{
    public static function authorize(string $action, mixed $id = null, bool $write = false, bool $image = false): void
    {
        $tenant=(int)(tenant_id()??0);$actor=Access::actor($tenant);
        $broad=userCan('image_gallery.'.$action) || userCan('image_gallery.manage');
        if (!$broad && !userCan('image_gallery.'.$action.'.own')) Access::deny();
        if ($write) Access::csrf();
        if ($id === null) return;
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<1) Access::deny(404);
        // Select * keeps legacy managers compatible before the optional ownership migration.
        $sql=$image ? 'SELECT g.* FROM image_galleries g JOIN gallery_images i ON i.gallery_id=g.id WHERE i.id=? AND g.tenant_id=?' : 'SELECT * FROM image_galleries WHERE id=? AND tenant_id=?';
        $q=Database::connect()->prepare($sql);$q->execute([(int)$id,$tenant]);$row=$q->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (!$broad && (int)($row['owner_admin_id']??0)!==(int)$actor['id'])) Access::deny(404);
    }

    public static function imageInput(array $input): array
    {
        foreach(['title','alt_text','caption','link_url','link_target'] as $key){
            if(!isset($input[$key]))continue;
            if(!is_string($input[$key]) || strlen($input[$key])>2000)Access::deny(422);
        }
        $url=$input['link_url']??'';
        if($url!=='' && (!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['http','https'],true)))Access::deny(422);
        if(isset($input['link_target']) && !in_array($input['link_target'],['_self','_blank'],true))Access::deny(422);
        return $input;
    }

    public static function filter(array $galleries): array
    {
        if (userCan('image_gallery.view') || userCan('image_gallery.manage')) return $galleries;
        $actor=Access::actor((int)(tenant_id()??0));
        return array_values(array_filter($galleries,static fn($g)=>(int)($g->owner_admin_id??0)===(int)$actor['id']));
    }

    public static function ownership(): array
    {
        $actor=Access::actor((int)tenant_id());
        if($actor['type']!=='admin')return [];
        $db=Database::connect();
        // Broad legacy users can continue creating galleries before this migration is approved.
        $driver=$db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $q=$db->prepare($driver==='pgsql' ? "SELECT 1 FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='image_galleries' AND column_name='owner_admin_id'" : "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='image_galleries' AND column_name='owner_admin_id'");
        $q->execute();
        if (!$q->fetchColumn()) {
            if (!userCan('image_gallery.create') && !userCan('image_gallery.manage')) Access::deny();
            return [];
        }
        return ['owner_admin_id'=>(int)Access::actor((int)tenant_id())['id']];
    }
}
