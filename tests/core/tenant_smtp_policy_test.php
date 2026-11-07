<?php
/** Isolated tests: no production settings, no sockets and no email. */
namespace Screenart\Musedock {
    final class Database {
        public static array $settings=[];
        public static array $permissions=[];
        public static bool $failSettings=false;
        public static bool $failPermissions=false;
        public static function connect(){return new class {public function prepare($sql){return new \SmtpPolicyTestStatement($sql);}};}
        public static function qi($key){return '"'.$key.'"';}
    }
}
namespace {
    final class SmtpPolicyTestStatement {
        public string $sql;public array $args=[];
        public function __construct($sql){$this->sql=$sql;}
        public function execute($args){$this->args=$args;if(str_contains($this->sql,'FROM tenant_settings')&&\Screenart\Musedock\Database::$failSettings)throw new \RuntimeException('fixture read failure');if(str_contains($this->sql,'FROM tenant_smtp_permissions')&&\Screenart\Musedock\Database::$failPermissions)throw new \RuntimeException('fixture policy failure');}
        public function fetchColumn(){return \Screenart\Musedock\Database::$permissions[$this->args[0]]??false;}
        public function fetchAll($mode=null){$r=[];foreach(\Screenart\Musedock\Database::$settings[$this->args[0]]??[] as $k=>$v)$r[]=['k'=>$k,'value'=>$v];return $r;}
    }
    require __DIR__.'/../../core/Mail/TenantSmtpPolicy.php';
    require __DIR__.'/../../core/Mail/Mailer.php';
    use Screenart\Musedock\Database;
    use Screenart\Musedock\Mail\Mailer;
    use Screenart\Musedock\Mail\TenantSmtpPolicy;
    foreach(['MAIL_DRIVER'=>'smtp','SMTP_HOST'=>'cms.example.test','SMTP_USERNAME'=>'cms@example.test','SMTP_PASSWORD'=>'fixture-only','SMTP_FALLBACK_HOST'=>'backup.example.test','SMTP_FALLBACK_USERNAME'=>'backup@example.test','SMTP_FALLBACK_PASSWORD'=>'fixture-only','MAIL_FROM_ADDRESS'=>'cms@example.test'] as $k=>$v)putenv($k.'='.$v);
    $resolve=new \ReflectionMethod(Mailer::class,'resolveMailConfig');$resolve->setAccessible(true);
    $candidates=new \ReflectionMethod(Mailer::class,'smtpCandidates');$candidates->setAccessible(true);
    $checks=0;$ok=static function($value,$label)use(&$checks){if(!$value)throw new \RuntimeException($label);$checks++;};
    $ok(!TenantSmtpPolicy::allowsGlobal(71),'Absent permission denied');
    Database::$settings[71]=['smtp_use_global'=>'1','smtp_global_allowed'=>'1'];
    $c=$resolve->invoke(null,71);$ok(!$c['enabled']&&!$c['allow_global_fallback'],'Tenant cannot self-grant');
    $ok(count($candidates->invoke(null,$c))===1,'No global candidate for unconfigured tenant');
    Database::$settings[71]=['smtp_use_global'=>'1','mail_driver'=>'smtp','smtp_host'=>'own.example.test','smtp_port'=>'587','smtp_username'=>'own@example.test','smtp_password'=>'fixture-only','mail_from_address'=>'own@example.test'];
    $c=$resolve->invoke(null,71);$ok($c['enabled']&&$c['smtp_host']==='own.example.test','Own SMTP takes precedence without grant');
    $ok(count($candidates->invoke(null,$c))===1,'Own SMTP failure cannot fall back');
    Database::$settings[71]['mail_driver']='mail';$c=$resolve->invoke(null,71);$ok($c['mail_driver']==='smtp','PHP mail bypass blocked');
    Database::$settings[71]['mail_driver']='sendmail';$c=$resolve->invoke(null,71);$ok($c['mail_driver']==='smtp','Sendmail bypass blocked');
    Database::$settings[71]['smtp_password']='';$c=$resolve->invoke(null,71);$ok(!$c['enabled'],'Incomplete own SMTP fails closed');
    Database::$permissions[71]=1;Database::$settings[71]['mail_driver']='smtp';$c=$resolve->invoke(null,71);$ok($c['smtp_host']==='cms.example.test'&&$c['enabled'],'Explicit grant permits chosen global SMTP');
    Database::$settings[71]['smtp_use_global']='0';$c=$resolve->invoke(null,71);$ok($c['source']==='global-fallback-incomplete-tenant','Explicit grant permits incomplete-config fallback');
    Database::$settings[71]['smtp_password']='fixture-only';$c=$resolve->invoke(null,71);$ok($c['smtp_host']==='own.example.test','Granted tenant can keep own SMTP');
    $ok(count($candidates->invoke(null,$c))===3,'Granted own SMTP can use CMS backups');
    $ok(!TenantSmtpPolicy::allowsGlobal(72),'Grant is tenant-specific');
    Database::$permissions[71]=0;$c=$resolve->invoke(null,71);$ok(count($candidates->invoke(null,$c))===1,'Revocation immediate');
    Database::$failPermissions=true;$c=$resolve->invoke(null,71);$ok(!$c['allow_global_fallback'],'Policy lookup error does not grant');Database::$failPermissions=false;
    Database::$failSettings=true;$c=$resolve->invoke(null,71);$ok(!$c['enabled']&&!$c['allow_global_fallback'],'Settings lookup error blocks sending');Database::$failSettings=false;
    $c=$resolve->invoke(null,null);$ok($c['enabled']&&$c['smtp_host']==='cms.example.test','Main CMS still has global mail');
    $ok(count($candidates->invoke(null,$c))===3,'Main CMS retains backup candidates');
    echo "OK: $checks casos SMTP; sin conexiones de red ni envío de correo.\n";
}
