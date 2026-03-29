<?php

namespace AISkinGenerator\Controllers;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Screenart\Musedock\View;
use Screenart\Musedock\Services\AI\AIService;
use Screenart\Musedock\Models\ThemeSkin;

class DashboardController
{
    /**
     * Main page: form + preview
     */
    public function index()
    {
        $pdo = Database::connect();

        // Get active AI providers
        $stmt = $pdo->query("SELECT id, name, provider_type as provider FROM ai_providers WHERE active = 1 ORDER BY name");
        $providers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get existing global skins for the default theme
        $stmt = $pdo->query("SELECT slug, name, options FROM theme_skins WHERE is_global = TRUE AND theme_slug = 'default' ORDER BY name");
        $skins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return View::renderSuperadmin('plugins.ai-skin-generator.index', [
            'providers' => $providers,
            'skins' => $skins,
        ]);
    }

    /**
     * Generate a skin using AI
     */
    public function generate()
    {
        header('Content-Type: application/json');

        try {
            $providerId = (int) ($_POST['ai_provider_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $baseSkin = trim($_POST['base_skin'] ?? '');
            $brandColors = trim($_POST['brand_colors'] ?? '');
            $fixedStructure = trim($_POST['fixed_structure'] ?? '');

            if (!$providerId) {
                echo json_encode(['success' => false, 'error' => 'Selecciona un proveedor de IA']);
                return;
            }
            if ($description === '') {
                echo json_encode(['success' => false, 'error' => 'Escribe una descripcion del skin que quieres generar']);
                return;
            }

            // Build the prompt
            $prompt = $this->buildGeneratePrompt($description, $baseSkin, $brandColors, $fixedStructure);

            // Call AI
            $result = AIService::generate($providerId, $prompt, [
                'temperature' => 0.8,
                'max_tokens' => 3000,
            ], [
                'module' => 'ai-skin-generator',
                'action' => 'generate',
            ]);

            // Parse JSON from response
            $content = $result['content'] ?? '';
            $options = $this->extractJson($content);

            if ($options === null) {
                echo json_encode([
                    'success' => false,
                    'error' => 'La IA no devolvio un JSON valido. Intenta de nuevo.',
                    'raw' => mb_substr($content, 0, 500),
                ]);
                return;
            }

            // Validate structure
            $validSections = ['topbar', 'header', 'hero', 'blog', 'footer', 'scroll_to_top', 'custom_code'];
            $filteredOptions = [];
            foreach ($options as $key => $value) {
                if (in_array($key, $validSections) && is_array($value)) {
                    $filteredOptions[$key] = $value;
                }
            }

            if (empty($filteredOptions)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'El JSON generado no tiene secciones validas (topbar, header, hero, blog, footer...).',
                    'raw' => mb_substr($content, 0, 500),
                ]);
                return;
            }

            // Validate and sanitize values against schema
            $filteredOptions = $this->sanitizeOptions($filteredOptions);

            echo json_encode([
                'success' => true,
                'options' => $filteredOptions,
                'tokens' => $result['tokens'] ?? 0,
                'model' => $result['model'] ?? 'unknown',
            ]);

        } catch (\Throwable $e) {
            Logger::error("[AI Skin Generator] Error en generate: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Refine an existing generation with a modification request
     */
    public function refine()
    {
        header('Content-Type: application/json');

        try {
            $providerId = (int) ($_POST['ai_provider_id'] ?? 0);
            $currentOptions = trim($_POST['current_options'] ?? '');
            $modification = trim($_POST['modification'] ?? '');

            if (!$providerId) {
                echo json_encode(['success' => false, 'error' => 'Selecciona un proveedor de IA']);
                return;
            }
            if ($modification === '') {
                echo json_encode(['success' => false, 'error' => 'Describe que cambios quieres hacer']);
                return;
            }

            $currentOptionsDecoded = json_decode($currentOptions, true);
            if (!$currentOptionsDecoded) {
                echo json_encode(['success' => false, 'error' => 'Las opciones actuales no son validas']);
                return;
            }

            $prompt = $this->buildRefinePrompt($currentOptionsDecoded, $modification);

            $result = AIService::generate($providerId, $prompt, [
                'temperature' => 0.7,
                'max_tokens' => 3000,
            ], [
                'module' => 'ai-skin-generator',
                'action' => 'refine',
            ]);

            $content = $result['content'] ?? '';
            $options = $this->extractJson($content);

            if ($options === null) {
                echo json_encode([
                    'success' => false,
                    'error' => 'La IA no devolvio un JSON valido al refinar. Intenta de nuevo.',
                    'raw' => mb_substr($content, 0, 500),
                ]);
                return;
            }

            // Filter valid sections
            $validSections = ['topbar', 'header', 'hero', 'blog', 'footer', 'scroll_to_top', 'custom_code'];
            $filteredOptions = [];
            foreach ($options as $key => $value) {
                if (in_array($key, $validSections) && is_array($value)) {
                    $filteredOptions[$key] = $value;
                }
            }

            $filteredOptions = $this->sanitizeOptions($filteredOptions);

            echo json_encode([
                'success' => true,
                'options' => $filteredOptions,
                'tokens' => $result['tokens'] ?? 0,
                'model' => $result['model'] ?? 'unknown',
            ]);

        } catch (\Throwable $e) {
            Logger::error("[AI Skin Generator] Error en refine: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * AI Project Consultant: analyzes a project idea and generates a skin description
     */
    public function consult()
    {
        header('Content-Type: application/json');

        try {
            $providerId = (int) ($_POST['ai_provider_id'] ?? 0);
            $projectName = trim($_POST['project_name'] ?? '');
            $projectDesc = trim($_POST['project_description'] ?? '');

            if (!$providerId) {
                echo json_encode(['success' => false, 'error' => 'Selecciona un proveedor de IA']);
                return;
            }
            if ($projectDesc === '') {
                echo json_encode(['success' => false, 'error' => 'Describe tu proyecto']);
                return;
            }

            $nameNote = $projectName ? "El proyecto se llama: {$projectName}" : '';

            $prompt = <<<PROMPT
Eres un consultor experto en medios digitales, editoriales online y branding. Un cliente te pide consejo sobre su proyecto web.

{$nameNote}

DESCRIPCION DEL PROYECTO:
{$projectDesc}

Tu respuesta debe tener EXACTAMENTE este formato (texto plano, sin markdown, sin asteriscos, sin #):

ANALISIS:
Aqui escribes 2-3 parrafos con tu analisis del proyecto: sector con mas potencial, tipo de contenido, publico objetivo, enfoque editorial, tono visual. Se concreto, da ejemplos de secciones o categorias.

SKIN_DESCRIPTION:
Aqui escribes la descripcion visual concreta del skin en una linea.

A continuacion te doy 3 ejemplos de como debe ser la linea de SKIN_DESCRIPTION:

Ejemplo 1: si el proyecto fuera de tecnologia, la linea seria:
SKIN_DESCRIPTION:
Editorial de tecnologia y productividad digital, tonos azul electrico y blanco con acentos gris grafito, estilo moderno y limpio, tipografia sans-serif Montserrat, layout newspaper con ticker de noticias

Ejemplo 2: si el proyecto fuera de moda, la linea seria:
SKIN_DESCRIPTION:
Revista femenina de moda y lifestyle, tonos rosa empolvado y dorado con fondo crema, estilo elegante y sofisticado, tipografia serif Playfair Display, layout fashion con header centered

Ejemplo 3: si el proyecto fuera de finanzas, la linea seria:
SKIN_DESCRIPTION:
Blog corporativo de finanzas, tonos azul oscuro y verde esmeralda, estilo profesional y serio, tipografia sans-serif Roboto, layout grid con topbar oscuro

IMPORTANTE: despues de "SKIN_DESCRIPTION:" escribe DIRECTAMENTE la descripcion del estilo (colores, tipografia, layout), NO escribas frases como "una linea con..." ni instrucciones. Escribe la descripcion real del skin que recomiendas para este proyecto.
PROMPT;

            $result = AIService::generate($providerId, $prompt, [
                'temperature' => 0.8,
                'max_tokens' => 1500,
            ], [
                'module' => 'ai-skin-generator',
                'action' => 'consult',
            ]);

            $content = $result['content'] ?? '';

            // Parse the response
            $analysis = '';
            $skinDescription = '';

            // Extract ANALISIS section
            if (preg_match('/ANALISIS:\s*\n(.*?)(?=\nSKIN_DESCRIPTION:)/s', $content, $m)) {
                $analysis = trim($m[1]);
            } elseif (preg_match('/ANALISIS:\s*(.*?)(?=SKIN_DESCRIPTION:)/s', $content, $m)) {
                $analysis = trim($m[1]);
            }

            // Extract SKIN_DESCRIPTION
            if (preg_match('/SKIN_DESCRIPTION:\s*\n?(.+)/s', $content, $m)) {
                $skinDescription = trim(strtok(trim($m[1]), "\n"));
                // Clean up: remove leading quotes, dashes, "Una linea..." prefixes
                $skinDescription = preg_replace('/^[\-\"\'"]+/', '', $skinDescription);
                $skinDescription = preg_replace('/^(Una?\s+(sola\s+)?l[ií]nea\s+(con\s+)?|Descripci[oó]n\s+(visual\s+)?(concreta\s+)?:?\s*)/iu', '', $skinDescription);
                $skinDescription = trim($skinDescription, ' "\'.-');
            }

            // Fallback if parsing failed
            if (!$skinDescription || mb_strlen($skinDescription) < 20) {
                // Try to extract any line that looks like a style description
                if (preg_match('/(?:tonos?|colores?|estilo|layout|tipograf)/iu', $content)) {
                    // Find the line containing style keywords
                    foreach (explode("\n", $content) as $line) {
                        $line = trim($line);
                        if (mb_strlen($line) > 30 && preg_match('/(?:tonos?|colores?|estilo|layout|tipograf)/iu', $line)) {
                            $skinDescription = preg_replace('/^[\-\*\d\.\)]+\s*/', '', $line);
                            break;
                        }
                    }
                }
            }

            if (!$analysis && !$skinDescription) {
                $analysis = $content;
                $skinDescription = 'Editorial digital, estilo moderno y limpio';
            }

            echo json_encode([
                'success' => true,
                'analysis' => $analysis,
                'skin_description' => $skinDescription,
                'tokens' => $result['tokens'] ?? 0,
            ]);

        } catch (\Throwable $e) {
            Logger::error("[AI Skin Generator] Error en consult: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Save a generated skin
     */
    public function save()
    {
        header('Content-Type: application/json');

        try {
            $skinName = trim($_POST['skin_name'] ?? '');
            $skinDescription = trim($_POST['skin_description'] ?? '');
            $optionsJson = trim($_POST['options'] ?? '');

            if ($skinName === '') {
                echo json_encode(['success' => false, 'error' => 'El nombre del skin es obligatorio']);
                return;
            }

            $options = json_decode($optionsJson, true);
            if (!$options || !is_array($options)) {
                echo json_encode(['success' => false, 'error' => 'Las opciones del skin no son validas']);
                return;
            }

            $slug = ThemeSkin::generateSlug($skinName);

            $saved = ThemeSkin::saveSkin([
                'slug' => $slug,
                'name' => $skinName,
                'description' => $skinDescription,
                'author' => 'AI Skin Generator',
                'version' => '1.0',
                'theme_slug' => 'default',
                'screenshot' => null,
                'options' => $options,
                'is_global' => 1,
                'tenant_id' => null,
                'is_active' => 1,
            ]);

            if ($saved) {
                echo json_encode(['success' => true, 'slug' => $slug, 'message' => 'Skin "' . htmlspecialchars($skinName) . '" guardado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al guardar el skin en la base de datos']);
            }

        } catch (\Throwable $e) {
            Logger::error("[AI Skin Generator] Error en save: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Extract JSON from AI response content (between first { and last })
     */
    private function extractJson(string $content): ?array
    {
        // Try direct parse first
        $decoded = json_decode($content, true);
        if ($decoded !== null && is_array($decoded)) {
            return $decoded;
        }

        // Extract between first { and last }
        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');

        if ($firstBrace === false || $lastBrace === false || $lastBrace <= $firstBrace) {
            return null;
        }

        $jsonStr = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);
        $decoded = json_decode($jsonStr, true);

        return ($decoded !== null && is_array($decoded)) ? $decoded : null;
    }

    /**
     * Build the full generation prompt with schema and references
     */
    private function buildGeneratePrompt(string $description, string $baseSkinSlug, string $brandColors, string $fixedStructure = ''): string
    {
        $schema = $this->getThemeOptionsSchema();
        $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Get existing skins as reference
        $skinsRef = $this->getExistingSkinsReference();
        $skinsJson = json_encode($skinsRef, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // If a base skin was selected, get its options
        $baseSkinNote = '';
        if ($baseSkinSlug !== '') {
            $pdo = Database::connect();
            $stmt = $pdo->prepare("SELECT options FROM theme_skins WHERE slug = ? AND is_global = TRUE LIMIT 1");
            $stmt->execute([$baseSkinSlug]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['options'])) {
                $baseSkinNote = "\n\nSKIN BASE (usa este como punto de partida y modifica segun la descripcion):\n" . $row['options'];
            }
        }

        $colorsNote = '';
        if ($brandColors !== '') {
            $colorsNote = "\n\nCOLORES DE MARCA: {$brandColors} - Usa estos como base para la paleta. El color principal para acentos/hover, el secundario para fondos oscuros o complementos.";
        }

        $structureNote = '';
        $structureMap = [
            'portfolio' => 'ESTRUCTURA FIJA: header_layout=sidebar, blog_layout=grid, footer_layout=minimal, topbar_enabled=0. NO cambies estos layouts, solo genera colores y tipografias.',
            'newspaper' => 'ESTRUCTURA FIJA: header_layout=logo-above-left, blog_layout=newspaper, newspaper_overlay=1, blog_header_ticker=1, footer_layout=banner, topbar_enabled=1, topbar_clock=1. NO cambies estos layouts.',
            'magazine' => 'ESTRUCTURA FIJA: header_layout=centered, blog_layout=magazine, footer_layout=default, topbar_enabled=1, header_cta_enabled=1. NO cambies estos layouts.',
            'blog-clean' => 'ESTRUCTURA FIJA: header_layout=default, blog_layout=grid, footer_layout=minimal, topbar_enabled=0, header_sticky=1. NO cambies estos layouts.',
            'blog-modern' => 'ESTRUCTURA FIJA: header_layout=tema1, blog_layout=list, footer_layout=banner, topbar_enabled=1, header_sticky=1, header_cta_enabled=1. NO cambies estos layouts.',
            'editorial' => 'ESTRUCTURA FIJA: header_layout=logo-above, blog_layout=newspaper, newspaper_overlay=1, blog_header_ticker=1, footer_layout=banner, topbar_enabled=1, topbar_clock=1. NO cambies estos layouts.',
            'fashion' => 'ESTRUCTURA FIJA: header_layout=centered, blog_layout=fashion, footer_layout=default, topbar_enabled=0, header_menu_uppercase=1. NO cambies estos layouts.',
            'minimal' => 'ESTRUCTURA FIJA: header_layout=default, blog_layout=minimal, footer_layout=minimal, topbar_enabled=0. NO cambies estos layouts. Menos es mas.',
            'corporate' => 'ESTRUCTURA FIJA: header_layout=default, blog_layout=grid, footer_layout=default, topbar_enabled=1, header_sticky=1, header_cta_enabled=1. NO cambies estos layouts.',
            'banner' => 'ESTRUCTURA FIJA: header_layout=banner, blog_layout=magazine, footer_layout=banner, topbar_enabled=1, blog_header_ticker=1, header_cta_enabled=1. NO cambies estos layouts.',
        ];
        if ($fixedStructure !== '' && isset($structureMap[$fixedStructure])) {
            $structureNote = "\n\n" . $structureMap[$fixedStructure];
        }

        $prompt = <<<PROMPT
Eres un disenador web experto especializado en sitios editoriales, blogs y medios de comunicacion. Genera una configuracion de tema (skin) para un CMS.

REGLAS ESTRICTAS:
- Devuelve SOLO JSON valido, sin explicaciones, sin markdown, sin bloques de codigo
- Usa SOLO las opciones y valores del esquema
- Los colores deben ser hexadecimales (#RRGGBB)
- Los toggles son "1" (activado) o "0" (desactivado) como STRINGS
- Los selects SOLO pueden tener los valores EXACTOS especificados (no inventes nuevos)
- NO incluyas la seccion custom_code
- NO repitas el mismo color oscuro en topbar y footer, varía al menos uno

REGLAS DE PALETA DE COLORES:
- Usa MAXIMO 2-3 colores oscuros, el resto deben ser claros o medios
- El color de fondo del header (header_bg_color) debe ser CLARO (blanco o gris muy claro) a menos que sea un tema dark explicito
- Contraste minimo: texto oscuro sobre fondo claro, texto claro sobre fondo oscuro
- Los fondos oscuros (#000-#333) se reservan para topbar O footer, NO ambos iguales
- Si el tema es femenino/elegante, usa tonos empolvados (rosa suave, lavanda, crema, dorado) en vez de negros puros
- Si el tema es corporativo, usa azules y grises
- Si el tema es tech, usa azul oscuro + acento brillante
- Los hover deben ser 15-20% mas oscuros o claros que el color base
- El color de acento (hover) debe usarse consistentemente en header_link_hover_color, footer_link_hover_color, scroll_to_top_bg_color y cta_bg_color

GUIA DE LAYOUTS Y FUNCIONALIDADES (usa esta informacion para elegir la combinacion adecuada):

HEADER LAYOUTS:
- "default": Logo a la izquierda, menu a la derecha. Clasico y universal, ideal para blogs y corporativos.
- "left": Todo alineado a la izquierda, estilo moderno informal.
- "centered": Logo centrado con menus a ambos lados. Elegante, ideal para revistas y moda.
- "logo-above": Logo grande centrado arriba, menu en barra debajo. Estilo editorial/periodico clasico. Ideal con redes sociales e icono de busqueda junto al logo y fecha/reloj en la barra del menu.
- "logo-above-left": Logo arriba a la izquierda, menu debajo. Estilo editorial serio. Ideal para periodicos con redes sociales y lupa a la derecha del logo, y fecha/reloj a la derecha del menu.
- "tema1": Topbar negro integrado con logo. Estilo tech/startup moderno.
- "banner": Logo en bandera de color + menu a la derecha. Estilo llamativo, ideal para marcas con identidad fuerte.
- "sidebar": Menu lateral fijo. Estilo portfolio/personal/fotografo.

FUNCIONALIDADES DEL HEADER:
- header_search_enabled: Muestra un icono de busqueda. En logo-above/logo-above-left aparece junto al logo; en otros layouts junto al menu.
- header_social_enabled: Iconos de redes sociales. En logo-above/logo-above-left aparecen junto al logo arriba; en otros junto al menu.
- header_clock_enabled: Muestra fecha y hora. En logo-above/logo-above-left aparece a la derecha de la barra del menu, dando aspecto de periodico. En otros layouts junto al menu.
- header_sticky: Cabecera fija al hacer scroll. Bueno para sitios con mucho contenido.
- header_cta_enabled: Boton de llamada a la accion (ej: Suscribirse, Login).

BLOG LAYOUTS:
- "grid": Cuadricula de 3 columnas con tarjetas. Universal y limpio.
- "list": Lista horizontal (imagen izquierda + contenido derecha). Clasico para blogs informativos.
- "magazine": Destacado grande + cuadricula. Estilo revista, bueno para medios con contenido visual.
- "minimal": Solo texto, sin imagenes. Ultra limpio, ideal para blogs literarios o academicos.
- "newspaper": 1 post grande + 2 laterales + lista. Estilo periodico, el mas completo para medios de noticias.
- "fashion": Imagenes circulares, estilo elegante. Ideal para moda, lifestyle.

FUNCIONALIDADES DEL BLOG:
- newspaper_overlay: Solo con layout newspaper. Superpone titulo, categorias y fecha sobre la imagen del post destacado. Da aspecto de periodico premium.
- blog_header_ticker: Barra de Top Tags + Latest Post (ticker de noticias). Da aspecto de medio de comunicacion profesional.
- blog_topbar_ticker: Muestra el ticker de ultimos posts integrado en el topbar del header.

FOOTER LAYOUTS:
- "default": Footer clasico con columnas (logo, menus, contacto, redes). Completo.
- "banner": Footer con logo grande tipo bandera + columnas + copyright. Mas visual y con presencia de marca.
- "minimal": Solo copyright + enlaces legales. Ultra limpio, ideal para sitios simples o portfolios.

TOPBAR:
- Barra superior encima del header. Puede mostrar email, telefono, redes sociales.
- topbar_clock: Reloj en tiempo real en el topbar, util para medios de noticias.
- Si el header ya tiene redes sociales (header_social_enabled), considera desactivarlas en el topbar (topbar_show_social) para no duplicar.

REGLAS DE CONTRASTE OBLIGATORIAS:
- topbar_bg_color oscuro -> topbar_text_color DEBE ser claro (#f5f5f5 o similar)
- topbar_bg_color claro -> topbar_text_color DEBE ser oscuro (#1a1a1a o similar)
- header_bg_color oscuro -> header_link_color y header_logo_text_color DEBEN ser claros
- header_bg_color claro -> header_link_color y header_logo_text_color DEBEN ser oscuros
- footer_bg_color oscuro -> footer_text_color, footer_heading_color, footer_link_color DEBEN ser claros
- footer_bg_color claro -> footer_text_color, footer_heading_color, footer_link_color DEBEN ser oscuros
- footer_bottom_bg_color oscuro -> usa texto claro en esa zona
- NUNCA fondo oscuro (#000-#444) con texto oscuro (#000-#444)
- NUNCA fondo claro (#ccc-#fff) con texto claro (#ccc-#fff)

COOKIE BANNER:
- El cookie banner hereda el color de acento para sus botones. No necesitas configurarlo, el sistema lo hace automaticamente.
- footer_cookie_banner_layout: "bar" es mas discreto (barra inferior), "card" es flotante (esquina), "modal" es pantalla completa.

COMBINACIONES RECOMENDADAS:
- Periodico/Noticias: header=logo-above-left, blog=newspaper, newspaper_overlay=1, blog_header_ticker=1, topbar_clock=1, header_clock_enabled=1, footer=banner
- Revista/Magazine: header=centered, blog=magazine, footer=default, header_search_enabled=1
- Blog tech/moderno: header=default o tema1, blog=grid o list, footer=minimal o default
- Portfolio/Personal: header=sidebar, blog=grid o fashion, footer=minimal
- Editorial clasico: header=logo-above, blog=newspaper, footer=banner, header_social_enabled=1, header_clock_enabled=1

ESQUEMA DE OPCIONES DISPONIBLES:
{$schemaJson}

SKINS DE REFERENCIA (ejemplos de combinaciones que funcionan):
{$skinsJson}
{$baseSkinNote}

DESCRIPCION DEL USUARIO:
{$description}
{$colorsNote}
{$structureNote}

Genera el JSON del skin completo con todas las secciones (topbar, header, hero, blog, footer, scroll_to_top). Elige los layouts y funcionalidades que mejor encajen con la descripcion:
PROMPT;

        return $prompt;
    }

    /**
     * Build prompt for refining an existing generation
     */
    private function buildRefinePrompt(array $currentOptions, string $modification): string
    {
        $schema = $this->getThemeOptionsSchema();
        $schemaJson = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $currentJson = json_encode($currentOptions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Eres un disenador web experto. Tienes un skin de tema existente y necesitas modificarlo segun las instrucciones del usuario.

REGLAS ESTRICTAS:
- Devuelve SOLO JSON valido, sin explicaciones ni markdown ni bloques de codigo
- Usa SOLO las opciones y valores del esquema
- Los colores deben ser hexadecimales (#RRGGBB)
- Los toggles son true o false (booleanos)
- Los selects SOLO pueden tener los valores especificados
- NO incluyas custom_code (CSS/JS personalizado)
- Mantén las opciones que no se piden cambiar
- Asegurate de mantener coherencia en la paleta de colores

ESQUEMA DE OPCIONES DISPONIBLES:
{$schemaJson}

SKIN ACTUAL:
{$currentJson}

MODIFICACION SOLICITADA:
{$modification}

Devuelve el JSON completo del skin modificado:
PROMPT;

        return $prompt;
    }

    /**
     * Get existing skins as reference (simplified)
     */
    private function getExistingSkinsReference(): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT slug, name, options FROM theme_skins WHERE is_global = TRUE AND theme_slug = 'default' ORDER BY name LIMIT 5");
        $skins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $ref = [];
        foreach ($skins as $skin) {
            $options = is_string($skin['options']) ? json_decode($skin['options'], true) : $skin['options'];
            if ($options) {
                $ref[] = [
                    'name' => $skin['name'],
                    'slug' => $skin['slug'],
                    'options' => $options,
                ];
            }
        }
        return $ref;
    }

    /**
     * Validate and fix AI-generated options against the schema.
     * Corrects invalid select values, normalizes toggles, removes unknown keys.
     */
    private function sanitizeOptions(array $options): array
    {
        $schema = $this->getThemeOptionsSchema();

        foreach ($options as $section => &$sectionOpts) {
            if (!isset($schema[$section]) || !is_array($sectionOpts)) continue;

            foreach ($sectionOpts as $key => &$value) {
                if (!isset($schema[$section][$key])) continue;

                $def = $schema[$section][$key];

                switch ($def['type']) {
                    case 'toggle':
                        // Normalize to "1"/"0" strings
                        if ($value === true || $value === 'true' || $value === 1) $value = '1';
                        elseif ($value === false || $value === 'false' || $value === 0) $value = '0';
                        elseif ($value !== '1' && $value !== '0') $value = $def['default'] ? '1' : '0';
                        break;

                    case 'select':
                        // If value not in allowed list, use default
                        if (isset($def['values']) && !in_array($value, $def['values'], true)) {
                            // Try case-insensitive match
                            $found = false;
                            foreach ($def['values'] as $valid) {
                                if (strtolower($value) === strtolower($valid)) {
                                    $value = $valid;
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $value = $def['default'];
                            }
                        }
                        break;

                    case 'color':
                        // Ensure valid hex color
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                            $value = $def['default'];
                        }
                        break;
                }
            }
        }

        // Remove custom_code if present
        unset($options['custom_code']);

        // ===== AUTO-FIX: Contrast validation =====
        $contrastPairs = [
            ['topbar', 'topbar_bg_color', 'topbar', 'topbar_text_color'],
            ['header', 'header_bg_color', 'header', 'header_link_color'],
            ['header', 'header_bg_color', 'header', 'header_logo_text_color'],
            ['footer', 'footer_bg_color', 'footer', 'footer_text_color'],
            ['footer', 'footer_bg_color', 'footer', 'footer_heading_color'],
            ['footer', 'footer_bg_color', 'footer', 'footer_link_color'],
            ['footer', 'footer_bottom_bg_color', 'footer', 'footer_text_color'],
        ];

        foreach ($contrastPairs as [$bgSec, $bgKey, $txtSec, $txtKey]) {
            $bg = $options[$bgSec][$bgKey] ?? null;
            $txt = $options[$txtSec][$txtKey] ?? null;
            if ($bg && $txt && !$this->hasContrast($bg, $txt)) {
                // Flip text to white or black based on background luminance
                $options[$txtSec][$txtKey] = $this->luminance($bg) > 0.5 ? '#1a1a1a' : '#f5f5f5';
            }
        }

        // ===== AUTO-FIX: Cookie banner colors match accent =====
        $accent = $options['header']['header_link_hover_color'] ?? '#ff5e15';
        if (!isset($options['footer']['footer_cookie_btn_accept'])) {
            // Derive cookie button colors from the accent
            $options['_cookie_colors'] = [
                'accept' => $accent,
                'reject' => $this->darkenColor($accent, 30),
            ];
        }

        // ===== AUTO-FIX: Banner header layout + logo incompatibility =====
        // The "banner" header layout renders a colored flag with the logo overlaid.
        // If the bg is very dark and there's no dark logo, it may look broken.
        // We don't disable the logo but we note it for the UI.

        return $options;
    }

    /**
     * Check if two hex colors have enough contrast (simplified WCAG).
     */
    private function hasContrast(string $color1, string $color2): bool
    {
        $l1 = $this->luminance($color1);
        $l2 = $this->luminance($color2);
        $ratio = (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
        return $ratio >= 3.0; // Minimum for large text
    }

    /**
     * Get relative luminance of a hex color (0=black, 1=white).
     */
    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) return 0.5;
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Darken a hex color by a percentage.
     */
    private function darkenColor(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        $r = max(0, hexdec(substr($hex, 0, 2)) - (int)(255 * $percent / 100));
        $g = max(0, hexdec(substr($hex, 2, 2)) - (int)(255 * $percent / 100));
        $b = max(0, hexdec(substr($hex, 4, 2)) - (int)(255 * $percent / 100));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Full theme options schema (simplified for AI consumption)
     * Contains all customizable options with their types, defaults, and valid values
     */
    private function getThemeOptionsSchema(): array
    {
        return [
            'topbar' => [
                'topbar_enabled' => ['type' => 'toggle', 'default' => true],
                'topbar_show_address' => ['type' => 'toggle', 'default' => false],
                'topbar_show_email' => ['type' => 'toggle', 'default' => true],
                'topbar_show_whatsapp' => ['type' => 'toggle', 'default' => true],
                'topbar_whatsapp_icon' => ['type' => 'select', 'default' => 'whatsapp', 'values' => ['phone', 'whatsapp']],
                'topbar_show_social' => ['type' => 'toggle', 'default' => true],
                'topbar_bg_color' => ['type' => 'color', 'default' => '#1a2a40'],
                'topbar_text_color' => ['type' => 'color', 'default' => '#ffffff'],
                'topbar_clock' => ['type' => 'toggle', 'default' => false],
                'topbar_ticker_clock' => ['type' => 'toggle', 'default' => false],
                'topbar_clock_locale' => ['type' => 'select', 'default' => 'es', 'values' => ['es', 'en', 'fr', 'de', 'pt']],
                'topbar_clock_timezone' => ['type' => 'select', 'default' => 'Europe/Madrid', 'values' => ['UTC', 'Europe/Madrid', 'Europe/London', 'Europe/Paris', 'America/New_York', 'America/Los_Angeles', 'America/Mexico_City', 'America/Bogota', 'America/Argentina/Buenos_Aires', 'America/Santiago', 'America/Lima', 'America/Caracas', 'Asia/Tokyo', 'Asia/Shanghai', 'Australia/Sydney']],
            ],
            'header' => [
                'header_layout' => ['type' => 'select', 'default' => 'default', 'values' => ['default', 'left', 'centered', 'logo-above', 'logo-above-left', 'tema1', 'aca', 'sidebar', 'banner']],
                'header_content_width' => ['type' => 'select', 'default' => 'full', 'values' => ['full', 'boxed']],
                'header_logo_max_height' => ['type' => 'select', 'default' => '45', 'values' => ['30', '40', '45', '55', '65', '80', '100', '120']],
                'header_bg_color' => ['type' => 'color', 'default' => '#f8f9fa'],
                'header_logo_text_color' => ['type' => 'color', 'default' => '#1a2a40'],
                'header_logo_font' => ['type' => 'select', 'default' => 'inherit', 'values' => ['inherit', 'Arial, sans-serif', 'Georgia, serif', 'Helvetica, sans-serif', "'Times New Roman', serif", "'Courier New', monospace", "'Trebuchet MS', sans-serif", "'Verdana', sans-serif", "'Playfair Display', serif", "'Montserrat', sans-serif", "'Roboto', sans-serif", "'Open Sans', sans-serif", "'Lato', sans-serif", "'Poppins', sans-serif", "'Oswald', sans-serif", "'Raleway', sans-serif"]],
                'header_link_color' => ['type' => 'color', 'default' => '#333333'],
                'header_link_hover_color' => ['type' => 'color', 'default' => '#ff5e15'],
                'header_menu_font' => ['type' => 'select', 'default' => "'Poppins', sans-serif", 'values' => ['inherit', 'Arial, sans-serif', 'Georgia, serif', 'Helvetica, sans-serif', "'Times New Roman', serif", "'Courier New', monospace", "'Trebuchet MS', sans-serif", "'Verdana', sans-serif", "'Playfair Display', serif", "'Montserrat', sans-serif", "'Roboto', sans-serif", "'Open Sans', sans-serif", "'Lato', sans-serif", "'Poppins', sans-serif", "'Oswald', sans-serif", "'Raleway', sans-serif"]],
                'header_menu_uppercase' => ['type' => 'toggle', 'default' => true],
                'header_tagline_color' => ['type' => 'color', 'default' => '#111827'],
                'header_tagline_enabled' => ['type' => 'toggle', 'default' => true],
                'header_sticky' => ['type' => 'toggle', 'default' => false],
                'header_cta_enabled' => ['type' => 'toggle', 'default' => false],
                'header_cta_text_es' => ['type' => 'text', 'default' => 'Iniciar sesion'],
                'header_cta_text_en' => ['type' => 'text', 'default' => 'Login'],
                'header_cta_url' => ['type' => 'text', 'default' => '#'],
                'header_cta_bg_color' => ['type' => 'color', 'default' => '#ff5e15'],
                'header_cta_text_color' => ['type' => 'color', 'default' => '#ffffff'],
                'header_lang_selector_enabled' => ['type' => 'toggle', 'default' => true],
                'header_search_enabled' => ['type' => 'toggle', 'default' => false],
                'header_social_enabled' => ['type' => 'toggle', 'default' => false],
                'header_clock_enabled' => ['type' => 'toggle', 'default' => false],
            ],
            'hero' => [
                'hero_title_color' => ['type' => 'color', 'default' => '#ffffff'],
                'hero_title_font' => ['type' => 'select', 'default' => 'inherit', 'values' => ['inherit', 'Arial, sans-serif', 'Georgia, serif', 'Helvetica, sans-serif', "'Times New Roman', serif", "'Playfair Display', serif", "'Montserrat', sans-serif", "'Roboto', sans-serif", "'Open Sans', sans-serif", "'Lato', sans-serif", "'Poppins', sans-serif", "'Oswald', sans-serif", "'Raleway', sans-serif", "'Merriweather', serif", "'Nunito', sans-serif", "'Quicksand', sans-serif"]],
                'hero_subtitle_color' => ['type' => 'color', 'default' => '#ffffff'],
                'hero_overlay_color' => ['type' => 'color', 'default' => '#000000'],
                'hero_overlay_opacity' => ['type' => 'select', 'default' => '0.5', 'values' => ['0', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8']],
            ],
            'blog' => [
                'blog_layout' => ['type' => 'select', 'default' => 'grid', 'values' => ['grid', 'list', 'magazine', 'minimal', 'newspaper', 'fashion']],
                'newspaper_overlay' => ['type' => 'toggle', 'default' => false],
                'blog_header_ticker' => ['type' => 'toggle', 'default' => false],
                'blog_topbar_ticker' => ['type' => 'toggle', 'default' => false],
                'blog_ticker_bg_color' => ['type' => 'color', 'default' => '#ffffff'],
                'blog_ticker_border_color' => ['type' => 'color', 'default' => '#e5e5e5'],
                'blog_ticker_label_bg' => ['type' => 'color', 'default' => '#333333'],
                'blog_ticker_label_text' => ['type' => 'color', 'default' => '#ffffff'],
                'blog_ticker_tag_bg' => ['type' => 'color', 'default' => '#f0f0f0'],
                'blog_ticker_tag_text' => ['type' => 'color', 'default' => '#333333'],
                'blog_ticker_post_text' => ['type' => 'color', 'default' => '#333333'],
                'blog_sidebar_search' => ['type' => 'toggle', 'default' => false],
                'blog_sidebar_search_order' => ['type' => 'select', 'default' => '1', 'values' => ['1', '2', '3', '4']],
                'blog_sidebar_related_posts' => ['type' => 'toggle', 'default' => true],
                'blog_sidebar_related_posts_count' => ['type' => 'select', 'default' => '4', 'values' => ['2', '3', '4', '6']],
                'blog_sidebar_related_posts_order' => ['type' => 'select', 'default' => '2', 'values' => ['1', '2', '3', '4']],
                'blog_sidebar_tags' => ['type' => 'toggle', 'default' => true],
                'blog_sidebar_tags_order' => ['type' => 'select', 'default' => '3', 'values' => ['1', '2', '3', '4']],
                'blog_sidebar_categories' => ['type' => 'toggle', 'default' => true],
                'blog_sidebar_categories_order' => ['type' => 'select', 'default' => '4', 'values' => ['1', '2', '3', '4']],
                'blog_pagination_style' => ['type' => 'select', 'default' => 'minimal', 'values' => ['minimal', 'rounded', 'pill', 'underline', 'dots']],
            ],
            'footer' => [
                'footer_layout' => ['type' => 'select', 'default' => 'default', 'values' => ['default', 'banner', 'minimal']],
                'footer_content_width' => ['type' => 'select', 'default' => 'full', 'values' => ['full', 'boxed']],
                'footer_logo_max_height' => ['type' => 'select', 'default' => '50', 'values' => ['30', '40', '50', '65', '80', '100']],
                'footer_bg_color' => ['type' => 'color', 'default' => '#f8fafe'],
                'footer_bottom_bg_color' => ['type' => 'color', 'default' => '#ffffff'],
                'footer_text_color' => ['type' => 'color', 'default' => '#333333'],
                'footer_heading_color' => ['type' => 'color', 'default' => '#333333'],
                'footer_link_color' => ['type' => 'color', 'default' => '#333333'],
                'footer_link_hover_color' => ['type' => 'color', 'default' => '#ff5e15'],
                'footer_icon_color' => ['type' => 'color', 'default' => '#333333'],
                'footer_border_color' => ['type' => 'color', 'default' => '#e5e5e5'],
                'footer_cookie_icon_enabled' => ['type' => 'toggle', 'default' => true],
                'footer_cookie_icon' => ['type' => 'select', 'default' => 'emoji', 'values' => ['emoji', 'fa-cookie', 'fa-cookie-bite', 'fa-shield-alt', 'fa-cog', 'none']],
                'footer_cookie_banner_layout' => ['type' => 'select', 'default' => 'card', 'values' => ['card', 'bar', 'modal']],
            ],
            'scroll_to_top' => [
                'scroll_to_top_enabled' => ['type' => 'toggle', 'default' => true],
                'scroll_to_top_bg_color' => ['type' => 'color', 'default' => '#ff5e15'],
                'scroll_to_top_icon_color' => ['type' => 'color', 'default' => '#ffffff'],
                'scroll_to_top_hover_bg_color' => ['type' => 'color', 'default' => '#e54c08'],
            ],
        ];
    }
}
