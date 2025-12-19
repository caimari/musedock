<?php
/**
 * Highlight Section Element Template
 *
 * A section for highlighting important content with eyebrow, title,
 * main text, secondary text, and optional soft CTA.
 *
 * Available variables:
 * - $element: The Element model instance
 * - $type: Element type ('highlight')
 * - $layout: Layout type ('centered', 'left', 'right')
 * - $data: Element data array
 * - $settings: Element settings array
 */

// Content fields
$eyebrow = $data['eyebrow'] ?? '';
$title = $data['title'] ?? '';
$mainText = $data['main_text'] ?? '';
$secondaryText = $data['secondary_text'] ?? '';
$ctaText = $data['cta_text'] ?? '';
$ctaUrl = $data['cta_url'] ?? '';
$ctaTarget = $data['cta_target'] ?? '_self';
$ctaDisplayStyle = $data['cta_style'] ?? 'link'; // link, button, button_rounded, button_outline

// Style options - default to transparent background
$backgroundStyle = $data['background_style'] ?? 'transparent';
$alignment = $data['alignment'] ?? 'center';
$contentWidth = $data['content_width'] ?? 'medium';
$fullWidth = isset($data['full_width']) && $data['full_width'] && $data['full_width'] !== '0';

// Background image
$backgroundImage = $data['background_image'] ?? '';
$backgroundOverlay = $data['background_overlay'] ?? '0.5';

// Decorative icon
$showIcon = isset($data['show_icon']) && $data['show_icon'] && $data['show_icon'] !== '0';
$iconType = $data['icon_type'] ?? 'rocket';
$iconColor = $data['icon_color'] ?? '#6366f1';

// Icon SVG mapping
$iconSvgs = [
    'rocket' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>',
    'star' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
    'heart' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
    'lightbulb' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg>',
    'trophy' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
    'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'gem' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9Z"/><path d="M11 3 8 9l4 13 4-13-3-6"/><path d="M2 9h20"/></svg>',
    'fire' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 23c-3.866 0-7-3.134-7-7 0-2.485 1.371-4.678 3.398-6.076.64-.44 1.532-.065 1.627.706.068.548.154 1.034.268 1.443C11.004 9.467 12 7.5 12 5c0-.624.312-1.21.83-1.56.518-.349 1.183-.422 1.768-.195C17.893 4.461 21 7.757 21 12.5c0 5.28-4.03 10.5-9 10.5z"/></svg>',
    'bolt' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
    'chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>',
    'globe' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
];
$iconSvg = $iconSvgs[$iconType] ?? $iconSvgs['rocket'];

// Color customization - check for non-default values
$eyebrowColor = $data['eyebrow_color'] ?? '';
$titleColor = $data['title_color'] ?? '';
$mainTextColor = $data['main_text_color'] ?? '';
$secondaryTextColor = $data['secondary_text_color'] ?? '';
$ctaColor = $data['cta_color'] ?? '';
$backgroundColor = $data['background_color'] ?? '';

// Font customization
$eyebrowFont = $data['eyebrow_font'] ?? '';
$titleFont = $data['title_font'] ?? '';
$mainTextFont = $data['main_text_font'] ?? '';
$secondaryTextFont = $data['secondary_text_font'] ?? '';
$ctaFont = $data['cta_font'] ?? '';

// Italic options
$eyebrowItalic = !empty($data['eyebrow_italic']);
$titleItalic = !empty($data['title_italic']);
$mainTextItalic = !empty($data['main_text_italic']);
$secondaryTextItalic = !empty($data['secondary_text_italic']);
$ctaItalic = !empty($data['cta_italic']);

// Build container class
$containerClass = 'element-highlight';
$containerClass .= ' layout-' . ($layout ?? 'centered');
// Add bg-X class for CSS rules (targeting text colors etc), but background is on body only
if (!empty($backgroundStyle) && $backgroundStyle !== 'transparent') {
    $containerClass .= ' bg-' . escape_html($backgroundStyle);
}
$containerClass .= ' align-' . escape_html($alignment);
$containerClass .= ' width-' . escape_html($contentWidth);
if ($fullWidth) {
    $containerClass .= ' full-width';
}
if ($backgroundImage) {
    $containerClass .= ' has-bg-image';
}
if ($showIcon) {
    $containerClass .= ' has-icon';
}

// Build body background style - only apply to highlight-body
$bodyStyles = [];
if ($backgroundColor && $backgroundColor !== '#f8fafc') {
    $bodyStyles[] = 'background-color: ' . escape_html($backgroundColor) . ' !important';
}
if ($backgroundImage) {
    $bodyStyles[] = 'background-image: url(' . escape_html($backgroundImage) . ')';
    $bodyStyles[] = 'background-size: cover';
    $bodyStyles[] = 'background-position: center';
    $bodyStyles[] = 'position: relative';
}
$bodyStyleAttr = !empty($bodyStyles) ? implode('; ', $bodyStyles) : '';

// Helper function to build font style
if (!function_exists('buildHighlightFontStyle')) {
    function buildHighlightFontStyle($font, $italic, $color) {
        $styles = [];
        if ($font) {
            $styles[] = "font-family: '{$font}', sans-serif";
        }
        if ($italic) {
            $styles[] = 'font-style: italic';
        }
        if ($color) {
            $styles[] = 'color: ' . $color;
        }
        return implode('; ', $styles);
    }
}

