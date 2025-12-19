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
$buttonTarget = $data['button_target'] ?? '_self';
$buttonSecondaryTarget = $data['button_secondary_target'] ?? '_self';
$buttonBgColor = $data['button_bg_color'] ?? '';
$buttonTextColor = $data['button_text_color'] ?? '';
$buttonSecondaryBgColor = $data['button_secondary_bg_color'] ?? '';
$buttonSecondaryTextColor = $data['button_secondary_text_color'] ?? '';
$imageUrl = $data['image_url'] ?? '';
$imageAlt = $data['image_alt'] ?? $heading;
$videoUrl = $data['video_url'] ?? '';
$mediaType = ($data['media_type'] ?? 'image') === 'video' ? 'video' : 'image';
$backgroundColor = $data['background_color'] ?? '';
$textColor = $data['text_color'] ?? '';
$minHeight = $data['min_height'] ?? '400';
$alignment = $data['alignment'] ?? 'left';

// Colores personalizados
$subheadingColor = $data['subheading_color'] ?? '';
$headingColor = $data['heading_color'] ?? '';
$descriptionColor = $data['description_color'] ?? '';
$cardBgColor = $data['card_bg_color'] ?? '';
$cardWrapperBgColor = $data['card_wrapper_bg_color'] ?? '';
$captionColor = $data['caption_color'] ?? '';

// Tipografías personalizadas
$subheadingFont = $data['subheading_font'] ?? '';
$subheadingItalic = !empty($data['subheading_italic']);
$headingFont = $data['heading_font'] ?? '';
$headingItalic = !empty($data['heading_italic']);
$descriptionFont = $data['description_font'] ?? '';
$descriptionItalic = !empty($data['description_italic']);
$captionFont = $data['caption_font'] ?? '';
$captionItalic = !empty($data['caption_italic']);

// Recolectar fuentes para cargar de Google Fonts
$fontsToLoad = [];
foreach ([$subheadingFont, $headingFont, $descriptionFont, $captionFont] as $font) {
    if ($font && !in_array($font, $fontsToLoad)) {
        $fontsToLoad[] = $font;
    }
}

// Helper para generar estilos de texto
if (!function_exists('heroTextStyle')) {
    function heroTextStyle($color, $font, $italic) {
        $styles = [];
        if ($color) {
            $styles[] = 'color: ' . escape_html($color) . ' !important';
        }
        if ($font) {
            $styles[] = 'font-family: "' . escape_html($font) . '", sans-serif !important';
        }
        if ($italic) {
            $styles[] = 'font-style: italic !important';
        }
        return $styles ? ' style="' . implode('; ', $styles) . ';"' : '';
    }
}

$videoEmbedUrl = '';
if ($videoUrl) {
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~', $videoUrl, $matches)) {
        $youtubeId = $matches[1];
        $videoEmbedUrl = 'https://www.youtube.com/embed/' . $youtubeId . '?controls=1&modestbranding=1&rel=0&playsinline=1';
    } elseif (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~', $videoUrl, $matches)) {
        $vimeoId = $matches[1];
        $videoEmbedUrl = 'https://player.vimeo.com/video/' . $vimeoId . '?autoplay=0&muted=0&loop=0&background=0&byline=0&title=0&badge=0';
    }
}

$buttonStyle = [];
if ($buttonBgColor !== '') {
    $buttonStyle[] = 'background-color: ' . escape_html($buttonBgColor) . ';';
}
if ($buttonTextColor !== '') {
    $buttonStyle[] = 'color: ' . escape_html($buttonTextColor) . ';';
}
$buttonStyleAttr = $buttonStyle ? ' style="' . implode(' ', $buttonStyle) . '"' : '';

