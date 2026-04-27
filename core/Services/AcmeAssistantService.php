<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Env;

class AcmeAssistantService
{
    private const DEFAULT_TEMP_TTL_SECONDS = 1800;
    private const MAX_HISTORY_EVENTS = 80;

    /** @var callable|null */
    private $commandRunner;

    public function __construct($commandRunner = null)
    {
        $this->commandRunner = is_callable($commandRunner) ? $commandRunner : null;
    }

    public function detectFirewallState(): array
    {
        $result = [
            'firewall' => 'unknown',
            'input_policy' => 'unknown',
            'ports' => [
                '80' => ['public_open' => false, 'restricted_open' => false, 'matches' => []],
                '443' => ['public_open' => false, 'restricted_open' => false, 'matches' => []],
            ],
            'raw_available' => false,
            'notes' => [],
        ];

        if (!$this->commandExists('iptables')) {
            $result['firewall'] = 'unknown';
            $result['notes'][] = 'iptables no disponible.';
            return $result;
        }

        $raw = $this->runCommand('iptables -S INPUT');
        if (($raw['code'] ?? 1) !== 0) {
            $result['firewall'] = 'iptables';
            $result['notes'][] = 'No se pudo leer iptables -S INPUT.';
            return $result;
        }

        $parsed = $this->parseIptablesInputRules((string)($raw['stdout'] ?? ''));
        $parsed['raw_available'] = true;
        return $parsed;
    }

    public function parseIptablesInputRules(string $rules): array
    {
        $result = [
            'firewall' => 'iptables',
            'input_policy' => 'unknown',
            'ports' => [
                '80' => ['public_open' => false, 'restricted_open' => false, 'matches' => []],
                '443' => ['public_open' => false, 'restricted_open' => false, 'matches' => []],
            ],
            'notes' => [],
        ];

        $lines = preg_split('/\R/', $rules) ?: [];
        foreach ($lines as $lineRaw) {
            $line = trim((string)$lineRaw);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^-P\s+INPUT\s+(\S+)/', $line, $matches)) {
                $result['input_policy'] = strtoupper($matches[1]);
                continue;
            }

            if (!str_starts_with($line, '-A INPUT') || !str_contains($line, '-j ACCEPT')) {
                continue;
            }

            $hasSource = preg_match('/\s-s\s+(\S+)/', $line, $sourceMatch) === 1;
            $source = $hasSource ? $sourceMatch[1] : null;
            $hasInputIface = preg_match('/\s-i\s+(\S+)/', $line, $ifaceMatch) === 1;
            $iface = $hasInputIface ? $ifaceMatch[1] : null;
            if ($iface === 'lo') {
                continue;
            }

            $isPublicSource = (!$hasSource || $source === '0.0.0.0/0');
            $hasDport80 = str_contains($line, '--dport 80');
            $hasDport443 = str_contains($line, '--dport 443');
            $hasAnyDport = str_contains($line, '--dport ');
            $isConntrackEstablishedRule = str_contains($line, '--ctstate RELATED,ESTABLISHED')
                || str_contains($line, '--ctstate ESTABLISHED,RELATED');

            // Regla que acepta todo el INPUT públicamente.
            if (!$hasAnyDport && $isPublicSource && !$isConntrackEstablishedRule) {
                $result['ports']['80']['public_open'] = true;
                $result['ports']['443']['public_open'] = true;
                $result['ports']['80']['matches'][] = $line;
                $result['ports']['443']['matches'][] = $line;
                continue;
            }

            foreach (['80' => $hasDport80, '443' => $hasDport443] as $port => $matched) {
                if (!$matched) {
                    continue;
                }
                if ($isPublicSource) {
                    $result['ports'][$port]['public_open'] = true;
                } else {
                    $result['ports'][$port]['restricted_open'] = true;
                }
                $result['ports'][$port]['matches'][] = $line;
            }
        }

