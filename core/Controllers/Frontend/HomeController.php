<?php

namespace Screenart\Musedock\Controllers\Frontend;


use Screenart\Musedock\View;
use Screenart\Musedock\Models\Page;
use Screenart\Musedock\Models\PageTranslation;
use Screenart\Musedock\Database;
use Screenart\Musedock\Models\PageMeta; // AÑADIDO para poder usar PageMeta

class HomeController
{
    /**
     * Muestra la página de inicio configurada o un contenido por defecto.
     */
    public function index()
    {
        error_log("HomeController: Accediendo a index()");

        $homepage = null;
        $homepageData = null;

        // Obtener tenant actual para filtrar correctamente
        $tenantId = \Screenart\Musedock\Services\TenantManager::currentTenantId();

        try {
            error_log("HomeController: Intentando buscar homepage para tenant_id: " . ($tenantId ?? 'NULL (master)'));

            // Query con filtro correcto por tenant
            if ($tenantId !== null) {
                $result = Database::query(
                    "SELECT * FROM pages WHERE is_homepage = :is_home AND status = :status AND tenant_id = :tenant_id LIMIT 1",
                    [':is_home' => 1, ':status' => 'published', ':tenant_id' => $tenantId]
                );
            } else {
                $result = Database::query(
                    "SELECT * FROM pages WHERE is_homepage = :is_home AND status = :status AND tenant_id IS NULL LIMIT 1",
                    [':is_home' => 1, ':status' => 'published']
                );
            }

            $homepageData = $result->fetch(\PDO::FETCH_ASSOC);
            error_log("HomeController: Datos crudos encontrados: " . ($homepageData ? json_encode(['id' => $homepageData['id']]) : 'NO'));

            if ($homepageData) {
                $homepage = new Page($homepageData);
                error_log("HomeController: Objeto Page instanciado, ID: {$homepage->id}");
            }

        } catch (\Throwable $e) {
            error_log("HomeController: ¡ERROR FATAL AL BUSCAR/INSTANCIAR HOMEPAGE!: " . $e->getMessage());
            $homepage = null;
        }

        if ($homepage instanceof Page) {
            $currentLocale = detectLanguage();
            $translation = $homepage->translation($currentLocale);

            $displayData = new \stdClass();
            $displayData->title = $translation->title ?? $homepage->title;

            // NO procesar shortcodes aquí, dejar que las vistas lo hagan con apply_filters('the_content')
            $displayData->content = $translation->content ?? $homepage->content ?? '';

            $displayData->seo_title = $translation->seo_title ?? $homepage->seo_title;
            $displayData->seo_description = $translation->seo_description ?? $homepage->seo_description;
            $displayData->seo_keywords = $translation->seo_keywords ?? $homepage->seo_keywords;
            $displayData->seo_image = $translation->seo_image ?? $homepage->seo_image;
            $displayData->canonical_url = $translation->canonical_url ?? $homepage->canonical_url;
            $displayData->robots_directive = $translation->robots_directive ?? $homepage->robots_directive;
            $displayData->twitter_title = $translation->twitter_title ?? $homepage->twitter_title;
            $displayData->twitter_description = $translation->twitter_description ?? $homepage->twitter_description;
            $displayData->twitter_image = $translation->twitter_image ?? $homepage->twitter_image;

            // === NUEVO: Obtener plantilla asignada ===
            $templateName = PageMeta::getMeta($homepage->id, 'page_template', 'page.blade.php');
            $templateName = str_replace('.blade.php', '', $templateName);
            error_log("HomeController: Usando plantilla '{$templateName}' para renderizar homepage.");
            // ========================================

            // === NUEVO: Cargar personalizaciones de página ===
            $pageCustomizations = $this->loadPageCustomizations($homepage->id);
            // ================================================

            try {
                return View::renderTheme($templateName, [
                    'page' => $homepage,
                    'translation' => $displayData,
                    'customizations' => $pageCustomizations
                ]);
            } catch (\Exception $e) {
                error_log("HomeController: Error al renderizar vista '{$templateName}': " . $e->getMessage());
                http_response_code(500);
                return "Error al cargar página (template '{$templateName}' ausente).";
            }
        } else {
            error_log("HomeController: No se encontró homepage para este dominio.");
            http_response_code(404);

            // Mostrar mensaje simple sin fallback de contenido
            return "<!DOCTYPE html>
<html lang=\"es\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Sitio en construcción - " . setting('site_name', 'MuseDock') . "</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; padding: 2rem; }
        h1 { color: #333; margin-bottom: 1rem; }
        p { color: #666; }
        .logo { font-size: 3rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"logo\">🚧</div>
        <h1>Sitio en construcción</h1>
        <p>Este dominio aún no tiene contenido configurado.</p>
        <p><small>Accede al panel de administración para crear tu primera página.</small></p>
    </div>
</body>
</html>";
        }
    }

    /**
     * Carga todas las personalizaciones de página desde la tabla page_meta y la tabla pages
     *
     * @param int $pageId ID de la página
     * @return object Objeto con todas las personalizaciones
     */
    private function loadPageCustomizations($pageId)
    {
        // Crear un objeto para almacenar las personalizaciones
        $customizations = new \stdClass();

        // Obtener la página para acceder a los datos de la tabla directamente
        $page = Page::find($pageId);

        // === Opciones del slider ===
        // Para show_slider, verificar primero en page_meta y luego en la tabla pages
        $show_slider_meta = PageMeta::getMeta($pageId, 'show_slider', null);
        $show_slider_value = ($show_slider_meta !== null) ? $show_slider_meta : ($page->show_slider ?? 0);

        // Forzar a booleano estricto para asegurar comparación correcta en la vista
        $customizations->show_slider = ($show_slider_value == 1 || $show_slider_value === true || $show_slider_value === "1") ? true : false;

        // Para hide_title, similar a show_slider
        $hide_title_meta = PageMeta::getMeta($pageId, 'hide_title', null);
        $hide_title_value = ($hide_title_meta !== null) ? $hide_title_meta : ($page->hide_title ?? 0);
        $customizations->hide_title = ($hide_title_value == 1 || $hide_title_value === true || $hide_title_value === "1") ? true : false;

        // Otras opciones
        $customizations->slider_image = PageMeta::getMeta($pageId, 'slider_image', null);
        if (empty($customizations->slider_image)) {
            $customizations->slider_image = $page->slider_image ?? 'themes/default/img/hero/contact_hero.jpg';
        }

        $customizations->slider_title = PageMeta::getMeta($pageId, 'slider_title', null);
        if (empty($customizations->slider_title)) {
            $customizations->slider_title = $page->slider_title;
        }

        $customizations->slider_content = PageMeta::getMeta($pageId, 'slider_content', null);
        if (empty($customizations->slider_content)) {
            $customizations->slider_content = $page->slider_content;
        }

        $customizations->slider_button_text = PageMeta::getMeta($pageId, 'slider_button_text', null);
        $customizations->slider_button_url = PageMeta::getMeta($pageId, 'slider_button_url', '#');

        $customizations->container_class = PageMeta::getMeta($pageId, 'container_class', 'container py-4');
        $customizations->content_class = PageMeta::getMeta($pageId, 'content_class', 'page-content-wrapper');

        return $customizations;
    }
}
