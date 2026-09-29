<?php
/**
 * Smoke tests para SafeHtml (sanitización de contenido de API REST / MCP).
 *
 * Uso:
 *   php cli/smoke-safehtml.php
 */

define('APP_ROOT', realpath(__DIR__ . '/..'));
require_once APP_ROOT . '/vendor/autoload.php';

use Screenart\Musedock\SafeHtml;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

$ok = 0;
$fail = 0;

function assertSafeHtml(bool $condition, string $label, int &$ok, int &$fail, string $got = ''): void
{
    if ($condition) {
        echo "[OK] {$label}\n";
        $ok++;
        return;
    }

    echo "[FAIL] {$label}" . ($got !== '' ? "\n       got: {$got}" : '') . "\n";
    $fail++;
}

$c = fn(string $h) => SafeHtml::clean($h);

// --- Conservación de contenido legítimo ---
$out = $c('<p>a</p><p>b</p><h2 id="intro">c</h2>texto final');
assertSafeHtml($out === '<p>a</p><p>b</p><h2 id="intro">c</h2>texto final', 'Conserva todos los nodos raíz', $ok, $fail, $out);

$out = $c('<p>ñandú — «comillas» 😀</p>');
assertSafeHtml($out === '<p>ñandú — «comillas» 😀</p>', 'Conserva UTF-8 (acentos, emoji)', $ok, $fail, $out);

$out = $c('<ul><li><a href="https://example.com" target="_blank">x</a></li></ul>');
assertSafeHtml(str_contains($out, 'href="https://example.com"') && str_contains($out, 'rel="noopener noreferrer"'), 'Enlace externo con noopener', $ok, $fail, $out);

$out = $c('<a href="/blog/post">rel</a><a href="#top">anc</a><a href="mailto:a@b.c">m</a>');
assertSafeHtml(substr_count($out, 'href=') === 3, 'URLs relativas, anclas y mailto permitidas', $ok, $fail, $out);

$out = $c('<section><custom-tag><p>dentro</p></custom-tag></section>');
assertSafeHtml($out === '<section><p>dentro</p></section>', 'Tag desconocido se desenvuelve sin perder hijos', $ok, $fail, $out);

$out = $c('<iframe src="https://www.youtube.com/embed/abc" allowfullscreen></iframe>');
assertSafeHtml(str_contains($out, '<iframe') && str_contains($out, 'youtube.com/embed/abc'), 'Iframe de YouTube permitido', $ok, $fail, $out);

$out = $c('<img src="/media/a.jpg" alt="A" srcset="/media/a-2x.jpg 2x">');
assertSafeHtml(str_contains($out, 'srcset="/media/a-2x.jpg 2x"'), 'img con srcset seguro', $ok, $fail, $out);

// --- Vectores XSS ---
$xss = [
    'script'                 => '<p>x</p><script>alert(1)</script>',
    'onerror'                => '<img src=x onerror=alert(1)>',
    'javascript: href'       => '<a href="javascript:alert(1)">x</a>',
    'espacio + javascript:'  => '<a href=" javascript:alert(1)">x</a>',
    'tab entity javascript:' => '<a href="java&#x09;script:alert(1)">x</a>',
    'mayúsculas JaVaScRiPt:' => '<a href="JaVaScRiPt:alert(1)">x</a>',
    'data: en src'           => '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">',
    'svg onload'             => '<svg onload=alert(1)><circle/></svg>',
    'style url(javascript)'  => '<div style="background:url(javascript:alert(1))">z</div>',
    'style con escapes css'  => '<div style="background:u\\72l(x)">z</div>',
    'iframe dominio ajeno'   => '<iframe src="https://evil.example/phish"></iframe>',
    'iframe javascript:'     => '<iframe src="javascript:alert(1)"></iframe>',
    'object/embed'           => '<object data="x.swf"></object><embed src="x.swf">',
    'form phishing'          => '<form action="https://evil"><input name=p></form>',
    'meta refresh'           => '<meta http-equiv="refresh" content="0;url=https://evil">',
    'srcset javascript'      => '<img src="/a.jpg" srcset="javascript:alert(1) 1x">',
    'comentario condicional' => '<!--[if IE]><script>alert(1)</script><![endif]-->',
    'position fixed overlay' => '<div style="position:fixed;top:0;left:0;width:100%;height:100%">fake login</div>',
    'onclick en permitido'   => '<p onclick="alert(1)">x</p>',
];

foreach ($xss as $label => $payload) {
    $out = $c($payload);
    $bad = preg_match('/<script|javascript:|onerror|onload|onclick|data:text|<svg|<object|<embed|<form|<input|<meta|evil|url\(|position:fixed/i', $out);
    assertSafeHtml(!$bad, "Bloquea: {$label}", $ok, $fail, $out);
}

// --- Texto plano ---
$out = SafeHtml::plain('<b>Hola</b> &amp; <script>x</script>adiós');
assertSafeHtml($out === 'Hola & xadiós', 'plain() quita HTML y decodifica entidades', $ok, $fail, $out);

assertSafeHtml($c('') === '', 'Cadena vacía', $ok, $fail);

echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
