<?php
require __DIR__.'/run.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Content,Authorization,Tools,Policy};
$s=new Store(new PDO('sqlite::memory:'));$c=new Content(fixture());$o=new Authorization($s);$t=new Tools($s,$c);resource($s,'plugin:cross-publisher','https://cms.example/mcp/cross-publisher',[1,2]);[$auth]=connection($o,'plugin:cross-publisher',[Policy::READ],[1,2],[10]);
$sites=$t->call('list_sites',$auth,[]);$a=array_values(array_filter($sites,fn($site)=>(int)$site['id']===1))[0];
check($a['aliases']===[['domain'=>'public-a.example','url'=>'https://public-a.example','include_www'=>true]],'only active aliases for authorized site are returned');
check($a['domain']==='a.example' && count($sites)===2,'alias preserves domain and site identity');
check(!str_contains(json_encode($sites),'private-client.example'),'foreign site aliases remain private');
check($a['public_urls']===['https://a.example','https://public-a.example'],'base and alias URLs are explicit');
echo "PASS: active aliases, public URLs, site identity and permission isolation.\n";
