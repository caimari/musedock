<?php
/**
 * Hero Element Template
 *
 * Available variables:
 * - $element: The Element model instance
 * - $type: Element type ('hero')
 * - $layout: Layout type ('image-right', 'image-left', 'centered', etc.)
 * - $data: Element data array
 * - $settings: Element settings array
 */

$heading = $data['heading'] ?? '';
$subheading = $data['subheading'] ?? '';
$description = $data['description'] ?? '';
$buttonText = $data['button_text'] ?? '';
$buttonUrl = $data['button_url'] ?? '';
$buttonSecondaryText = $data['button_secondary_text'] ?? '';
$buttonSecondaryUrl = $data['button_secondary_url'] ?? '';
$imageUrl = $data['image_url'] ?? '';
$imageAlt = $data['image_alt'] ?? $heading;
$videoUrl = $data['video_url'] ?? '';
$backgroundColor = $data['background_color'] ?? '';
$textColor = $data['text_color'] ?? '';
$minHeight = $data['min_height'] ?? '400';
$alignment = $data['alignment'] ?? 'left';

$containerClass = 'hero-element hero-' . ($layout ?? 'image-right');
$textAlign = 'text-' . $alignment;
?>

<section class="<?= escape_html($containerClass) ?>"
         style="<?= $backgroundColor ? 'background-color: ' . escape_html($backgroundColor) . '; ' : '' ?><?= $minHeight ? 'min-height: ' . escape_html($minHeight) . 'px; ' : '' ?><?= $textColor ? 'color: ' . escape_html($textColor) . '; ' : '' ?>">

    <div class="container py-5">
        <?php if ($layout === 'image-right' || $layout === 'image-left'): ?>
            <div class="row align-items-center g-4 <?= $layout === 'image-left' ? 'flex-row-reverse' : '' ?>">
                <div class="col-lg-6 <?= $textAlign ?>">
                    <?php if ($subheading): ?>
                        <p class="hero-subheading text-muted mb-2"><?= escape_html($subheading) ?></p>
                    <?php endif; ?>

                    <?php if ($heading): ?>
                        <h1 class="hero-heading display-4 fw-bold mb-3"><?= escape_html($heading) ?></h1>
                    <?php endif; ?>

                    <?php if ($description): ?>
                        <p class="hero-description lead mb-4"><?= nl2br(escape_html($description)) ?></p>
                    <?php endif; ?>

                    <?php if ($buttonText && $buttonUrl): ?>
                        <div class="hero-buttons">
                            <a href="<?= escape_html($buttonUrl) ?>" class="btn btn-primary btn-lg me-2 mb-2">
                                <?= escape_html($buttonText) ?>
                            </a>
                            <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
                                <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="btn btn-outline-secondary btn-lg mb-2">
                                    <?= escape_html($buttonSecondaryText) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-6">
                    <?php if ($imageUrl): ?>
                        <img src="<?= escape_html($imageUrl) ?>"
                             alt="<?= escape_html($imageAlt) ?>"
                             class="img-fluid rounded shadow-lg"
                             loading="lazy">
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($layout === 'centered'): ?>
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <?php if ($subheading): ?>
                        <p class="hero-subheading text-muted mb-2"><?= escape_html($subheading) ?></p>
                    <?php endif; ?>

                    <?php if ($heading): ?>
                        <h1 class="hero-heading display-3 fw-bold mb-3"><?= escape_html($heading) ?></h1>
                    <?php endif; ?>

                    <?php if ($description): ?>
                        <p class="hero-description lead mb-4"><?= nl2br(escape_html($description)) ?></p>
                    <?php endif; ?>

                    <?php if ($buttonText && $buttonUrl): ?>
                        <div class="hero-buttons">
                            <a href="<?= escape_html($buttonUrl) ?>" class="btn btn-primary btn-lg me-2 mb-2">
                                <?= escape_html($buttonText) ?>
                            </a>
                            <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
                                <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="btn btn-outline-secondary btn-lg mb-2">
                                    <?= escape_html($buttonSecondaryText) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($imageUrl): ?>
                        <div class="mt-5">
                            <img src="<?= escape_html($imageUrl) ?>"
                                 alt="<?= escape_html($imageAlt) ?>"
                                 class="img-fluid rounded shadow-lg"
                                 loading="lazy">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($layout === 'background'): ?>
            <div class="hero-background-content"
                 style="background-image: url('<?= escape_html($imageUrl) ?>'); background-size: cover; background-position: center; border-radius: 1rem; padding: 5rem 2rem; text-align: center; position: relative;">
                <div style="position: relative; z-index: 2;">
                    <?php if ($subheading): ?>
                        <p class="hero-subheading mb-2 text-white"><?= escape_html($subheading) ?></p>
                    <?php endif; ?>

                    <?php if ($heading): ?>
                        <h1 class="hero-heading display-3 fw-bold mb-3 text-white"><?= escape_html($heading) ?></h1>
                    <?php endif; ?>

                    <?php if ($description): ?>
                        <p class="hero-description lead mb-4 text-white"><?= nl2br(escape_html($description)) ?></p>
                    <?php endif; ?>

                    <?php if ($buttonText && $buttonUrl): ?>
                        <div class="hero-buttons">
                            <a href="<?= escape_html($buttonUrl) ?>" class="btn btn-primary btn-lg me-2 mb-2">
                                <?= escape_html($buttonText) ?>
                            </a>
                            <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
                                <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="btn btn-light btn-lg mb-2">
                                    <?= escape_html($buttonSecondaryText) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); border-radius: 1rem; z-index: 1;"></div>
            </div>

        <?php else: ?>
            <!-- Default layout -->
            <div class="row">
                <div class="col-12 <?= $textAlign ?>">
                    <?php if ($heading): ?>
                        <h1 class="hero-heading display-4 fw-bold mb-3"><?= escape_html($heading) ?></h1>
                    <?php endif; ?>
                    <?php if ($description): ?>
                        <p class="hero-description lead"><?= nl2br(escape_html($description)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
