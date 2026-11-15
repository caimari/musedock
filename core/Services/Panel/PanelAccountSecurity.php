<?php
namespace Screenart\Musedock\Services\Panel;

use PDO;
use InvalidArgumentException;
use Screenart\Musedock\Helpers\PermissionHelper;
use Screenart\Musedock\Mail\Mailer;

/** Shared checks for panel account mutations. Never identifies Members as panel users. */
final class PanelAccountSecurity
{
    public static function root(array $actor,int $tenant): bool
    {
        return ($actor['type']??'')==='super_admin'
            ? PermissionHelper::isSuperAdminRoot((int)$actor['id'])
            : (($actor['type']??'')==='admin' && PermissionHelper::isTenantRootAdmin((int)$actor['id'],$tenant));
    }

    public static function can(array $actor,string $slug,int $tenant): bool
    {
        return ($actor['type']??'')==='super_admin' ? PermissionHelper::superAdminCan((int)$actor['id'],$slug) : PermissionHelper::userCan((int)$actor['id'],$slug,$tenant,'admin');
    }

    public static function assertHierarchy(PDO $db,int $tenant,array $target,array $actor): void
    {
        if(self::root($actor,$tenant)) return;
        if((int)$target['is_root_admin']) throw new InvalidArgumentException('panel.error.protected');
        // Inspect current effective permissions, including legacy unscoped assignments.
        $q=$db->prepare("SELECT DISTINCT p.slug FROM user_roles ur JOIN roles r ON r.id=ur.role_id JOIN role_permissions rp ON rp.role_id=r.id JOIN permissions p ON p.id=rp.permission_id WHERE ur.user_id=? AND (ur.user_type='admin' OR ur.user_type IS NULL) AND (ur.tenant_id=? OR ur.tenant_id IS NULL) AND (r.tenant_id=? OR r.tenant_id IS NULL) AND (p.tenant_id=? OR p.tenant_id IS NULL) UNION SELECT permission_slug FROM user_permissions WHERE user_id=? AND (tenant_id=? OR tenant_id IS NULL)");
        $q->execute([$target['id'],$tenant,$tenant,$tenant,$target['id'],$tenant]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $slug) if(!self::can($actor,$slug,$tenant)) throw new InvalidArgumentException('panel.error.protected');
    }

    /** Replace ambiguous legacy rows without removing the other account type's access. */
    public static function preserveUntypedOther(PDO $db,int $tenant,int $id,string $changingType):void
    {
        $other=$changingType==='admin'?'user':'admin';$table=$other==='admin'?'admins':'users';if(!self::table($db,$table))return;
        $q=$db->prepare('SELECT tenant_id FROM '.$table.' WHERE id=?');$q->execute([$id]);$otherAccount=$q->fetch(PDO::FETCH_ASSOC);if(!$otherAccount)return;$otherTenant=$otherAccount['tenant_id'];
        $q=$db->prepare('SELECT ur.role_id,ur.tenant_id,r.tenant_id AS role_tenant FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND ur.user_type IS NULL AND (ur.tenant_id=? OR ur.tenant_id IS NULL) AND (r.tenant_id=? OR r.tenant_id IS NULL)');$q->execute([$id,$tenant,$tenant]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){if($otherTenant!==null&&(($row['tenant_id']!==null&&(int)$row['tenant_id']!==(int)$otherTenant)||($row['role_tenant']!==null&&(int)$row['role_tenant']!==(int)$otherTenant)))continue;$check=$db->prepare('SELECT 1 FROM user_roles WHERE user_id=? AND user_type=? AND role_id=? AND (tenant_id=? OR tenant_id IS NULL)');$check->execute([$id,$other,$row['role_id'],$row['tenant_id']]);if(!$check->fetchColumn())$db->prepare('INSERT INTO user_roles(user_id,user_type,role_id,tenant_id) VALUES(?,?,?,?)')->execute([$id,$other,$row['role_id'],$row['tenant_id']]);}
    }

    private static function table(PDO $db,string $table): bool
    {
        $schema=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql'?'current_schema()':'DATABASE()';
        $q=$db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema='.$schema.' AND table_name=?');$q->execute([$table]);return (bool)$q->fetchColumn();
    }

