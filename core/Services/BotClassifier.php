<?php

namespace Screenart\Musedock\Services;

/**
 * Clasifica un user-agent: persona o robot, y qué tipo de robot (entrenamiento de IA, buscador, herramienta…).
 *
 * Solo mira la cabecera User-Agent: un robot que se hace pasar por un navegador completo no se detecta aquí.
 * Lo que sí detecta es el rastro habitual de los que se disfrazan mal (navegador sin motor reconocible, UA vacío).
 * Las visitas de robots se guardan igual (para estadísticas avanzadas); el panel las separa de las reales.
 */
class BotClassifier
{
    /** Etiquetas de cada tipo, en el orden en que se muestran. */
    public const KINDS = [
        'ai_training' => 'IA: entrenamiento y rastreo',
        'ai_assistant' => 'IA: asistentes y búsqueda con IA',
        'search' => 'Buscadores',
        'seo' => 'Herramientas SEO',
        'social' => 'Redes sociales y vistas previas',
        'monitor' => 'Monitorización',
        'tool' => 'Herramientas y scripts (curl, python…)',
        'suspect' => 'Navegador sospechoso o incompleto',
        'other' => 'Otros robots',
    ];

    /** [tipo, nombre, patrón en minúsculas]; el primero que coincide gana, así que lo específico va antes. */
    private const RULES = [
        ['ai_training', 'GPTBot (OpenAI)', 'gptbot'],
        ['ai_assistant', 'OAI-SearchBot (OpenAI)', 'oai-searchbot'],
        ['ai_assistant', 'ChatGPT-User (OpenAI)', 'chatgpt-user'],
        ['ai_training', 'ClaudeBot (Anthropic)', 'claudebot'],
        ['ai_training', 'anthropic-ai', 'anthropic-ai'],
        ['ai_assistant', 'Claude-User (Anthropic)', 'claude-user'],
        ['ai_assistant', 'Claude-SearchBot (Anthropic)', 'claude-searchbot'],
        ['ai_training', 'Google-Extended', 'google-extended'],
        ['ai_training', 'Applebot-Extended', 'applebot-extended'],
        ['ai_training', 'CCBot (Common Crawl)', 'ccbot'],
        ['ai_training', 'Bytespider (ByteDance)', 'bytespider'],
        ['ai_training', 'Meta-ExternalAgent', 'meta-externalagent'],
        ['ai_training', 'Meta-ExternalFetcher', 'meta-externalfetcher'],
        ['ai_training', 'Amazonbot', 'amazonbot'],
        ['ai_training', 'cohere-ai', 'cohere-ai'],
        ['ai_training', 'Diffbot', 'diffbot'],
        ['ai_training', 'ImagesiftBot', 'imagesiftbot'],
        ['ai_training', 'Timpibot', 'timpibot'],
        ['ai_training', 'PetalBot (Huawei)', 'petalbot'],
        ['ai_assistant', 'PerplexityBot', 'perplexity'],
        ['ai_assistant', 'DuckAssistBot', 'duckassistbot'],
        ['ai_assistant', 'MistralAI-User', 'mistralai-user'],
        ['search', 'Googlebot', 'googlebot'],
        ['search', 'Google (otros robots)', 'google-inspectiontool'],
        ['search', 'Google (otros robots)', 'adsbot-google'],
        ['search', 'Google (otros robots)', 'mediapartners-google'],
        ['search', 'Google (otros robots)', 'apis-google'],
        ['search', 'Google (otros robots)', 'feedfetcher-google'],
        ['search', 'Bingbot', 'bingbot'],
        ['search', 'Bingbot', 'bingpreview'],
        ['search', 'DuckDuckBot', 'duckduckbot'],
        ['search', 'YandexBot', 'yandexbot'],
        ['search', 'Baiduspider', 'baiduspider'],
        ['search', 'Applebot', 'applebot'],
        ['search', 'Yahoo Slurp', 'slurp'],
        ['search', 'Qwantify', 'qwantify'],
        ['search', 'SeznamBot', 'seznambot'],
        ['search', 'Sogou', 'sogou'],
        ['seo', 'SemrushBot', 'semrushbot'],
        ['seo', 'AhrefsBot', 'ahrefsbot'],
        ['seo', 'MJ12bot (Majestic)', 'mj12bot'],
        ['seo', 'DotBot (Moz)', 'dotbot'],
        ['seo', 'Screaming Frog', 'screaming frog'],
        ['seo', 'DataForSEO', 'dataforseo'],
        ['seo', 'BLEXBot', 'blexbot'],
        ['seo', 'SERankingBacklinksBot', 'serankingbacklinks'],
        ['social', 'Facebook', 'facebookexternalhit'],
        ['social', 'Facebook', 'facebot'],
        ['social', 'Twitter/X', 'twitterbot'],
        ['social', 'LinkedIn', 'linkedinbot'],
        ['social', 'Slack', 'slackbot'],
        ['social', 'WhatsApp', 'whatsapp'],
        ['social', 'Telegram', 'telegrambot'],
        ['social', 'Discord', 'discordbot'],
        ['social', 'Pinterest', 'pinterestbot'],
        ['monitor', 'UptimeRobot', 'uptimerobot'],
        ['monitor', 'Pingdom', 'pingdom'],
        ['monitor', 'StatusCake', 'statuscake'],
        ['monitor', 'Site24x7', 'site24x7'],
        ['monitor', 'Better Uptime', 'betteruptime'],
        ['monitor', 'Zabbix', 'zabbix'],
        ['monitor', 'Nagios', 'nagios'],
        ['tool', 'curl', 'curl/'],
        ['tool', 'wget', 'wget'],
        ['tool', 'python', 'python-requests'],
        ['tool', 'python', 'python-urllib'],
        ['tool', 'python', 'aiohttp'],
        ['tool', 'python', 'httpx'],
        ['tool', 'Go (http-client)', 'go-http-client'],
        ['tool', 'Scrapy', 'scrapy'],
        ['tool', 'Node.js (fetch/axios)', 'node-fetch'],
        ['tool', 'Node.js (fetch/axios)', 'axios/'],
        ['tool', 'Node.js (fetch/axios)', 'undici'],
        ['tool', 'Java', 'java/'],
        ['tool', 'Java', 'okhttp'],
        ['tool', 'Java', 'apache-httpclient'],
        ['tool', 'libwww-perl', 'libwww-perl'],
        ['tool', 'PHP', 'guzzlehttp'],
        ['tool', 'Navegador sin pantalla (headless)', 'headlesschrome'],
        ['tool', 'Navegador sin pantalla (headless)', 'phantomjs'],
        ['tool', 'Navegador sin pantalla (headless)', 'puppeteer'],
        ['tool', 'Navegador sin pantalla (headless)', 'playwright'],
        ['tool', 'Lector de feeds', 'feedly'],
        ['tool', 'Lector de feeds', 'feedparser'],
        ['tool', 'Escáner de seguridad', 'nikto'],
        ['tool', 'Escáner de seguridad', 'sqlmap'],
        ['tool', 'Escáner de seguridad', 'masscan'],
        ['tool', 'Escáner de seguridad', 'zgrab'],
        ['tool', 'Escáner de seguridad', 'nuclei'],
    ];

