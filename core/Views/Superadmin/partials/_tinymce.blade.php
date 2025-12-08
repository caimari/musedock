{{-- Scripts y estilos necesarios --}}
<script src="/assets/vendor/tinymce/js/tinymce/tinymce.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" />

<!-- Estilos actualizados para eliminar completamente el borde azul del editor TinyMCE -->
<style>
  /* --- Skeleton Loader para TinyMCE --- */
  .tinymce-skeleton {
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    background: #fff;
    height: 600px;
    display: flex;
    flex-direction: column;
  }

  .tinymce-skeleton-toolbar {
    background: #f8f9fa;
    border-bottom: 1px solid #ced4da;
    padding: 8px 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .tinymce-skeleton-btn {
    width: 28px;
    height: 28px;
    background: linear-gradient(90deg, #e9ecef 25%, #f8f9fa 50%, #e9ecef 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite;
    border-radius: 3px;
  }

  .tinymce-skeleton-separator {
    width: 1px;
    height: 28px;
    background: #dee2e6;
    margin: 0 4px;
  }

  .tinymce-skeleton-content {
    flex: 1;
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .tinymce-skeleton-line {
    height: 16px;
    background: linear-gradient(90deg, #e9ecef 25%, #f8f9fa 50%, #e9ecef 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite;
    border-radius: 4px;
  }

  .tinymce-skeleton-line:nth-child(1) { width: 90%; }
  .tinymce-skeleton-line:nth-child(2) { width: 75%; }
  .tinymce-skeleton-line:nth-child(3) { width: 85%; }
  .tinymce-skeleton-line:nth-child(4) { width: 60%; }
  .tinymce-skeleton-line:nth-child(5) { width: 80%; animation-delay: 0.1s; }
  .tinymce-skeleton-line:nth-child(6) { width: 70%; animation-delay: 0.2s; }

  @keyframes skeleton-shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
  }

  /* Ocultar textarea mientras TinyMCE no esté listo */
  #content-editor {
    display: none;
  }

  /* --- Estilos Generales para Inputs --- */
  textarea:focus, input:focus, select:focus, .form-control:focus, .form-select:focus, .btn:focus, .input-group-text:focus {
    outline: none !important; box-shadow: none !important; border-color: #ced4da !important;
  }

  /* --- Estilos Específicos para TinyMCE UI --- */
  .tox-tinymce {
    border: 1px solid #ced4da !important;
    border-radius: 0.25rem !important;
  }
  
  .tox .tox-edit-area__iframe { 
    background-color: white !important; 
  }
  
  /* Eliminar TODOS los bordes de foco y cajas de sombra en TinyMCE */
  .tox *, 
  .tox-tinymce *, 
  .tox-tinymce *:focus,
  .tox *:focus,
  .tox .tox-edit-area__iframe,
  .tox .tox-edit-area__iframe:focus,
  .tox .tox-edit-area:focus,
  .tox-tinymce--toolbar-bottom,
  .tox-tinymce-aux:focus,
  .tox-tinymce:focus,
  .tox-tinymce:focus-within,
  .tox .tox-toolbar:focus,
  .tox .tox-toolbar__group:focus,
  .tox .tox-toolbar__primary:focus,
  .tox .tox-tbtn:focus,
  .tox .tox-menu:focus,
  .tox .tox-collection__item--active,
  .tox .tox-dialog *:focus,
  .tox-editor-container,
  .tox-editor-container *,
  .mce-content-body,
  .mce-content-body:focus {
    outline: none !important;
    box-shadow: none !important;
  }
  
  /* Agregar un borde diferente al hacer foco, en lugar del azul predeterminado */
  .tox.tox-tinymce:focus, 
  .tox.tox-tinymce:focus-within { 
    border: 1px solid #ced4da !important; 
  }
  
  /* Estilos adicionales para el área de edición */
  .tox-edit-area {
    border: none !important;
  }
  
  /* Estilos para el contenido editable */
  .mce-content-body {
    outline: none !important;
  }
  
  /* Eliminar el borde azul dentro del iframe */
  iframe#content-editor_ifr {
    outline: none !important;
  }
  
  /* --- Estilos Opcionales --- */
  .tox .tox-tbtn--bespoke { background-color: #f8f9fa !important; }
  .tox-collection__item-label h1 { font-size: 2em !important; margin: 0 !important; }
  .tox-collection__item-label h2 { font-size: 1.5em !important; margin: 0 !important; }
  .tox-collection__item-label h3 { font-size: 1.17em !important; margin: 0 !important; }
  .tox-collection__item-label h4 { font-size: 1em !important; margin: 0 !important; }
  .tox-collection__item-label h5 { font-size: 0.83em !important; margin: 0 !important; }
  .tox-collection__item-label h6 { font-size: 0.67em !important; margin: 0 !important; }
</style>

@php
// --- Lógica PHP para determinar el estado de AIWriter ---
$aiWriterActive = false;
$aiWriterPluginPath = '/modules/aiwriter/js/tiny-ai-plugin.js';

try {
    $tenantId = function_exists('tenant_id') ? tenant_id() : null;
    if ($tenantId !== null) {
        $query = "SELECT m.active, tm.enabled FROM modules m LEFT JOIN tenant_modules tm ON tm.module_id = m.id AND tm.tenant_id = :tenant_id WHERE m.slug = 'aiwriter'";
        $module = \Screenart\Musedock\Database::query($query, ['tenant_id' => $tenantId])->fetch();
        $aiWriterActive = $module && $module['active'] && ($module['enabled'] ?? false);
    } else {
        $query = "SELECT active, cms_enabled FROM modules WHERE slug = 'aiwriter'";
        $module = \Screenart\Musedock\Database::query($query)->fetch();
        $aiWriterActive = $module && $module['active'] && $module['cms_enabled'];
    }
} catch (\Throwable $e) {
    error_log("Error al verificar el estado del módulo AIWriter: " . $e->getMessage());
    $aiWriterActive = false;
}

// --- Preparación de la configuración básica de TinyMCE (sin depender de AIWriter) ---
$tinymce_plugins_list = [
    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor',
    'searchreplace', 'visualblocks', 'code', 'fullscreen',
    'insertdatetime', 'media', 'table', 'help', 'wordcount', 'codesample'
    // Eliminado 'quickbars' para deshabilitar la barra flotante junto al cursor
];
$tinymce_toolbar_lines = [
    'undo redo | cut copy paste removeformat | blocks | bold italic underline strikethrough | forecolor backcolor',
    'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media codesample | code fullscreen | help'
];
$tinymce_external_plugins = [];
$tinymce_context_menu_items = ['cut', 'copy', 'paste', '|', 'link', 'image', 'table'];

// La configuración del plugin AIWriter se agregará mediante JavaScript
// para evitar problemas si el plugin no está disponible

$pluginsString = implode(' ', $tinymce_plugins_list);
$externalPluginsJson = json_encode($tinymce_external_plugins);
$contextmenuString = implode(' ', $tinymce_context_menu_items);

// Pasar la configuración a JavaScript como variables
@endphp

<script>
// Inicializar TinyMCE inmediatamente (sin esperar DOMContentLoaded para más rapidez)
(function() {
    // Función para ocultar skeleton y mostrar editor
    function hideSkeleton() {
        console.log('Ocultando skeleton loader...');
        const skeleton = document.getElementById('tinymce-skeleton');
        if (skeleton) {
            skeleton.style.display = 'none';
            console.log('Skeleton ocultado');
        } else {
            console.warn('Skeleton no encontrado');
        }

        // Asegurarse de que el editor TinyMCE sea visible
        const tinymceContainer = document.querySelector('.tox-tinymce');
        if (tinymceContainer) {
            tinymceContainer.style.display = 'block';
            tinymceContainer.style.visibility = 'visible';
            console.log('Contenedor TinyMCE mostrado');
        } else {
            console.warn('Contenedor TinyMCE no encontrado');
        }
    }

    // Función para mostrar el textarea como fallback
    function showTextareaFallback(showError = false) {
        hideSkeleton();
        const textarea = document.getElementById('content-editor');
        if (textarea) {
            textarea.style.cssText = 'display: block !important; width: 100%; height: 400px; min-height: 400px; padding: 15px; border: 1px solid #ced4da; border-radius: 0.25rem; font-family: monospace;';

            if (showError) {
                // Agregar un mensaje sobre el error si no existe ya
                if (!document.getElementById('tinymce-error-msg')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.id = 'tinymce-error-msg';
                    errorDiv.className = 'alert alert-warning mt-2';
                    errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>No se pudo cargar el editor visual. Puedes editar el contenido en modo texto.';
                    textarea.parentNode.insertBefore(errorDiv, textarea.nextSibling);
                }
            }
        }
    }

    // Timeout de seguridad: si TinyMCE no carga en 10 segundos, mostrar textarea
    const safetyTimeout = setTimeout(function() {
        console.warn('TinyMCE no cargó en 10 segundos. Mostrando textarea como fallback.');
        if (typeof tinymce === 'undefined' || !tinymce.get('content-editor')) {
            showTextareaFallback(true);
        }
    }, 10000);

    // Verificar si TinyMCE está disponible
    if (typeof tinymce === 'undefined') {
        console.error('TinyMCE no está cargado. Mostrando textarea.');
        clearTimeout(safetyTimeout);
        showTextareaFallback(true);
        return;
    }

    // Prevenir inicialización múltiple
    if (tinymce.get('content-editor')) {
        console.log("TinyMCE ya está inicializado para #content-editor. No se inicializará de nuevo.");
        clearTimeout(safetyTimeout);
        hideSkeleton();
        return;
    }

    // Variables para la configuración de AIWriter
    const isAiWriterActive = {{ $aiWriterActive ? 'true' : 'false' }};
    const aiWriterPluginPath = '{{ $aiWriterPluginPath }}';

    console.log("Inicializando TinyMCE para #content-editor (AIWriter " + (isAiWriterActive ? "ACTIVO" : "INACTIVO") + ")");
    
    // Configuración básica de TinyMCE (sin plugins externos)
    const baseConfig = {
        selector: '#content-editor',
        height: 600,
        menubar: true,
        license_key: 'gpl',
        
        // --- Configuración generada ---
        plugins: '{{ $pluginsString }}',
        toolbar: <?php echo json_encode($tinymce_toolbar_lines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        contextmenu: '{{ $contextmenuString }}',
        
        // --- Otras configuraciones ---
        block_formats: 'Párrafo=p; Encabezado 1=h1; Encabezado 2=h2; Encabezado 3=h3; Encabezado 4=h4; Encabezado 5=h5; Encabezado 6=h6; Preformateado=pre; Bloque de Código=code',
        content_style: `
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; font-size: 16px; margin: 15px; }
            code { background-color: #f4f4f4; padding: 2px 4px; border-radius: 4px; font-size: 90%; font-family: monospace; }
            pre > code { display: block; padding: 10px; background-color: #2d2d2d; color: #f1f1f1; border-radius: 5px; overflow-x: auto; }
        `,
        branding: false,
        promotion: false,
        browser_spellcheck: true,
        paste_data_images: true,
        image_caption: true,
        
        // Desactivar las quickbars (barras flotantes)
        quickbars_selection_toolbar: false,
        quickbars_insert_toolbar: false,
        
        entity_encoding: 'raw',
        convert_urls: false,

        // Habilitar el file picker para imágenes y medios
        file_picker_types: 'image media file',
        file_picker_callback: function(callback, value, meta) {
            // Verificar si el Media Manager está disponible
            if (typeof window.openMediaManagerForTinyMCE === 'function') {
                window.openMediaManagerForTinyMCE(callback, value, meta);
            } else if (typeof window.openMediaManager === 'function') {
                // Fallback: crear un input temporal y usar el modal estándar
                window._tinymceFilePickerCallback = callback;
                window._tinymceFilePickerMeta = meta;

                // Crear elementos temporales para el media manager
                let tempInput = document.getElementById('tinymce-media-input');
                if (!tempInput) {
                    tempInput = document.createElement('input');
                    tempInput.type = 'hidden';
                    tempInput.id = 'tinymce-media-input';
                    document.body.appendChild(tempInput);
                }

                // Abrir el gestor de medios con los selectores
                const mediaModal = document.getElementById('md-media-manager');
                if (mediaModal) {
                    // Configurar callback personalizado para TinyMCE
                    window._tinymceMediaCallback = function(url) {
                        if (window._tinymceFilePickerCallback) {
                            window._tinymceFilePickerCallback(url, { title: '' });
                            window._tinymceFilePickerCallback = null;
                        }
                    };

                    // Simular clic en botón que abre el modal
                    const btn = document.createElement('button');
                    btn.className = 'open-media-modal-button';
                    btn.dataset.inputTarget = '#tinymce-media-input';
                    btn.style.display = 'none';
                    document.body.appendChild(btn);
                    btn.click();
                    btn.remove();
                }
            } else {
                // Si no hay Media Manager, usar input file nativo
                const input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', meta.filetype === 'image' ? 'image/*' : '*/*');

                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function() {
                            callback(reader.result, { title: file.name });
                        };
                        reader.readAsDataURL(file);
                    }
                });

                input.click();
            }
        },

        // Función Setup con solución para el borde azul
        setup: function(editor) {
            editor.on('init', function() {
                console.log('TinyMCE inicializado para: #' + editor.id);

                // Limpiar timeout de seguridad
                if (typeof safetyTimeout !== 'undefined') {
                    clearTimeout(safetyTimeout);
                }

                // Ocultar skeleton loader
                hideSkeleton();

                // Hacer visible el contenedor del editor
                const container = editor.getContainer();
                if (container) container.style.visibility = 'visible';
                
                // ---- INICIO DE SOLUCIÓN PARA EL BORDE AZUL ----
                
                // 1. Aplicar estilos al contenedor principal de TinyMCE
                const tinymceElement = document.querySelector('.tox.tox-tinymce');
                if (tinymceElement) {
                    tinymceElement.style.outline = 'none';
                    tinymceElement.style.boxShadow = 'none';
                }
                
                // 2. Aplicar estilos al iframe de edición
                const iframeElement = document.getElementById('content-editor_ifr');
                if (iframeElement) {
                    // Quitar el borde del iframe mismo
                    iframeElement.style.outline = 'none';
                    iframeElement.style.boxShadow = 'none';
                    
                    // Acceder al documento dentro del iframe
                    try {
                        const iframeDoc = iframeElement.contentDocument || iframeElement.contentWindow.document;
                        
                        // Añadir estilos específicos al body del iframe
                        const styleElement = iframeDoc.createElement('style');
                        styleElement.textContent = `
                            body.mce-content-body {
                                outline: none !important;
                                box-shadow: none !important;
                            }
                            body.mce-content-body:focus,
                            body.mce-content-body:focus-visible {
                                outline: none !important;
                                box-shadow: none !important;
                                border: none !important;
                            }
                            *:focus, *:focus-visible {
                                outline: none !important;
                                box-shadow: none !important;
                            }
                        `;
                        
                        iframeDoc.head.appendChild(styleElement);
                        
                        // Eliminar el foco en todo el documento
                        iframeDoc.body.addEventListener('focus', function(e) {
                            this.style.outline = 'none';
                            e.target.style.outline = 'none';
                        }, true);
                    } catch (e) {
                        console.error("Error al aplicar estilos al iframe:", e);
                    }
                }
                
                // 3. Desactivar la apariencia de foco para todo el contenedor de TinyMCE
                document.querySelectorAll('.tox *').forEach(el => {
                    el.style.outline = 'none';
                    el.addEventListener('focus', function() {
                        this.style.outline = 'none';
                    });
                });
                
                // ---- FIN DE SOLUCIÓN PARA EL BORDE AZUL ----
            });
        }
    };
    
    // Función para intentar inicializar TinyMCE con la configuración dada
    function initTinyMCE(config) {
        return new Promise((resolve, reject) => {
            tinymce.init(config)
                .then(function(editors) {
                    resolve(editors);
                })
                .catch(function(error) {
                    reject(error);
                });
        });
    }

    // Función para inicializar TinyMCE con retroceso a configuración básica en caso de error
    async function initTinyMCEWithFallback() {
        // Si AIWriter está activo, intentar agregar el plugin
        let currentConfig = {...baseConfig};
        
        if (isAiWriterActive) {
            // Añadir configuración de AIWriter
            try {
                console.log("Intentando inicializar con AIWriter...");
                
                // Agregar plugin AIWriter a la configuración
                currentConfig.plugins += ' aiwriter';
                currentConfig.toolbar[1] += ' | aiwritermenu';
                currentConfig.contextmenu += ' | aiwriter';
                currentConfig.external_plugins = {
                    'aiwriter': aiWriterPluginPath
                };
                
                // Extender setup para manejar AIWriter
                const originalSetup = currentConfig.setup;
                currentConfig.setup = function(editor) {
                    // Llamar al setup original
                    if (originalSetup) originalSetup(editor);
                    
                    // Agregar verificación de AIWriter
                    editor.on('init', function() {
                        console.log('AI Writer Plugin está ACTIVO. Ruta esperada:', aiWriterPluginPath);
                        setTimeout(function() {
                            try {
                                if (tinymce.PluginManager.get('aiwriter')) {
                                    console.log('-> Plugin "aiwriter" REGISTRADO en PluginManager.');
                                } else { 
                                    console.warn('-> Plugin "aiwriter" NO REGISTRADO en PluginManager.'); 
                                }
                                
                                if (editor.ui && editor.ui.registry && editor.ui.registry.getAll().menuButtons && editor.ui.registry.getAll().menuButtons.aiwritermenu) {
                                    console.log('-> MenuButton "aiwritermenu" REGISTRADO en UI.');
                                } else { 
                                    console.warn('-> MenuButton "aiwritermenu" NO ENCONTRADO en UI.'); 
                                }
                            } catch (e) { 
                                console.error("Error durante verificaciones AIWriter:", e); 
                            }
                        }, 500);
                    });
                };
                
                // Intentar inicializar con AIWriter
                try {
                    await initTinyMCE(currentConfig);
                    clearTimeout(safetyTimeout);
                    console.log("TinyMCE inicializado correctamente con AIWriter");
                    return; // Éxito, salir de la función
                } catch (error) {
                    console.warn("Error al inicializar TinyMCE con AIWriter:", error);
                    console.log("Retrocediendo a configuración básica...");
                    // Continuar con la configuración básica (sin AIWriter)
                }
            } catch (e) {
                console.error("Error al configurar AIWriter:", e);
                // Continuar con la configuración básica
            }
        }
        
        // Si no se ha inicializado con AIWriter, usar configuración básica
        try {
            await initTinyMCE(baseConfig);
            clearTimeout(safetyTimeout);
            console.log("TinyMCE inicializado correctamente con configuración básica");
        } catch (error) {
            console.error("Error crítico al inicializar TinyMCE:", error);
            
            // Último intento con configuración mínima
            const minimalConfig = {
                selector: '#content-editor',
                height: 600,
                menubar: false,
                plugins: 'link image table code',
                toolbar: 'undo redo | formatselect | bold italic | link image | table | code',
                setup: function(editor) {
                    editor.on('init', function() {
                        console.log('TinyMCE inicializado (configuración mínima) para: #' + editor.id);
                        hideSkeleton();
                        const container = editor.getContainer();
                        if (container) container.style.visibility = 'visible';
                        
                        // Aplicar la misma solución para el borde azul
                        const iframeElement = document.getElementById('content-editor_ifr');
                        if (iframeElement) {
                            iframeElement.style.outline = 'none';
                            try {
                                const iframeDoc = iframeElement.contentDocument || iframeElement.contentWindow.document;
                                const styleElement = iframeDoc.createElement('style');
                                styleElement.textContent = `
                                    body.mce-content-body, body.mce-content-body:focus, *:focus {
                                        outline: none !important;
                                        box-shadow: none !important;
                                    }
                                `;
                                iframeDoc.head.appendChild(styleElement);
                            } catch (e) {}
                        }
                    });
                }
            };
            
            try {
                await initTinyMCE(minimalConfig);
                clearTimeout(safetyTimeout);
                console.log("TinyMCE inicializado con configuración mínima");
            } catch (finalError) {
                console.error("ERROR FATAL: No se pudo inicializar TinyMCE:", finalError);
                clearTimeout(safetyTimeout);
                showTextareaFallback(true);
            }
        }
    }

    // Esperar a que el DOM esté listo antes de inicializar
    if (document.readyState === 'loading') {
        console.log('DOM aún cargando, esperando DOMContentLoaded...');
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded disparado, verificando textarea...');
            const textarea = document.getElementById('content-editor');
            console.log('Textarea encontrado:', textarea ? 'SÍ' : 'NO');
            if (textarea) {
                initTinyMCEWithFallback();
            } else {
                console.error('ERROR: No se encontró el textarea #content-editor');
                showTextareaFallback(true);
            }
        });
    } else {
        // DOM ya está listo, verificar que el textarea exista
        console.log('DOM ya listo, verificando textarea...');
        const textarea = document.getElementById('content-editor');
        console.log('Textarea encontrado:', textarea ? 'SÍ' : 'NO');
        if (textarea) {
            initTinyMCEWithFallback();
        } else {
            console.error('ERROR: No se encontró el textarea #content-editor');
            showTextareaFallback(true);
        }
    }
})();
</script>

@php
// Verificar si el módulo Media Manager está activo
$mediaModuleActive = function_exists('is_module_active') ? is_module_active('media-manager') : false;
@endphp

@if($mediaModuleActive)
{{-- Incluir el Media Manager para TinyMCE --}}
@include('partials._tinymce_media_manager')
@endif