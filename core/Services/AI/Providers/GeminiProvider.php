<?php
namespace Screenart\Musedock\Services\AI\Providers;

/**
 * Implementación del proveedor Gemini (Google)
 */
class GeminiProvider extends AbstractProvider
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
        $model = $options['model'] ?? $this->getConfig('model', 'gemini-pro');
        $temperature = $options['temperature'] ?? $this->getConfig('temperature', 0.7);
        $maxTokens = $options['max_tokens'] ?? $this->getConfig('max_tokens', 1000);
        
        // Construcción del endpoint con API key
        $baseEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models';
        $endpoint = "{$baseEndpoint}/{$model}:generateContent?key={$apiKey}";
        
        // Construir payload
        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$temperature,
                'maxOutputTokens' => (int)$maxTokens,
                'topP' => 0.95,
                'topK' => 40
            ]
        ];
        
        // Configurar la solicitud
        $headers = ['Content-Type: application/json'];
        
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
            throw new \Exception(($error['error']['message'] ?? 'Error en la API de Gemini') . " (HTTP $httpCode)");
        }
        
        $responseData = json_decode($response, true);
        
        // Extraer el contenido
        $content = '';
        if (!empty($responseData['candidates'][0]['content']['parts'])) {
            foreach ($responseData['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'];
                }
            }
        }
        
        // Gemini no reporta tokens usados, así que estimamos
        $tokensUsed = (int)(strlen($prompt) / 4) + (int)(strlen($content) / 4);
        
        return [
            'content' => $content,
            'tokens' => $tokensUsed,
            'model' => $model,
            'provider' => 'gemini'
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function getInfo()
    {
        return [
            'name' => 'Gemini',
            'description' => 'API de Google para modelos Gemini',
            'website' => 'https://ai.google.dev/',
            'models' => $this->getAvailableModels()
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function getAvailableModels()
    {
        return [
            'gemini-pro' => 'Gemini Pro',
            'gemini-ultra' => 'Gemini Ultra'
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function validateConfig()
    {
        if (empty($this->getConfig('api_key'))) {
            return 'La API Key de Google es obligatoria';
        }
        
        return true;
    }
}