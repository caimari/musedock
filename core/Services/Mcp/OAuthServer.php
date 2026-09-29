<?php

namespace Screenart\Musedock\Services\Mcp;

use Screenart\Musedock\Database;
use Screenart\Musedock\Env;
use Screenart\Musedock\View;
use Screenart\Musedock\Models\ApiKey;
use Screenart\Musedock\Security\IPHelper;
use Screenart\Musedock\Security\RateLimiter;
use Screenart\Musedock\Security\UrlGuard;
use Screenart\Musedock\Services\AuditLogger;

/**
 * Servidor de autorización OAuth 2.1 por tenant para el servidor MCP.
 *
 * Cada dominio de tenant es su propio issuer (https://{dominio}) y protege el
 * recurso https://{dominio}/mcp. Implementa lo que exige la especificación de
 * autorización de MCP para clientes como Claude.ai, ChatGPT o Claude Code:
 *
 *   - Protected Resource Metadata (RFC 9728)     /.well-known/oauth-protected-resource[/mcp]
 *   - Authorization Server Metadata (RFC 8414)   /.well-known/oauth-authorization-server
 *   - Dynamic Client Registration (RFC 7591)     POST /oauth/register
 *   - Client ID Metadata Documents               client_id = URL https con el documento JSON
 *   - Authorization Code + PKCE S256             /oauth/authorize (login + consentimiento)
 *   - Tokens opacos con rotación de refresh      POST /oauth/token
 *   - Revocación (RFC 7009)                      POST /oauth/revoke
 *   - Resource Indicators (RFC 8707)             los tokens quedan ligados a este /mcp
 *
 * Cada consentimiento aprobado crea (o actualiza) una conexión en api_keys con
 * auth_type = 'oauth' y los permisos sección × nivel elegidos; los tokens
 * apuntan a esa conexión, así que revocarla en el panel corta el acceso.
 */
class OAuthServer
{
    public const SCOPES = ['musedock.read', 'musedock.write', 'musedock.publish', 'musedock.delete'];

    private const ACCESS_TTL = 3600;
    private const REFRESH_TTL = 5184000; // 60 días
    private const CODE_TTL = 300;
    private const PENDING_TTL = 900;

    private const AUTH_METHODS = ['none', 'client_secret_basic', 'client_secret_post'];

    /** Redirect hosts de clientes conocidos (se muestran como verificados en el consentimiento). */
    private const KNOWN_REDIRECT_HOSTS = [
        'claude.ai' => 'Claude', 'claude.com' => 'Claude',
        'chatgpt.com' => 'ChatGPT', 'chat.openai.com' => 'ChatGPT', 'openai.com' => 'ChatGPT',
    ];

    // =========================================================================
    // Contexto
    // =========================================================================

    public static function tenant(): ?array
    {
        $tenant = $GLOBALS['tenant'] ?? null;
        return !empty($tenant['id']) ? $tenant : null;
    }

