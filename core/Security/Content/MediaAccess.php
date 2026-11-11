<?php
namespace Screenart\Musedock\Security\Content;
use Screenart\Musedock\Database;

final class MediaAccess
{
    public static function authorize(string $action, ?int $tenant, mixed $id = null, bool $write = false): void
    {
        $actor = Access::actor($tenant);
        $manager = userCan('media.manage');
        $permission = match($action) {'read'=>'media.view.own','upload'=>'media.upload','edit'=>'media.edit.own','delete'=>'media.delete.own',default=>null};
        if (!$manager && (!$permission || !userCan($permission))) Access::deny();
        if ($write) Access::csrf();
        if (!$manager && $action === 'upload' && ((!empty($_POST['disk']) && $_POST['disk'] !== 'media') || !empty($_POST['folder_id']))) Access::deny();
        if (!$manager && $write && $action === 'upload') self::ownership($tenant);
        if ($id === null) return;
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) Access::deny(404);
        $sql = 'SELECT * FROM media WHERE id=? AND '.($tenant === null ? 'tenant_id IS NULL' : 'tenant_id=?');
        $q=Database::connect()->prepare($sql);$q->execute($tenant === null ? [(int)$id] : [(int)$id,$tenant]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if (!$row || (!$manager && (int)($row['owner_admin_id']??0) !== (int)$actor['id'])) Access::deny(404);
    }

    public static function imageFile(array $file): void
    {
        if(!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)Access::deny(422);
        $bytes=filesize($file['tmp_name']);$size=@getimagesize($file['tmp_name']);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!$size||$bytes>5*1024*1024||$size[0]*$size[1]>24000000||!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true))Access::deny(422);
    }

    public static function ownership(?int $tenant): array
    {
        if($tenant===null)return [];
        $actor=Access::actor($tenant);
        if ($actor['type'] !== 'admin') return [];
        $db=Database::connect();
        $scope=$db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='pgsql'?'table_schema=current_schema()':'table_schema=DATABASE()';
        $q=$db->query("SELECT 1 FROM information_schema.columns WHERE $scope AND table_name='media' AND column_name='owner_admin_id'");
        if(!$q->fetchColumn()) {
            if(!userCan('media.manage'))Access::deny(503);
            return [];
        }
        return ['owner_admin_id'=>(int)$actor['id']];
    }

    public static function scope($query, ?int $tenant): void
    {
        $actor=Access::actor($tenant);
        if (!userCan('media.manage')) $query->where('owner_admin_id',(int)$actor['id']);
    }
}
