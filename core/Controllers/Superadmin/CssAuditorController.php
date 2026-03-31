<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class CssAuditorController
{
    use RequiresPermission;

    private string $progressDir;
    private string $clonesDir;

    public function __construct()
    {
        $this->progressDir = __DIR__ . '/../../../storage/css-auditor';
        $this->clonesDir   = __DIR__ . '/../../../storage/css-auditor/clones';
        if (!is_dir($this->progressDir)) @mkdir($this->progressDir, 0755, true);
        if (!is_dir($this->clonesDir))   @mkdir($this->clonesDir, 0755, true);
    }

    /**
     * Show the CSS Auditor page.
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        return View::render('Superadmin.plugins.theme-extractor.index', [
            'title' => 'CSS Auditor',
        ]);
    }

    /**
     * Start analysis (AJAX POST). Runs synchronously but writes progress to a file
     * that the frontend polls via the progress() endpoint.
     */
    public function extract()
    {
        // Set JSON response type FIRST to prevent HTML error pages
        header('Content-Type: application/json; charset=utf-8');

        try {
            SessionSecurity::startSession();
            // Required by EnforcePermissionMiddleware (scans source for this call)
            $this->checkPermission('appearance.themes');

            $url = trim($_GET['url'] ?? $_POST['url'] ?? '');
            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['error' => 'URL no válida.']);
                exit;
            }

            $jobId = md5($url . microtime(true));
            $progressFile = $this->progressDir . "/{$jobId}.json";

            // Release session lock during long operation
            session_write_close();
            set_time_limit(120);

            $this->writeProgress($progressFile, 'Descargando pagina...', 1, 0);

            $html = $this->fetchUrl($url);
            if (!$html) {
                $this->writeProgress($progressFile, 'Error: No se pudo descargar la pagina.', 0, 0, true);
                echo json_encode(['error' => 'No se pudo descargar la pagina. Verifica que la URL sea accesible.']);
                exit;
            }

            $htmlSize = number_format(strlen($html) / 1024, 0);
            $this->writeProgress($progressFile, "Pagina descargada ({$htmlSize} KB). Buscando archivos CSS...", 2, 0);

            $baseUrl = $this->getBaseUrl($url);

            // Discover stylesheets
            $sheetRefs = $this->discoverStylesheetUrls($html, $baseUrl, $progressFile);
            $totalSheets = count($sheetRefs);

            $this->writeProgress($progressFile, "Encontrados {$totalSheets} archivos CSS. Analizando selectores...", 3, $totalSheets);

            // Parse HTML selectors
            $htmlSelectors = $this->extractHtmlSelectors($html);

            $results = [];
            $allUsedRules = [];

            foreach ($sheetRefs as $idx => $ref) {
                $sheetNum = $idx + 1;
                $label = $ref['is_inline']
                    ? "style inline #{$sheetNum}"
                    : basename(parse_url($ref['url'] ?? '', PHP_URL_PATH) ?: "css-{$sheetNum}");

                $this->writeProgress($progressFile, "Analizando [{$sheetNum}/{$totalSheets}]: {$label}", $sheetNum, $totalSheets, false, $results);

                $cssContent = $ref['content'];
                if (empty(trim($cssContent))) continue;

                $analysis = $this->analyzeCss($cssContent, $htmlSelectors);

                $result = [
                    'url'             => $ref['url'] ?? '(inline)',
                    'is_inline'       => $ref['is_inline'],
                    'original_size'   => strlen($cssContent),
                    'clean_size'      => strlen($analysis['clean_css']),
                    'reduction_pct'   => $analysis['reduction_pct'],
                    'total_selectors' => $analysis['total_selectors'],
                    'used_selectors'  => $analysis['used_selectors'],
                    'used_pct'        => $analysis['used_pct'],
                ];

                $results[] = $result;

                if (!empty($analysis['clean_css'])) {
                    $allUsedRules[] = "/* === " . ($ref['url'] ?? 'inline') . " === */\n" . $analysis['clean_css'];
                }
            }

            // Build unified CSS
            $unifiedCss = implode("\n\n", $allUsedRules);

            $totalOriginal  = array_sum(array_column($results, 'original_size'));
            $totalClean     = array_sum(array_column($results, 'clean_size'));
            $totalSelectors = array_sum(array_column($results, 'total_selectors'));
            $totalUsed      = array_sum(array_column($results, 'used_selectors'));

            // ─── JS Discovery & Unification ───
            $this->writeProgress($progressFile, 'Analizando archivos JavaScript...', $totalSheets, $totalSheets, false, $results);

            $jsResults = [];
            $allJsParts = [];
            $jsRefs = $this->discoverScripts($html, $baseUrl, $progressFile);

            foreach ($jsRefs as $js) {
                $jsResults[] = [
                    'url'       => $js['url'] ?? '(inline)',
                    'is_inline' => $js['is_inline'],
                    'size'      => strlen($js['content']),
                    'filename'  => $js['is_inline'] ? '(inline)' : basename(parse_url($js['url'] ?? '', PHP_URL_PATH) ?: 'script.js'),
                ];

                $label = $js['url'] ?? 'inline script';
                $allJsParts[] = "/* === {$label} === */\n" . $js['content'];
            }

            $unifiedJs = implode("\n\n", $allJsParts);
            $totalJsOriginal = array_sum(array_column($jsResults, 'size'));

            // Save to session for download and clone
            session_start();
            $_SESSION['css_auditor_unified']    = $unifiedCss;
            $_SESSION['css_auditor_unified_js'] = $unifiedJs;
            $_SESSION['css_auditor_source_url'] = $url;
            $_SESSION['css_auditor_html']       = $html;
            $_SESSION['css_auditor_base_url']   = $baseUrl;
            session_write_close();

            $this->writeProgress($progressFile, 'Analisis completado!', $totalSheets, $totalSheets, true, $results);
            $this->cleanupProgressFiles();

            echo json_encode([
                'success'         => true,
                'jobId'           => $jobId,
                'results'         => $results,
                'totalOriginal'   => $totalOriginal,
                'totalClean'      => $totalClean,
                'totalSelectors'  => $totalSelectors,
                'totalUsed'       => $totalUsed,
                'jsResults'       => $jsResults,
                'totalJsFiles'    => count($jsResults),
                'totalJsSize'     => $totalJsOriginal,
                'unifiedJsSize'   => strlen($unifiedJs),
            ]);

        } catch (\Throwable $e) {
            echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        }

        exit;
    }

    /**
     * Poll progress (AJAX GET).
     */
    public function progress()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        header('Content-Type: application/json; charset=utf-8');

        $jobId = preg_replace('/[^a-f0-9]/', '', $_GET['job'] ?? '');
        $progressFile = $this->progressDir . "/{$jobId}.json";

        if (!$jobId || !file_exists($progressFile)) {
            echo json_encode(['message' => 'Trabajando...', 'current' => 0, 'total' => 0, 'done' => false, 'fileResults' => []]);
            exit;
        }

        $data = @json_decode(file_get_contents($progressFile), true);
        echo json_encode($data ?: ['message' => 'Trabajando...', 'current' => 0, 'total' => 0, 'done' => false, 'fileResults' => []]);
        exit;
    }

    /**
     * Download the unified clean CSS file.
     */
    public function download()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $css = $_SESSION['css_auditor_unified'] ?? '';
        $sourceUrl = $_SESSION['css_auditor_source_url'] ?? 'unknown';

        if (empty($css)) {
            flash('error', 'No hay CSS limpio disponible. Analiza una URL primero.');
            header('Location: /musedock/theme-extractor');
            exit;
        }

        $domain = parse_url($sourceUrl, PHP_URL_HOST) ?: 'site';
        $filename = preg_replace('/[^a-z0-9.-]/', '_', $domain) . '_clean.css';

        $header = "/*\n * Clean CSS generated by MuseDock CSS Auditor\n * Source: {$sourceUrl}\n * Date: " . date('Y-m-d H:i:s') . "\n * Only used selectors are included.\n */\n\n";

        header('Content-Type: text/css; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($header . $css));
        echo $header . $css;
        exit;
    }

    /**
     * Return the unified CSS as JSON (for copy-to-clipboard / preview).
     */
    public function getCleanCss()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'css'       => $_SESSION['css_auditor_unified'] ?? '',
            'sourceUrl' => $_SESSION['css_auditor_source_url'] ?? '',
        ]);
        exit;
    }

    /**
     * Clone the page: build a self-contained HTML with clean CSS inline, save to disk.
     * Returns JSON with the preview URL.
     */
    public function clonePage()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            SessionSecurity::startSession();
            $this->checkPermission('appearance.themes');

            $html       = $_SESSION['css_auditor_html'] ?? '';
            $cleanCss   = $_SESSION['css_auditor_unified'] ?? '';
            $unifiedJs  = $_SESSION['css_auditor_unified_js'] ?? '';
            $sourceUrl  = $_SESSION['css_auditor_source_url'] ?? '';
            $baseUrl    = $_SESSION['css_auditor_base_url'] ?? '';
            $includeJs  = ($_GET['include_js'] ?? '0') === '1';

            if (empty($html) || empty($sourceUrl)) {
                echo json_encode(['error' => 'No hay datos. Analiza una URL primero.']);
                exit;
            }

            // Build the cloned page (with or without JS based on checkbox)
            $clonedHtml = $this->buildClonedPage($html, $cleanCss, $sourceUrl, $baseUrl, $includeJs ? $unifiedJs : '');

            // Save to disk
            $domain = parse_url($sourceUrl, PHP_URL_HOST) ?: 'site';
            $slug   = preg_replace('/[^a-z0-9.-]/', '_', $domain);
            $id     = $slug . '_' . date('Ymd_His');
            $file   = $this->clonesDir . "/{$id}.html";

            file_put_contents($file, $clonedHtml);

            // Cleanup old clones (> 1 hour)
            foreach (glob($this->clonesDir . '/*.html') as $f) {
                if (filemtime($f) < time() - 3600) @unlink($f);
            }

            echo json_encode([
                'success'    => true,
                'previewUrl' => '/musedock/theme-extractor/preview?id=' . urlencode($id),
                'cloneId'    => $id,
                'size'       => strlen($clonedHtml),
            ]);

        } catch (\Throwable $e) {
            echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
        }

        exit;
    }

    /**
     * Serve a cloned page for preview.
     */
    public function preview()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $id = preg_replace('/[^a-z0-9._-]/i', '', $_GET['id'] ?? '');
        $file = $this->clonesDir . "/{$id}.html";

        if (!$id || !file_exists($file)) {
            http_response_code(404);
            echo '<h1>Preview no encontrada</h1><p>El clon ha expirado o no existe. <a href="/musedock/theme-extractor">Volver al CSS Auditor</a></p>';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');

        // Check if clone has JS (look for our marker)
        $content = file_get_contents($file);
        $hasJs = strpos($content, 'musedock-unified-js') !== false;

        if ($hasJs) {
            // Allow inline scripts + external resources
            header("Content-Security-Policy: default-src * data: blob: 'unsafe-inline' 'unsafe-eval'; object-src 'none';");
        } else {
            // CSS-only clone: block all scripts
            header("Content-Security-Policy: default-src * data: blob: 'unsafe-inline'; script-src 'none'; object-src 'none';");
        }

        echo $content;
        exit;
    }

    /**
     * Download the cloned HTML page.
     */
    public function downloadClone()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $id = preg_replace('/[^a-z0-9._-]/i', '', $_GET['id'] ?? '');
        $file = $this->clonesDir . "/{$id}.html";

        if (!$id || !file_exists($file)) {
            flash('error', 'Clon no encontrado o expirado.');
            header('Location: /musedock/theme-extractor');
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $id . '.html"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    // ─── Clone builder ───

    /**
     * Build a self-contained HTML page with:
     * - All external CSS <link> removed and replaced with the clean CSS inline
     * - All inline <style> removed (already included in clean CSS)
     * - Images/assets converted to absolute URLs so they still load
     * - A small banner indicating it's a MuseDock clone
     */
    private function buildClonedPage(string $html, string $cleanCss, string $sourceUrl, string $baseUrl, string $unifiedJs = ''): string
    {
        // Convert relative URLs to absolute for images, fonts, etc.
        $html = $this->makeUrlsAbsolute($html, $baseUrl);

        // Remove all <link rel="stylesheet"> tags
        $html = preg_replace('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>\s*/i', '', $html);
        $html = preg_replace('/<link\b[^>]*href=["\'][^"\']+["\'][^>]*rel=["\']stylesheet["\'][^>]*>\s*/i', '', $html);

        // Remove all inline <style> blocks
        $html = preg_replace('/<style[^>]*>.*?<\/style>\s*/is', '', $html);

        // Remove all <script> tags (replaced with unified JS)
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>\s*/is', '', $html);
        $html = preg_replace('/<script\b[^>]*\/>\s*/i', '', $html);

        // Remove noscript tags but keep their content
        $html = preg_replace('/<\/?noscript[^>]*>/i', '', $html);

        // Inject clean CSS
        $injectCss = "\n<!-- MuseDock CSS Auditor: Clean CSS from {$sourceUrl} -->\n"
            . "<style id=\"musedock-clean-css\">\n{$cleanCss}\n</style>\n";

        // Inject unified JS before </body>
        $injectJs = '';
        if (!empty($unifiedJs)) {
            $injectJs = "\n<!-- MuseDock CSS Auditor: Unified JS from {$sourceUrl} -->\n"
                . "<script id=\"musedock-unified-js\">\n{$unifiedJs}\n</script>\n";
        }

        $cssKb = number_format(strlen($cleanCss) / 1024, 1);
        $jsKb  = !empty($unifiedJs) ? number_format(strlen($unifiedJs) / 1024, 1) : '0';

        // Banner
        $banner = '<div id="musedock-clone-banner" style="position:fixed;top:0;left:0;right:0;z-index:999999;'
            . 'background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;text-align:center;'
            . 'padding:8px 16px;font:13px/1.4 -apple-system,BlinkMacSystemFont,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,0.2);">'
            . '<strong>MuseDock Clone</strong> &mdash; <a href="' . htmlspecialchars($sourceUrl) . '" target="_blank" style="color:#fff;text-decoration:underline;">'
            . htmlspecialchars($sourceUrl) . '</a>'
            . ' &mdash; CSS: ' . $cssKb . ' KB | JS: ' . $jsKb . ' KB'
            . ' <button onclick="this.parentElement.remove()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;padding:2px 8px;border-radius:4px;cursor:pointer;margin-left:12px;">X</button>'
            . '</div>';

        $bannerStyle = '<style id="musedock-banner-style">body { padding-top: 42px !important; }</style>';

        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $injectCss . $bannerStyle . "\n</head>", $html);
        } else {
            // No </head> found, prepend
            $html = $injectCss . $bannerStyle . "\n" . $html;
        }

        if (stripos($html, '<body') !== false) {
            $html = preg_replace('/(<body[^>]*>)/i', '$1' . "\n" . $banner, $html);
        } else {
            $html = $banner . "\n" . $html;
        }

        // Inject unified JS before </body>
        if (!empty($injectJs)) {
            if (stripos($html, '</body>') !== false) {
                $html = str_ireplace('</body>', $injectJs . "</body>", $html);
            } else {
                $html .= $injectJs;
            }
        }

        return $html;
    }

    /**
     * Convert relative URLs (src, href, action, poster, srcset, data-src) to absolute.
     */
    private function makeUrlsAbsolute(string $html, string $baseUrl): string
    {
        $attrs = ['src', 'href', 'action', 'poster', 'data-src', 'data-lazy-src'];

        foreach ($attrs as $attr) {
            $html = preg_replace_callback(
                '/(' . preg_quote($attr, '/') . '=["\'])([^"\']+)(["\'])/i',
                function ($m) use ($baseUrl, $attr) {
                    $val = $m[2];
                    // Skip if already absolute, data:, javascript:, mailto:, tel:, #anchor
                    if (preg_match('/^(https?:|\/\/|data:|javascript:|mailto:|tel:|#)/i', $val)) {
                        return $m[0];
                    }
                    // Skip stylesheet hrefs (we removed those)
                    if ($attr === 'href' && preg_match('/\.css(\?|$)/i', $val)) {
                        return $m[0];
                    }
                    $absolute = $this->resolveUrl($val, $baseUrl);
                    return $m[1] . $absolute . $m[3];
                },
                $html
            );
        }

        // Also handle srcset (comma-separated)
        $html = preg_replace_callback(
            '/(srcset=["\'])([^"\']+)(["\'])/i',
            function ($m) use ($baseUrl) {
                $parts = explode(',', $m[2]);
                $fixed = [];
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (preg_match('/^(\S+)(\s+.*)$/', $part, $pm)) {
                        $url = $pm[1];
                        $rest = $pm[2];
                        if (!preg_match('/^(https?:|\/\/|data:)/i', $url)) {
                            $url = $this->resolveUrl($url, $baseUrl);
                        }
                        $fixed[] = $url . $rest;
                    } else {
                        $fixed[] = $part;
                    }
                }
                return $m[1] . implode(', ', $fixed) . $m[3];
            },
            $html
        );

        return $html;
    }

    /**
     * Download the unified JS file.
     */
    public function downloadJs()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $js = $_SESSION['css_auditor_unified_js'] ?? '';
        $sourceUrl = $_SESSION['css_auditor_source_url'] ?? 'unknown';

        if (empty($js)) {
            flash('error', 'No hay JS disponible. Analiza una URL primero.');
            header('Location: /musedock/theme-extractor');
            exit;
        }

        $domain = parse_url($sourceUrl, PHP_URL_HOST) ?: 'site';
        $filename = preg_replace('/[^a-z0-9.-]/', '_', $domain) . '_unified.js';

        $header = "/*\n * Unified JS generated by MuseDock CSS Auditor\n * Source: {$sourceUrl}\n * Date: " . date('Y-m-d H:i:s') . "\n */\n\n";

        header('Content-Type: application/javascript; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($header . $js));
        echo $header . $js;
        exit;
    }

    /**
     * Return unified JS as JSON.
     */
    public function getUnifiedJs()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'js'        => $_SESSION['css_auditor_unified_js'] ?? '',
            'sourceUrl' => $_SESSION['css_auditor_source_url'] ?? '',
        ]);
        exit;
    }

    // ─── Progress helpers ───

    private function writeProgress(string $file, string $message, int $current, int $total, bool $done = false, array $fileResults = []): void
    {
        file_put_contents($file, json_encode([
            'message'     => $message,
            'current'     => $current,
            'total'       => $total,
            'done'        => $done,
            'fileResults' => $fileResults,
            'timestamp'   => time(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function cleanupProgressFiles(): void
    {
        foreach (glob($this->progressDir . '/*.json') as $f) {
            if (filemtime($f) < time() - 300) @unlink($f);
        }
    }

    // ─── Fetch helpers ───

    private function fetchUrl(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_ENCODING       => '',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($code >= 200 && $code < 400 && $body) ? $body : null;
    }

    private function getBaseUrl(string $url): string
    {
        $parsed = parse_url($url);
        return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
    }

    /**
     * Discover all stylesheets from HTML, download each and report progress.
     */
    private function discoverStylesheetUrls(string $html, string $baseUrl, string $progressFile): array
    {
        $sheets = [];
        $seenUrls = [];

        // Match all <link> tags with rel="stylesheet"
        if (preg_match_all('/<link\b[^>]*>/i', $html, $linkTags)) {
            foreach ($linkTags[0] as $tag) {
                if (!preg_match('/rel=["\']stylesheet["\']/i', $tag)) continue;
                if (!preg_match('/href=["\']([^"\']+)["\']/i', $tag, $hrefMatch)) continue;

                $fullUrl = $this->resolveUrl($hrefMatch[1], $baseUrl);
                if (isset($seenUrls[$fullUrl])) continue;
                $seenUrls[$fullUrl] = true;

                $label = basename(parse_url($fullUrl, PHP_URL_PATH) ?: $fullUrl);
                $this->writeProgress($progressFile, "Descargando: {$label}", count($sheets), 0);

                $content = $this->fetchUrl($fullUrl);
                if ($content) {
                    $sheets[] = ['url' => $fullUrl, 'is_inline' => false, 'content' => $content];
                }
            }
        }

        // Inline <style> blocks
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $styleMatches)) {
            foreach ($styleMatches[1] as $styleContent) {
                if (!empty(trim($styleContent))) {
                    $sheets[] = ['url' => null, 'is_inline' => true, 'content' => $styleContent];
                }
            }
        }

        return $sheets;
    }

    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) return $href;
        if (str_starts_with($href, '//')) return 'https:' . $href;
        if (str_starts_with($href, '/')) return rtrim($baseUrl, '/') . $href;
        return rtrim($baseUrl, '/') . '/' . $href;
    }

    /**
     * Discover all external scripts and inline script blocks from HTML.
     */
    private function discoverScripts(string $html, string $baseUrl, string $progressFile): array
    {
        $scripts = [];
        $seenUrls = [];

        // External <script src="...">
        if (preg_match_all('/<script\b[^>]*src=["\']([^"\']+)["\'][^>]*>\s*<\/script>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $fullUrl = $this->resolveUrl($src, $baseUrl);
                if (isset($seenUrls[$fullUrl])) continue;
                $seenUrls[$fullUrl] = true;

                $this->writeProgress($progressFile, 'Descargando JS: ' . basename(parse_url($fullUrl, PHP_URL_PATH) ?: 'script.js'), 0, 0);

                $content = $this->fetchUrl($fullUrl);
                if ($content) {
                    $scripts[] = ['url' => $fullUrl, 'is_inline' => false, 'content' => $content];
                }
            }
        }

        // Also match self-closing or src-only script tags
        if (preg_match_all('/<script\b[^>]*src=["\']([^"\']+)["\'][^>]*\/?\s*>/i', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $fullUrl = $this->resolveUrl($src, $baseUrl);
                if (isset($seenUrls[$fullUrl])) continue;
                $seenUrls[$fullUrl] = true;

                $content = $this->fetchUrl($fullUrl);
                if ($content) {
                    $scripts[] = ['url' => $fullUrl, 'is_inline' => false, 'content' => $content];
                }
            }
        }

        // Inline <script> blocks (without src attribute)
        if (preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[1] as $scriptContent) {
                $trimmed = trim($scriptContent);
                if (!empty($trimmed) && strlen($trimmed) > 10) {
                    $scripts[] = ['url' => null, 'is_inline' => true, 'content' => $trimmed];
                }
            }
        }

        return $scripts;
    }

    // ─── HTML parsing ───

    private function extractHtmlSelectors(string $html): array
    {
        $selectors = ['tags' => [], 'classes' => [], 'ids' => []];

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//*') as $el) {
            $selectors['tags'][strtolower($el->nodeName)] = true;

            $classAttr = $el->getAttribute('class');
            if ($classAttr) {
                foreach (preg_split('/\s+/', trim($classAttr)) as $cls) {
                    if ($cls !== '') $selectors['classes'][$cls] = true;
                }
            }

            $idAttr = $el->getAttribute('id');
            if ($idAttr) $selectors['ids'][$idAttr] = true;
        }

        return $selectors;
    }

    // ─── CSS analysis ───

    private function analyzeCss(string $css, array $htmlSelectors): array
    {
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        $blocks = $this->parseBlocks($css);

        $totalSelectors = 0;
        $usedSelectors  = 0;
        $cleanParts     = [];

        foreach ($blocks as $block) {
            if ($block['type'] === 'at-rule') {
                if (str_starts_with($block['name'], '@keyframes') ||
                    str_starts_with($block['name'], '@font-face') ||
                    str_starts_with($block['name'], '@import') ||
                    str_starts_with($block['name'], '@charset')) {
                    $cleanParts[] = $block['raw'];
                    continue;
                }

                $inner = $this->analyzeCss($block['body'], $htmlSelectors);
                $totalSelectors += $inner['total_selectors'];
                $usedSelectors  += $inner['used_selectors'];

                if (!empty(trim($inner['clean_css']))) {
                    $cleanParts[] = $block['name'] . " {\n" . $inner['clean_css'] . "\n}";
                }
            } elseif ($block['type'] === 'rule') {
                $individualSelectors = array_map('trim', explode(',', $block['selector']));
                $usedIndividual = [];

                foreach ($individualSelectors as $sel) {
                    $totalSelectors++;
                    if ($this->isSelectorUsed($sel, $htmlSelectors)) {
                        $usedSelectors++;
                        $usedIndividual[] = $sel;
                    }
                }

                if (!empty($usedIndividual)) {
                    $cleanParts[] = implode(",\n", $usedIndividual) . " {\n" . trim($block['declarations']) . "\n}";
                }
            }
        }

        $cleanCss     = implode("\n\n", $cleanParts);
        $originalSize = strlen($css);
        $cleanSize    = strlen($cleanCss);

        return [
            'clean_css'       => $cleanCss,
            'reduction_pct'   => $originalSize > 0 ? round((1 - $cleanSize / $originalSize) * 100) : 0,
            'total_selectors' => $totalSelectors,
            'used_selectors'  => $usedSelectors,
            'used_pct'        => $totalSelectors > 0 ? round(($usedSelectors / $totalSelectors) * 100) : 0,
        ];
    }

    private function parseBlocks(string $css): array
    {
        $blocks = [];
        $css = trim($css);
        $len = strlen($css);
        $i = 0;

        while ($i < $len) {
            while ($i < $len && ctype_space($css[$i])) $i++;
            if ($i >= $len) break;

            if ($css[$i] === '@') {
                $start = $i;
                $nameEnd = $i;
                while ($nameEnd < $len && $css[$nameEnd] !== '{' && $css[$nameEnd] !== ';') $nameEnd++;

                $name = trim(substr($css, $start, $nameEnd - $start));

                if ($nameEnd < $len && $css[$nameEnd] === ';') {
                    $blocks[] = ['type' => 'at-rule', 'name' => $name, 'body' => '', 'raw' => $name . ';'];
                    $i = $nameEnd + 1;
                } elseif ($nameEnd < $len && $css[$nameEnd] === '{') {
                    $bodyStart = $nameEnd + 1;
                    $depth = 1;
                    $j = $bodyStart;
                    while ($j < $len && $depth > 0) {
                        if ($css[$j] === '{') $depth++;
                        elseif ($css[$j] === '}') $depth--;
                        $j++;
                    }
                    $body = substr($css, $bodyStart, $j - $bodyStart - 1);
                    $blocks[] = ['type' => 'at-rule', 'name' => $name, 'body' => $body, 'raw' => substr($css, $start, $j - $start)];
                    $i = $j;
                } else {
                    $i = $nameEnd;
                }
            } else {
                $bracePos = strpos($css, '{', $i);
                if ($bracePos === false) break;

                $selector = trim(substr($css, $i, $bracePos - $i));
                $depth = 1;
                $j = $bracePos + 1;
                while ($j < $len && $depth > 0) {
                    if ($css[$j] === '{') $depth++;
                    elseif ($css[$j] === '}') $depth--;
                    $j++;
                }
                $declarations = substr($css, $bracePos + 1, $j - $bracePos - 2);

                if (!empty(trim($selector))) {
                    $blocks[] = ['type' => 'rule', 'selector' => $selector, 'declarations' => $declarations];
                }
                $i = $j;
            }
        }

        return $blocks;
    }

    private function isSelectorUsed(string $selector, array $htmlSelectors): bool
    {
        $selector = trim($selector);
        $selector = preg_replace('/::?[a-z-]+(\([^)]*\))?/i', '', $selector);
        $selector = trim($selector);

        if (empty($selector) || $selector === '*') return true;

        $parts = preg_split('/[\s>+~]+/', $selector);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || $part === '*') continue;

            if (preg_match_all('/#([a-zA-Z0-9_-]+)/', $part, $m)) {
                foreach ($m[1] as $id) {
                    if (!isset($htmlSelectors['ids'][$id])) return false;
                }
            }

            if (preg_match_all('/\.([a-zA-Z0-9_-]+)/', $part, $m)) {
                foreach ($m[1] as $cls) {
                    if (!isset($htmlSelectors['classes'][$cls])) return false;
                }
            }

            $tagOnly = trim(preg_replace('/[.#\[][^ ]*/', '', $part));
            if ($tagOnly && !isset($htmlSelectors['tags'][strtolower($tagOnly)])) {
                if (preg_match('/^[a-z][a-z0-9]*$/i', $tagOnly)) return false;
            }
        }

        return true;
    }
}