    /**
     * Origen público con el que el cliente llegó (dominio del tenant o alias).
     */
    public static function origin(): string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
            $host = strtolower((string) (self::tenant()['domain'] ?? 'localhost'));
        }
        return 'https://' . $host;
    }

    public static function issuer(): string
    {
        return self::origin();
    }

    public static function resourceUrl(): string
    {
        return self::origin() . '/mcp';
    }

    public static function resourceMetadataUrl(): string
    {
        return self::origin() . '/.well-known/oauth-protected-resource/mcp';
    }

    public static function available(): bool
    {
        return self::tenant() !== null
            && filter_var(Env::get('MCP_ENABLED', true), FILTER_VALIDATE_BOOLEAN)
            && (string) tenant_setting('mcp_enabled', '0') === '1';
    }

    // =========================================================================
    // Metadata
    // =========================================================================

    public function protectedResourceMetadata(): void
    {
        $this->corsHeaders();
        if (!self::available()) {
            $this->json(404, ['error' => 'not_found', 'error_description' => 'MCP is not enabled for this website.']);
            return;
        }

        $this->json(200, [
            'resource'                 => self::resourceUrl(),
            'authorization_servers'    => [self::issuer()],
            'scopes_supported'         => self::SCOPES,
            'bearer_methods_supported' => ['header'],
            'resource_name'            => 'MuseDock — ' . (self::tenant()['name'] ?? self::tenant()['domain']),
        ], 'public, max-age=300');
    }

    public function authorizationServerMetadata(): void
    {
        $this->corsHeaders();
        if (!self::available()) {
            $this->json(404, ['error' => 'not_found', 'error_description' => 'MCP is not enabled for this website.']);
            return;
        }

        $base = self::issuer();
        $this->json(200, [
            'issuer'                                         => $base,
            'authorization_endpoint'                         => $base . '/oauth/authorize',
            'token_endpoint'                                 => $base . '/oauth/token',
            'registration_endpoint'                          => $base . '/oauth/register',
            'revocation_endpoint'                            => $base . '/oauth/revoke',
            'scopes_supported'                               => self::SCOPES,
            'response_types_supported'                       => ['code'],
            'response_modes_supported'                       => ['query'],
            'grant_types_supported'                          => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported'               => ['S256'],
            'token_endpoint_auth_methods_supported'          => self::AUTH_METHODS,
            'revocation_endpoint_auth_methods_supported'     => self::AUTH_METHODS,
            'client_id_metadata_document_supported'          => true,
            'authorization_response_iss_parameter_supported' => true,
        ], 'public, max-age=300');
    }

    // =========================================================================
    // Dynamic Client Registration (RFC 7591)
    // =========================================================================

    public function register(): void
    {
        $this->corsHeaders();
        $tenant = self::tenant();
        if (!self::available()) {
            $this->json(404, ['error' => 'not_found', 'error_description' => 'MCP is not enabled for this website.']);
            return;
        }

        $ip = IPHelper::getRealIP();
        $limiterKey = 'oauth_register|' . $ip;
        if (!RateLimiter::check($limiterKey, 20, 60)) {
            header('Retry-After: 3600');
            $this->json(429, ['error' => 'too_many_requests', 'error_description' => 'Too many client registrations from this IP.']);
            return;
        }
        RateLimiter::increment($limiterKey, 60);

        $meta = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($meta)) {
            $this->json(400, ['error' => 'invalid_client_metadata', 'error_description' => 'Body must be a JSON object.']);
            return;
        }

        $redirectUris = $meta['redirect_uris'] ?? null;
        if (!is_array($redirectUris) || !$redirectUris || count($redirectUris) > 10) {
            $this->json(400, ['error' => 'invalid_redirect_uri', 'error_description' => 'redirect_uris must be a non-empty array (max 10).']);
            return;
        }
        foreach ($redirectUris as $uri) {
            if (!is_string($uri) || !self::isValidRedirectUri($uri)) {
                $this->json(400, ['error' => 'invalid_redirect_uri', 'error_description' => 'Invalid redirect URI: use https, a loopback http URI, or a private-use scheme.']);
                return;
            }
        }

        $authMethod = $meta['token_endpoint_auth_method'] ?? 'client_secret_basic';
        if (!in_array($authMethod, self::AUTH_METHODS, true)) {
            $this->json(400, ['error' => 'invalid_client_metadata', 'error_description' => 'Unsupported token_endpoint_auth_method.']);
            return;
        }

        $grantTypes = $meta['grant_types'] ?? ['authorization_code', 'refresh_token'];
        $responseTypes = $meta['response_types'] ?? ['code'];
        if (!is_array($grantTypes) || array_diff($grantTypes, ['authorization_code', 'refresh_token'])
            || !is_array($responseTypes) || array_diff($responseTypes, ['code'])) {
            $this->json(400, ['error' => 'invalid_client_metadata', 'error_description' => 'Only authorization_code/refresh_token grants and the code response type are supported.']);
            return;
        }

        $clientName = mb_substr(trim(strip_tags((string) ($meta['client_name'] ?? ''))), 0, 255) ?: null;
        $clientUri = self::safeHttpsUrl($meta['client_uri'] ?? null);
        $logoUri = self::safeHttpsUrl($meta['logo_uri'] ?? null);

        $clientId = 'mdc_' . bin2hex(random_bytes(16));
        $clientSecret = $authMethod === 'none' ? null : 'mds_' . bin2hex(random_bytes(32));

        Database::connect()->prepare("
            INSERT INTO oauth_clients (tenant_id, client_id, client_secret_hash, client_name, client_uri, logo_uri, redirect_uris, token_endpoint_auth_method, registration_ip, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            (int) $tenant['id'], $clientId, $clientSecret ? hash('sha256', $clientSecret) : null,
            $clientName, $clientUri, $logoUri, json_encode(array_values($redirectUris)), $authMethod, $ip,
        ]);

        if (random_int(1, 50) === 1) {
            self::cleanup();
        }

        $response = array_filter([
            'client_id'                  => $clientId,
            'client_secret'              => $clientSecret,
            'client_id_issued_at'        => time(),
            'client_secret_expires_at'   => $clientSecret ? 0 : null,
            'client_name'                => $clientName,
            'client_uri'                 => $clientUri,
            'logo_uri'                   => $logoUri,
            'redirect_uris'              => array_values($redirectUris),
            'token_endpoint_auth_method' => $authMethod,
            'grant_types'                => array_values($grantTypes),
            'response_types'             => array_values($responseTypes),
        ], fn($v) => $v !== null);

        $this->json(201, $response);
    }

    // =========================================================================
    // Authorization endpoint
    // =========================================================================

    public function authorize(): void
    {
        header('Cache-Control: no-store');
        header('X-Frame-Options: DENY');
        self::consentCsp(null);

        if (!self::available()) {
            $this->errorPage('Este sitio no tiene activadas las conexiones de IA (MCP).', 404);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->authorizeDecision();
            return;
        }

        $params = $_GET;
        $client = $this->findClient((string) ($params['client_id'] ?? ''));
        if (!$client) {
            $this->errorPage('La aplicación que intenta conectarse no está registrada (client_id desconocido).');
            return;
        }

        $redirectUri = (string) ($params['redirect_uri'] ?? '');
        $registered = json_decode($client['redirect_uris'], true) ?: [];
        if ($redirectUri === '' && count($registered) === 1) {
            $redirectUri = $registered[0];
        }
        if (!self::redirectUriMatches($redirectUri, $registered)) {
            $this->errorPage('La dirección de retorno (redirect_uri) no coincide con la registrada por la aplicación.');
            return;
        }

        // A partir de aquí los errores se devuelven al cliente por redirect
        $state = isset($params['state']) ? (string) $params['state'] : null;

        if (($params['response_type'] ?? '') !== 'code') {
            $this->redirectError($redirectUri, 'unsupported_response_type', 'Only response_type=code is supported.', $state);
            return;
        }
        $challenge = (string) ($params['code_challenge'] ?? '');
        if (($params['code_challenge_method'] ?? '') !== 'S256' || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge)) {
            $this->redirectError($redirectUri, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.', $state);
            return;
        }
        $resource = isset($params['resource']) ? (string) $params['resource'] : null;
        if ($resource !== null && !self::resourceMatches($resource)) {
            $this->redirectError($redirectUri, 'invalid_target', 'Unknown resource. Use ' . self::resourceUrl(), $state);
            return;
        }

        // Rebote same-site: con SameSite=Strict la cookie de sesión no llega en la
        // navegación que viene de claude.ai/chatgpt.com. Esta respuesta es sin estado
        // (no toca la cookie) y reenvía al mismo URL desde nuestro propio origen.
        if (!isset($params['_ss'])) {
            $this->bouncePage(($_SERVER['REQUEST_URI'] ?? '/oauth/authorize') . '&_ss=1');
            return;
        }

        $user = $this->sessionUser();
        if (!$user) {
            $_SESSION['auth_return_to'] = $_SERVER['REQUEST_URI'];
            flash('warning', 'Inicia sesión para autorizar la conexión de "' . ($client['client_name'] ?: 'la aplicación') . '".');
            header('Location: /' . admin_path() . '/login');
            exit;
        }

        if (!self::canAuthorize()) {
            $this->errorPage('Tu usuario no tiene permiso para conectar asistentes de IA en este sitio. Pide a un administrador el permiso "Conectar asistentes de IA".', 403);
            return;
        }

        // Solo se ofrece lo que esta persona puede hacer en el panel
        $grantable = McpPermissions::grantableMatrix((int) $user['id'], (int) self::tenant()['id']);
        if (!array_filter($grantable, fn($levels) => in_array('read', $levels, true))) {
            $this->errorPage('Tu usuario no tiene permisos sobre ningún contenido que se pueda compartir con un asistente de IA.', 403);
            return;
        }

        // Guardar la petición pendiente en sesión (el formulario solo lleva su id)
        $rid = bin2hex(random_bytes(16));
        $pending = array_filter($_SESSION['oauth_pending'] ?? [], fn($p) => ($p['created'] ?? 0) > time() - self::PENDING_TTL);
        $pending = array_slice($pending, -4, null, true);
        $pending[$rid] = [
            'client_id'      => $client['client_id'],
            'redirect_uri'   => $redirectUri,
            'state'          => $state,
            'code_challenge' => $challenge,
            'resource'       => $resource,
            'scope'          => (string) ($params['scope'] ?? ''),
            'created'        => time(),
        ];
        $_SESSION['oauth_pending'] = $pending;

        $redirectHost = strtolower((string) parse_url($redirectUri, PHP_URL_HOST));
        $known = null;
        foreach (self::KNOWN_REDIRECT_HOSTS as $host => $label) {
            if ($redirectHost === $host || str_ends_with($redirectHost, '.' . $host)) {
                $known = $label;
                break;
            }
        }
        $isLoopback = in_array($redirectHost, ['localhost', '127.0.0.1', '::1', '[::1]'], true);

        $templates = array_keys(McpPermissions::TEMPLATES);
        $existing = $this->findGrant((int) self::tenant()['id'], $client['client_id'], (int) $user['id']);

        // El 302 tras enviar el consentimiento va a otro origen: Chrome aplica form-action a esa redirección
        self::consentCsp($redirectUri);

        echo View::renderTenantAdmin('oauth.consent', [
            'title'          => 'Autorizar conexión',
            'rid'            => $rid,
            'client'         => $client,
            'clientName'     => $client['client_name'] ?: ($known ?? 'Aplicación sin nombre'),
            'redirectHost'   => $redirectHost ?: parse_url($redirectUri, PHP_URL_SCHEME) . '://',
            'knownClient'    => $known,
            'isLoopback'     => $isLoopback,
            'siteName'       => self::tenant()['name'] ?? self::tenant()['domain'],
            'siteDomain'     => self::tenant()['domain'],
            'userName'       => $user['name'] ?? $user['email'] ?? '',
            'sections'       => McpPermissions::sections(),
            'levels'         => McpPermissions::LEVELS,
            'templates'      => McpPermissions::TEMPLATES,
            'templateMatrix' => array_map(fn($t) => self::intersectMatrix(McpPermissions::templateMatrix($t), $grantable), array_combine($templates, $templates)),
            'grantable'      => $grantable,
            'selected'       => self::templateFromScope((string) ($params['scope'] ?? '')),
            'existing'       => $existing ? McpPermissions::summarize(json_decode($existing['permissions'] ?? '[]', true) ?: []) : null,
        ]);
    }

    private function authorizeDecision(): void
    {
        $rid = (string) ($_POST['rid'] ?? '');
        $pending = $_SESSION['oauth_pending'][$rid] ?? null;
        unset($_SESSION['oauth_pending'][$rid]);

        if (!$pending || ($pending['created'] ?? 0) < time() - self::PENDING_TTL) {
            $this->errorPage('La solicitud de autorización ha caducado. Vuelve a iniciar la conexión desde la aplicación.');
            return;
        }

        $user = $this->sessionUser();
        if (!$user || !self::canAuthorize()) {
            $this->errorPage('Tu sesión ha caducado o no tienes permiso para autorizar conexiones.', 403);
            return;
        }

        $client = $this->findClient($pending['client_id']);
        if (!$client) {
            $this->errorPage('La aplicación ya no está registrada.');
            return;
        }

        if (($_POST['decision'] ?? '') !== 'approve') {
            $this->redirectError($pending['redirect_uri'], 'access_denied', 'The site administrator denied the request.', $pending['state']);
            return;
        }

        $template = $_POST['template'] ?? 'custom';
        $permissions = isset(McpPermissions::TEMPLATES[$template])
            ? McpPermissions::toPermissions(McpPermissions::templateMatrix($template))
            : McpPermissions::toPermissions(is_array($_POST['perm'] ?? null) ? $_POST['perm'] : []);

        // Nunca más de lo que la persona que autoriza puede hacer en el panel
        $permissions = McpPermissions::capToUser($permissions, (int) $user['id'], (int) self::tenant()['id']);

        if (!$permissions) {
            $this->redirectError($pending['redirect_uri'], 'access_denied', 'No permissions were granted.', $pending['state']);
            return;
        }

        $tenantId = (int) self::tenant()['id'];
        $name = $client['client_name'] ?: 'Aplicación OAuth';
        $grant = $this->findGrant($tenantId, $client['client_id'], (int) $user['id']);

        $previousPermissions = $grant ? (json_decode($grant['permissions'] ?? '[]', true) ?: []) : null;

        if ($grant) {
            $key = ApiKey::find((int) $grant['id']);
            $key->update(['permissions' => json_encode($permissions), 'is_active' => 1, 'name' => $name]);
        } else {
            $key = ApiKey::create([
                'tenant_id'       => $tenantId,
                'domain_group_id' => null,
                'name'            => $name,
                // Nunca utilizable como token mdk_: el valor en claro no existe
                'api_key_hash'    => hash('sha256', 'oauth-grant:' . bin2hex(random_bytes(32))),
                'permissions'     => json_encode($permissions),
                'rate_limit'      => 120,
                'expires_at'      => null,
                'is_active'       => 1,
                'auth_type'       => 'oauth',
                'oauth_client_id' => $client['client_id'],
                'user_id'         => (int) $user['id'],
            ]);
        }

        $code = bin2hex(random_bytes(32));
        Database::connect()->prepare("
            INSERT INTO oauth_auth_codes (code_hash, tenant_id, client_id, api_key_id, redirect_uri, code_challenge, resource, scope, family_id, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            hash('sha256', $code), $tenantId, $client['client_id'], (int) $key->id, $pending['redirect_uri'],
            $pending['code_challenge'], $pending['resource'] ?? self::resourceUrl(), self::scopeFromPermissions($permissions),
            bin2hex(random_bytes(16)), self::utc(self::CODE_TTL),
        ]);

        AuditLogger::log('mcp.oauth_authorized', 'api_key', (int) $key->id, [
            'client_id' => $client['client_id'], 'client_name' => $name, 'permissions' => $permissions,
        ]);

        // Aviso por email: conexión nueva, o re-autorización que cambia permisos
        $sortedPrev = $previousPermissions;
        $sortedNew = $permissions;
        if (is_array($sortedPrev)) { sort($sortedPrev); }
        sort($sortedNew);
        if ($previousPermissions === null || $sortedPrev !== $sortedNew) {
            McpNotifier::connectionChanged($tenantId, $previousPermissions === null ? 'created' : 'updated', $name, 'oauth', $permissions,
                $user['name'] ?? null, $user['email'] ?? null, (string) parse_url($pending['redirect_uri'], PHP_URL_HOST));
        }

        $this->redirectTo($pending['redirect_uri'], array_filter([
            'code'  => $code,
            'state' => $pending['state'],
            'iss'   => self::issuer(),
        ], fn($v) => $v !== null));
    }

    // =========================================================================
    // Token endpoint
    // =========================================================================

    public function token(): void
    {
        $this->corsHeaders();
        header('Pragma: no-cache');

        if (!self::available()) {
            $this->json(400, ['error' => 'invalid_request', 'error_description' => 'MCP is not enabled for this website.']);
            return;
        }

        $params = $this->requestParams();
        $ip = IPHelper::getRealIP();
        $limiterKey = 'oauth_token_fail|' . $ip;
        if (!RateLimiter::check($limiterKey, 30, 15)) {
            header('Retry-After: 900');
            $this->json(429, ['error' => 'too_many_requests', 'error_description' => 'Too many failed token requests.']);
            return;
        }

        $client = $this->authenticateClient($params);
        if (!$client) {
            RateLimiter::increment($limiterKey, 15);
            header('WWW-Authenticate: Basic realm="MuseDock OAuth"');
            $this->json(401, ['error' => 'invalid_client', 'error_description' => 'Client authentication failed.']);
            return;
        }

        $grantType = (string) ($params['grant_type'] ?? '');
        $result = match ($grantType) {
            'authorization_code' => $this->grantAuthorizationCode($client, $params),
            'refresh_token'      => $this->grantRefreshToken($client, $params),
            default              => ['error' => 'unsupported_grant_type', 'error_description' => 'Use authorization_code or refresh_token.'],
        };

        if (isset($result['error'])) {
            RateLimiter::increment($limiterKey, 15);
            $this->json(400, $result);
            return;
        }

        Database::connect()->prepare("UPDATE oauth_clients SET last_used_at = NOW() WHERE id = ?")->execute([(int) $client['id']]);
        $this->json(200, $result);
    }

    private function grantAuthorizationCode(array $client, array $params): array
    {
        $pdo = Database::connect();
        $tenantId = (int) self::tenant()['id'];

        $stmt = $pdo->prepare("SELECT * FROM oauth_auth_codes WHERE code_hash = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([hash('sha256', (string) ($params['code'] ?? '')), $tenantId]);
        $code = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$code || $code['client_id'] !== $client['client_id']) {
            return ['error' => 'invalid_grant', 'error_description' => 'Invalid authorization code.'];
        }

        if ($code['used_at'] !== null) {
            // Reutilización de código: se revocan los tokens emitidos con él (RFC 9700)
            $this->revokeFamily($code['family_id']);
            return ['error' => 'invalid_grant', 'error_description' => 'Authorization code already used.'];
        }

        if (self::isPast($code['expires_at'])) {
            return ['error' => 'invalid_grant', 'error_description' => 'Authorization code expired.'];
        }

        if (isset($params['redirect_uri']) && (string) $params['redirect_uri'] !== $code['redirect_uri']) {
            return ['error' => 'invalid_grant', 'error_description' => 'redirect_uri mismatch.'];
        }

        $verifier = (string) ($params['code_verifier'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)
            || !hash_equals($code['code_challenge'], rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='))) {
            return ['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed.'];
        }

        if (isset($params['resource']) && !self::resourceMatches((string) $params['resource'])) {
            return ['error' => 'invalid_target', 'error_description' => 'Unknown resource.'];
        }

        $claim = $pdo->prepare("UPDATE oauth_auth_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL");
        $claim->execute([(int) $code['id']]);
        if ($claim->rowCount() !== 1) {
            return ['error' => 'invalid_grant', 'error_description' => 'Authorization code already used.'];
        }

        if (!$this->activeGrant((int) $code['api_key_id'], $tenantId)) {
            return ['error' => 'invalid_grant', 'error_description' => 'The connection was revoked.'];
        }

        return $this->issueTokens($client['client_id'], (int) $code['api_key_id'], $code['family_id'], $code['resource'], $code['scope']);
    }

    private function grantRefreshToken(array $client, array $params): array
    {
        $pdo = Database::connect();
        $tenantId = (int) self::tenant()['id'];

        $stmt = $pdo->prepare("SELECT * FROM oauth_tokens WHERE token_hash = ? AND token_type = 'refresh' AND tenant_id = ? LIMIT 1");
        $stmt->execute([hash('sha256', (string) ($params['refresh_token'] ?? '')), $tenantId]);
        $token = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$token || $token['client_id'] !== $client['client_id']) {
            return ['error' => 'invalid_grant', 'error_description' => 'Invalid refresh token.'];
        }

        if (!$this->activeGrant((int) $token['api_key_id'], $tenantId)) {
            return ['error' => 'invalid_grant', 'error_description' => 'The connection was revoked by the site administrator.'];
        }

        if ($token['revoked_at'] !== null) {
            // Un refresh token ya rotado se ha vuelto a usar: posible robo → cortar la familia
            $this->revokeFamily($token['family_id']);
            return ['error' => 'invalid_grant', 'error_description' => 'Refresh token reuse detected; the session was revoked.'];
        }

        if (self::isPast($token['expires_at'])) {
            return ['error' => 'invalid_grant', 'error_description' => 'Refresh token expired.'];
        }

        if (isset($params['resource']) && !self::resourceMatches((string) $params['resource'])) {
            return ['error' => 'invalid_target', 'error_description' => 'Unknown resource.'];
        }

        $rotate = $pdo->prepare("UPDATE oauth_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL");
        $rotate->execute([(int) $token['id']]);
        if ($rotate->rowCount() !== 1) {
            $this->revokeFamily($token['family_id']);
            return ['error' => 'invalid_grant', 'error_description' => 'Refresh token reuse detected; the session was revoked.'];
        }

        $grant = $this->activeGrant((int) $token['api_key_id'], $tenantId);
        if (!$grant) {
            return ['error' => 'invalid_grant', 'error_description' => 'The connection was revoked.'];
        }

        // El scope refleja los permisos actuales de la conexión (pueden haberse editado en el panel)
        $scope = self::scopeFromPermissions(json_decode($grant['permissions'] ?? '[]', true) ?: []);

        return $this->issueTokens($client['client_id'], (int) $token['api_key_id'], $token['family_id'], $token['resource'], $scope);
    }

    private function issueTokens(string $clientId, int $apiKeyId, string $familyId, ?string $resource, ?string $scope): array
    {
        $access = 'mdo_' . bin2hex(random_bytes(32));
        $refresh = 'mdr_' . bin2hex(random_bytes(32));
        $tenantId = (int) self::tenant()['id'];
        $resource = $resource ?: self::resourceUrl();

        $insert = Database::connect()->prepare("
            INSERT INTO oauth_tokens (token_hash, token_type, tenant_id, client_id, api_key_id, family_id, resource, scope, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert->execute([hash('sha256', $access), 'access', $tenantId, $clientId, $apiKeyId, $familyId, $resource, $scope, self::utc(self::ACCESS_TTL)]);
        $insert->execute([hash('sha256', $refresh), 'refresh', $tenantId, $clientId, $apiKeyId, $familyId, $resource, $scope, self::utc(self::REFRESH_TTL)]);

        return array_filter([
            'access_token'  => $access,
            'token_type'    => 'Bearer',
            'expires_in'    => self::ACCESS_TTL,
            'refresh_token' => $refresh,
            'scope'         => $scope,
        ], fn($v) => $v !== null && $v !== '');
    }

    // =========================================================================
    // Revocation (RFC 7009)
    // =========================================================================

    public function revoke(): void
    {
        $this->corsHeaders();
        if (!self::available()) {
            $this->json(200, new \stdClass());
            return;
        }

        $params = $this->requestParams();
        $client = $this->authenticateClient($params);
        if (!$client) {
            header('WWW-Authenticate: Basic realm="MuseDock OAuth"');
            $this->json(401, ['error' => 'invalid_client']);
            return;
        }

        $stmt = Database::connect()->prepare("SELECT * FROM oauth_tokens WHERE token_hash = ? AND tenant_id = ? AND client_id = ? LIMIT 1");
        $stmt->execute([hash('sha256', (string) ($params['token'] ?? '')), (int) self::tenant()['id'], $client['client_id']]);
        $token = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($token) {
            if ($token['token_type'] === 'refresh') {
                $this->revokeFamily($token['family_id']);
            } else {
                Database::connect()->prepare("UPDATE oauth_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL")->execute([(int) $token['id']]);
            }
        }

        // RFC 7009: responder 200 aunque el token no exista
        $this->json(200, new \stdClass());
    }

    // =========================================================================
    // Validación de access tokens (desde McpServer)
    // =========================================================================

    /**
     * Devuelve la conexión (ApiKey) de un access token válido para este tenant y
     * este recurso, o null.
     */
    public static function validateAccessToken(string $raw): ?ApiKey
    {
        $tenant = self::tenant();
        if (!$tenant || !preg_match('/^mdo_[a-f0-9]{64}$/', $raw)) {
            return null;
        }

        $stmt = Database::connect()->prepare("
            SELECT * FROM oauth_tokens
            WHERE token_hash = ? AND token_type = 'access' AND tenant_id = ?
              AND revoked_at IS NULL AND expires_at > ?
            LIMIT 1
        ");
        $stmt->execute([hash('sha256', $raw), (int) $tenant['id'], self::utc()]);
        $token = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$token || !self::resourceMatches((string) $token['resource'])) {
            return null;
        }

        $key = ApiKey::find((int) $token['api_key_id']);
        if (!$key || (int) $key->tenant_id !== (int) $tenant['id'] || ($key->auth_type ?? '') !== 'oauth') {
            return null;
        }
        if (!self::grantOwnerActive($key->user_id !== null ? (int) $key->user_id : null, (int) $tenant['id'])) {
            return null;
        }

        return $key;
    }

    /**
     * Revoca todos los tokens de una conexión (al revocarla desde el panel).
     */
    public static function revokeGrantTokens(int $apiKeyId): void
    {
        try {
            Database::connect()->prepare("UPDATE oauth_tokens SET revoked_at = NOW() WHERE api_key_id = ? AND revoked_at IS NULL")->execute([$apiKeyId]);
            Database::connect()->prepare("DELETE FROM oauth_auth_codes WHERE api_key_id = ?")->execute([$apiKeyId]);
        } catch (\Throwable $e) {
            error_log('OAuth revokeGrantTokens: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // Clientes
    // =========================================================================

    private function findClient(string $clientId): ?array
    {
        if ($clientId === '' || strlen($clientId) > 255) {
            return null;
        }

        $tenantId = (int) self::tenant()['id'];
        $stmt = Database::connect()->prepare("SELECT * FROM oauth_clients WHERE tenant_id = ? AND client_id = ? LIMIT 1");
        $stmt->execute([$tenantId, $clientId]);
        $client = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $isMetadataUrl = str_starts_with($clientId, 'https://');
        $stale = $client && $client['is_metadata_document'] && self::isPast((string) $client['metadata_fetched_at'], 86400);

        if ($isMetadataUrl && (!$client || $stale)) {
            $fetched = $this->fetchClientMetadataDocument($clientId);
            if ($fetched) {
                $this->storeMetadataClient($tenantId, $clientId, $fetched, (bool) $client);
                $stmt->execute([$tenantId, $clientId]);
                $client = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            } elseif (!$client) {
                return null;
            }
        }

        return $client;
    }

    /**
     * Client ID Metadata Document: el client_id es una URL https que sirve el
     * JSON con los metadatos del cliente.
     */
    private function fetchClientMetadataDocument(string $url): ?array
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || empty($parts['path']) || isset($parts['fragment'])) {
            return null;
        }

        $body = UrlGuard::fetch($url, 20000, 5, 0);
        $doc = $body ? json_decode($body, true) : null;

        if (!is_array($doc) || ($doc['client_id'] ?? null) !== $url) {
            return null;
        }
        if (!is_array($doc['redirect_uris'] ?? null) || !$doc['redirect_uris']) {
            return null;
        }
        foreach ($doc['redirect_uris'] as $uri) {
            if (!is_string($uri) || !self::isValidRedirectUri($uri)) {
                return null;
            }
        }
        if (($doc['token_endpoint_auth_method'] ?? 'none') !== 'none') {
            return null; // solo clientes públicos (private_key_jwt no soportado)
        }

        return $doc;
    }

    private function storeMetadataClient(int $tenantId, string $clientId, array $doc, bool $update): void
    {
        $values = [
            mb_substr(trim(strip_tags((string) ($doc['client_name'] ?? ''))), 0, 255) ?: null,
            self::safeHttpsUrl($doc['client_uri'] ?? null),
            self::safeHttpsUrl($doc['logo_uri'] ?? null),
            json_encode(array_values($doc['redirect_uris'])),
        ];

        $pdo = Database::connect();
        if ($update) {
            $pdo->prepare("
                UPDATE oauth_clients SET client_name = ?, client_uri = ?, logo_uri = ?, redirect_uris = ?, metadata_fetched_at = ?
                WHERE tenant_id = ? AND client_id = ?
            ")->execute(array_merge($values, [self::utc(), $tenantId, $clientId]));
        } else {
            $pdo->prepare("
                INSERT INTO oauth_clients (tenant_id, client_id, client_name, client_uri, logo_uri, redirect_uris, token_endpoint_auth_method, is_metadata_document, metadata_fetched_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'none', 1, ?, NOW())
            ")->execute(array_merge([$tenantId, $clientId], $values, [self::utc()]));
        }
    }

    private function authenticateClient(array $params): ?array
    {
        $clientId = null;
        $secret = null;

        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Basic\s+(.+)$/i', $auth, $m)) {
            $decoded = base64_decode($m[1], true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$clientId, $secret] = array_map('urldecode', explode(':', $decoded, 2));
            }
        }

        $clientId = $clientId ?? (isset($params['client_id']) ? (string) $params['client_id'] : null);
        $secret = $secret ?? (isset($params['client_secret']) ? (string) $params['client_secret'] : null);

        if (!$clientId) {
            return null;
        }

        $client = $this->findClient($clientId);
        if (!$client) {
            return null;
        }

        if ($client['token_endpoint_auth_method'] === 'none') {
            return $client; // cliente público: PKCE protege el intercambio
        }

        if (!$secret || !$client['client_secret_hash'] || !hash_equals($client['client_secret_hash'], hash('sha256', $secret))) {
            return null;
        }

        return $client;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function findGrant(int $tenantId, string $clientId, int $userId): ?array
    {
        $stmt = Database::connect()->prepare("
            SELECT * FROM api_keys WHERE tenant_id = ? AND auth_type = 'oauth' AND oauth_client_id = ? AND user_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$tenantId, $clientId, $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function activeGrant(int $apiKeyId, int $tenantId): ?array
    {
        $stmt = Database::connect()->prepare("SELECT * FROM api_keys WHERE id = ? AND tenant_id = ? AND auth_type = 'oauth' AND is_active = 1 LIMIT 1");
        $stmt->execute([$apiKeyId, $tenantId]);
        $grant = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($grant && !self::grantOwnerActive($grant['user_id'] !== null ? (int) $grant['user_id'] : null, $tenantId)) {
            return null;
        }
        return $grant;
    }

    /**
     * La persona que autorizó la conexión sigue existiendo en este tenant
     * (si se borra el usuario, sus conexiones dejan de funcionar).
     */
    public static function grantOwnerActive(?int $userId, int $tenantId): bool
    {
        if (!$userId) {
            return true; // conexiones antiguas sin propietario registrado
        }
        $table = $userId > 0 ? 'admins' : 'users';
        $stmt = Database::connect()->prepare("SELECT 1 FROM {$table} WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([abs($userId), $tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Puede conectar asistentes de IA: permiso específico o gestión de ajustes.
     */
    private static function canAuthorize(): bool
    {
        return userCan('mcp.connect') || userCan('settings.edit');
    }

    private static function intersectMatrix(array $matrix, array $allowed): array
    {
        $out = [];
        foreach ($matrix as $section => $levels) {
            $out[$section] = array_values(array_intersect($levels, $allowed[$section] ?? []));
        }
        return $out;
    }

    private function revokeFamily(string $familyId): void
    {
        Database::connect()->prepare("UPDATE oauth_tokens SET revoked_at = NOW() WHERE family_id = ? AND revoked_at IS NULL")->execute([$familyId]);
    }

    /**
     * Usuario del panel con sesión en este tenant. 'id' se normaliza: positivo para
     * la tabla admins, negativo para users (evita colisiones en api_keys.user_id).
     */
    private function sessionUser(): ?array
    {
        $tenantId = (int) self::tenant()['id'];
        foreach (['admin' => 1, 'user' => -1] as $type => $sign) {
            $candidate = $_SESSION[$type] ?? null;
            if (is_array($candidate) && (int) ($candidate['tenant_id'] ?? 0) === $tenantId && !empty($candidate['id'])) {
                $candidate['id'] = $sign * (int) $candidate['id'];
                return $candidate;
            }
        }
        return null;
    }

    public static function scopeFromPermissions(array $permissions): string
    {
        $levels = [];
        foreach (McpPermissions::summarize($permissions) as $sectionLevels) {
            foreach ($sectionLevels as $level) {
                $levels[$level] = true;
            }
        }
        $scopes = [];
        foreach (array_keys(McpPermissions::LEVELS) as $level) {
            if (isset($levels[$level])) {
                $scopes[] = 'musedock.' . $level;
            }
        }
        return implode(' ', $scopes);
    }

    public static function templateFromScope(string $scope): string
    {
        $scopes = preg_split('/\s+/', trim($scope));
        return match (true) {
            in_array('musedock.delete', $scopes, true)  => 'full',
            in_array('musedock.publish', $scopes, true) => 'editor',
            in_array('musedock.write', $scopes, true)   => 'writer',
            in_array('musedock.read', $scopes, true)    => 'readonly',
            default                                     => 'writer',
        };
    }

    /**
     * El recurso pertenece a este tenant (acepta dominio con o sin www).
     */
    public static function resourceMatches(string $resource): bool
    {
        $parts = parse_url(rtrim($resource, '/'));
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['path'] ?? '') !== '/mcp' || isset($parts['query'])) {
            return false;
        }

        $normalize = fn(string $host) => preg_replace('/^www\./', '', strtolower($host));
        $host = $normalize((string) ($parts['host'] ?? ''));

        return $host === $normalize((string) parse_url(self::origin(), PHP_URL_HOST))
            || $host === $normalize((string) (self::tenant()['domain'] ?? ''));
    }

    public static function isValidRedirectUri(string $uri): bool
    {
        if (strlen($uri) > 500 || str_contains($uri, '#')) {
            return false;
        }

        $parts = parse_url($uri);
        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme === 'https') {
            return !empty($parts['host']);
        }
        if ($scheme === 'http') {
            return in_array(strtolower($parts['host'] ?? ''), ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        }

        // Esquemas privados de apps de escritorio (cursor://, vscode://...)
        return (bool) preg_match('/^[a-z][a-z0-9+.-]{1,40}$/', $scheme)
            && !in_array($scheme, ['javascript', 'data', 'vbscript', 'file', 'about', 'blob', 'ftp', 'ws', 'wss'], true);
    }

    /**
     * Coincidencia exacta; para loopback se ignora el puerto (RFC 8252 §7.3).
     */
    public static function redirectUriMatches(string $uri, array $registered): bool
    {
        if ($uri === '') {
            return false;
        }
        if (in_array($uri, $registered, true)) {
            return true;
        }

        $parts = parse_url($uri);
        if (($parts['scheme'] ?? '') !== 'http' || !in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)) {
            return false;
        }

        $withoutPort = fn(array $p) => ($p['scheme'] ?? '') . '://' . ($p['host'] ?? '') . ($p['path'] ?? '') . (isset($p['query']) ? '?' . $p['query'] : '');
        foreach ($registered as $candidate) {
            $c = parse_url($candidate);
            if ($c && $withoutPort($c) === $withoutPort($parts)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Las caducidades se guardan y comparan en UTC: el CMS cambia la zona horaria
     * de PHP según el tenant y NOW() de la BD puede ir en otra.
     */
    private static function utc(int $offsetSeconds = 0): string
    {
        return gmdate('Y-m-d H:i:s', time() + $offsetSeconds);
    }

    private static function isPast(?string $utcTimestamp, int $graceSeconds = 0): bool
    {
        return !$utcTimestamp || strtotime($utcTimestamp . ' UTC') + $graceSeconds < time();
    }

    private static function safeHttpsUrl($url): ?string
    {
        return is_string($url) && strlen($url) <= 500 && preg_match('#^https://[^\s"<>]+$#i', $url) ? $url : null;
    }

    private function requestParams(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        $raw = (string) file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        parse_str($raw, $form);
        return $form;
    }

    private function redirectError(string $redirectUri, string $error, string $description, ?string $state): void
    {
        $this->redirectTo($redirectUri, array_filter([
            'error'             => $error,
            'error_description' => $description,
            'state'             => $state,
            'iss'               => self::issuer(),
        ], fn($v) => $v !== null));
    }

    private function redirectTo(string $uri, array $query): void
    {
        header('Location: ' . $uri . (str_contains($uri, '?') ? '&' : '?') . http_build_query($query));
        http_response_code(302);
        exit;
    }

    /**
     * CSP propio de la página de autorización (sustituye al global): sin framing,
     * y form-action permite solo nuestro origen y el origen de retorno del cliente.
     */
    private static function consentCsp(?string $redirectUri): void
    {
        $formAction = "'self'";
        if ($redirectUri !== null) {
            $p = parse_url($redirectUri);
            $scheme = strtolower($p['scheme'] ?? '');
            if ($scheme === 'https' && !empty($p['host'])) {
                $formAction .= ' https://' . $p['host'] . (isset($p['port']) ? ':' . (int) $p['port'] : '');
            } elseif ($scheme === 'http') {
                $formAction .= ' http://' . $p['host'] . ':*';
            } elseif (preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme)) {
                $formAction .= ' ' . $scheme . ':';
            }
        }

        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' data: https://cdn.jsdelivr.net; "
            . "img-src 'self' data: https:; connect-src 'self'; object-src 'none'; base-uri 'self'; "
            . "frame-ancestors 'none'; form-action {$formAction}");
    }

    private function bouncePage(string $url): void
    {
        $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        header('Content-Type: text/html; charset=utf-8');
        header('Referrer-Policy: no-referrer');
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<meta http-equiv="refresh" content="0;url=' . $safe . '"><title>Conectando…</title></head>'
            . '<body style="font-family:system-ui,sans-serif;text-align:center;padding:3rem;color:#555">'
            . 'Conectando… <a href="' . $safe . '">Continuar</a></body></html>';
    }

    private function errorPage(string $message, int $status = 400): void
    {
        http_response_code($status);
        echo View::renderTenantAdmin('oauth.error', [
            'title'    => 'No se puede autorizar',
            'message'  => $message,
            'siteName' => self::tenant()['name'] ?? (self::tenant()['domain'] ?? ''),
        ]);
    }

    private function corsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, MCP-Protocol-Version');
        header('Cache-Control: no-store');
    }

    private function json(int $status, $data, ?string $cacheControl = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($cacheControl) {
            header('Cache-Control: ' . $cacheControl);
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Limpieza de códigos/tokens caducados y clientes DCR nunca usados.
     */
    public static function cleanup(): void
    {
        try {
            $pdo = Database::connect();
            $dayAgo = self::utc(-86400);
            $weekAgo = self::utc(-7 * 86400);
            $pdo->prepare("DELETE FROM oauth_auth_codes WHERE expires_at < ?")->execute([$dayAgo]);
            $pdo->prepare("DELETE FROM oauth_tokens WHERE expires_at < ?")->execute([$weekAgo]);
            $pdo->prepare("
                DELETE FROM oauth_clients
                WHERE last_used_at IS NULL AND is_metadata_document = 0 AND created_at < ?
                  AND client_id NOT IN (SELECT oauth_client_id FROM api_keys WHERE auth_type = 'oauth' AND oauth_client_id IS NOT NULL)
            ")->execute([$weekAgo]);
        } catch (\Throwable $e) {
            error_log('OAuth cleanup: ' . $e->getMessage());
        }
    }
}
