<?php

namespace Modules\SearchConsole\Services;

use PDO;

/**
 * Objetivo de cada dominio y cómo pondera las prioridades. El impacto SEO estimado no es lo único que importa: una
 * noticia sobre Grok no vale lo mismo que la página de contratación de un servicio. El objetivo ajusta el ORDEN de
 * «Esta semana merece tu tiempo» (nunca los datos), con palabras clave sencillas y visibles.
 */
class GoalService
{
    public const GOALS = [
        'traffic' => ['Tráfico', 'Lectores desde Google: pesa igual todo lo informativo y lo comercial.'],
        'leads' => ['Captar clientes', 'Pesan más las búsquedas comerciales (precio, comprar, alternativas, mejor…) y menos las puramente informativas.'],
        'editorial' => ['Reputación editorial', 'Pesan más los contenidos informativos y de guía (cómo, qué es, tutorial…).'],
        'cultural' => ['Difusión cultural', 'Pesan más entrevistas, selecciones, cineastas y la edición vigente.'],
    ];

    private const COMMERCIAL = '/\b(precio|precios|price|pricing|comprar|buy|contratar|hire|plan|planes|demo|alternativa|alternativas|alternative|alternatives|mejor|mejores|best|vs|presupuesto|cotizaci[oó]n|software|servicio|service|gratis|free)\b/iu';
    private const INFORMATIONAL = '/\b(c[oó]mo|como|qu[eé] es|que es|tutorial|gu[ií]a|guide|how to|what is|por qu[eé]|why|explicaci[oó]n|ejemplo|ejemplos|historia)\b/iu';
    private const CULTURAL = '/\b(entrevista|entrevistas|interview|selecci[oó]n|oficial|ganadores|winners|cineasta|cineastas|director|directora|festival|convocatoria|call|cortometraje|documental|pel[ií]cula|film)\b/iu';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string,string> dominio => objetivo */
    public function all(): array
    {
        try {
            return $this->pdo->query('SELECT domain, goal FROM gsc_property_goal')->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function set(string $domain, string $goal): bool
    {
        if (!isset(self::GOALS[$goal])) {
            return false;
        }
        $domain = PropertyService::normalizeHost($domain);
        $stmt = $this->pdo->prepare('SELECT 1 FROM gsc_property_goal WHERE domain = ?');
        $stmt->execute([$domain]);
        if ($stmt->fetchColumn()) {
            $this->pdo->prepare('UPDATE gsc_property_goal SET goal = ?, updated_at = ? WHERE domain = ?')->execute([$goal, date('Y-m-d H:i:s'), $domain]);
        } else {
            $this->pdo->prepare('INSERT INTO gsc_property_goal (domain, goal, updated_at) VALUES (?, ?, ?)')->execute([$domain, $goal, date('Y-m-d H:i:s')]);
        }

        return true;
    }

    /**
     * Multiplicador del orden según el objetivo del dominio y el contenido del aviso (consulta o ruta de la página).
     * Siempre entre 0,5 y 1,8: ajusta el orden, no lo invierte.
     */
    public static function factor(string $goal, array $item): float
    {
        $text = $item['kind'] === 'page' ? str_replace(['-', '_', '/'], ' ', (string) parse_url((string) $item['name'], PHP_URL_PATH)) : (string) $item['label'];
        $f = 1.0;
        switch ($goal) {
            case 'leads':
                if (preg_match(self::COMMERCIAL, $text)) { $f *= 1.6; }
                if (preg_match(self::INFORMATIONAL, $text)) { $f *= 0.7; }
                if (!empty($item['brand'])) { $f *= 1.2; }
                break;
            case 'editorial':
                if (preg_match(self::INFORMATIONAL, $text)) { $f *= 1.3; }
                if (preg_match(self::COMMERCIAL, $text) && !preg_match(self::INFORMATIONAL, $text)) { $f *= 0.8; }
                break;
            case 'cultural':
                if (preg_match(self::CULTURAL, $text)) { $f *= 1.3; }
                if (($item['context'] ?? null) === 'current') { $f *= 1.5; }
                break;
        }

        return max(0.5, min(1.8, $f));
    }
}
