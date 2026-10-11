<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\AI\ImageTrace;
use Screenart\Musedock\Services\Mcp\Direct\{Media,Content};
$directory=sys_get_temp_dir().'/cms-image-trace-'.bin2hex(random_bytes(8));mkdir($directory,0700);
putenv('MCP_CMS_PRIVATE_DIRECTORY='.$directory);
try {
    ImageTrace::$context=['request_id'=>'test-request','tenant_id'=>1,'provider_id'=>1,'api_key'=>'must-never-appear'];
    ImageTrace::record('provider_http_response',['http_status'=>200,'curl_errno'=>0,'duration_ms'=>100,'prompt'=>'must-never-appear','url'=>'https://secret.example']);
    $raw=file_get_contents(ImageTrace::path());
    if(str_contains($raw,'must-never-appear')||str_contains($raw,'secret.example')||(fileperms(ImageTrace::path())&0777)!==0600)throw new RuntimeException('Unsafe image diagnostics.');
    if(count(ImageTrace::recent())!==1)throw new RuntimeException('Trace unavailable.');
    $_SERVER['REQUEST_URI']='/musedock/settings/mcp';
    $media=new Media(new Content(fixture()));
    $context=new ReflectionMethod($media,'context');
    $result=$context->invoke($media,1,fn()=>['site'=>$GLOBALS['tenant']['id'],'uri'=>$_SERVER['REQUEST_URI']]);
    if($result['site']!==1 || !str_contains($result['uri'],'/admin/') || $_SERVER['REQUEST_URI']!=='/musedock/settings/mcp' || isset($GLOBALS['tenant']))throw new RuntimeException('Incorrect background tenant context.');
    echo "PASS: private image diagnostics omit credentials/prompts/URLs; tenant media context is scoped and restored.\n";
} finally { ImageTrace::$context=[];unlink($directory.'/image-generation.jsonl');rmdir($directory);putenv('MCP_CMS_PRIVATE_DIRECTORY'); }
