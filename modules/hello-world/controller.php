<?php

use Screenart\Musedock\View;

class HelloWorldController {
    public function index() {
        return View::renderModule('hello-world', 'welcome', [
    'title' => 'Hello desde el módulo'
]);

    }
}
