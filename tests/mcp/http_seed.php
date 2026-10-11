<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Policy,Authorization};
$_ENV['MCP_CMS_PRIVATE_DIRECTORY']=getenv('MCP_CMS_PRIVATE_DIRECTORY');
$s=new Store(); $r=['id'=>'main','url'=>'https://cms.example/mcp','name'=>'Test CMS','enabled'=>true,'allow_drafts'=>true,'allow_mcp_approval'=>true,'owned_sites'=>[],'revision'=>1];$s->putResource($r);
$o=new Authorization($s);$new=$o->createClient('main','HTTP Test','https://client.example/callback',[Policy::READ,Policy::CREATE,Policy::APPROVE],[],[],'127.0.0.1/32');
echo Store::json(['client'=>$new['client']['id'],'secret'=>$new['secret']]);
