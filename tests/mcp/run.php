<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Policy,Authorization,Content,Tools,Network};
use Screenart\Musedock\Security\IPHelper;

$count=0;
function check(bool $condition,string $label): void { global $count; if (!$condition) throw new RuntimeException('FAIL: '.$label); $count++; }
function denied(callable $fn,string $label): void { try { $fn(); } catch (Throwable $e) { check(true,$label); return; } check(false,$label); }
check(Policy::clientCallback('chatgpt','https://attacker.example/callback')==='https://chatgpt.com/connector_platform_oauth_redirect','ChatGPT callback is fixed server-side');
check(Policy::clientCallback('claude')==='https://claude.ai/api/mcp/auth_callback','Claude callback preset');
check(Policy::clientCallback('custom',' https://client.example/return ')==='https://client.example/return','Custom exact HTTPS callback');
denied(fn()=>Policy::clientCallback('custom',''),'Empty custom callback denied');
denied(fn()=>Policy::clientCallback('custom','http://client.example/return'),'Non-HTTPS custom callback denied');
denied(fn()=>Policy::clientCallback('unknown'),'Unknown provider denied');
$store=new Store(new PDO('sqlite::memory:')); $content=new Content(fixture()); $oauth=new Authorization($store); $tools=new Tools($store,$content);
function resource(Store $s,string $id,string $url,array $owned=[]): array {
 $r=['id'=>$id,'url'=>$url,'name'=>$id,'enabled'=>true,'allow_drafts'=>true,'owned_sites'=>$owned,'revision'=>1]; $s->putResource($r); return $r;
}
resource($store,'main','https://cms.example/mcp');resource($store,'tenant:1','https://a.example/mcp');
resource($store,'plugin:cross-publisher','https://cms.example/mcp/cross-publisher',[1,2]);
resource($store,'plugin:news-aggregator','https://cms.example/mcp/news-aggregator',[1,2]);
function connection(Authorization $o,string $r,array $scopes,array $sites=[],array $groups=[],int $actor=7): array {
 $new=$o->createClient($r,'Test','https://chatgpt.com/callback',$scopes,$sites,$groups);
 $c=$new['client']; $verifier=str_repeat('A',43);
 $p=['client_id'=>$c['id'],'redirect_uri'=>$c['callback'],'response_type'=>'code','code_challenge_method'=>'S256','code_challenge'=>Policy::challenge($verifier),'scope'=>implode(' ',$scopes),'resource'=>$o->store->resource($r)['url'],'state'=>'state'];
 $pending=$o->validateRequest($r,$p); $code=$o->consent($pending,$actor);
 $auth=$o->authenticateClient($r,'Basic '.base64_encode($c['id'].':'.$new['secret']));
 $exchange=['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$c['callback'],'code_verifier'=>$verifier,'resource'=>$p['resource']];
 $tokens=$o->exchange($auth,$exchange); return [$o->access($r,$tokens['access_token']),$tokens,$c,$exchange,$new,$p];
}
[$main,$tokens,$c,$exchange,$new,$p]=connection($oauth,'main',[Policy::READ,Policy::CREATE,Policy::UPDATE]);
check($new['secret']!==$c['secret_hash'],'secret hashed');
check(!str_contains(Store::json($store->all('SELECT * FROM tokens')),$tokens['access_token']),'tokens hashed');
denied(fn()=>$oauth->exchange($c,$exchange),'authorization code single use');
denied(fn()=>$oauth->authenticateClient('main','Basic '.base64_encode($c['id'].':wrong')),'wrong client secret');
denied(fn()=>$oauth->authenticateClient('tenant:1','Basic '.base64_encode($c['id'].':'.$new['secret'])),'client resource binding');
denied(fn()=>$oauth->access('tenant:1',$tokens['access_token']),'access token audience tenant');
denied(fn()=>$oauth->access('plugin:cross-publisher',$tokens['access_token']),'access token audience plugin');
denied(fn()=>$oauth->validateRequest('main',array_replace($p,['redirect_uri'=>'https://evil.example/callback'])),'exact callback');
denied(fn()=>$oauth->validateRequest('main',array_replace($p,['code_challenge_method'=>'plain'])),'S256 mandatory');
denied(fn()=>$oauth->validateRequest('main',array_replace($p,['resource'=>'https://cms.example/mcp/cross-publisher'])),'authorization resource exact');
denied(fn()=>$oauth->validateRequest('main',array_replace($p,['scope'=>'musedock-cms:publish'])),'no publish scope');
foreach (['https://evil.example/*','https://good.example/#fragment','https://user:pass@example.com/callback','https://example.com\\@evil.com','http://example.com/callback'] as $uri) check(!Policy::callback($uri),'unsafe callback');
check(count($tools->call('list_posts',$main,[]))===1,'main lists only main');
check($tools->call('get_post',$main,['post_id'=>1])['title']==='Principal','main reads own');
denied(fn()=>$tools->call('get_post',$main,['post_id'=>3]),'main cannot read client by id');
denied(fn()=>$tools->call('list_posts',$main,['site_id'=>1]),'main cannot select tenant');
denied(fn()=>$tools->call('create_draft',$main,['title'=>'X','content'=>'X','tenant_id'=>3]),'extra tenant argument denied');
denied(fn()=>$tools->call('create_draft',$main,['title'=>'X','content'=>'X','status'=>'published']),'publication argument denied');
denied(fn()=>$tools->call('create_draft',$main,['title'=>'X','content'=>'X','category_ids'=>[3]]),'foreign category denied');
$names=array_column($tools->catalog($main),'name');
check(!in_array('copy_draft',$names,true)&&!in_array('delete_post',$names,true),'closed tool allowlist');
$before=$content->db->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
$plan=$tools->call('create_draft',$main,['title'=>'Manual sin IA','content'=>'<p>Texto</p><script>alert(1)</script>']);
check($plan['status']==='plan' && !str_contains($plan['plan']['fields']['content'],'<script'),'plan sanitized');
check($before==$content->db->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn(),'plan has no effects');
$request=$tools->call('create_draft',$main,['title'=>'Manual sin IA','content'=>'<p>Texto</p>','category_ids'=>[1],'apply'=>true]);
check($before==$content->db->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn(),'apply only enqueues');
denied(fn()=>$tools->decide('tenant:1',$request['request_id'],7,true),'request bound to resource');
denied(fn()=>$tools->decide('main',$request['request_id'],999,true),'approver must exist');
$result=$tools->decide('main',$request['request_id'],7,true);
$row=$content->db->query('SELECT * FROM blog_posts WHERE id='.$result['post_id'])->fetch(PDO::FETCH_ASSOC);
check($row['tenant_id']===null && $row['status']==='draft' && $row['published_at']===null,'approved main draft stays isolated');
check($content->db->query('SELECT COUNT(*) FROM slugs WHERE reference_id='.$result['post_id'])->fetchColumn()==1,'CMS slug created');
denied(fn()=>$tools->decide('main',$request['request_id'],7,true),'approval replay denied');
$update=$tools->call('update_draft',$main,['post_id'=>1,'content'=>'Actualizado','apply'=>true]);
$content->db->exec("UPDATE blog_posts SET content='Editado por humano' WHERE id=1");
denied(fn()=>$tools->decide('main',$update['request_id'],7,true),'changed plan needs new request');
$expired=$tools->call('create_draft',$main,['title'=>'Caduca','content'=>'X','apply'=>true]);
$store->run('UPDATE requests SET expires=? WHERE id=?',[time()-1,$expired['request_id']]);
denied(fn()=>$tools->decide('main',$expired['request_id'],7,true),'expired request denied');
[$cross]=connection($oauth,'plugin:cross-publisher',[Policy::READ,Policy::CREATE,Policy::COPY],[1,2,3],[10]);
check(count($tools->call('list_sites',$cross,[]))===2,'owned site plus group intersection');
denied(fn()=>$tools->call('get_post',$cross,['post_id'=>3]),'plugin cannot read client');
denied(fn()=>$tools->call('copy_draft',$cross,['post_id'=>2,'site_id'=>3]),'plugin cannot target client');
$copy=$tools->call('copy_draft',$cross,['post_id'=>2,'site_id'=>2,'apply'=>true]);
$copied=$tools->decide('plugin:cross-publisher',$copy['request_id'],7,true);
check($copied['site_id']===2&&$copied['status']==='draft','copy to owned site only');
denied(fn()=>$tools->call('update_draft',$cross,['post_id'=>4,'site_id'=>1,'title'=>'Touch live']),'scope update absent');
[$editor]=connection($oauth,'plugin:cross-publisher',[Policy::READ,Policy::UPDATE],[1,2],[10]);
denied(fn()=>$tools->call('update_draft',$editor,['post_id'=>4,'site_id'=>1,'title'=>'Touch live']),'published post cannot be edited');
[$news]=connection($oauth,'plugin:news-aggregator',[Policy::READ,Policy::IMPORT],[1,2],[10]);
check(count($tools->call('list_news_items',$news,['site_id'=>1]))===1,'news list isolated');
denied(fn()=>$tools->call('import_news_draft',$news,['site_id'=>1,'item_id'=>2]),'news item isolation');
$import=$tools->call('import_news_draft',$news,['site_id'=>1,'item_id'=>1,'apply'=>true]);
$imported=$tools->decide('plugin:news-aggregator',$import['request_id'],7,true);
check($imported['status']==='draft','news import stays draft');
denied(fn()=>$tools->call('import_news_draft',$news,['site_id'=>1,'item_id'=>1]),'news duplicate import denied');
[$readonly]=connection($oauth,'main',[Policy::READ]);
denied(fn()=>$tools->call('create_draft',$readonly,['title'=>'No','content'=>'No']),'read-only cannot write');
// Refresh rotation and reuse must revoke the entire family, including access and pending writes.
[$refreshAuth,$rt,$rc]=connection($oauth,'main',[Policy::READ,Policy::CREATE]);
$pending=$tools->call('create_draft',$refreshAuth,['title'=>'Revocado','content'=>'No','apply'=>true]);
$refresh=['grant_type'=>'refresh_token','resource'=>'https://cms.example/mcp','refresh_token'=>$rt['refresh_token']];
$rotated=$oauth->exchange($rc,$refresh);
check($rotated['refresh_token']!==$rt['refresh_token'],'refresh rotation');
denied(fn()=>$oauth->exchange($rc,$refresh),'refresh replay denied');
denied(fn()=>$oauth->access('main',$rotated['access_token']),'refresh replay revokes new access');
denied(fn()=>$tools->decide('main',$pending['request_id'],7,true),'revocation invalidates pending requests');
[$revised,$rTokens,$rClient]=connection($oauth,'main',[Policy::READ,Policy::CREATE]);
$oauth->reviseClient('main',$rClient['id'],[Policy::READ],true);
denied(fn()=>$oauth->access('main',$rTokens['access_token']),'permission change needs new consent');
[$removed,$removedTokens]=connection($oauth,'main',[Policy::READ]);
$content->db->exec('DELETE FROM super_admins WHERE id=7');
check(!$tools->catalog($removed),'deleted owner loses all tools');
$content->db->exec("INSERT INTO super_admins VALUES(7,'superadmin')");
$content->db->exec('UPDATE tenants SET group_id=20 WHERE id=2');
check(count($tools->call('list_sites',$cross,[]))===1,'group move removes access at execution');
// IP headers from untrusted peers must never authorize ingress.
$_SERVER=['REMOTE_ADDR'=>'203.0.113.9','HTTP_X_FORWARDED_FOR'=>'192.0.2.1','HTTP_CF_CONNECTING_IP'=>'192.0.2.1','HTTP_X_REAL_IP'=>'192.0.2.1'];
check(IPHelper::getRealIP()==='203.0.113.9','spoofed proxy headers ignored');
check(Network::allows(Network::parse('192.0.2.0/24'),'192.0.2.99'),'IPv4 network match');
check(!Network::allows(Network::parse('192.0.2.0/24'),IPHelper::getRealIP()),'spoofed IP cannot match');
check(Network::allows(Network::parse('2001:db8::/32'),'2001:db8::1234'),'IPv6 network match');
foreach (['192.0.2.0/-1','192.0.2.0/33','::/129','invalid/0'] as $cidr) denied(fn()=>Network::parse($cidr),'invalid network rejected');
for($i=0;$i<3;$i++) $store->limit('test-limit',3);
denied(fn()=>$store->limit('test-limit',3),'atomic rate limit');
check(!str_contains(Store::json($store->all('SELECT * FROM events')),'Texto sin IA'),'audit has no article bodies');
// Existing REST group keys: assert all direct-ID post entry points enforce canAccessTenant.
$api=file_get_contents(APP_ROOT.'/core/Controllers/Api/V1/PostController.php');
check(substr_count($api,'canAccessTenant')>=5,'REST show update delete and cross source/target guarded');
// Tenant identities remain isolated even when IDs overlap with a superadmin.
[$tenantAuth]=connection($oauth,'tenant:1',[Policy::READ,Policy::CREATE],[],[],11);
check(count($tools->call('list_posts',$tenantAuth,[]))===3,'tenant own content');
denied(fn()=>$tools->call('get_post',$tenantAuth,['post_id'=>1]),'tenant cannot read main');
denied(fn()=>$tools->call('get_post',$tenantAuth,['post_id'=>3]),'tenant cannot read other tenant');
// Exercise the actual REST controller, not only source patterns.
$content->db->exec("CREATE TABLE api_keys(id INTEGER PRIMARY KEY,tenant_id INTEGER,domain_group_id INTEGER,name TEXT,permissions TEXT,is_active INTEGER,expires_at TEXT,auth_type TEXT,user_id INTEGER); INSERT INTO api_keys VALUES(9,NULL,10,'Group','[\"posts.read\",\"posts.update\",\"posts.delete\",\"cross-publish\"]',1,NULL,'token',NULL)");
$key=\Screenart\Musedock\Models\ApiKey::find(9);
\Screenart\Musedock\Middlewares\ApiKeyAuth::beginInternal($key);
$controller=new \Screenart\Musedock\Controllers\Api\V1\PostController();
foreach (['show','update','destroy'] as $method) {
 try { $controller->$method(3); check(false,'REST foreign ID'); }
 catch (\Screenart\Musedock\Services\Mcp\ApiAbort $e) { check($e->status===404,'REST '.$method.' isolation'); }
}
$GLOBALS['_JSON_INPUT']=['target_tenant_ids'=>[3]];
try { $controller->crossPublish(2); check(false,'REST cross target'); }
catch (\Screenart\Musedock\Services\Mcp\ApiAbort $e) { check($e->status===403,'REST cross target isolation'); }
$GLOBALS['_JSON_INPUT']=['target_tenant_ids'=>[1]];
try { $controller->crossPublish(3); check(false,'REST cross source'); }
catch (\Screenart\Musedock\Services\Mcp\ApiAbort $e) { check($e->status===404,'REST cross source isolation'); }
\Screenart\Musedock\Middlewares\ApiKeyAuth::endInternal();
$job=(object)['origin_api_key_id'=>9,'source_tenant_id'=>1,'target_tenant_id'=>1,'source_post_id'=>2,'target_status'=>'draft'];
\Screenart\Musedock\Services\Mcp\CrossPublisherQueueGuard::assertAllowed($job);check(true,'queued API job permitted in scope');
$job->target_tenant_id=3;denied(fn()=>\Screenart\Musedock\Services\Mcp\CrossPublisherQueueGuard::assertAllowed($job),'queued job outside group denied');
$job->target_tenant_id=1;$content->db->exec('UPDATE api_keys SET is_active=0 WHERE id=9');
denied(fn()=>\Screenart\Musedock\Services\Mcp\CrossPublisherQueueGuard::assertAllowed($job),'queued job checks revocation');
$content->db->exec('UPDATE api_keys SET is_active=1 WHERE id=9');
$content->db->exec('UPDATE tenants SET group_id=10 WHERE id=2');
$key=\Screenart\Musedock\Models\ApiKey::find(9);
\Screenart\Musedock\Middlewares\ApiKeyAuth::beginInternal($key);
$GLOBALS['_JSON_INPUT']=['target_tenant_ids'=>[2]];
ob_start();$controller->crossPublish(2);$queued=json_decode(ob_get_clean(),true);
check($queued['results'][0]['status']==='queued','REST cross-publish uses real plugin queue table');
$queuedRow=$content->db->query('SELECT * FROM cross_publish_queue')->fetch(PDO::FETCH_ASSOC);
check((int)$queuedRow['origin_api_key_id']===9,'API origin retained for execution guard');
\Screenart\Musedock\Middlewares\ApiKeyAuth::endInternal();
$_ENV['MCP_CMS_DIRECT_ENABLED']=false;$_SERVER['HTTP_HOST']='cms.example';
$endpoint=new \Screenart\Musedock\Services\Mcp\Direct\Endpoint('main',$store);
check($endpoint->configuration()===null,'deployment gate blocks prepared resources');
$_ENV['MCP_CMS_DIRECT_ENABLED']=true;
check($endpoint->configuration()!==null,'operator gate and enabled resource both required');
echo "PASS: {$count} checks; isolated fixtures, no production changes.\n";
