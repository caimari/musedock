<?php
// Respaldo de traducciones: una etiqueta que falta en el idioma activo se toma del idioma que sí la tiene
// SIN cambiar el idioma de la página (antes load() del respaldo dejaba toda la página en español).
require __DIR__ . '/../../core/bootstrap.php';

use Screenart\Musedock\Services\TranslationService as T;

$f = 0;
$check = function (string $name, bool $ok) use (&$f) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $f++;
    }
};
$cur = new ReflectionProperty(T::class, 'currentLocale'); $cur->setAccessible(true);
$ctx = new ReflectionProperty(T::class, 'context'); $ctx->setAccessible(true);
$tr = new ReflectionProperty(T::class, 'translations'); $tr->setAccessible(true);

T::setContext('tenant');
T::load('en', 'tenant');
$check('una etiqueta que existe en inglés sale en inglés', T::get('blog.read_more') === 'Read More');

// Etiqueta solo en español (simulada en memoria: el diccionario real ya está completo)
T::load('es', 'tenant');
$all = $tr->getValue();
$all['tenant']['es']['solo_es'] = ['etiqueta' => 'Solo en español'];
$tr->setValue(null, $all);
T::load('en', 'tenant');

$check('la etiqueta que falta en inglés sale del idioma que sí la tiene', T::get('solo_es.etiqueta') === 'Solo en español');
$check('el idioma de la página sigue siendo en', $cur->getValue() === 'en');
$check('el contexto no cambia', $ctx->getValue() === 'tenant');
$check('el resto de la página sigue en inglés', T::get('blog.read_more') === 'Read More');
$check('una etiqueta que no existe en ningún idioma devuelve la clave', T::get('no.existe.en.ninguno') === 'no.existe.en.ninguno');
$check('buscar en todos los idiomas tampoco cambia el idioma', $cur->getValue() === 'en');

// El diccionario inglés del contexto tenant tiene todas las claves del español
$flat = function ($d, $p = '') use (&$flat) {
    $o = [];
    foreach ($d as $k => $v) {
        $kk = $p ? "$p.$k" : $k;
        is_array($v) ? $o += $flat($v, $kk) : $o[$kk] = $v;
    }
    return $o;
};
$es = $flat(json_decode(file_get_contents(__DIR__ . '/../../lang/tenant/es.json'), true));
$en = $flat(json_decode(file_get_contents(__DIR__ . '/../../lang/tenant/en.json'), true));
$check('el inglés del contexto tenant no tiene claves sin traducir (' . count(array_diff_key($es, $en)) . ' faltan)', !array_diff_key($es, $en));

exit($f ? 1 : 0);
