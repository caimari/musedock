<?php
/**
 * Verificar permisos del token de Cloudflare
 */

$token = getenv('CLOUDFLARE_CUSTOM_DOMAINS_API_TOKEN') ?: '5zNPRZ_WBdGdCwNUufCOKFK1NPETyM_YmvfbQ527';

echo "=== Verificando permisos del token de Cloudflare ===\n\n";

// Verificar el token
$ch = curl_init('https://api.cloudflare.com/client/v4/user/tokens/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && $data['success']) {
    echo "✅ Token válido\n\n";

    echo "📋 Información del token:\n";
    echo "ID: " . ($data['result']['id'] ?? 'N/A') . "\n";
    echo "Status: " . ($data['result']['status'] ?? 'N/A') . "\n";
    echo "Expira: " . ($data['result']['expires_on'] ?? 'Nunca') . "\n\n";

    if (!empty($data['result']['policies'])) {
        echo "🔐 Permisos configurados:\n\n";
        foreach ($data['result']['policies'] as $idx => $policy) {
            echo "Política #" . ($idx + 1) . ":\n";

            if (!empty($policy['permission_groups'])) {
                foreach ($policy['permission_groups'] as $perm) {
                    echo "  - " . ($perm['name'] ?? $perm['id']) . "\n";
                }
            }

            if (!empty($policy['resources'])) {
                echo "  Recursos:\n";
                foreach ($policy['resources'] as $resource => $value) {
                    if (is_array($value)) {
                        echo "    {$resource}: " . json_encode($value) . "\n";
                    } else {
                        echo "    {$resource}: {$value}\n";
                    }
                }
            }
            echo "\n";
        }
    }
} else {
    echo "❌ Error verificando token\n";
    echo "HTTP Code: {$httpCode}\n";
    echo "Response: {$response}\n";
}

echo "\n=== Permisos recomendados para Email Routing ===\n";
echo "Zone - Email Routing Addresses - Read\n";
echo "Zone - Email Routing Addresses - Edit\n";
echo "Zone - Email Routing Rules - Read\n";
echo "Zone - Email Routing Rules - Edit\n";
echo "Zone - DNS - Read\n";
echo "Zone - Zone - Read\n";
echo "Zone - Zone Settings - Read\n";
echo "Account - Account Settings - Read\n";