$buttonSecondaryStyle = [];
if ($buttonSecondaryBgColor !== '') {
    $buttonSecondaryStyle[] = 'background-color: ' . escape_html($buttonSecondaryBgColor) . ';';
}
if ($buttonSecondaryTextColor !== '') {
    $buttonSecondaryStyle[] = 'color: ' . escape_html($buttonSecondaryTextColor) . ';';
}
$buttonSecondaryStyleAttr = $buttonSecondaryStyle ? ' style="' . implode(' ', $buttonSecondaryStyle) . '"' : '';

$buttonTargetAttr = $buttonTarget === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
$buttonSecondaryTargetAttr = $buttonSecondaryTarget === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';

$containerClass = 'element-hero layout-' . ($layout ?? 'image-right');
$textAlign = 'text-' . $alignment;
$sectionStyles = [];
if ($backgroundColor) {
    $sectionStyles[] = 'background-color: ' . escape_html($backgroundColor) . ';';
}
if ($minHeight) {
    $sectionStyles[] = 'min-height: ' . escape_html($minHeight) . 'px;';
}
if ($textColor) {
    $sectionStyles[] = 'color: ' . escape_html($textColor) . ';';
}
if ($layout === 'background' && $imageUrl) {
    $sectionStyles[] = 'background-image: url(\'' . escape_html($imageUrl) . '\');';
}
$sectionStyleAttr = $sectionStyles ? implode(' ', $sectionStyles) : '';
?>

<?php if ($fontsToLoad): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php
$fontParams = [];
foreach ($fontsToLoad as $font) {
    $fontName = str_replace(' ', '+', $font);
    $fontParams[] = 'family=' . $fontName . ':ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700';
}
$googleFontsUrl = 'https://fonts.googleapis.com/css2?' . implode('&', $fontParams) . '&display=swap';
?>
<link href="<?= escape_html($googleFontsUrl) ?>" rel="stylesheet">
<?php endif; ?>

