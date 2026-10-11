<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;

final class Policy
{
    public const READ = 'musedock-cms:read';
    public const CREATE = 'musedock-cms:drafts:create';
    public const UPDATE = 'musedock-cms:drafts:update';
    public const EDITOR = 'musedock-cms:posts:edit';
    public const POSTS = 'musedock-cms:posts:manage';
    public const TAXONOMY = 'musedock-cms:taxonomy:manage';
    public const COMMENTS = 'musedock-cms:comments:manage';
    public const TRANSLATIONS = 'musedock-cms:translations:manage';
    public const MEDIA = 'musedock-cms:media:manage';
    public const APPROVE = 'musedock-cms:requests:approve';
    public const COPY = 'musedock-cross-publisher:drafts:copy';
    public const IMPORT = 'musedock-news-aggregator:drafts:import';
    public static function scopes(string $resource): array
    {
        $scopes = [self::READ, self::CREATE, self::UPDATE, self::EDITOR, self::POSTS, self::TAXONOMY, self::COMMENTS, self::TRANSLATIONS, self::MEDIA, self::APPROVE];
        if ($resource === 'plugin:cross-publisher') $scopes[] = self::COPY;
        if ($resource === 'plugin:news-aggregator') $scopes[] = self::IMPORT;
        return $scopes;
    }
    public static function label(string $scope): string
    {
        return match($scope) { self::READ=>'Consultar artículos, categorías y etiquetas', self::CREATE=>'Crear borradores', self::UPDATE=>'Editar borradores', self::EDITOR=>'Editar todas las opciones de borradores', self::POSTS=>'Publicar, programar y editar artículos publicados', self::TAXONOMY=>'Crear y editar categorías y etiquetas', self::COMMENTS=>'Consultar y moderar comentarios', self::TRANSLATIONS=>'Consultar y guardar traducciones', self::MEDIA=>'Consultar, subir y generar imágenes', self::APPROVE=>'Aprobar solicitudes propias desde MCP', self::COPY=>'Copiar artículos a otros sitios como borradores', self::IMPORT=>'Importar noticias como borradores', default=>$scope };
    }
    public static function site(string $resource): ?int
    {
        if ($resource === 'main') return 0;
        if (preg_match('/^tenant:([1-9][0-9]*)$/D', $resource, $m)) return (int)$m[1];
        if (in_array($resource, ['plugin:cross-publisher','plugin:news-aggregator'], true)) return null;
        throw new RuntimeException('Recurso MCP desconocido.');
    }
    public static function callback(string $uri): bool
    {
        if (strlen($uri) > 500 || preg_match('/[\\\\\s\x00-\x1f\x7f*]/', $uri)) return false;
        $p = parse_url($uri);
        return $p && ($p['scheme'] ?? '') === 'https' && !empty($p['host']) && !isset($p['fragment'])
            && !isset($p['user']) && !isset($p['pass']);
    }
    public static function challenge(string $verifier): string { return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='); }
    public static function requestedScopes(string $scope, array $allowed): array
    {
        $requested = array_values(array_unique(preg_split('/\s+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY)));
        if (!$requested) $requested = [self::READ];
        if (array_diff($requested, $allowed) || !in_array(self::READ, $requested, true)) throw new RuntimeException('Ámbitos no autorizados.');
        return $requested;
    }
    public static function allows(array $client, array $grant, string $scope): bool
    {
        return in_array($scope, $client['scopes'], true) && in_array($scope, $grant['scopes'], true);
    }
    public static function active(array $resource, array $client, array $grant): bool
    {
        return !empty($resource['enabled']) && !empty($client['enabled']) && !empty($grant['active'])
            && $client['resource'] === $resource['id'] && $grant['resource'] === $resource['id'] && $grant['client'] === $client['id']
            && $grant['revision'] === $client['revision'] && $grant['resource_revision'] === $resource['revision']
            && !array_diff($grant['scopes'], $client['scopes']);
    }
    public static function fingerprint(array $plan): string
    {
        $sort = function (&$a) use (&$sort) { if (!is_array($a)) return; if (!array_is_list($a)) ksort($a); foreach ($a as &$v) $sort($v); };
        $sort($plan); return hash('sha256', Store::json($plan));
    }
}
