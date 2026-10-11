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
    $dsn=getenv('MCP_TEST_MYSQL_DSN');
    $p=$dsn?new PDO($dsn,'root',''):new PDO('sqlite::memory:'); $p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $schema="CREATE TABLE settings(key TEXT,value TEXT); INSERT INTO settings VALUES('force_lang','es');
        CREATE TABLE languages(code TEXT,active INTEGER); INSERT INTO languages VALUES('es',1);
        CREATE TABLE tenants(id INTEGER PRIMARY KEY,name TEXT,domain TEXT,group_id INTEGER,status TEXT);
        INSERT INTO tenants VALUES(1,'Propio A','a.example',10,'active'),(2,'Propio B','b.example',10,'active'),(3,'Cliente','client.example',20,'active');
        CREATE TABLE super_admins(id INTEGER PRIMARY KEY,role TEXT); INSERT INTO super_admins VALUES(7,'superadmin');
        CREATE TABLE admins(id INTEGER PRIMARY KEY,tenant_id INTEGER,role TEXT,is_root_admin INTEGER); INSERT INTO admins VALUES(11,1,'admin',1);
        CREATE TABLE users(id INTEGER PRIMARY KEY,tenant_id INTEGER);
        CREATE TABLE tenant_settings(tenant_id INTEGER,key TEXT,value TEXT);
        CREATE TABLE blog_posts(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,user_type TEXT,title TEXT,slug TEXT,content TEXT,excerpt TEXT,status TEXT,base_locale TEXT DEFAULT 'es',seo_title TEXT,seo_description TEXT,updated_at TEXT,created_at TEXT,deleted_at TEXT,visibility TEXT,published_at TEXT,allow_comments INTEGER);
        INSERT INTO blog_posts(id,tenant_id,title,slug,content,excerpt,status,updated_at) VALUES(1,NULL,'Principal','principal','Contenido principal','','draft','2026-01-01'),(2,1,'Propio','propio','Contenido propio','','draft','2026-01-01'),(3,3,'Cliente privado','cliente','Secreto cliente','','draft','2026-01-01'),(4,1,'Publicado','publicado','Contenido público','','published','2026-01-01');
        CREATE TABLE blog_categories(id INTEGER PRIMARY KEY,tenant_id INTEGER,name TEXT,slug TEXT,post_count INTEGER DEFAULT 0); INSERT INTO blog_categories(id,tenant_id,name,slug) VALUES(1,NULL,'Principal','principal'),(2,1,'Propio','propio'),(3,3,'Cliente','cliente');
        CREATE TABLE blog_tags(id INTEGER PRIMARY KEY,tenant_id INTEGER,name TEXT,slug TEXT,post_count INTEGER DEFAULT 0);
        CREATE TABLE cross_publish_relations(id INTEGER PRIMARY KEY AUTOINCREMENT,source_post_id INTEGER,source_tenant_id INTEGER,target_post_id INTEGER,target_tenant_id INTEGER,sync_enabled INTEGER,created_at TEXT,UNIQUE(source_post_id,source_tenant_id,target_tenant_id));
        CREATE TABLE blog_post_categories(post_id INTEGER,category_id INTEGER); CREATE TABLE blog_post_tags(post_id INTEGER,tag_id INTEGER);
        CREATE TABLE slugs(id INTEGER PRIMARY KEY AUTOINCREMENT,module TEXT,reference_id INTEGER,slug TEXT,tenant_id INTEGER,prefix TEXT);
        CREATE TABLE cross_publish_queue(id INTEGER PRIMARY KEY AUTOINCREMENT,source_post_id INTEGER,source_tenant_id INTEGER,target_tenant_id INTEGER,target_status TEXT,status TEXT,created_at TEXT);
        CREATE TABLE news_aggregator_items(id INTEGER PRIMARY KEY,tenant_id INTEGER,original_title TEXT,original_content TEXT,original_url TEXT,rewritten_title TEXT,rewritten_content TEXT,rewritten_excerpt TEXT,status TEXT,created_post_id INTEGER);
        INSERT INTO news_aggregator_items VALUES(1,1,'Noticia','Texto sin IA','https://source.example/news',NULL,NULL,NULL,'pending',NULL),(2,3,'Privada','Privada','https://source.example/private',NULL,NULL,NULL,'pending',NULL);";
    if ($dsn) {
        $schema=str_replace(['INTEGER PRIMARY KEY AUTOINCREMENT','INTEGER PRIMARY KEY','key TEXT'],['INT PRIMARY KEY AUTO_INCREMENT','INT PRIMARY KEY','`key` VARCHAR(100)'],$schema);
    }
    $p->exec($schema);
    if (!$dsn) { $p->exec('ALTER TABLE cross_publish_queue ADD COLUMN origin_api_key_id INTEGER NULL'); $p->sqliteCreateFunction('NOW',fn()=>gmdate('Y-m-d H:i:s')); }
    else { require_once APP_ROOT.'/database/migrations/2026_10_11_130000_add_cross_publish_api_origin.php'; }
    $property=new ReflectionProperty(\Screenart\Musedock\Database::class,'connection'); $property->setAccessible(true); $property->setValue(null,$p);
    if ($dsn) { (new AddCrossPublishApiOrigin_2026_10_11_130000())->up(); (new AddCrossPublishApiOrigin_2026_10_11_130000())->up(); }
    return $p;
}
