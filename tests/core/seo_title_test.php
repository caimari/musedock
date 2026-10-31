<?php
// Título de página con el nombre del sitio detrás, sin pasar de 60 caracteres (Google corta ahí).
require __DIR__ . '/../../core/bootstrap.php';

$f = 0;
$check = function (string $name, bool $ok) use (&$f) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $f++;
    }
};

$check('cabe con el sufijo: se añade', seo_title_with_suffix('Precios de MuseDock', ['MuseDock']) === 'Precios de MuseDock | MuseDock');
$check('no cabe: va solo el título', seo_title_with_suffix(str_repeat('a', 55), ['MuseDock']) === str_repeat('a', 55));
$check('justo 60 caracteres: se añade', mb_strlen(seo_title_with_suffix(str_repeat('a', 49), ['MuseDock'])) === 60);
$check('61 caracteres: no se añade', seo_title_with_suffix(str_repeat('a', 50), ['MuseDock']) === str_repeat('a', 50));
$check('si el primero no cabe usa el siguiente (docs)', seo_title_with_suffix(str_repeat('a', 45), ['Docs | MuseDock', 'MuseDock']) === str_repeat('a', 45) . ' | MuseDock');
$check('el sufijo largo cabe y gana', seo_title_with_suffix('Guía de instalación', ['Docs | MuseDock', 'MuseDock']) === 'Guía de instalación | Docs | MuseDock');
$check('cuenta caracteres, no bytes (tildes)', seo_title_with_suffix('Instalación y configuración rápida del CMS', ['MuseDock']) === 'Instalación y configuración rápida del CMS | MuseDock');
$check('sufijos vacíos se ignoran', seo_title_with_suffix('Hola', ['', '  ']) === 'Hola');

exit($f ? 1 : 0);