<section class="<?= escape_html($containerClass) ?>"
         style="<?= $sectionStyleAttr ?>">

	    <div class="container">
	        <?php if ($layout === 'image-right' || $layout === 'image-left'): ?>
	            <div class="hero-content">
	                <?php if ($subheading): ?>
	                    <div class="subheading"<?= heroTextStyle($subheadingColor, $subheadingFont, $subheadingItalic) ?>><?= escape_html($subheading) ?></div>
	                <?php endif; ?>

	                <?php if ($heading): ?>
	                    <h1 class="hero-title"<?= heroTextStyle($headingColor, $headingFont, $headingItalic) ?>><?= escape_html($heading) ?></h1>
	                <?php endif; ?>

	                <?php if ($description): ?>
	                    <p class="hero-description"<?= heroTextStyle($descriptionColor, $descriptionFont, $descriptionItalic) ?>><?= nl2br(escape_html($description)) ?></p>
	                <?php endif; ?>

	                <?php if ($buttonText && $buttonUrl): ?>
	                    <div class="hero-buttons">
	                        <a href="<?= escape_html($buttonUrl) ?>" class="hero-btn"<?= $buttonTargetAttr ?><?= $buttonStyleAttr ?>>
	                            <?= escape_html($buttonText) ?>
	                        </a>
	                        <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
	                            <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="hero-btn hero-btn-secondary"<?= $buttonSecondaryTargetAttr ?><?= $buttonSecondaryStyleAttr ?>>
	                                <?= escape_html($buttonSecondaryText) ?>
	                            </a>
	                        <?php endif; ?>
	                    </div>
	                <?php endif; ?>
	            </div>

                <div class="hero-image">
                    <div class="hero-visual"<?= $cardWrapperBgColor ? ' style="--hero-wrapper-bg: ' . escape_html($cardWrapperBgColor) . ';"' : '' ?>>
                        <div class="hero-visual-bg" aria-hidden="true"<?= $cardWrapperBgColor ? ' style="background: linear-gradient(135deg, ' . escape_html($cardWrapperBgColor) . ', ' . escape_html($cardWrapperBgColor) . 'ee) !important;"' : '' ?>></div>
                        <div class="hero-visual-card<?= $mediaType === 'video' ? ' hero-visual-card-media' : '' ?>"<?= $cardBgColor ? ' style="background-color: ' . escape_html($cardBgColor) . ' !important;"' : '' ?>>
                            <?php if ($mediaType === 'video' && ($videoEmbedUrl || $videoUrl)): ?>
                                <div class="hero-visual-video">
                                    <?php if ($videoEmbedUrl): ?>
                                        <iframe class="hero-visual-embed"
                                                src="<?= escape_html($videoEmbedUrl) ?>"
                                                title="Video background"
                                                frameborder="0"
                                                allow="autoplay; fullscreen; picture-in-picture"
                                                allowfullscreen></iframe>
                                    <?php else: ?>
                                        <video class="hero-visual-video-file" controls playsinline>
                                            <source src="<?= escape_html($videoUrl) ?>">
                                        </video>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($imageUrl): ?>
                                <img class="hero-visual-img"
                                     src="<?= escape_html($imageUrl) ?>"
                                     alt="<?= escape_html($imageAlt) ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="hero-visual-placeholder" aria-label="Ilustración corporativa (placeholder)">
                                    <div class="hero-visual-letter">H</div>
                                    <p class="hero-visual-text">
                                        Ilustración corporativa / mapa de EE. UU.<br>
                                        (placeholder visual)
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($imageAlt): ?>
                        <p class="hero-image-caption"<?= heroTextStyle($captionColor, $captionFont, $captionItalic) ?>><?= escape_html($imageAlt) ?></p>
                    <?php endif; ?>
                </div>

        <?php elseif ($layout === 'centered'): ?>
            <div class="hero-centered text-center">
                <?php if ($subheading): ?>
                    <p class="hero-subheading"<?= heroTextStyle($subheadingColor, $subheadingFont, $subheadingItalic) ?>><?= escape_html($subheading) ?></p>
                <?php endif; ?>

                <?php if ($heading): ?>
                    <h1 class="hero-title"<?= heroTextStyle($headingColor, $headingFont, $headingItalic) ?>><?= escape_html($heading) ?></h1>
                <?php endif; ?>

                <?php if ($description): ?>
                    <p class="hero-description"<?= heroTextStyle($descriptionColor, $descriptionFont, $descriptionItalic) ?>><?= nl2br(escape_html($description)) ?></p>
                <?php endif; ?>

                <?php if ($buttonText && $buttonUrl): ?>
                    <div class="hero-buttons">
                        <a href="<?= escape_html($buttonUrl) ?>" class="hero-btn"<?= $buttonTargetAttr ?><?= $buttonStyleAttr ?>>
                            <?= escape_html($buttonText) ?>
                        </a>
                        <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
                            <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="hero-btn hero-btn-secondary"<?= $buttonSecondaryTargetAttr ?><?= $buttonSecondaryStyleAttr ?>>
                                <?= escape_html($buttonSecondaryText) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="hero-image hero-image-centered">
                    <div class="hero-visual"<?= $cardWrapperBgColor ? ' style="--hero-wrapper-bg: ' . escape_html($cardWrapperBgColor) . ';"' : '' ?>>
                        <div class="hero-visual-bg" aria-hidden="true"<?= $cardWrapperBgColor ? ' style="background: linear-gradient(135deg, ' . escape_html($cardWrapperBgColor) . ', ' . escape_html($cardWrapperBgColor) . 'ee) !important;"' : '' ?>></div>
                        <div class="hero-visual-card<?= $mediaType === 'video' ? ' hero-visual-card-media' : '' ?>"<?= $cardBgColor ? ' style="background-color: ' . escape_html($cardBgColor) . ' !important;"' : '' ?>>
                            <?php if ($mediaType === 'video' && ($videoEmbedUrl || $videoUrl)): ?>
                                <div class="hero-visual-video">
                                    <?php if ($videoEmbedUrl): ?>
                                        <iframe class="hero-visual-embed"
                                                src="<?= escape_html($videoEmbedUrl) ?>"
                                                title="Video background"
                                                frameborder="0"
                                                allow="autoplay; fullscreen; picture-in-picture"
                                                allowfullscreen></iframe>
                                    <?php else: ?>
                                        <video class="hero-visual-video-file" controls playsinline>
                                            <source src="<?= escape_html($videoUrl) ?>">
                                        </video>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($imageUrl): ?>
                                <img class="hero-visual-img"
                                     src="<?= escape_html($imageUrl) ?>"
                                     alt="<?= escape_html($imageAlt) ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="hero-visual-placeholder" aria-label="Ilustración corporativa (placeholder)">
                                    <div class="hero-visual-letter">H</div>
                                    <p class="hero-visual-text">
                                        Imagen corporativa / ilustración / mapa de EE. UU.<br>
                                        (placeholder visual centrado)
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($imageAlt): ?>
                        <p class="hero-image-caption"<?= heroTextStyle($captionColor, $captionFont, $captionItalic) ?>><?= escape_html($imageAlt) ?></p>
                    <?php endif; ?>
                </div>
            </div>

	        <?php elseif ($layout === 'background'): ?>
                <?php if ($mediaType === 'video' && ($videoEmbedUrl || $videoUrl)): ?>
                    <div class="hero-media hero-media-video">
                        <?php if ($videoEmbedUrl): ?>
                            <iframe class="hero-video-embed"
                                    src="<?= escape_html($videoEmbedUrl) ?>"
                                    title="Video background"
                                    frameborder="0"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    allowfullscreen></iframe>
                        <?php else: ?>
                            <video class="hero-video-bg" controls playsinline>
                                <source src="<?= escape_html($videoUrl) ?>">
                            </video>
                        <?php endif; ?>
                        <div class="hero-media-content">
                            <?php if ($subheading): ?>
                                <p class="hero-subheading"<?= heroTextStyle($subheadingColor, $subheadingFont, $subheadingItalic) ?>><?= escape_html($subheading) ?></p>
                            <?php endif; ?>

                            <?php if ($heading): ?>
                                <h1 class="hero-title"<?= heroTextStyle($headingColor, $headingFont, $headingItalic) ?>><?= escape_html($heading) ?></h1>
                            <?php endif; ?>

                            <?php if ($description): ?>
                                <p class="hero-description"<?= heroTextStyle($descriptionColor, $descriptionFont, $descriptionItalic) ?>><?= nl2br(escape_html($description)) ?></p>
                            <?php endif; ?>

                            <?php if ($buttonText && $buttonUrl): ?>
                                <div class="hero-buttons">
                                    <a href="<?= escape_html($buttonUrl) ?>" class="hero-btn"<?= $buttonTargetAttr ?><?= $buttonStyleAttr ?>>
                                        <?= escape_html($buttonText) ?>
                                    </a>
                                    <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
                                        <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="hero-btn hero-btn-secondary"<?= $buttonSecondaryTargetAttr ?><?= $buttonSecondaryStyleAttr ?>>
                                            <?= escape_html($buttonSecondaryText) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($imageAlt): ?>
                                <p class="hero-image-caption"<?= heroTextStyle($captionColor, $captionFont, $captionItalic) ?>><?= escape_html($imageAlt) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="hero-media">
                        <div class="hero-media-content">
                            <?php if ($subheading): ?>
                                <p class="hero-subheading"<?= heroTextStyle($subheadingColor, $subheadingFont, $subheadingItalic) ?>><?= escape_html($subheading) ?></p>
                            <?php endif; ?>

                            <?php if ($heading): ?>
                                <h1 class="hero-title"<?= heroTextStyle($headingColor, $headingFont, $headingItalic) ?>><?= escape_html($heading) ?></h1>
                            <?php endif; ?>

                            <?php if ($description): ?>
                                <p class="hero-description"<?= heroTextStyle($descriptionColor, $descriptionFont, $descriptionItalic) ?>><?= nl2br(escape_html($description)) ?></p>
                            <?php endif; ?>

                            <?php if ($buttonText && $buttonUrl): ?>
                                <div class="hero-buttons">
                                    <a href="<?= escape_html($buttonUrl) ?>" class="hero-btn"<?= $buttonTargetAttr ?><?= $buttonStyleAttr ?>>
                                        <?= escape_html($buttonText) ?>
                                    </a>
                                    <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
                                        <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="hero-btn hero-btn-secondary"<?= $buttonSecondaryTargetAttr ?><?= $buttonSecondaryStyleAttr ?>>
                                            <?= escape_html($buttonSecondaryText) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($imageAlt): ?>
                                <p class="hero-image-caption"<?= heroTextStyle($captionColor, $captionFont, $captionItalic) ?>><?= escape_html($imageAlt) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

	        <?php elseif ($layout === 'video'): ?>
                <div class="hero-media hero-media-video">
                    <?php if ($videoEmbedUrl): ?>
                        <iframe class="hero-video-embed"
                                src="<?= escape_html($videoEmbedUrl) ?>"
                                title="Video background"
                                frameborder="0"
                                allow="autoplay; fullscreen; picture-in-picture"
                                allowfullscreen></iframe>
                    <?php elseif ($videoUrl): ?>
                        <video class="hero-video-bg" controls playsinline>
                            <source src="<?= escape_html($videoUrl) ?>">
                        </video>
                    <?php endif; ?>
                    <div class="hero-media-content">
                        <?php if ($subheading): ?>
                            <p class="hero-subheading"<?= heroTextStyle($subheadingColor, $subheadingFont, $subheadingItalic) ?>><?= escape_html($subheading) ?></p>
                        <?php endif; ?>

                        <?php if ($heading): ?>
                            <h1 class="hero-title"<?= heroTextStyle($headingColor, $headingFont, $headingItalic) ?>><?= escape_html($heading) ?></h1>
                        <?php endif; ?>

                        <?php if ($description): ?>
                            <p class="hero-description"<?= heroTextStyle($descriptionColor, $descriptionFont, $descriptionItalic) ?>><?= nl2br(escape_html($description)) ?></p>
                        <?php endif; ?>

                        <?php if ($buttonText && $buttonUrl): ?>
                            <div class="hero-buttons">
                                <a href="<?= escape_html($buttonUrl) ?>" class="hero-btn"<?= $buttonTargetAttr ?><?= $buttonStyleAttr ?>>
                                    <?= escape_html($buttonText) ?>
                                </a>
                                <?php if ($buttonSecondaryText && $buttonSecondaryUrl): ?>
                                    <a href="<?= escape_html($buttonSecondaryUrl) ?>" class="hero-btn hero-btn-secondary"<?= $buttonSecondaryTargetAttr ?><?= $buttonSecondaryStyleAttr ?>>
                                        <?= escape_html($buttonSecondaryText) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($imageAlt): ?>
                            <p class="hero-image-caption"<?= heroTextStyle($captionColor, $captionFont, $captionItalic) ?>><?= escape_html($imageAlt) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

	        <?php else: ?>
	            <!-- Default layout -->
	            <div class="row">
	                <div class="col-12 <?= $textAlign ?>">
	                    <?php if ($heading): ?>
	                        <h1 class="hero-title hero-heading display-4 fw-bold mb-3"<?= heroTextStyle($headingColor, $headingFont, $headingItalic) ?>><?= escape_html($heading) ?></h1>
	                    <?php endif; ?>
                    <?php if ($description): ?>
                        <p class="hero-description lead"<?= heroTextStyle($descriptionColor, $descriptionFont, $descriptionItalic) ?>><?= nl2br(escape_html($description)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
