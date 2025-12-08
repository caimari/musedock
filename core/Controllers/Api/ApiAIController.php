<?php
namespace Screenart\Musedock\Controllers\Api;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
// Importaciones correctas para los servicios de IA
use Screenart\Musedock\Services\AI\AIService;
use Screenart\Musedock\Services\AI\Models\Provider;
use Screenart\Musedock\Services\AI\Models\Usage;
use Screenart\Musedock\Services\AI\Exceptions\NoActiveProviderException;
use Screenart\Musedock\Services\AI\Exceptions\ProviderNotActiveException;
use Screenart\Musedock\Services\AI\Exceptions\MissingApiKeyException;
use Screenart\Musedock\Services\AI\Exceptions\AIConfigurationException;

// Renombramos la clase para evitar conflictos
class ApiAIController
{
    /**
     * Obtener proveedores disponibles
     */
    public function getProviders()
    {
        try {
            Logger::debug("Obteniendo proveedores disponibles");
            $tenantId = tenant_id();
            
            // Consulta para obtener proveedores disponibles para el tenant actual
            if ($tenantId) {
                $sql = "SELECT * FROM ai_providers 
                        WHERE active = 1 AND (tenant_id = :tenant_id OR (tenant_id IS NULL AND system_wide = 1))
                        ORDER BY name ASC";
                $providers = Database::query($sql, ['tenant_id' => $tenantId])->fetchAll();
            } else {
                $sql = "SELECT * FROM ai_providers 
                        WHERE active = 1 AND tenant_id IS NULL
                        ORDER BY name ASC";
                $providers = Database::query($sql)->fetchAll();
            }
            
            $this->jsonResponse([
                'success' => true,
                'providers' => $providers
            ]);
        } catch (\Exception $e) {
            Logger::error("Error al obtener proveedores: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "Error al obtener proveedores: " . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generar contenido con IA
     */
    public function generate()
    {
        try {
            Logger::debug("Procesando solicitud de generación");
            
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
            
            Logger::debug("Usuario autenticado", ['userId' => $userId, 'userType' => $userType]);
            
            // Verificar permiso
            if (!has_permission('ai.use')) {
                throw new \Exception("No tienes permiso para usar la IA");
            }
            
            Logger::debug("Permiso 'ai.use' verificado");
            
            // Obtener datos de la solicitud
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data) {
                throw new \Exception("Datos inválidos o JSON mal formado");
            }
            
            Logger::debug("Datos recibidos", ['data' => $data]);
            
            // Validar datos
            if (empty($data['prompt']) && empty($data['text'])) {
                throw new \Exception("Se requiere texto para procesar");
            }
            
            $providerId = $data['provider_id'] ?? null;
            $tenantId = tenant_id();
            
            // Si no se especifica proveedor, usar el por defecto
            if (!$providerId) {
                Logger::debug("Buscando proveedor por defecto");
                
                // Verificar si la clase Provider existe
                if (!class_exists('\\Screenart\\Musedock\\Services\\AI\\Models\\Provider')) {
                    throw new \Exception("La clase Provider no está disponible. Verifica la instalación del sistema de IA.");
                }
                
                $provider = Provider::getDefault($tenantId);
                if (!$provider) {
                    throw new NoActiveProviderException("No hay proveedores de IA activos");
                }
                $providerId = $provider['id'];
                Logger::debug("Usando proveedor por defecto", ['providerId' => $providerId]);
            }
            
            // Construir prompt según la acción
            $prompt = $data['prompt'] ?? '';
            $text = $data['text'] ?? '';
            
            Logger::debug("Construyendo prompt", ['action' => $data['action'] ?? 'generate']);
            
            if (!empty($data['action'])) {
                switch ($data['action']) {
                    case 'improve':
                        $prompt = "Mejora la redacción del siguiente texto, manteniendo su significado pero haciéndolo más profesional y atractivo: \n\n$text";
                        if (!empty($data['prompt'])) {
                            $prompt .= "\n\nInstrucciones adicionales: " . $data['prompt'];
                        }
                        break;
                    case 'summarize':
                        $prompt = "Resume el siguiente texto de manera concisa manteniendo los puntos clave: \n\n$text";
                        break;
                    case 'correct':
                        $prompt = "Corrige los errores gramaticales, ortográficos y de estilo en el siguiente texto: \n\n$text";
                        break;
                    case 'titles':
                        $prompt = "Genera 5 ideas de títulos atractivos para contenido sobre: " . ($prompt ?: $text);
                        break;
                    case 'continue':
                        $prompt = "Continúa el siguiente texto de manera coherente: \n\n$text";
                        if (!empty($data['prompt'])) {
                            $prompt .= "\n\nConsideraciones adicionales: " . $data['prompt'];
                        }
                        break;
                }
            }
            
            Logger::debug("Prompt construido", ['action' => $data['action'] ?? 'generate']);
            
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
            
            Logger::debug("Metadatos preparados", ['metadata' => $metadata]);
            
            // Verificar si la clase AIService existe
            if (!class_exists('\\Screenart\\Musedock\\Services\\AI\\AIService')) {
                throw new \Exception("La clase AIService no está disponible. Verifica la instalación del sistema de IA.");
            }
            
            // Generar contenido
            Logger::info("Llamando a AIService::generate", ['providerId' => $providerId]);
            $result = AIService::generate($providerId, $prompt, $options, $metadata);
            Logger::info("Contenido generado exitosamente");
            
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
            exit;
            
        } catch (NoActiveProviderException $e) {
            Logger::warning("No hay proveedores activos: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "No hay proveedores de IA activos. Por favor, configura y activa un proveedor en la sección IA del panel de administración.",
                'error_type' => 'no_active_provider'
            ], 400);
            
        } catch (ProviderNotActiveException $e) {
            Logger::warning("Proveedor no activo: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "El proveedor de IA seleccionado no está activo. Por favor, actívalo en la sección IA del panel de administración.",
                'error_type' => 'provider_not_active'
            ], 400);
            
        } catch (MissingApiKeyException $e) {
            Logger::warning("Falta API key: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "El proveedor de IA seleccionado no tiene configurada una clave API. Por favor, configura la API key en la sección IA del panel de administración.",
                'error_type' => 'missing_api_key'
            ], 400);
            
        } catch (\Exception $e) {
            Logger::error("Error al generar contenido: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'general_error'
            ], 500);
        }
    }
    
    /**
     * Acción rápida con IA
     */
    public function quickAction()
    {
        try {
            Logger::debug("Procesando acción rápida con IA");
            
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
            
            Logger::debug("Usuario autenticado", ['userId' => $userId, 'userType' => $userType]);
            
            // Verificar permiso
            if (!has_permission('ai.use')) {
                throw new \Exception("No tienes permiso para usar la IA");
            }
            
            Logger::debug("Permiso 'ai.use' verificado");
            
            // Obtener datos de la solicitud
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data) {
                throw new \Exception("Datos inválidos o JSON mal formado");
            }
            
            Logger::debug("Datos recibidos", ['data' => $data]);
            
            // Validar acción
            if (empty($data['action'])) {
                throw new \Exception("La acción es obligatoria");
            }
            
            Logger::debug("Acción validada", ['action' => $data['action']]);
            
            $tenantId = tenant_id();
            
            // Construir prompt según la acción
            $prompt = "";
            
            Logger::debug("Construyendo prompt", ['action' => $data['action']]);
            
            switch ($data['action']) {
                case 'generate':
                    if (empty($data['prompt'])) {
                        throw new \Exception("Se necesita un tema para generar contenido");
                    }
                    $prompt = "Genera contenido de alta calidad sobre el siguiente tema: " . ($data['prompt'] ?? '');
                    break;
                case 'improve':
                    if (empty($data['text'])) {
                        throw new \Exception("Se necesita texto para mejorar");
                    }
                    $prompt = "Mejora la redacción del siguiente texto, manteniendo su significado pero haciéndolo más profesional y atractivo: \n\n" . ($data['text'] ?? '');
                    if (!empty($data['prompt'])) {
                        $prompt .= "\n\nInstrucciones adicionales: " . $data['prompt'];
                    }
                    break;
                case 'summarize':
                    if (empty($data['text'])) {
                        throw new \Exception("Se necesita texto para resumir");
                    }
                    $prompt = "Resume el siguiente texto de manera concisa manteniendo los puntos clave: \n\n" . ($data['text'] ?? '');
                    break;
                case 'correct':
                    if (empty($data['text'])) {
                        throw new \Exception("Se necesita texto para corregir");
                    }
                    $prompt = "Corrige los errores gramaticales, ortográficos y de estilo en el siguiente texto: \n\n" . ($data['text'] ?? '');
                    break;
                case 'titles':
                    $baseContent = $data['prompt'] ?? $data['text'] ?? '';
                    if (empty($baseContent)) {
                        throw new \Exception("Se necesita un tema para generar títulos");
                    }
                    $prompt = "Genera 5 ideas de títulos atractivos para contenido sobre: " . $baseContent;
                    break;
                case 'continue':
                    if (empty($data['text'])) {
                        throw new \Exception("Se necesita texto para continuar");
                    }
                    $prompt = "Continúa el siguiente texto de manera coherente: \n\n" . ($data['text'] ?? '');
                    if (!empty($data['prompt'])) {
                        $prompt .= "\n\nConsideraciones adicionales: " . $data['prompt'];
                    }
                    break;
                default:
                    $prompt = $data['prompt'] ?? '';
            }
            
            Logger::debug("Prompt construido", ['action' => $data['action']]);
            
            // Metadatos para el registro
            $metadata = [
                'user_id' => $userId,
                'user_type' => $userType,
                'module' => $data['module'] ?? 'api',
                'action' => $data['action'],
                'tenant_id' => $tenantId
            ];
            
            Logger::debug("Metadatos preparados", ['metadata' => $metadata]);
            
            // Verificar si la clase AIService existe antes de llamarla
            if (!class_exists('\\Screenart\\Musedock\\Services\\AI\\AIService')) {
                throw new \Exception("La clase AIService no está disponible. Verifica la instalación del sistema de IA.");
            }
            
            // Generar contenido con el proveedor por defecto
            Logger::info("Llamando a AIService::generateWithDefault");
            
            // Intentar usar la clase directamente (con namespace completo)
            $result = \Screenart\Musedock\Services\AI\AIService::generateWithDefault($prompt, [], $metadata);
            
            Logger::info("Contenido generado exitosamente");
            
            $this->jsonResponse([
                'success' => true,
                'content' => $result['content'],
                'usage' => [
                    'tokens' => $result['tokens'],
                    'model' => $result['model'],
                    'provider' => $result['provider']
                ]
            ]);
            
        } catch (NoActiveProviderException $e) {
            Logger::warning("No hay proveedores activos: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "No hay proveedores de IA activos. Por favor, configura y activa un proveedor en la sección IA del panel de administración.",
                'error_type' => 'no_active_provider'
            ], 400);
            
        } catch (ProviderNotActiveException $e) {
            Logger::warning("Proveedor no activo: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "El proveedor de IA seleccionado no está activo. Por favor, actívalo en la sección IA del panel de administración.",
                'error_type' => 'provider_not_active'
            ], 400);
            
        } catch (MissingApiKeyException $e) {
            Logger::warning("Falta API key: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => "El proveedor de IA seleccionado no tiene configurada una clave API. Por favor, configura la API key en la sección IA del panel de administración.",
                'error_type' => 'missing_api_key'
            ], 400);
            
        } catch (\Exception $e) {
            Logger::error("Error al generar contenido: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'general_error'
            ], 400);
        }
    }
    
    /**
     * Devolver una respuesta JSON
     */
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}