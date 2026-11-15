<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;
use PDO;

/** Plans contain only the authorized site's evidence; writes execute after normal approval. */
final class NewsActions
{
    public const TOOLS = ['set_news_item_status', 'extract_news_context', 'research_news_item', 'fetch_news_now', 'link_news_item_to_post'];
    public function __construct(private Content $content) {}

    public static function load(): void
    {
        static $registered = false;
        if ($registered) return;
        $base = rtrim((string) \Screenart\Musedock\Env::get('PRIVATE_PLUGINS_PATH', dirname(APP_ROOT) . '/private-plugins'), '/') . '/tenant-shared/news-aggregator/';
        spl_autoload_register(function ($class) use ($base) {
            foreach (['NewsAggregator\\Models\\' => 'models/', 'NewsAggregator\\Services\\' => 'services/'] as $prefix => $directory) {
                if (str_starts_with($class, $prefix)) {
                    $file = $base . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                    if (is_file($file)) require_once $file;
                    return;
                }
            }
        });
        $registered = true;
    }

    public static function description(string $tool): string
    {
        return match ($tool) {
            'set_news_item_status' => 'Marca el aviso rejected, used (idea usada) o pending; admite nota editorial.',
            'extract_news_context' => 'Extrae contexto de las URLs del aviso con UrlGuard; reutiliza la caché existente.',
            'research_news_item' => 'Busca documentación con las APIs configuradas del medio; reutiliza resultados guardados. Sin generación IA.',
            'fetch_news_now' => 'Captura fuentes activas del medio, o una source_id concreta. Mínimo cinco minutos entre capturas manuales. No reescribe, aprueba ni publica.',
            'link_news_item_to_post' => 'Enlaza el aviso con un artículo propio del mismo medio y marca la idea used.',
        };
    }

    public function plan(string $tool, array $r, array $c, array $args): array
    {
        if (!Policy::news($r['id']) || !Endpoint::installed($r['id'])) throw new RuntimeException('News Aggregator no disponible.');
        $site = $this->content->siteArg($r, $c, $args);
        $source = null; $fields = [];
        if ($tool === 'fetch_news_now') {
            $sql = 'SELECT id,tenant_id,enabled FROM news_aggregator_sources WHERE tenant_id=?'; $params = [$site];
            if (isset($args['source_id'])) { $sql .= ' AND id=?'; $params[] = $args['source_id']; }
            $sql .= ' ORDER BY id';
            $source = array_values(array_filter($this->content->query($sql, $params), fn($s) => !empty($s['enabled'])));
            if (!$source) throw new RuntimeException('Fuente no disponible o desactivada en este medio.');
            $fields['minimum_interval_minutes'] = 5;
        } else {
            $source = $this->content->query('SELECT id,tenant_id,status,created_post_id,editorial_note,source_context,research_context FROM news_aggregator_items WHERE id=? AND tenant_id=?', [$args['item_id'], $site])[0] ?? null;
            if (!$source) throw new RuntimeException('Aviso no disponible en este medio.');
            if ($tool === 'set_news_item_status') {
                if (!in_array($args['status'], ['rejected', 'used', 'pending'], true)) throw new RuntimeException('Estado no válido.');
                $fields = ['status' => $args['status']];
                if (isset($args['note'])) $fields['editorial_note'] = \Screenart\Musedock\SafeHtml::plain($args['note']);
            } elseif ($tool === 'link_news_item_to_post') {
                $post = $this->content->post($r, $c, $args['post_id']);
                if ((int) $post['tenant_id'] !== $site) throw new RuntimeException('El artículo pertenece a otro medio.');
                if ($source['created_post_id'] && (int) $source['created_post_id'] !== (int) $post['id']) throw new RuntimeException('La idea ya tiene otro artículo.');
                $fields = ['status' => 'used', 'created_post_id' => (int) $post['id']];
            }
        }
        return ['tool' => $tool, 'site_id' => $site, 'source' => $source, 'fields' => $fields, 'taxonomy' => [], 'result_status' => 'evidence_only'];
    }

    public function execute(array $plan, int $actor, ?array $auth, array $args): array
    {
        if (!$auth || !$this->content->actorActive($auth['resource']['id'], $actor, Policy::IMPORT)) throw new RuntimeException('Permiso de noticias revocado.');
        self::load();
        $db = $this->content->db; $site = (int) $plan['site_id']; $tool = $plan['tool'];
        $db->beginTransaction();
        try {
            $current = $this->plan($tool, $auth['resource'], $auth['client'], $args);
            if (!hash_equals(Policy::fingerprint($plan), Policy::fingerprint($current))) throw new RuntimeException('El aviso ha cambiado. Solicita un plan nuevo.');
            if ($tool === 'fetch_news_now') {
                $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
                $db->prepare($mysql ? 'INSERT IGNORE INTO news_aggregator_settings(tenant_id) VALUES(?)' : 'INSERT INTO news_aggregator_settings(tenant_id) VALUES(?) ON CONFLICT(tenant_id) DO NOTHING')->execute([$site]);
                $q = $db->prepare('UPDATE news_aggregator_settings SET last_manual_fetch_at=CURRENT_TIMESTAMP WHERE tenant_id=? AND (last_manual_fetch_at IS NULL OR last_manual_fetch_at < ' . ($mysql ? 'DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)' : "CURRENT_TIMESTAMP - INTERVAL '5 minutes'") . ')');
                $q->execute([$site]);
                if ($q->rowCount() !== 1) throw new RuntimeException('Espera cinco minutos entre capturas manuales del medio.');
                // Commit the reservation before external I/O; failed fetches also consume the interval.
                $db->commit();
                $results = [];
                foreach ($plan['source'] as $source) {
                    $q = $db->prepare('SELECT * FROM news_aggregator_sources WHERE id=? AND tenant_id=? AND enabled=' . ($mysql ? '1' : 'TRUE'));
                    $q->execute([$source['id'], $site]);
                    if (!Endpoint::tenantPluginActive($site)) throw new RuntimeException('Plugin desactivado.');
                    if ($row = $q->fetch(PDO::FETCH_OBJ)) $results[] = ['source_id' => (int) $row->id] + \NewsAggregator\Services\FetcherFactory::fetch($site, $row);
                }
                return ['site_id' => $site, 'sources' => $results, 'rewritten' => 0, 'published' => 0];
            }
            if (in_array($tool, ['set_news_item_status', 'link_news_item_to_post'], true)) {
                $fields = $plan['fields'];
                $q = $db->prepare('UPDATE news_aggregator_items SET ' . implode(',', array_map(fn($k) => $k . '=?', array_keys($fields))) . ' WHERE id=? AND tenant_id=?');
                $q->execute([...array_values($fields), $args['item_id'], $site]);
                $result = ['item_id' => $args['item_id'], 'site_id' => $site] + $fields;
            } else {
                $service = new \NewsAggregator\Services\NewsEvidence($site);
                $result = $tool === 'extract_news_context' ? $service->extractContext($args['item_id']) : $service->research($args['item_id']);
                $result += ['item_id' => $args['item_id'], 'site_id' => $site];
            }
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
