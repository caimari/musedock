<?php
use Screenart\Musedock\Database;

class AddCrossPublishApiOrigin_2026_10_11_130000
{
    public function up(): void
    {
        $db=Database::connect(); $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $table=$driver==='mysql'?"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='cross_publish_queue'":"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=current_schema() AND table_name='cross_publish_queue'";
        if (!(int)$db->query($table)->fetchColumn()) return;
        $column=$driver==='mysql'?"SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='cross_publish_queue' AND column_name='origin_api_key_id'":"SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='cross_publish_queue' AND column_name='origin_api_key_id'";
        if (!(int)$db->query($column)->fetchColumn()) $db->exec('ALTER TABLE cross_publish_queue ADD COLUMN origin_api_key_id INTEGER NULL');
    }
    public function down(): void { /* Retain origin metadata for already queued jobs. */ }
}
