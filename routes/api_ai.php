<?php
use Screenart\Musedock\Route;
use Screenart\Musedock\Services\AI\AIService;
use Screenart\Musedock\Services\AI\Models\Provider;
use Screenart\Musedock\Services\AI\Models\Usage;

// Ruta para obtener proveedores disponibles
Route::get('/api/ai/providers', function() {
    try {
        $tenantId = tenant_id();
        $providers = Provider::getAll($tenantId, true);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'providers' => $providers
        ]);
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
});

// Ruta para generar contenido
Route::post('/api/ai/generate', function() {
    try {
        // Verificar permisos
        $userType = null;
        $userId = null;
        
        if (isset($_SESSION['super_admin'])) {
            $userType = 'super_admin';
            $userId = $_SESSION['super_admin']['id'];
        } elseif (isset($_SESSION['admin'])) {
            $userType = 'admin';
            $userId = $_SESSION['admin']['id'];
        } elseif (isset($_SESSION['user'])) {
            $userType = 'user';
            $userId = $_SESSION['user']['id'];
        }
        
        if (!$userId) {
            throw new \Exception("No has iniciado sesión");
        }
        
        // Verificar permiso
        if (!has_permission('advanced.ai') && !has_permission('ai.use')) {
            throw new \Exception("No tienes permiso para usar la IA");
        }
        
        // Obtener datos de la solicitud (CSRF middleware may have already consumed php://input)
        $data = $GLOBALS['_JSON_INPUT'] ?? null;
        if (!$data) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
        }

        if (!$data) {
            throw new \Exception("Datos inválidos");
        }

        // Validar datos
        if (empty($data['prompt'])) {
            throw new \Exception("El prompt es obligatorio");
        }
        
        $providerId = $data['provider_id'] ?? null;
        $tenantId = tenant_id();
        
        // Si no se especifica proveedor, usar el por defecto
        if (!$providerId) {
            $provider = Provider::getDefault($tenantId);
            if (!$provider) {
                throw new \Exception("No hay proveedores de IA activos");
            }
            $providerId = $provider['id'];
        }
        
        // Opciones adicionales
        $defaultSystemMsg = 'Eres un asistente de redacción para un editor web. Responde SOLO con el contenido solicitado en formato HTML limpio (párrafos con <p>, listas con <ul>/<ol>, encabezados con <h2>/<h3> si aplica). No incluyas explicaciones, razonamientos, preámbulos ni comentarios sobre tu proceso. No uses markdown. Responde en el mismo idioma del prompt del usuario.';
        $options = [
            'model' => $data['model'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'max_tokens' => $data['max_tokens'] ?? null,
            'system_message' => $data['system_message'] ?? $defaultSystemMsg
        ];
        
        // Metadatos para el registro
        $metadata = [
            'user_id' => $userId,
            'user_type' => $userType,
            'module' => $data['module'] ?? 'api',
            'action' => $data['action'] ?? 'generate',
            'tenant_id' => $tenantId
        ];
        
        // Generar contenido
        $result = AIService::generate($providerId, $data['prompt'], $options, $metadata);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'content' => $result['content'],
            'usage' => [
                'tokens' => $result['tokens'],
                'model' => $result['model'],
                'provider' => $result['provider']
            ]
        ]);
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
});

// ---------- AI Image Generation ----------
Route::get('/api/ai/image/providers', function() {
    $controller = new \Screenart\Musedock\Controllers\Api\ApiAIImageController();
    $controller->providers();
});

Route::post('/api/ai/image/generate', function() {
    $controller = new \Screenart\Musedock\Controllers\Api\ApiAIImageController();
    $controller->generate();
});

