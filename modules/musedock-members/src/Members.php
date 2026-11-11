<?php
namespace MuseDockMembers;
use MuseDockForms\Forms;
use PDO;
final class Members
{
    public static function current(bool $lock = false): ?array {
        if(session_status()!==PHP_SESSION_ACTIVE)\Screenart\Musedock\Security\SessionSecurity::startSession();
        $s=$_SESSION['md_members'][Forms::tenant()]??null;
        if(!is_array($s)||($s['expires']??0)<time())return null;
        $m=Forms::query('SELECT * FROM md_members WHERE id=? AND tenant_id=?'.($lock && Forms::db()->inTransaction()?' FOR UPDATE':''),[(int)$s['id'],Forms::tenant()])->fetch(PDO::FETCH_ASSOC);
        if(!$m||$m['status']!=='approved'||(int)$m['session_version']!==(int)$s['version'])return null;return $m;
    }
    /** Minimal tenant-scoped identity for integrations; never exposes profile or credentials. */
    public static function findApproved(int $id, bool $lock = false): ?array {
        $q=Forms::query("SELECT id,tenant_id,email,display_name,member_type FROM md_members WHERE id=? AND tenant_id=? AND status='approved'".($lock && Forms::db()->inTransaction()?' FOR UPDATE':''),[$id,Forms::tenant()]);
        $row=$q->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }
    public static function safePublic(array $m): array {
        // Deliberate allowlist. Sensitive and legacy metadata never leave the private area.
        return ['name'=>(string)$m['display_name'],'member_type'=>(string)$m['member_type']];
    }
    public static function login(string $identifier,string $password): ?array {
        if(session_status()!==PHP_SESSION_ACTIVE)\Screenart\Musedock\Security\SessionSecurity::startSession();
        $m=Forms::query('SELECT * FROM md_members WHERE tenant_id=? AND (email=? OR username=?)',[Forms::tenant(),strtolower($identifier),$identifier])->fetch(PDO::FETCH_ASSOC);
        if(!$m){password_verify($password,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');return null;}
        if(!Passwords::verify($password,$m['password_hash'])||$m['status']!=='approved')return null;
        if(password_needs_rehash($m['password_hash'],PASSWORD_DEFAULT)||str_starts_with($m['password_hash'],'$P$')||str_starts_with($m['password_hash'],'$wp$'))Forms::query('UPDATE md_members SET password_hash=? WHERE id=? AND tenant_id=?',[password_hash($password,PASSWORD_DEFAULT),$m['id'],Forms::tenant()]);
        session_regenerate_id(true);$_SESSION['md_members'][Forms::tenant()]=['id'=>(int)$m['id'],'version'=>(int)$m['session_version'],'expires'=>time()+7200];return $m;
    }
    /** Existing tenants retain registration unless explicitly closed by their administrator. */
    public static function registrationEnabled(): bool {
        $q=Forms::query('SELECT value FROM tenant_settings WHERE tenant_id=? AND '.\Screenart\Musedock\Database::qi('key')."='musedock_members_registration_enabled'",[Forms::tenant()]);
        $value=$q->fetchColumn();return $value===false || (string)$value==='1';
    }
    public static function register(array $form,array $values,string $password): int {
        if(!self::registrationEnabled())throw new \RuntimeException('Registro no disponible.');
        if(strlen($password)<12||strlen($password)>4096)throw new \InvalidArgumentException('Contraseña: entre 12 y 4096 caracteres');
        $email=strtolower(trim((string)($values['user_email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Email inválido');
        $name=trim(($values['first_name']??'').' '.($values['last_name']??''));
        $db=Forms::db();$db->beginTransaction();try{
            $pg=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql';$q=Forms::query('INSERT INTO md_members (tenant_id,email,username,password_hash,display_name,status,member_type,profile,public_profile,source) VALUES (?,?,?,?,?,?,?,?,0,?)'.($pg?' RETURNING id':''),[Forms::tenant(),$email,$email,password_hash($password,PASSWORD_DEFAULT),$name,'awaiting_admin_review',$form['slug'],json_encode($values,JSON_UNESCAPED_UNICODE),'native']);$id=$pg?(int)$q->fetchColumn():(int)$db->lastInsertId();
            Forms::query('INSERT INTO md_form_submissions (tenant_id,form_id,data,source,privacy_version) VALUES (?,?,?,?,?)',[Forms::tenant(),$form['id'],json_encode($values,JSON_UNESCAPED_UNICODE),'native','privacy-2026-10-31']);
            $db->commit();return $id;
        }catch(\Throwable $e){$db->rollBack();throw $e;}
    }
}
