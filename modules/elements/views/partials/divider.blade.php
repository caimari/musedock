<?php
/**
 * Section Divider Element Template
 *
 * A decorative separator between sections with various styles:
 * spacer, line, dots, zigzag, wave, arrows, diamonds, mountains, clouds, triangle, curve, tilt, drops
 *
 * Available variables:
 * - $element: The Element model instance
 * - $type: Element type ('divider')
 * - $layout: Layout type (spacer, line, dots, zigzag, wave, arrows, diamonds, mountains, clouds, triangle, curve, tilt, drops)
 * - $data: Element data array
 * - $settings: Element settings array
 */

// Layout/style
$dividerLayout = $layout ?? 'spacer';

// Height settings
$height = $data['height'] ?? 'medium';
$customHeight = $data['custom_height'] ?? '40';

// Color settings (shape color)
$color = $data['color'] ?? 'default';
$customColor = $data['custom_color'] ?? '#e5e7eb';

// Background color settings (for shape dividers)
$bgColor = $data['bg_color'] ?? 'transparent';
$customBgColor = $data['custom_bg_color'] ?? '#ffffff';

// Flip option (vertical inversion)
$flip = isset($data['flip']) && $data['flip'] && $data['flip'] !== '0';

// Line-specific settings
$lineStyle = $data['line_style'] ?? 'solid';
$lineThickness = $data['line_thickness'] ?? 'medium';

// Pattern-specific settings
$patternSize = $data['pattern_size'] ?? 'medium';

// Options
$fullWidth = isset($data['full_width']) && $data['full_width'] && $data['full_width'] !== '0';
$animate = isset($data['animate']) && $data['animate'] && $data['animate'] !== '0';

// Calculate height value
$heightValues = [
    'small' => '20px',
    'medium' => '40px',
    'large' => '60px',
    'xlarge' => '100px',
    'xxlarge' => '150px',
    'custom' => $customHeight . 'px'
];
$heightValue = $heightValues[$height] ?? '40px';

// Calculate color value (shape color)
$colorValues = [
    'default' => 'var(--element-divider-color, #e5e7eb)',
    'light' => '#f3f4f6',
    'dark' => '#374151',
    'primary' => 'var(--element-primary-color, #3b82f6)',
    'gradient' => 'linear-gradient(90deg, transparent, var(--element-primary-color, #3b82f6), transparent)',
    'white' => '#ffffff',
    'custom' => $customColor
];
$colorValue = $colorValues[$color] ?? '#e5e7eb';

// Calculate background color value
$bgColorValues = [
    'transparent' => 'transparent',
    'white' => '#ffffff',
    'light' => '#f3f4f6',
    'dark' => '#1f2937',
    'primary' => 'var(--element-primary-color, #3b82f6)',
    'custom' => $customBgColor
];
$bgColorValue = $bgColorValues[$bgColor] ?? 'transparent';

// Line thickness values
$thicknessValues = [
    'thin' => '1px',
    'medium' => '2px',
    'thick' => '4px'
];
$thicknessValue = $thicknessValues[$lineThickness] ?? '2px';

// Pattern size values
$patternSizeValues = [
    'small' => '8',
    'medium' => '16',
    'large' => '24'
];
$patternSizeValue = $patternSizeValues[$patternSize] ?? '16';

// Build container class
$containerClass = 'element-divider';
$containerClass .= ' divider-' . escape_html($dividerLayout);
$containerClass .= ' height-' . escape_html($height);
$containerClass .= ' color-' . escape_html($color);
if ($fullWidth) {
    $containerClass .= ' full-width';
}
if ($animate) {
    $containerClass .= ' animate';
}
if ($flip) {
    $containerClass .= ' flipped';
}

// Build inline styles
$containerStyles = [];
$containerStyles[] = '--divider-height: ' . $heightValue;
$containerStyles[] = '--divider-color: ' . $colorValue;
$containerStyles[] = '--divider-bg-color: ' . $bgColorValue;
$containerStyles[] = '--divider-thickness: ' . $thicknessValue;
$containerStyles[] = '--divider-pattern-size: ' . $patternSizeValue . 'px';
$containerStyleAttr = implode('; ', $containerStyles);
?>

