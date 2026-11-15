<?php
namespace Screenart\Musedock\Services\Mcp\Direct;

use RuntimeException;

final class Policy
{
    public const PAGES_ARCHIVE='musedock-cms:pages:archive';
    public const COLLECTIONS_ARCHIVE='musedock-cms:collections:archive';
    public const PAGES_READ='musedock-cms:pages:read';
    public const PAGES_EDIT='musedock-cms:pages:edit';
    public const PAGES_PUBLISH='musedock-cms:pages:publish';
    public const COLLECTIONS_READ='musedock-cms:collections:read';
    public const COLLECTIONS_EDIT='musedock-cms:collections:edit';
    public const COLLECTIONS_PUBLISH='musedock-cms:collections:publish';
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
        $scopes = [self::PAGES_ARCHIVE,self::COLLECTIONS_ARCHIVE,self::PAGES_READ,self::PAGES_EDIT,self::PAGES_PUBLISH,self::COLLECTIONS_READ,self::COLLECTIONS_EDIT,self::COLLECTIONS_PUBLISH,self::READ, self::CREATE, self::UPDATE, self::EDITOR, self::POSTS, self::TAXONOMY, self::COMMENTS, self::TRANSLATIONS, self::MEDIA, self::APPROVE];
        if ($resource === 'plugin:cross-publisher') $scopes[] = self::COPY;
        if (self::news($resource)) $scopes[] = self::IMPORT;
        return $scopes;
    }
    public static function label(string $scope): string
    {
        return match($scope) { self::PAGES_ARCHIVE=>'Archivar páginas',self::COLLECTIONS_ARCHIVE=>'Archivar colecciones', self::PAGES_READ=>'Consultar páginas',self::PAGES_EDIT=>'Crear y editar páginas',self::PAGES_PUBLISH=>'Publicar y despublicar páginas',self::COLLECTIONS_READ=>'Consultar colecciones',self::COLLECTIONS_EDIT=>'Organizar colecciones y capítulos',self::COLLECTIONS_PUBLISH=>'Publicar y despublicar colecciones', self::READ=>'Consultar contenido autorizado', self::CREATE=>'Crear borradores', self::UPDATE=>'Editar borradores', self::EDITOR=>'Editar todas las opciones de borradores', self::POSTS=>'Publicar, programar y editar artículos publicados', self::TAXONOMY=>'Crear y editar categorías y etiquetas', self::COMMENTS=>'Consultar y moderar comentarios', self::TRANSLATIONS=>'Consultar y guardar traducciones', self::MEDIA=>'Consultar, subir y generar imágenes', self::APPROVE=>'Aprobar solicitudes propias desde MCP', self::COPY=>'Copiar artículos a otros sitios como borradores y encargar resúmenes editoriales', self::IMPORT=>(function_exists('detectLanguage') && detectLanguage()==='en' ? 'Manage news tips and evidence' : 'Gestionar avisos y documentación de noticias'), default=>$scope };
    }
    public static function news(string $resource): bool
    {
        return $resource === 'plugin:news-aggregator' || (bool) preg_match('/^tenant:[1-9][0-9]*:plugin:news-aggregator$/D', $resource);
    }
    public static function tenantNews(int $tenant): string { return 'tenant:'.$tenant.':plugin:news-aggregator'; }
    public static function plugin(string $resource): ?string
    {
        if (self::news($resource)) return 'news-aggregator';
        return $resource === 'plugin:cross-publisher' ? 'cross-publisher' : null;
    }
    public static function site(string $resource): ?int
    {
        if ($resource === 'main') return 0;
        if (preg_match('/^tenant:([1-9][0-9]*)(?::plugin:news-aggregator)?$/D', $resource, $m)) return (int)$m[1];
        if (in_array($resource, ['plugin:cross-publisher','plugin:news-aggregator'], true)) return null;
        throw new RuntimeException('Recurso MCP desconocido.');
    }
    public static function clientCallback(string $provider, string $custom = ''): string
    {
        $uri = match ($provider) {
            'chatgpt' => 'https://chatgpt.com/connector_platform_oauth_redirect',
            'claude' => 'https://claude.ai/api/mcp/auth_callback',
            'custom' => trim($custom),
            default => throw new RuntimeException('Asistente desconocido.'),
        };
        if (!self::callback($uri)) throw new RuntimeException('Indica una dirección de retorno HTTPS exacta.');
        return $uri;
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
