<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;

/**
 * Traducciones de las categorías del blog (nombre, descripción y SEO por idioma).
 *
 * El slug no se traduce (/category/x y /en/category/x). Solo se aplican con los listados por idioma activados
 * (ajuste blog_language_lists) y cuando la página se ve en un idioma distinto del por defecto del sitio: sin
 * eso, o sin fila de traducción, la categoría conserva su nombre. No toca la base de datos: cambia los objetos
 * en memoria antes de pintar la página.
 */
final class CategoryTranslation
{
    /** @var array<string, array<int, object|false>> idioma => categoría => fila (false = sin traducción) */
    private static array $cache = [];

    /** Idioma al que traducir en esta petición; null si no hay que traducir nada. */
    public static function activeLocale(): ?string
    {
        if (!function_exists('blog_language_lists_enabled') || !blog_language_lists_enabled()) {
            return null;
        }
        $locale = detectLanguage();

        return $locale !== site_default_language() ? $locale : null;
    }

    /** @return array<int, object> categoría => traducción (vacío si no hay ninguna o falta la tabla) */
    public static function forLocale(array $ids, string $locale): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $missing = array_values(array_filter($ids, static fn (int $i): bool => !isset(self::$cache[$locale][$i])));
        if ($missing) {
            $found = [];
            try {
                $in = implode(',', array_fill(0, count($missing), '?'));
                $stmt = Database::connect()->prepare(
                    "SELECT category_id, name, description, seo_title, seo_description
                     FROM blog_category_translations WHERE locale = ? AND category_id IN ({$in})"
                );
                $stmt->execute(array_merge([$locale], $missing));
                foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $row) {
                    $found[(int) $row->category_id] = $row;
                }
            } catch (\Throwable $e) {
                // Sin la tabla (instalación sin migrar): las categorías se quedan como están
            }
            foreach ($missing as $i) {
                self::$cache[$locale][$i] = $found[$i] ?? false;
            }
        }
        $out = [];
        foreach ($ids as $i) {
            if (!empty(self::$cache[$locale][$i])) {
                $out[$i] = self::$cache[$locale][$i];
            }
        }

        return $out;
    }

    /**
     * Pone el nombre, la descripción y el SEO traducidos a unas categorías (objetos o modelos).
     *
     * @param iterable<object> $categories
     */
    public static function apply(iterable $categories, ?string $locale = null): void
    {
        $locale ??= self::activeLocale();
        if ($locale === null) {
            return;
        }
        $list = [];
        foreach ($categories as $c) {
            if (is_object($c) && (int) ($c->id ?? 0) > 0) {
                $list[] = $c;
            }
        }
        $translations = self::forLocale(array_map(static fn ($c): int => (int) $c->id, $list), $locale);
        foreach ($list as $c) {
            $t = $translations[(int) $c->id] ?? null;
            if (!$t) {
                continue;
            }
            $c->name = $t->name;
            if ($t->description !== null && $t->description !== '') {
                $c->description = $t->description;
            }
            if (!empty($t->seo_title)) {
                $c->seo_title = $t->seo_title;
            }
            if (!empty($t->seo_description)) {
                $c->seo_description = $t->seo_description;
            }
        }
    }
}