<div class="<?= escape_html($containerClass) ?>" style="<?= escape_html($containerStyleAttr) ?>">
    <?php if ($dividerLayout === 'spacer'): ?>
        <!-- Simple spacer - just empty space -->
        <div class="divider-spacer"></div>

    <?php elseif ($dividerLayout === 'line'): ?>
        <!-- Line divider -->
        <div class="divider-line divider-line-<?= escape_html($lineStyle) ?>"></div>

    <?php elseif ($dividerLayout === 'dots'): ?>
        <!-- Dots pattern -->
        <div class="divider-pattern divider-dots">
            <svg class="divider-svg" viewBox="0 0 100 20" preserveAspectRatio="xMidYMid slice">
                <pattern id="dots-<?= $element->id ?? uniqid() ?>" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                    <circle cx="10" cy="10" r="3" fill="currentColor"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#dots-<?= $element->id ?? uniqid() ?>)"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'zigzag'): ?>
        <!-- Zigzag pattern -->
        <div class="divider-pattern divider-zigzag">
            <svg class="divider-svg" viewBox="0 0 100 20" preserveAspectRatio="xMidYMid slice">
                <pattern id="zigzag-<?= $element->id ?? uniqid() ?>" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                    <polyline points="0,15 10,5 20,15" fill="none" stroke="currentColor" stroke-width="2"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#zigzag-<?= $element->id ?? uniqid() ?>)"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'wave'): ?>
        <!-- Wave shape -->
        <div class="divider-shape divider-wave">
            <svg class="divider-svg" viewBox="0 0 1200 60" preserveAspectRatio="none">
                <path d="M0,30 C150,60 350,0 600,30 C850,60 1050,0 1200,30 L1200,60 L0,60 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'arrows'): ?>
        <!-- Arrows pattern -->
        <div class="divider-pattern divider-arrows">
            <svg class="divider-svg" viewBox="0 0 100 20" preserveAspectRatio="xMidYMid slice">
                <pattern id="arrows-<?= $element->id ?? uniqid() ?>" x="0" y="0" width="30" height="20" patternUnits="userSpaceOnUse">
                    <polyline points="5,15 15,5 25,15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#arrows-<?= $element->id ?? uniqid() ?>)"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'diamonds'): ?>
        <!-- Diamonds pattern -->
        <div class="divider-pattern divider-diamonds">
            <svg class="divider-svg" viewBox="0 0 100 20" preserveAspectRatio="xMidYMid slice">
                <pattern id="diamonds-<?= $element->id ?? uniqid() ?>" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                    <polygon points="10,2 18,10 10,18 2,10" fill="currentColor"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#diamonds-<?= $element->id ?? uniqid() ?>)"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'mountains'): ?>
        <!-- Mountains shape divider - realistic mountain silhouette -->
        <div class="divider-shape divider-mountains">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,120 L0,80 L100,40 L180,70 L280,20 L380,60 L450,30 L550,55 L650,10 L750,50 L850,25 L950,65 L1050,15 L1150,55 L1200,35 L1200,120 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'clouds'): ?>
        <!-- Clouds shape divider - fluffy cloud silhouette -->
        <div class="divider-shape divider-clouds">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,120 L0,100
                    Q50,100 75,85 Q100,70 130,75 Q160,80 180,70 Q200,60 240,65 Q280,70 300,60
                    Q320,50 360,55 Q400,60 430,50 Q460,40 500,45 Q540,50 570,40
                    Q600,30 650,40 Q700,50 740,45 Q780,40 820,50 Q860,60 900,55
                    Q940,50 980,60 Q1020,70 1060,65 Q1100,60 1140,70 Q1180,80 1200,75
                    L1200,120 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'triangle'): ?>
        <!-- Triangle/Arrow shape divider -->
        <div class="divider-shape divider-triangle">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <polygon points="600,0 1200,120 0,120" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'curve'): ?>
        <!-- Smooth asymmetric curve divider -->
        <div class="divider-shape divider-curve">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,120 L0,60 Q300,120 600,60 Q900,0 1200,60 L1200,120 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'tilt'): ?>
        <!-- Diagonal/Tilt divider -->
        <div class="divider-shape divider-tilt">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <polygon points="0,120 1200,0 1200,120" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'drops'): ?>
        <!-- Water drops/Drips shape divider -->
        <div class="divider-shape divider-drops">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0 L0,60
                    Q60,60 60,90 Q60,120 100,120 Q140,120 140,90 Q140,60 200,60
                    Q260,60 260,80 Q260,100 300,100 Q340,100 340,80 Q340,60 400,60
                    Q460,60 460,100 Q460,120 520,120 Q580,120 580,100 Q580,60 640,60
                    Q700,60 700,75 Q700,90 750,90 Q800,90 800,75 Q800,60 860,60
                    Q920,60 920,95 Q920,120 980,120 Q1040,120 1040,95 Q1040,60 1100,60
                    Q1160,60 1160,80 Q1160,100 1200,100 L1200,0 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'waves_multi'): ?>
        <!-- Multiple waves stacked -->
        <div class="divider-shape divider-waves-multi">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,0 V46.29 C47.79,22.2 103.59,21.2 158.11,35.66 C267.31,65.7 379.43,84.07 498.42,77.48 C618.41,70.89 740.5,42.91 859.32,45.77 C979.54,48.67 1100,94.57 1200,94.57 V0 H0 Z" fill="currentColor" fill-opacity="0.3"/>
                <path d="M0,0 V15.81 C80,47.37 158.65,58.63 248.98,44.74 C365.02,27.57 477.41,3.24 593.15,10.72 C707.83,18.14 820.75,48.82 932.45,56.24 C1044.58,63.69 1150,47.57 1200,40 V0 H0 Z" fill="currentColor" fill-opacity="0.5"/>
                <path d="M0,0 V5.63 C49.93,31.02 123.01,41.42 195.15,35.93 C281.88,29.29 370.94,5.5 458.17,9.87 C554.24,14.72 646.78,49.17 743.69,54.57 C840.96,59.99 940.5,31.94 1036.51,20.89 C1133.05,9.78 1200,20.18 1200,20.18 V0 H0 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'book'): ?>
        <!-- Book/Page fold shape -->
        <div class="divider-shape divider-book">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,120 L0,0 Q600,100 1200,0 L1200,120 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php elseif ($dividerLayout === 'split'): ?>
        <!-- Split/Torn paper effect -->
        <div class="divider-shape divider-split">
            <svg class="divider-svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M0,120 L0,60 L100,65 L200,55 L300,70 L400,50 L500,75 L600,45 L700,70 L800,55 L900,65 L1000,50 L1100,60 L1200,55 L1200,120 Z" fill="currentColor"/>
            </svg>
        </div>

    <?php endif; ?>
</div>
