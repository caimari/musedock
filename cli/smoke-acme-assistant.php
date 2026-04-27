<?php
/**
 * Smoke tests para ACME Assistant.
 *
 * Uso:
 *   php cli/smoke-acme-assistant.php
 */

define('APP_ROOT', realpath(__DIR__ . '/..'));
require_once APP_ROOT . '/vendor/autoload.php';

use Screenart\Musedock\Services\AcmeAssistantService;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

$ok = 0;
$fail = 0;

function assertTrue(bool $condition, string $label, int &$ok, int &$fail): void
{
    if ($condition) {
        echo "[OK] {$label}\n";
        $ok++;
    } else {
        echo "[FAIL] {$label}\n";
        $fail++;
    }
}

$sampleRules = <<<IPT
-P INPUT DROP
-A INPUT -i lo -j ACCEPT
-A INPUT -p tcp --dport 80 -j ACCEPT
-A INPUT -s 10.0.0.0/8 -p tcp --dport 443 -j ACCEPT
IPT;

$baseService = new AcmeAssistantService();
$parsed = $baseService->parseIptablesInputRules($sampleRules);
assertTrue(($parsed['input_policy'] ?? '') === 'DROP', 'Parsea policy INPUT', $ok, $fail);
assertTrue(($parsed['ports']['80']['public_open'] ?? false) === true, 'Detecta 80 público abierto', $ok, $fail);
assertTrue(($parsed['ports']['443']['public_open'] ?? false) === false, '443 no público en regla restringida', $ok, $fail);
assertTrue(($parsed['ports']['443']['restricted_open'] ?? false) === true, 'Detecta 443 restringido', $ok, $fail);

$credCfOk = $baseService->providerCredentialStatus('cloudflare', ['CLOUDFLARE_API_TOKEN' => 'x']);
assertTrue(($credCfOk['ok'] ?? false) === true, 'Cloudflare OK con token', $ok, $fail);

$credCfFail = $baseService->providerCredentialStatus('cloudflare', []);
assertTrue(($credCfFail['ok'] ?? false) === false, 'Cloudflare falla sin token', $ok, $fail);

$credRoute53Fail = $baseService->providerCredentialStatus('route53', ['AWS_ACCESS_KEY_ID' => 'a']);
assertTrue(($credRoute53Fail['ok'] ?? false) === false, 'Route53 falla si falta secret', $ok, $fail);

$credRoute53Ok = $baseService->providerCredentialStatus('route53', [
    'AWS_ACCESS_KEY_ID' => 'a',
    'AWS_SECRET_ACCESS_KEY' => 'b',
]);
assertTrue(($credRoute53Ok['ok'] ?? false) === true, 'Route53 OK con access+secret', $ok, $fail);

$openedPorts = [];
$mockRunner = function (string $cmd) use (&$openedPorts): array {
    if (str_contains($cmd, 'command -v')) {
        return ['code' => 0, 'stdout' => '', 'stderr' => ''];
    }

    if (str_contains($cmd, 'iptables -S INPUT')) {
        return [
            'code' => 0,
            'stdout' => "-P INPUT DROP\n-A INPUT -i lo -j ACCEPT\n-A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT",
            'stderr' => '',
        ];
    }

    if (str_contains($cmd, 'caddy list-modules')) {
        return [
            'code' => 0,
            'stdout' => "dns.providers.cloudflare\ndns.providers.route53\nhttp.handlers.reverse_proxy",
            'stderr' => '',
        ];
    }

    if (str_contains($cmd, 'journalctl -u caddy')) {
        return [
            'code' => 0,
            'stdout' => '{"level":"error","msg":"challenge failed","problem":{"detail":"Timeout during connect (likely firewall problem)"},"ts":1777313500}' . "\n"
                . '{"level":"info","msg":"trying to solve challenge","ts":1777313502}',
            'stderr' => '',
        ];
    }

    if (preg_match('/iptables -C INPUT -p tcp --dport (\d+)/', $cmd, $m)) {
        return [
            'code' => isset($openedPorts[$m[1]]) ? 0 : 1,
            'stdout' => '',
            'stderr' => '',
        ];
    }

    if (preg_match('/iptables -I INPUT 1 -p tcp --dport (\d+)/', $cmd, $m)) {
        $openedPorts[$m[1]] = true;
        return ['code' => 0, 'stdout' => '', 'stderr' => ''];
    }

    if (preg_match('/iptables -D INPUT -p tcp --dport (\d+)/', $cmd, $m)) {
        unset($openedPorts[$m[1]]);
        return ['code' => 0, 'stdout' => '', 'stderr' => ''];
    }

    if (str_contains($cmd, 'nohup ')) {
        return ['code' => 0, 'stdout' => '', 'stderr' => ''];
    }

    return ['code' => 0, 'stdout' => '', 'stderr' => ''];
};

