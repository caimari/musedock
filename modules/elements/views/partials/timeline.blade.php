<?php
/**
 * Timeline Element Template (Placeholder)
 */

$heading = $data['heading'] ?? '';
$items = $data['items'] ?? [];
?>

<section class="timeline-element py-5">
    <div class="container">
        <?php if ($heading): ?>
            <h2 class="text-center mb-5"><?= escape_html($heading) ?></h2>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php foreach ($items as $index => $item): ?>
                    <div class="timeline-item d-flex mb-4">
                        <div class="timeline-marker me-3">
                            <div class="bg-primary rounded-circle" style="width: 20px; height: 20px;"></div>
                        </div>
                        <div class="timeline-content flex-grow-1">
                            <?php if (!empty($item['date'])): ?>
                                <p class="text-muted small mb-1"><?= escape_html($item['date']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($item['title'])): ?>
                                <h5><?= escape_html($item['title']) ?></h5>
                            <?php endif; ?>
                            <?php if (!empty($item['description'])): ?>
                                <p class="text-muted"><?= escape_html($item['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
