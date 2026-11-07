<?php
return new class {
    private ?\PDO $connection=null;
    public function setConnection(\PDO $db,string $driver='pgsql'): void { $this->connection=$db; }
    public function up(): void {
        $db=$this->connection??\Screenart\Musedock\Database::connect();
        $db->exec("CREATE TABLE IF NOT EXISTS tenant_smtp_permissions (
            tenant_id INTEGER PRIMARY KEY REFERENCES tenants(id) ON DELETE CASCADE,
            global_allowed SMALLINT NOT NULL DEFAULT 0 CHECK(global_allowed IN (0,1)),
            updated_by INTEGER NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }
    public function down(): void { ($this->connection??\Screenart\Musedock\Database::connect())->exec('DROP TABLE IF EXISTS tenant_smtp_permissions'); }
};
