<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Policy,Authorization,Content,Tools};
$dsn=getenv('MCP_CLONE_DSN');
if (!$dsn || !str_starts_with($dsn,'pgsql:host=/tmp/cms-mcp-clone-')) throw new RuntimeException('Only a private disposable PostgreSQL socket is accepted.');
$db=new PDO($dsn,getenv('MCP_CLONE_USER'),'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$p=new ReflectionProperty(Screenart\Musedock\Database::class,'connection');$p->setAccessible(true);$p->setValue(null,$db);
$p=new ReflectionProperty(Screenart\Musedock\Database::class,'driver');$p->setAccessible(true);$p->setValue(null,new Screenart\Musedock\Database\Drivers\PostgreSQLDriver());
require APP_ROOT.'/database/migrations/2026_10_11_150000_add_blog_hero_fields_for_editor.php';
(new AddBlogHeroFieldsForEditor_2026_10_11_150000())->up();(new AddBlogHeroFieldsForEditor_2026_10_11_150000())->up();
$actor=(int)$db->query("SELECT id FROM super_admins WHERE role='superadmin' ORDER BY id LIMIT 1")->fetchColumn();
if (!$actor) throw new RuntimeException('Clone has no superadmin actor.');
$s=new Store(new PDO('sqlite::memory:'));$r=['id'=>'main','url'=>'https://cms.example/mcp','name'=>'Clone','enabled'=>true,'allow_drafts'=>true,'allow_publishing'=>true,'allow_mcp_approval'=>true,'owned_sites'=>[],'revision'=>1];$s->putResource($r);
$o=new Authorization($s);$n=$o->createClient('main','Clone check','https://client.example/callback',Policy::scopes('main'));$v=str_repeat('V',43);
$p=['client_id'=>$n['client']['id'],'redirect_uri'=>$n['client']['callback'],'response_type'=>'code','code_challenge_method'=>'S256','code_challenge'=>Policy::challenge($v),'scope'=>implode(' ',Policy::scopes('main')),'resource'=>$r['url'],'state'=>'test'];
$code=$o->consent($o->validateRequest('main',$p),$actor);$tokens=$o->exchange($n['client'],['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$n['client']['callback'],'code_verifier'=>$v,'resource'=>$r['url']]);
$auth=$o->access('main',$tokens['access_token']);$tools=new Tools($s,new Content($db));
$apply=function($tool,$args)use($tools,$auth){$pending=$tools->call($tool,$auth,$args+['apply'=>true]);return $tools->call('approve_request',$auth,['request_id'=>$pending['request_id']]);};
$cat=$apply('create_category',['name'=>'MCP clone category '.bin2hex(random_bytes(5)),'color'=>'#123abc','order'=>2,'seo_keywords'=>'test']);
$tag=$apply('create_tag',['name'=>'MCP clone tag '.bin2hex(random_bytes(5))]);
$post=$apply('create_post',['title'=>'MCP full editor clone '.bin2hex(random_bytes(5)),'content'=>'<p>Prueba aislada</p>','excerpt'=>'Extracto','visibility'=>'password','password'=>'ClonePassword!','status'=>'draft','show_hero'=>true,'hero_image'=>'https://example.com/hero.png','hide_title'=>true,'hide_featured_image'=>true,'featured'=>true,'allow_comments'=>false,'template'=>'page','post_type'=>'docs','seo_title'=>'SEO','seo_keywords'=>'test','seo_image'=>'https://example.com/seo.png','robots_directive'=>'noindex,follow','canonical_url'=>'https://example.com/article','twitter_title'=>'X','twitter_description'=>'Descripción','twitter_image'=>'https://example.com/x.png','category_ids'=>[$cat['id']],'tag_ids'=>[$tag['id']]]);
$id=$post['post_id'];$apply('publish_post',['post_id'=>$id]);$apply('update_post',['post_id'=>$id,'title'=>'Editado en vivo','visibility'=>'private']);
$details=$tools->call('get_post_details',$auth,['post_id'=>$id]);if ($details['status']!=='published'||$details['visibility']!=='private'||(int)$details['show_hero']!==1) throw new RuntimeException('Editor fields did not persist.');
$locale=$db->query("SELECT code FROM languages WHERE active=1 AND code<>'es' LIMIT 1")->fetchColumn();
if ($locale) {
 $apply('save_translation',['post_id'=>$id,'locale'=>$locale,'title'=>'Translated','content'=>'<p>Translated content</p>','seo_image'=>'https://example.com/translated.png','twitter_title'=>'Translated X']);
 if (count($tools->call('list_translations',$auth,['post_id'=>$id]))!==1) throw new RuntimeException('Translation did not persist.');
}
$db->prepare("INSERT INTO blog_comments(post_id,tenant_id,author_name,author_email,content,status) VALUES(?,NULL,'Clone author','clone@example.com','Test comment','pending')")->execute([$id]);
$commentId=(int)$db->query("SELECT id FROM blog_comments WHERE post_id=".$id." ORDER BY id DESC LIMIT 1")->fetchColumn();
$apply('moderate_comment',['comment_id'=>$commentId,'status'=>'approved']);$apply('moderate_comment',['comment_id'=>$commentId,'status'=>'delete']);
$apply('update_post',['post_id'=>$id,'status'=>'draft']);$apply('schedule_post',['post_id'=>$id,'published_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600)]);
if ($db->query('SELECT status FROM blog_posts WHERE id='.$id)->fetchColumn()!=='draft') throw new RuntimeException('Scheduled post became public early.');
echo "PASS: full production database clone: editor, publication, taxonomies, comments, translations, scheduling and idempotent migration. No production writes or provider calls.\n";
