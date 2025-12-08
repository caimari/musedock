<?php

namespace Screenart\Musedock\Helpers;

use Screenart\Musedock\Database;

class MenuHelper
{
    private static $menuCache = [];

    // --- Mantenemos renderMenu como estaba si aún la usas en otro lugar ---
    /**
     * Renderiza un menú por su ubicación (versión original si es necesaria)
     *
     * @param string $location Ubicación del menú (nav, footer, sidebar)
     * @param string|null $locale Idioma (es, en, fr, etc.)
     * @return string HTML del menú
     */
    public static function renderMenu($location, $locale = null)
    {
        $cacheKey = 'std_' . $location . '_' . ($locale ?? setting('language', 'es'));
        if (isset(self::$menuCache[$cacheKey])) {
            return self::$menuCache[$cacheKey];
        }

        if (!$locale) $locale = setting('language', 'es');

        $menu = self::getMenuData($location, $locale);
        if (!$menu) return '';

        $items = self::getMenuItemsData($menu['id']);
        $tree = self::buildMenuTree($items);

        // Usamos una versión simple del renderizado recursivo para este método
        $html = self::renderMenuItemsSimple($tree);
        self::$menuCache[$cacheKey] = $html;
        return $html;
    }

    // --- Nueva función de renderizado simple (usada por renderMenu original) ---
    private static function renderMenuItemsSimple($items, $depth = 0)
    {
        if (empty($items)) return '';
        $className = ($depth === 0) ? 'menu' : 'sub-menu'; // Clases básicas
        $html = '<ul class="' . $className . '">';
        foreach ($items as $item) {
            $hasChildren = !empty($item['children']);
            $classes = [];
            if ($hasChildren) $classes[] = 'has-children';
            $currentUrl = $_SERVER['REQUEST_URI'];
            if ($currentUrl === $item['link']) $classes[] = 'current';
            $classAttribute = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';

            $html .= '<li' . $classAttribute . '>';
            $html .= '<a href="' . e($item['link']) . '" title="' . e($item['title']) . '">' . e($item['title']) . '</a>';
            if ($hasChildren) {
                $html .= self::renderMenuItemsSimple($item['children'], $depth + 1);
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }


    // --- renderCustomMenu REESCRITA ---
    /**
     * Renderiza un menú con clases y estructura totalmente personalizadas.
     *
     * @param string $location Ubicación del menú (nav, footer, sidebar)
     * @param string|null $locale Idioma (es, en, fr, etc.)
     * @param array $options Opciones de renderizado:
     *                       'ul_id' => ID para el UL principal (ej: 'navigation')
     *                       'nav_class' => Clase para el UL principal (ej: 'navigation')
     *                       'li_class' => Clase base para todos los LI
     *                       'a_class' => Clase base para todos los A
     *                       'submenu_class' => Clase para los UL anidados (ej: 'submenu')
     *                       'li_active_class' => Clase para LI activo (opcional, defecto 'current')
     *                       'li_parent_class' => Clase para LI con hijos (opcional, defecto 'has-children')
     * @return string HTML del menú
     */
    public static function renderCustomMenu($location, $locale = null, $options = [])
    {
        // --- Obtención de datos (similar a antes) ---
        $cacheKey = 'custom_' . $location . '_' . ($locale ?? setting('language', 'es')) . '_' . md5(serialize($options));
        if (isset(self::$menuCache[$cacheKey])) {
            return self::$menuCache[$cacheKey];
        }

        if (!$locale) $locale = setting('language', 'es');

        $menu = self::getMenuData($location, $locale);
        if (!$menu) return '';

        $items = self::getMenuItemsData($menu['id']);
        $tree = self::buildMenuTree($items);

        // --- Renderizado con opciones personalizadas ---

        // Preparar atributos del UL principal
        $ulIdAttr = isset($options['ul_id']) ? ' id="' . e($options['ul_id']) . '"' : '';
        $ulClassAttr = isset($options['nav_class']) && $options['nav_class'] ? ' class="' . e($options['nav_class']) . '"' : ''; // Solo si no está vacía

        // Iniciar HTML con el UL principal
        $html = "<ul{$ulIdAttr}{$ulClassAttr}>";

        // Llamar a la función recursiva pasando las opciones
        $html .= self::renderMenuItemsRecursive($tree, $options);

        // Cerrar el UL principal
        $html .= '</ul>';

        self::$menuCache[$cacheKey] = $html;
        return $html;
    }

    // --- NUEVA Función recursiva para renderizar items con opciones ---
    private static function renderMenuItemsRecursive(array $items, array $options): string
    {
        if (empty($items)) {
            return '';
        }

        $html = '';
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/'; // URL actual para la clase 'active'

        // Clases por defecto si no se especifican en $options
        $baseLiClass = $options['li_class'] ?? '';
        $baseAClass = $options['a_class'] ?? '';
        $submenuUlClass = $options['submenu_class'] ?? 'submenu'; // Usa la opción o 'submenu'
        $activeLiClass = $options['li_active_class'] ?? 'current'; // Clase para LI activo
        $parentLiClass = $options['li_parent_class'] ?? 'has-children'; // Clase para LI con hijos

        foreach ($items as $item) {
            $hasChildren = !empty($item['children']);
            $link = $item['link'] ?? '#';
            $title = $item['title'] ?? '';

            // Construir clases del LI
            $liClasses = [$baseLiClass]; // Empezar con la clase base
            if ($hasChildren) {
                $liClasses[] = $parentLiClass;
            }
            // Comprobar si es la página actual
            if ($currentUrl === $link) {
                $liClasses[] = $activeLiClass;
            }
            // Limpiar clases vacías y unir
            $finalLiClass = trim(implode(' ', array_filter($liClasses)));
            $liClassAttr = $finalLiClass ? ' class="' . e($finalLiClass) . '"' : '';

            // Construir clases del A
            $aClasses = [$baseAClass];
            // Limpiar clases vacías y unir
            $finalAClass = trim(implode(' ', array_filter($aClasses)));
            $aClassAttr = $finalAClass ? ' class="' . e($finalAClass) . '"' : '';


            // Renderizar LI y A
            $html .= "<li{$liClassAttr}>";
            $html .= '<a href="' . e($link) . '" title="' . e($title) . '"' . $aClassAttr . '>' . e($title) . '</a>';

            // Si tiene hijos, renderizar submenú
            if ($hasChildren) {
                $html .= '<ul class="' . e($submenuUlClass) . '">'; // Usa la clase de submenu de las opciones
                // Llamada RECURSIVA pasando las MISMAS opciones
                $html .= self::renderMenuItemsRecursive($item['children'], $options);
                $html .= '</ul>';
            }

            $html .= '</li>';
        }

        return $html;
    }


    // --- Funciones auxiliares de datos (separadas para claridad) ---
    private static function getMenuData($location, $locale)
    {
        $pdo = Database::connect();

        // Obtener tenant_id actual (si existe)
        $tenantId = null;
        if (class_exists('\\Screenart\\Musedock\\Services\\TenantManager')) {
            $tenant = \Screenart\Musedock\Services\TenantManager::current();
            if ($tenant) {
                $tenantId = $tenant['id'] ?? null;
            }
        }

        // Si es un tenant, buscar menú del tenant. Si es main site (tenant_id = null), buscar menú principal
        if ($tenantId) {
            // Para tenants: solo buscar menus con su tenant_id
            $stmt = $pdo->prepare("
                SELECT m.id, mt.title
                FROM site_menus m
                LEFT JOIN site_menu_translations mt ON m.id = mt.menu_id AND mt.locale = ?
                WHERE m.location = ? AND m.tenant_id = ?
                ORDER BY m.id DESC
                LIMIT 1
            ");
            $stmt->execute([$locale, $location, $tenantId]);
        } else {
            // Para main site: solo buscar menus sin tenant_id (NULL)
            $stmt = $pdo->prepare("
                SELECT m.id, mt.title
                FROM site_menus m
                LEFT JOIN site_menu_translations mt ON m.id = mt.menu_id AND mt.locale = ?
                WHERE m.location = ? AND m.tenant_id IS NULL
                ORDER BY m.id DESC
                LIMIT 1
            ");
            $stmt->execute([$locale, $location]);
        }

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private static function getMenuItemsData($menuId)
    {
        $pdo = Database::connect();

        // Obtener tenant_id actual (si existe)
        $tenantId = null;
        if (class_exists('\\Screenart\\Musedock\\Services\\TenantManager')) {
            $tenant = \Screenart\Musedock\Services\TenantManager::current();
            if ($tenant) {
                $tenantId = $tenant['id'] ?? null;
            }
        }

        // Filtrar items por tenant_id para seguridad adicional
        if ($tenantId) {
            $stmt = $pdo->prepare("
                SELECT * FROM site_menu_items
                WHERE menu_id = ? AND tenant_id = ?
                ORDER BY depth ASC, sort ASC
            ");
            $stmt->execute([$menuId, $tenantId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM site_menu_items
                WHERE menu_id = ? AND tenant_id IS NULL
                ORDER BY depth ASC, sort ASC
            ");
            $stmt->execute([$menuId]);
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Construye una estructura jerárquica a partir de elementos planos
     */
    private static function buildMenuTree($items, $parentId = null)
    {
        $branch = [];
        foreach ($items as $key => $item) {
            // Comprobar si el parent_id coincide
             $itemParent = $item['parent'] ?? null; // Asegurarse que existe la clave
             // Convertir a null si es una cadena vacía o 0 para consistencia
             if ($itemParent === '' || $itemParent === 0) {
                 $itemParent = null;
             }

             $match = ($parentId === null && $itemParent === null) || // Nivel raíz
                      ($parentId !== null && (string)$itemParent == (string)$parentId); // Subnivel (comparar como string por si acaso)


            if ($match) {
                 // Evitar re-procesar el mismo item si buildMenuTree se llama múltiples veces con los mismos datos
                 // unset($items[$key]); // Descomentar si hay riesgo de bucles infinitos con datos mal formados

                // Recursivamente buscar hijos para ESTE item
                $children = self::buildMenuTree($items, $item['id']);
                if (!empty($children)) {
                    $item['children'] = $children;
                } else {
                     $item['children'] = []; // Asegurar que la clave 'children' siempre existe
                }
                $branch[] = $item;
            }
        }
        // Ordenar la rama actual por el campo 'sort' si existe
         usort($branch, function($a, $b) {
             return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
         });
        return $branch;
    }

    /**
     * Limpia la caché de menús
     */
    public static function clearCache()
    {
        self::$menuCache = [];
    }

    // Helper para escapar HTML (si no tienes uno global)
    private static function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}