<?php

namespace NewsAggregator\Services;

use NewsAggregator\Models\Item;
use NewsAggregator\Models\Cluster;
use NewsAggregator\Models\Log;
use NewsAggregator\Models\Settings;
use Screenart\Musedock\Services\AI\AIService;
use Screenart\Musedock\Database;

/**
 * Servicio para reescribir noticias con IA
 * Soporta modo simple (item por item) y modo clustering (síntesis multi-fuente)
 */
class AIRewriter
{
    private int $tenantId;
    private array $settings;
    private ?int $userId = null;
    private string $userType = 'system';

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->settings = Settings::getWithDefaults($tenantId);

        // Detectar usuario de la sesión activa
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->userId = $_SESSION['admin']['id'] ?? $_SESSION['user']['id'] ?? null;
            $this->userType = isset($_SESSION['admin']) ? 'admin' : (isset($_SESSION['user']) ? 'user' : 'system');
        }
    }

    /**
     * Reescribir un item individual (modo simple)
     */
    public function rewrite(int $itemId): array
    {
        $result = [
            'success' => false,
            'tokens' => 0,
            'error' => null
        ];

        try {
            $item = Item::find($itemId);

            if (!$item) {
                throw new \Exception("Item no encontrado");
            }

            if ($item->tenant_id != $this->tenantId) {
                throw new \Exception("Acceso denegado");
            }

            // Verificar si la fuente excluye reescritura
            $source = $this->getSource($item->source_id);
            if ($source && $source->exclude_rewrite) {
                // Marcar como ready sin reescribir — usar original
                Item::updateRewritten($itemId, $item->original_title, $item->original_content, null, 0);
                $result['success'] = true;
                return $result;
            }

            // Si attribution_mode = headline_only, solo guardar titular
            if ($source && $source->attribution_mode === 'headline_only') {
                $lang = $this->settings['output_language'] ?? 'es';
                $readMoreText = $lang === 'ca' ? 'Llegir notícia completa a' : 'Leer noticia completa en';
                $headlineContent = '<p>' . htmlspecialchars($item->original_title) . '</p>'
                    . '<p><a href="' . htmlspecialchars($item->original_url) . '" target="_blank" rel="noopener">'
                    . $readMoreText . ' ' . htmlspecialchars($item->source_name ?? ($lang === 'ca' ? 'la font original' : 'la fuente original'))
                    . '</a></p>';
                Item::updateRewritten($itemId, $item->original_title, $headlineContent, null, 0);
                $result['success'] = true;
                return $result;
            }

            // Marcar como procesando
            Item::updateStatus($itemId, Item::STATUS_PROCESSING);

            $aiProviderId = $this->settings['ai_provider_id'];
            if (!$aiProviderId) {
                throw new \Exception("No hay proveedor de IA configurado");
            }

            $existingCategories = $this->getExistingCategories();

            // Si el item tiene cluster_sources (fuente verificada), construir prompt multi-fuente
            $clusterSources = !empty($item->cluster_sources) ? json_decode($item->cluster_sources, true) : [];
            if (!empty($clusterSources) && count($clusterSources) > 1) {
                $prompt = $this->buildVerifiedItemPrompt($item, $clusterSources, $existingCategories);
                $aiAction = 'rewrite-verified';
            } else {
                $prompt = $this->buildSimplePrompt($item, $existingCategories);
                $aiAction = 'rewrite';
            }

            $aiResponse = AIService::generate($aiProviderId, $prompt, [], [
                'tenant_id' => $this->tenantId,
                'module' => 'news-aggregator',
                'action' => $aiAction,
                'user_id' => $this->userId,
                'user_type' => $this->userType
            ]);

            $parsed = $this->parseResponse($aiResponse['content']);

            // Atribución programática al final del contenido
            if ($source && $source->show_attribution) {
                if (!empty($clusterSources) && count($clusterSources) > 1) {
                    $names = array_unique(array_map(fn($cs) => $cs['feed_name'] ?? 'fuente', $clusterSources));
                    $parsed['content'] = $this->appendAttribution($parsed['content'], $names);
                } else {
                    $sName = $item->source_name ?? null;
                    if ($sName) {
                        $parsed['content'] = $this->appendAttribution($parsed['content'], [$sName]);
                    }
                }
            }

            Item::updateRewritten(
                $itemId,
                $parsed['title'],
                $parsed['content'],
                $parsed['excerpt'],
                $aiResponse['tokens'] ?? 0
            );

            if (!empty($parsed['categories']) || !empty($parsed['tags'])) {
                Item::updateAITaxonomy($itemId, $parsed['categories'], $parsed['tags']);
            }

            Log::logRewrite($this->tenantId, $itemId, $aiResponse['tokens'] ?? 0, true);

            $result['success'] = true;
            $result['tokens'] = $aiResponse['tokens'] ?? 0;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            if (isset($itemId)) {
                Item::updateStatus($itemId, Item::STATUS_PENDING);
            }

            Log::logRewrite($this->tenantId, $itemId ?? 0, 0, false, $e->getMessage());
        }

        return $result;
    }

    /**
     * Reescribir un cluster (modo clustering) — síntesis multi-fuente
     */
    public function rewriteCluster(int $clusterId): array
    {
        $result = [
            'success' => false,
            'tokens' => 0,
            'error' => null
        ];

        try {
            $cluster = Cluster::findWithItems($clusterId);

            if (!$cluster) {
                throw new \Exception("Cluster no encontrado");
            }

            if (empty($cluster->items)) {
                throw new \Exception("Cluster sin items");
            }

            // Marcar cluster como procesando
            Cluster::updateStatus($clusterId, Cluster::STATUS_PROCESSING);

            $aiProviderId = $this->settings['ai_provider_id'];
            if (!$aiProviderId) {
                throw new \Exception("No hay proveedor de IA configurado");
            }

            $existingCategories = $this->getExistingCategories();
            $prompt = $this->buildClusterPrompt($cluster, $existingCategories);

            $aiResponse = AIService::generate($aiProviderId, $prompt, [], [
                'tenant_id' => $this->tenantId,
                'module' => 'news-aggregator',
                'action' => 'rewrite-cluster',
                'user_id' => $this->userId,
                'user_type' => $this->userType
            ]);

            $parsed = $this->parseResponse($aiResponse['content']);

            // Atribución programática al final del contenido
            $clusterSource = $this->getSource($cluster->source_id ?? ($cluster->items[0]->source_id ?? 0));
            if (!$clusterSource || $clusterSource->show_attribution) {
                $names = [];
                foreach ($cluster->items as $ci) {
                    $names[] = $ci->feed_name ?? $ci->source_name ?? 'fuente';
                }
                $names = array_unique($names);
                $parsed['content'] = $this->appendAttribution($parsed['content'], $names);
            }

            // Actualizar el primer item del cluster con el contenido sintetizado
            $primaryItem = $cluster->items[0];
            Item::updateRewritten(
                $primaryItem->id,
                $parsed['title'],
                $parsed['content'],
                $parsed['excerpt'],
                $aiResponse['tokens'] ?? 0
            );

            if (!empty($parsed['categories']) || !empty($parsed['tags'])) {
                Item::updateAITaxonomy($primaryItem->id, $parsed['categories'], $parsed['tags']);
            }

            // Marcar los demás items del cluster como procesados (vinculados al primero)
            foreach ($cluster->items as $clusterItem) {
                if ($clusterItem->id !== $primaryItem->id) {
                    Item::updateStatus($clusterItem->id, Item::STATUS_READY);
                }
            }

            // Marcar cluster como ready
            Cluster::updateStatus($clusterId, Cluster::STATUS_READY);

            Log::logRewrite($this->tenantId, $primaryItem->id, $aiResponse['tokens'] ?? 0, true);

            $result['success'] = true;
            $result['tokens'] = $aiResponse['tokens'] ?? 0;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();

            if (isset($clusterId)) {
                Cluster::updateStatus($clusterId, Cluster::STATUS_PENDING);
            }

            Log::logRewrite($this->tenantId, 0, 0, false, $e->getMessage());
        }

        return $result;
    }

    /**
     * Prompt para modo simple (un solo item, una sola fuente)
     */
    private function buildSimplePrompt(object $item, array $existingCategories): string
    {
        $systemPrompt = $this->settings['rewrite_prompt'];
        $outputLanguage = $this->settings['output_language'];
        $generateTags = $this->settings['auto_generate_tags'];

        $taxonomySection = '';
        if ($generateTags) {
            $categoryNames = array_map(fn($c) => $c->name, $existingCategories);
            $categoryList = !empty($categoryNames) ? implode(', ', $categoryNames) : '(no hay categorías creadas)';

            $taxonomySection = <<<TAXONOMY

6. Sugiere 1-2 categorías de las existentes que mejor encajen con la noticia
7. Genera 3-5 etiquetas (tags) relevantes para SEO

CATEGORÍAS DISPONIBLES: {$categoryList}

<categorias>Categoría1, Categoría2</categorias>
<tags>tag1, tag2, tag3, tag4</tags>
TAXONOMY;
        }

        $sourceName = $item->source_name ?? 'fuente original';

        // Atribución según idioma de salida
        $isCatalan = ($outputLanguage === 'ca');
        $attributionPhrase = $isCatalan
            ? "Segons informa {$sourceName}."
            : "Según informa {$sourceName}.";
        $quoteInstruction = $isCatalan
            ? "Si detectes citacions de persones reals, atribueix-les: \"segons {$sourceName}, [persona] va afirmar que...\""
            : "Si detectas citas de personas reales, atribúyelas: \"según {$sourceName}, [persona] afirmó que...\"";

        // Contexto adicional (fuente original + investigación)
        $extraContext = $this->buildExtraContext($item);

        $prompt = <<<PROMPT
{$systemPrompt}

IDIOMA DE SALIDA: {$outputLanguage}
IMPORTANTE: La fuente original puede estar en cualquier idioma (castellano, catalán, inglés...). Debes SIEMPRE escribir la salida en {$outputLanguage}, independientemente del idioma de la fuente.

NOTICIA ORIGINAL (fuente: {$sourceName}):
Título: {$item->original_title}
URL: {$item->original_url}
Autor original: {$item->original_author}

Extracto del feed:
{$item->original_content}
{$extraContext}
---

INSTRUCCIONES:
1. Reescribe completamente el título y el contenido con tus propias palabras EN {$outputLanguage}
2. NUNCA copies frases literales del original — parafrasea todo
3. Mantén toda la información factual
4. Aporta contexto o perspectiva adicional cuando sea posible (no te limites a parafrasear)
5. Al final del artículo incluye: "{$attributionPhrase}" con enlace a {$item->original_url}
6. Genera también un extracto de 2-3 oraciones
7. {$quoteInstruction}
8. Máximo una frase textual por fuente original{$taxonomySection}

FORMATO DE RESPUESTA (usa exactamente estas etiquetas):
<titulo>Título reescrito aquí</titulo>
<extracto>Extracto de 2-3 oraciones aquí</extracto>
<contenido>Contenido completo reescrito aquí</contenido>
PROMPT;

        return $prompt;
    }

    /**
     * Prompt para item verificado con cluster_sources (síntesis multi-fuente desde JSON)
     * Recibe el item principal + los extractos de todos los feeds guardados en cluster_sources
     */
    private function buildVerifiedItemPrompt(object $item, array $clusterSources, array $existingCategories): string
    {
        $systemPrompt = $this->settings['rewrite_prompt'];
        $outputLanguage = $this->settings['output_language'];
        $generateTags = $this->settings['auto_generate_tags'];

        // Construir sección de fuentes desde cluster_sources
        $sourcesText = '';
        $sourceNames = [];
        $sourceUrls = [];
        $num = 0;

        foreach ($clusterSources as $cs) {
            $num++;
            $sName = $cs['feed_name'] ?? ('Fuente ' . $num);
            $sourceNames[] = $sName;
            if (!empty($cs['url'])) {
                $sourceUrls[] = $cs['url'];
            }

            $content = $cs['content'] ?? '(sin extracto disponible)';
            $url = $cs['url'] ?? '';
            $author = $cs['author'] ?? '';

            $sourcesText .= <<<SOURCE

--- FUENTE {$num}: {$sName} ---
Título: {$cs['title']}
URL: {$url}
Autor: {$author}
Extracto:
{$content}

SOURCE;
        }

        $sourceCount = count(array_unique($sourceNames));
        $sourcesList = implode(', ', array_unique($sourceNames));
        $isCatalan = ($outputLanguage === 'ca');

        // Atribución
        if ($sourceCount >= 3) {
            $fontsLabel = $isCatalan ? 'Fonts' : 'Fuentes';
            $attributionInstruction = $isCatalan
                ? "Hi ha {$sourceCount} fonts que cobreixen aquest esdeveniment (fets confirmats). Al final inclou: \"{$fontsLabel}: {$sourcesList}\" amb enllaços a cadascuna."
                : "Hay {$sourceCount} fuentes que cubren este evento (hechos confirmados). Al final incluye: \"{$fontsLabel}: {$sourcesList}\" con enlaces a cada una.";
        } else {
            $fontsLabel = $isCatalan ? 'Fonts' : 'Fuentes';
            $attributionInstruction = $isCatalan
                ? "Hi ha {$sourceCount} fonts. Al final inclou: \"{$fontsLabel}: {$sourcesList}\" amb enllaços."
                : "Hay {$sourceCount} fuentes. Al final incluye: \"{$fontsLabel}: {$sourcesList}\" con enlaces.";
        }

        $taxonomySection = '';
        if ($generateTags) {
            $categoryNames = array_map(fn($c) => $c->name, $existingCategories);
            $categoryList = !empty($categoryNames) ? implode(', ', $categoryNames) : '(no hay categorías creadas)';

            $taxonomySection = <<<TAXONOMY

9. Sugiere 1-2 categorías de las existentes que mejor encajen
10. Genera 3-5 etiquetas (tags) relevantes para SEO

CATEGORÍAS DISPONIBLES: {$categoryList}

<categorias>Categoría1, Categoría2</categorias>
<tags>tag1, tag2, tag3, tag4</tags>
TAXONOMY;
        }

        $quoteInstruction = $isCatalan
            ? "Si detectes citacions de persones reals, atribueix-les: \"segons [Mitjà], [persona] va afirmar que...\""
            : "Si detectas citas de personas reales, atribúyelas: \"según [Medio], [persona] afirmó que...\"";

        // Contexto adicional (fuente original + investigación)
        $extraContext = $this->buildExtraContext($item);

        $prompt = <<<PROMPT
{$systemPrompt}

IDIOMA DE SALIDA: {$outputLanguage}
IMPORTANTE: Las fuentes originales pueden estar en cualquier idioma (castellano, catalán, inglés...). Debes SIEMPRE escribir la salida en {$outputLanguage}, independientemente del idioma de las fuentes.

MODO: SÍNTESIS DE MÚLTIPLES FUENTES ({$sourceCount} medios cubren este evento)

{$sourcesText}
{$extraContext}
---

INSTRUCCIONES:
1. Genera una SÍNTESIS ORIGINAL que combine la información de todas las fuentes EN {$outputLanguage}
2. NUNCA copies frases literales de ninguna fuente — usa tus propias palabras
3. NUNCA reproduzcas párrafos enteros de las fuentes originales
4. Cruza datos entre fuentes: si hay información que solo aparece en una, menciónala con atribución
5. Aporta contexto, conecta con antecedentes o añade perspectiva — esto es obra nueva, no paráfrasis
6. {$quoteInstruction}
7. Máximo una frase textual por fuente original
8. {$attributionInstruction}
9. Genera un extracto de 2-3 oraciones{$taxonomySection}

FORMATO DE RESPUESTA (usa exactamente estas etiquetas):
<titulo>Título sintetizado aquí</titulo>
<extracto>Extracto de 2-3 oraciones aquí</extracto>
<contenido>Contenido completo sintetizado aquí</contenido>
PROMPT;

        return $prompt;
    }

    /**
     * Prompt para modo clustering (síntesis de múltiples fuentes)
     */
    private function buildClusterPrompt(object $cluster, array $existingCategories): string
    {
        $systemPrompt = $this->settings['rewrite_prompt'];
        $outputLanguage = $this->settings['output_language'];
        $generateTags = $this->settings['auto_generate_tags'];
        $sourceCount = count($cluster->items);

        // Construir sección de fuentes
        $sourcesText = '';
        $sourceNames = [];
        $sourceUrls = [];

        foreach ($cluster->items as $i => $item) {
            $num = $i + 1;
            // Para fuentes verificadas: usar feed_name (nombre del medio RSS individual)
            $sName = $item->feed_name ?? $item->source_name ?? 'Fuente ' . $num;
            $sourceNames[] = $sName;
            $sourceUrls[] = $item->original_url;

            $sourcesText .= <<<SOURCE

--- FUENTE {$num}: {$sName} ---
Título: {$item->original_title}
URL: {$item->original_url}
Autor: {$item->original_author}
Extracto:
{$item->original_content}

SOURCE;
        }

        $sourcesList = implode(', ', array_unique($sourceNames));
        $isCatalan = ($outputLanguage === 'ca');

        // Sección de atribución según número de fuentes e idioma
        $attributionInstruction = '';
        if ($sourceCount === 1) {
            $phrase = $isCatalan
                ? "Segons informa {$sourceNames[0]}."
                : "Según informa {$sourceNames[0]}.";
            $attributionInstruction = $isCatalan
                ? "IMPORTANT: Només hi ha 1 font. Inclou obligatòriament al final: \"{$phrase}\" amb enllaç."
                : "IMPORTANTE: Solo hay 1 fuente. Incluye obligatoriamente al final: \"{$phrase}\" con enlace.";
        } elseif ($sourceCount >= 3) {
            $fontsLabel = $isCatalan ? 'Fonts' : 'Fuentes';
            $attributionInstruction = $isCatalan
                ? "Hi ha {$sourceCount} fonts que cobreixen aquest esdeveniment (fets confirmats). Al final inclou: \"{$fontsLabel}: {$sourcesList}\" amb enllaços a cadascuna."
                : "Hay {$sourceCount} fuentes que cubren este evento (hechos confirmados). Al final incluye: \"{$fontsLabel}: {$sourcesList}\" con enlaces a cada una.";
        } else {
            $fontsLabel = $isCatalan ? 'Fonts' : 'Fuentes';
            $attributionInstruction = $isCatalan
                ? "Hi ha {$sourceCount} fonts. Al final inclou: \"{$fontsLabel}: {$sourcesList}\" amb enllaços."
                : "Hay {$sourceCount} fuentes. Al final incluye: \"{$fontsLabel}: {$sourcesList}\" con enlaces.";
        }

        $taxonomySection = '';
        if ($generateTags) {
            $categoryNames = array_map(fn($c) => $c->name, $existingCategories);
            $categoryList = !empty($categoryNames) ? implode(', ', $categoryNames) : '(no hay categorías creadas)';

            $taxonomySection = <<<TAXONOMY

9. Sugiere 1-2 categorías de las existentes que mejor encajen
10. Genera 3-5 etiquetas (tags) relevantes para SEO

CATEGORÍAS DISPONIBLES: {$categoryList}

<categorias>Categoría1, Categoría2</categorias>
<tags>tag1, tag2, tag3, tag4</tags>
TAXONOMY;
        }

        $quoteInstruction = $isCatalan
            ? "Si detectes citacions de persones reals, atribueix-les: \"segons [Mitjà], [persona] va afirmar que...\""
            : "Si detectas citas de personas reales, atribúyelas: \"según [Medio], [persona] afirmó que...\"";

        $prompt = <<<PROMPT
{$systemPrompt}

IDIOMA DE SALIDA: {$outputLanguage}
IMPORTANTE: Las fuentes originales pueden estar en cualquier idioma (castellano, catalán, inglés...). Debes SIEMPRE escribir la salida en {$outputLanguage}, independientemente del idioma de las fuentes.

MODO: SÍNTESIS DE MÚLTIPLES FUENTES ({$sourceCount} medios cubren este evento)

{$sourcesText}

---

INSTRUCCIONES:
1. Genera una SÍNTESIS ORIGINAL que combine la información de todas las fuentes EN {$outputLanguage}
2. NUNCA copies frases literales de ninguna fuente — usa tus propias palabras
3. NUNCA reproduzcas párrafos enteros de las fuentes originales
4. Cruza datos entre fuentes: si hay información que solo aparece en una, menciónala con atribución
5. Aporta contexto, conecta con antecedentes o añade perspectiva — esto es obra nueva, no paráfrasis
6. {$quoteInstruction}
7. Máximo una frase textual por fuente original
8. {$attributionInstruction}
9. Genera un extracto de 2-3 oraciones{$taxonomySection}

FORMATO DE RESPUESTA (usa exactamente estas etiquetas):
<titulo>Título sintetizado aquí</titulo>
<extracto>Extracto de 2-3 oraciones aquí</extracto>
<contenido>Contenido completo sintetizado aquí</contenido>
PROMPT;

        return $prompt;
    }

    /**
     * Construir secciones adicionales de contexto para el prompt
     * Incluye: contexto de la fuente original (si marcado) + fragmentos de investigación (si marcados)
     */
    private function buildExtraContext(object $item): string
    {
        $sections = '';

        // 1. Contexto de la fuente original (texto extraído del HTML)
        if (!empty($item->source_context_included) && $item->source_context_included && !empty($item->source_context)) {
            $context = mb_substr($item->source_context, 0, 4000); // Limitar para no exceder tokens
            $sections .= <<<CTX

CONTEXTO ADICIONAL DE LA FUENTE ORIGINAL (texto completo del artículo):
{$context}

CTX;
        }

        // 2. Fragmentos de investigación externa seleccionados
        $researchContext = !empty($item->research_context) ? json_decode($item->research_context, true) : [];
        $researchIncluded = !empty($item->research_context_included) ? json_decode($item->research_context_included, true) : [];

        if (!empty($researchContext) && !empty($researchIncluded)) {
            $selectedFragments = [];
            foreach ($researchContext as $rr) {
                if (in_array($rr['id'] ?? '', $researchIncluded)) {
                    $selectedFragments[] = $rr;
                }
            }

            if (!empty($selectedFragments)) {
                $sections .= "\nINVESTIGACIÓN EXTERNA (noticias relacionadas de otros medios para enriquecer el artículo):\n";
                foreach ($selectedFragments as $i => $frag) {
                    $num = $i + 1;
                    $title = $frag['title'] ?? '';
                    $source = $frag['source'] ?? '';
                    $excerpt = $frag['excerpt'] ?? '';
                    $sections .= "- [{$num}] {$title} ({$source}): {$excerpt}\n";
                }
                $sections .= "\n";
            }
        }

        return $sections;
    }

    /**
     * Parsear respuesta de la IA
     */
    private function parseResponse(string $response): array
    {
        $title = '';
        $content = '';
        $excerpt = '';
        $categories = [];
        $tags = [];

        if (preg_match('/<titulo>(.*?)<\/titulo>/s', $response, $matches)) {
            $title = trim($matches[1]);
        }

        if (preg_match('/<extracto>(.*?)<\/extracto>/s', $response, $matches)) {
            $excerpt = trim($matches[1]);
        }

        if (preg_match('/<contenido>(.*?)<\/contenido>/s', $response, $matches)) {
            $content = trim($matches[1]);
        }

        if (preg_match('/<categorias>(.*?)<\/categorias>/s', $response, $matches)) {
            $categories = array_map('trim', explode(',', $matches[1]));
            $categories = array_filter($categories);
        }

        if (preg_match('/<tags>(.*?)<\/tags>/s', $response, $matches)) {
            $tags = array_map('trim', explode(',', $matches[1]));
            $tags = array_filter($tags);
        }

        // Fallback
        if (empty($title) && empty($content)) {
            $content = $response;
            $lines = explode("\n", $response);
            $title = trim($lines[0]);
            $content = trim(implode("\n", array_slice($lines, 1)));
        }

        if (empty($excerpt) && !empty($content)) {
            $excerpt = $this->generateExcerpt($content);
        }

        return [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'categories' => $categories,
            'tags' => $tags
        ];
    }

    /**
     * Generar excerpt del contenido
     */
    private function generateExcerpt(string $content, int $length = 200): string
    {
        $text = strip_tags($content);

        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace !== false) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            $text .= '...';
        }

        return $text;
    }

    /**
     * Obtener categorías existentes del blog
     */
    private function getExistingCategories(): array
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("
                SELECT id, name, slug
                FROM blog_categories
                WHERE tenant_id = ? AND deleted_at IS NULL
                ORDER BY name
            ");
            $stmt->execute([$this->tenantId]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Añadir atribución de fuente al final del contenido
     * 1 fuente: <p class="news-source"><em>Fuente: Nombre.</em></p>
     * 2+ fuentes: <p class="news-source"><strong>Fuentes:</strong> Medio1, Medio2.</p>
     */
    private function appendAttribution(string $content, array $sourceNames): string
    {
        $sourceNames = array_values(array_filter(array_unique($sourceNames)));
        if (empty($sourceNames)) {
            return $content;
        }

        $isCatalan = ($this->settings['output_language'] ?? 'es') === 'ca';

        if (count($sourceNames) === 1) {
            $label = $isCatalan ? 'Font' : 'Fuente';
            $attribution = '<p class="news-source"><em>' . $label . ': '
                . htmlspecialchars($sourceNames[0]) . '.</em></p>';
        } else {
            $label = $isCatalan ? 'Fonts' : 'Fuentes';
            $escaped = array_map('htmlspecialchars', $sourceNames);
            $attribution = '<p class="news-source"><strong>' . $label . ':</strong> '
                . implode(', ', $escaped) . '.</p>';
        }

        return $content . "\n" . $attribution;
    }

    /**
     * Obtener datos de una fuente
     */
    private function getSource(int $sourceId): ?object
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM news_aggregator_sources WHERE id = ?");
            $stmt->execute([$sourceId]);
            return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Procesar cola: routing per-source
     * - Items de fuentes directas → rewrite simple (item por item)
     * - Clusters de fuentes verificadas (que cumplen min_sources) → rewrite síntesis
     */
    public function processQueue(int $limit = 5): array
    {
        $processed = [];

        // 1. Procesar items pendientes de fuentes directas
        $directItems = $this->getPendingDirectItems($limit);
        foreach ($directItems as $item) {
            $result = $this->rewrite($item->id);
            $processed[] = [
                'id' => $item->id,
                'title' => $item->original_title,
                'type' => 'direct',
                'success' => $result['success'],
                'tokens' => $result['tokens'],
                'error' => $result['error']
            ];

            if ($result['success']) {
                usleep(500000);
            }

            if (count($processed) >= $limit) {
                return $processed;
            }
        }

        // 2. Procesar clusters de fuentes verificadas
        $remaining = $limit - count($processed);
        if ($remaining > 0) {
            $verifiedResults = $this->processVerifiedSources($remaining);
            $processed = array_merge($processed, $verifiedResults);
        }

        return $processed;
    }

    /**
     * Obtener items pendientes de fuentes con processing_type='direct'
     */
    private function getPendingDirectItems(int $limit): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT i.* FROM news_aggregator_items i
            JOIN news_aggregator_sources s ON i.source_id = s.id
            WHERE i.tenant_id = ? AND i.status = ?
              AND (s.processing_type = 'direct' OR s.processing_type IS NULL)
            ORDER BY i.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$this->tenantId, Item::STATUS_PENDING, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Procesar clusters de fuentes verificadas que cumplen el mínimo de feeds distintos
     */
    private function processVerifiedSources(int $limit): array
    {
        $processed = [];
        $verifiedSources = $this->getVerifiedSources();

        foreach ($verifiedSources as $source) {
            $minSources = (int) ($source->min_sources_for_publish ?? 2);
            $clusters = Cluster::getPendingForSource($this->tenantId, $source->id, $minSources, $limit);

            foreach ($clusters as $cluster) {
                $result = $this->rewriteCluster($cluster->id);
                $processed[] = [
                    'id' => $cluster->id,
                    'title' => $cluster->title,
                    'type' => 'verified',
                    'success' => $result['success'],
                    'tokens' => $result['tokens'],
                    'error' => $result['error']
                ];

                if ($result['success']) {
                    usleep(500000);
                }

                if (count($processed) >= $limit) {
                    return $processed;
                }
            }
        }

        return $processed;
    }

    /**
     * Obtener fuentes activas con processing_type='verified'
     */
    private function getVerifiedSources(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM news_aggregator_sources
            WHERE tenant_id = ? AND enabled = true AND processing_type = 'verified'
            ORDER BY name ASC
        ");
        $stmt->execute([$this->tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}
