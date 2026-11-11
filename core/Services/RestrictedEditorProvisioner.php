<?php
namespace Screenart\Musedock\Services;
use PDO;
use RuntimeException;

/** Opt-in provisioning. Never rewrites an existing user's roles or permissions. */
final class RestrictedEditorProvisioner
{
    public const SLUG='blog-contributor';
    public const PERMISSIONS=['blog.view','blog.create','blog.edit.own','media.view.own','media.upload','media.edit.own','media.delete.own','image_gallery.view.own','image_gallery.create.own','image_gallery.edit.own','image_gallery.delete.own'];
    public function __construct(private PDO $db) {}

    /** Prepare a new restricted role without creating an account or changing existing assignments. */
    public function prepareRole(int $tenant): int
    {
        $own=!$this->db->inTransaction();if($own)$this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');$q->execute([$tenant]);if(!$q->fetchColumn())throw new RuntimeException('Tenant inexistente.');
            $role=$this->role($tenant);if($own)$this->db->commit();return $role;
        } catch(\Throwable $e){if($own&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function create(int $tenant, array $data): int
    {
        $email=strtolower(trim((string)($data['email']??'')));$name=trim((string)($data['name']??''));$password=$data['password']??'';
        if($tenant<1 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || $name==='' || strlen($name)>255 || !is_string($password) || strlen($password)<12 || strlen($password)>4096) throw new RuntimeException('Datos del colaborador inválidos; contraseña mínima de 12 caracteres.');
        $ownTransaction=!$this->db->inTransaction();if($ownTransaction)$this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');$q->execute([$tenant]);if(!$q->fetchColumn())throw new RuntimeException('Tenant inexistente.');
            foreach(['admins','users'] as $table){$q=$this->db->prepare("SELECT id FROM $table WHERE LOWER(email)=?");$q->execute([$email]);if($q->fetchColumn())throw new RuntimeException('El correo ya tiene una cuenta; no se modificarán sus permisos.');}
            $role=$this->role($tenant);
            $id=$this->insert('admins',['tenant_id'=>$tenant,'is_root_admin'=>0,'email'=>$email,'name'=>$name,'password'=>password_hash($password,PASSWORD_DEFAULT),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $this->db->prepare('INSERT INTO user_roles(user_id,user_type,role_id,tenant_id) VALUES(?,?,?,?)')->execute([$id,'admin',$role,$tenant]);
            if($ownTransaction)$this->db->commit();return $id;
        } catch(\Throwable $e){if($ownTransaction && $this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function role(int $tenant): int
    {
        $q=$this->db->prepare('SELECT id FROM roles WHERE tenant_id=? AND slug=?');$q->execute([$tenant,self::SLUG]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
        if(count($ids)>1)throw new RuntimeException('Rol de colaborador duplicado.');
        if($ids){
            $q=$this->db->prepare('SELECT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id WHERE rp.role_id=?');$q->execute([$ids[0]]);$actual=$q->fetchAll(PDO::FETCH_COLUMN);$expected=self::PERMISSIONS;sort($actual);sort($expected);
            if($actual!==$expected)throw new RuntimeException('El rol existente tiene otros permisos; se requiere revisión, no se modificará.');
            return (int)$ids[0];
        }
        $role=$this->insert('roles',['tenant_id'=>$tenant,'slug'=>self::SLUG,'name'=>'Editor de blog restringido','description'=>'Artículos, imágenes y galerías propios.']);
        foreach(self::PERMISSIONS as $slug){
            $q=$this->db->prepare('SELECT id FROM permissions WHERE tenant_id=? AND slug=?');$q->execute([$tenant,$slug]);$permission=(int)$q->fetchColumn();
            if(!$permission)$permission=$this->insert('permissions',['tenant_id'=>$tenant,'slug'=>$slug,'name'=>$slug]);
            $this->db->prepare('INSERT INTO role_permissions(role_id,permission_id,tenant_id) VALUES(?,?,?)')->execute([$role,$permission,$tenant]);
        }
        return $role;
    }

    private function insert(string $table,array $data): int
    {
        $pg=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='pgsql';
        $q=$this->db->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')'.($pg?' RETURNING id':''));$q->execute(array_values($data));return $pg?(int)$q->fetchColumn():(int)$this->db->lastInsertId();
    }
}
