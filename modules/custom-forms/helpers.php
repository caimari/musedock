<?php

/**
 * Custom Forms Module - Helper Functions
 */

use CustomForms\Models\Form;
use CustomForms\Models\FormSetting;

/**
 * Translation helper for the module
 */
if (!function_exists('__forms')) {
    function __forms(string $key, array $replace = []): string
    {
        static $translations = null;

        if ($translations === null) {
            $locale = defined('LOCALE') ? LOCALE : 'es';
            $langFile = __DIR__ . '/lang/' . $locale . '.json';

            if (!file_exists($langFile)) {
                $langFile = __DIR__ . '/lang/es.json';
            }

            $translations = [];
            if (file_exists($langFile)) {
                $translations = json_decode(file_get_contents($langFile), true) ?: [];
            }
        }

        $text = $translations[$key] ?? $key;

        foreach ($replace as $search => $value) {
            $text = str_replace(':' . $search, $value, $text);
        }

        return $text;
    }
}

/**
 * Process custom form shortcodes in content
 *
 * Supports:
 * - [custom-form id=1]
 * - [custom-form id="1"]
 * - [custom-form slug="contact"]
 * - [form id=1] (alias)
 */
if (!function_exists('process_custom_form_shortcodes')) {
    function process_custom_form_shortcodes(string $content): string
    {
        // Pattern for [custom-form id=X] or [custom-form slug="X"] or [form id=X]
        $pattern = '/\[(custom-form|form)\s+([^\]]+)\]/i';

        return preg_replace_callback($pattern, function ($matches) {
            $attributes = parse_shortcode_attributes($matches[2]);

            $form = null;

            // Find by ID
            if (!empty($attributes['id'])) {
                $form = Form::find((int) $attributes['id']);
            }
            // Find by slug
            elseif (!empty($attributes['slug'])) {
                $form = Form::findBySlug($attributes['slug']);
            }

            if (!$form || !$form->is_active) {
                return '<!-- Custom Form not found or inactive -->';
            }

            return render_custom_form($form, $attributes);
        }, $content);
    }
}

/**
 * Parse shortcode attributes
 */
if (!function_exists('parse_shortcode_attributes')) {
    function parse_shortcode_attributes(string $text): array
    {
        $attributes = [];
        $pattern = '/(\w+)\s*=\s*["\']?([^"\'>\s]+)["\']?/';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[strtolower($match[1])] = $match[2];
            }
        }

        return $attributes;
    }
}

/**
 * Render a custom form
 */
