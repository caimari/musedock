<?php
namespace MediaManager\Controllers;
/** In-memory fixture adapter. Does not write any image to deployed storage. */
class MediaController
{
    public function uploadFromFile(string $path,string $mime,?int $site,array $options): array
    {
        if (($GLOBALS['tenant']['id']??null)!==$site || ($_SESSION['tenant_id']??null)!==$site) throw new \RuntimeException('Wrong upload context');
        if (!is_file($path) || mime_content_type($path)!==$mime) throw new \RuntimeException('Wrong file');
        $db=\Screenart\Musedock\Database::connect();$pg=$db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='pgsql';
        $s=$db->prepare('INSERT INTO media(tenant_id,filename,mime_type,public_token,slug,seo_filename) VALUES(?,?,?,?,?,?)'.($pg?' RETURNING id':''));$s->execute([$site,$options['original_name'],$mime,'uploaded-token','uploaded','uploaded.png']);
        $id=$pg?(int)$s->fetchColumn():(int)$db->lastInsertId();return ['id'=>$id,'url'=>'/media/p/uploaded-uploaded-token/png'];
    }
}
