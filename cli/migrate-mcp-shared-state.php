<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/core/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\Store;
$config=require APP_ROOT.'/config/config.php';$driver=Screenart\Musedock\Database::getDriver();$pg=new PDO($driver->getDSN($config['db']['host'],$config['db']['name'],$config['db']['port']??null),$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$dest=new Store($pg);
$directory=(string)Screenart\Musedock\Env::get('MCP_CMS_PRIVATE_DIRECTORY',dirname(APP_ROOT).'/.musedock-cms');$lock=fopen($directory.'/approvals-worker.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Worker active: retry migration later');
$src=new PDO('sqlite:'.$directory.'/mcp.sqlite');$src->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$src->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$src->exec('BEGIN IMMEDIATE');
try {
 $backup=$directory.'/state-before-shared-'.gmdate('YmdHis').'.json';$snapshot=[];foreach(Store::TABLES as$table)$snapshot[$table]=$src->query('SELECT * FROM '.$table)->fetchAll();file_put_contents($backup,json_encode($snapshot));chmod($backup,0600);
 $pg->beginTransaction();foreach($snapshot as$table=>$rows){foreach($rows as$row){$cols=array_keys($row);$q=$pg->prepare('INSERT INTO mcp_state_'.$table.'('.implode(',',$cols).') VALUES('.implode(',',array_fill(0,count($cols),'?')).') ON CONFLICT DO NOTHING');$q->execute(array_values($row));}echo $table.': '.count($rows)." rows copied\n";}
 $pg->exec("SELECT setval(pg_get_serial_sequence('mcp_state_events','id'),GREATEST(COALESCE((SELECT MAX(id) FROM mcp_state_events),1),1))");$pg->commit();
 $path=APP_ROOT.'/.env';$text=file_get_contents($path);$line='MCP_CMS_STATE_DRIVER=pgsql';$text=preg_match('/^MCP_CMS_STATE_DRIVER=.*$/m',$text)?preg_replace('/^MCP_CMS_STATE_DRIVER=.*$/m',$line,$text):rtrim($text)."\n".$line."\n";$temp=tempnam(APP_ROOT,'.mcp-state-env-');file_put_contents($temp,$text);chmod($temp,fileperms($path)&0777);rename($temp,$path);$src->exec('COMMIT');
 echo "Shared PostgreSQL state enabled. Existing clients, grants and token hashes preserved.\n";
} catch(Throwable $e){if($pg->inTransaction())$pg->rollBack();$src->exec('ROLLBACK');throw $e;}finally{flock($lock,LOCK_UN);fclose($lock);}