if (!function_exists('render_custom_form')) {
    function render_custom_form(Form $form, array $options = []): string
    {
        $fields = $form->activeFields();
        $settings = FormSetting::getAll($form->tenant_id);
        $submitUrl = '/forms/' . $form->id . '/submit';

        // CSS classes
        $formClass = $options['class'] ?? 'musedock-form';
        $theme = $options['theme'] ?? 'default';

        ob_start();
        ?>
        <div class="custom-form-wrapper form-theme-<?= htmlspecialchars($theme) ?>">
            <form
                id="custom-form-<?= $form->id ?>"
                class="<?= htmlspecialchars($formClass) ?>"
                action="<?= htmlspecialchars($submitUrl) ?>"
                method="POST"
                enctype="multipart/form-data"
                data-form-id="<?= $form->id ?>"
            >
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="_form_id" value="<?= $form->id ?>">
                <input type="hidden" name="_page_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">

                <?php
                // Honeypot anti-spam
                if ($settings['honeypot_enabled'] ?? true):
                ?>
                <div style="position: absolute; left: -9999px;" aria-hidden="true">
                    <input type="text" name="_hp_<?= $form->id ?>" tabindex="-1" autocomplete="off">
                </div>
                <?php endif; ?>

                <div class="form-fields">
                    <?php foreach ($fields as $field): ?>
                        <?php echo render_form_field($field); ?>
                    <?php endforeach; ?>
                </div>

                <div class="form-submit">
                    <button type="submit" class="btn btn-primary form-submit-btn">
                        <?= htmlspecialchars($form->submit_button_text ?: __forms('form.submit')) ?>
                    </button>
                </div>

                <div class="form-messages" style="display: none;">
                    <div class="form-success alert alert-success"></div>
                    <div class="form-error alert alert-danger"></div>
                </div>
            </form>
        </div>

        <script>
        (function() {
            const form = document.getElementById('custom-form-<?= $form->id ?>');
            if (!form) return;

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = form.querySelector('.form-submit-btn');
                const messagesDiv = form.querySelector('.form-messages');
                const successDiv = form.querySelector('.form-success');
                const errorDiv = form.querySelector('.form-error');

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> <?= __forms('form.sending') ?>';

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const result = await response.json();

                    messagesDiv.style.display = 'block';

                    if (result.success) {
                        successDiv.textContent = result.message;
                        successDiv.style.display = 'block';
                        errorDiv.style.display = 'none';
                        form.reset();

                        if (result.redirect) {
                            setTimeout(() => {
                                window.location.href = result.redirect;
                            }, 1500);
                        }
                    } else {
                        errorDiv.innerHTML = result.message;
                        if (result.errors) {
                            let errorHtml = '<ul class="mb-0">';
                            for (const [field, msgs] of Object.entries(result.errors)) {
                                msgs.forEach(msg => {
                                    errorHtml += '<li>' + msg + '</li>';
                                });
                            }
                            errorHtml += '</ul>';
                            errorDiv.innerHTML += errorHtml;
                        }
                        errorDiv.style.display = 'block';
                        successDiv.style.display = 'none';
                    }
                } catch (error) {
                    errorDiv.textContent = '<?= __forms('form.error_generic') ?>';
                    errorDiv.style.display = 'block';
                    successDiv.style.display = 'none';
                    messagesDiv.style.display = 'block';
                }

                submitBtn.disabled = false;
                submitBtn.textContent = '<?= htmlspecialchars($form->submit_button_text ?: __forms('form.submit')) ?>';
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

/**
 * Render a single form field
 */
if (!function_exists('render_form_field')) {
    function render_form_field($field): string
    {
        $name = htmlspecialchars($field->field_name);
        $label = htmlspecialchars($field->field_label);
        $placeholder = htmlspecialchars($field->placeholder ?? '');
        $required = $field->is_required ? 'required' : '';
        $requiredMark = $field->is_required ? '<span class="required">*</span>' : '';
        $cssClass = htmlspecialchars($field->css_class ?? '');
        $defaultValue = htmlspecialchars($field->default_value ?? '');

        $html = '<div class="form-group field-' . $field->field_type . ' ' . $cssClass . '">';

        // Non-input fields (headings, dividers, etc.)
        if (!$field->isInputField()) {
            switch ($field->field_type) {
                case 'heading':
                    $html .= '<h3 class="form-heading">' . $label . '</h3>';
                    break;
                case 'paragraph':
                    $html .= '<p class="form-paragraph">' . $label . '</p>';
                    break;
                case 'divider':
                    $html .= '<hr class="form-divider">';
                    break;
                case 'html':
                    $html .= '<div class="form-html">' . $field->default_value . '</div>';
                    break;
            }
            $html .= '</div>';
            return $html;
        }

        // Label
        if (!in_array($field->field_type, ['hidden'])) {
            $html .= '<label for="' . $name . '" class="form-label">' . $label . ' ' . $requiredMark . '</label>';
        }

        // Input field based on type
        switch ($field->field_type) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
            case 'number':
                $type = $field->field_type;
                $html .= '<input type="' . $type . '" name="' . $name . '" id="' . $name . '" class="form-control" placeholder="' . $placeholder . '" value="' . $defaultValue . '" ' . $required . '>';
                break;

            case 'password':
                $html .= '<input type="password" name="' . $name . '" id="' . $name . '" class="form-control" placeholder="' . $placeholder . '" ' . $required . '>';
                break;

            case 'textarea':
                $rows = $field->validation_rules['rows'] ?? 4;
                $html .= '<textarea name="' . $name . '" id="' . $name . '" class="form-control" rows="' . $rows . '" placeholder="' . $placeholder . '" ' . $required . '>' . $defaultValue . '</textarea>';
                break;

            case 'select':
                $html .= '<select name="' . $name . '" id="' . $name . '" class="form-select" ' . $required . '>';
                $html .= '<option value="">' . ($placeholder ?: __forms('field.select_option')) . '</option>';
                foreach ($field->getOptions() as $option) {
                    $selected = $option['value'] == $defaultValue ? 'selected' : '';
                    $html .= '<option value="' . htmlspecialchars($option['value']) . '" ' . $selected . '>' . htmlspecialchars($option['label']) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'radio':
                $html .= '<div class="radio-group">';
                foreach ($field->getOptions() as $option) {
                    $checked = $option['value'] == $defaultValue ? 'checked' : '';
                    $html .= '<div class="form-check">';
                    $html .= '<input type="radio" name="' . $name . '" id="' . $name . '_' . htmlspecialchars($option['value']) . '" value="' . htmlspecialchars($option['value']) . '" class="form-check-input" ' . $checked . ' ' . $required . '>';
                    $html .= '<label class="form-check-label" for="' . $name . '_' . htmlspecialchars($option['value']) . '">' . htmlspecialchars($option['label']) . '</label>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                break;

            case 'checkbox':
                if (!empty($field->options)) {
                    // Multiple checkboxes
                    $html .= '<div class="checkbox-group">';
                    foreach ($field->getOptions() as $option) {
                        $html .= '<div class="form-check">';
                        $html .= '<input type="checkbox" name="' . $name . '[]" id="' . $name . '_' . htmlspecialchars($option['value']) . '" value="' . htmlspecialchars($option['value']) . '" class="form-check-input">';
                        $html .= '<label class="form-check-label" for="' . $name . '_' . htmlspecialchars($option['value']) . '">' . htmlspecialchars($option['label']) . '</label>';
                        $html .= '</div>';
                    }
                    $html .= '</div>';
                } else {
                    // Single checkbox
                    $html .= '<div class="form-check">';
                    $html .= '<input type="checkbox" name="' . $name . '" id="' . $name . '" value="1" class="form-check-input" ' . $required . '>';
                    $html .= '<label class="form-check-label" for="' . $name . '">' . $label . '</label>';
                    $html .= '</div>';
                }
                break;

            case 'date':
                $html .= '<input type="date" name="' . $name . '" id="' . $name . '" class="form-control" value="' . $defaultValue . '" ' . $required . '>';
                break;

            case 'time':
                $html .= '<input type="time" name="' . $name . '" id="' . $name . '" class="form-control" value="' . $defaultValue . '" ' . $required . '>';
                break;

            case 'datetime':
                $html .= '<input type="datetime-local" name="' . $name . '" id="' . $name . '" class="form-control" value="' . $defaultValue . '" ' . $required . '>';
                break;

            case 'file':
                $accept = $field->validation_rules['accept'] ?? '';
                $html .= '<input type="file" name="' . $name . '" id="' . $name . '" class="form-control" accept="' . $accept . '" ' . $required . '>';
                break;

            case 'hidden':
                $html .= '<input type="hidden" name="' . $name . '" id="' . $name . '" value="' . $defaultValue . '">';
                break;
        }

        $html .= '</div>';
        return $html;
    }
}

/**
 * Get CSRF token
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }
}
