<?php
namespace Screenart\Musedock\Services\AI\Providers;

/**
 * Implementación del proveedor OpenAI
 */
class OpenAIProvider extends AbstractProvider
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
        $model = $options['model'] ?? $this->getConfig('model', 'gpt-4');
        $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
        $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);
        $endpoint = $options['endpoint'] ?? $this->getConfig('endpoint', 'https://api.openai.com/v1/chat/completions');
        
        // Construir payload
        $systemMessage = $options['system_message'] ?? 'Eres un asistente experto en redacción de contenido web profesional. Tu objetivo es generar textos de alta calidad, bien estructurados y atractivos.';
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemMessage],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => (float)$temperature,
            'max_tokens' => (int)$maxTokens
        ];
        
        // Añadir parámetros opcionales
        if (!empty($options['stop'])) {
            $data['stop'] = $options['stop'];
        }
        
        if (!empty($options['presence_penalty'])) {
            $data['presence_penalty'] = (float)$options['presence_penalty'];
        }
        
        if (!empty($options['frequency_penalty'])) {
            $data['frequency_penalty'] = (float)$options['frequency_penalty'];
        }
        
        // Configurar la solicitud
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
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
            throw new \Exception(($error['error']['message'] ?? 'Error en la API de OpenAI') . " (HTTP $httpCode)");
        }
        
        $responseData = json_decode($response, true);
        
        // Extraer el contenido y los tokens
        $content = $responseData['choices'][0]['message']['content'] ?? '';
        $tokensUsed = $responseData['usage']['total_tokens'] ?? 0;
        
        return [
            'content' => $content,
            'tokens' => $tokensUsed,
            'model' => $model,
            'provider' => 'openai'
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function getInfo()
    {
        return [
            'name' => 'OpenAI',
            'description' => 'API de OpenAI para modelos GPT',
            'website' => 'https://openai.com/',
            'models' => $this->getAvailableModels()
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function getAvailableModels()
    {
        return [
            'gpt-4' => 'GPT-4 (Más capaz)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Más rápido)'
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function validateConfig()
    {
        if (empty($this->getConfig('api_key'))) {
            return 'La API Key de OpenAI es obligatoria';
        }
        
        return true;
    }
}