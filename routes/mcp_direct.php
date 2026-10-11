<?php
use Screenart\Musedock\Route;
use Screenart\Musedock\Services\Mcp\Direct\Endpoint;
use Screenart\Musedock\Services\Mcp\Direct\Routing;

foreach (['cross-publisher','news-aggregator'] as $slug) {
    foreach (['get','post','options'] as $verb) Route::$verb('/mcp/'.$slug, function () use ($slug) {
        if (!empty($GLOBALS['tenant']['id'])) { http_response_code(404); return; }
        (new Endpoint('plugin:'.$slug))->mcp();
    });
    Route::get('/.well-known/oauth-protected-resource/mcp/'.$slug, function () use ($slug) {
        if (!empty($GLOBALS['tenant']['id'])) { http_response_code(404); return; }
        (new Endpoint('plugin:'.$slug))->metadata();
    });
}
foreach (['cms','cross-publisher','news-aggregator'] as $slug) {
    $resolve = function () use ($slug) { return new Endpoint(Routing::id($slug)); };
    Route::get('/.well-known/oauth-authorization-server/mcp-auth/'.$slug, function () use ($resolve) {
        try { $resolve()->metadata(true); } catch (Throwable $e) { http_response_code(404); }
    });
    foreach (['get','post'] as $verb) Route::$verb('/mcp-auth/'.$slug.'/authorize', function () use ($resolve) {
        try { $resolve()->authorize(); } catch (Throwable $e) { http_response_code(404); }
    });
    foreach (['token','revoke'] as $action) Route::post('/mcp-auth/'.$slug.'/'.$action, function () use ($resolve,$action) {
        try { $resolve()->machine($action); } catch (Throwable $e) { http_response_code(404); }
    });
}
