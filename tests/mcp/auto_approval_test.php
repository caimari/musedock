<?php
require __DIR__.'/bootstrap.php';
use Screenart\Musedock\Services\Mcp\Direct\Tools;
foreach (['create_post','update_post','publish_post','schedule_post','set_featured_image','generate_image'] as $tool) {
    if (Tools::autoApprove($tool, [])) throw new RuntimeException('Auto approval must be opt-in.');
}
if (!Tools::autoApprove('publish_post',['auto_approve_posts'=>true]) || Tools::autoApprove('generate_image',['auto_approve_posts'=>true])) throw new RuntimeException('Post and image settings must be independent.');
if (!Tools::autoApprove('generate_image',['auto_approve_images'=>true]) || Tools::autoApprove('publish_post',['auto_approve_images'=>true])) throw new RuntimeException('Image permission leaked to publication.');
foreach (['moderate_comment','create_category','copy_draft','approve_request'] as $tool) if (Tools::autoApprove($tool,['auto_approve_images'=>true,'auto_approve_posts'=>true])) throw new RuntimeException('Unrelated operation auto approved.');
echo "PASS: opt-in defaults, independent settings and explicit operation allowlist.\n";
