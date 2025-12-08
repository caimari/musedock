<?php

namespace Screenart\Musedock\Helpers;

use Screenart\Musedock\Models\Slider;
use Screenart\Musedock\Models\Slide;

class SliderHelper
{
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

    $autoplay = !empty($settings['autoplay']);
    $autoplayDelay = intval($settings['autoplay_delay'] ?? 3000);
    $loop = !empty($settings['loop']);
    $pagination = !empty($settings['pagination']);
    $navigation = !empty($settings['navigation']);

    $arrowStyleClass = !empty($settings['arrows_style']) ? $settings['arrows_style'] : '';
    $arrowColor = $settings['arrows_color'] ?? '#ffffff';
    $arrowBgColor = $settings['arrows_bg_color'] ?? 'rgba(255,255,255,0.9)';

    $output = '<link rel="stylesheet" href="/assets/css/swiper-bundle.min.css">';
    $output .= '<link rel="stylesheet" href="/assets/themes/default/css/slider-themes.css">';
    $output .= '<div class="swiper slider-' . $sliderId . ' theme-' . \e($theme) . '" style="width:100%;height:' . intval($height) . 'px;">';
    $output .= '<div class="swiper-wrapper">';

    foreach ($slides as $slide) {
        $output .= '<div class="swiper-slide" style="position:relative;">';
        $output .= '<img src="' . \e($slide->image_url) . '" style="width:100%;height:100%;object-fit:cover;">';

        // Solo mostrar caption si está activado y hay contenido
        if (!empty($settings['show_caption']) && ($slide->title || $slide->description)) {
            // Configurar estilo básico del caption con todos los estilos personalizados
            $captionStyle = 'position:absolute;z-index:15;';
            
            // Aplicar posición según selección
            switch ($captionPosition) {
                case 'top-left':    $captionStyle .= 'top:20px;left:20px;text-align:left;'; break;
                case 'top-right':   $captionStyle .= 'top:20px;right:20px;text-align:right;'; break;
                case 'bottom-left': $captionStyle .= 'bottom:20px;left:20px;text-align:left;'; break;
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
                $titleStyle = 'font-size:' . $titleSize . ';font-weight:bold;color:' . $captionColor . ' !important;';
                
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
                $descStyle = 'font-size:' . $descriptionSize . ';margin-top:6px;color:' . $captionColor . ' !important;';
                $output .= '<div style="' . $descStyle . '">' . \e($slide->description) . '</div>';
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

    $output .= '<script src="/assets/js/swiper-bundle.min.js"></script>';
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

        $output = '<link rel="stylesheet" href="/assets/css/swiper-bundle.min.css">';
        $output .= '<link rel="stylesheet" href="/assets/themes/default/css/slider-themes.css">';
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

        $output .= '<script src="/assets/js/swiper-bundle.min.js"></script>';
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

        return $output;
    }

    public static function processShortcodes(?string $content): string
    {
        if (empty($content)) return '';

        $pattern = '/\[slider\s+id=["\']?(\d+)["\']?\s*\]/i';
        return preg_replace_callback($pattern, function ($matches) {
            return (string) self::renderSlider((int)$matches[1]);
        }, $content) ?? $content;
    }
}