    /** Called within the account transaction; failures roll back the account change too. */
    public static function revoke(PDO $db,?int $tenant,array $account,string $type='admin'): void
    {
        [$sessionTable,$ownerColumn,$table]=match($type) {
            'admin'=>['admin_session_tokens','admin_id','admins'],
            'super_admin'=>['super_admin_session_tokens','super_admin_id','super_admins'],
            'user'=>['user_session_tokens','user_id','users'],
            default=>throw new InvalidArgumentException('panel.error.protected'),
        };
        if(self::table($db,$sessionTable))$db->prepare('DELETE FROM '.$sessionTable.' WHERE '.$ownerColumn.'=?')->execute([$account['id']]);
        if($type!=='user')$db->prepare('UPDATE '.$table.' SET auth_version=auth_version+1 WHERE id=?')->execute([$account['id']]);
        if($type==='admin')$db->prepare("DELETE FROM password_resets WHERE (tenant_id=? AND admin_id=?) OR (purpose='password_reset' AND LOWER(email)=? AND (tenant_id=? OR tenant_id IS NULL))")->execute([$tenant,$account['id'],strtolower($account['email']),$tenant]);
        else $db->prepare("DELETE FROM password_resets WHERE purpose='password_reset' AND LOWER(email)=? AND (tenant_id IS NULL OR tenant_id=?)")->execute([strtolower($account['email']),$tenant]);
        if($type!=='super_admin' && self::table($db,'api_keys')) {
            $owner=$type==='user'?-(int)$account['id']:(int)$account['id'];$q=$db->prepare('SELECT id FROM api_keys WHERE '.($tenant===null?'tenant_id IS NULL':'tenant_id=?').' AND user_id=? FOR UPDATE');$q->execute($tenant===null?[$owner]:[$tenant,$owner]);
            foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $db->prepare('UPDATE api_keys SET is_active=0 WHERE id=?')->execute([$id]);
                if(self::table($db,'oauth_tokens'))$db->prepare('UPDATE oauth_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE api_key_id=? AND revoked_at IS NULL')->execute([$id]);
                if(self::table($db,'oauth_auth_codes'))$db->prepare('DELETE FROM oauth_auth_codes WHERE api_key_id=?')->execute([$id]);
            }
        }
        self::revokeDirect($db,$tenant,(int)$account['id'],$type);
        \Screenart\Musedock\Security\SessionSecurity::clearAuthenticationCache();
    }
    private static function revokeDirect(PDO $db,?int $tenant,int $id,string $type): void
    {
        $pg=\Screenart\Musedock\Env::get('MCP_CMS_STATE_DRIVER','sqlite')==='pgsql';
        if($pg&&!self::table($db,'mcp_state_grants'))return;
        if(!$pg&&!is_file((string)\Screenart\Musedock\Env::get('MCP_CMS_PRIVATE_DIRECTORY',dirname(APP_ROOT).'/.musedock-cms').'/mcp.sqlite'))return;
        $store=new \Screenart\Musedock\Services\Mcp\Direct\Store($pg?$db:null);
        $resources=$type==='super_admin'?['main','plugin:cross-publisher','plugin:news-aggregator']:[ 'tenant:'.$tenant, \Screenart\Musedock\Services\Mcp\Direct\Policy::tenantNews((int)$tenant) ];
        foreach($resources as $resource)foreach($store->all('SELECT data FROM grants WHERE resource=?',[$resource]) as $row) {
            $grant=$store->decode($row);
            if((int)($grant['actor']??0)===($type==='user'?-$id:$id)){ $grant['active']=false;$store->putGrant($grant); }
        }
    }
    public static function emailChanged(?int $tenant,string $oldEmail,bool $passwordOnly=false): void
    {
        // The security change is already committed; SMTP failure must not restore old tokens.
        $key=$passwordOnly?'panel.password_changed':'panel.email_changed';
        ob_start();
        try {
            if(!Mailer::send($oldEmail,__($key.'.subject'),'<p>'.htmlspecialchars(__($key.'.body'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>',__($key.'.body'),null,null,$tenant)) error_log('Panel account email notification failed');
        } catch(\Throwable $e) { error_log('Panel account email notification failed: '.get_class($e)); }
        finally { ob_end_clean(); }
    }
}
