<?php
/**
 * CTA (Call to Action) Element Template
 *
 * Available variables:
 * - $element: The Element model instance
 * - $type: Element type ('cta')
 * - $layout: Layout type ('horizontal', 'centered', 'box')
 * - $data: Element data array
 * - $settings: Element settings array
 */

$heading = $data['heading'] ?? '';
$text = $data['text'] ?? '';
$buttonText = $data['button_text'] ?? '';
$buttonUrl = $data['button_url'] ?? '';
$backgroundColor = $data['background_color'] ?? '';
$textColor = $data['text_color'] ?? '';
$buttonStyle = $data['button_style'] ?? 'primary';
$containerClass = 'element-cta layout-' . ($layout ?? 'horizontal');
?>

<section class="<?= escape_html($containerClass) ?>"
         style="<?= $backgroundColor ? 'background-color: ' . escape_html($backgroundColor) . '; ' : '' ?><?= $textColor ? 'color: ' . escape_html($textColor) . '; ' : '' ?>">
    <div class="container">
        <?php if ($layout === 'horizontal' || !$layout): ?>
            <div class="cta-content">
                <?php if ($heading): ?>
                    <h2><?= escape_html($heading) ?></h2>
                <?php endif; ?>
                <?php if ($text): ?>
                    <p><?= escape_html($text) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($buttonText && $buttonUrl): ?>
                <div class="cta-action">
                    <a href="<?= escape_html($buttonUrl) ?>" class="cta-btn">
                        <?= escape_html($buttonText) ?>
                    </a>
                </div>
            <?php endif; ?>

        <?php elseif ($layout === 'centered'): ?>
            <div class="cta-content-centered">
                <?php if ($heading): ?>
                    <h2><?= escape_html($heading) ?></h2>
                <?php endif; ?>
                <?php if ($text): ?>
                    <p><?= escape_html($text) ?></p>
                <?php endif; ?>
                <?php if ($buttonText && $buttonUrl): ?>
                    <a href="<?= escape_html($buttonUrl) ?>" class="cta-btn">
                        <?= escape_html($buttonText) ?>
                    </a>
                <?php endif; ?>
            </div>

        <?php elseif ($layout === 'box'): ?>
            <div class="cta-box">
                <?php if ($heading): ?>
                    <h2><?= escape_html($heading) ?></h2>
                <?php endif; ?>
                <?php if ($text): ?>
                    <p><?= escape_html($text) ?></p>
                <?php endif; ?>
                <?php if ($buttonText && $buttonUrl): ?>
                    <a href="<?= escape_html($buttonUrl) ?>" class="cta-btn">
                        <?= escape_html($buttonText) ?>
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Fallback layout -->
            <div class="cta-content">
                <?php if ($heading): ?>
                    <h2><?= escape_html($heading) ?></h2>
                <?php endif; ?>
                <?php if ($text): ?>
                    <p><?= escape_html($text) ?></p>
                <?php endif; ?>
                <?php if ($buttonText && $buttonUrl): ?>
                    <a href="<?= escape_html($buttonUrl) ?>" class="cta-btn">
                        <?= escape_html($buttonText) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
