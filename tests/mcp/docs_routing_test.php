<?php
require __DIR__.'/run.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Content,Authorization,Tools,Policy,ApprovalQueue};
$s=new Store(new PDO('sqlite::memory:'));$c=new Content(fixture());$o=new Authorization($s);$tools=new Tools($s,$c);
resource($s,'main','https://cms.example/mcp');[$auth]=connection($o,'main',Policy::scopes('main'));
$client=$auth['client'];$client['auto_approve_posts']=true;$s->putClient($client);$auth['client']=$client;
$execute=function($tool,$args)use($tools,$auth,$s,$c){$request=$tools->call($tool,$auth,$args+['apply'=>true]);(new ApprovalQueue($s,$c))->run();$row=$s->one('SELECT data,status FROM requests WHERE id=?',[$request['request_id']]);check($row['status']==='completed','documentation edit completes');return json_decode($row['data'],true)['result'];};
$post=$execute('create_post',['title'=>'Documentation routing','content'=>'<h2>Setup</h2><p>Guide</p>','post_type'=>'docs']);$id=$post['post_id'];
check($c->db->query('SELECT prefix FROM slugs WHERE reference_id='.$id)->fetchColumn()==='docs','new documentation uses docs prefix');
$execute('update_post',['post_id'=>$id,'post_type'=>'post']);
check($c->db->query('SELECT prefix FROM slugs WHERE reference_id='.$id)->fetchColumn()==='blog','conversion restores blog prefix without requiring slug change');
$execute('update_post',['post_id'=>$id,'post_type'=>'docs']);
check($c->db->query('SELECT prefix FROM slugs WHERE reference_id='.$id)->fetchColumn()==='docs','conversion to docs updates route');
echo "PASS: documentation routing and type changes; isolated fixtures only.\n";
