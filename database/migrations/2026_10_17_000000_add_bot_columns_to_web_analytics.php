<?php
use Screenart\Musedock\Database;

/**
 * Las visitas de robots se guardan (con su tipo) en vez de descartarse: el panel enseña las visitas reales
 * por separado y el desglose de robots (IA, buscadores, herramientas…) sirve para estadísticas más avanzadas.
 */
class AddBotColumnsToWebAnalytics_2026_10_17_000000
{
    public function up(): void
    {
        $db = Database::connect();
        $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach (['bot_kind' => 'VARCHAR(20) NULL', 'bot_name' => 'VARCHAR(80) NULL'] as $column => $definition) {
            $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=' . ($mysql ? 'DATABASE()' : 'current_schema()') . ' AND table_name=? AND column_name=?');
            $statement->execute(['web_analytics', $column]);
            if (!$statement->fetchColumn()) {
                $db->exec('ALTER TABLE web_analytics ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }

    public function down(): void { /* Se conservan las columnas: contienen datos. */ }
}
