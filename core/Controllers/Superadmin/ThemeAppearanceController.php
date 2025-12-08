<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Database; 
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Security\PermissionManager;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Models\ThemeOption; 

class ThemeAppearanceController
{
    use RequiresPermission;

    /**
     * Muestra la página de personalización de apariencia para un tema y tenant (opcional).
     */
    public function index($slug, $tenantId = null) // $tenantId será null para opciones globales
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        // --- Verificar y obtener la ruta del tema (tu lógica es correcta) ---
        $themePath = null;
        if ($tenantId) {
            $tryPath = realpath(__DIR__ . "/../../../themes/tenant_{$tenantId}/{$slug}");
            if ($tryPath && is_dir($tryPath)) { $themePath = $tryPath; }
        }
        // Si no hay tema de tenant o no existe, usar el global
        if (!$themePath) {
             $tryPath = realpath(__DIR__ . "/../../../themes/{$slug}");
             if ($tryPath && is_dir($tryPath)) { $themePath = $tryPath; }
        }

        if (!$themePath) {
            flash('error', 'El tema no existe o no se pudo encontrar.');
            header('Location: /musedock/themes'); // O ruta apropiada
            exit;
        }
        // --- Fin Verificar Ruta Tema ---

        // --- Cargar configuración theme.json ---
        $configFile = $themePath . '/theme.json';
        if (!file_exists($configFile)) {
            flash('warning', 'El tema no tiene archivo de configuración (theme.json).');
            header('Location: /musedock/themes');
            exit;
        }
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
             flash('error', 'Error al leer o decodificar theme.json.');
             header('Location: /musedock/themes');
             exit;
        }
        // --- Fin Cargar theme.json ---

        // --- Verificar si el tema es personalizable ---
        if (!($config['customizable'] ?? false) || empty($config['customizable_options'])) {
            flash('warning', 'Este tema no tiene opciones de personalización disponibles.');
            header('Location: /musedock/themes');
            exit;
        }
        // --- Fin Verificar Personalizable ---

        // --- Cargar valores guardados usando el Modelo ---
        $savedOptions = ThemeOption::getOptions($slug, $tenantId);
        // Determinar origen (si necesitamos saber si son específicas del tenant o globales heredadas)
        $optionSource = 'global'; // Asumir global por defecto
        if ($tenantId !== null) {
             $tenantSpecificOptions = ThemeOption::query()
                                       ->where('theme_slug', $slug)
                                       ->where('tenant_id', $tenantId)
                                       ->first();
             if ($tenantSpecificOptions) {
                 $optionSource = 'tenant';
             }
        }
        // ---------------------------------------------

        // --- Preparar datos para la vista ---
        $data = [
            'title'        => 'Personalizar: ' . ($config['name'] ?? $slug) . ($tenantId ? ' (Tenant #' . $tenantId . ')' : ' (Global)'),
            'theme'        => $config, // Configuración completa de theme.json
            'slug'         => $slug,
            'tenantId'     => $tenantId,
            'optionsSchema'=> $config['customizable_options'], // El esquema de opciones del JSON
            'savedOptions' => $savedOptions, // Los valores guardados en BD (o [] si no hay)
            'optionSource' => $optionSource  // 'global' o 'tenant'
        ];

        return View::renderSuperadmin('themes.appearance', $data);
    }

    /**
     * Guarda las opciones de apariencia personalizadas.
     */
  public function save($slug, $tenantId = null)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');
    // --- Permisos (Añadir verificación) ---
    // if (!PermissionManager::canManageThemes()) { /* ... error ... */ }

    // --- Recopilar opciones del formulario ---
    $submittedOptions = [];
    foreach ($_POST as $key => $value) {
        // Asumimos que los campos del formulario tienen names como 'option[seccion][opcion]'
        if ($key === 'options' && is_array($value)) {
            $submittedOptions = $value;
            break; // Salir si encontramos el array 'options' principal
        }
        // Fallback si los nombres son planos con puntos (menos ideal)
        // if (strpos($key, 'option_') === 0) { /* ... tu lógica anterior con explode ... */ }
    }

    // Validar datos recibidos (básico)
    if (empty($submittedOptions)) {
        flash('error', 'No se recibieron datos de opciones para guardar.');
        // Construir URL de redirección
        $redirectUrl = "/musedock/themes/appearance/{$slug}" . ($tenantId ? "/{$tenantId}" : '');
        header("Location: {$redirectUrl}");
        exit;
    }
    // --- Fin Recopilar Opciones ---

    // --- Guardar usando el Modelo ---
    $success = ThemeOption::saveOptions($slug, $tenantId, $submittedOptions);
    // -----------------------------

    if ($success) {
        try {
            // Generar CSS personalizado
            $this->generateCustomCSS($slug, $submittedOptions, $tenantId);
            
            // Generar JS personalizado
            $this->generateCustomJS($slug, $submittedOptions, $tenantId);
            
            flash('success', 'Opciones y archivos personalizados guardados correctamente.');
        } catch (\Exception $e) {
            flash('warning', 'Opciones guardadas, pero hubo un error al generar los archivos personalizados: ' . $e->getMessage());
            error_log("Error generando archivos para {$slug}/{$tenantId}: " . $e->getMessage());
        }
        
        // --- Limpiar caché de Blade (si aplica) ---
        // $this->clearThemeCache(); // Crear este método si es necesario
        // --------------------------------------
    } else {
        flash('error', 'Error al guardar las opciones de apariencia en la base de datos.');
    }

    // Redirigir de vuelta al personalizador
    $redirectUrl = "/musedock/themes/appearance/{$slug}" . ($tenantId ? "/{$tenantId}" : '');
    header("Location: {$redirectUrl}");
    exit;
}
    /**
     * Genera un archivo CSS personalizado basado en las opciones guardadas.
     * (Lógica similar a tu PDF, adaptada y simplificada)
     */
   protected function generateCustomCSS($slug, $options, $tenantId = null)
{
    // 1. Determinar la ruta del tema (global o tenant)
    $themePath = null;
    if ($tenantId) {
        $tryPath = realpath(__DIR__ . "/../../../themes/tenant_{$tenantId}/{$slug}");
        if ($tryPath && is_dir($tryPath)) { $themePath = $tryPath; }
    }
    if (!$themePath) {
        $tryPath = realpath(__DIR__ . "/../../../themes/{$slug}");
        if ($tryPath && is_dir($tryPath)) { $themePath = $tryPath; }
    }
    if (!$themePath) { throw new \Exception("Ruta del tema no encontrada para {$slug}/{$tenantId}"); }

    // 2. Cargar plantilla CSS base (template.css)
    $cssTemplatePath = $themePath . '/css/template.css';
    $cssTemplate = ":root {\n /* Variables CSS Generadas */ \n}\n"; // Default si no existe
    if (file_exists($cssTemplatePath)) {
        $cssTemplate = file_get_contents($cssTemplatePath);
    }

    // 3. Configurar variables CSS desde las opciones guardadas
    $cssVars = [];
    // Mapeo simplificado (ajusta según tu theme.json y template.css)
    $mappings = $this->getOptionCssMappings(); // Obtener mapeos (ver método abajo)

    foreach ($mappings as $optionPath => $cssVar) {
        $value = $this->getNestedValue($options, explode('.', $optionPath));
        if ($value !== null && $value !== '') {
            // Aplicar lógica específica si es necesario (ej: fuentes)
            if (str_contains($optionPath, '_font') && $value !== 'default') {
                $cssVars[$cssVar] = "'{$value}', sans-serif"; // Añadir fallback
            } elseif(str_contains($optionPath, 'font_size')) {
                $sizeMap = ['small' => '14px', 'medium' => '16px', 'large' => '18px'];
                $cssVars[$cssVar] = $sizeMap[$value] ?? '16px'; // Mapear tamaño
            } else {
                $cssVars[$cssVar] = $value; // Usar valor directamente (colores, etc)
            }
        }
    }

    // 4. Generar bloque :root
    $cssVarsBlock = ":root {\n";
    foreach ($cssVars as $var => $value) {
        // Escapar valor si contiene caracteres especiales? Generalmente no para CSS.
        $cssVarsBlock .= "  {$var}: " . $value . ";\n";
    }
    $cssVarsBlock .= "}\n";

    // 5. Reemplazar bloque :root en la plantilla
    //    (Usar regex más simple si sabes que :root está al inicio)
    $finalCss = preg_replace('/:root\s*\{[^}]*\}/s', $cssVarsBlock, $cssTemplate, 1); // Reemplaza solo la primera ocurrencia
    // Si no encontró :root, lo añade al principio
    if (strpos($finalCss, ':root {') === false) {
        $finalCss = $cssVarsBlock . "\n" . $cssTemplate;
    }

    // 6. Añadir CSS personalizado del usuario
    $customCSS = $this->getNestedValue($options, ['custom_code', 'custom_css']);
    if (!empty($customCSS)) {
        $finalCss .= "\n\n/* --- CSS Personalizado --- */\n" . $customCSS;
    }

    // 7. Determinar ruta de guardado y guardar
    $publicBaseDir = realpath(__DIR__ . "/../../../public/assets/themes"); // Directorio base público
    if (!$publicBaseDir) { throw new \Exception("El directorio público de assets/themes no existe."); }

    if ($tenantId) {
        $customCssDir = "{$publicBaseDir}/tenant_{$tenantId}/{$slug}/css";
    } else {
        $customCssDir = "{$publicBaseDir}/{$slug}/css"; // Asume tema global
    }

    if (!is_dir($customCssDir)) {
        if (!mkdir($customCssDir, 0775, true)) {
            throw new \Exception("No se pudo crear el directorio CSS: {$customCssDir}");
        }
    }

    $filename = "custom.css"; // Nombre fijo para el archivo generado
    $filepath = "{$customCssDir}/{$filename}";

    if (file_put_contents($filepath, $finalCss) === false) {
        throw new \Exception("No se pudo escribir en el archivo CSS: {$filepath}");
    }

    // Guardar timestamp para cache busting
    file_put_contents("{$customCssDir}/{$filename}.timestamp", time());

    return true;
}
    /**
     * Devuelve el mapeo entre claves de opciones (theme.json) y variables CSS.
     * Debería coincidir con tu archivo template.css y theme.json.
     */
		protected function getOptionCssMappings(): array
		{
			return [
				// Top Bar
				'topbar.topbar_bg_color' => '--topbar-bg-color',
				'topbar.topbar_text_color' => '--topbar-text-color',
				// Header
				'header.header_bg_color' => '--header-bg-color',
				'header.header_cta_bg_color' => '--header-cta-bg-color',
				'header.header_cta_text_color' => '--header-cta-text-color',
				// Footer
				'footer.footer_bg_color' => '--footer-bg-color',
				'footer.footer_text_color' => '--footer-text-color',
				'footer.footer_link_color' => '--footer-link-color',
				'footer.footer_link_hover_color' => '--footer-link-hover-color',
			];
		}

    
		protected function generateCustomJS($slug, $options, $tenantId = null)
	{
		$customJS = $this->getNestedValue($options, ['custom_code', 'custom_js']);

		// Si no hay JS personalizado, no hacemos nada
		if (empty($customJS)) {
			return true;
		}

		// Determinar ruta de guardado
		$publicBaseDir = realpath(__DIR__ . "/../../../public/assets/themes");
		if (!$publicBaseDir) {
			throw new \Exception("El directorio público de assets/themes no existe.");
		}

		if ($tenantId) {
			$customJsDir = "{$publicBaseDir}/tenant_{$tenantId}/{$slug}/js";
		} else {
			$customJsDir = "{$publicBaseDir}/{$slug}/js";
		}

		if (!is_dir($customJsDir)) {
			if (!mkdir($customJsDir, 0775, true)) {
				throw new \Exception("No se pudo crear el directorio JS: {$customJsDir}");
			}
		}

		// Preparar contenido con un wrapper para evitar conflictos
		$finalJs = "(function() {\n";
		$finalJs .= "/* --- JavaScript Personalizado --- */\n";
		$finalJs .= $customJS;
		$finalJs .= "\n})();";

		// Guardar archivo
		$filename = "custom.js";
		$filepath = "{$customJsDir}/{$filename}";

		if (file_put_contents($filepath, $finalJs) === false) {
			throw new \Exception("No se pudo escribir en el archivo JS: {$filepath}");
		}

		// Opcional: Timestamp para cache busting
		file_put_contents("{$customJsDir}/{$filename}.timestamp", time());

		return true;
	}
	
	
	
	/**
     * Obtiene un valor de un array anidado usando una lista de claves (dot notation).
     * (Función helper como en tu PDF)
     */
	
    protected function getNestedValue($array, $keys, $default = null)
    {
        if (!is_array($keys)) { $keys = explode('.', $keys); } // Aceptar string con puntos
        $current = $array;
        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }
        return $current;
    }

    // --- (Método preview - Más complejo, lo dejamos para después si quieres) ---
    // public function preview($slug, $tenantId = null) { /* ... */ }

    // --- (Método generatePreviewCSS - Necesario para preview) ---
    // protected function generatePreviewCSS($slug, $options, $previewId) { /* ... */ }

    /**
     * Restaura las opciones de apariencia a los valores por defecto del tema.
     */
    public function reset($slug, $tenantId = null)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        // Eliminar opciones guardadas de la BD
        $deleted = ThemeOption::deleteOptions($slug, $tenantId);

        if ($deleted) {
            // Eliminar archivos CSS y JS personalizados
            try {
                $publicBaseDir = realpath(__DIR__ . "/../../../public/assets/themes");

                if ($tenantId) {
                    $customDir = "{$publicBaseDir}/tenant_{$tenantId}/{$slug}";
                } else {
                    $customDir = "{$publicBaseDir}/{$slug}";
                }

                // Eliminar custom.css
                $customCssPath = "{$customDir}/css/custom.css";
                if (file_exists($customCssPath)) {
                    unlink($customCssPath);
                }
                $customCssTimestamp = "{$customDir}/css/custom.css.timestamp";
                if (file_exists($customCssTimestamp)) {
                    unlink($customCssTimestamp);
                }

                // Eliminar custom.js
                $customJsPath = "{$customDir}/js/custom.js";
                if (file_exists($customJsPath)) {
                    unlink($customJsPath);
                }
                $customJsTimestamp = "{$customDir}/js/custom.js.timestamp";
                if (file_exists($customJsTimestamp)) {
                    unlink($customJsTimestamp);
                }

                flash('success', 'Opciones de apariencia restauradas a los valores por defecto.');
            } catch (\Exception $e) {
                flash('warning', 'Opciones eliminadas de la BD, pero hubo un error al eliminar los archivos: ' . $e->getMessage());
            }
        } else {
            flash('info', 'No había opciones personalizadas guardadas para restaurar.');
        }

        // Redirigir de vuelta al personalizador
        $redirectUrl = "/musedock/themes/appearance/{$slug}" . ($tenantId ? "/{$tenantId}" : '');
        header("Location: {$redirectUrl}");
        exit;
    }

} // Fin clase ThemeAppearanceController
