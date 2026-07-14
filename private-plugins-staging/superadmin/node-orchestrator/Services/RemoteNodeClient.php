<?php

namespace NodeOrchestrator\Services;

class RemoteNodeClient
{
    public function createTenant(array $node, array $tenantData, array $adminData, ?string $tokenOverride = null): array
    {
        $apiUrl = rtrim((string) ($node['api_url'] ?? ''), '/');
        $token = $tokenOverride !== null ? trim($tokenOverride) : (string) ($node['api_key'] ?? '');

        if ($apiUrl === '' || $token === '') {
            return [
                'success' => false,
                'error' => 'Nodo remoto sin api_url/api_key configurados',
            ];
        }

        $payload = [
            'tenant' => $tenantData,
            'admin' => $adminData,
        ];

        return $this->request('POST', $apiUrl . '/api/v1/node/tenants', $token, $payload);
    }

    public function health(array $node, ?string $tokenOverride = null): array
    {
        $apiUrl = rtrim((string) ($node['api_url'] ?? ''), '/');
        $token = $tokenOverride !== null ? trim($tokenOverride) : (string) ($node['api_key'] ?? '');
        if ($apiUrl === '' || $token === '') {
            return [
                'success' => false,
                'error' => 'Nodo remoto sin api_url/api_key configurados',
            ];
        }

        return $this->request('GET', $apiUrl . '/api/v1/node/health', $token);
    }

    private function request(string $method, string $url, string $token, ?array $payload = null): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'success' => false,
                'error' => "cURL error {$errno}: {$error}",
            ];
        }

        $response = json_decode((string) $raw, true);
        if (!is_array($response)) {
            $response = ['success' => false, 'error' => 'Respuesta JSON inválida del nodo remoto'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $response['success'] = false;
            $response['error'] = $response['error'] ?? ('HTTP ' . $httpCode . ' al llamar nodo remoto');
        }

        return $response;
    }
}
