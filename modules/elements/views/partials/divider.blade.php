<?php
/**
 * Section Divider Element Template
 *
 * A decorative separator between sections with various styles:
 * spacer, line, dots, zigzag, wave, arrows, diamonds
 *
 * Available variables:
 * - $element: The Element model instance
 * - $type: Element type ('divider')
 * - $layout: Layout type ('spacer', 'line', 'dots', 'zigzag', 'wave', 'arrows', 'diamonds')
 * - $data: Element data array
 * - $settings: Element settings array
 */

// Layout/style
$dividerLayout = $layout ?? 'spacer';

// Height settings
$height = $data['height'] ?? 'medium';
$customHeight = $data['custom_height'] ?? '40';

// Color settings
$color = $data['color'] ?? 'default';
$customColor = $data['custom_color'] ?? '#e5e7eb';

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
    'custom' => $customHeight . 'px'
];
$heightValue = $heightValues[$height] ?? '40px';

// Calculate color value
$colorValues = [
    'default' => 'var(--element-divider-color, #e5e7eb)',
    'light' => '#f3f4f6',
    'dark' => '#374151',
    'primary' => 'var(--element-primary-color, #3b82f6)',
    'gradient' => 'linear-gradient(90deg, transparent, var(--element-primary-color, #3b82f6), transparent)',
    'custom' => $customColor
];
$colorValue = $colorValues[$color] ?? '#e5e7eb';

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

// Build inline styles
$containerStyles = [];
$containerStyles[] = '--divider-height: ' . $heightValue;
$containerStyles[] = '--divider-color: ' . $colorValue;
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
    <?php endif; ?>
</div>