// Ruta para acciones rápidas
Route::post('/api/ai/quick', function() {
    try {
        // Verificar permisos
        $userType = null;
        $userId = null;
        
        if (isset($_SESSION['super_admin'])) {
            $userType = 'super_admin';
            $userId = $_SESSION['super_admin']['id'];
        } elseif (isset($_SESSION['admin'])) {
            $userType = 'admin';
            $userId = $_SESSION['admin']['id'];
        } elseif (isset($_SESSION['user'])) {
            $userType = 'user';
            $userId = $_SESSION['user']['id'];
        }
        
        if (!$userId) {
            throw new \Exception("No has iniciado sesión");
        }
        
        // Verificar permiso
        if (!has_permission('advanced.ai') && !has_permission('ai.use')) {
            throw new \Exception("No tienes permiso para usar la IA");
        }
        
        // Obtener datos de la solicitud (CSRF middleware may have already consumed php://input)
        $data = $GLOBALS['_JSON_INPUT'] ?? null;
        if (!$data) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
        }

        if (!$data) {
            throw new \Exception("Datos inválidos");
        }
        
        // Validar acción
        if (empty($data['action'])) {
            throw new \Exception("La acción es obligatoria");
        }
        
        $tenantId = tenant_id();
        
        // Construir prompt según la acción
        $prompt = "";
        switch ($data['action']) {
            case 'generate':
                $prompt = "Genera contenido de alta calidad sobre el siguiente tema: " . ($data['prompt'] ?? '');
                break;
            case 'improve':
                $prompt = "Mejora la redacción del siguiente texto, manteniendo su significado pero haciéndolo más profesional y atractivo: \n\n" . ($data['text'] ?? '');
                if (!empty($data['prompt'])) {
                    $prompt .= "\n\nInstrucciones adicionales: " . $data['prompt'];
                }
                break;
            case 'summarize':
                $prompt = "Resume el siguiente texto de manera concisa manteniendo los puntos clave: \n\n" . ($data['text'] ?? '');
                break;
            case 'correct':
                $prompt = "Corrige los errores gramaticales, ortográficos y de estilo en el siguiente texto: \n\n" . ($data['text'] ?? '');
                break;
            case 'titles':
                $prompt = "Genera 5 ideas de títulos atractivos para contenido sobre: " . ($data['prompt'] ?? $data['text'] ?? '');
                break;
            case 'continue':
                $prompt = "Continúa el siguiente texto de manera coherente: \n\n" . ($data['text'] ?? '');
                if (!empty($data['prompt'])) {
                    $prompt .= "\n\nConsideraciones adicionales: " . $data['prompt'];
                }
                break;
            default:
                $prompt = $data['prompt'] ?? '';
        }
        
        // Metadatos para el registro
        $metadata = [
            'user_id' => $userId,
            'user_type' => $userType,
            'module' => $data['module'] ?? 'api',
            'action' => $data['action'],
            'tenant_id' => $tenantId
        ];
        
        // System message para que el modelo devuelva solo contenido útil
        $aiOptions = [
            'system_message' => 'Eres un asistente de redacción para un editor web. Responde SOLO con el contenido solicitado en formato HTML limpio (párrafos con <p>, listas con <ul>/<ol>, encabezados con <h2>/<h3> si aplica). No incluyas explicaciones, razonamientos, preámbulos ni comentarios sobre tu proceso. No uses markdown. Responde en el mismo idioma del prompt del usuario.'
        ];

        // Generar contenido con el proveedor por defecto
        $result = AIService::generateWithDefault($prompt, $aiOptions, $metadata);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'content' => $result['content'],
            'usage' => [
                'tokens' => $result['tokens'],
                'model' => $result['model'],
                'provider' => $result['provider']
            ]
        ]);
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
});

