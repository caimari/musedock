<?php
require __DIR__.'/run.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Authorization,Content,Tools,Policy,ArticleWorkflow};
$s=new Store(new PDO('sqlite::memory:')); $c=new Content(fixture()); $o=new Authorization($s);
$r=resource($s,'main','https://cms.example/mcp'); $r['allow_publishing']=true; $s->putResource($r);
[$auth]=connection($o,'main',Policy::scopes('main'));
$client=$auth['client']; $client['auto_approve_posts']=true; $client['auto_approve_images']=true; $s->putClient($client); $auth['client']=$client;
$c->db->exec("INSERT INTO media(id,tenant_id,filename,mime_type,public_token,slug,seo_filename) VALUES(10,NULL,'cover.jpg','image/jpeg','cover-token','cover','cover-token.jpg')");
$key=hash('sha256','main|'.$client['id'].'|0|1');
$job=['id'=>$key,'resource'=>'main','client'=>$client['id'],'grant'=>$auth['grant']['id'],'site'=>0,'post'=>1,'args'=>[],'stage'=>'image_saved','media_id'=>10,'status'=>'queued'];
$s->run('INSERT INTO article_workflows VALUES(?,?,?,?,?)',[$key,'main',$client['id'],'queued',Store::json($job)]);
$workflow=new ArticleWorkflow($s,$c);
$workflow->run();
$state=$workflow->call($auth,['post_id'=>1,'action'=>'status']);
check((new Tools($s,$c))->call('publish_article_with_image',$auth,['post_id'=>1,'action'=>'status'])['status']==='completed','workflow accessible through MCP schema and dispatch');
check($state['status']==='completed','workflow completes from persisted image: '.($state['error']??''));
check($c->db->query('SELECT status FROM blog_posts WHERE id=1')->fetchColumn()==='published','workflow publishes after image');
check($workflow->run()===0,'completed workflow never runs twice');
check($workflow->call($auth,['post_id'=>1,'apply'=>true])['id']===$key,'repeated call returns same job');
// A new OAuth grant of the same access can inspect the durable result.
$newGrant=$auth['grant']; $newGrant['id']='another-session'; $s->putGrant($newGrant);
$newAuth=$auth; $newAuth['grant']=$newGrant;
check($workflow->call($newAuth,['post_id'=>1,'action'=>'status'])['status']==='completed','new session of same access reads durable result');
// Interrupted provider calls never create another image. Recovery requires an existing site-owned medium.
$s->run('DELETE FROM article_workflows WHERE id=?',[$key]);
$job['stage']='generating'; $job['status']='failed'; unset($job['media_id']);
$s->run('INSERT INTO article_workflows VALUES(?,?,?,?,?)',[$key,'main',$client['id'],'failed',Store::json($job)]);
denied(fn()=>$workflow->call($newAuth,['post_id'=>1,'action'=>'resume','apply'=>true]),'uncertain generation cannot be retried');
$recovered=$workflow->call($newAuth,['post_id'=>1,'action'=>'resume','apply'=>true,'media_id'=>10]);
check($recovered['media_id']===10 && $recovered['stage']==='image_saved','resume recovers site-owned media');
$workflow->run();
check($workflow->call($newAuth,['post_id'=>1,'action'=>'status'])['status']==='completed','recovered media completes without generation');
[$second]=connection($o,'main',Policy::scopes('main'));
denied(fn()=>$workflow->call($second,['post_id'=>1,'action'=>'status']),'different access cannot read workflow');
// Provider completed and the article was published, but the workflow lost its checkpoint.
$c->db->prepare('UPDATE media SET metadata=?,created_at=? WHERE id=10')->execute([json_encode(['ai_generation'=>['request_id'=>$key]]),'2026-01-01 00:00:00']);
$lost=$s->one('SELECT data FROM article_workflows WHERE id=?',[$key]);
$lost=json_decode($lost['data'],true); unset($lost['media_id'],$lost['public_url']);
$lost['status']='failed';$lost['stage']='generating';$lost['error']='Interrupted';
$s->run('UPDATE article_workflows SET status=?,data=? WHERE id=?',['failed',Store::json($lost),$key]);
$before=$c->db->query('SELECT updated_at FROM blog_posts WHERE id=1')->fetchColumn();
$reconciled=$workflow->call($newAuth,['post_id'=>1,'action'=>'status']);
check($reconciled['status']==='completed' && $reconciled['media_id']===10 && !isset($reconciled['error']),'lost checkpoint reconciled from matching request metadata and published cover');
check($c->db->query('SELECT updated_at FROM blog_posts WHERE id=1')->fetchColumn()===$before,'reconciliation does not republish article');
$client['auto_approve_images']=false;$s->putClient($client);
$job['id']='revoked';$job['post']=1;$job['stage']='image_saved';$job['status']='queued';
$s->run('INSERT INTO article_workflows VALUES(?,?,?,?,?)',['revoked','main',$client['id'],'queued',Store::json($job)]);
$workflow->run();check($s->one('SELECT status FROM article_workflows WHERE id=?',['revoked'])['status']==='failed','revoked automation fails before work');
echo "PASS: workflow checkpoints, publication, deduplication and permissions; no provider calls.\n";
