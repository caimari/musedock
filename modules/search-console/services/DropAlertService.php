<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Aviso por correo cuando el SEO de un dominio cae de forma relevante: los clics (o las impresiones) del sitio se
 * reducen a la mitad, o una página con tráfico pierde la mitad de sus clics, frente al periodo anterior de 28 días.
 * Solo dominios visibles para el superadmin (propios y autorizados). Cada aviso se repite como mucho una vez por semana.
 */
class DropAlertService
{
    public const DAYS = 28;
    public const REPEAT_AFTER = 7 * 86400;
    /** Mínimos para no avisar por ruido en sitios casi sin tráfico. */
    public const MIN_PREV_CLICKS = 20;
    public const MIN_PREV_IMPRESSIONS = 300;
    public const MIN_PAGE_PREV_CLICKS = 10;

    private string $file;

    public function __construct(private PDO $pdo, ?string $file = null)
    {
        $this->file = $file ?? (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3)) . '/storage/cache/search-console-alerts.json';
    }

    private function load(): array
    {
        $s = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;

        return is_array($s) ? $s + ['sent' => [], 'last_run' => ''] : ['sent' => [], 'last_run' => ''];
    }

    private function save(array $s): void
    {
        file_put_contents($this->file, json_encode($s), LOCK_EX);
    }

    /** ¿Toca ejecutar hoy? (una vez al día, a partir de las 8:00) */
    public function due(?int $now = null): bool
    {
        $now ??= time();

        return (int) date('G', $now) >= 8 && $this->load()['last_run'] !== date('Y-m-d', $now);
    }

    /** Caídas de un dominio: lista de ['key','text']. */
    public function dropsFor(array $property): array
    {
        $id = (int) $property['id'];
        $last = $this->pdo->prepare('SELECT MAX(date) FROM gsc_site_daily WHERE property_id = ?');
        $last->execute([$id]);
        $to = (string) $last->fetchColumn();
        if ($to === '') {
            return [];
        }
        $from = date('Y-m-d', strtotime($to . ' -' . (self::DAYS - 1) . ' days'));
        $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . (self::DAYS - 1) . ' days'));
        // Sin histórico suficiente del periodo anterior no se compara (no sería justo)
        if (!empty($property['data_from']) && $property['data_from'] > $prevFrom) {
            return [];
        }
        $sum = $this->pdo->prepare('SELECT COALESCE(SUM(clicks), 0) c, COALESCE(SUM(impressions), 0) i FROM gsc_site_daily WHERE property_id = ? AND date >= ? AND date <= ?');
        $sum->execute([$id, $from, $to]);
        $now = $sum->fetch(PDO::FETCH_ASSOC);
        $sum->execute([$id, $prevFrom, $prevTo]);
        $prev = $sum->fetch(PDO::FETCH_ASSOC);

        $out = [];
        if ($prev['c'] >= self::MIN_PREV_CLICKS && $now['c'] <= $prev['c'] * 0.5) {
            $out[] = ['key' => $property['domain'] . '|site-clicks', 'text' => "Los clics bajan de {$prev['c']} a {$now['c']} (−" . round((1 - $now['c'] / $prev['c']) * 100) . ' %) frente a los 28 días anteriores.'];
        } elseif ($prev['i'] >= self::MIN_PREV_IMPRESSIONS && $now['i'] <= $prev['i'] * 0.5) {
            $out[] = ['key' => $property['domain'] . '|site-impr', 'text' => "Las impresiones bajan de {$prev['i']} a {$now['i']} (−" . round((1 - $now['i'] / $prev['i']) * 100) . ' %) frente a los 28 días anteriores.'];
        }
        $pages = $this->pdo->prepare('SELECT page, SUM(CASE WHEN date >= ? AND date <= ? THEN clicks ELSE 0 END) AS c, SUM(CASE WHEN date >= ? AND date <= ? THEN clicks ELSE 0 END) AS pc FROM gsc_page_daily WHERE property_id = ? AND date >= ? AND date <= ? GROUP BY page HAVING SUM(CASE WHEN date >= ? AND date <= ? THEN clicks ELSE 0 END) >= ? ORDER BY pc DESC LIMIT 20');
        $pages->execute([$from, $to, $prevFrom, $prevTo, $id, $prevFrom, $to, $prevFrom, $prevTo, self::MIN_PAGE_PREV_CLICKS]);
        foreach ($pages->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if ((int) $p['c'] <= (int) $p['pc'] * 0.5) {
                $out[] = ['key' => $property['domain'] . '|page|' . sha1($p['page']), 'text' => 'La página ' . $p['page'] . " pasa de {$p['pc']} a {$p['c']} clics."];
            }
            if (count($out) >= 4) {
                break;
            }
        }

        return $out;
    }

    /**
     * Revisa todos los dominios y envía un solo correo con lo nuevo.
     *
     * @param callable(string,string,string):bool $mail (destinatario, asunto, html)
     * @return array{checked:int,alerts:int,sent:bool}
     */
    public function run(PropertyService $props, callable $mail, array $emails, ?int $now = null): array
    {
        $now ??= time();
        $state = $this->load();
        $checked = 0;
        $new = [];
        foreach ($props->visibleForSuperadmin() as $p) {
            if ($p['status'] !== 'active' || ($p['page_filter'] ?? '') !== '') {
                continue; // los subdominios virtuales ya están dentro del dominio que los cubre
            }
            $checked++;
            $drops = array_filter($this->dropsFor($p), fn ($d) => ($now - (int) ($state['sent'][$d['key']] ?? 0)) > self::REPEAT_AFTER);
            if ($drops) {
                $new[$p['domain']] = $drops;
            }
        }
        $sent = false;
        if ($new) {
            $html = '<p>El SEO de estos dominios ha caído de forma relevante en Google:</p>';
            foreach ($new as $domain => $drops) {
                $html .= '<h4 style="margin:14px 0 4px">' . htmlspecialchars($domain) . '</h4><ul>';
                foreach ($drops as $d) {
                    $html .= '<li>' . htmlspecialchars($d['text']) . '</li>';
                }
                $html .= '</ul>';
            }
            $html .= '<p>Mira el informe del dominio en Ajustes → Search Console para ver las consultas y páginas afectadas. Puede ser una caída real o estacional; compara con el mismo periodo del año anterior si tienes histórico.</p>';
            foreach ($emails as $to) {
                $sent = $mail($to, '⚠ Caída de SEO en ' . count($new) . ' dominio(s)', $html) || $sent;
            }
            if ($sent) {
                foreach ($new as $drops) {
                    foreach ($drops as $d) {
                        $state['sent'][$d['key']] = $now;
                    }
                }
            }
        }
        if ($sent || !$new) {
            $state['last_run'] = date('Y-m-d', $now);
        }
        $state['sent'] = array_filter($state['sent'], fn ($t) => $now - (int) $t < 60 * 86400);
        $this->save($state);

        return ['checked' => $checked, 'alerts' => array_sum(array_map('count', $new)), 'sent' => $sent];
    }
}
