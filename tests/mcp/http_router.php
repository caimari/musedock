<?php
require __DIR__.'/bootstrap.php';
fixture();
$_ENV['MCP_CMS_PRIVATE_DIRECTORY']=getenv('MCP_CMS_PRIVATE_DIRECTORY');
$_ENV['MCP_CMS_DIRECT_ENABLED']=getenv('MCP_CMS_DIRECT_ENABLED')?:'true';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$stateless=$path==='/mcp' || str_starts_with($path,'/.well-known/') || preg_match('#^/mcp-auth/(cms|cross-publisher|news-aggregator)/(token|revoke)$#D',$path)
    || ($path==='/mcp-auth/cms/authorize' && $_SERVER['REQUEST_METHOD']==='GET' && !isset($_GET['_ss']));
define('MUSEDOCK_STATELESS_REQUEST',(bool)$stateless);
if ($stateless) { ini_set('session.use_cookies','0'); ini_set('session.save_path',getenv('MCP_TEST_SESSIONS')); }
if (!$stateless) { ini_set('session.save_path',getenv('MCP_TEST_SESSIONS')); ini_set('session.cookie_httponly','1'); session_start(); }
use Screenart\Musedock\Route;
Route::get('/__test/login',function(){ $_SESSION['super_admin']=['id'=>7,'role'=>'superadmin']; $_SESSION['_csrf_token']=bin2hex(random_bytes(32)); echo 'test login'; });
require APP_ROOT.'/routes/mcp.php';
Route::resolve();
