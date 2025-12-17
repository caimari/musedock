<?php
/**
 * Testimonials Element Template (Placeholder)
 */

$heading = $data['heading'] ?? '';
$items = $data['items'] ?? [];
?>

<section class="testimonials-element py-5 bg-light">
    <div class="container">
        <?php if ($heading): ?>
            <h2 class="text-center mb-5"><?= escape_html($heading) ?></h2>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach ($items as $item): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <?php if (!empty($item['quote'])): ?>
                                <p class="card-text">"<?= escape_html($item['quote']) ?>"</p>
                            <?php endif; ?>
                            <?php if (!empty($item['author'])): ?>
                                <p class="text-muted mb-0"><strong><?= escape_html($item['author']) ?></strong></p>
                            <?php endif; ?>
                            <?php if (!empty($item['position'])): ?>
                                <p class="text-muted small"><?= escape_html($item['position']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
