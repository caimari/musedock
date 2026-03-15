<?php
/**
 * News Aggregator - Source Form View
 */
$isEdit = $action === 'edit';
$pageTitle = $isEdit ? ($lang['sources_edit'] ?? 'Edit Source') : ($lang['sources_add'] ?? 'Add Source');
$formAction = $isEdit
    ? "/admin/plugins/news-aggregator/sources/{$source->id}/update"
    : "/admin/plugins/news-aggregator/sources";
?>

<div class="news-aggregator-source-form">
    <div class="page-header mb-4">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="<?= $formAction ?>" method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                <?= $lang['sources_name'] ?? 'Name' ?> *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="name"
                                   name="name"
                                   value="<?= htmlspecialchars($source->name ?? '') ?>"
                                   required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="source_type" class="form-label">
                                <?= $lang['sources_type'] ?? 'Type' ?>
                            </label>
                            <select class="form-select" id="source_type" name="source_type">
                                <option value="rss" <?= ($source->source_type ?? 'rss') === 'rss' ? 'selected' : '' ?>>
                                    <?= $lang['sources_type_rss'] ?? 'RSS/Atom Feed' ?>
                                </option>
                                <option value="newsapi" <?= ($source->source_type ?? '') === 'newsapi' ? 'selected' : '' ?>>
                                    <?= $lang['sources_type_newsapi'] ?? 'NewsAPI' ?>
                                </option>
                                <option value="gnews" <?= ($source->source_type ?? '') === 'gnews' ? 'selected' : '' ?>>
                                    <?= $lang['sources_type_gnews'] ?? 'GNews' ?>
                                </option>
                                <option value="mediastack" <?= ($source->source_type ?? '') === 'mediastack' ? 'selected' : '' ?>>
                                    <?= $lang['sources_type_mediastack'] ?? 'MediaStack' ?>
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="url" class="form-label">
                        <?= $lang['sources_url'] ?? 'Feed URL' ?>
                    </label>
                    <input type="url"
                           class="form-control"
                           id="url"
                           name="url"
                           value="<?= htmlspecialchars($source->url ?? '') ?>"
                           placeholder="https://example.com/feed.xml">
                    <div class="form-text"><?= $lang['sources_url_help'] ?? 'Full URL of the RSS or Atom feed.' ?></div>
                </div>

                <div class="mb-3">
                    <label for="api_key" class="form-label">
                        <?= $lang['sources_api_key'] ?? 'API Key' ?>
                    </label>
                    <input type="text"
                           class="form-control"
                           id="api_key"
                           name="api_key"
                           value="<?= htmlspecialchars($source->api_key ?? '') ?>">
                    <div class="form-text"><?= $lang['sources_api_key_help'] ?? 'API key (only for sources that require it).' ?></div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="keywords" class="form-label">
                                <?= $lang['sources_keywords'] ?? 'Keywords' ?>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="keywords"
                                   name="keywords"
                                   value="<?= htmlspecialchars($source->keywords ?? '') ?>"
                                   placeholder="cultura, arte, música">
                            <div class="form-text"><?= $lang['sources_keywords_help'] ?? 'Comma-separated keywords to filter news.' ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="language" class="form-label">
                                <?= $lang['sources_language'] ?? 'Source Language' ?>
                            </label>
                            <select class="form-select" id="language" name="language">
                                <option value="">Auto-detectar</option>
                                <option value="es" <?= ($source->language ?? '') === 'es' ? 'selected' : '' ?>>Español</option>
                                <option value="en" <?= ($source->language ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="ca" <?= ($source->language ?? '') === 'ca' ? 'selected' : '' ?>>Català</option>
                                <option value="fr" <?= ($source->language ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                                <option value="de" <?= ($source->language ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                <option value="it" <?= ($source->language ?? '') === 'it' ? 'selected' : '' ?>>Italiano</option>
                                <option value="pt" <?= ($source->language ?? '') === 'pt' ? 'selected' : '' ?>>Português</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="fetch_interval" class="form-label">
                                <?= $lang['sources_interval'] ?? 'Fetch Interval' ?>
                            </label>
                            <select class="form-select" id="fetch_interval" name="fetch_interval">
                                <option value="1800" <?= ($source->fetch_interval ?? 3600) == 1800 ? 'selected' : '' ?>>30 minutos</option>
                                <option value="3600" <?= ($source->fetch_interval ?? 3600) == 3600 ? 'selected' : '' ?>>1 hora</option>
                                <option value="7200" <?= ($source->fetch_interval ?? 3600) == 7200 ? 'selected' : '' ?>>2 horas</option>
                                <option value="14400" <?= ($source->fetch_interval ?? 3600) == 14400 ? 'selected' : '' ?>>4 horas</option>
                                <option value="28800" <?= ($source->fetch_interval ?? 3600) == 28800 ? 'selected' : '' ?>>8 horas</option>
                                <option value="86400" <?= ($source->fetch_interval ?? 3600) == 86400 ? 'selected' : '' ?>>24 horas</option>
                            </select>
                            <div class="form-text"><?= $lang['sources_interval_help'] ?? 'How often to fetch news.' ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="max_articles" class="form-label">
                                <?= $lang['sources_max_articles'] ?? 'Max Articles' ?>
                            </label>
                            <input type="number"
                                   class="form-control"
                                   id="max_articles"
                                   name="max_articles"
                                   value="<?= $source->max_articles ?? 10 ?>"
                                   min="1"
                                   max="50">
                            <div class="form-text"><?= $lang['sources_max_articles_help'] ?? 'Maximum number of articles to fetch per run.' ?></div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="form-check">
                        <input type="checkbox"
                               class="form-check-input"
                               id="enabled"
                               name="enabled"
                               <?= ($source->enabled ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enabled">
                            <?= $lang['sources_status_active'] ?? 'Active' ?>
                        </label>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?= $lang['sources_save'] ?? 'Save Source' ?>
                    </button>
                    <a href="/admin/plugins/news-aggregator/sources" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
