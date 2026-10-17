<?php
require __DIR__.'/run.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Authorization,Content,Tools,Policy,ArticleWorkflow};
$s=new Store(new PDO('sqlite::memory:'));$c=new Content(fixture());$o=new Authorization($s);
require dirname(__DIR__,2).'/database/migrations/2026_10_17_150000_add_mcp_article_receipts.php';
(new AddMcpArticleReceipts_2026_10_17_150000)->up();
$r=resource($s,'main','https://cms.example/mcp');$r['allow_publishing']=true;$s->putResource($r);
[$auth]=connection($o,'main',Policy::scopes('main'));
$client=$auth['client'];$client['auto_approve_posts']=true;$client['auto_approve_images']=true;$s->putClient($client);$auth['client']=$client;
$c->db->exec("INSERT INTO media(id,tenant_id,filename,mime_type,public_token,slug,seo_filename) VALUES(10,NULL,'cover.jpg','image/jpeg','cover-token','cover','cover-token.jpg')");
$calls=0;
$generator=function($site,$actor,$args)use(&$calls){$calls++;check(str_contains($args['prompt'],'Sin texto'),'workflow enforces no-text instruction');return ['media'=>['id'=>10]];};
$w=new ArticleWorkflow($s,$c,$generator);
$args=['title'=>'One call article','content'=>'<p>HTML body for SEO</p>','prompt'=>'Editorial cover','category_ids'=>[1],'idempotency_key'=>'article-001','apply'=>true];
// Initiate through the actual MCP tool; worker uses isolated fake image generation.
$start=(new Tools($s,$c))->call('create_and_publish_article',$auth,$args);
check($start['status']==='queued' && $start['post_id']===0,'one call enqueues creation');
$again=$w->call($auth,$args,true);check($again['job_id']===$start['job_id'],'same key reuses job');
$w->run();$result=$w->call($auth,['job_id'=>$start['job_id'],'action'=>'status'],true);
check($result['status']==='completed' && $result['post_id']>4 && !empty($result['public_url']),'complete publication and public link');
check($calls===1,'exactly one generation');
$post=$c->db->query('SELECT * FROM blog_posts WHERE id='.(int)$result['post_id'])->fetch(PDO::FETCH_ASSOC);
check($post['status']==='published' && !empty($post['featured_image']) && !empty($post['seo_description']),'image and SEO saved before publication');
$w->call($auth,$args,true);$w->run();check($calls===1,'retry never generates twice');
check($c->db->query("SELECT COUNT(*) FROM blog_posts WHERE title='One call article'")->fetchColumn()==1,'retry never creates duplicate article');
// Simulated restart: lost SQLite post checkpoint, CMS receipt remains in the committed transaction.
$row=$s->one('SELECT data FROM article_workflows WHERE id=?',[$start['job_id']]);$job=json_decode($row['data'],true);
$job['post']=0;$job['stage']='creating';$job['status']='executing';
$s->run('UPDATE article_workflows SET status=?,data=? WHERE id=?',['executing',Store::json($job),$job['id']]);
$restart=new ArticleWorkflow($s,$c,$generator);$restart->run();
$state=$restart->call($auth,['job_id'=>$job['id'],'action'=>'status'],true);
check($state['status']==='completed' && $state['post_id']===$result['post_id'],'restart reconciles committed receipt without duplication');
check($calls===1,'restart never repeats image');
// Draft-only mode completes with cover, never publishes.
$draftArgs=$args;$draftArgs['idempotency_key']='draft-002';$draftArgs['mode']='draft';$draftArgs['title']='Draft only';
$draft=$w->call($auth,$draftArgs,true);$w->run();$draft=$w->call($auth,['job_id'=>$draft['job_id'],'action'=>'status'],true);
check($draft['status']==='completed' && $draft['stage']==='draft_ready' && empty($draft['public_url']),'explicit draft mode has no public URL');
check($c->db->query('SELECT status FROM blog_posts WHERE id='.(int)$draft['post_id'])->fetchColumn()==='draft','draft mode stays unpublished');
// Generation failure preserves draft and cannot be repeated if its outcome is uncertain.
$failedArgs=$args;$failedArgs['idempotency_key']='failure-003';$failedArgs['title']='Failed image';
$failed=$w->call($auth,$failedArgs,true);
$failureWorker=new ArticleWorkflow($s,$c,function(){throw new RuntimeException('Provider interrupted');});$failureWorker->run();
$failed=$failureWorker->call($auth,['job_id'=>$failed['job_id'],'action'=>'status'],true);
check($failed['status']==='failed' && $failed['stage']==='generating','image error preserved');
check($c->db->query('SELECT status FROM blog_posts WHERE id='.(int)$failed['post_id'])->fetchColumn()==='draft','image failure leaves draft');
denied(fn()=>$failureWorker->call($auth,['job_id'=>$failed['job_id'],'action'=>'resume','apply'=>true],true),'uncertain provider not retried automatically');
$failureWorker->call($auth,['job_id'=>$failed['job_id'],'action'=>'resume','apply'=>true,'media_id'=>10],true);$w->run();
check($w->call($auth,['job_id'=>$failed['job_id'],'action'=>'status'],true)['status']==='completed','recovery reuses existing image');
check(!empty($w->call($auth,['job_id'=>$failed['job_id'],'action'=>'status'],true)['errors']),'error history survives successful recovery');
// Publication failure keeps the confirmed image and resumes without a provider call.
$publishArgs=$args;$publishArgs['idempotency_key']='publication-failure-004';$publishArgs['title']='Publication failure';
$publishJob=$w->call($auth,$publishArgs,true);
$publicationWorker=new ArticleWorkflow($s,$c,function()use($c){$c->db->exec("UPDATE blog_posts SET published_at='2099-01-01 00:00:00' WHERE title='Publication failure'");return ['media'=>['id'=>10]];});
$publicationWorker->run();$state=$w->call($auth,['job_id'=>$publishJob['job_id'],'action'=>'status'],true);
check($state['status']==='failed' && $state['stage']==='image_assigned','publication failure preserves cover checkpoint');
check($c->db->query('SELECT status FROM blog_posts WHERE id='.(int)$state['post_id'])->fetchColumn()==='draft','publication failure stays draft');
$c->db->exec('UPDATE blog_posts SET published_at=NULL WHERE id='.(int)$state['post_id']);
$beforeCalls=$calls;$w->call($auth,['job_id'=>$state['job_id'],'action'=>'resume','apply'=>true],true);$w->run();
check($w->call($auth,['job_id'=>$state['job_id'],'action'=>'status'],true)['status']==='completed' && $calls===$beforeCalls,'publication resume never regenerates confirmed image');
// Draft-only mode works without publication scopes, while publish mode is rejected.
[$limited]=connection($o,'main',[Policy::READ,Policy::EDITOR,Policy::MEDIA]);
$limitedClient=$limited['client'];$limitedClient['auto_approve_posts']=true;$limitedClient['auto_approve_images']=true;$s->putClient($limitedClient);$limited['client']=$limitedClient;
$limitedArgs=$draftArgs;$limitedArgs['idempotency_key']='limited-draft-005';$limitedArgs['title']='Limited draft';
$limitedJob=$w->call($limited,$limitedArgs,true);$w->run();
check($w->call($limited,['job_id'=>$limitedJob['job_id'],'action'=>'status'],true)['stage']==='draft_ready','draft mode does not require publication scope');
$limitedArgs['mode']='publish';$limitedArgs['idempotency_key']='denied-publish-006';
denied(fn()=>$w->call($limited,$limitedArgs,true),'publish mode requires publication scope');
denied(fn()=>$w->call($auth,['site_id'=>1]+$args,true),'foreign site cannot be targeted');
[$other]=connection($o,'main',Policy::scopes('main'));
denied(fn()=>$w->call($other,['job_id'=>$start['job_id'],'action'=>'status'],true),'another client cannot inspect the job');
echo "PASS: one-call creation, SEO, publication, draft mode, restart receipts, deduplication, failure recovery and access isolation. No paid provider calls.\n";
