<?php
// Renderizar layout con contenido
$layoutData = array_merge($data ?? [], [
    'current_page' => 'profile',
    'content' => \Screenart\Musedock\View::renderTheme('Customer/profile_content', $data ?? [])
]);

echo \Screenart\Musedock\View::renderTheme('Customer/layout', $layoutData);
