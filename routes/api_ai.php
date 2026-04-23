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
    // Limpiar cualquier output buffer previo para garantizar JSON limpio
    while (ob_get_level() > 0) { ob_end_clean(); }
    ob_start();

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

        // Determinar tenant_id: primero del body (el superadmin edita posts de tenants remotos),
        // luego del contexto actual (tenant_id() en contexto tenant)
        $tenantId = null;
        if (!empty($data['tenant_id']) && is_numeric($data['tenant_id'])) {
            $tenantId = (int) $data['tenant_id'];
        } else {
            $tenantId = tenant_id();
        }

        if (!$tenantId) {
            throw new \Exception('No se pudo determinar el tenant del post. Asegúrate de que el post pertenece a un tenant.');
        }

        $tenantId = (int) $tenantId;
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

        // Helper para normalizar slugs (mismo criterio que BlogAutoTagger::normalizeSlug)
        $normalizeSlug = function(string $slug): string {
            $s = mb_strtolower(trim($slug), 'UTF-8');
            // Quitar acentos
            $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
            // Reemplazar caracteres no alfanuméricos por guiones
            $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
            $s = trim($s, '-');
            return $s;
        };

        // Helpers de truncado para respetar los límites varchar de la BD
        // blog_categories.name varchar(255), blog_categories.slug varchar(300)
        // blog_tags.name varchar(100), blog_tags.slug varchar(150)
        $truncateName = function(string $name, int $max): string {
            return mb_substr(trim($name), 0, $max, 'UTF-8');
        };
        $truncateSlug = function(string $slug, int $max): string {
            return mb_substr($slug, 0, $max, 'UTF-8');
        };

        // Helper upsert seguro (evita duplicados y race conditions)
        $safeUpsert = function(string $table, int $tenantId, string $name, string $slug) use ($pdo) {
            // 1. SELECT primero
            $sel = $pdo->prepare("SELECT id, name FROM {$table} WHERE tenant_id = ? AND slug = ? LIMIT 1");
            $sel->execute([$tenantId, $slug]);
            $existing = $sel->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                return ['id' => (int)$existing['id'], 'name' => $existing['name'], 'is_new' => false];
            }

            // 2. INSERT con ON CONFLICT / INSERT IGNORE
            try {
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($table === 'blog_categories') {
                    if ($driver === 'pgsql') {
                        $ins = $pdo->prepare("INSERT INTO blog_categories (tenant_id, name, slug, post_count, \"order\", created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW()) ON CONFLICT (tenant_id, slug) DO NOTHING RETURNING id");
                    } else {
                        $ins = $pdo->prepare("INSERT IGNORE INTO blog_categories (tenant_id, name, slug, post_count, `order`, created_at, updated_at) VALUES (?, ?, ?, 0, 0, NOW(), NOW())");
                    }
                } else {
                    if ($driver === 'pgsql') {
                        $ins = $pdo->prepare("INSERT INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW()) ON CONFLICT (tenant_id, slug) DO NOTHING RETURNING id");
                    } else {
                        $ins = $pdo->prepare("INSERT IGNORE INTO blog_tags (tenant_id, name, slug, post_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
                    }
                }
                $ins->execute([$tenantId, $name, $slug]);

                if ($driver === 'pgsql') {
                    $newId = $ins->fetchColumn();
                    if ($newId) {
                        return ['id' => (int)$newId, 'name' => $name, 'is_new' => true];
                    }
                } else {
                    if ($ins->rowCount() > 0) {
                        return ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'is_new' => true];
                    }
                }
            } catch (\Exception $e) {
                // Fall through
            }

            // 3. Race condition: otro request creó el registro, re-SELECT
            $sel->execute([$tenantId, $slug]);
            $row = $sel->fetch(\PDO::FETCH_ASSOC);
            return $row ? ['id' => (int)$row['id'], 'name' => $row['name'], 'is_new' => false] : null;
        };

        // Create new categories/tags in DB and return all with IDs
        $resultCategories = [];
        $resultTags = [];

        // Index existentes por slug normalizado
        $catBySlug = [];
        foreach ($existingCategories as $cat) {
            $catBySlug[$normalizeSlug($cat['slug'])] = $cat;
        }
        $tagBySlug = [];
        foreach ($existingTags as $tag) {
            $tagBySlug[$normalizeSlug($tag['slug'])] = $tag;
        }

        foreach ($suggestions['categories'] ?? [] as $catSugg) {
            $slug = $catSugg['slug'] ?? '';
            $name = $catSugg['name'] ?? '';
            if (empty($slug) || empty($name)) continue;

            // Truncar a límites de BD: blog_categories.name(255), slug(300)
            $name = $truncateName($name, 255);
            $slug = $truncateSlug($normalizeSlug($slug), 300);
            $key = $slug;

            if (isset($catBySlug[$key])) {
                $resultCategories[] = [
                    'id' => (int)$catBySlug[$key]['id'],
                    'name' => $catBySlug[$key]['name'],
                    'is_new' => false,
                ];
                continue;
            }

            $upserted = $safeUpsert('blog_categories', $tenantId, $name, $slug);
            if ($upserted) {
                $catBySlug[$key] = ['id' => $upserted['id'], 'name' => $upserted['name'], 'slug' => $slug];
                $resultCategories[] = $upserted;
            }
        }

        foreach ($suggestions['tags'] ?? [] as $tagSugg) {
            $slug = $tagSugg['slug'] ?? '';
            $name = $tagSugg['name'] ?? '';
            if (empty($slug) || empty($name)) continue;

            // Truncar a límites de BD: blog_tags.name(100), slug(150)
            $name = $truncateName($name, 100);
            $slug = $truncateSlug($normalizeSlug($slug), 150);
            $key = $slug;

            if (isset($tagBySlug[$key])) {
                $resultTags[] = [
                    'id' => (int)$tagBySlug[$key]['id'],
                    'name' => $tagBySlug[$key]['name'],
                    'is_new' => false,
                ];
                continue;
            }

            $upserted = $safeUpsert('blog_tags', $tenantId, $name, $slug);
            if ($upserted) {
                $tagBySlug[$key] = ['id' => $upserted['id'], 'name' => $upserted['name'], 'slug' => $slug];
                $resultTags[] = $upserted;
            }
        }

        // Descartar cualquier output accidental (warnings, notices) antes del JSON
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'categories' => $resultCategories,
            'tags' => $resultTags,
            'tokens_used' => $result['tokens'] ?? 0,
            'model' => $result['model'] ?? '',
        ]);
    } catch (\Throwable $e) {
        // Capturar también errores fatales (TypeError, ParseError, etc.)
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        error_log('[suggest-taxonomy] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ]);
    }
});