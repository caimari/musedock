<?php
/**
 * FAQ Element Template
 *
 * Available variables:
 * - $element: The Element model instance
 * - $type: Element type ('faq')
 * - $layout: Layout type ('accordion', 'simple', 'two-columns')
 * - $data: Element data array
 * - $settings: Element settings array
 */

$heading = $data['heading'] ?? '';
$items = $data['items'] ?? [];
$accordionId = 'faq-' . $element->id;
?>

<section class="faq-element faq-<?= escape_html($layout ?? 'accordion') ?> py-5">
    <div class="container">
        <?php if ($heading): ?>
            <h2 class="faq-heading text-center mb-4"><?= escape_html($heading) ?></h2>
        <?php endif; ?>

        <?php if ($layout === 'accordion'): ?>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="accordion" id="<?= $accordionId ?>">
                        <?php foreach ($items as $index => $item): ?>
                            <?php
                            $question = $item['question'] ?? '';
                            $answer = $item['answer'] ?? '';
                            if (!$question || !$answer) continue;
                            $collapseId = $accordionId . '-item-' . $index;
                            ?>
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?>"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?= $collapseId ?>"
                                            aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                                            aria-controls="<?= $collapseId ?>">
                                        <?= escape_html($question) ?>
                                    </button>
                                </h3>
                                <div id="<?= $collapseId ?>"
                                     class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                                     data-bs-parent="#<?= $accordionId ?>">
                                    <div class="accordion-body">
                                        <?= nl2br(escape_html($answer)) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($layout === 'two-columns'): ?>
            <div class="row g-4">
                <?php
                $half = ceil(count($items) / 2);
                $columns = [
                    array_slice($items, 0, $half),
                    array_slice($items, $half)
                ];
                ?>
                <?php foreach ($columns as $columnItems): ?>
                    <div class="col-md-6">
                        <?php foreach ($columnItems as $item): ?>
                            <?php
                            $question = $item['question'] ?? '';
                            $answer = $item['answer'] ?? '';
                            if (!$question || !$answer) continue;
                            ?>
                            <div class="faq-item mb-4">
                                <h4 class="faq-question h5 fw-bold mb-2">
                                    <i class="bi bi-question-circle text-primary me-2"></i>
                                    <?= escape_html($question) ?>
                                </h4>
                                <div class="faq-answer text-muted">
                                    <?= nl2br(escape_html($answer)) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Simple layout -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $question = $item['question'] ?? '';
                        $answer = $item['answer'] ?? '';
                        if (!$question || !$answer) continue;
                        ?>
                        <div class="faq-item mb-4 pb-4 border-bottom">
                            <h4 class="faq-question h5 fw-bold mb-2">
                                <i class="bi bi-question-circle text-primary me-2"></i>
                                <?= escape_html($question) ?>
                            </h4>
                            <div class="faq-answer text-muted">
                                <?= nl2br(escape_html($answer)) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
