<?php
// core/Plugins/MenuComposer.php

namespace Screenart\Musedock\Plugins;

use Screenart\Musedock\Helpers\MenuHelper;
use Screenart\Musedock\View;

class MenuComposer
{
    /**
     * Inicializa el compositor de menús
     */
    public static function init()
    {
        // Añadir menús globales a las vistas
        View::addGlobalData([
            'navMenu' => MenuHelper::renderCustomMenu('nav', null, [
                'nav' => 'header__nav',
                'submenu' => 'sub-menu'
            ]),
            'footerMenu' => MenuHelper::renderCustomMenu('footer', null, [
                'footer' => 'footer-menu',
                'submenu' => 'sub-menu'
            ]),
            'sidebarMenu' => MenuHelper::renderCustomMenu('sidebar', null, [
                'sidebar' => 'sidebar-menu',
                'submenu' => 'sub-menu'
            ])
        ]);
    }
}