    /**
     * @return array{is_bot:bool,kind:?string,name:?string}
     */
    public static function classify(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return ['is_bot' => true, 'kind' => 'suspect', 'name' => 'Sin user-agent'];
        }
        $l = strtolower($ua);
        foreach (self::RULES as [$kind, $name, $needle]) {
            if (str_contains($l, $needle)) {
                return ['is_bot' => true, 'kind' => $kind, 'name' => $name];
            }
        }
        // Un navegador de verdad lleva un token de navegador detrás del motor (Chrome/, Safari/, Firefox/, Version/…).
        // «Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36» a secas es un disfraz incompleto.
        if (str_contains($l, 'applewebkit') && !preg_match('#(chrome|chromium|crios|safari|version/|firefox|edg|opr/|samsungbrowser|fxios|duckduckgo)#', $l)) {
            return ['is_bot' => true, 'kind' => 'suspect', 'name' => 'Navegador incompleto (AppleWebKit sin navegador)'];
        }
        if (preg_match('/crawl|spider|scraper|\bbot\b|(?<!cu)[a-z]bot[\/;) ]|\b(scan|fetch)\b/i', $ua)) {
            return ['is_bot' => true, 'kind' => 'other', 'name' => 'Otro robot (' . mb_substr(preg_replace('/[^\w.\- \/]/u', '', $ua), 0, 40) . ')'];
        }
        if (!str_contains($l, 'mozilla') && !str_contains($l, 'opera') && !str_contains($l, 'safari')) {
            return ['is_bot' => true, 'kind' => 'other', 'name' => 'Cliente no navegador'];
        }

        return ['is_bot' => false, 'kind' => null, 'name' => null];
    }

    /** Patrones SQL (ILIKE) de cada tipo para clasificar de golpe lo ya guardado; sin los heurísticos de forma. */
    public static function rules(): array
    {
        return self::RULES;
    }
}
