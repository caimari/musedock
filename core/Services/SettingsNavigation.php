<?php
namespace Screenart\Musedock\Services;

final class SettingsNavigation
{
    public static function items(bool $tenant = false): array
    {
        $base = $tenant ? '/' . admin_path() : '/musedock';
        $definitions = [
            ['general', 'General', 'gear', '/settings'],
            ['reading', 'Lectura', 'book', '/settings/reading'],
            ['seo', 'SEO y Social', 'search', '/settings/seo'],
        ];
        // Acceso directo al módulo Search Console (solo superadmin, si el módulo está instalado)
        if (!$tenant && defined('APP_ROOT') && is_dir(APP_ROOT . '/modules/search-console')) {
            $definitions[] = ['search-console', 'Search Console', 'graph-up', '/search-console'];
        }
        $definitions = array_merge($definitions, [
            ['email', 'Email (SMTP)', 'envelope', '/settings/email'],
            ['cookies', 'Cookies', 'shield-check', '/settings/cookies'],
            ['storage', 'Almacenamiento', 'hdd', '/settings/storage'],
        ]);
        if ($tenant) $definitions[] = ['security', 'Seguridad', 'shield-lock', '/settings/security'];
        else $definitions[] = ['advanced', 'Avanzado', 'sliders', '/settings/advanced'];
        $definitions[] = ['mcp', '(MCP) Conexiones IA', 'robot', $tenant ? '/mcp/direct' : '/settings/mcp'];
        $definitions[] = ['api-keys', 'API Keys', 'key', '/settings/api-keys'];
        if (!$tenant) {
            $definitions[] = ['tenant-defaults', 'Defaults tenants', 'building-gear', '/settings/tenant-defaults'];
            $definitions[] = ['acme-assistant', 'Certificados', 'patch-check', '/settings/acme-assistant'];
            $definitions[] = ['dns-accounts', 'Cuentas DNS', 'cloud', '/settings/dns-accounts'];
            $definitions[] = ['backups', 'Backups', 'database', '/settings/backups'];
        }
        return array_map(static fn($item) => [
            'key' => $item[0], 'label' => $item[1], 'icon' => 'bi-' . $item[2], 'url' => $base . $item[3],
        ], $definitions);
    }

    public static function synchronize(array $menus, bool $tenant = false): array
    {
        foreach ($menus as $menuKey => &$menu) {
            if ($menuKey !== 'settings' && ($menu['slug'] ?? '') !== 'settings' && ($menu['title'] ?? '') !== 'Ajustes') continue;
            $children = $menu['children'] ?? [];
            $ordered = [];
            foreach (self::items($tenant) as $item) {
                $match = null;
                foreach ($children as $key => $child) {
                    if (($child['url'] ?? '') === $item['url']) {
                        $match = $child;
                        unset($children[$key]);
                        break;
                    }
                }
                // Tenant menus are already filtered by permissions: never restore hidden entries.
                if ($tenant && $match === null) continue;
                $ordered[$item['key']] = array_merge($match ?? [
                    'permission' => $menu['permission'] ?? null,
                ], ['title' => $item['label'], 'url' => $item['url'], 'icon' => $item['icon'], 'icon_type' => 'bi']);
            }
            $menu['children'] = array_merge($ordered, $children);
        }
        unset($menu);
        return $menus;
    }
}
