<?php
namespace MuseDockForms;

use PDO;
use Screenart\Musedock\Database;

/** Tenant-scoped forms and submissions. No WordPress runtime dependency. */
final class Forms
{
    public static function db(): PDO { return Database::connect(); }
    public static function tenant(): int {
        $id=(int)(tenant_id()??0);
        if($id<1) throw new \RuntimeException('Tenant requerido');
        return $id;
    }
    public static function e($s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function query(string $sql,array $args=[]): \PDOStatement { $q=self::db()->prepare($sql);$q->execute($args);return $q; }
    public static function form(string $slug): ?array {
        $r=self::query('SELECT * FROM md_forms WHERE tenant_id=? AND slug=? AND active=1',[self::tenant(),$slug])->fetch(PDO::FETCH_ASSOC);return $r?:null;
    }
    public static function schema(array $fields): array {
        if(count($fields)>80)throw new \InvalidArgumentException('Máximo 80 campos');
        $seen=[];$types=['text','email','tel','url','date','number','textarea','select','checkbox','checkboxes'];
        foreach($fields as $f){if(!is_array($f)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,79}$/',$f['key']??'')||isset($seen[$f['key']])||!in_array($f['type']??'',$types,true)||!is_string($f['label']??null))throw new \InvalidArgumentException('Campo inválido o duplicado');$seen[$f['key']]=true;if(strlen($f['label'])>1000||count($f['options']??[])>300)throw new \InvalidArgumentException('Campo demasiado largo');if(!is_array($f['options']??[]))throw new \InvalidArgumentException('Opciones inválidas');foreach($f['options']??[] as $o)if(!is_scalar($o))throw new \InvalidArgumentException('Opción inválida');}
        return $fields;
    }
    public static function validate(array $fields,array $input): array {
        self::schema($fields);$values=[];$errors=[];
        foreach($fields as $f){$k=$f['key'];$v=$input[$k]??($f['type']==='checkboxes'?[]:'');
            if($f['type']==='checkboxes'){
                if(!is_array($v)||count($v)>100){$errors[$k]='Selección inválida';continue;}
                $bad=false;foreach($v as $x)if(!is_string($x)||!in_array($x,$f['options']??[],true))$bad=true;
                if($bad){$errors[$k]='Selección inválida';continue;}$v=array_values(array_unique($v));
            }else{
                if(!is_scalar($v)){$errors[$k]='Valor inválido';continue;}$v=trim((string)$v);
                if(strlen($v)>10000){$errors[$k]='Texto demasiado largo';continue;}
                if($v!==''&&$f['type']==='email'&&!filter_var($v,FILTER_VALIDATE_EMAIL))$errors[$k]='Email inválido';
                if($v!==''&&$f['type']==='url'&&(!filter_var($v,FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($v,PHP_URL_SCHEME)??''),['http','https'],true)))$errors[$k]='URL inválida';
                if($v!==''&&$f['type']==='number'&&!is_numeric($v))$errors[$k]='Número inválido';
                if($v!==''&&$f['type']==='date'&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)||date('Y-m-d',strtotime($v))!==$v))$errors[$k]='Fecha inválida';
                if($f['type']==='select'&&$v!==''&&!in_array($v,$f['options']??[],true))$errors[$k]='Selección inválida';
                if($f['type']==='checkbox'&&!in_array($v,['','1'],true))$errors[$k]='Selección inválida';
            }
            if(!empty($f['required'])&&($v===''||$v===[]))$errors[$k]='Campo obligatorio';
            $values[$k]=$v;
        }
        return [$values,$errors];
    }
    public static function fields(array $fields,array $values=[]): string {
        $html='';foreach($fields as $f){$k=$f['key'];$type=$f['type'];$id='mdf-'.$k;$value=$values[$k]??'';$req=!empty($f['required'])?' required':'';
            $html.='<div class="md-field"><label for="'.self::e($id).'">'.self::e($f['label']).($req?' *':'').'</label>';
            $attr=' id="'.self::e($id).'" name="'.self::e($k).'"'.$req;
            if($type==='textarea')$html.='<textarea'.$attr.' maxlength="10000">'.self::e($value).'</textarea>';
            elseif($type==='select'){$html.='<select'.$attr.'><option value="">Selecciona / Select</option>';foreach($f['options']??[] as $o)$html.='<option value="'.self::e($o).'"'.($value===$o?' selected':'').'>'.self::e($o).'</option>';$html.='</select>';}
            elseif($type==='checkboxes'){$html.='<fieldset><legend class="sr-only">'.self::e($f['label']).'</legend>';foreach($f['options']??[] as $i=>$o)$html.='<label><input type="checkbox" name="'.self::e($k).'[]" value="'.self::e($o).'"'.(is_array($value)&&in_array($o,$value,true)?' checked':'').'> '.self::e($o).'</label>';$html.='</fieldset>';}
            elseif($type==='checkbox')$html.='<input type="checkbox"'.$attr.' value="1"'.($value==='1'?' checked':'').'>';
            else $html.='<input type="'.self::e($type).'"'.$attr.' value="'.self::e(is_scalar($value)?$value:'').'" maxlength="10000">';
            $html.='</div>';
        }return $html;
    }
    public static function noCache(): void { header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin'); }
    public static function page(string $title,string $body,int $status=200): string {
        self::noCache();http_response_code($status);
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.self::e($title).'</title><link rel="stylesheet" href="/assets/modules/musedock-forms/forms.css"></head><body><main class="md-app"><h1>'.self::e($title).'</h1>'.$body.'</main><script src="/assets/modules/musedock-forms/frame.js" defer></script></body></html>';
    }
    public static function csrf(): void {
        if(session_status()!==PHP_SESSION_ACTIVE)\Screenart\Musedock\Security\SessionSecurity::startSession();
        if(!validate_csrf(is_string($_POST['_csrf']??null)?$_POST['_csrf']:'')){http_response_code(419);throw new \RuntimeException('Sesión caducada. Recarga el formulario.');}
    }
    public static function throttle(string $action,int $limit=8): void {
        $ip=\Screenart\Musedock\Security\IPHelper::getRealIP();
        $key='md:'.self::tenant().':'.$action.':'.hash('sha256',$ip);
        if(!\Screenart\Musedock\Security\RateLimiter::check($key,$limit,15)){http_response_code(429);throw new \RuntimeException('Demasiados intentos. Inténtalo más tarde.');}
        \Screenart\Musedock\Security\RateLimiter::increment($key,15);
    }
    public static function admin(): void {
        if(!userCan('custom_forms.manage')){http_response_code(403);throw new \RuntimeException('Sin permiso para gestionar formularios y socios');}
        $auth=\Screenart\Musedock\Security\SessionSecurity::getAuthenticatedUser();
        if(!$auth || (int)($auth['tenant_id']??0)!==self::tenant()){http_response_code(403);throw new \RuntimeException('Administrador de otro tenant');}
        self::noCache();
    }
}
