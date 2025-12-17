<?php
/**
 * Stats/Numbers Element Template (Placeholder)
 */

$heading = $data['heading'] ?? '';
$items = $data['items'] ?? [];
?>

<section class="stats-element py-5">
    <div class="container">
        <?php if ($heading): ?>
            <h2 class="text-center mb-5"><?= escape_html($heading) ?></h2>
        <?php endif; ?>

        <div class="row g-4 text-center">
            <?php foreach ($items as $item): ?>
                <div class="col-6 col-md-3">
                    <?php if (!empty($item['number'])): ?>
                        <div class="display-4 fw-bold text-primary"><?= escape_html($item['number']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['label'])): ?>
                        <p class="text-muted"><?= escape_html($item['label']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
