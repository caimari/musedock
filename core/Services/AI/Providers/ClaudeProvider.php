<?php
namespace Screenart\Musedock\Services\AI\Providers;

/**
 * Implementación del proveedor Claude (Anthropic)
 */
class ClaudeProvider extends AbstractProvider
{
    /**
     * @inheritdoc
     */
    public function generate($prompt, array $options = [])
    {
        // Validar configuración
        $validation = $this->validateConfig();
        if ($validation !== true) {
            throw new \Exception("Configuración inválida: $validation");
        }
        
        // Configurar parámetros
        $apiKey = $this->getConfig('api_key');
        $model = $options['model'] ?? $this->getConfig('model', 'claude-3-opus-20240229');
        $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
        $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);
        $endpoint = $options['endpoint'] ?? $this->getConfig('endpoint', 'https://api.anthropic.com/v1/messages');
        
        // Construir payload
        $systemMessage = $options['system_message'] ?? 'Eres un asistente experto en redacción de contenido web profesional. Tu objetivo es generar textos de alta calidad, bien estructurados y atractivos.';
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'system' => $systemMessage,
            'temperature' => (float)$temperature,
            'max_tokens' => (int)$maxTokens
        ];
        
        // Configurar la solicitud
        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
            'Anthropic-Version: 2023-06-01'
        ];
        
        // Realizar la llamada a la API
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new \Exception('Error de cURL: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            throw new \Exception(($error['error']['message'] ?? 'Error en la API de Claude') . " (HTTP $httpCode)");
        }
        
        $responseData = json_decode($response, true);
        
        // Extraer el contenido
        $content = $responseData['content'][0]['text'] ?? '';
        $tokensUsed = $responseData['usage']['input_tokens'] + $responseData['usage']['output_tokens'] ?? 0;
        
        return [
            'content' => $content,
            'tokens' => $tokensUsed,
            'model' => $model,
            'provider' => 'claude'
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function getInfo()
    {
        return [
            'name' => 'Claude',
            'description' => 'API de Anthropic para modelos Claude',
            'website' => 'https://anthropic.com/',
            'models' => $this->getAvailableModels()
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function getAvailableModels()
    {
        return [
            'claude-3-opus-20240229' => 'Claude 3 Opus (Más capaz)',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (Equilibrado)',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku (Más rápido)'
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function validateConfig()
    {
        if (empty($this->getConfig('api_key'))) {
            return 'La API Key de Claude es obligatoria';
        }
        
        return true;
    }
}