<?php
/**
 * MuseDock CMS — Servidor MCP remoto por tenant
 *
 *   POST https://{dominio-del-tenant}/mcp
 *   Authorization: Bearer mdk_...
 *
 * Petición sin estado (sin sesión PHP): ver MUSEDOCK_STATELESS_REQUEST en public/index.php.
 * También los endpoints OAuth 2.1 (metadata, register, token, revoke).
 */

use Screenart\Musedock\Route;
use Screenart\Musedock\Services\Mcp\McpServer;
use Screenart\Musedock\Services\Mcp\OAuthServer;

Route::post('/mcp', function () {
    (new McpServer())->handleHttp();
});

Route::get('/mcp', function () {
    (new McpServer())->handleHttp();
});

Route::options('/mcp', function () {
    (new McpServer())->handleHttp();
});

// ============================================================================
// OAuth 2.1 (Claude.ai, ChatGPT, Claude Code...) — ver OAuthServer
// ============================================================================

$__oauthPreflight = function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, MCP-Protocol-Version');
    http_response_code(204);
};

foreach (['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp'] as $__path) {
    Route::get($__path, fn() => (new OAuthServer())->protectedResourceMetadata());
    Route::options($__path, $__oauthPreflight);
}
foreach (['/.well-known/oauth-authorization-server', '/.well-known/openid-configuration'] as $__path) {
    Route::get($__path, fn() => (new OAuthServer())->authorizationServerMetadata());
    Route::options($__path, $__oauthPreflight);
}

Route::post('/oauth/register', fn() => (new OAuthServer())->register());
Route::get('/oauth/authorize', fn() => (new OAuthServer())->authorize());
Route::post('/oauth/authorize', fn() => (new OAuthServer())->authorize());
Route::post('/oauth/token', fn() => (new OAuthServer())->token());
Route::post('/oauth/revoke', fn() => (new OAuthServer())->revoke());
foreach (['/oauth/register', '/oauth/token', '/oauth/revoke'] as $__path) {
    Route::options($__path, $__oauthPreflight);
}
