<?php
require __DIR__.'/mcp/bootstrap.php';
use Screenart\Musedock\{Database,TenantPluginManager};
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));
$db->exec('CREATE TABLE tenant_plugins(id INTEGER PRIMARY KEY,tenant_id INTEGER,slug TEXT,name TEXT,description TEXT,version TEXT,author TEXT,active INTEGER,settings TEXT,permissions TEXT,updated_at TEXT)');
$connection=new ReflectionProperty(Database::class,'connection');$connection->setValue(null,$db);
$dir=sys_get_temp_dir().'/tenant-shared-test-'.bin2hex(random_bytes(6));mkdir($dir.'/tenant-shared/test-shared',0700,true);
file_put_contents($dir.'/tenant-shared/test-shared/plugin.json',json_encode(['name'=>'Test shared','version'=>'1.0','settings'=>[]]));$_ENV['PRIVATE_PLUGINS_PATH']=$dir;
try {
 if(!TenantPluginManager::activateSharedPlugin(40,'test-shared'))throw new RuntimeException('Private shared activation failed');
 $db->exec("INSERT INTO tenant_plugins(tenant_id,slug,active) VALUES(41,'test-shared',1)");
 if(!TenantPluginManager::deactivateSharedPlugin(40,'test-shared'))throw new RuntimeException('Deactivation failed');
 if((int)$db->query('SELECT active FROM tenant_plugins WHERE tenant_id=41')->fetchColumn()!==1)throw new RuntimeException('Foreign tenant changed');
 if(!TenantPluginManager::activateSharedPlugin(40,'test-shared') || (int)$db->query('SELECT COUNT(*) FROM tenant_plugins WHERE tenant_id=40')->fetchColumn()!==1)throw new RuntimeException('Reactivation duplicated installation');
 if(TenantPluginManager::activateSharedPlugin(40,'../test-shared'))throw new RuntimeException('Traversal accepted');
 echo "PASS: shared plugins from private directory, activation, deactivation, isolation, reactivation and invalid slug rejection. No production changes.\n";
} finally {unlink($dir.'/tenant-shared/test-shared/plugin.json');rmdir($dir.'/tenant-shared/test-shared');rmdir($dir.'/tenant-shared');rmdir($dir);}
