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
use Screenart\Musedock\Services\AI\Models\Usage;
use Screenart\Musedock\Services\AI\Models\TenantProvider;
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

        // 2. Detectar si el tenant tiene API key propia
        $tenantId = $metadata['tenant_id'] ?? null;
        $usingTenantKey = false;
        $tenantProvider = null;

        if ($tenantId !== null) {
            $tenantProvider = TenantProvider::getForTenant((int) $tenantId);
        }

        if ($tenantProvider) {
            // Tenant tiene key propia → usar esa, sin cuota del sistema
            $usingTenantKey = true;
            $apiKey = $tenantProvider['api_key'];
            $providerType = $tenantProvider['provider_type'] ?? 'openai';
            $provider = [
                'id' => $tenantProvider['id'],
                'name' => 'Tenant #' . $tenantId . ' (' . $providerType . ')',
                'provider_type' => $providerType,
                'api_key' => $apiKey,
                'model' => $tenantProvider['model'] ?? 'gpt-4',
                'max_tokens' => $tenantProvider['max_tokens'] ?? 1500,
                'temperature' => $tenantProvider['temperature'] ?? 0.7,
                'endpoint' => $tenantProvider['endpoint'] ?? null,
                'active' => true
            ];
            $metadata['source'] = 'tenant_key';
            Logger::info("Usando API key propia del tenant", ['tenant_id' => $tenantId, 'provider_type' => $providerType]);
        } else {
            // Sin key propia → usar proveedor global del sistema
            $metadata['source'] = 'system_key';

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

            // Validar API Key del sistema
            $apiKey = $provider['api_key'] ?? null;
            if (empty($apiKey)) {
                self::logUsage($providerId, $prompt, 0, 'error: missing_api_key', $metadata);
                throw new MissingApiKeyException("Proveedor '{$provider['name']}' (ID: {$providerId}) no tiene API key configurada.");
            }

            // Verificar cuota diaria (solo cuando usa key del sistema)
            if ($tenantId !== null) {
                $quota = Usage::hasTenantExceededDailyLimit((int) $tenantId);
                if ($quota['exceeded']) {
                    $msg = "Límite diario de tokens excedido para tenant {$tenantId}: {$quota['used']}/{$quota['limit']} tokens usados.";
                    Logger::warning($msg);
                    self::logUsage($providerId, $prompt, 0, 'error: daily_limit_exceeded', $metadata);
                    throw new AIConfigurationException($msg);
                }
            }

            Logger::debug("Proveedor del sistema validado", ['id' => $providerId, 'name' => $provider['name'], 'type' => $provider['provider_type']]);
        }

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
                    Logger::info("Ejecutando lógica para proveedor Claude (Anthropic)", ['providerId' => $providerId]);

                    $model = $options['model'] ?? $provider['model'] ?? 'claude-sonnet-4-20250514';
                    if (empty($model)) $model = 'claude-sonnet-4-20250514';

                    $temperature = $options['temperature'] ?? $provider['temperature'] ?? 0.7;
                    $temperature = max(0.0, min(1.0, (float)$temperature));

                    $maxTokens = self::resolveMaxTokens($provider, $options, 1500);

                    $endpoint = $provider['endpoint'] ?? 'https://api.anthropic.com/v1/messages';

                    // Construir payload — Claude usa 'system' como campo separado
                    $claudeData = [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ];
                    if (!empty($options['system_message'])) {
                        $claudeData['system'] = trim($options['system_message']);
                    }

                    Logger::debug("Parámetros FINALES para Claude API", $claudeData);

                    $claudeResponse = self::curlPost($endpoint, $claudeData, [
                        'Content-Type: application/json',
                        'X-API-Key: ' . $apiKey,
                        'Anthropic-Version: 2023-06-01',
                    ]);

                    $claudeContent = $claudeResponse['content'][0]['text'] ?? '';
                    $claudeInputTokens = (int)($claudeResponse['usage']['input_tokens'] ?? 0);
                    $claudeOutputTokens = (int)($claudeResponse['usage']['output_tokens'] ?? 0);

                    $apiResult = [
                        'content' => trim($claudeContent),
                        'tokens' => $claudeInputTokens + $claudeOutputTokens,
                        'model' => $model,
                    ];
                    break;

                case 'gemini':
                    Logger::info("Ejecutando lógica para proveedor Gemini (Google)", ['providerId' => $providerId]);

                    $model = $options['model'] ?? $provider['model'] ?? 'gemini-2.0-flash';
                    if (empty($model)) $model = 'gemini-2.0-flash';

                    $temperature = $options['temperature'] ?? $provider['temperature'] ?? 0.7;
                    $temperature = max(0.0, min(2.0, (float)$temperature));

                    $maxTokens = self::resolveMaxTokens($provider, $options, 1500);

                    $geminiEndpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                    // Construir payload Gemini
                    $geminiContents = [];
                    if (!empty($options['system_message'])) {
                        $geminiContents[] = [
                            'role' => 'user',
                            'parts' => [['text' => trim($options['system_message'])]]
                        ];
                        $geminiContents[] = [
                            'role' => 'model',
                            'parts' => [['text' => 'Entendido.']]
                        ];
                    }
                    $geminiContents[] = [
                        'role' => 'user',
                        'parts' => [['text' => $prompt]]
                    ];

                    $geminiData = [
                        'contents' => $geminiContents,
                        'generationConfig' => [
                            'temperature' => $temperature,
                            'maxOutputTokens' => $maxTokens,
                            'topP' => 0.95,
                            'topK' => 40,
                        ],
                    ];

                    Logger::debug("Parámetros FINALES para Gemini API", ['model' => $model, 'temperature' => $temperature, 'maxOutputTokens' => $maxTokens]);

                    $geminiResponse = self::curlPost($geminiEndpoint, $geminiData, [
                        'Content-Type: application/json',
                    ]);

                    // Extraer contenido de la respuesta Gemini
                    $geminiContent = '';
                    if (!empty($geminiResponse['candidates'][0]['content']['parts'])) {
                        foreach ($geminiResponse['candidates'][0]['content']['parts'] as $part) {
                            if (isset($part['text'])) {
                                $geminiContent .= $part['text'];
                            }
                        }
                    }

                    // Gemini v1beta reporta tokens en usageMetadata
                    $geminiTokens = 0;
                    if (!empty($geminiResponse['usageMetadata'])) {
                        $geminiTokens = (int)($geminiResponse['usageMetadata']['promptTokenCount'] ?? 0)
                                      + (int)($geminiResponse['usageMetadata']['candidatesTokenCount'] ?? 0);
                    }
                    if ($geminiTokens === 0) {
                        $geminiTokens = (int)(strlen($prompt) / 4) + (int)(strlen($geminiContent) / 4);
                    }

                    $apiResult = [
                        'content' => trim($geminiContent),
                        'tokens' => $geminiTokens,
                        'model' => $model,
                    ];
                    break;

                case 'ollama':
                    Logger::info("Ejecutando lógica para proveedor Ollama (local)", ['providerId' => $providerId]);

                    $model = $options['model'] ?? $provider['model'] ?? 'llama3';
                    if (empty($model)) $model = 'llama3';

                    $temperature = $options['temperature'] ?? $provider['temperature'] ?? 0.7;
                    $temperature = max(0.0, min(2.0, (float)$temperature));

                    $maxTokens = self::resolveMaxTokens($provider, $options, 1500);

                    // Ollama usa API compatible con OpenAI en /v1/chat/completions
                    $ollamaEndpoint = rtrim($provider['endpoint'] ?? 'http://localhost:11434', '/') . '/v1/chat/completions';

                    $ollamaMessages = [];
                    if (!empty($options['system_message'])) {
                        $ollamaMessages[] = ['role' => 'system', 'content' => trim($options['system_message'])];
                    }
                    $ollamaMessages[] = ['role' => 'user', 'content' => $prompt];

                    $ollamaData = [
                        'model' => $model,
                        'messages' => $ollamaMessages,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                        'stream' => false,
                    ];

                    Logger::debug("Parámetros FINALES para Ollama API", $ollamaData);

                    $ollamaHeaders = ['Content-Type: application/json'];
                    if (!empty($apiKey) && $apiKey !== 'none') {
                        $ollamaHeaders[] = 'Authorization: Bearer ' . $apiKey;
                    }

                    $ollamaResponse = self::curlPost($ollamaEndpoint, $ollamaData, $ollamaHeaders, 120);

                    $ollamaContent = $ollamaResponse['choices'][0]['message']['content'] ?? '';
                    $ollamaTokens = (int)($ollamaResponse['usage']['total_tokens'] ?? 0);
                    if ($ollamaTokens === 0) {
                        $ollamaTokens = (int)(strlen($prompt) / 4) + (int)(strlen($ollamaContent) / 4);
                    }

                    $apiResult = [
                        'content' => trim($ollamaContent),
                        'tokens' => $ollamaTokens,
                        'model' => $ollamaResponse['model'] ?? $model,
                    ];
                    break;

                case 'minimax':
                    Logger::info("Ejecutando lógica para proveedor MiniMax", ['providerId' => $providerId]);

                    $model = $options['model'] ?? $provider['model'] ?? 'MiniMax-M2.5';
                    if (empty($model)) $model = 'MiniMax-M2.5';

                    $temperature = $options['temperature'] ?? $provider['temperature'] ?? 0.7;
                    // MiniMax API: temperature range (0, 1]
                    $temperature = max(0.01, min(1.0, (float)$temperature));

                    $maxTokens = self::resolveMaxTokens($provider, $options, 1500);

                    // MiniMax usa API compatible con OpenAI (nueva URL oficial)
                    $minimaxEndpoint = $provider['endpoint'] ?? 'https://api.minimax.io/v1/chat/completions';

                    $minimaxMessages = [];
                    if (!empty($options['system_message'])) {
                        $minimaxMessages[] = ['role' => 'system', 'content' => trim($options['system_message'])];
                    }
                    $minimaxMessages[] = ['role' => 'user', 'content' => $prompt];

                    $minimaxData = [
                        'model' => $model,
                        'messages' => $minimaxMessages,
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                    ];

                    Logger::debug("Parámetros FINALES para MiniMax API", $minimaxData);

                    $minimaxResponse = self::curlPost($minimaxEndpoint, $minimaxData, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey,
                    ]);

                    $minimaxContent = $minimaxResponse['choices'][0]['message']['content'] ?? '';
                    $minimaxTokens = (int)($minimaxResponse['usage']['total_tokens'] ?? 0);
                    if ($minimaxTokens === 0) {
                        $minimaxTokens = (int)(strlen($prompt) / 4) + (int)(strlen($minimaxContent) / 4);
                    }

                    $apiResult = [
                        'content' => trim($minimaxContent),
                        'tokens' => $minimaxTokens,
                        'model' => $minimaxResponse['model'] ?? $model,
                    ];
                    break;

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
     * Resuelve el valor final de max_tokens según prioridad: options > provider > default.
     */
    private static function resolveMaxTokens(array $provider, array $options, int $default = 1500): int
    {
        $maxTokens = $default;
        $providerVal = $provider['max_tokens'] ?? null;
        if (isset($providerVal) && (int)$providerVal > 0) {
            $maxTokens = (int)$providerVal;
        }
        $optionVal = $options['max_tokens'] ?? null;
        if (isset($optionVal) && (int)$optionVal > 0) {
            $maxTokens = (int)$optionVal;
        }
        return max(50, $maxTokens);
    }

    /**
     * Realiza una petición POST con cURL y devuelve la respuesta decodificada.
     *
     * @throws \Exception En caso de error de red o respuesta HTTP no exitosa.
     */
    private static function curlPost(string $url, array $data, array $headers = [], int $timeout = 60): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \Exception("Error de red (cURL): {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $errMsg = $decoded['error']['message'] ?? $decoded['base_resp']['status_msg'] ?? "HTTP {$httpCode}";
            throw new \Exception("Error en API ({$httpCode}): {$errMsg}");
        }

        return $decoded ?? [];
    }

    /**
     * Registra el uso de la IA en la base de datos.
     */
    private static function logUsage(?int $providerId, string $prompt, int $tokensUsed, string $status, array $metadata): void
    {
        $logPromptsEnabled = true; // Asumir true
        $promptToLog = $logPromptsEnabled ? mb_substr($prompt, 0, 1500) : '[PROMPT OCULTO]';
        $statusToLog = mb_substr($status, 0, 255);

        try {
            $sql = "INSERT INTO ai_usage_logs
                        (provider_id, prompt, tokens_used, status, user_id, user_type, module, action, tenant_id, source, created_at)
                    VALUES
                        (:provider_id, :prompt, :tokens_used, :status, :user_id, :user_type, :module, :action, :tenant_id, :source, NOW())";

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
                'source'      => $metadata['source'] ?? 'system_key',
            ];
            Database::query($sql, $params);
            Logger::debug("Uso de IA registrado", $params);

        } catch (\Throwable $e) {
            Logger::error("Error CRÍTICO al registrar el uso de IA: " . $e->getMessage(), ['metadata' => $metadata, 'exception' => $e]);
        }
    }
}