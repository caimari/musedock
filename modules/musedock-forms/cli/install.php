<?php
if(PHP_SAPI!=='cli')exit;
define('APP_ROOT',dirname(__DIR__,3));require APP_ROOT.'/vendor/autoload.php';\Screenart\Musedock\Env::load();require_once APP_ROOT.'/core/helpers.php';
$o=getopt('',['tenant:','apply']);$tid=(int)($o['tenant']??0);$db=\Screenart\Musedock\Database::connect();
if(!$tid||!($q=$db->prepare('SELECT id FROM tenants WHERE id=?'))||!$q->execute([$tid])||!$q->fetchColumn())throw new \RuntimeException('Indica un tenant existente');
if(!isset($o['apply'])){echo "Simulación: instalaría MuseDock Forms y Members solo en tenant $tid. Usa --apply.\n";exit;}
foreach(['musedock-forms','musedock-members'] as $slug){$migration=require APP_ROOT.'/modules/'.$slug.'/migrate.php';$migration($db);$meta=json_decode(file_get_contents(APP_ROOT.'/modules/'.$slug.'/module.json'),true);
$q=$db->prepare('SELECT id FROM modules WHERE slug=?');$q->execute([$slug]);$id=$q->fetchColumn();if(!$id){$q=$db->prepare('INSERT INTO modules (slug,name,description,version,author,active,public,cms_enabled) VALUES (?,?,?,?,?,1,1,1) RETURNING id');$q->execute([$slug,$meta['name'],$meta['description'],$meta['version'],$meta['author']]);$id=$q->fetchColumn();}
if($slug==='musedock-forms'){require APP_ROOT.'/modules/musedock-forms/install.php';}
$q=$db->prepare('SELECT module_id FROM tenant_modules WHERE tenant_id=? AND module_id=?');$q->execute([$tid,$id]);$existing=$q->fetchColumn();if($existing){$q=$db->prepare('UPDATE tenant_modules SET enabled=1 WHERE tenant_id=? AND module_id=?');$q->execute([$tid,$id]);}else{$q=$db->prepare('INSERT INTO tenant_modules (tenant_id,module_id,enabled) VALUES (?,?,1)');$q->execute([$tid,$id]);}echo "$slug instalado en tenant $tid\n";
}
