<?php
// Dedicated temporary PostgreSQL schema; never uses production MCP tables.
$dsn=getenv('MCP_TEST_SHARED_PG_DSN');
if(!$dsn){echo "SKIP: set MCP_TEST_SHARED_PG_DSN to test shared PostgreSQL state.\n";return;}
if(!class_exists(Screenart\Musedock\Services\Mcp\Direct\Store::class))require dirname(__DIR__,2).'/vendor/autoload.php';
use Screenart\Musedock\Services\Mcp\Direct\Store;
$pdo=new PDO($dsn,getenv('MCP_TEST_SHARED_PG_USER'),getenv('MCP_TEST_SHARED_PG_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$schema='mcp_isolated_'.bin2hex(random_bytes(6));$pdo->exec('CREATE SCHEMA '.$schema);$pdo->exec('SET search_path TO '.$schema);
try{
 $s=new Store($pdo);$s->putResource(['id'=>'main','enabled'=>true]);
 $s->atomic(function()use($s){$s->run("INSERT INTO requests(id,resource,client,status,expires,data) VALUES(?,?,?,?,?,?)",['request','main','client','pending',time()+100,Store::json(['grant'=>'g','created'=>time()])]);});
 $s->run('INSERT OR IGNORE INTO article_workflows(id,resource,client,status,data) VALUES(?,?,?,?,?)',['job','main','client','queued',Store::json(['created'=>time()])]);
 $s->run('INSERT OR IGNORE INTO article_workflows(id,resource,client,status,data) VALUES(?,?,?,?,?)',['job','main','client','queued','{}']);
 $other=new PDO($dsn,getenv('MCP_TEST_SHARED_PG_USER'),getenv('MCP_TEST_SHARED_PG_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$other->exec('SET search_path TO '.$schema);$reader=new Store($other);
 if(!$reader->resource('main') || !$reader->one('SELECT id FROM requests WHERE id=?',['request']))throw new RuntimeException('Cross-connection persistence failed');
 if(count($reader->all('SELECT data FROM article_workflows ORDER BY rowid DESC'))!==1)throw new RuntimeException('Idempotent insert failed');
 $reader->run("UPDATE requests SET status='revoked' WHERE json_extract(data,'$.grant')=?",['g']);if($s->one('SELECT status FROM requests WHERE id=?',['request'])['status']!=='revoked')throw new RuntimeException('JSON grant lookup failed');
 try{$s->atomic(function()use($s){$s->putResource(['id'=>'rollback']);throw new RuntimeException('Expected rollback');});}catch(RuntimeException $expected){}
 if($reader->resource('rollback'))throw new RuntimeException('Rollback failed');
 if($s->run("UPDATE article_workflows SET status='executing' WHERE id='job' AND status='queued'")!==1 || $reader->run("UPDATE article_workflows SET status='executing' WHERE id='job' AND status='queued'")!==0)throw new RuntimeException('Worker claim is not exclusive');
 echo "PASS: shared PostgreSQL persistence, independent connections, namespaced tables, atomic rollback, grant lookup, idempotence and exclusive worker claim.\n";
}finally{$pdo->exec('DROP SCHEMA '.$schema.' CASCADE');}
