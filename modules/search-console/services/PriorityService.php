<?php

namespace Modules\SearchConsole\Services;

/**
 * «Qué merece tu tiempo esta semana»: de todos los dominios, las pocas cosas con más impacto potencial.
 * Reúne las oportunidades de PRIORIDAD ALTA del detector y las caídas relevantes de los dominios, las expresa en una
 * unidad común (clics por periodo de 28 días, estimados) y no deja que un solo dominio acapare la lista.
 */
class PriorityService
{
    public const PER_DOMAIN = 2;

    public function __construct(private OpportunityService $opportunities, private DropAlertService $drops)
    {
    }

    /**
     * @param array<int,array> $properties propiedades activas visibles (no virtuales)
     * @return array<int,array{domain:string,property_id:int,kind:string,impact:float,title:string,text:string}>
     */
    public function top(array $properties, int $limit = 5): array
    {
        $all = [];
        foreach ($properties as $p) {
            if (($p['status'] ?? '') !== 'active' || ($p['page_filter'] ?? '') !== '') {
                continue;
            }
            foreach ($this->drops->dropsFor($p) as $d) {
                $all[] = ['domain' => $p['domain'], 'property_id' => (int) $p['id'], 'kind' => 'drop-site', 'impact' => (float) $d['impact'],
                    'title' => 'Caída de visibilidad', 'text' => $d['text']];
            }
            $r = $this->opportunities->detect($p, 28);
            foreach ($r['items'] ?? [] as $it) {
                if ($it['tier'] !== 'high' || $it['type'] === 'rising') {
                    continue;
                }
                if (!empty($it['brand']) && $it['type'] !== 'drop') {
                    continue; // en una búsqueda de marca, salir arriba es lo normal: solo importa si cae
                }
                $impact = $it['type'] === 'far' ? $it['impressions'] * 0.01 : (float) $it['score'];
                $what = $it['kind'] === 'page' ? 'la página ' . $it['label'] : 'la búsqueda «' . $it['label'] . '»';
                $text = match ($it['type']) {
                    'low_ctr' => 'Mucha visibilidad y pocos clics en ' . $what . ': revisa su título y descripción.',
                    'striking' => ucfirst($what) . ' está a punto de despegar (posición ' . number_format((float) $it['position'], 1, ',', '.') . '): refuérzala.',
                    'far' => 'Hay demanda sin aprovechar en ' . $what . ' (' . number_format($it['impressions'], 0, ',', '.') . ' impresiones, estás lejos).',
                    default => ucfirst($what) . ': ' . $it['detail'] . '.',
                };
                $all[] = ['domain' => $p['domain'], 'property_id' => (int) $p['id'], 'kind' => $it['type'], 'impact' => $impact,
                    'title' => OpportunityService::TYPES[$it['type']][0], 'text' => $text];
            }
        }
        usort($all, fn ($a, $b) => $b['impact'] <=> $a['impact']);
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
