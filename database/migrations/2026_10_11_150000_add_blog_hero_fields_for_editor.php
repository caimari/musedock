<?php
use Screenart\Musedock\Database;
class AddBlogHeroFieldsForEditor_2026_10_11_150000
{
    public function up(): void
    {
        $db=Database::connect();$mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $tables=['blog_posts'=>['show_hero'=>'SMALLINT NOT NULL DEFAULT 0','hero_image'=>'VARCHAR(500) NULL'],'blog_post_translations'=>['seo_image'=>'VARCHAR(500) NULL','canonical_url'=>'VARCHAR(500) NULL','robots_directive'=>'VARCHAR(50) NULL','twitter_title'=>'VARCHAR(255) NULL','twitter_description'=>'TEXT NULL','twitter_image'=>'VARCHAR(500) NULL']];
        foreach ($tables as $table=>$columns) foreach ($columns as $name=>$type) {
            $s=$db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='.($mysql?'DATABASE()':'current_schema()')." AND table_name=? AND column_name=?");$s->execute([$table,$name]);
            if (!$s->fetchColumn()) $db->exec('ALTER TABLE '.$table.' ADD COLUMN '.$name.' '.$type);
        }
    }
    public function down(): void { /* Preserve editor data on rollback. */ }
}
