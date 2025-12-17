<?php
/**
 * Features Element Template (Placeholder)
 *
 * This is a placeholder template. Customize as needed.
 */

$heading = $data['heading'] ?? '';
$items = $data['items'] ?? [];
?>

<section class="features-element py-5">
    <div class="container">
        <?php if ($heading): ?>
            <h2 class="text-center mb-5"><?= escape_html($heading) ?></h2>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach ($items as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-item text-center p-4">
                        <?php if (!empty($item['icon'])): ?>
                            <i class="bi bi-<?= escape_html($item['icon']) ?> display-4 text-primary mb-3"></i>
                        <?php endif; ?>
                        <?php if (!empty($item['title'])): ?>
                            <h4><?= escape_html($item['title']) ?></h4>
                        <?php endif; ?>
                        <?php if (!empty($item['description'])): ?>
                            <p class="text-muted"><?= escape_html($item['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
