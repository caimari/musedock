<?php
use Screenart\Musedock\Services\Mcp\Direct\Policy;
// Uses the existing isolated CMS/OAuth fixtures from run.php.
$content->db->exec("CREATE TABLE news_aggregator_sources(id INTEGER PRIMARY KEY,tenant_id INTEGER,name TEXT,source_type TEXT,url TEXT,api_key TEXT,enabled INTEGER);
CREATE TABLE news_aggregator_source_feeds(id INTEGER PRIMARY KEY,source_id INTEGER,name TEXT,url TEXT,enabled INTEGER,last_fetch_at TEXT,last_fetch_count INTEGER,fetch_error TEXT);
CREATE TABLE news_aggregator_clusters(id INTEGER PRIMARY KEY,tenant_id INTEGER,title TEXT,source_count INTEGER,status TEXT,source_id INTEGER,created_at TEXT,updated_at TEXT);
CREATE TABLE news_aggregator_settings(tenant_id INTEGER,rewrite_prompt TEXT,output_language TEXT,generate_translations INTEGER,translation_instructions TEXT);
INSERT INTO news_aggregator_settings VALUES(1,'Enfoque propio','es',0,'{}');
ALTER TABLE news_aggregator_items ADD COLUMN source_context TEXT;
ALTER TABLE news_aggregator_items ADD COLUMN cluster_id INTEGER;
ALTER TABLE news_aggregator_items ADD COLUMN research_context TEXT;
UPDATE news_aggregator_items SET source_id=1,cluster_id=1,research_context='[{\"url\":\"https://source.example/reference\",\"content\":\"Contexto\"}]' WHERE id=1;
UPDATE news_aggregator_items SET source_id=2,cluster_id=2 WHERE id=2;
INSERT INTO news_aggregator_sources VALUES(1,1,'Source','rss','https://source.example/feed','PRIVATE-API-SECRET',1),(2,3,'Private source','rss','https://private.example/feed','OTHER-SECRET',1);
INSERT INTO news_aggregator_source_feeds VALUES(1,1,'Feed','https://source.example/feed',1,NULL,1,NULL),(2,2,'Private','https://private.example/feed',1,NULL,1,NULL);
INSERT INTO news_aggregator_clusters VALUES(1,1,'Event',2,'ready',1,NULL,NULL),(2,3,'Private event',2,'ready',2,NULL,NULL);");
foreach (\Screenart\Musedock\Services\Mcp\Direct\NewsReader::TOOLS as $tool) {
 check(in_array($tool,array_column($tools->catalog($news),'name'),true),'news tool exposed to authorized aggregator');
 check(!in_array($tool,array_column($tools->catalog($full),'name'),true),'news tool hidden from cross publisher');
}
$item=$tools->call('get_news_item',$news,['site_id'=>1,'item_id'=>1]);
check($item['original_content']==='Texto sin IA'&&$item['source']['feeds'][0]['name']==='Feed','full item and its source feeds returned');
check(!str_contains(json_encode($item),'PRIVATE-API-SECRET'),'source API credentials never exposed');
$analysis=$tools->call('get_news_analysis',$news,['site_id'=>1,'item_id'=>1]);
check($analysis['new_ai_call']===false&&$analysis['analysis']['research_context'][0]['content']==='Contexto','existing analysis retrieved without paid calls');
$cluster=$tools->call('get_news_cluster',$news,['site_id'=>1,'cluster_id'=>1]);
check(count($cluster['items'])===1&&$cluster['items'][0]['id']===1,'cluster details retain tenant scope');
foreach (['get_news_item'=>'item_id','get_news_source'=>'source_id','get_news_cluster'=>'cluster_id'] as $tool=>$key) denied(fn()=>$tools->call($tool,$news,['site_id'=>1,$key=>2]),'foreign news object denied');
check(count($tools->call('list_news_sources',$news,['site_id'=>1]))===1,'sources listing scoped');
check(count($tools->call('list_news_clusters',$news,['site_id'=>1]))===1,'clusters listing scoped');

// A tenant plugin resource is an independent audience with a fixed site, without groups.
$content->db->exec('UPDATE tenants SET group_id=NULL WHERE id=1');
$rid=Policy::tenantNews(1);resource($store,$rid,'https://a.example/mcp/news-aggregator');
[$tenantNews]=connection($oauth,$rid,[Policy::READ,Policy::IMPORT],[],[],11);
check($content->sites($tenantNews['resource'],$tenantNews['client'])===[1],'tenant news fixed site without editorial group');
check(count($tools->call('list_news_items',$tenantNews,['status'=>'pending','source_id'=>1]))===1,'news list supports state and source filters');
denied(fn()=>$tools->call('get_news_item',$tenantNews,['item_id'=>2]),'tenant news rejects another tenant item');
denied(fn()=>$tools->call('fetch_news_now',$tenantNews,['source_id'=>2]),'tenant news rejects another tenant source');
denied(fn()=>$tools->call('set_news_item_status',$tenantNews,['item_id'=>2,'status'=>'used']),'tenant news rejects foreign status change');
denied(fn()=>$tools->call('list_news_items',$tenantNews,['site_id'=>3]),'tenant news cannot spoof site');
$change=$tools->call('set_news_item_status',$tenantNews,['item_id'=>1,'status'=>'rejected','note'=>'Idea descartada','apply'=>true]);
check($content->db->query('SELECT status FROM news_aggregator_items WHERE id=1')->fetchColumn()==='pending','news mutation waits for approval');
$tools->decide($rid,$change['request_id'],11,true);
check($content->db->query('SELECT status FROM news_aggregator_items WHERE id=1')->fetchColumn()==='rejected','approved news status applied');
$link=$tools->call('link_news_item_to_post',$tenantNews,['item_id'=>1,'post_id'=>2,'apply'=>true]);
$tools->decide($rid,$link['request_id'],11,true);
check($content->db->query('SELECT status FROM news_aggregator_items WHERE id=1')->fetchColumn()==='used','linked idea marked used');
check((int)$content->db->query('SELECT created_post_id FROM news_aggregator_items WHERE id=1')->fetchColumn()===2,'own post linked');
check($tools->call('list_news_items',$tenantNews,['status'=>'used'])[0]['has_linked_article']==1,'linked idea visible in list');
$content->db->exec("UPDATE tenant_plugins SET active=0 WHERE tenant_id=1 AND slug='news-aggregator'");
check(!\Screenart\Musedock\Services\Mcp\Direct\Endpoint::installed($rid),'tenant plugin connection disabled with plugin');
denied(fn()=>$tools->call('get_news_item',$tenantNews,['item_id'=>1]),'existing tenant key stops after plugin deactivation');
$content->db->exec("UPDATE tenant_plugins SET active=1 WHERE tenant_id=1 AND slug='news-aggregator'");

[$readNews]=connection($oauth,$rid,[Policy::READ],[],[],11);
check(!in_array('set_news_item_status',array_column($tools->catalog($readNews),'name'),true),'news mutations require IMPORT scope');