        return $result;
    }

    public function listDnsProviders(): array
    {
        $response = [
            'loaded' => [],
            'available' => [],
            'command_ok' => false,
            'error' => null,
        ];

        if (!$this->commandExists('caddy')) {
            $response['error'] = 'No se encontró binario caddy.';
            $response['available'] = array_keys($this->providerCredentialMap());
            return $response;
        }

        $run = $this->runCommand('caddy list-modules');
        if (($run['code'] ?? 1) !== 0) {
            $response['error'] = trim((string)($run['stderr'] ?? '')) ?: 'No se pudo listar módulos de Caddy.';
            $response['available'] = array_keys($this->providerCredentialMap());
            return $response;
        }

        $providers = $this->parseDnsProvidersFromModules((string)($run['stdout'] ?? ''));
        $response['loaded'] = $providers;
        $response['available'] = array_keys($this->providerCredentialMap());
        $response['command_ok'] = true;
        return $response;
    }

    public function providerCredentialStatus(string $provider, ?array $env = null): array
    {
        $provider = strtolower(trim($provider));
        $map = $this->providerCredentialMap();

        if (!isset($map[$provider])) {
            return [
                'provider' => $provider,
                'known' => false,
                'ok' => false,
                'missing' => [],
                'matched' => [],
                'help' => 'Proveedor no soportado por la matriz de validación.',
                'security_tips' => [],
                'next_steps' => ['Selecciona un proveedor soportado por el asistente.'],
            ];
        }

        $envData = $env ?? $this->collectEnv();
        $matched = [];
        $missing = [];

        foreach ($map[$provider] as $groupName => $groupVars) {
            $found = null;
            foreach ($groupVars as $varName) {
                $value = trim((string)($envData[$varName] ?? ''));
                if ($value !== '') {
                    $found = $varName;
                    break;
                }
            }

            if ($found !== null) {
                $matched[$groupName] = $found;
            } else {
                $missing[$groupName] = $groupVars;
            }
        }

        return [
            'provider' => $provider,
            'known' => true,
            'ok' => count($missing) === 0,
            'missing' => $missing,
            'matched' => $matched,
            'help' => count($missing) === 0
                ? 'Credenciales detectadas.'
                : 'Faltan variables de entorno requeridas para este proveedor.',
            'security_tips' => $this->providerSecurityTips($provider),
            'next_steps' => count($missing) === 0
                ? ['Proveedor listo para DNS-01.']
                : [
                    'Configura las variables faltantes en .env o en entorno del servicio Caddy.',
                    'Reinicia Caddy para recargar credenciales.',
                ],
        ];
    }

    public function providerPostInstallCheck(string $provider, ?array $env = null, ?array $loadedProviders = null): array
    {
        $provider = strtolower(trim($provider));
        if ($provider === '') {
            $provider = 'cloudflare';
        }

        $providers = $this->listDnsProviders();
        $loaded = is_array($loadedProviders) ? $loadedProviders : ($providers['loaded'] ?? []);
        $moduleLoaded = in_array($provider, $loaded, true);
        $credentials = $this->providerCredentialStatus($provider, $env);

        $nextSteps = [];
        $status = 'ready';
        $summary = 'Proveedor listo para usar DNS-01.';

        if (!$moduleLoaded) {
            $status = 'blocked';
            $summary = "Falta módulo dns.providers.{$provider} en Caddy.";
            $nextSteps[] = "Instala/compila Caddy con dns.providers.{$provider}.";
        }

        if (!(bool)($credentials['ok'] ?? false)) {
            $status = 'blocked';
            $missing = $this->formatMissingCredentialGroups($credentials['missing'] ?? []);
            if ($missing !== '') {
                $nextSteps[] = "Define credenciales: {$missing}.";
            } else {
                $nextSteps[] = 'Define credenciales requeridas del proveedor.';
            }
            $nextSteps[] = 'Reinicia Caddy y valida de nuevo.';
            if ($moduleLoaded) {
                $summary = "Módulo cargado pero credenciales incompletas para {$provider}.";
            } elseif ($summary === 'Proveedor listo para usar DNS-01.') {
                $summary = "Proveedor {$provider} no está listo para DNS-01.";
            }
        }

        if ($status === 'ready') {
            $nextSteps[] = 'Puedes emitir/renovar por DNS-01 sin abrir 80/443.';
        }

        return [
            'provider' => $provider,
            'status' => $status,
            'ready' => $status === 'ready',
            'module_loaded' => $moduleLoaded,
            'credentials' => $credentials,
            'summary' => $summary,
            'next_steps' => array_values(array_unique($nextSteps)),
            'security_tips' => $this->providerSecurityTips($provider),
        ];
    }

    public function validateTemporaryPortRequest(array $input, bool $requirePassword = true): array
    {
        $ticketId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($input['ticket_id'] ?? 'manual'));
        if ($ticketId === '') {
            $ticketId = 'manual';
        }

        $ttl = (int)($input['ttl_seconds'] ?? self::DEFAULT_TEMP_TTL_SECONDS);
        $ttl = max(60, min(3600, $ttl));

        $password = (string)($input['password'] ?? '');
        if ($requirePassword && trim($password) === '') {
            return [
                'ok' => false,
                'message' => 'La contraseña de administrador es obligatoria.',
            ];
        }

        return [
            'ok' => true,
            'ticket_id' => $ticketId,
            'ttl_seconds' => $ttl,
            'password' => $password,
        ];
    }

    public function dryRun(array $input): array
    {
        $domain = $this->normalizeDomain((string)($input['domain'] ?? ''));
        $requestedChallenge = strtolower(trim((string)($input['challenge'] ?? 'auto')));
        $provider = strtolower(trim((string)($input['provider'] ?? '')));
        $provider = $provider === '' ? 'cloudflare' : $provider;
        $envOverride = is_array($input['env'] ?? null) ? $input['env'] : null;

        $firewall = $this->detectFirewallState();
        $providers = $this->listDnsProviders();
        $providerCheck = $this->providerPostInstallCheck($provider, $envOverride, $providers['loaded'] ?? []);
        $providerCreds = $providerCheck['credentials'] ?? $this->providerCredentialStatus($provider, $envOverride);
        $loadedProvider = (bool)($providerCheck['module_loaded'] ?? false);

        $selected = $requestedChallenge;
        if (!in_array($selected, ['auto', 'dns-01', 'http-01', 'tls-alpn-01'], true)) {
            $selected = 'auto';
        }

        $reasons = [];
        $actions = [];
        $canProceed = true;
        $requiresTemporaryPorts = false;

        if ($domain === '') {
            $canProceed = false;
            $reasons[] = 'Debes indicar un dominio válido.';
        }

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            $canProceed = false;
            $reasons[] = 'Let\'s Encrypt no emite certificados públicos para IP; usa un FQDN.';
        }

        if ($selected === 'auto') {
            $selected = ($providerCheck['ready'] ?? false) ? 'dns-01' : 'http-01';
        }

        if ($selected === 'dns-01') {
            if (!$loadedProvider) {
                $canProceed = false;
                $reasons[] = "El módulo dns.providers.{$provider} no está cargado en Caddy.";
                $actions[] = "Compila/instala Caddy con dns.providers.{$provider}.";
            }
            if (!(bool)($providerCreds['ok'] ?? false)) {
                $canProceed = false;
                $reasons[] = 'Faltan credenciales del proveedor DNS.';
                $missing = $this->formatMissingCredentialGroups($providerCreds['missing'] ?? []);
                $actions[] = $missing !== ''
                    ? "Configura credenciales: {$missing}."
                    : 'Configura las variables requeridas del proveedor.';
                $actions[] = 'Reinicia Caddy tras guardar credenciales.';
            }
        }

        if ($selected === 'http-01' && !(bool)($firewall['ports']['80']['public_open'] ?? false)) {
            $canProceed = false;
            $requiresTemporaryPorts = true;
            $reasons[] = 'HTTP-01 requiere puerto 80 público.';
            $actions[] = 'Abrir temporalmente 80 para emitir certificado.';
        }

        if ($selected === 'tls-alpn-01' && !(bool)($firewall['ports']['443']['public_open'] ?? false)) {
            $canProceed = false;
            $requiresTemporaryPorts = true;
            $reasons[] = 'TLS-ALPN-01 requiere puerto 443 público.';
            $actions[] = 'Abrir temporalmente 443 para emitir certificado.';
        }

        // Caddy intenta HTTP-01 y TLS-ALPN-01 automáticamente para ACME.
        if ($selected === 'http-01' && !(bool)($firewall['ports']['443']['public_open'] ?? false)) {
            $actions[] = 'Abrir también 443 mejora la probabilidad de emisión/renovación.';
        }

        $estimate = $this->buildDryRunEstimate(
            $selected,
            $canProceed,
            $requiresTemporaryPorts,
            $firewall,
            $providerCheck
        );

        return [
            'success' => true,
            'domain' => $domain,
            'challenge' => $selected,
            'requested_challenge' => $requestedChallenge,
            'provider' => $provider,
            'firewall' => $firewall,
            'providers' => $providers,
            'provider_credentials' => $providerCreds,
            'provider_loaded' => $loadedProvider,
            'provider_check' => $providerCheck,
            'can_proceed' => $canProceed,
            'requires_temporary_ports' => $requiresTemporaryPorts,
            'reasons' => $reasons,
            'actions' => array_values(array_unique($actions)),
            'eta_seconds' => 90,
            'estimate' => $estimate,
        ];
    }

    public function openTemporaryPorts(int $ttlSeconds, string $ticketId): array
    {
        $ttlSeconds = max(60, min(3600, $ttlSeconds));
        $ticketId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $ticketId);
        if ($ticketId === '') {
            $ticketId = 'default';
        }

        if (!$this->commandExists('iptables')) {
            return ['success' => false, 'message' => 'iptables no está disponible en este servidor.'];
        }

        $comment = $this->buildRuleComment($ticketId);
        $commentArg = escapeshellarg($comment);
        $opened = [];
        $errors = [];

        foreach ([80, 443] as $port) {
            $check = $this->runCommand("iptables -C INPUT -p tcp --dport {$port} -m comment --comment {$commentArg} -j ACCEPT");
            if (($check['code'] ?? 1) === 0) {
                $opened[] = (string)$port;
                continue;
            }

            $insert = $this->runCommand("iptables -I INPUT 1 -p tcp --dport {$port} -m comment --comment {$commentArg} -j ACCEPT");
            if (($insert['code'] ?? 1) === 0) {
                $opened[] = (string)$port;
            } else {
                $errors[] = "No se pudo abrir {$port}: " . trim((string)($insert['stderr'] ?? ''));
            }
        }

        $expiresAt = time() + $ttlSeconds;
        $this->saveTemporaryRuleState($ticketId, [
            'ticket_id' => $ticketId,
            'comment' => $comment,
            'opened_ports' => $opened,
            'opened_at' => gmdate('c'),
            'expires_at' => gmdate('c', $expiresAt),
            'ttl_seconds' => $ttlSeconds,
        ]);

        // Lanzar cierre asíncrono.
        $php = escapeshellarg(PHP_BINARY ?: '/usr/bin/php');
        $script = escapeshellarg(APP_ROOT . '/cli/acme-firewall-close.php');
        $ticket = escapeshellarg($ticketId);
        $delay = (int)$ttlSeconds;
        $spawn = "nohup {$php} {$script} --ticket={$ticket} --delay={$delay} > /dev/null 2>&1 &";
        if (function_exists('exec')) {
            @exec($spawn);
        } else {
            $this->runCommand($spawn);
        }

        return [
            'success' => count($opened) > 0 && count($errors) === 0,
            'opened_ports' => $opened,
            'expires_at' => gmdate('c', $expiresAt),
            'errors' => $errors,
            'message' => count($errors) === 0
                ? 'Puertos abiertos temporalmente para ACME.'
                : 'Se abrieron puertos con errores parciales.',
        ];
    }

    public function removeTemporaryPorts(string $ticketId): array
    {
        $ticketId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $ticketId);
        $comment = $this->buildRuleComment($ticketId);
        $commentArg = escapeshellarg($comment);
        $removed = [];

        if (!$this->commandExists('iptables')) {
            return ['success' => false, 'message' => 'iptables no está disponible.', 'removed_ports' => []];
        }

        foreach ([80, 443] as $port) {
            $wasRemoved = false;
            while (true) {
                $check = $this->runCommand("iptables -C INPUT -p tcp --dport {$port} -m comment --comment {$commentArg} -j ACCEPT");
                if (($check['code'] ?? 1) !== 0) {
                    break;
                }
                $delete = $this->runCommand("iptables -D INPUT -p tcp --dport {$port} -m comment --comment {$commentArg} -j ACCEPT");
                if (($delete['code'] ?? 1) !== 0) {
                    break;
                }
                $wasRemoved = true;
            }

            if ($wasRemoved) {
                $removed[] = (string)$port;
            }
        }

        $this->deleteTemporaryRuleState($ticketId);

        return [
            'success' => true,
            'removed_ports' => $removed,
            'message' => empty($removed)
                ? 'No había reglas temporales pendientes.'
                : 'Reglas temporales eliminadas.',
        ];
    }

    public function loadTemporaryRuleState(string $ticketId): ?array
    {
        $file = $this->stateFilePath($ticketId);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }

    public function getAcmeStatusSnapshot(string $domain = ''): array
    {
        $runtime = $this->inspectRuntimeAcme($domain);
        if (($runtime['observed'] ?? false) === true) {
            $this->persistRuntimeSnapshot($runtime);
        }

        $store = $this->loadAcmeStatusStore();
        return [
            'runtime' => $runtime,
            'last' => $store['last'] ?? null,
            'history' => $store['history'] ?? [],
        ];
    }

    public function recordAssistantEvent(string $status, string $title, string $message, array $context = []): void
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['success', 'warning', 'error', 'info'], true)) {
            $status = 'info';
        }

        $event = [
            'id' => 'assistant-' . sha1($status . '|' . $title . '|' . $message . '|' . microtime(true)),
            'ts' => gmdate('c'),
            'source' => 'assistant',
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'next_step' => $context['next_step'] ?? null,
            'context' => $context,
        ];

        $store = $this->loadAcmeStatusStore();
        $store['last'] = $event;
        $store['history'] = $this->appendHistoryEvent($store['history'] ?? [], $event);
        $this->saveAcmeStatusStore($store);
    }

    private function buildDryRunEstimate(
        string $selectedChallenge,
        bool $canProceed,
        bool $requiresTemporaryPorts,
        array $firewall,
        array $providerCheck
    ): array {
        $certainty = $canProceed ? 'high' : 'low';
        $duration = '60-180s';
        if ($selectedChallenge === 'dns-01') {
            $duration = '60-240s';
        }
        if ($requiresTemporaryPorts) {
            $duration = '90-300s';
        }

        $summary = $canProceed
            ? "Listo para intentar emisión con {$selectedChallenge}."
            : "Hay bloqueos previos antes de emitir con {$selectedChallenge}.";

        $blocking = [];
        if (!$canProceed) {
            if ($selectedChallenge === 'dns-01' && !($providerCheck['ready'] ?? false)) {
                $blocking[] = 'Proveedor DNS incompleto (módulo o credenciales).';
            }
            if ($selectedChallenge === 'http-01' && !(bool)($firewall['ports']['80']['public_open'] ?? false)) {
                $blocking[] = 'Puerto 80 cerrado públicamente.';
            }
            if ($selectedChallenge === 'tls-alpn-01' && !(bool)($firewall['ports']['443']['public_open'] ?? false)) {
                $blocking[] = 'Puerto 443 cerrado públicamente.';
            }
        }

        return [
            'challenge' => $selectedChallenge,
            'can_proceed' => $canProceed,
            'certainty' => $certainty,
            'expected_duration' => $duration,
            'summary' => $summary,
            'blocking_issues' => $blocking,
        ];
    }

    private function inspectRuntimeAcme(string $domain = ''): array
    {
        if (!$this->commandExists('journalctl')) {
            return [
                'observed' => false,
                'status' => 'unknown',
                'message' => 'journalctl no disponible para diagnóstico ACME runtime.',
                'detail' => null,
                'next_step' => 'Revisa logs de Caddy manualmente.',
                'ts' => gmdate('c'),
                'fingerprint' => null,
            ];
        }

        $run = $this->runCommand('journalctl -u caddy --since "120 minutes ago" --no-pager');
        if (($run['code'] ?? 1) !== 0) {
            return [
                'observed' => false,
                'status' => 'unknown',
                'message' => 'No se pudieron leer logs de Caddy.',
                'detail' => trim((string)($run['stderr'] ?? '')),
                'next_step' => 'Ejecuta journalctl -u caddy con permisos adecuados.',
                'ts' => gmdate('c'),
                'fingerprint' => null,
            ];
        }

        $lines = preg_split('/\R/', (string)($run['stdout'] ?? '')) ?: [];
        $events = $this->extractAcmeEvents($lines, $domain);
        if (empty($events) && $domain !== '') {
            // Fallback: mostrar estado ACME global aunque el dominio no aparezca explícito en cada línea.
            $events = $this->extractAcmeEvents($lines, '');
        }

        if (empty($events)) {
            return [
                'observed' => true,
                'status' => 'unknown',
                'message' => 'Sin eventos ACME recientes en Caddy.',
                'detail' => null,
                'next_step' => 'Lanza una emisión/reconfiguración y vuelve a consultar.',
                'ts' => gmdate('c'),
                'fingerprint' => sha1('unknown|no-events|' . $domain),
            ];
        }

        $selected = end($events);
        $status = 'info';

        foreach (array_reverse($events) as $event) {
            $text = strtolower(($event['message'] ?? '') . ' ' . ($event['detail'] ?? ''));
            $level = strtolower((string)($event['level'] ?? ''));
            if ($level === 'error' || str_contains($text, 'challenge failed') || str_contains($text, 'job failed')) {
                $selected = $event;
                $status = 'error';
                break;
            }
        }

        if ($status === 'info') {
            foreach (array_reverse($events) as $event) {
                $text = strtolower(($event['message'] ?? '') . ' ' . ($event['detail'] ?? ''));
                if (str_contains($text, 'certificate obtained') || str_contains($text, 'obtained certificate')) {
                    $selected = $event;
                    $status = 'success';
                    break;
                }
            }
        }

        if ($status === 'info') {
            foreach (array_reverse($events) as $event) {
                $text = strtolower(($event['message'] ?? '') . ' ' . ($event['detail'] ?? ''));
                if (str_contains($text, 'trying to solve challenge') || str_contains($text, 'obtaining certificate')) {
                    $selected = $event;
                    $status = 'warning';
                    break;
                }
            }
        }

        $diagnosis = $this->diagnoseAcmeIssue(($selected['message'] ?? '') . ' ' . ($selected['detail'] ?? ''));
        return [
            'observed' => true,
            'status' => $status,
            'message' => $selected['message'] ?? 'Evento ACME detectado.',
            'detail' => $selected['detail'] ?? null,
            'next_step' => $diagnosis['next_step'],
            'category' => $diagnosis['category'],
            'ts' => $selected['ts'] ?? gmdate('c'),
            'fingerprint' => sha1($status . '|' . ($selected['message'] ?? '') . '|' . ($selected['detail'] ?? '') . '|' . $domain),
        ];
    }

    private function parseCaddyAcmeLogLine(string $line): array
    {
        $payload = null;
        $jsonPos = strpos($line, '{');
        if ($jsonPos !== false) {
            $candidate = substr($line, $jsonPos);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (!is_array($payload)) {
            return [
                'ts' => gmdate('c'),
                'level' => 'info',
                'message' => $line,
                'detail' => null,
            ];
        }

        $message = (string)($payload['msg'] ?? $payload['message'] ?? 'Evento ACME');
        $detail = null;
        if (!empty($payload['error'])) {
            $detail = (string)$payload['error'];
        } elseif (!empty($payload['problem']['detail'])) {
            $detail = (string)$payload['problem']['detail'];
        }

        $ts = gmdate('c');
        if (isset($payload['ts']) && is_numeric($payload['ts'])) {
            $ts = gmdate('c', (int)$payload['ts']);
        }

        return [
            'ts' => $ts,
            'level' => (string)($payload['level'] ?? 'info'),
            'message' => $message,
            'detail' => $detail,
        ];
    }

    private function diagnoseAcmeIssue(string $text): array
    {
        $text = strtolower($text);
        if ($text === '') {
            return [
                'category' => 'unknown',
                'next_step' => 'Revisa logs detallados de Caddy para obtener más contexto.',
            ];
        }

        $rules = [
            'timeout during connect' => [
                'category' => 'firewall',
                'next_step' => 'Abre 80/443 públicamente (o usa DNS-01) y reintenta emisión.',
            ],
            'firewall' => [
                'category' => 'firewall',
                'next_step' => 'Verifica reglas INPUT para 80/443 durante el challenge.',
            ],
            'context canceled' => [
                'category' => 'reload',
                'next_step' => 'Evita recargas/restarts de Caddy durante la emisión ACME.',
            ],
            'stopping apps' => [
                'category' => 'reload',
                'next_step' => 'No reinicies servicios mientras ACME valida challenge.',
            ],
            'no information found to solve challenge' => [
                'category' => 'challenge-state',
                'next_step' => 'Reinicia flujo de emisión; ese endpoint solo existe durante el challenge activo.',
            ],
            'dns.providers' => [
                'category' => 'provider-module',
                'next_step' => 'Instala módulo dns.providers y valida credenciales del proveedor.',
            ],
            'unauthorized' => [
                'category' => 'authorization',
                'next_step' => 'Confirma DNS/proxy y challenge correcto para el dominio.',
            ],
            'rate limit' => [
                'category' => 'rate-limit',
                'next_step' => 'Espera la ventana de rate-limit y vuelve a intentar.',
            ],
            'nxdomain' => [
                'category' => 'dns',
                'next_step' => 'Corrige DNS público del dominio y reintenta.',
            ],
        ];

        foreach ($rules as $pattern => $result) {
            if (str_contains($text, $pattern)) {
                return $result;
            }
        }

        return [
            'category' => 'generic',
            'next_step' => 'Revisa el último error ACME y ejecuta un nuevo intento controlado.',
        ];
    }

    private function extractAcmeEvents(array $lines, string $domainFilter): array
    {
        $events = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }

            if (
                !str_contains($line, 'acme')
                && !str_contains($line, 'tls.obtain')
                && !str_contains($line, 'challenge')
                && !str_contains($line, 'certificate')
            ) {
                continue;
            }

            if ($domainFilter !== '' && !str_contains(strtolower($line), strtolower($domainFilter))) {
                continue;
            }

            $events[] = $this->parseCaddyAcmeLogLine($line);
        }
        return $events;
    }

    private function persistRuntimeSnapshot(array $runtime): void
    {
        $fingerprint = (string)($runtime['fingerprint'] ?? '');
        if ($fingerprint === '') {
            return;
        }

        $store = $this->loadAcmeStatusStore();
        $existing = (string)($store['runtime']['fingerprint'] ?? '');
        if ($existing === $fingerprint) {
            return;
        }

        $store['runtime'] = $runtime;
        $event = [
            'id' => 'runtime-' . $fingerprint,
            'ts' => $runtime['ts'] ?? gmdate('c'),
            'source' => 'runtime',
            'status' => $runtime['status'] ?? 'info',
            'title' => 'Caddy ACME runtime',
            'message' => $runtime['message'] ?? 'Evento ACME',
            'detail' => $runtime['detail'] ?? null,
            'next_step' => $runtime['next_step'] ?? null,
            'context' => [
                'category' => $runtime['category'] ?? 'unknown',
            ],
        ];
        $store['last'] = $event;
        $store['history'] = $this->appendHistoryEvent($store['history'] ?? [], $event);
        $this->saveAcmeStatusStore($store);
    }

    private function loadAcmeStatusStore(): array
    {
        $this->ensureStateDir();
        $file = $this->acmeStatusStorePath();
        if (!is_file($file)) {
            return ['last' => null, 'runtime' => null, 'history' => []];
        }

        $raw = @file_get_contents($file);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return ['last' => null, 'runtime' => null, 'history' => []];
        }

        if (!isset($decoded['history']) || !is_array($decoded['history'])) {
            $decoded['history'] = [];
        }
        return $decoded;
    }

    private function saveAcmeStatusStore(array $store): void
    {
        $this->ensureStateDir();
        @file_put_contents($this->acmeStatusStorePath(), json_encode($store, JSON_PRETTY_PRINT));
    }

    private function appendHistoryEvent(array $history, array $event): array
    {
        $history[] = $event;
        if (count($history) > self::MAX_HISTORY_EVENTS) {
            $history = array_slice($history, -self::MAX_HISTORY_EVENTS);
        }
        return array_values($history);
    }

    private function parseDnsProvidersFromModules(string $modulesOutput): array
    {
        $providers = [];
        $lines = preg_split('/\R/', $modulesOutput) ?: [];
        foreach ($lines as $lineRaw) {
            $line = trim((string)$lineRaw);
            if (!str_starts_with($line, 'dns.providers.')) {
                continue;
            }
            $providers[] = substr($line, strlen('dns.providers.'));
        }
        sort($providers);
        return array_values(array_unique($providers));
    }

    private function providerCredentialMap(): array
    {
        return [
            'cloudflare' => [
                'token' => ['CLOUDFLARE_API_TOKEN'],
            ],
            'digitalocean' => [
                'token' => ['DIGITALOCEAN_API_TOKEN', 'DIGITALOCEAN_TOKEN'],
            ],
            'route53' => [
                'access_key' => ['AWS_ACCESS_KEY_ID'],
                'secret_key' => ['AWS_SECRET_ACCESS_KEY'],
            ],
            'hetzner' => [
                'token' => ['HETZNER_API_TOKEN'],
            ],
            'ovh' => [
                'endpoint' => ['OVH_ENDPOINT'],
                'app_key' => ['OVH_APPLICATION_KEY'],
                'app_secret' => ['OVH_APPLICATION_SECRET'],
                'consumer_key' => ['OVH_CONSUMER_KEY'],
            ],
            'vultr' => [
                'token' => ['VULTR_API_KEY', 'VULTR_API_TOKEN'],
            ],
            'linode' => [
                'token' => ['LINODE_TOKEN', 'LINODE_API_TOKEN'],
            ],
            'porkbun' => [
                'api_key' => ['PORKBUN_API_KEY'],
                'secret_key' => ['PORKBUN_SECRET_API_KEY'],
            ],
            'namecheap' => [
                'api_user' => ['NAMECHEAP_API_USER'],
                'api_key' => ['NAMECHEAP_API_KEY'],
                'username' => ['NAMECHEAP_USERNAME'],
                'client_ip' => ['NAMECHEAP_CLIENT_IP'],
            ],
            'gandi' => [
                'token' => ['GANDI_API_TOKEN'],
            ],
            'rfc2136' => [
                'nameserver' => ['RFC2136_NAMESERVER'],
                'tsig_keyname' => ['RFC2136_TSIG_KEYNAME'],
                'tsig_secret' => ['RFC2136_TSIG_SECRET'],
            ],
            'powerdns' => [
                'api_url' => ['POWERDNS_API_URL'],
                'api_key' => ['POWERDNS_API_KEY'],
            ],
        ];
    }

    private function collectEnv(): array
    {
        $keys = [];
        foreach ($this->providerCredentialMap() as $groups) {
            foreach ($groups as $groupVars) {
                foreach ($groupVars as $varName) {
                    $keys[$varName] = true;
                }
            }
        }

        $envData = [];
        foreach (array_keys($keys) as $key) {
            $value = trim((string)Env::get($key, ''));
            if ($value === '') {
                $value = trim((string)(getenv($key) ?: ''));
            }
            if ($value !== '') {
                $envData[$key] = $value;
            }
        }
        return $envData;
    }

    private function providerSecurityTips(string $provider): array
    {
        $common = [
            'Usa tokens con permisos mínimos (solo DNS Zone Edit/Read cuando sea posible).',
            'No reutilices tokens de producción en entornos de prueba.',
            'Guarda credenciales en variables de entorno; no en vistas ni logs.',
        ];

        $specific = [
            'cloudflare' => ['Token recomendado: Zone.DNS Edit + Zone.Zone Read solo para la zona objetivo.'],
            'route53' => ['Usuario IAM dedicado con política mínima sobre la zona Route53.'],
            'ovh' => ['Limita endpoint/app keys a la zona necesaria y rota credenciales periódicamente.'],
            'namecheap' => ['Restringe Client IP al servidor emisor para reducir exposición del API key.'],
            'rfc2136' => ['Usa clave TSIG dedicada y restringe updates al nombre _acme-challenge.'],
        ];

        return array_values(array_unique(array_merge($common, $specific[$provider] ?? [])));
    }

    private function formatMissingCredentialGroups(array $missing): string
    {
        $parts = [];
        foreach ($missing as $groupVars) {
            if (is_array($groupVars) && !empty($groupVars)) {
                $parts[] = implode(' o ', $groupVars);
            }
        }
        return implode(' | ', $parts);
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?: $domain;
        $domain = explode('/', $domain)[0] ?? $domain;
        $domain = explode(':', $domain)[0] ?? $domain;
        return trim($domain);
    }

    private function commandExists(string $command): bool
    {
        $check = $this->runCommand('command -v ' . escapeshellarg($command) . ' >/dev/null 2>&1');
        return ($check['code'] ?? 1) === 0;
    }

    private function runCommand(string $command): array
    {
        if ($this->commandRunner) {
            $res = call_user_func($this->commandRunner, $command);
            return is_array($res) ? $res : ['code' => 1, 'stdout' => '', 'stderr' => 'Invalid runner output'];
        }

        if (!function_exists('exec')) {
            return ['code' => 1, 'stdout' => '', 'stderr' => 'exec() no disponible'];
        }

        $stderrFile = tempnam(sys_get_temp_dir(), 'mdk-acme-');
        if ($stderrFile === false) {
            $stderrFile = '/tmp/mdk-acme.err';
        }

        $stdout = [];
        $code = 0;
        exec($command . ' 2>' . escapeshellarg($stderrFile), $stdout, $code);
        $stderr = '';
        if (is_file($stderrFile)) {
            $stderr = (string)@file_get_contents($stderrFile);
            @unlink($stderrFile);
        }

        return [
            'code' => $code,
            'stdout' => implode("\n", $stdout),
            'stderr' => trim($stderr),
        ];
    }

    private function buildRuleComment(string $ticketId): string
    {
        return 'musedock-acme-temp-' . $ticketId;
    }

    private function stateFilePath(string $ticketId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $ticketId);
        return $this->stateDir() . '/acme-temp-' . $safe . '.json';
    }

    private function stateDir(): string
    {
        return APP_ROOT . '/storage/cache/acme-assistant';
    }

    private function acmeStatusStorePath(): string
    {
        return $this->stateDir() . '/acme-status.json';
    }

    private function ensureStateDir(): void
    {
        $dir = $this->stateDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function saveTemporaryRuleState(string $ticketId, array $state): void
    {
        $this->ensureStateDir();
        @file_put_contents($this->stateFilePath($ticketId), json_encode($state, JSON_PRETTY_PRINT));
    }

    private function deleteTemporaryRuleState(string $ticketId): void
    {
        $file = $this->stateFilePath($ticketId);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function defaultTemporaryTtlSeconds(): int
    {
        return self::DEFAULT_TEMP_TTL_SECONDS;
    }
}
