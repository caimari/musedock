<?php
// Deliberately no core/bootstrap.php, production .env or Composer helpers.
define('APP_ROOT',dirname(__DIR__,2));
spl_autoload_register(function ($class) {
    $prefix='Screenart\\Musedock\\';
    if (str_starts_with($class,$prefix)) {
        $file=APP_ROOT.'/core/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if (is_file($file)) require $file;
    }
});
\Screenart\Musedock\Env::load('/nonexistent-cms-mcp-test-env');
function fixture(): PDO
{
    $pg=getenv('MCP_TEST_PG_DSN'); $dsn=$pg?:getenv('MCP_TEST_MYSQL_DSN');
    $p=$dsn?new PDO($dsn,$pg?getenv('MCP_TEST_PG_USER'):'root',''):new PDO('sqlite::memory:'); $p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $schema="CREATE TABLE mcp_taxonomy_policies(site_id BIGINT PRIMARY KEY,data TEXT NOT NULL);
        CREATE TABLE mcp_article_receipts(job_id VARCHAR(64) PRIMARY KEY,post_id BIGINT NOT NULL,site_id BIGINT NOT NULL);
        CREATE TABLE settings(key TEXT,value TEXT); INSERT INTO settings VALUES('force_lang','es');
        CREATE TABLE languages(code TEXT,active INTEGER); INSERT INTO languages VALUES('es',1);
        CREATE TABLE domain_groups(id INTEGER PRIMARY KEY,name TEXT); INSERT INTO domain_groups VALUES(10,'Propios'),(20,'Clientes');
        CREATE TABLE tenants(id INTEGER PRIMARY KEY,name TEXT,domain TEXT,group_id INTEGER,status TEXT);
        INSERT INTO tenants VALUES(1,'Propio A','a.example',10,'active'),(2,'Propio B','b.example',10,'active'),(3,'Cliente','client.example',20,'active');
        CREATE TABLE super_admins(id INTEGER PRIMARY KEY,role TEXT); INSERT INTO super_admins VALUES(7,'superadmin');
        CREATE TABLE admins(id INTEGER PRIMARY KEY,tenant_id INTEGER,role TEXT,is_root_admin INTEGER); INSERT INTO admins VALUES(11,1,'admin',1);
        CREATE TABLE users(id INTEGER PRIMARY KEY,tenant_id INTEGER);
        CREATE TABLE domain_aliases(id INTEGER PRIMARY KEY,tenant_id INTEGER,domain TEXT,status TEXT,include_www INTEGER DEFAULT 0);
        INSERT INTO domain_aliases VALUES(1,1,'public-a.example','active',1),(2,3,'private-client.example','active',0),(3,1,'disabled-a.example','inactive',0);
        CREATE TABLE tenant_settings(tenant_id INTEGER,key TEXT,value TEXT);
        CREATE TABLE blog_posts(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,user_type TEXT,title TEXT,slug TEXT,content TEXT,excerpt TEXT,status TEXT,base_locale TEXT DEFAULT 'es',seo_title TEXT,seo_description TEXT,updated_at TEXT,created_at TEXT,deleted_at TEXT,visibility TEXT,published_at TEXT,allow_comments INTEGER, password TEXT, featured_image TEXT, hide_featured_image INTEGER DEFAULT 0, template TEXT DEFAULT 'template-sidebar-right', featured INTEGER DEFAULT 0, hide_title INTEGER DEFAULT 0, post_type TEXT DEFAULT 'post', seo_keywords TEXT, canonical_url TEXT, robots_directive TEXT, seo_image TEXT, twitter_title TEXT, twitter_description TEXT, twitter_image TEXT, show_hero INTEGER DEFAULT 0, hero_image TEXT, comment_count INTEGER DEFAULT 0);
        INSERT INTO blog_posts(id,tenant_id,title,slug,content,excerpt,status,updated_at) VALUES(1,NULL,'Principal','principal','Contenido principal','','draft','2026-01-01'),(2,1,'Propio','propio','Contenido propio','','draft','2026-01-01'),(3,3,'Cliente privado','cliente','Secreto cliente','','draft','2026-01-01'),(4,1,'Publicado','publicado','Contenido público','','published','2026-01-01');
        CREATE TABLE blog_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,name TEXT,slug TEXT,post_count INTEGER DEFAULT 0,parent_id INTEGER,description TEXT,image TEXT,color TEXT,\"order\" INTEGER DEFAULT 0,seo_title TEXT,seo_description TEXT,seo_keywords TEXT,created_at TEXT,updated_at TEXT,deleted_at TEXT); INSERT INTO blog_categories(id,tenant_id,name,slug) VALUES(1,NULL,'Principal','principal'),(2,1,'Propio','propio'),(3,3,'Cliente','cliente');
        CREATE TABLE blog_tags(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,name TEXT,slug TEXT,post_count INTEGER DEFAULT 0,description TEXT,color TEXT,created_at TEXT,updated_at TEXT,deleted_at TEXT);
        CREATE TABLE cross_publish_relations(id INTEGER PRIMARY KEY AUTOINCREMENT,source_post_id INTEGER,source_tenant_id INTEGER,target_post_id INTEGER,target_tenant_id INTEGER,sync_enabled INTEGER,created_at TEXT,UNIQUE(source_post_id,source_tenant_id,target_tenant_id));
        CREATE TABLE blog_post_categories(post_id INTEGER,category_id INTEGER); CREATE TABLE blog_post_tags(post_id INTEGER,tag_id INTEGER);
        CREATE TABLE slugs(id INTEGER PRIMARY KEY AUTOINCREMENT,module TEXT,reference_id INTEGER,slug TEXT,tenant_id INTEGER,prefix TEXT,locale TEXT);
        CREATE TABLE cross_publish_queue(id INTEGER PRIMARY KEY AUTOINCREMENT,source_post_id INTEGER,source_tenant_id INTEGER,target_tenant_id INTEGER,target_status TEXT,status TEXT,created_at TEXT);
        CREATE TABLE news_aggregator_items(id INTEGER PRIMARY KEY,tenant_id INTEGER,original_title TEXT,original_content TEXT,original_url TEXT,rewritten_title TEXT,rewritten_content TEXT,rewritten_excerpt TEXT,status TEXT,created_post_id INTEGER);
        INSERT INTO news_aggregator_items VALUES(1,1,'Noticia','Texto sin IA','https://source.example/news',NULL,NULL,NULL,'pending',NULL),(2,3,'Privada','Privada','https://source.example/private',NULL,NULL,NULL,'pending',NULL);";
    if ($pg) {
        $schema=str_replace('INTEGER PRIMARY KEY AUTOINCREMENT','SERIAL PRIMARY KEY',$schema);
        $schema=str_replace('sync_enabled INTEGER','sync_enabled BOOLEAN',$schema);
    } elseif ($dsn) {
        $schema=str_replace(['INTEGER PRIMARY KEY AUTOINCREMENT','INTEGER PRIMARY KEY','key TEXT'],['INT PRIMARY KEY AUTO_INCREMENT','INT PRIMARY KEY','`key` VARCHAR(100)'],$schema);
    }
    $schema.="CREATE TABLE superadmin_plugins(id INTEGER PRIMARY KEY,slug TEXT,is_active INTEGER,is_installed INTEGER); INSERT INTO superadmin_plugins VALUES(1,'cross-publisher',1,1),(2,'news-aggregator',1,1);
    CREATE TABLE blog_comments(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,post_id INTEGER,author_name TEXT,author_email TEXT,content TEXT,status TEXT,ip_address TEXT,user_agent TEXT,approved_at TEXT,approved_by INTEGER,approved_by_type TEXT,created_at TEXT,updated_at TEXT);
    INSERT INTO blog_comments(tenant_id,post_id,author_name,author_email,content,status) VALUES(1,2,'Autor','private@example.com','Hola','pending'),(3,3,'Cliente','client@example.com','Secreto','pending');
    CREATE TABLE blog_post_translations(id INTEGER PRIMARY KEY AUTOINCREMENT,post_id INTEGER,tenant_id INTEGER,locale TEXT,title TEXT,content TEXT,excerpt TEXT,seo_title TEXT,seo_description TEXT,seo_keywords TEXT,seo_image TEXT,canonical_url TEXT,robots_directive TEXT,twitter_title TEXT,twitter_description TEXT,twitter_image TEXT,created_at TEXT,updated_at TEXT,UNIQUE(post_id,locale));
    CREATE TABLE media(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,filename TEXT,mime_type TEXT,alt_text TEXT,public_token TEXT,slug TEXT,seo_filename TEXT,metadata TEXT,created_at TEXT);
    INSERT INTO media(tenant_id,filename,mime_type,public_token,slug,seo_filename) VALUES(1,'own.png','image/png','own-token','own','own-token.png'),(3,'client.png','image/png','client-token','client','client-token.png');
    CREATE TABLE ai_image_providers(id INTEGER PRIMARY KEY,tenant_id INTEGER,system_wide INTEGER,active INTEGER,name TEXT,model TEXT,provider_type TEXT);
    INSERT INTO ai_image_providers VALUES(1,NULL,1,1,'Test image provider','test-model','openai');";
    if ($pg) $schema=str_replace('INTEGER PRIMARY KEY AUTOINCREMENT','SERIAL PRIMARY KEY',$schema);
    elseif ($dsn) $schema=str_replace('INTEGER PRIMARY KEY AUTOINCREMENT','INT PRIMARY KEY AUTO_INCREMENT',$schema);
    if ($dsn && !$pg) $schema=str_replace('"order"','`order`',$schema);
    $p->exec($schema);
    if ($pg) { $p->exec("SELECT setval('blog_posts_id_seq',4)");$p->exec("SELECT setval('blog_categories_id_seq',3)"); }
    if (!$dsn) { $p->exec('ALTER TABLE cross_publish_queue ADD COLUMN origin_api_key_id INTEGER NULL'); $p->sqliteCreateFunction('NOW',fn()=>gmdate('Y-m-d H:i:s')); }
    else { require_once APP_ROOT.'/database/migrations/2026_10_11_130000_add_cross_publish_api_origin.php'; }
    $property=new ReflectionProperty(\Screenart\Musedock\Database::class,'connection'); $property->setAccessible(true); $property->setValue(null,$p);
    if ($pg) { $driver=new ReflectionProperty(\Screenart\Musedock\Database::class,'driver'); $driver->setAccessible(true); $driver->setValue(null,new \Screenart\Musedock\Database\Drivers\PostgreSQLDriver()); }
    if ($dsn) { (new AddCrossPublishApiOrigin_2026_10_11_130000())->up(); (new AddCrossPublishApiOrigin_2026_10_11_130000())->up(); }
    return $p;
}
