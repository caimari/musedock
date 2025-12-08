<?php
// Ruta Esperada: core/Services/AI/AIService.php

namespace Screenart\Musedock\Services\AI; // Namespace Correcto

// Dependencias Core
use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

// Dependencias del Servicio AI
use Screenart\Musedock\Services\AI\Models\Provider; // Modelo para buscar proveedores por defecto
// Importar excepciones
use Screenart\Musedock\Services\AI\Exceptions\NoActiveProviderException;
use Screenart\Musedock\Services\AI\Exceptions\ProviderNotActiveException;
use Screenart\Musedock\Services\AI\Exceptions\MissingApiKeyException;
use Screenart\Musedock\Services\AI\Exceptions\AIConfigurationException;
// Importar cliente de OpenAI instalado
use OpenAI; // Facade principal de la librería openai-php/client

class AIService // Nombre de clase Correcto
{
    /**
     * Genera contenido con un proveedor específico.
     *
     * @param int $providerId ID del proveedor.
     * @param string $prompt El prompt para la IA.
     * @param array $options Opciones adicionales que pueden sobrescribir la configuración del proveedor (model, temperature, max_tokens, system_message).
     * @param array $metadata Metadatos para logging (user_id, user_type, module, action, tenant_id).
     * @return array ['content' => string, 'tokens' => int, 'model' => string, 'provider' => string]
     * @throws ProviderNotActiveException Si el proveedor no se encuentra o no está activo.
     * @throws MissingApiKeyException Si falta la API key requerida para el proveedor.
     * @throws \InvalidArgumentException Si el prompt está vacío.
     * @throws AIConfigurationException Si el tipo de proveedor no es soportado o falta configuración esencial.
     * @throws \Exception Para errores generales de API o configuración.
     */
    public static function generate(int $providerId, string $prompt, array $options = [], array $metadata = []): array
    {
        Logger::debug("AIService::generate iniciada", compact('providerId', 'options', 'metadata'));

        // 1. Validar Prompt básico
        if (trim($prompt) === '') {
            throw new \InvalidArgumentException("El prompt no puede estar vacío.");
        }

        // 2. Obtener datos del proveedor y validar estado usando Database::query()
        $sqlProvider = "SELECT * FROM ai_providers WHERE id = :id LIMIT 1";
        $provider = Database::query($sqlProvider, ['id' => $providerId])->fetch();

        if (!$provider) {
            self::logUsage($providerId, $prompt, 0, 'error: provider_not_found', $metadata);
            throw new ProviderNotActiveException("Proveedor con ID {$providerId} no encontrado.");
        }
        if (empty($provider['active'])) {
            self::logUsage($providerId, $prompt, 0, 'error: provider_not_active', $metadata);
            throw new ProviderNotActiveException("Proveedor '{$provider['name']}' (ID: {$providerId}) no está activo.");
        }

        // 3. Validar y obtener API Key (requerida para la mayoría de los proveedores)
        $apiKey = $provider['api_key'] ?? null; // Desencriptar si es necesario
        if (empty($apiKey)) {
            self::logUsage($providerId, $prompt, 0, 'error: missing_api_key', $metadata);
            throw new MissingApiKeyException("Proveedor '{$provider['name']}' (ID: {$providerId}) no tiene API key configurada en la base de datos.");
        }
        Logger::debug("Proveedor activo encontrado y validado", ['id' => $providerId, 'name' => $provider['name'], 'type' => $provider['provider_type']]);

        // 4. Determinar el tipo de proveedor y ejecutar la lógica correspondiente
        $providerType = $provider['provider_type'] ?? 'unknown';
        $apiResult = []; // Para almacenar el resultado de la API

        try {
            switch (strtolower($providerType)) { // Convertir a minúsculas para comparación robusta
                case 'openai':
                    Logger::info("Ejecutando lógica para proveedor OpenAI", ['providerId' => $providerId]);
                    $client = OpenAI::client($apiKey); // Crear cliente OpenAI

                    // Determinar modelo final (opciones > proveedor > fallback)
                    $model = $options['model'] ?? $provider['model'] ?? 'gpt-3.5-turbo';
                    if (empty($model)) $model = 'gpt-3.5-turbo'; // Asegurar fallback

                    // Determinar temperatura final (opciones > proveedor > fallback)
                    $temperature = $options['temperature'] ?? $provider['temperature'] ?? 0.7;
                    $temperature = max(0.0, min(2.0, (float)$temperature)); // Asegurar rango válido (0.0 a 2.0)

                    // --- INICIO LÓGICA MEJORADA MAX_TOKENS ---
                    $providerMaxTokensSetting = $provider['max_tokens'] ?? null; // Valor de la DB
                    $maxTokensOption = $options['max_tokens'] ?? null;          // Valor de las opciones
                    $defaultMaxTokens = 1500;                                  // Fallback del código

                    $maxTokens = $defaultMaxTokens; // 1. Empezar con el fallback

                    // 2. Si el proveedor tiene valor > 0, usarlo
                    if (isset($providerMaxTokensSetting) && (int)$providerMaxTokensSetting > 0) {
                        $maxTokens = (int)$providerMaxTokensSetting;
                         Logger::debug("Usando max_tokens del proveedor", ['value' => $maxTokens]);
                    } else {
                         Logger::debug("max_tokens del proveedor es 0, null o inválido. Usando fallback temporal.", ['fallback' => $maxTokens]);
                    }

                    // 3. Si las opciones tienen valor > 0, sobrescribir
                    if (isset($maxTokensOption) && (int)$maxTokensOption > 0) {
                        $maxTokens = (int)$maxTokensOption;
                        Logger::debug("Sobrescribiendo max_tokens con valor de options", ['value' => $maxTokens]);
                    }

                    // 4. Asegurar un mínimo absoluto razonable
                    $minTokens = 50; // Puedes ajustar este mínimo si necesitas respuestas más cortas
                    $maxTokens = max($minTokens, $maxTokens);
                    // Opcional: Añadir un máximo absoluto si quieres limitar costes
                    // $maxTokens = min(4090, $maxTokens); // Ej: Limitar a máx 4090 tokens
                    // --- FIN LÓGICA MEJORADA MAX_TOKENS ---


                    // Construir mensajes para la API Chat Completions
                    $messages = [];
                    if (!empty($options['system_message'])) {
                        $messages[] = ['role' => 'system', 'content' => trim($options['system_message'])];
                    }
                    $messages[] = ['role' => 'user', 'content' => $prompt];

                    // Parámetros para la API (usará el $maxTokens calculado)
                    $params = [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens, // Valor final calculado
                    ];
                    Logger::debug("Parámetros FINALES para OpenAI API", $params);

                    // Realizar la llamada
                    $response = $client->chat()->create($params);

                    // Extraer resultados
                    $generatedContent = $response->choices[0]->message->content ?? '';
                    $tokensUsed = $response->usage->totalTokens ?? 0;
                    $modelUsed = $response->model ?? $model;

                    // Almacenar resultado para procesamiento posterior
                    $apiResult = [
                         'content' => trim($generatedContent),
                         'tokens' => $tokensUsed,
                         'model' => $modelUsed,
                    ];
                    break; // Fin del case 'openai'

                case 'claude':
                    Logger::warning("Implementación para Claude aún no disponible en AIService", ['providerId' => $providerId]);
                    throw new AIConfigurationException("La implementación para el proveedor 'claude' aún no está disponible.");
                    // break;

                case 'gemini':
                    Logger::warning("Implementación para Gemini aún no disponible en AIService", ['providerId' => $providerId]);
                    throw new AIConfigurationException("La implementación para el proveedor 'gemini' aún no está disponible.");
                    // break;

                default:
                    self::logUsage($providerId, $prompt, 0, 'error: unknown_provider_type', $metadata);
                    throw new AIConfigurationException("Tipo de proveedor desconocido o no soportado: '{$providerType}'");
            }

            // Si llegamos aquí, la llamada API (para el proveedor soportado) fue exitosa
            Logger::info("Llamada a API externa completada con éxito", ['provider' => $providerType, 'tokens_used' => $apiResult['tokens'] ?? 0]);

            // 6. Preparar resultado final estandarizado
            $finalResult = [
                'content' => $apiResult['content'] ?? '',
                'tokens' => (int)($apiResult['tokens'] ?? 0),
                'model' => $apiResult['model'] ?? 'unknown',
                'provider' => $provider['name']
            ];

            // 7. Registrar uso exitoso
            self::logUsage($providerId, $prompt, $finalResult['tokens'], 'success', $metadata);

            return $finalResult;

        } catch (\Throwable $e) {
            $errorMessage = "Error procesando IA con proveedor {$provider['name']} ({$providerType}): " . $e->getMessage();
            $errorMessageLog = str_replace($apiKey, '[API_KEY_REDACTED]', $errorMessage);
            Logger::error($errorMessageLog, ['exception' => get_class($e), 'providerId' => $providerId, 'trace' => $e->getTraceAsString()]);
            self::logUsage($providerId, $prompt, 0, 'error: api_call_failed - ' . substr($e->getMessage(), 0, 200), $metadata);
            throw new \Exception($errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * Genera contenido con el proveedor por defecto.
     * (Sin cambios)
     */
    public static function generateWithDefault(string $prompt, array $options = [], array $metadata = []): array
    {
        Logger::debug("AIService::generateWithDefault iniciada", compact('options', 'metadata'));
        $tenantId = $metadata['tenant_id'] ?? (function_exists('tenant_id') ? tenant_id() : null);

        if (!method_exists(Provider::class, 'getDefault')) {
             throw new \RuntimeException("El método estático Provider::getDefault() no existe.");
        }
        $defaultProvider = Provider::getDefault($tenantId);

        if (!$defaultProvider || empty($defaultProvider['active'])) {
            self::logUsage(null, $prompt, 0, 'error: no_default_provider', $metadata);
            throw new NoActiveProviderException("No hay un proveedor de IA por defecto activo o configurado.");
        }

        $defaultProviderId = (int)$defaultProvider['id'];
        Logger::info("Usando proveedor por defecto", ['providerId' => $defaultProviderId, 'name' => $defaultProvider['name']]);

        return self::generate($defaultProviderId, $prompt, $options, $metadata);
    }

    /**
     * Registra el uso de la IA en la base de datos.
     * (Sin cambios)
     */
    private static function logUsage(?int $providerId, string $prompt, int $tokensUsed, string $status, array $metadata): void
    {
        $logPromptsEnabled = true; // Asumir true
        $promptToLog = $logPromptsEnabled ? mb_substr($prompt, 0, 1500) : '[PROMPT OCULTO]';
        $statusToLog = mb_substr($status, 0, 255);

        try {
            $sql = "INSERT INTO ai_usage_logs
                        (provider_id, prompt, tokens_used, status, user_id, user_type, module, action, tenant_id, created_at)
                    VALUES
                        (:provider_id, :prompt, :tokens_used, :status, :user_id, :user_type, :module, :action, :tenant_id, NOW())";

            $params = [ /* ... parámetros ... */ ];
             $params = [
                'provider_id' => $providerId,
                'prompt'      => $promptToLog,
                'tokens_used' => $tokensUsed,
                'status'      => $statusToLog,
                'user_id'     => $metadata['user_id'] ?? null,
                'user_type'   => $metadata['user_type'] ?? null,
                'module'      => $metadata['module'] ?? 'unknown',
                'action'      => $metadata['action'] ?? 'unknown',
                'tenant_id'   => $metadata['tenant_id'] ?? null,
            ];
            Database::query($sql, $params);
            Logger::debug("Uso de IA registrado", $params);

        } catch (\Throwable $e) {
            Logger::error("Error CRÍTICO al registrar el uso de IA: " . $e->getMessage(), ['metadata' => $metadata, 'exception' => $e]);
        }
    }
}