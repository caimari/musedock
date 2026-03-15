<?php
/**
 * News Aggregator - Settings View
 */
$pageTitle = $lang['settings_title'] ?? 'News Aggregator Settings';
?>

<div class="news-aggregator-settings">
    <div class="page-header mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <form action="/admin/plugins/news-aggregator/settings" method="POST">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><?= $lang['settings_general'] ?? 'General Settings' ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="ai_provider_id" class="form-label">
                                <?= $lang['settings_ai_provider'] ?? 'AI Provider' ?>
                            </label>
                            <select class="form-select" id="ai_provider_id" name="ai_provider_id">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($aiProviders as $provider): ?>
                                    <option value="<?= $provider->id ?>"
                                            <?= ($settings['ai_provider_id'] ?? '') == $provider->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($provider->name) ?> (<?= $provider->provider ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?= $lang['settings_ai_provider_help'] ?? 'Provider to use for rewriting news.' ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="output_language" class="form-label">
                                <?= $lang['settings_output_language'] ?? 'Output Language' ?>
                            </label>
                            <select class="form-select" id="output_language" name="output_language">
                                <option value="es" <?= ($settings['output_language'] ?? 'es') === 'es' ? 'selected' : '' ?>>Español</option>
                                <option value="en" <?= ($settings['output_language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="ca" <?= ($settings['output_language'] ?? '') === 'ca' ? 'selected' : '' ?>>Català</option>
                                <option value="fr" <?= ($settings['output_language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                                <option value="de" <?= ($settings['output_language'] ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                <option value="it" <?= ($settings['output_language'] ?? '') === 'it' ? 'selected' : '' ?>>Italiano</option>
                                <option value="pt" <?= ($settings['output_language'] ?? '') === 'pt' ? 'selected' : '' ?>>Português</option>
                            </select>
                            <div class="form-text"><?= $lang['settings_output_language_help'] ?? 'Language for rewritten content.' ?></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="rewrite_prompt" class="form-label">
                        <?= $lang['settings_rewrite_prompt'] ?? 'Rewrite Prompt' ?>
                    </label>
                    <textarea class="form-control" id="rewrite_prompt" name="rewrite_prompt" rows="4"><?= htmlspecialchars($settings['rewrite_prompt'] ?? '') ?></textarea>
                    <div class="form-text"><?= $lang['settings_rewrite_prompt_help'] ?? 'Instructions for AI when rewriting news.' ?></div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="default_category_id" class="form-label">
                                <?= $lang['settings_default_category'] ?? 'Default Category' ?>
                            </label>
                            <select class="form-select" id="default_category_id" name="default_category_id">
                                <option value="">-- Sin categoría --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category->id ?>"
                                            <?= ($settings['default_category_id'] ?? '') == $category->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><?= $lang['settings_default_category_help'] ?? 'Category to assign to posts created from news.' ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="duplicate_check_days" class="form-label">
                                <?= $lang['settings_duplicate_days'] ?? 'Duplicate Check Days' ?>
                            </label>
                            <input type="number" class="form-control" id="duplicate_check_days"
                                   name="duplicate_check_days"
                                   value="<?= $settings['duplicate_check_days'] ?? 7 ?>"
                                   min="1" max="30">
                            <div class="form-text"><?= $lang['settings_duplicate_days_help'] ?? 'Number of days to look back for duplicate news.' ?></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="auto_rewrite" name="auto_rewrite"
                               <?= ($settings['auto_rewrite'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="auto_rewrite">
                            <?= $lang['settings_auto_rewrite'] ?? 'Auto-rewrite' ?>
                        </label>
                        <div class="form-text"><?= $lang['settings_auto_rewrite_help'] ?? 'Automatically rewrite news when captured.' ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="enabled" name="enabled"
                               <?= ($settings['enabled'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enabled">
                            Plugin activo
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?= $lang['settings_save'] ?? 'Save Settings' ?>
        </button>
    </form>
</div>