$service = new AcmeAssistantService($mockRunner);

$providerBlocked = $service->providerPostInstallCheck('cloudflare', []);
assertTrue(($providerBlocked['ready'] ?? true) === false, 'Provider-check bloquea módulo cargado sin token', $ok, $fail);

$providerReady = $service->providerPostInstallCheck('cloudflare', ['CLOUDFLARE_API_TOKEN' => 'token']);
assertTrue(($providerReady['ready'] ?? false) === true, 'Provider-check OK con módulo + token', $ok, $fail);

$dryRunAutoDns = $service->dryRun([
    'domain' => 'midominio.com',
    'challenge' => 'auto',
    'provider' => 'cloudflare',
    'env' => ['CLOUDFLARE_API_TOKEN' => 'token'],
]);
assertTrue(($dryRunAutoDns['challenge'] ?? '') === 'dns-01', 'Auto selecciona DNS-01 si proveedor listo', $ok, $fail);
assertTrue(($dryRunAutoDns['can_proceed'] ?? false) === true, 'DNS-01 puede proceder con provider listo', $ok, $fail);

$dryRunAutoHttp = $service->dryRun([
    'domain' => 'midominio.com',
    'challenge' => 'auto',
    'provider' => 'cloudflare',
    'env' => [],
]);
assertTrue(($dryRunAutoHttp['challenge'] ?? '') === 'http-01', 'Auto cae a HTTP-01 sin credenciales DNS', $ok, $fail);
assertTrue(($dryRunAutoHttp['requires_temporary_ports'] ?? false) === true, 'HTTP-01 detecta apertura temporal requerida', $ok, $fail);
assertTrue(($dryRunAutoHttp['can_proceed'] ?? true) === false, 'HTTP-01 no procede con 80 cerrado', $ok, $fail);
assertTrue(!empty($dryRunAutoHttp['estimate']['summary'] ?? ''), 'Dry-run incluye estimación', $ok, $fail);

$validatedNoPass = $service->validateTemporaryPortRequest(['ticket_id' => 'abc'], true);
assertTrue(($validatedNoPass['ok'] ?? true) === false, 'Apertura temporal exige contraseña', $ok, $fail);

$validated = $service->validateTemporaryPortRequest([
    'ticket_id' => 'abc$%123',
    'ttl_seconds' => 99999,
    'password' => 'secret',
], true);
assertTrue(($validated['ok'] ?? false) === true, 'Validación de payload temporal OK', $ok, $fail);
assertTrue(($validated['ticket_id'] ?? '') === 'abc123', 'Sanitiza ticket_id', $ok, $fail);
assertTrue(($validated['ttl_seconds'] ?? 0) === 3600, 'Normaliza TTL máximo a 3600s', $ok, $fail);

$opened = $service->openTemporaryPorts(1800, 'smoke-run');
assertTrue(($opened['success'] ?? false) === true, 'Abre puertos temporales en mock', $ok, $fail);
assertTrue(count($opened['opened_ports'] ?? []) === 2, 'Abre 80 y 443 en mock', $ok, $fail);

$closed = $service->removeTemporaryPorts('smoke-run');
assertTrue(($closed['success'] ?? false) === true, 'Cierra puertos temporales en mock', $ok, $fail);

$snapshot = $service->getAcmeStatusSnapshot('midominio.com');
assertTrue(isset($snapshot['runtime']['status']), 'Snapshot ACME runtime disponible', $ok, $fail);
assertTrue(($snapshot['runtime']['status'] ?? '') === 'error', 'Detecta error ACME runtime desde logs', $ok, $fail);

echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
