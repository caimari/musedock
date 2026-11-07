<?php
require_once __DIR__.'/src/Forms.php';
require_once __DIR__.'/src/Controller.php';
if(function_exists('add_filter'))add_filter('the_content',static function($html){
    return preg_replace_callback('/\[musedock-form\s+slug=["\']([a-z0-9-]+)["\']\]/',static function($m){
        $form=\MuseDockForms\Forms::form($m[1]);if(!$form)return '<p>Formulario no disponible.</p>';
        return '<iframe class="md-form-frame" src="/md-forms/form/'.rawurlencode($m[1]).'" title="'.\MuseDockForms\Forms::e($form['title']).'" width="100%" height="1250" style="border:0;width:100%;max-width:100%" loading="lazy"></iframe><script src="/assets/modules/musedock-forms/parent.js" defer></script>';
    },$html);
},15);
require_once __DIR__.'/../module-menu-helper.php';
register_module_admin_menu(['module_slug'=>'musedock-forms','menu_slug'=>'musedock-forms','title'=>'MuseDock Forms','superadmin_url'=>'/musedock/modules','tenant_url'=>'/md-forms/manage','icon'=>'bi-ui-checks','icon_type'=>'bi','order'=>62,'permission'=>'custom_forms.manage']);
