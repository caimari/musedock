<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use Screenart\Musedock\Database;
use Screenart\Musedock\Env;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\IPHelper;

final class Endpoint
{
    public Store $store;
    public Authorization $oauth;
    public function __construct(public string $resource, ?Store $store=null) { $this->store=$store??new Store(); $this->oauth=new Authorization($this->store); }
    public static function siteResource(): string { return !empty($GLOBALS['tenant']['id'])?'tenant:'.(int)$GLOBALS['tenant']['id']:'main'; }
    public static function installed(string $id): bool
    {
        if (!str_starts_with($id,'plugin:')) return true;
        $slug=substr($id,7);
        return function_exists('is_superadmin_plugin_active') ? is_superadmin_plugin_active($slug) : self::pluginActive($slug);
    }
    private static function pluginActive(string $slug): bool
    {
        try { $s=Database::connect()->prepare('SELECT id FROM superadmin_plugins WHERE slug=? AND is_active=1 AND is_installed=1'); $s->execute([$slug]); return (bool)$s->fetchColumn(); }
        catch (\Throwable $e) { return false; }
    }
    public function configuration(): ?array
    {
        $r=$this->store->resource($this->resource);
        if (!$r || empty($r['enabled']) || !filter_var(Env::get('MCP_CMS_DIRECT_ENABLED',false),FILTER_VALIDATE_BOOLEAN)
            || !filter_var(Env::get('MCP_ENABLED',true),FILTER_VALIDATE_BOOLEAN) || !self::installed($this->resource)) return null;
        $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
        if ($host!==strtolower((string)parse_url($r['url'],PHP_URL_HOST))) return null;
        if (str_starts_with($this->resource,'plugin:') && !empty($GLOBALS['tenant']['id'])) return null;
        if (Policy::site($this->resource)!==null && self::siteResource()!==$this->resource) return null;
        return $r;
    }
    public static function slug(string $resource): string { return str_starts_with($resource,'plugin:')?substr($resource,7):'cms'; }
    public static function issuer(array $r): string { return 'https://'.parse_url($r['url'],PHP_URL_HOST).'/mcp-auth/'.self::slug($r['id']); }
    public static function metadataUrl(array $r): string { return 'https://'.parse_url($r['url'],PHP_URL_HOST).'/.well-known/oauth-protected-resource'.parse_url($r['url'],PHP_URL_PATH); }
    public function metadata(bool $server=false): void
    {
        $r=$this->configuration();
        if (!$r) { $this->json(404,['error'=>'not_found']); return; }
        $issuer=self::issuer($r);
        $this->json(200,$server?[
            'issuer'=>$issuer,'authorization_endpoint'=>$issuer.'/authorize','token_endpoint'=>$issuer.'/token','revocation_endpoint'=>$issuer.'/revoke',
            'response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],
            'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['client_secret_basic'],
            'scopes_supported'=>Policy::scopes($r['id']),'authorization_response_iss_parameter_supported'=>true,
        ]:[ 'resource'=>$r['url'],'authorization_servers'=>[$issuer],'scopes_supported'=>Policy::scopes($r['id']),'bearer_methods_supported'=>['header'],'resource_name'=>$r['name'] ]);
    }
    public function machine(string $action): void
    {
        if (!$this->configuration()) { $this->json(404,['error'=>'not_found']); return; }
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') { $this->json(405,['error'=>'invalid_request']); return; }
        try {
            $this->store->limit('oauth:'.$this->resource.':'.IPHelper::getRealIP(),30);
            $c=$this->oauth->authenticateClient($this->resource,$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
            if (!Network::allows($c['networks']??[],IPHelper::getRealIP())) { $this->json(403,['error'=>'network_denied']); return; }
            if (!empty($_POST)) $p=$_POST; else { parse_str((string)file_get_contents('php://input'),$p); }
            if ($action==='revoke') { $this->oauth->revoke($c,(string)($p['token']??'')); $this->json(200,(object)[]); }
            else {
                $tokens=$this->oauth->exchange($c,$p);
                // Revalidate owner before returning usable tokens. Access checks also repeat this.
                $auth=$this->oauth->access($this->resource,$tokens['access_token']);
                $content=new Content(Database::connect());
                if (!$content->actorActive($this->resource,$auth['grant']['actor'])) { $this->oauth->invalidateClient($c['id']); throw new \RuntimeException('Usuario ya no autorizado.'); }
                $this->json(200,$tokens);
            }
        } catch (\Throwable $e) { $this->json(400,['error'=>'invalid_grant','error_description'=>'Cliente, código o autorización no válidos.']); }
    }
    public function authorize(): void
    {
        $r=$this->configuration();
        if (!$r) { $this->json(404,['error'=>'not_found']); return; }
        header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); header('Referrer-Policy: no-referrer');
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET' && !isset($_GET['_ss'])) {
            // SameSite=Strict bounce; do not create a session on the cross-origin first hop.
            $uri=(string)($_SERVER['REQUEST_URI']??''); $safe=htmlspecialchars($uri.(str_contains($uri,'?')?'&':'?').'_ss=1',ENT_QUOTES,'UTF-8');
            header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");
            echo '<!doctype html><meta http-equiv="refresh" content="0;url='.$safe.'"><a href="'.$safe.'">Continuar</a>'; return;
        }
        SessionSecurity::startSession();
        $actor=$this->actor();
        if (!$actor) {
            $_SESSION['auth_return_to']=$_SERVER['REQUEST_URI'];
            header('Location: '.$this->adminBase().'/login'); return;
        }
        try {
            $content=new Content(Database::connect());
            if (!$content->actorActive($this->resource,$actor)) throw new \RuntimeException('No tienes permiso para conectar este recurso.');
            if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
                $rid=(string)($_POST['rid']??''); $p=$_SESSION['cms_mcp_consent'][$rid]??null; unset($_SESSION['cms_mcp_consent'][$rid]);
                if (!$p || $p['resource']!==$this->resource) throw new \RuntimeException('Solicitud de consentimiento caducada.');
                if (empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'],(string)($_POST['_token']??''))) throw new \RuntimeException('CSRF no válido.');
                $query=['state'=>$p['state'],'iss'=>self::issuer($r)];
                if (($_POST['decision']??'')==='approve') {
                    foreach ($p['scopes'] as $scope) if (!$content->actorActive($this->resource,$actor,$scope)) throw new \RuntimeException('No puedes conceder esos permisos.');
                    $query['code']=$this->oauth->consent($p,$actor);
                } else $query['error']='access_denied';
                header('Location: '.$p['callback'].(str_contains($p['callback'],'?')?'&':'?').http_build_query($query)); return;
            }
            $p=$this->oauth->validateRequest($this->resource,$_GET);
            foreach ($p['scopes'] as $scope) if (!$content->actorActive($this->resource,$actor,$scope)) throw new \RuntimeException('No puedes conceder esos permisos.');
            $rid=bin2hex(random_bytes(16));
            $_SESSION['cms_mcp_consent']=array_slice(array_filter($_SESSION['cms_mcp_consent']??[],fn($v)=>$v['created']>time()-900),-4,null,true);
            $_SESSION['cms_mcp_consent'][$rid]=$p;
            $c=$this->store->client($p['client']); $e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
            $_SESSION['_csrf_token']=$_SESSION['_csrf_token']??bin2hex(random_bytes(32));
            $callbackOrigin='https://'.parse_url($p['callback'],PHP_URL_HOST).(parse_url($p['callback'],PHP_URL_PORT)?':'.parse_url($p['callback'],PHP_URL_PORT):'');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'self' ".$callbackOrigin);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Autorizar MCP</title><body style="font-family:system-ui;max-width:720px;margin:3rem auto;padding:1rem"><h1>Conectar '.$e($c['name']).'</h1><p>Recurso: '.$e($r['name']).' — '.$e($r['url']).'</p><p>Permisos: '.$e(implode(', ',array_map([Policy::class,'label'],$p['scopes']))).'</p><p>Retorno: '.$e($p['callback']).'</p>';
            if (Policy::site($r['id'])===null) echo '<p>Sitios autorizados: '.$e(implode(', ',array_column($content->listSites($r,$c),'domain'))).'</p>';
            if (!empty($r['allow_mcp_approval']) && in_array(Policy::APPROVE,$p['scopes'],true)) echo '<p><strong>Este cliente podrá aprobar y ejecutar sus propias solicitudes desde MCP, incluidos los cambios de publicación que hayas autorizado.</strong></p>';
            else echo '<p>Las solicitudes de cambios deben aprobarse en el CMS. Publicar requiere además el permiso de publicación.</p>';
            echo '<form method="post"><input type="hidden" name="rid" value="'.$e($rid).'"><input type="hidden" name="_token" value="'.$e($_SESSION['_csrf_token']).'"><button name="decision" value="approve">Autorizar</button> <button name="decision" value="reject">Rechazar</button></form></body></html>';
        } catch (\Throwable $e) { $this->json(400,['error'=>'invalid_request','error_description'=>$e->getMessage()]); }
    }
    public function actor(): int
    {
        $site=Policy::site($this->resource);
        if ($site===null || $site===0) return ($_SESSION['super_admin']['role']??'')==='superadmin'?(int)($_SESSION['super_admin']['id']??0):0;
        foreach (['admin'=>1,'user'=>-1] as $type=>$sign) {
            $u=$_SESSION[$type]??[];
            if ((int)($u['tenant_id']??0)===$site && !empty($u['id']) && (userCan('mcp.connect')||userCan('settings.edit'))) return $sign*(int)$u['id'];
        }
        return 0;
    }
    public function adminBase(): string
    {
        return Policy::site($this->resource)>0?'/'.trim((string)($GLOBALS['tenant']['admin_path']??'admin'),'/'):'/'.trim((string)Env::get('ADMIN_PATH_MUSEDOCK','musedock'),'/');
    }
    public function mcp(): void
    {
        $r=$this->configuration();
        if (!$r) { $this->json(404,['error'=>'not_found']); return; }
        if (($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS') { $this->json(204,null); return; }
        try {
            $header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
            if (!preg_match('/^Bearer (cms_access_[a-f0-9]{64})$/D',$header,$m)) throw new \RuntimeException('Token requerido.');
            $auth=$this->oauth->access($this->resource,$m[1]);
            $content=new Content(Database::connect());
            if (!$content->actorActive($this->resource,$auth['grant']['actor'])) throw new \RuntimeException('Usuario revocado.');
        } catch (\Throwable $e) {
            header('WWW-Authenticate: Bearer resource_metadata="'.self::metadataUrl($r).'", scope="'.Policy::READ.'"');
            $this->json(401,['error'=>'invalid_token']); return;
        }
        if (!Network::allows($auth['client']['networks']??[],IPHelper::getRealIP())) { $this->json(403,['error'=>'network_denied']); return; }
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') { header('Allow: POST, OPTIONS'); $this->json(405,['error'=>'method_not_allowed']); return; }
        try { $this->store->limit('mcp:'.$auth['client']['id']); }
        catch (\Throwable $e) { header('Retry-After: 60'); $this->json(429,['error'=>'rate_limited']); return; }
        $raw=file_get_contents('php://input',false,null,0,3000001);
        $msg=strlen($raw)>3000000?null:json_decode($raw,true,32);
        if (!is_array($msg) || array_is_list($msg) || ($msg['jsonrpc']??'')!=='2.0' || !is_string($msg['method']??null) || (isset($msg['id']) && !is_string($msg['id']) && !is_int($msg['id']))) { $this->json(400,['jsonrpc'=>'2.0','id'=>null,'error'=>['code'=>-32600,'message'=>'Invalid Request']]); return; }
        if (!array_key_exists('id',$msg)) { $this->json(202,null); return; }
        $tools=new Tools($this->store,$content); $id=$msg['id'];
        try {
            $result=match($msg['method']) {
                'initialize'=>['protocolVersion'=>$this->protocol($msg['params']['protocolVersion']??''),'capabilities'=>['tools'=>(object)[]],'serverInfo'=>['name'=>'MuseDock CMS — '.$r['name'],'version'=>'2.21.0']],
                'ping'=>(object)[],
                'tools/list'=>['tools'=>$tools->catalog($auth)],
                'tools/call'=>$this->callTool($tools,$auth,$msg['params']??[]),
                default=>throw new \RuntimeException('Método no admitido.'),
            };
            $this->json(200,['jsonrpc'=>'2.0','id'=>$id,'result'=>$result]);
        } catch (\Throwable $e) { $this->json(200,['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32602,'message'=>$e instanceof \PDOException?'Operación no disponible.':$e->getMessage()]]); }
    }
    private function protocol(string $v): string { return in_array($v,['2025-11-25','2025-06-18','2025-03-26'],true)?$v:'2025-11-25'; }
    private function callTool(Tools $tools,array $auth,array $params): array
    {
        if (!is_string($params['name']??null) || !is_array($params['arguments']??[])) throw new \RuntimeException('Argumentos no válidos.');
        try { $result=$tools->call($params['name'],$auth,$params['arguments']??[]); $encoded=Store::json($result); if (strlen($encoded)>1000000) throw new \RuntimeException('Respuesta demasiado grande; reduce el límite de resultados.'); return ['content'=>[['type'=>'text','text'=>$encoded]],'isError'=>false]; }
        catch (\Throwable $e) { return ['content'=>[['type'=>'text','text'=>$e instanceof \PDOException?'Contenido o plugin no disponible.':$e->getMessage()]],'isError'=>true]; }
    }
    private function json(int $status,$data): void
    {
        header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Headers: Authorization, Content-Type, MCP-Protocol-Version');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS'); header('Cache-Control: no-store'); header('Content-Type: application/json; charset=utf-8');
        http_response_code($status); if ($data!==null) echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