// ---------- Blog Auto-Tagger (single post, from editor) ----------
Route::post('/api/ai/blog/suggest-taxonomy', function() {
    try {
        $userType = null;
        $userId = null;

        if (isset($_SESSION['super_admin'])) {
            $userType = 'super_admin';
            $userId = $_SESSION['super_admin']['id'];
        } elseif (isset($_SESSION['admin'])) {
            $userType = 'admin';
            $userId = $_SESSION['admin']['id'];
        }

        if (!$userId) {
            throw new \Exception("No has iniciado sesión");
        }

        if (!has_permission('advanced.ai') && !has_permission('ai.use')) {
            throw new \Exception("No tienes permiso para usar la IA");
        }

        // Use cached JSON from CSRF middleware, or read php://input as fallback
        $data = $GLOBALS['_JSON_INPUT'] ?? null;
        if (!$data) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
        }

        if (!$data || (empty($data['title']) && empty($data['content']))) {
            throw new \Exception("Se requiere título o contenido del post");
        }

        $tenantId = tenant_id();
        $pdo = \Screenart\Musedock\Database::connect();

        // Get existing categories and tags for this tenant
        $catStmt = $pdo->prepare("SELECT id, name, slug FROM blog_categories WHERE tenant_id = ? ORDER BY name");
        $catStmt->execute([$tenantId]);
        $existingCategories = $catStmt->fetchAll(\PDO::FETCH_ASSOC);

        $tagStmt = $pdo->prepare("SELECT id, name, slug FROM blog_tags WHERE tenant_id = ? ORDER BY name");
        $tagStmt->execute([$tenantId]);
        $existingTags = $tagStmt->fetchAll(\PDO::FETCH_ASSOC);

        $catNames = array_column($existingCategories, 'name');
        $tagNames = array_column($existingTags, 'name');
        $catList = !empty($catNames) ? implode(', ', $catNames) : '(ninguna)';
        $tagList = !empty($tagNames) ? implode(', ', $tagNames) : '(ninguna)';

        // Current selections (IDs already selected in the form)
        $currentCatIds = $data['current_categories'] ?? [];
        $currentTagIds = $data['current_tags'] ?? [];

        $currentCatNames = [];
        foreach ($existingCategories as $cat) {
            if (in_array($cat['id'], $currentCatIds)) {
                $currentCatNames[] = $cat['name'];
            }
        }
        $currentTagNames = [];
        foreach ($existingTags as $tag) {
            if (in_array($tag['id'], $currentTagIds)) {
                $currentTagNames[] = $tag['name'];
            }
        }

        $currentCatStr = !empty($currentCatNames) ? implode(', ', $currentCatNames) : '(ninguna asignada)';
        $currentTagStr = !empty($currentTagNames) ? implode(', ', $currentTagNames) : '(ninguna asignada)';

        $title = $data['title'] ?? '';
        $content = mb_substr(strip_tags($data['content'] ?? ''), 0, 2000);

        $prompt = <<<PROMPT
Eres un experto en SEO y taxonomía de blogs. Analiza este post y sugiere categorías y tags.

CATEGORÍAS QUE YA EXISTEN en el sitio: {$catList}
TAGS QUE YA EXISTEN en el sitio: {$tagList}

CATEGORÍAS YA ASIGNADAS a este post: {$currentCatStr}
TAGS YA ASIGNADOS a este post: {$currentTagStr}

TÍTULO DEL POST: {$title}

CONTENIDO (primeros 2000 caracteres):
{$content}

REGLAS ESTRICTAS:
1. CATEGORÍAS: Sugiere 2-4 categorías relevantes. Reutiliza categorías existentes si son apropiadas. Solo crea nuevas si no hay ninguna adecuada.
2. TAGS: Sugiere 5-10 tags. Incluye nombres propios (empresas, productos, modelos), conceptos técnicos y términos SEO relevantes. Reutiliza tags existentes si aplican.
3. PROHIBIDO sugerir algo que el post YA tiene asignado.
4. PROHIBIDO crear duplicados semánticos de categorías/tags existentes.
5. Nombres en el mismo idioma del contenido. Nombres propios mantienen su forma original.
6. Slugs: minúsculas, sin tildes ni eñes, guiones para separar.

Responde SOLO con JSON puro, sin markdown, sin ```:
{"categories":[{"name":"Nombre","slug":"slug","is_new":true}],"tags":[{"name":"Nombre","slug":"slug","is_new":true}]}

- "is_new": true si no existe en el sitio y se debe crear, false si existe pero no está asignado a este post.
PROMPT;

        $result = AIService::generateWithDefault($prompt, [
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'system_message' => 'Eres un experto en SEO y taxonomía de contenidos. Responde SOLO con JSON válido, sin markdown ni explicaciones.',
        ], [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'user_type' => $userType,
            'module' => 'blog-auto-tagger',
            'action' => 'suggest-single',
        ]);

        // Parse AI response
        $aiContent = trim($result['content'] ?? '');
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $aiContent, $matches)) {
            $aiContent = trim($matches[1]);
        }
        $jsonStart = strpos($aiContent, '{');
        if ($jsonStart !== false && $jsonStart > 0) {
            $aiContent = substr($aiContent, $jsonStart);
        }
        $lastBrace = strrpos($aiContent, '}');
        if ($lastBrace !== false) {
            $aiContent = substr($aiContent, 0, $lastBrace + 1);
        }

        $suggestions = json_decode(trim($aiContent), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('La IA no devolvió JSON válido');
        }

        // Create new categories/tags in DB and return all with IDs
        $resultCategories = [];
        $resultTags = [];

        $catBySlug = [];
        foreach ($existingCategories as $cat) {
            $catBySlug[$cat['slug']] = $cat;
        }
        $tagBySlug = [];
        foreach ($existingTags as $tag) {
            $tagBySlug[$tag['slug']] = $tag;
        }

        foreach ($suggestions['categories'] ?? [] as $catSugg) {
            $slug = $catSugg['slug'] ?? '';
            $name = $catSugg['name'] ?? '';
            if (empty($slug) || empty($name)) continue;

            if (isset($catBySlug[$slug])) {
                $resultCategories[] = [
                    'id' => (int)$catBySlug[$slug]['id'],
                    'name' => $catBySlug[$slug]['name'],
                    'is_new' => false,
                ];
            } else {
                $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, post_count, \"order\", created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW()) RETURNING id");
                $ins->execute([$tenantId, $name, $slug]);
                $newId = (int) $ins->fetchColumn();
                $catBySlug[$slug] = ['id' => $newId, 'name' => $name, 'slug' => $slug];
                $resultCategories[] = [
                    'id' => $newId,
                    'name' => $name,
                    'is_new' => true,
                ];
            }
        }

        foreach ($suggestions['tags'] ?? [] as $tagSugg) {
            $slug = $tagSugg['slug'] ?? '';
            $name = $tagSugg['name'] ?? '';
            if (empty($slug) || empty($name)) continue;

            if (isset($tagBySlug[$slug])) {
                $resultTags[] = [
                    'id' => (int)$tagBySlug[$slug]['id'],
                    'name' => $tagBySlug[$slug]['name'],
                    'is_new' => false,
                ];
            } else {
                $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW()) RETURNING id");
                $ins->execute([$tenantId, $name, $slug]);
                $newId = (int) $ins->fetchColumn();
                $tagBySlug[$slug] = ['id' => $newId, 'name' => $name, 'slug' => $slug];
                $resultTags[] = [
                    'id' => $newId,
                    'name' => $name,
                    'is_new' => true,
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'categories' => $resultCategories,
            'tags' => $resultTags,
            'tokens_used' => $result['tokens'] ?? 0,
            'model' => $result['model'] ?? '',
        ]);
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
});