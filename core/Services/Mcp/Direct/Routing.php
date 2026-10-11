<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

final class Routing
{
    public static function useDirect(): bool
    {
        if (Endpoint::siteResource()==='main') return true;
        $store=new Store(); $r=$store->resource(Endpoint::siteResource());
        // Preparing clients while the new service is gated must not interrupt legacy tenants.
        if (!$r) return false;
        if (!empty($r['managed'])) return true;
        if (!empty($r['enabled']) && filter_var(\Screenart\Musedock\Env::get('MCP_CMS_DIRECT_ENABLED',false),FILTER_VALIDATE_BOOLEAN)) {
            $r['managed']=true; $store->putResource($r); return true;
        }
        return false;
    }
    public static function resourceMetadata(): void
    {
        if (self::useDirect()) (new Endpoint(Endpoint::siteResource()))->metadata();
        else (new \Screenart\Musedock\Services\Mcp\OAuthServer())->protectedResourceMetadata();
    }
    public static function id(string $slug): string
    {
        if ($slug==='cms') return Endpoint::siteResource();
        if (!empty($GLOBALS['tenant']['id'])) throw new \RuntimeException('Plugins editoriales no disponibles en este dominio.');
        if (!in_array($slug,['cross-publisher','news-aggregator'],true)) throw new \RuntimeException('Recurso desconocido.');
        return 'plugin:'.$slug;
    }
}
