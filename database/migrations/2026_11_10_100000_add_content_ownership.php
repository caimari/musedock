<?php
/** Explicit migration only. Leaves existing galleries unassigned and existing permissions unchanged. */
return new class {
    private ?PDO $connection=null;
    public function setConnection(PDO $db,string $driver='pgsql'): void {$this->connection=$db;}
    public function up(): void {
        $db=$this->connection??\Screenart\Musedock\Database::connect();$driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach(['image_galleries','media'] as $table) {
        $q=$db->query($driver==='pgsql' ? "SELECT 1 FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='$table' AND column_name='owner_admin_id'" : "SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='$table' AND column_name='owner_admin_id'");
        if (!$q->fetchColumn()) $db->exec('ALTER TABLE '.$table.' ADD COLUMN owner_admin_id INTEGER NULL');
        }
    }
    public function down(): void {($this->connection??\Screenart\Musedock\Database::connect())->exec('ALTER TABLE image_galleries DROP COLUMN owner_admin_id'); ($this->connection??\Screenart\Musedock\Database::connect())->exec('ALTER TABLE media DROP COLUMN owner_admin_id');}
};
