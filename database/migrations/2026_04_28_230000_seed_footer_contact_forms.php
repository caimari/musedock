<?php

class SeedFooterContactForms_2026_04_28_230000
{
    public function up()
    {
        $files = [
            APP_ROOT . '/modules/custom-forms/models/Form.php',
            APP_ROOT . '/modules/custom-forms/models/FormField.php',
            APP_ROOT . '/modules/custom-forms/models/FormSetting.php',
            APP_ROOT . '/modules/custom-forms/models/FormSubmission.php',
            APP_ROOT . '/modules/custom-forms/controllers/PublicController.php',
        ];
        foreach ($files as $f) {
            if (is_file($f)) {
                require_once $f;
            }
        }

        if (!class_exists('CustomForms\\Controllers\\PublicController')) {
            echo "! CustomForms PublicController not found, skipping\n";
            return;
        }

        $result = \CustomForms\Controllers\PublicController::ensureFooterContactFormsForAllTenants();
        echo "✓ Footer contact forms ensured (checked={$result['checked']}, ok={$result['ok']}, failed={$result['failed']})\n";
    }

    public function down()
    {
        // No-op: no eliminamos formularios porque podrían tener envíos reales.
        echo "✓ No rollback for footer contact forms seed\n";
    }
}
