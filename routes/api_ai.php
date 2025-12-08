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
        if (!has_permission('ai.use')) {
            throw new \Exception("No tienes permiso para usar la IA");
        }
        
        // Obtener datos de la solicitud
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
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
        $options = [
            'model' => $data['model'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'max_tokens' => $data['max_tokens'] ?? null,
            'system_message' => $data['system_message'] ?? null
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
        if (!has_permission('ai.use')) {
            throw new \Exception("No tienes permiso para usar la IA");
        }
        
        // Obtener datos de la solicitud
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
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
        
        // Generar contenido con el proveedor por defecto
        $result = AIService::generateWithDefault($prompt, [], $metadata);
        
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