// Build individual element styles
$eyebrowStyle = buildHighlightFontStyle($eyebrowFont, $eyebrowItalic, $eyebrowColor);
$titleStyle = buildHighlightFontStyle($titleFont, $titleItalic, $titleColor);
$mainTextStyle = buildHighlightFontStyle($mainTextFont, $mainTextItalic, $mainTextColor);
$secondaryTextStyle = buildHighlightFontStyle($secondaryTextFont, $secondaryTextItalic, $secondaryTextColor);
$ctaStyle = buildHighlightFontStyle($ctaFont, $ctaItalic, $ctaColor);

// Load Google Fonts if custom fonts are used
$usedFonts = array_filter([$eyebrowFont, $titleFont, $mainTextFont, $secondaryTextFont, $ctaFont]);
$uniqueFonts = array_unique($usedFonts);
?>
<?php if (!empty($uniqueFonts)): ?>
<link href="https://fonts.googleapis.com/css2?family=<?= implode('&family=', array_map(function($f) { return str_replace(' ', '+', $f) . ':wght@400;500;600;700'; }, $uniqueFonts)) ?>&display=swap" rel="stylesheet">
<?php endif; ?>

<section class="<?= escape_html($containerClass) ?>">
    <?php if ($eyebrow): ?>
    <div class="highlight-header">
        <div class="container">
            <span class="highlight-eyebrow"<?= $eyebrowStyle ? ' style="' . escape_html($eyebrowStyle) . '"' : '' ?>><?= escape_html($eyebrow) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <div class="highlight-body"<?= $bodyStyleAttr ? ' style="' . $bodyStyleAttr . '"' : '' ?>>
        <?php if ($backgroundImage): ?>
        <div class="highlight-overlay" style="background: rgba(0,0,0,<?= escape_html($backgroundOverlay) ?>);"></div>
        <?php endif; ?>
        <div class="container">
            <div class="highlight-wrapper">
                <?php if ($showIcon): ?>
                <div class="highlight-icon" style="color: <?= escape_html($iconColor) ?>;">
                    <?= $iconSvg ?>
                </div>
                <?php endif; ?>
                <div class="highlight-content">
                    <?php if ($title): ?>
                        <h2 class="highlight-title"<?= $titleStyle ? ' style="' . escape_html($titleStyle) . '"' : '' ?>><?= escape_html($title) ?></h2>
                    <?php endif; ?>

                    <?php if ($mainText): ?>
                        <p class="highlight-main-text"<?= $mainTextStyle ? ' style="' . escape_html($mainTextStyle) . '"' : '' ?>><?= nl2br(escape_html($mainText)) ?></p>
                    <?php endif; ?>

                    <?php if ($secondaryText): ?>
                        <p class="highlight-secondary-text"<?= $secondaryTextStyle ? ' style="' . escape_html($secondaryTextStyle) . '"' : '' ?>><?= nl2br(escape_html($secondaryText)) ?></p>
                    <?php endif; ?>

                    <?php if ($ctaText && $ctaUrl): ?>
                        <?php
                        // Determine CTA class based on style
                        $isButton = in_array($ctaDisplayStyle, ['button', 'button_rounded', 'button_outline']);
                        $ctaClass = 'highlight-cta-link'; // default
                        if ($ctaDisplayStyle === 'button') {
                            $ctaClass = 'highlight-cta-btn';
                        } elseif ($ctaDisplayStyle === 'button_rounded') {
                            $ctaClass = 'highlight-cta-btn btn-rounded';
                        } elseif ($ctaDisplayStyle === 'button_outline') {
                            $ctaClass = 'highlight-cta-btn btn-outline';
                        }

                        // Build CTA inline style
                        // For buttons: use custom color as background-color
                        // For links: use custom color as text color
                        $ctaInlineStyles = [];
                        if ($ctaFont) {
                            $ctaInlineStyles[] = "font-family: '{$ctaFont}', sans-serif";
                        }
                        if ($ctaItalic) {
                            $ctaInlineStyles[] = 'font-style: italic';
                        }
                        if ($ctaColor) {
                            if ($isButton && $ctaDisplayStyle !== 'button_outline') {
                                // For filled buttons, color becomes background
                                $ctaInlineStyles[] = 'background-color: ' . $ctaColor . ' !important';
                            } elseif ($ctaDisplayStyle === 'button_outline') {
                                // For outline buttons, color is border and text (all 4 sides)
                                $ctaInlineStyles[] = 'border: 1px solid ' . $ctaColor . ' !important';
                                $ctaInlineStyles[] = 'color: ' . $ctaColor . ' !important';
                                $ctaInlineStyles[] = 'background: transparent !important';
                            } else {
                                // For links, just text color
                                $ctaInlineStyles[] = 'color: ' . $ctaColor;
                            }
                        }
                        $ctaStyleInline = !empty($ctaInlineStyles) ? implode('; ', $ctaInlineStyles) : '';
                        ?>
                        <div class="highlight-cta">
                            <a href="<?= escape_html($ctaUrl) ?>"
                               target="<?= escape_html($ctaTarget) ?>"
                               class="<?= escape_html($ctaClass) ?>"
                               <?= $ctaStyleInline ? ' style="' . escape_html($ctaStyleInline) . '"' : '' ?>>
                                <?= escape_html($ctaText) ?>
                                <svg class="highlight-cta-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 8H13M13 8L9 4M13 8L9 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="highlight-footer"></div>
</section>
