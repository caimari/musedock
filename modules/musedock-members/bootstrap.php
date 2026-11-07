<?php
require_once __DIR__.'/../musedock-forms/src/Forms.php';
require_once __DIR__.'/src/Passwords.php';
require_once __DIR__.'/src/Members.php';
require_once __DIR__.'/src/Controller.php';
require_once __DIR__.'/../module-menu-helper.php';
register_module_admin_menu(['module_slug'=>'musedock-members','menu_slug'=>'musedock-members','title'=>'MuseDock Members','superadmin_url'=>'/musedock/modules','tenant_url'=>'/md-members/manage','icon'=>'bi-people','icon_type'=>'bi','order'=>63,'permission'=>'custom_forms.manage']);
if(function_exists('add_filter'))add_filter('the_content',static function($html){
    return preg_replace_callback('/\[musedock-member\s+view=["\'](login|account|register|directory|reset)["\']\]/',static function($m){
        return '<iframe class="md-form-frame" src="/md-members/'.$m[1].'" title="Área de socios" width="100%" height="1250" style="border:0;width:100%;max-width:100%" loading="lazy"></iframe><script src="/assets/modules/musedock-forms/parent.js" defer></script>';
    },$html);
},15);
