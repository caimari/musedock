<?php
namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Helpers\PermissionHelper;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\IPHelper;
use Screenart\Musedock\Services\Mcp\Direct\Store;

/** Enforce the visibility selected by the editor before any post body is rendered. */
final class BlogAccess
{
    public static function mayRead(array $post): bool
    {
        if (($post['visibility']??'public')==='public') return true;
        $site=(int)($post['tenant_id']??0);
        $root=(int)($_SESSION['super_admin']['id']??0);
        if ($root) { $s=Database::connect()->prepare("SELECT id FROM super_admins WHERE id=? AND role='superadmin'");$s->execute([$root]);if ($s->fetchColumn()) return true; }
        $admin=$_SESSION['admin']??[];
        if ($site && (int)($admin['tenant_id']??0)===$site && !empty($admin['id']) && (PermissionHelper::isTenantRootAdmin((int)$admin['id'],$site) || PermissionHelper::userCan((int)$admin['id'],'blog.edit',$site,'admin'))) return true;
        $user=$_SESSION['user']??[];
        if ($site && ($post['user_type']??'')==='user' && (int)($user['tenant_id']??0)===$site && (int)($post['user_id']??0)===(int)($user['id']??-1)) { $s=Database::connect()->prepare('SELECT id FROM users WHERE id=? AND tenant_id=?');$s->execute([(int)$user['id'],$site]);if ($s->fetchColumn()) return true; }
        if (($post['visibility']??'')!=='password') return false;
        $access=$_SESSION['blog_password_access'][$site.':'.$post['id']]??null;
        return $access && $access['expires']>time() && hash_equals($access['revision'],hash('sha256',(string)($post['password']??'')));
    }
    public static function guard(array $post): bool
    {
        if (($post['visibility']??'public')==='public') return true;
        SessionSecurity::startSession(); header('Cache-Control: private, no-store');
        if (self::mayRead($post)) return true;
        if (($post['visibility']??'')!=='password') { http_response_code(404);echo 'Artículo no disponible.';return false; }
        http_response_code(401);
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Artículo protegido</title><body style="font-family:system-ui;background:#f4f6fa;padding:32px"><main style="max-width:420px;margin:10vh auto;background:white;padding:28px;border-radius:12px"><h1>Artículo protegido</h1><p>Introduce la contraseña para leerlo.</p><form method="post" action="/blog/unlock/'.(int)$post['id'].'">'.csrf_field().'<label for="post-password">Contraseña</label><input id="post-password" name="password" type="password" required autocomplete="current-password" style="display:block;width:100%;margin:12px 0;padding:10px;box-sizing:border-box"><button type="submit">Leer artículo</button></form></main></body></html>';
        return false;
    }
    public static function unlock(int $id): void
    {
        SessionSecurity::startSession();header('Cache-Control: private, no-store');
        $site=(int)($GLOBALS['tenant']['id']??0);$db=Database::connect();
        $s=$db->prepare("SELECT * FROM blog_posts WHERE id=? AND COALESCE(tenant_id,0)=? AND status='published' AND visibility='password' AND deleted_at IS NULL");$s->bindValue(1,$id,\PDO::PARAM_INT);$s->bindValue(2,$site,\PDO::PARAM_INT);$s->execute();$post=$s->fetch(\PDO::FETCH_ASSOC);
        if (!$post) { http_response_code(404);echo 'Artículo no disponible.';return; }
        if (!isset($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'],(string)($_POST['_csrf']??$_POST['_token']??''))) { http_response_code(403);echo 'Solicitud no válida.';return; }
        try { (new Store())->limit('blog-unlock:'.$site.':'.$id.':'.hash('sha256',IPHelper::getRealIP()),10,600); }
        catch(\Throwable $e) { http_response_code(429);echo 'Demasiados intentos. Vuelve a intentarlo más tarde.';return; }
        $password=(string)($_POST['password']??'');$hash=(string)$post['password'];
        $valid=$hash!=='' && strlen($password)<=200 && (str_starts_with($hash,'$2') || str_starts_with($hash,'$argon')?password_verify($password,$hash):hash_equals($hash,$password));
        if (!$valid) { http_response_code(401);echo 'Contraseña no válida.';return; }
        $_SESSION['blog_password_access'][$site.':'.$id]=['expires'=>time()+3600,'revision'=>hash('sha256',$hash)];
        $prefix=function_exists('blog_prefix')?blog_prefix():'blog';
        header('Location: /'.($prefix!==''?trim($prefix,'/').'/':'').rawurlencode($post['slug']));
    }
}
