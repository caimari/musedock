<?php
require __DIR__.'/run.php';
use Screenart\Musedock\Services\Mcp\Direct\{Store,Content,Authorization,Tools,Policy};
$s=new Store(new PDO('sqlite::memory:'));$c=new Content(fixture());$o=new Authorization($s);
resource($s,'main','https://cms.example/mcp');[$a]=connection($o,'main',[Policy::READ,Policy::EDITOR]);$tools=new Tools($s,$c);
$args=['title'=>'Receipt article','slug'=>'receipt-article','content'=>'<p>Persistent article</p>','apply'=>true];
$request=$tools->call('create_post',$a,$args);$id=$request['request_id'];$post=$tools->decide('main',$id,7,true);
check((int)$c->db->query("SELECT post_id FROM mcp_article_receipts WHERE job_id='$id'")->fetchColumn()===$post['post_id'],'receipt committed with create_post');
$s->run("UPDATE requests SET status='pending' WHERE id=?",[$id]);
$recovered=$tools->decide('main',$id,7,true);
check($recovered['recovered'] && $recovered['post_id']===$post['post_id'],'lost checkpoint recovers exact original article before slug validation');
check($c->db->query("SELECT COUNT(*) FROM blog_posts WHERE slug='receipt-article'")->fetchColumn()==1,'no duplicate and no URL change');
denied(fn()=>$tools->call('create_post',$a,$args),'another request may not adopt an article merely by slug');
echo "PASS: create_post transaction receipt and safe recovery without duplication.\n";
