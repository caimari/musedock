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
$backgroundColor = $data['background_color'] ?? '#0d6efd';
$textColor = $data['text_color'] ?? '#ffffff';
$buttonStyle = $data['button_style'] ?? 'primary';
?>

<section class="cta-element cta-<?= escape_html($layout ?? 'horizontal') ?> py-5">
    <div class="container">
        <?php if ($layout === 'horizontal'): ?>
            <div class="cta-horizontal rounded p-4"
                 style="background-color: <?= escape_html($backgroundColor) ?>; color: <?= escape_html($textColor) ?>;">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <?php if ($heading): ?>
                            <h3 class="cta-heading mb-2"><?= escape_html($heading) ?></h3>
                        <?php endif; ?>
                        <?php if ($text): ?>
                            <p class="cta-text mb-lg-0"><?= escape_html($text) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                        <?php if ($buttonText && $buttonUrl): ?>
                            <a href="<?= escape_html($buttonUrl) ?>"
                               class="btn btn-<?= $buttonStyle === 'outline' ? 'outline-light' : ($buttonStyle === 'secondary' ? 'secondary' : 'light') ?> btn-lg">
                                <?= escape_html($buttonText) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($layout === 'centered'): ?>
            <div class="cta-centered text-center rounded p-5"
                 style="background-color: <?= escape_html($backgroundColor) ?>; color: <?= escape_html($textColor) ?>;">
                <?php if ($heading): ?>
                    <h2 class="cta-heading display-5 fw-bold mb-3"><?= escape_html($heading) ?></h2>
                <?php endif; ?>
                <?php if ($text): ?>
                    <p class="cta-text lead mb-4"><?= escape_html($text) ?></p>
                <?php endif; ?>
                <?php if ($buttonText && $buttonUrl): ?>
                    <a href="<?= escape_html($buttonUrl) ?>"
                       class="btn btn-<?= $buttonStyle === 'outline' ? 'outline-light' : ($buttonStyle === 'secondary' ? 'secondary' : 'light') ?> btn-lg">
                        <?= escape_html($buttonText) ?>
                    </a>
                <?php endif; ?>
            </div>

        <?php elseif ($layout === 'box'): ?>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="cta-box border rounded p-4 text-center"
                         style="border-color: <?= escape_html($backgroundColor) ?> !important; border-width: 2px !important;">
                        <?php if ($heading): ?>
                            <h3 class="cta-heading mb-3" style="color: <?= escape_html($backgroundColor) ?>;">
                                <?= escape_html($heading) ?>
                            </h3>
                        <?php endif; ?>
                        <?php if ($text): ?>
                            <p class="cta-text mb-4"><?= escape_html($text) ?></p>
                        <?php endif; ?>
                        <?php if ($buttonText && $buttonUrl): ?>
                            <a href="<?= escape_html($buttonUrl) ?>"
                               class="btn btn-<?= $buttonStyle === 'outline' ? 'outline-' : '' ?><?= $buttonStyle === 'secondary' ? 'secondary' : 'primary' ?> btn-lg"
                               style="<?= $buttonStyle !== 'outline' ? 'background-color: ' . escape_html($backgroundColor) . '; border-color: ' . escape_html($backgroundColor) . ';' : '' ?>">
                                <?= escape_html($buttonText) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Default layout -->
            <div class="cta-default text-center py-4">
                <?php if ($heading): ?>
                    <h3 class="cta-heading mb-3"><?= escape_html($heading) ?></h3>
                <?php endif; ?>
                <?php if ($text): ?>
                    <p class="cta-text mb-3"><?= escape_html($text) ?></p>
                <?php endif; ?>
                <?php if ($buttonText && $buttonUrl): ?>
                    <a href="<?= escape_html($buttonUrl) ?>" class="btn btn-primary btn-lg">
                        <?= escape_html($buttonText) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
