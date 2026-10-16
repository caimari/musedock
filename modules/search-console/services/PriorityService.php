<?php

namespace Modules\SearchConsole\Services;

/**
 * «Qué merece tu tiempo esta semana»: de todos los dominios, las pocas cosas con más impacto potencial.
 * Reúne las oportunidades de PRIORIDAD ALTA del detector y las caídas relevantes de los dominios y las ordena por
 * impacto estimado × confianza × objetivo del dominio. Ni el impacto ni el orden son una previsión: son una forma de
 * decidir por dónde empezar. Un mismo dominio no ocupa más de dos puestos.
 */
class PriorityService
{
    public const PER_DOMAIN = 2;
    private const CONFIDENCE = ['high' => 1.0, 'medium' => 0.6, 'low' => 0.3];

    public function __construct(private OpportunityService $opportunities, private DropAlertService $drops)
    {
    }

    /** Identificador estable de una tarea del plan semanal (para recordar si está hecha). */
    public static function taskKey(array $item): string
    {
        return substr(sha1($item['domain'] . '|' . $item['kind'] . '|' . $item['text']), 0, 20);
    }

    /**
     * Igual que top(), pero sin recalcular todos los dominios en cada carga del panel: el resultado se guarda y se
     * reutiliza durante $ttl segundos mientras no cambien los dominios ni sus objetivos. El cron lo recalienta.
     */
    public function topCached(array $properties, int $limit, array $goals, string $file, int $ttl = 3600): array
    {
        $ids = [];
        foreach ($properties as $p) {
            $ids[] = $p['id'] . ':' . ($p['status'] ?? '') . ':' . ($p['last_sync_at'] ?? '');
        }
        $sig = sha1(json_encode([$ids, $goals, $limit]));
        $c = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (is_array($c) && ($c['sig'] ?? '') === $sig && time() - (int) ($c['at'] ?? 0) < $ttl) {
            return $c['data'];
        }
        $data = $this->top($properties, $limit, $goals);
        file_put_contents($file, json_encode(['sig' => $sig, 'at' => time(), 'data' => $data]), LOCK_EX);

        return $data;
    }

    /**
     * @param array<int,array>     $properties propiedades activas visibles
     * @param array<string,string> $goals      dominio => objetivo (GoalService::GOALS)
     * @return array<int,array{domain:string,property_id:int,kind:string,impact:float,impact_label:string,confidence:string,goal:string,title:string,text:string,rank:float}>
     */
    public function top(array $properties, int $limit = 5, array $goals = []): array
    {
        $all = [];
        $n = fn ($v) => number_format((float) $v, 0, ',', '.');
        foreach ($properties as $p) {
            if (($p['status'] ?? '') !== 'active' || ($p['page_filter'] ?? '') !== '') {
                continue;
            }
            $goal = isset(GoalService::GOALS[$goals[$p['domain']] ?? '']) ? $goals[$p['domain']] : 'traffic';
            foreach ($this->drops->dropsFor($p) as $d) {
                $isClicks = str_contains($d['key'], 'site-clicks') || str_contains($d['key'], '|page|');
                $all[] = ['domain' => $p['domain'], 'property_id' => (int) $p['id'], 'kind' => 'drop-site', 'impact' => (float) $d['impact'], 'goal' => $goal,
                    'impact_label' => $isClicks ? '≈ ' . $n($d['impact']) . ' clics menos que antes' : 'visibilidad: impresiones a la baja (no son clics)',
                    'confidence' => 'medium', 'title' => 'Caída de visibilidad', 'text' => $d['text'], 'rank' => (float) $d['impact'] * self::CONFIDENCE['medium']];
            }
            $r = $this->opportunities->detect($p, 28);
            foreach ($r['items'] ?? [] as $it) {
                if ($it['tier'] !== 'high' || $it['type'] === 'rising' || !empty($it['navigational'])) {
                    continue; // las búsquedas de un dominio (navegacionales) no son una oportunidad de contenido
                }
                if (!empty($it['brand']) && $it['type'] !== 'drop') {
                    continue; // en una búsqueda de marca, salir arriba es lo normal: solo importa si cae
                }
                $impact = $it['type'] === 'far' ? $it['impressions'] * 0.01 : (float) $it['score'];
                $isHome = $it['kind'] === 'page' && in_array(OpportunityService::shortPage($it['name'], (string) $p['domain']), ['/', '[www] /'], true);
                $what = $it['kind'] === 'page' ? ($isHome ? 'la portada' : 'la página ' . $it['label']) : 'la búsqueda «' . $it['label'] . '»';
                $text = match (true) {
                    $isHome => 'La portada recibe búsquedas de marca y otras muy distintas: analiza primero qué consultas la activan antes de cambiar su título o su enfoque.',
                    $it['type'] === 'low_ctr' => 'Mucha visibilidad y pocos clics en ' . $what . ': revisa si responde bien a lo que se busca antes de reescribir el título solo para subir el CTR.',
                    $it['type'] === 'striking' => ucfirst($what) . ' está a punto de despegar (posición ' . number_format((float) $it['position'], 1, ',', '.') . '): revisa su contenido y sus enlaces internos.',
                    $it['type'] === 'far' => 'Hay demanda en ' . $what . ' (' . $n($it['impressions']) . ' impresiones) pero estás lejos: comprueba si merece una página propia.',
                    $it['type'] === 'drop' && ($it['basis'] ?? '') === 'impressions' => ucfirst($what) . ' ha perdido impresiones (' . $it['detail'] . '). Comprueba qué se buscaba realmente antes de darlo por una pérdida.',
                    default => ucfirst($what) . ': ' . $it['detail'] . '.',
                };
                $kind = ($it['type'] === 'drop' && ($it['basis'] ?? '') === 'impressions') ? 'drop-impr' : $it['type'];
                $label = $kind === 'drop-impr' ? 'visibilidad: ' . $it['detail'] . ' (no son clics)' : '≈ +' . $n($impact) . ' clics/28 d';
                $all[] = ['domain' => $p['domain'], 'property_id' => (int) $p['id'], 'kind' => $kind, 'impact' => $impact, 'goal' => $goal, 'impact_label' => $label,
                    'confidence' => $it['confidence'] ?? 'low', 'title' => OpportunityService::TYPES[$it['type']][0], 'text' => $text,
                    'rank' => $impact * self::CONFIDENCE[$it['confidence'] ?? 'low'] * GoalService::factor($goal, $it)];
            }
        }
        usort($all, fn ($a, $b) => $b['rank'] <=> $a['rank']);
        $out = [];
        $per = [];
        foreach ($all as $a) {
            if (($per[$a['domain']] ?? 0) >= self::PER_DOMAIN) {
                continue;
            }
            $per[$a['domain']] = ($per[$a['domain']] ?? 0) + 1;
            $out[] = $a;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
