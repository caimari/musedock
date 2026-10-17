<?php
require __DIR__.'/run.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Authorization,Content,Tools,Policy,ApprovalQueue};
$store=new Store(new PDO('sqlite::memory:')); $content=new Content(fixture()); $oauth=new Authorization($store);
resource($store,'main','https://cms.example/mcp');
[$auth]=connection($oauth,'main',[Policy::READ,Policy::CREATE]);
$client=$store->client($auth['client']['id']); $revision=$client['revision'];
$oauth->reviseClient('main',$client['id'],array_reverse($client['scopes']),true);
check($store->client($client['id'])['revision']===$revision,'saving unchanged scopes preserves revision');
check($store->grant($auth['grant']['id'])['active'],'saving preferences preserves consent');
$client['auto_approve_posts']=true; $store->putClient($client); $auth['client']=$client;
$request=(new Tools($store,$content))->call('create_draft',$auth,['title'=>'Automatic legacy draft','content'=>'Test','apply'=>true]);
check($request['status']==='queued' && $request['auto_approved'],'legacy draft queued automatically');
check((new ApprovalQueue($store,$content))->run()===1,'automatic draft executes via existing worker');
check($content->db->query("SELECT COUNT(*) FROM blog_posts WHERE title='Automatic legacy draft'")->fetchColumn()==1,'automatic draft saved once');
echo "PASS: unchanged scopes preserve OAuth and legacy drafts auto execute.\n";
