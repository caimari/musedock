<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;
use Screenart\Musedock\SafeHtml;
use Screenart\Musedock\Security\UrlGuard;
use Screenart\Musedock\Services\AI\AIImageService;

/** Uses the CMS media library and provider; never the Panel or assistant credentials. */
final class Media
{
    public function __construct(private Content $content) {}
    public function one(int $id,int $site): array
    {
        $row=$this->content->query("SELECT id,tenant_id,filename,mime_type,alt_text,public_token,slug,seo_filename,metadata,created_at FROM media WHERE id=? AND COALESCE(tenant_id,0)=? AND mime_type LIKE 'image/%'",[$id,$site])[0]??null;
        if (!$row) throw new RuntimeException('Imagen no disponible en este sitio.');
        $row['url']=$this->publicUrl($row);
        $metadata=json_decode($row['metadata']??'{}',true)?:[];
        $row['generation']=$metadata['ai_generation']??null;
        $row['resolution']=$metadata['ai_generation']['actual_resolution']??($metadata['original_resolution']??null);
        unset($row['public_token'],$row['metadata']); return $row;
    }
    private function publicUrl(array $row): string
    {
        if (empty($row['public_token'])) throw new RuntimeException('Esta imagen antigua no tiene un enlace público gestionado.');
        if (!empty($row['slug']) && !empty($row['seo_filename'])) return '/media/p/'.rawurlencode($row['slug'].'-'.$row['public_token']).'/'.rawurlencode(pathinfo($row['seo_filename'],PATHINFO_EXTENSION));
        return '/media/t/'.rawurlencode($row['public_token']);
    }
    public function listing(int $site,int $limit,int $offset): array
    {
        $rows=$this->content->query("SELECT id FROM media WHERE COALESCE(tenant_id,0)=? AND mime_type LIKE 'image/%' AND public_token IS NOT NULL AND public_token<>'' ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",[$site]);
        return array_map(fn($r)=>$this->one((int)$r['id'],$site),$rows);
    }
    public function prompts(int $site,int $limit,int $offset): array
    {
        $driver=$this->content->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $needle='%'.chr(34).'ai_generation'.chr(34).'%';
        $filter=match($driver) {
            'pgsql'=>"metadata::text LIKE ?",
            'mysql'=>"CAST(metadata AS CHAR) LIKE ?",
            default=>"metadata LIKE ?",
        };
        $rows=$this->content->query("SELECT id FROM media WHERE COALESCE(tenant_id,0)=? AND public_token IS NOT NULL AND public_token<>'' AND {$filter} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",[$site,$needle]);
        return array_map(fn($row)=>$this->one((int)$row['id'],$site),$rows);
    }
    public function options(int $site): array
    {
        $provider=AIImageService::getDefaultProvider($site?:null);
        if (!$provider) throw new RuntimeException('Configura un proveedor de imágenes en IA → AI Image para este sitio.');
        $model=$provider['model']??null;
        $sizes=match ($provider['provider_type']) {
            'minimax'=>['1024x1024','1280x720','720x1280','1152x864','864x1152','1248x832','832x1248','1344x576','1792x1024','1024x1792','512x512'],
            'openai'=>($model==='dall-e-3'||!$model)?['1024x1024','1792x1024','1024x1792']:['1024x1024'],
            default=>['1024x1024','1792x1024','1024x1792','1536x1024','1024x1536'],
        };
        return ['provider_id'=>(int)$provider['id'],'provider_name'=>$provider['name'],'provider_type'=>$provider['provider_type'],'model'=>$model,'sizes'=>$sizes,
            'qualities'=>$provider['provider_type']==='openai' && $model==='dall-e-3'?['standard','hd']:['standard'],
            'size_mode'=>'pixels',
            'custom_size_constraints'=>$provider['provider_type']==='minimax'?['minimum'=>512,'maximum'=>2048,'multiple_of'=>8]:null,
            'max_prompt_length'=>$provider['provider_type']==='minimax'?1500:4000,
            'note'=>'Opciones compatibles con el adaptador instalado del CMS. MiniMax recibe ancho y alto explícitos. El historial informa de las dimensiones reales de la imagen recibida. Generar una imagen no la asigna al post: usa update_post con featured_image_id.',
            'uses_site_ai_quota'=>true];
    }
    public function plan(string $tool,int $site,array $args): array
    {
        $f=['alt_text'=>SafeHtml::plain($args['alt_text']??'')];
        if ($tool==='generate_image') {
            $provider=AIImageService::getDefaultProvider($site?:null);
            if (!$provider) throw new RuntimeException('Configura un proveedor de imágenes en IA → AI Image para este sitio.');
            $options=$this->options($site);
            $size=$args['size']??'1024x1024';
            $validSize=in_array($size,$options['sizes'],true);
            if ($provider['provider_type']==='minimax') {
                $validSize=(bool)preg_match('/^([0-9]{3,4})x([0-9]{3,4})$/D',$size,$dimensions);
                if ($validSize) foreach ([(int)$dimensions[1],(int)$dimensions[2]] as $n) if ($n<512 || $n>2048 || $n%8!==0) $validSize=false;
            }
            if (!$validSize || !in_array($args['quality']??'standard',$options['qualities'],true)) throw new RuntimeException('Parámetros no admitidos. '.$options['provider_name'].' admite quality='.implode('|',$options['qualities']).'. Consulta list_image_options para tamaños.');
            if (mb_strlen($args['prompt'])>$options['max_prompt_length']) throw new RuntimeException('El prompt supera el límite del proveedor: '.$options['max_prompt_length'].' caracteres.');
            return $f+['prompt'=>SafeHtml::plain($args['prompt']),'size'=>$args['size']??'1024x1024','quality'=>$args['quality']??'standard','provider_id'=>(int)$provider['id'],'provider_name'=>$provider['name'],'model'=>$provider['model']??null,'uses_site_ai_quota'=>true];
        }
        if (isset($args['url'])===isset($args['base64'])) throw new RuntimeException('Indica una URL pública o una imagen base64, solo una.');
        $f['filename']=$args['filename']??'imagen';
        if (isset($args['base64'])) {
            $data=base64_decode($args['base64'],true); if ($data===false) throw new RuntimeException('Base64 no válido.');
            $mime=self::validateImage($data,2097152);
            return $f+['sha256'=>hash('sha256',$data),'mime_type'=>$mime,'bytes'=>strlen($data),'source'=>'base64'];
        }
        $parts=parse_url($args['url']);
        if (!$parts || ($parts['scheme']??'')!=='https' || isset($parts['user']) || isset($parts['pass']) || empty($parts['host']) || strlen($args['url'])>2000 || preg_match('/[\\\\\x00-\x20]/',$args['url'])) throw new RuntimeException('Usa una URL HTTPS pública sin credenciales.');
        // Literal local IPs are denied before any external request. DNS is checked and pinned on execution.
        if (filter_var(trim($parts['host'],'[]'),FILTER_VALIDATE_IP) && !UrlGuard::isPublicIp(trim($parts['host'],'[]'))) throw new RuntimeException('No se permiten imágenes de redes internas.');
        return $f+['url'=>$args['url'],'source'=>'public_url','max_bytes'=>10485760];
    }
    public static function validateImage(string $data,int $limit): string
    {
        if ($data==='' || strlen($data)>$limit) throw new RuntimeException('La imagen supera el tamaño permitido.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($data); $dim=@getimagesizefromstring($data);
        if (!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true) || !$dim || $dim[0]*$dim[1]>40000000) throw new RuntimeException('Imagen no válida. Usa JPEG, PNG, WebP o GIF de hasta 40 megapíxeles.');
        return $mime;
    }
    private function context(int $site,callable $fn): array
    {
        $old=$GLOBALS['tenant']??null; $oldSession=$_SESSION['tenant_id']??null;
        $oldUri=$_SERVER['REQUEST_URI']??null;
        $hasGlobal=array_key_exists('tenant',$GLOBALS); $hasSession=isset($_SESSION)&&array_key_exists('tenant_id',$_SESSION);
        try {
            if ($site) { $row=$this->content->query("SELECT * FROM tenants WHERE id=? AND status='active'",[$site])[0]??null; if (!$row) throw new RuntimeException('Sitio no disponible.'); $GLOBALS['tenant']=$row; $_SESSION['tenant_id']=$site; $_SERVER['REQUEST_URI']='/'.trim(function_exists('admin_path')?admin_path():'admin','/').'/mcp/internal'; }
            else { unset($GLOBALS['tenant'],$_SESSION['tenant_id']); $_SERVER['REQUEST_URI']='/musedock/mcp/internal'; }
            return $fn();
        } finally {
            if ($oldUri===null) unset($_SERVER['REQUEST_URI']); else $_SERVER['REQUEST_URI']=$oldUri;
            if ($hasGlobal) $GLOBALS['tenant']=$old; else unset($GLOBALS['tenant']);
            if ($hasSession) $_SESSION['tenant_id']=$oldSession; else unset($_SESSION['tenant_id']);
        }
    }
    public function execute(string $tool,int $site,int $actor,array $args): array
    {
        if (!class_exists(\MediaManager\Controllers\MediaController::class)) throw new RuntimeException('El gestor de medios no está disponible.');
        return $this->context($site,function () use($tool,$site,$actor,$args) {
            if ($tool==='generate_image') {
                $result=AIImageService::generateWithDefault(SafeHtml::plain($args['prompt']),['size'=>$args['size']??'1024x1024','quality'=>$args['quality']??'standard'],['tenant_id'=>$site?:null,'user_id'=>null,'user_type'=>'mcp','module'=>'mcp','action'=>'generate_image']);
                $row=$this->content->query('SELECT id,public_token,slug,seo_filename FROM media WHERE COALESCE(tenant_id,0)=? ORDER BY id DESC LIMIT 5',[$site]);
                $id=0;foreach($row as $m) if(!empty($m['public_token']) && $this->publicUrl($m)===$result['local_path']) $id=(int)$m['id'];
                if (!$id) throw new RuntimeException('No se pudo localizar la imagen generada en el sitio autorizado.');
            } else {
                $data=isset($args['base64'])?base64_decode($args['base64'],true):UrlGuard::fetch($args['url'],10485760,20);
                if (!is_string($data)) throw new RuntimeException('No se pudo descargar una imagen pública válida.');
                $mime=self::validateImage($data,isset($args['base64'])?2097152:10485760);
                if ($site) {
                    $t=$GLOBALS['tenant']; $quota=(int)($t['storage_quota_mb']??1024)*1048576;
                    if ((int)($t['storage_used_bytes']??0)+strlen($data)*3>$quota) throw new RuntimeException('No hay espacio suficiente para la imagen y sus miniaturas.');
                }
                $ext=match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'};
                $file=tempnam(sys_get_temp_dir(),'cms_mcp_image_'); chmod($file,0600); file_put_contents($file,$data);
                try { $result=(new \MediaManager\Controllers\MediaController())->uploadFromFile($file,$mime,$site?:null,['original_name'=>substr(preg_replace('/[^a-zA-Z0-9_-]+/','-',$args['filename']??'imagen'),0,80).'.'.$ext,'user_id'=>null,'disk'=>'media']); }
                finally { @unlink($file); }
                $id=(int)($result['id']??0); if(!$id) throw new RuntimeException('No se pudo guardar la imagen.');
            }
            $alt=mb_substr(SafeHtml::plain($args['alt_text']??($args['prompt']??'')),0,250);
            $update=$this->content->db->prepare('UPDATE media SET alt_text=? WHERE id=? AND COALESCE(tenant_id,0)=?');
            $update->bindValue(1,$alt);$update->bindValue(2,$id,\PDO::PARAM_INT);$update->bindValue(3,$site,\PDO::PARAM_INT);$update->execute();
            $media=$this->one($id,$site);
            return ['id'=>$id,'site_id'=>$site,'status'=>'saved','media'=>$media,'html'=>'<img src="'.htmlspecialchars($media['url'],ENT_QUOTES).'" alt="'.htmlspecialchars($alt,ENT_QUOTES).'" loading="lazy">'];
        });
    }
}
