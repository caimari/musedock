<?php
namespace MuseDockMembers;
use MuseDockForms\Forms;
use PDO;
final class Controller
{
    private function nav(): string {
        $html='<nav><a href="/">Inicio</a><a href="/md-members/login">Acceder</a><a href="/md-members/account">Mi cuenta</a><a href="/md-members/reset">Recuperar contraseña</a></nav>';
        return function_exists('apply_filters')?(string)apply_filters('musedock_members_navigation',$html):$html;
    }
    public function login(): string { return Forms::page('Acceso de socios',$this->nav().'<form method="post" action="/md-members/login">'.csrf_field().'<label>Email o usuario<input name="identifier" required autocomplete="username" maxlength="254"></label><label>Contraseña<input type="password" name="password" required autocomplete="current-password" maxlength="4096"></label><button>Acceder</button></form>'); }
    public function authenticate(): string {
        Forms::csrf();Forms::throttle('member-login',6);$m=Members::login(trim((string)($_POST['identifier']??'')),(string)($_POST['password']??''));
        if(!$m)return Forms::page('Acceso no disponible',$this->nav().'<p>Revisa los datos o consulta con la organización si tu solicitud está pendiente de aprobación.</p>',401);
        header('Location: /md-members/account',true,303);return '';
    }
    public function account(): string {
        $m=Members::current();if(!$m){header('Location: /md-members/login',true,303);return '';}
        $p=json_decode($m['profile'],true)?:[];$p['display_name']=$m['display_name'];
        $fields=[['key'=>'display_name','type'=>'text','label'=>'Nombre mostrado','required'=>true],['key'=>'first_name','type'=>'text','label'=>'Nombre'],['key'=>'last_name','type'=>'text','label'=>'Apellidos'],['key'=>'DNI','type'=>'text','label'=>'DNI / pasaporte privado'],['key'=>'country','type'=>'text','label'=>'País'],['key'=>'Provincia','type'=>'text','label'=>'Provincia'],['key'=>'PoblacionV','type'=>'text','label'=>'Población'],['key'=>'CP_V','type'=>'text','label'=>'Código postal'],['key'=>'Telefono','type'=>'tel','label'=>'Teléfono privado'],['key'=>'Direccion','type'=>'text','label'=>'Dirección privada'],['key'=>'PaginaWeb','type'=>'url','label'=>'Web'],['key'=>'ProfesionPasionPrincipales','type'=>'text','label'=>'Actividad artística']];
        $photo=!empty($p['profile_photo'])?'<img src="/md-members/photo/'.(int)$m['id'].'" alt="Foto de perfil" width="120" height="120">':'';
        $body=$this->nav().$photo.'<p>Estado: '.Forms::e($m['status']).'. Tus datos de contacto e identificación son privados.</p><form method="post" action="/md-members/account">'.csrf_field().Forms::fields($fields,$p).'<p>La publicación en el directorio está desactivada por defecto. Si autorizas que aparezca tu perfil, solo se muestra el nombre y tipo de asociado.</p><label><input type="checkbox" name="public_profile" value="1"'.((int)$m['public_profile']?' checked':'').'> Mostrar mi nombre en el directorio público</label><button>Guardar perfil</button></form><form method="post" action="/md-members/logout">'.csrf_field().'<button>Cerrar sesión</button></form>';
        return Forms::page('Mi cuenta',$body);
    }
    public function update(): string {
        Forms::csrf();$m=Members::current();if(!$m)return Forms::page('No autorizado','',401);
        $p=json_decode($m['profile'],true)?:[];$name=trim((string)($_POST['display_name']??''));if($name===''||strlen($name)>255)return Forms::page('Nombre inválido','',422);
        foreach(['first_name','last_name','DNI','country','Provincia','PoblacionV','CP_V','Telefono','Direccion','PaginaWeb','ProfesionPasionPrincipales'] as $k){$v=$_POST[$k]??'';if(!is_string($v)||strlen($v)>1000)return Forms::page('Dato inválido','',422);$p[$k]=$v;}
        if($p['PaginaWeb']!==''&&(!filter_var($p['PaginaWeb'],FILTER_VALIDATE_URL)||!in_array(parse_url($p['PaginaWeb'],PHP_URL_SCHEME),['http','https'],true)))return Forms::page('Web inválida','',422);
        Forms::query('UPDATE md_members SET display_name=?,profile=?,public_profile=? WHERE id=? AND tenant_id=?',[$name,json_encode($p,JSON_UNESCAPED_UNICODE),($_POST['public_profile']??'')==='1'?1:0,$m['id'],Forms::tenant()]);header('Location: /md-members/account',true,303);return '';
    }
    public function logout(): string { Forms::csrf();unset($_SESSION['md_members'][Forms::tenant()]);session_regenerate_id(true);header('Location: /',true,303);return ''; }
    public function register(): string {
        if(!Members::registrationEnabled())return Forms::page('Registro no disponible','',404);
        $f=Forms::form((string)site_setting('musedock_members_registration_form','international-associate'));if(!$f)return Forms::page('Registration unavailable','',404);
        return Forms::page('International associate registration',$this->nav().'<p>Your application is subject to approval by the association.</p><form method="post" action="/md-members/register">'.csrf_field().Forms::fields(json_decode($f['fields'],true)?:[]).'<label>Password (minimum 12 characters)<input type="password" name="password" minlength="12" maxlength="4096" required autocomplete="new-password"></label><label>Repeat password<input type="password" name="password_confirmation" required autocomplete="new-password"></label><div class="md-trap"><input name="contact_website" tabindex="-1" autocomplete="off"></div><p><a href="/privacy" target="_top">Privacy information</a></p><label><input type="checkbox" name="privacy_ack" value="1" required> I have read the privacy information.</label><button>Apply for membership</button></form>');
    }
    public function create(): string {
        if(!Members::registrationEnabled())return Forms::page('Registro no disponible','',404);
        Forms::csrf();Forms::throttle('member-register',4);$f=Forms::form((string)site_setting('musedock_members_registration_form','international-associate'));if(!$f)return Forms::page('Unavailable','',404);
        if(!empty($_POST['contact_website']))return Forms::page('Application received','Thank you.');
        [$values,$errors]=Forms::validate(json_decode($f['fields'],true)?:[],$_POST);
        if(($_POST['privacy_ack']??'')!=='1')$errors['privacy']='Read the privacy information.';
        if(($_POST['password']??'')!==($_POST['password_confirmation']??''))$errors['password']='Passwords do not match.';
        if($errors)return Forms::page('Review your application','<p>'.Forms::e(implode(' · ',$errors)).'</p><a href="/md-members/register">Try again</a>',422);
        try{Members::register($f,$values,(string)($_POST['password']??''));}catch(\PDOException $e){if($e->getCode()!=='23505'&&$e->getCode()!=='23000')throw $e;return Forms::page('Check your application','<p>If you already have an account, use the login or password recovery page.</p>');}catch(\InvalidArgumentException $e){return Forms::page('Review your application',Forms::e($e->getMessage()),422);}
        return Forms::page('Application received','<p>Your application is awaiting review by the association. You can log in once it is approved.</p>');
    }
    public function directory(): string {
        $rows=Forms::query("SELECT display_name,member_type FROM md_members WHERE tenant_id=? AND status='approved' AND public_profile=1 ORDER BY display_name LIMIT 100",[Forms::tenant()])->fetchAll(PDO::FETCH_ASSOC);$html=$this->nav().'<ul>';foreach($rows as $m)$html.='<li>'.Forms::e($m['display_name']).' · '.Forms::e($m['member_type']).'</li>';return Forms::page('Directorio de socios',$html.'</ul>');
    }
    public function admin(): string {
        Forms::admin();$offset=(max(1,(int)($_GET['page']??1))-1)*50;$rows=Forms::query('SELECT id,display_name,email,member_type,status,source_id FROM md_members WHERE tenant_id=? ORDER BY id LIMIT 50 OFFSET '.$offset,[Forms::tenant()])->fetchAll(PDO::FETCH_ASSOC);
        $html='<nav><a href="/'.Forms::e(admin_path()).'/dashboard">Panel</a><a href="/md-forms/manage">Formularios y solicitudes</a></nav><div class="md-scroll"><table><thead><tr><th>Socio</th><th>Email</th><th>Tipo</th><th>Estado</th><th>Revisión</th></tr></thead><tbody>';
        foreach($rows as $r)$html.='<tr><td><a href="/md-members/manage/'.$r['id'].'">'.Forms::e($r['display_name']).'</a></td><td>'.Forms::e($r['email']).'</td><td>'.Forms::e($r['member_type']).'</td><td>'.Forms::e($r['status']).'</td><td><form method="post" action="/md-members/review/'.$r['id'].'">'.csrf_field().'<select name="status"><option value="awaiting_admin_review">Pendiente</option><option value="approved">Aprobar</option><option value="rejected">Rechazar</option></select><button>Aplicar</button></form></td></tr>';
        $page=max(1,(int)($_GET['page']??1));return Forms::page('MuseDock Members',$html.'</tbody></table></div><p><a href="?page='.max(1,$page-1).'">Anterior</a> · <a href="?page='.($page+1).'">Siguiente</a></p>');
    }
    public function detail($id): string {
        Forms::admin();$m=Forms::query('SELECT id,display_name,email,username,status,member_type,profile,source,source_id,created_at FROM md_members WHERE id=? AND tenant_id=?',[(int)$id,Forms::tenant()])->fetch(PDO::FETCH_ASSOC);if(!$m)return Forms::page('No encontrado','',404);
        $m['profile']=json_decode($m['profile'],true);return Forms::page('Ficha privada de socio','<a href="/md-members/manage">Volver</a><pre>'.Forms::e(json_encode($m,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>');
    }
    public function review($id): string {
        Forms::admin();Forms::csrf();$status=(string)($_POST['status']??'');if(!in_array($status,['approved','awaiting_admin_review','rejected'],true))return Forms::page('Estado inválido','',422);
        $db=Forms::db();$db->beginTransaction();try{$q=Forms::query('UPDATE md_members SET status=?,session_version=session_version+1 WHERE id=? AND tenant_id=?',[$status,(int)$id,Forms::tenant()]);if($q->rowCount())Forms::query('INSERT INTO md_member_audit (tenant_id,member_id,actor_id,action) VALUES (?,?,?,?)',[Forms::tenant(),(int)$id,(int)(\Screenart\Musedock\Security\SessionSecurity::getAuthenticatedUser()['id']??0),$status]);$db->commit();}catch(\Throwable $e){$db->rollBack();throw $e;}
        header('Location: /md-members/manage',true,303);return '';
    }
    public function photo($id): string {
        Forms::noCache();$m=Members::current();if(!$m||(int)$m['id']!==(int)$id){http_response_code(403);return '';}
        $p=json_decode($m['profile'],true)?:[];$name=$p['profile_photo']??'';
        if(!is_string($name)||basename($name)!==$name||!preg_match('/^[a-z0-9_.-]+\.(?:jpe?g|png|gif|webp)$/i',$name)||!ctype_digit((string)$m['source_id'])){http_response_code(404);return '';}
        $dir=APP_ROOT.'/storage/tenants/'.Forms::tenant().'/private/musedock-members/legacy-media/'.$m['source_id'];$path=$dir.'/'.$name;
        if(!is_file($path)||is_link($path)){http_response_code(404);return '';}
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true)){http_response_code(404);return '';}
        header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));readfile($path);return '';
    }
    public function reset(): string {
        $token=(string)($_GET['token']??'');$body=$this->nav();
        if($token!=='')$body.='<form method="post" action="/md-members/reset-password">'.csrf_field().'<input type="hidden" name="token" value="'.Forms::e($token).'"><label>Nueva contraseña<input type="password" name="password" required minlength="12" maxlength="4096" autocomplete="new-password"></label><button>Guardar contraseña</button></form>';
        else $body.='<form method="post" action="/md-members/reset">'.csrf_field().'<label>Email<input type="email" name="email" required maxlength="254"></label><button>Solicitar recuperación</button></form>';
        return Forms::page('Recuperar contraseña',$body);
    }
    public function requestReset(): string {
        Forms::csrf();Forms::throttle('member-reset',4);$email=strtolower(trim((string)($_POST['email']??'')));$m=Forms::query('SELECT id,email FROM md_members WHERE tenant_id=? AND email=?',[Forms::tenant(),$email])->fetch(PDO::FETCH_ASSOC);
        if($m){$raw=bin2hex(random_bytes(32));Forms::query('INSERT INTO md_member_tokens (tenant_id,member_id,token_hash,expires_at) VALUES (?,?,?,?)',[Forms::tenant(),$m['id'],hash('sha256',$raw),date('Y-m-d H:i:s',time()+3600)]);
            $q=Forms::query('SELECT domain FROM tenants WHERE id=?',[Forms::tenant()]);$domain=$q->fetchColumn();$url='https://'.$domain.'/md-members/reset?token='.rawurlencode($raw);
            $ok=\Screenart\Musedock\Mail\Mailer::send($m['email'],'Recuperación de acceso','<p>Has solicitado recuperar tu acceso. El enlace caduca en una hora. Si no has sido tú, ignora este mensaje.</p><p><a href="'.Forms::e($url).'">Cambiar contraseña</a></p>','Recuperación de acceso: '.$url,null,null,Forms::tenant());
            if(!$ok)Forms::query('DELETE FROM md_member_tokens WHERE tenant_id=? AND token_hash=?',[Forms::tenant(),hash('sha256',$raw)]);
        }
        return Forms::page('Revisa tu correo','<p>Si la dirección corresponde a una cuenta y el correo está operativo, recibirás instrucciones. Si no llegan, contacta con la asociación.</p>');
    }
    public function resetPassword(): string {
        Forms::csrf();Forms::throttle('member-reset-apply',6);$password=(string)($_POST['password']??'');$raw=(string)($_POST['token']??'');if(strlen($password)<12||strlen($password)>4096||!preg_match('/^[a-f0-9]{64}$/',$raw))return Forms::page('Datos inválidos','',422);
        $db=Forms::db();$db->beginTransaction();try{$t=Forms::query('SELECT * FROM md_member_tokens WHERE tenant_id=? AND token_hash=? AND used_at IS NULL AND expires_at>CURRENT_TIMESTAMP FOR UPDATE',[Forms::tenant(),hash('sha256',$raw)])->fetch(PDO::FETCH_ASSOC);if(!$t){$db->rollBack();return Forms::page('Enlace caducado o utilizado','',422);}
            Forms::query('UPDATE md_members SET password_hash=?,session_version=session_version+1 WHERE id=? AND tenant_id=?',[password_hash($password,PASSWORD_DEFAULT),$t['member_id'],Forms::tenant()]);Forms::query('UPDATE md_member_tokens SET used_at=CURRENT_TIMESTAMP WHERE member_id=? AND tenant_id=? AND used_at IS NULL',[$t['member_id'],Forms::tenant()]);$db->commit();}catch(\Throwable $e){$db->rollBack();throw $e;}
        return Forms::page('Contraseña actualizada',$this->nav().'<p>Ya puedes acceder si tu cuenta está aprobada.</p>');
    }
}
