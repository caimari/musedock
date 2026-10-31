<?php
// Traducciones de categorías del blog: se aplican en memoria al idioma pedido y no tocan el resto.
// Usa las traducciones al inglés del sitio principal (blog_category_translations) y la ruta del índice de docs.
require __DIR__ . '/../../core/bootstrap.php';

use Screenart\Musedock\Services\CategoryTranslation as CT;
use Screenart\Musedock\Services\LanguageUrl;

$f = 0;
$check = function (string $name, bool $ok) use (&$f) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $f++;
    }
};
$cat = fn (int $id, string $name) => (object) ['id' => $id, 'name' => $name, 'description' => 'original'];

$sec = $cat(164, 'Seguridad');
CT::apply([$sec], 'en');
$check('164 Seguridad → Security en inglés', $sec->name === 'Security');

$many = [$cat(125, 'Tecnología'), $cat(159, 'Certificados y DNS'), $cat(165, 'Cluster y Alta Disponibilidad')];
CT::apply($many, 'en');
$check('varias categorías a la vez', [$many[0]->name, $many[1]->name, $many[2]->name] === ['Technology', 'Certificates & DNS', 'Cluster & high availability']);

$d = $cat(114, 'MuseDock CMS');
CT::apply([$d], 'en');
$check('la descripción traducida sustituye a la original', $d->description === 'Documentation for the multi-tenant CMS');

$none = $cat(999999, 'Sin traducción');
CT::apply([$none], 'en');
$check('una categoría sin traducción conserva su nombre y descripción', $none->name === 'Sin traducción' && $none->description === 'original');

$es = $cat(164, 'Seguridad');
CT::apply([$es], 'es');
$check('en el idioma original no cambia nada (sin fila es)', $es->name === 'Seguridad');

$nid = (object) ['name' => 'Sin id'];
CT::apply([$nid, 'no es objeto'], 'en');
$check('objetos sin id y valores sueltos se ignoran', $nid->name === 'Sin id');

$check('sin idioma de página distinto del por defecto no se traduce', CT::activeLocale() === null);
$check('el índice de docs se reconoce con y sin barra final', LanguageUrl::isDocsIndexPath('/docs') && LanguageUrl::isDocsIndexPath('/docs/') && !LanguageUrl::isDocsIndexPath('/docs/guia'));

exit($f ? 1 : 0);
