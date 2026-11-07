<?php
/** Integration tests: synthetic tenants only, always rolled back; no mail. */
define('APP_ROOT',dirname(__DIR__,3));require APP_ROOT.'/vendor/autoload.php';\Screenart\Musedock\Env::load();require_once APP_ROOT.'/core/helpers.php';
require APP_ROOT.'/modules/musedock-forms/src/Forms.php';require dirname(__DIR__).'/src/Passwords.php';require dirname(__DIR__).'/src/Members.php';
use MuseDockForms\Forms;use MuseDockMembers\Passwords;use MuseDockMembers\Members;
$count=0;function ok($v,$m){global $count;if(!$v)throw new RuntimeException($m);$count++;}
$fixture='$P$BiZiYbQXgRhU2Lz9INZOShKFwxEVo51';ok(Passwords::verify('Fixture-Only-Password-2026',$fixture),'Portable WP hash');ok(!Passwords::verify('wrong',$fixture),'Wrong WP password rejected');ok(!Passwords::verify('x','$P$z12345678'.str_repeat('x',22)),'Excessive work factor rejected');
$bcrypt=password_hash('Fixture-Only-Password-2026',PASSWORD_BCRYPT);ok(Passwords::verify('Fixture-Only-Password-2026',$bcrypt),'Native hash');
$wp='$wp'.password_hash(base64_encode(hash_hmac('sha384','Fixture-Only-Password-2026','wp-sha384',true)),PASSWORD_BCRYPT);ok(Passwords::verify('Fixture-Only-Password-2026',$wp),'Modern WP hash');
$fields=[['key'=>'email','type'=>'email','label'=>'Email','required'=>true],['key'=>'choices','type'=>'checkboxes','label'=>'Choices','options'=>['A','B']]];
[$v,$e]=Forms::validate($fields,['email'=>'a@example.test','choices'=>['A']]);ok(!$e&&$v['choices']===['A'],'Valid form');
[, $e]=Forms::validate($fields,['email'=>'bad','choices'=>['injected']]);ok(count($e)===2,'Invalid email and tampered option');
[, $e]=Forms::validate($fields,['email'=>['a@example.test']]);ok(isset($e['email']),'Array injection rejected');
ok(!str_contains(Forms::fields([['key'=>'a','type'=>'text','label'=>'<script>x</script>']],['a'=>'"><script>x</script>']),'<script>'),'HTML escaped');
$db=Forms::db();$db->beginTransaction();$old=$GLOBALS['tenant']??null;
try{
$tid=900071001;$GLOBALS['tenant']=['id'=>$tid];$_SESSION=[];
$f=Forms::query('INSERT INTO md_forms (tenant_id,slug,title,fields) VALUES (?,?,?,?) RETURNING id',[$tid,'fixture-only','Fixture','[]'])->fetchColumn();
ok(Forms::form('fixture-only')['title']==='Fixture','Own tenant form');
$GLOBALS['tenant']['id']=$tid+1;ok(Forms::form('fixture-only')===null,'Other tenant form blocked');$GLOBALS['tenant']['id']=$tid;
$id=Forms::query('INSERT INTO md_members (tenant_id,email,username,password_hash,display_name,status,profile) VALUES (?,?,?,?,?,?,?) RETURNING id',[$tid,'fixture@example.test','fixture',$fixture,'Fixture','awaiting_admin_review',json_encode(['DNI'=>'PRIVATE','Telefono'=>'PRIVATE'])])->fetchColumn();
ok(Members::login('fixture','Fixture-Only-Password-2026')===null,'Pending approval cannot log in');
Forms::query("UPDATE md_members SET status='approved' WHERE id=?",[$id]);$m=Members::login('fixture','Fixture-Only-Password-2026');ok($m!==null,'Approved member login');
$upgraded=Forms::query('SELECT password_hash FROM md_members WHERE id=?',[$id])->fetchColumn();ok(password_verify('Fixture-Only-Password-2026',$upgraded),'Hash upgraded');
ok(Members::current()!==null,'Valid isolated member session');ok(!isset($_SESSION['admin'])&&!isset($_SESSION['user']),'No CMS privilege elevation');
$GLOBALS['tenant']['id']=$tid+1;ok(Members::current()===null,'Session rejected on another tenant');$GLOBALS['tenant']['id']=$tid;
Forms::query("UPDATE md_members SET status='rejected',session_version=session_version+1 WHERE id=?",[$id]);ok(Members::current()===null,'Rejection revokes current access');
ok(array_keys(Members::safePublic($m))===['name','member_type'],'Public profile allowlist');
$db->rollBack();$GLOBALS['tenant']=$old;echo "OK: $count comprobaciones; fixtures revertidos; sin correos.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();$GLOBALS['tenant']=$old;throw $e;}
