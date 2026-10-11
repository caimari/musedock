<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Policy,Authorization};
if (!function_exists('pcntl_fork')) throw new RuntimeException('Esta prueba requiere pcntl.');
$directory=sys_get_temp_dir().'/cms-mcp-race-'.bin2hex(random_bytes(8));mkdir($directory,0700);
$_ENV['MCP_CMS_PRIVATE_DIRECTORY']=$directory;
try {
    $s=new Store();$s->putResource(['id'=>'main','url'=>'https://cms.example/mcp','name'=>'Race','enabled'=>true,'allow_drafts'=>false,'revision'=>1]);
    $o=new Authorization($s);$new=$o->createClient('main','Race','https://client.example/callback',[Policy::READ]);$c=$new['client'];$verifier=str_repeat('A',43);
    $p=['client_id'=>$c['id'],'redirect_uri'=>$c['callback'],'response_type'=>'code','code_challenge_method'=>'S256','code_challenge'=>Policy::challenge($verifier),'resource'=>'https://cms.example/mcp'];
    $code=$o->consent($o->validateRequest('main',$p),7);
    $exchange=['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$c['callback'],'code_verifier'=>$verifier,'resource'=>$p['resource']];
    $start=microtime(true)+.2;$children=[];
    for($i=0;$i<2;$i++) {
        $pid=pcntl_fork();
        if($pid===0) {
            while(microtime(true)<$start) usleep(1000);
            $child=new Authorization(new Store());
            try { $child->exchange($c,$exchange);file_put_contents($directory.'/result-'.$i,'success'); }
            catch(Throwable $e) { file_put_contents($directory.'/result-'.$i,'denied'); }
            exit(0);
        }
        if($pid<0) throw new RuntimeException('No se pudo iniciar la prueba concurrente.');
        $children[]=$pid;
    }
    foreach($children as $pid) pcntl_waitpid($pid,$status);
    $results=[file_get_contents($directory.'/result-0'),file_get_contents($directory.'/result-1')];sort($results);
    if($results!==['denied','success']) throw new RuntimeException('Un código se intercambió más de una vez concurrentemente.');
    echo "PASS: concurrent OAuth code exchange: exactly one succeeds.\n";
} finally {
    foreach(glob($directory.'/*') as $file) if(is_file($file)) unlink($file);
    rmdir($directory);
}
