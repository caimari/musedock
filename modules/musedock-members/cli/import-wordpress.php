<?php
if(PHP_SAPI!=='cli')exit;
define('APP_ROOT',dirname(__DIR__,3));require APP_ROOT.'/vendor/autoload.php';\Screenart\Musedock\Env::load();require_once APP_ROOT.'/core/helpers.php';
require_once APP_ROOT.'/modules/musedock-forms/cli/WordPressSource.php';
require_once APP_ROOT.'/modules/musedock-forms/src/Forms.php';
use MuseDockForms\Import\WordPressSource;
use MuseDockForms\Forms;
$o=getopt('',['source-config:','tenant:','backup-dir:','apply']);$tid=(int)($o['tenant']??0);$target=Forms::query('SELECT id,domain FROM tenants WHERE id=?',[$tid])->fetch(PDO::FETCH_ASSOC);if(!$target)throw new RuntimeException('Tenant no encontrado');
$source=new WordPressSource((string)($o['source-config']??''));$users=$source->rows('users');$metas=$source->rows('usermeta');$forms=$source->rows('formmaker');$submits=$source->rows('formmaker_submits');$posts=$source->rows('posts',"post_type IN ('um_form','um_directory')");$postmeta=$source->rows('postmeta','post_id IN (SELECT ID FROM `'.$source->prefix."posts` WHERE post_type IN ('um_form','um_directory'))");
$groups=[];foreach($submits as $s)$groups[$s['form_id'].':'.$s['group_id']][]=$s;
echo 'Destino: tenant '.$tid.' '.$target['domain'].'; cuentas: '.count($users).'; solicitudes: '.count($groups).'; definiciones UM: '.count($posts).PHP_EOL;
if(!isset($o['apply'])){echo "Simulación: sin escrituras. Usa --apply y --backup-dir privado.\n";exit;}
$backup=(string)($o['backup-dir']??'');if($backup==='')throw new RuntimeException('Copia privada obligatoria');
$snapshot=$source->backup($backup,['users'=>'1=1','usermeta'=>'1=1','posts'=>"post_type IN ('um_form','um_directory')",'postmeta'=>'post_id IN (SELECT ID FROM `'.$source->prefix."posts` WHERE post_type IN ('um_form','um_directory'))",'options'=>"option_name LIKE 'um\\_%' OR option_name LIKE '%ultimate%' OR option_name LIKE '%user_roles'",'formmaker'=>'1=1','formmaker_submits'=>'1=1','formmaker_groups'=>'1=1','um_metadata'=>'1=1']);
// Preserve media privately as well; never expose WordPress uploads as executable/public files.
$mediaSource=dirname((string)$o['source-config']).'/wp-content/uploads/ultimatemember';$mediaCount=0;
if(is_dir($mediaSource)){
 $iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaSource,FilesystemIterator::SKIP_DOTS));
 foreach($iter as $file){if(!$file->isFile()||$file->isLink())continue;$relative=substr($file->getPathname(),strlen($mediaSource)+1);
  foreach([$backup.'/legacy-media',APP_ROOT.'/storage/tenants/'.$tid.'/private/musedock-members/legacy-media'] as $root){$dest=$root.'/'.$relative;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0700,true);if(!file_exists($dest)){if(!copy($file->getPathname(),$dest))throw new RuntimeException('No se pudo preservar adjunto');chmod($dest,0600);}if(!hash_equals(hash_file('sha256',$file->getPathname()),hash_file('sha256',$dest)))throw new RuntimeException('Adjunto no coincide; no se sobrescribirá');}
  $mediaCount++;
 }
}
$db=Forms::db();$db->beginTransaction();
try{
$map=[];$fieldmaps=[];
foreach($forms as $form){$fields=[];$fieldmap=[];
 foreach(explode('*:*new_field*:*',$form['form_fields']) as $chunk){$parts=explode('*:*',$chunk);$f=[];for($i=0;$i<count($parts)-1;$i+=2)$f[$parts[$i+1]]=$parts[$i];$id=(string)($f['id']??'');$type=$f['type']??'';
 if(!ctype_digit($id)||in_array($type,['type_editor','type_submit_reset','type_recaptcha'],true))continue;
 $types=['type_name'=>'text','type_submitter_mail'=>'email','type_own_select'=>'select','type_checkbox'=>'checkboxes','type_spinner'=>'text','type_text'=>'text','type_textarea'=>'textarea'];
 if(!isset($types[$type]))throw new RuntimeException('Tipo de campo no soportado: '.$type);
 $label=html_entity_decode(strip_tags($f['w_field_label']??$id),ENT_QUOTES,'UTF-8');$key='f_'.$id;
 $entry=['key'=>$key,'label'=>$label,'type'=>$types[$type],'required'=>($f['w_required']??'')==='yes','source_field_id'=>$id];
 if(in_array($entry['type'],['select','checkboxes'],true)){$entry['options']=explode('***',$f['w_choices']??'');if($entry['type']==='select'&&str_starts_with($entry['options'][0]??'','Seleccione'))array_shift($entry['options']);}
 $fields[]=$entry;$fieldmap[$id]=['key'=>$key,'label'=>$label];
 }
 Forms::schema($fields);$slug=(int)$form['id']===7?'asociado-artista':'simpatizante-voluntario';
 Forms::query('INSERT INTO md_forms (tenant_id,slug,title,description,fields,source,source_id,legacy_definition) VALUES (?,?,?,?,?,?,?,?) ON CONFLICT (tenant_id,source,source_id) DO NOTHING',[$tid,$slug,$form['title'],'Solicitud de asociación. La admisión depende de la Junta Directiva.',json_encode($fields,JSON_UNESCAPED_UNICODE),'formmaker',(string)$form['id'],json_encode($form,JSON_UNESCAPED_UNICODE)]);
 $q=Forms::query('SELECT id FROM md_forms WHERE tenant_id=? AND source=? AND source_id=?',[$tid,'formmaker',(string)$form['id']]);$map[$form['id']]=(int)$q->fetchColumn();$fieldmaps[$form['id']]=$fieldmap;
}
foreach($groups as $sourceid=>$items){$data=[];foreach($items as $s){$key=$fieldmaps[$s['form_id']][$s['element_label']]['key']??'legacy_'.$s['element_label'];if(array_key_exists($key,$data)){$data[$key]=(array)$data[$key];$data[$key][]=$s['element_value'];}else $data[$key]=$s['element_value'];}
 $s=$items[0];Forms::query('INSERT INTO md_form_submissions (tenant_id,form_id,data,source,source_id,legacy_data,submitted_at) VALUES (?,?,?,?,?,?,?) ON CONFLICT (tenant_id,source,source_id) DO NOTHING',[$tid,$map[$s['form_id']],json_encode($data,JSON_UNESCAPED_UNICODE),'formmaker',$sourceid,json_encode($items,JSON_UNESCAPED_UNICODE),$s['date']]);}
$pm=[];foreach($postmeta as $r)$pm[$r['post_id']][$r['meta_key']][]=$r['meta_value'];
foreach($posts as $post){$meta=$pm[$post['ID']]??[];$mode=$meta['_um_mode'][0]??($post['post_type']==='um_directory'?'directory':'unknown');
 Forms::query('INSERT INTO md_member_definitions (tenant_id,source,source_id,title,mode,definition) VALUES (?,?,?,?,?,?) ON CONFLICT (tenant_id,source,source_id) DO NOTHING',[$tid,'ultimatemember',(string)$post['ID'],$post['post_title'],$mode,json_encode(['post'=>$post,'meta'=>$meta],JSON_UNESCAPED_UNICODE)]);
 if((int)$post['ID']===258){$original=WordPressSource::decode($meta['_um_custom_fields'][0]??'');$fields=[];foreach(is_array($original)?$original:[] as $k=>$f){if(!is_array($f)||in_array($f['type']??'',['block','row','password'],true))continue;$type=['text'=>'text','select'=>'select','multiselect'=>'checkboxes','url'=>'url','number'=>'tel'][$f['type']??'']??'text';if($k==='user_email')$type='email';$choices=array_values($f['options']??[]);if(in_array($type,['select','checkboxes'],true)&&!$choices)$type='text';$e=['key'=>$k,'label'=>$f['label']??$f['title']??$k,'type'=>$type,'required'=>!empty($f['required'])];if($choices)$e['options']=array_map('strval',$choices);$fields[]=$e;}
 Forms::schema($fields);Forms::query('INSERT INTO md_forms (tenant_id,slug,title,description,locale,fields,source,source_id,legacy_definition) VALUES (?,?,?,?,?,?,?,?,?) ON CONFLICT (tenant_id,source,source_id) DO NOTHING',[$tid,'international-associate','International Associate Registration','Application subject to approval by the association.','en',json_encode($fields,JSON_UNESCAPED_UNICODE),'ultimatemember','258',json_encode(['post'=>$post,'meta'=>$meta],JSON_UNESCAPED_UNICODE)]);
 }
}
$um=[];foreach($metas as $r)$um[$r['user_id']][$r['meta_key']][]=$r['meta_value'];
$allowed=['first_name','last_name','full_name','country','CP_V','Direccion','DNI','PaginaWeb','PerfilPublicooPrivado','PoblacionV','ProfesionPasionPrincipales','Provincia','Telefono','Comunicaciones','profile_photo','cover_photo','profile_privacy','hide_in_members','description','form_id'];
foreach($users as $u){$meta=$um[$u['ID']]??[];$profile=[];foreach($allowed as $key)if(isset($meta[$key])){$raw=$meta[$key][0];$decoded=WordPressSource::decode($raw);$profile[$key]=$decoded===false?$raw:$decoded;}
 $roles=WordPressSource::decode($meta[$source->prefix.'capabilities'][0]??'');$status=$meta['account_status'][0]??'awaiting_admin_review';$kind=is_array($roles)?implode(',',array_keys(array_filter($roles))):'associate';
 // Only legacy records, never CMS roles or login sessions. Public directory remains opt-in.
 Forms::query('INSERT INTO md_members (tenant_id,email,username,password_hash,display_name,status,member_type,profile,public_profile,source,source_id,legacy_data,created_at) VALUES (?,?,?,?,?,?,?,?,0,?,?,?,?) ON CONFLICT (tenant_id,source,source_id) DO NOTHING',[$tid,strtolower($u['user_email']),$u['user_login'],$u['user_pass'],$u['display_name'],$status,$kind,json_encode($profile,JSON_UNESCAPED_UNICODE),'wordpress',(string)$u['ID'],json_encode(['user'=>$u,'meta'=>$meta],JSON_UNESCAPED_UNICODE),$u['user_registered']]);}
$counts=[];foreach(['md_forms','md_form_submissions','md_member_definitions','md_members'] as $t)$counts[$t]=(int)Forms::query("SELECT COUNT(*) FROM $t WHERE tenant_id=?",[$tid])->fetchColumn();
if($counts['md_members']<count($users)||$counts['md_form_submissions']<count($groups))throw new RuntimeException('Recuentos no coinciden');
// Verify every original hash and every response payload against its imported counterpart.
foreach($users as $u){$r=Forms::query('SELECT legacy_data FROM md_members WHERE tenant_id=? AND source=? AND source_id=?',[$tid,'wordpress',(string)$u['ID']])->fetchColumn();if(!hash_equals($u['user_pass'],json_decode($r,true)['user']['user_pass']??''))throw new RuntimeException('Hash no coincidente; no se sobrescribirá una cuenta ya cambiada');}
foreach($groups as $key=>$items){$r=Forms::query('SELECT legacy_data FROM md_form_submissions WHERE tenant_id=? AND source=? AND source_id=?',[$tid,'formmaker',$key])->fetchColumn();if(json_decode($r,true)!=$items)throw new RuntimeException('Solicitud no coincidente');}
$db->commit();
file_put_contents($backup.'/import-result.json',json_encode(['tenant_id'=>$tid,'counts'=>$counts,'hashes_verified'=>count($users),'submissions_verified'=>count($groups),'source_unchanged'=>true,'media_verified'=>$mediaCount],JSON_PRETTY_PRINT));chmod($backup.'/import-result.json',0600);
echo json_encode($counts),"; hashes y respuestas originales verificados.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
