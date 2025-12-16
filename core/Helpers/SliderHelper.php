<?php

namespace Screenart\Musedock\Helpers;

use Screenart\Musedock\Models\Slider;
use Screenart\Musedock\Models\Slide;

class SliderHelper
{
    private static function sanitizeColor($value, string $default): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') return $default;

        // HEX (#rgb, #rrggbb, #rrggbbaa)
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return $value;
        }

        // rgb/rgba
        if (preg_match('/^rgba?\\(\\s*\\d{1,3}\\s*,\\s*\\d{1,3}\\s*,\\s*\\d{1,3}(\\s*,\\s*(0(\\.\\d+)?|1(\\.0+)?)\\s*)?\\)$/', $value)) {
            return $value;
        }

        return $default;
    }
    /**
     * Renderiza un slider por su ID
     * Retorna string directo (sin SafeHtml) porque el contenido es generado internamente
     * y necesita incluir scripts/estilos que SafeHtml eliminaría
     */
    public static function renderSlider(int $sliderId): string
    {
        try {
            $slider = Slider::find($sliderId);
            if (!$slider) {
                return "<!-- Slider ID {$sliderId} no encontrado -->";
            }

            // settings ya viene como array desde el modelo (cast automático)
            $settings = $slider->settings ?? [];
            $engine = strtolower($settings['engine'] ?? ($slider->engine ?? 'swiper'));

            error_log("Engine detectado FINAL para slider {$sliderId}: " . $engine);

            $slides = $slider->slides();
            $slides = array_filter($slides, fn($slide) => $slide->is_active);
            usort($slides, fn($a, $b) => $a->sort_order <=> $b->sort_order);

            if (empty($slides)) {
                return "<!-- Slider ID {$sliderId} no tiene slides activas -->";
            }

            if ($engine === 'gallery') {
                return self::renderGallery($sliderId, $slides, $settings);
            }

            return self::renderSwiper($sliderId, $slides, $settings);

        } catch (\Exception $e) {
            error_log("Error renderizando slider ID {$sliderId}: " . $e->getMessage());
            return "<!-- Error al renderizar slider ID {$sliderId} -->";
        }
    }

protected static function renderSwiper(int $sliderId, array $slides, array $settings): string
{
    $height = $settings['height'] ?? 400;
    $captionBg = $settings['caption_bg'] ?? 'rgba(0,0,0,0.6)';
    $captionColor = $settings['caption_color'] ?? '#ffffff';
    $titleSize = $settings['caption_title_size'] ?? '24px';
    $descriptionSize = $settings['caption_description_size'] ?? '16px';
    $theme = $settings['theme'] ?? 'default';
    $captionPosition = $settings['caption_position'] ?? 'bottom-left';
    $captionAnimation = $settings['caption_animation'] ?? 'none';
    $transitionEffect = $settings['transition_effect'] ?? 'slide';
    $fullWidth = !empty($settings['full_width']);
    $globalTitleFont = isset($settings['caption_title_font']) ? trim((string)$settings['caption_title_font']) : '';
    $globalDescFont = isset($settings['caption_description_font']) ? trim((string)$settings['caption_description_font']) : '';
    $globalTitleFont = preg_replace('/[;\r\n]/', '', $globalTitleFont);
    $globalDescFont = preg_replace('/[;\r\n]/', '', $globalDescFont);
    $globalTitleColor = self::sanitizeColor($settings['caption_title_color'] ?? '', $captionColor);
    $globalDescColor = self::sanitizeColor($settings['caption_description_color'] ?? '', $captionColor);
    $globalBtnBg = self::sanitizeColor($settings['cta_bg_color'] ?? '', '#1d4ed8');
    $globalBtnText = self::sanitizeColor($settings['cta_text_color'] ?? '', '#ffffff');
    $globalBtnBorder = self::sanitizeColor($settings['cta_border_color'] ?? '', '#ffffff');

    $autoplay = !empty($settings['autoplay']);
    $autoplayDelay = intval($settings['autoplay_delay'] ?? 3000);
    $loop = !empty($settings['loop']);
    $pagination = !empty($settings['pagination']);
    $navigation = !empty($settings['navigation']);

    $arrowStyleClass = !empty($settings['arrows_style']) ? $settings['arrows_style'] : '';
    $arrowColor = $settings['arrows_color'] ?? '#ffffff';
    $arrowBgColor = $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)';

    // Cache-busting para evitar que el navegador se quede con versiones viejas del CSS
    $appRoot = defined('APP_ROOT') ? APP_ROOT : realpath(__DIR__ . '/../../..');
    $swiperCssPath = $appRoot . '/public/assets/css/swiper-bundle.min.css';
    $themesCssPath = $appRoot . '/public/assets/themes/default/css/slider-themes.css';
    $swiperCssVersion = is_file($swiperCssPath) ? filemtime($swiperCssPath) : time();
    $themesCssVersion = is_file($themesCssPath) ? filemtime($themesCssPath) : time();

    $output = '<link rel="stylesheet" href="/assets/css/swiper-bundle.min.css?v=' . $swiperCssVersion . '">';
    $output .= '<link rel="stylesheet" href="/assets/themes/default/css/slider-themes.css?v=' . $themesCssVersion . '">';

    // Cargar Google Fonts si se seleccionaron (global o por slide)
    $googleFontMap = [
        'Playfair Display' => 'Playfair+Display:wght@400;700;800',
        'Montserrat' => 'Montserrat:wght@400;600;700;800',
        'Roboto' => 'Roboto:wght@400;500;700;900',
        'Open Sans' => 'Open+Sans:wght@400;600;700;800',
        'Lato' => 'Lato:wght@400;700;900',
        'Poppins' => 'Poppins:wght@400;500;600;700;800',
        'Oswald' => 'Oswald:wght@400;500;600;700',
        'Raleway' => 'Raleway:wght@400;500;600;700;800',
    ];
    $fontsWanted = [];
    $extractFirstFamily = static function (string $fontFamily): string {
        $first = trim(explode(',', $fontFamily, 2)[0] ?? '');
        $first = trim($first, " \t\n\r\0\x0B\"'");
        return $first;
    };
    foreach ([$globalTitleFont, $globalDescFont] as $ff) {
        if ($ff !== '') $fontsWanted[] = $extractFirstFamily($ff);
    }
    foreach ($slides as $slide) {
        if (!empty($slide->title_font)) $fontsWanted[] = $extractFirstFamily((string)$slide->title_font);
        if (!empty($slide->description_font)) $fontsWanted[] = $extractFirstFamily((string)$slide->description_font);
    }
    $fontsWanted = array_values(array_unique(array_filter($fontsWanted)));
    $googleFamilies = [];
    foreach ($fontsWanted as $fontName) {
        if (isset($googleFontMap[$fontName])) $googleFamilies[] = $googleFontMap[$fontName];
    }
    if (!empty($googleFamilies)) {
        $output .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
        $output .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        $output .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . implode('&family=', $googleFamilies) . '&display=swap">';
    }

    // Wrapper para full-width (a sangre)
    if ($fullWidth) {
        // Full-bleed wrapper robusto con estilos inline para máxima compatibilidad
        // Los estilos inline garantizan que funcione incluso con overflow:hidden en contenedores padre
        $output .= '<div class="slider-full-width-wrapper" data-full-bleed="true" style="position:relative !important;left:50% !important;width:100vw !important;max-width:100vw !important;margin-left:-50vw !important;margin-right:-50vw !important;box-sizing:border-box !important;margin-top:0 !important;">';

        // Añadir script para forzar overflow visible en contenedores padre y eliminar márgenes/paddings
        $output .= '<script>
        (function(){
            var wrapper = document.querySelector(\'.slider-full-width-wrapper[data-full-bleed="true"]\');
            if(wrapper){
                var parent = wrapper.parentElement;
                while(parent && parent !== document.body){
                    parent.style.overflow = "visible";
                    parent.style.overflowX = "visible";
                    parent.style.paddingTop = "0";
                    parent.style.marginTop = "0";
                    parent = parent.parentElement;
                }
                // Eliminar margen del main y container
                var main = document.querySelector("main");
                if(main){ main.style.paddingTop = "0"; main.style.marginTop = "0"; }
                var containers = document.querySelectorAll(".container.py-4, .page-container, .has-slider-content");
                containers.forEach(function(c){ c.style.paddingTop = "0"; c.style.marginTop = "0"; });
                // Eliminar borde del header para que quede pegado al slider
                var header = document.querySelector("header, .musedock-header");
                if(header){ header.style.marginBottom = "0"; header.style.borderBottom = "none"; }
                // Ocultar elementos vacíos antes del slider
                var prev = wrapper.previousElementSibling;
                while(prev){
                    if(prev.tagName === "P" && prev.textContent.trim() === ""){
                        prev.style.display = "none";
                    }
                    prev = prev.previousElementSibling;
                }
            }
        })();
        </script>';
    }

    $themeClass = 'theme-' . \e($theme);
    $sliderStyle = 'width:100%;height:' . intval($height) . 'px;';

    // Preset robusto (inline) para garantizar que rounded-shadow se vea incluso si existe algún override externo.
    if ($theme === 'rounded-shadow') {
        $sliderStyle .= 'border-radius:22px !important;';
        $sliderStyle .= 'overflow:hidden !important;';
        $sliderStyle .= 'background:#ffffff !important;';
        $sliderStyle .= 'box-shadow:0 18px 50px rgba(18, 38, 63, 0.18) !important;';
        $sliderStyle .= 'border:1px solid rgba(18, 38, 63, 0.08) !important;';

        // En full-width, dar inset horizontal para que se aprecien las esquinas/sombra.
        if ($fullWidth) {
            $sliderStyle .= 'width:calc(100% - 32px) !important;';
            $sliderStyle .= 'margin-left:auto !important;margin-right:auto !important;';
        }
    }

    $output .= '<div class="swiper slider-' . $sliderId . ' ' . $themeClass . '" style="' . $sliderStyle . '">';
    $output .= '<div class="swiper-wrapper">';

    foreach ($slides as $slide) {
        $slideStyle = 'position:relative;';
        $imgStyle = 'width:100%;height:100%;object-fit:cover;';
        if ($theme === 'rounded-shadow') {
            $slideStyle .= 'border-radius:22px !important;overflow:hidden !important;';
            $imgStyle .= 'border-radius:22px !important;';
        }
        $output .= '<div class="swiper-slide" style="' . $slideStyle . '">';
        $output .= '<img src="' . \e($slide->image_url) . '" style="' . $imgStyle . '">';

        // Verificar si hay botones configurados
        $linkText = isset($slide->link_text) ? trim((string) $slide->link_text) : '';
        $link2Text = isset($slide->link2_text) ? trim((string) $slide->link2_text) : '';
        $hasBtn1 = !empty($slide->link_url) && $linkText !== '';
        $hasBtn2 = !empty($slide->link2_url) && $link2Text !== '';

        // Solo mostrar caption si está activado y hay contenido (título, descripción o botones)
        if (!empty($settings['show_caption']) && ($slide->title || $slide->description || $hasBtn1 || $hasBtn2)) {
            // Configurar estilo básico del caption con todos los estilos personalizados
            $captionStyle = 'position:absolute;z-index:15;';
            
            // Aplicar posición según selección
            switch ($captionPosition) {
                case 'top-left':    $captionStyle .= 'top:20px;left:20px;text-align:left;'; break;
                case 'top-center':  $captionStyle .= 'top:20px;left:50%;transform:translateX(-50%);text-align:center;'; break;
                case 'top-right':   $captionStyle .= 'top:20px;right:20px;text-align:right;'; break;
                case 'bottom-left': $captionStyle .= 'bottom:20px;left:20px;text-align:left;'; break;
                case 'bottom-center': $captionStyle .= 'bottom:20px;left:50%;transform:translateX(-50%);text-align:center;'; break;
                case 'bottom-right': $captionStyle .= 'bottom:20px;right:20px;text-align:right;'; break;
                case 'center': default: $captionStyle .= 'top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;'; break;
            }
            
            // IMPORTANTE: Siempre aplicar fondo y color personalizados, independientemente del tema
            $captionStyle .= 'background:' . $captionBg . ' !important;';
            $captionStyle .= 'color:' . $captionColor . ' !important;';
            $captionStyle .= 'padding:12px 20px;border-radius:6px;';
            
            // Clases para el caption
            $captionClasses = 'caption';
            $captionClasses .= ' position-' . \e($captionPosition);
            if (!empty($captionAnimation) && $captionAnimation !== 'none') {
                $captionClasses .= ' animation-' . \e($captionAnimation);
            }
            
            // Renderizar el caption - NO añadir onclick aquí
            $output .= '<div class="' . $captionClasses . '" style="' . $captionStyle . '">';
            
            // CAMBIO: Si hay link_url, hacer que SOLO el título sea clickeable
            if (!empty($slide->title)) {
                $isTitleBold = !isset($slide->title_bold) || (bool) $slide->title_bold;
                $titleFont = isset($slide->title_font) ? trim((string) $slide->title_font) : '';
                $titleFont = preg_replace('/[;\\r\\n]/', '', $titleFont);
                $titleWeight = $isTitleBold ? '800' : '400';
                $titleColor = self::sanitizeColor($slide->title_color ?? '', $globalTitleColor);
                $titleStyle = 'font-size:' . $titleSize . ';font-weight:' . $titleWeight . ';color:' . $titleColor . ' !important;';
                $finalTitleFont = $titleFont !== '' ? $titleFont : $globalTitleFont;
                if ($finalTitleFont !== '') $titleStyle .= 'font-family:' . \e($finalTitleFont) . ' !important;';
                
                if (!empty($slide->link_url)) {
                    // Título con enlace
                    $output .= '<a href="' . \e($slide->link_url) . '" style="' . $titleStyle . 'text-decoration:none;display:block;color:inherit;">' . \e($slide->title) . '</a>';
                } else {
                    // Título sin enlace
                    $output .= '<div style="' . $titleStyle . '">' . \e($slide->title) . '</div>';
                }
            }
            
            // Descripción (sin enlace)
            if (!empty($slide->description)) {
                $descFont = isset($slide->description_font) ? trim((string) $slide->description_font) : '';
                $descFont = preg_replace('/[;\\r\\n]/', '', $descFont);
                $descColor = self::sanitizeColor($slide->description_color ?? '', $globalDescColor);
                $descStyle = 'font-size:' . $descriptionSize . ';margin-top:6px;color:' . $descColor . ' !important;';
                $finalDescFont = $descFont !== '' ? $descFont : $globalDescFont;
                if ($finalDescFont !== '') $descStyle .= 'font-family:' . \e($finalDescFont) . ' !important;';
                $output .= '<div style="' . $descStyle . '">' . \e($slide->description) . '</div>';
            }

            // Botón opcional (si hay link_url + link_text) - variables ya definidas arriba
            $buttonShape = isset($slide->button_shape) && $slide->button_shape === 'square' ? 'shape-square' : '';

            if ($hasBtn1 || $hasBtn2) {
                // Solo añadir margin-top si hay título o descripción antes
                $btnMargin = ($slide->title || $slide->description) ? 'margin-top:14px;' : '';
                $output .= '<div style="' . $btnMargin . '" class="slider-cta-buttons ' . $buttonShape . '">';

                if ($hasBtn1) {
                    $linkText = preg_replace('/[\\r\\n]/', '', $linkText);
                    $useCustomButton = !empty($slide->button_custom);
                    $btnBg = self::sanitizeColor($useCustomButton ? ($slide->button_bg_color ?? '') : '', $globalBtnBg);
                    $btnText = self::sanitizeColor($useCustomButton ? ($slide->button_text_color ?? '') : '', $globalBtnText);
                    $btnBorder = self::sanitizeColor($useCustomButton ? ($slide->button_border_color ?? '') : '', $globalBtnBorder);
                    $btnStyle = 'background-color:' . $btnBg . ' !important;color:' . $btnText . ' !important;border-color:' . $btnBorder . ' !important;';
                    $target = (isset($slide->link_target) && $slide->link_target === '_blank') ? '_blank' : '_self';
                    $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
                    $output .= '<a class="slider-cta-button" style="' . $btnStyle . '" href="' . \e($slide->link_url) . '" target="' . $target . '"' . $rel . '>' . \e($linkText) . '</a>';
                }

                if ($hasBtn2) {
                    $link2Text = preg_replace('/[\\r\\n]/', '', $link2Text);
                    $useCustomButton2 = !empty($slide->button2_custom);
                    // Botón 2 por defecto: transparente (outline). Si hay custom, puede tener fondo.
                    $btn2Bg = self::sanitizeColor($useCustomButton2 ? ($slide->button2_bg_color ?? '') : '', 'transparent');
                    $btn2Text = self::sanitizeColor($useCustomButton2 ? ($slide->button2_text_color ?? '') : '', $globalBtnText);
                    $btn2Border = self::sanitizeColor($useCustomButton2 ? ($slide->button2_border_color ?? '') : '', $globalBtnBorder);
                    $btn2Style = 'background-color:' . $btn2Bg . ' !important;color:' . $btn2Text . ' !important;border-color:' . $btn2Border . ' !important;';
                    $target2 = (isset($slide->link2_target) && $slide->link2_target === '_blank') ? '_blank' : '_self';
                    $rel2 = $target2 === '_blank' ? ' rel="noopener noreferrer"' : '';
                    $output .= '<a class="slider-cta-button secondary" style="' . $btn2Style . '" href="' . \e($slide->link2_url) . '" target="' . $target2 . '"' . $rel2 . '>' . \e($link2Text) . '</a>';
                }

                $output .= '</div>';
            }
            
            $output .= '</div>'; // Fin caption
        }

        $output .= '</div>'; // Fin swiper-slide
    }

    $output .= '</div>'; // Fin swiper-wrapper

    // Pagination
    if ($pagination) {
        $output .= '<div class="swiper-pagination"></div>';
    }

    // Navigation - Aplicar el color y fondo personalizado a las flechas
    if ($navigation) {
        $output .= '<div class="swiper-button-prev ' . \e($arrowStyleClass) . '"></div>';
        $output .= '<div class="swiper-button-next ' . \e($arrowStyleClass) . '"></div>';

        // Verificar si es estilo minimalista (sin fondo)
        $isMinimal = strpos($arrowStyleClass, 'arrow-minimal') !== false;

        // Agregar estilos CSS inline para personalización de flechas
        $customStyles = '<style>';

        // Color de las flechas - aplicar SIEMPRE
        $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-prev,';
        $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-next,';
        $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev,';
        $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next,';
        $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-prev::after,';
        $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-next::after,';
        $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev::after,';
        $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next::after {';
        $customStyles .= ' color: ' . \e($arrowColor) . ' !important; }';

        // Fondo de las flechas - NO aplicar si es minimalista
        if ($isMinimal) {
            // Forzar transparente para estilos minimalistas
            $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-prev,';
            $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-next,';
            $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev,';
            $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next {';
            $customStyles .= ' background-color: transparent !important; background: transparent !important; box-shadow: none !important; }';

            // Tamaños específicos para minimalistas
            if ($arrowStyleClass === 'arrow-minimal-large') {
                // Flecha grande: contenedor 56px, icono 18px
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev,';
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next {';
                $customStyles .= ' width: 56px !important; height: 56px !important; }';
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev::after,';
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next::after {';
                $customStyles .= ' width: 18px !important; height: 18px !important; border-width: 4px !important; }';
            } else {
                // Flecha pequeña: contenedor 40px, icono 12px
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev,';
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next {';
                $customStyles .= ' width: 40px !important; height: 40px !important; }';
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev::after,';
                $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next::after {';
                $customStyles .= ' width: 12px !important; height: 12px !important; border-width: 3px !important; }';
            }
        } else {
            // Aplicar color de fondo normal
            $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-prev,';
            $customStyles .= '.slider-' . $sliderId . '.swiper .swiper-button-next,';
            $customStyles .= '.slider-' . $sliderId . ' .swiper-button-prev,';
            $customStyles .= '.slider-' . $sliderId . ' .swiper-button-next {';
            $customStyles .= ' background-color: ' . \e($arrowBgColor) . ' !important; }';
        }

        $customStyles .= '</style>';
        $output .= $customStyles;
    }

    $output .= '</div>'; // Fin swiper

    // JS inicialización
    // Configuración de efectos especiales
    $effectConfig = '';
    switch ($transitionEffect) {
        case 'fade':
            $effectConfig = 'fadeEffect: { crossFade: true },';
            break;
        case 'cube':
            $effectConfig = 'cubeEffect: { shadow: true, slideShadows: true, shadowOffset: 20, shadowScale: 0.94 },';
            break;
        case 'coverflow':
            $effectConfig = 'coverflowEffect: { rotate: 50, stretch: 0, depth: 100, modifier: 1, slideShadows: true },';
            break;
        case 'flip':
            $effectConfig = 'flipEffect: { slideShadows: true },';
            break;
    }

    $swiperJsPath = $appRoot . '/public/assets/js/swiper-bundle.min.js';
    $swiperJsVersion = is_file($swiperJsPath) ? filemtime($swiperJsPath) : time();
    $output .= '<script src="/assets/js/swiper-bundle.min.js?v=' . $swiperJsVersion . '"></script>';
    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function () {
        new Swiper(".slider-' . $sliderId . '", {
            loop: ' . ($loop ? 'true' : 'false') . ',
            speed: 600,
            autoplay: ' . ($autoplay ? '{ delay: ' . $autoplayDelay . ', disableOnInteraction: false }' : 'false') . ',
            pagination: ' . ($pagination ? '{ el: ".slider-' . $sliderId . ' .swiper-pagination", clickable: true }' : 'false') . ',
            navigation: ' . ($navigation ? '{ nextEl: ".slider-' . $sliderId . ' .swiper-button-next", prevEl: ".slider-' . $sliderId . ' .swiper-button-prev" }' : 'false') . ',
            effect: "' . \e($transitionEffect) . '",
            ' . $effectConfig . '
        });
    });
    </script>';

    // Cerrar wrapper full-width si está activado
    if ($fullWidth) {
        $output .= '</div>'; // Cierra slider-full-width-wrapper
    }

    return $output;
}

    protected static function renderGallery(int $sliderId, array $slides, array $settings): string
    {
        $thumbsPerView = intval($settings['thumbs_per_view'] ?? 5);
        $thumbsHeight = intval($settings['thumbs_height'] ?? 70);
        $mainHeight = intval($settings['height'] ?? 280);
        $theme = $settings['theme'] ?? 'gallery-light';
        $navigation = !empty($settings['navigation']);
        $pagination = !empty($settings['pagination']);
        $arrowStyleClass = !empty($settings['arrows_style']) ? $settings['arrows_style'] : '';
        $arrowColor = $settings['arrows_color'] ?? '#ffffff';
        $arrowBgColor = $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)';
        $fullWidth = !empty($settings['full_width']);

        // Cache-busting para evitar versiones viejas en navegador
        $appRoot = defined('APP_ROOT') ? APP_ROOT : realpath(__DIR__ . '/../../..');
        $swiperCssPath = $appRoot . '/public/assets/css/swiper-bundle.min.css';
        $themesCssPath = $appRoot . '/public/assets/themes/default/css/slider-themes.css';
        $swiperCssVersion = is_file($swiperCssPath) ? filemtime($swiperCssPath) : time();
        $themesCssVersion = is_file($themesCssPath) ? filemtime($themesCssPath) : time();

        $output = '<link rel="stylesheet" href="/assets/css/swiper-bundle.min.css?v=' . $swiperCssVersion . '">';
        $output .= '<link rel="stylesheet" href="/assets/themes/default/css/slider-themes.css?v=' . $themesCssVersion . '">';

        // Wrapper para full-width (a sangre)
        if ($fullWidth) {
            // Full-bleed wrapper robusto con estilos inline para máxima compatibilidad
            $output .= '<div class="slider-full-width-wrapper" data-full-bleed="true" style="position:relative !important;left:50% !important;width:100vw !important;max-width:100vw !important;margin-left:-50vw !important;margin-right:-50vw !important;box-sizing:border-box !important;margin-top:0 !important;">';

            // Añadir script para forzar overflow visible en contenedores padre y eliminar márgenes/paddings
            $output .= '<script>
            (function(){
                var wrapper = document.querySelector(\'.slider-full-width-wrapper[data-full-bleed="true"]\');
                if(wrapper){
                    var parent = wrapper.parentElement;
                    while(parent && parent !== document.body){
                        parent.style.overflow = "visible";
                        parent.style.overflowX = "visible";
                        parent.style.paddingTop = "0";
                        parent.style.marginTop = "0";
                        parent = parent.parentElement;
                    }
                    var main = document.querySelector("main");
                    if(main){ main.style.paddingTop = "0"; main.style.marginTop = "0"; }
                    var containers = document.querySelectorAll(".container.py-4, .page-container, .has-slider-content");
                    containers.forEach(function(c){ c.style.paddingTop = "0"; c.style.marginTop = "0"; });
                    var header = document.querySelector("header, .musedock-header");
                    if(header){ header.style.marginBottom = "0"; header.style.borderBottom = "none"; }
                    var prev = wrapper.previousElementSibling;
                    while(prev){
                        if(prev.tagName === "P" && prev.textContent.trim() === ""){
                            prev.style.display = "none";
                        }
                        prev = prev.previousElementSibling;
                    }
                }
            })();
            </script>';
        }

        $output .= '<div class="gallery-container theme-' . \e($theme) . '" style="display:flex;flex-direction:column;gap:0;margin:0;padding:0;">';

        // Main swiper con altura configurable
        $output .= '<div class="swiper slider-' . $sliderId . '-main gallery-top" style="width:100%;height:' . $mainHeight . 'px;">';
        $output .= '<div class="swiper-wrapper">';
        foreach ($slides as $slide) {
            $output .= '<div class="swiper-slide"><img src="' . \e($slide->image_url) . '" alt="" style="width:100%;height:100%;object-fit:cover;"></div>';
        }
        $output .= '</div>';

        // Paginación si está activada
        if ($pagination) {
            $output .= '<div class="swiper-pagination"></div>';
        }

        // Flechas con estilo personalizado
        if ($navigation) {
            $output .= '<div class="swiper-button-prev ' . \e($arrowStyleClass) . '"></div>';
            $output .= '<div class="swiper-button-next ' . \e($arrowStyleClass) . '"></div>';
        }
        $output .= '</div>';

        // Thumbnails con estilos que coinciden con el preview
        $output .= '<div class="swiper slider-' . $sliderId . '-thumbs gallery-thumbs" style="width:100%;height:' . $thumbsHeight . 'px;box-sizing:border-box;padding:5px 0;">';
        $output .= '<div class="swiper-wrapper">';
        foreach ($slides as $slide) {
            $output .= '<div class="swiper-slide" style="width:80px;height:60px;border:2px solid transparent;border-radius:4px;overflow:hidden;"><img src="' . \e($slide->image_url) . '" alt="" style="width:100%;height:100%;object-fit:cover;"></div>';
        }
        $output .= '</div></div></div>';

        // Estilos personalizados para flechas - SIEMPRE aplicar
        if ($navigation) {
            $customStyles = '<style>';

            // Color de las flechas - aplicar SIEMPRE
            $customStyles .= '.slider-' . $sliderId . '-main .swiper-button-prev,';
            $customStyles .= '.slider-' . $sliderId . '-main .swiper-button-next,';
            $customStyles .= '.slider-' . $sliderId . '-main .swiper-button-prev::after,';
            $customStyles .= '.slider-' . $sliderId . '-main .swiper-button-next::after {';
            $customStyles .= ' color: ' . \e($arrowColor) . ' !important; }';

            // Fondo de las flechas - aplicar SIEMPRE
            $customStyles .= '.slider-' . $sliderId . '-main .swiper-button-prev,';
            $customStyles .= '.slider-' . $sliderId . '-main .swiper-button-next {';
            $customStyles .= ' background-color: ' . \e($arrowBgColor) . ' !important; }';

            $customStyles .= '</style>';
            $output .= $customStyles;
        }

        $swiperJsPath = $appRoot . '/public/assets/js/swiper-bundle.min.js';
        $swiperJsVersion = is_file($swiperJsPath) ? filemtime($swiperJsPath) : time();
        $output .= '<script src="/assets/js/swiper-bundle.min.js?v=' . $swiperJsVersion . '"></script>';
        $output .= '<script>
        document.addEventListener("DOMContentLoaded", function () {
            var thumbs = new Swiper(".slider-' . $sliderId . '-thumbs", {
                spaceBetween: 10,
                slidesPerView: "auto",
                freeMode: true,
                watchSlidesProgress: true,
                centerInsufficientSlides: true
            });

            new Swiper(".slider-' . $sliderId . '-main", {
                spaceBetween: 10,
                ' . ($pagination ? 'pagination: { el: ".slider-' . $sliderId . '-main .swiper-pagination", clickable: true },' : '') . '
                ' . ($navigation ? 'navigation: { nextEl: ".slider-' . $sliderId . '-main .swiper-button-next", prevEl: ".slider-' . $sliderId . '-main .swiper-button-prev" },' : '') . '
                thumbs: { swiper: thumbs },
            });
        });
        </script>';

        // Cerrar wrapper full-width si está activado
        if ($fullWidth) {
            $output .= '</div>'; // Cierra slider-full-width-wrapper
        }

        return $output;
    }

    public static function processShortcodes(?string $content): string
    {
        if (empty($content)) return '';

        // PASO 1: Procesar shortcodes de slider y reemplazar por marcadores temporales
        $pattern = '/\[slider\s+id=["\']?(\d+)["\']?\s*\]/i';
        $sliders = [];
        $content = preg_replace_callback($pattern, function ($matches) use (&$sliders) {
            $sliderId = (int)$matches[1];
            $marker = '<!--SLIDER_PLACEHOLDER_' . $sliderId . '_' . count($sliders) . '-->';
            $sliders[] = [
                'marker' => $marker,
                'html' => (string) self::renderSlider($sliderId)
            ];
            return $marker;
        }, $content) ?? $content;

        // PASO 2: Limpiar cualquier <p> que envuelva los marcadores de slider
        // Esto evita que el HTML del slider quede dentro de párrafos
        foreach ($sliders as $slider) {
            // Eliminar <p> que envuelven el marcador (puede haber espacios/saltos de línea)
            $content = preg_replace(
                '/<p[^>]*>\s*' . preg_quote($slider['marker'], '/') . '\s*<\/p>/is',
                $slider['marker'],
                $content
            );
        }

        // PASO 3: Reemplazar los marcadores por el HTML real del slider
        foreach ($sliders as $slider) {
            $content = str_replace($slider['marker'], $slider['html'], $content);
        }

        return $content;
    }
}
