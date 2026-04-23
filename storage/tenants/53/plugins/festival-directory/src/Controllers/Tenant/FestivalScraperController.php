<?php

namespace FestivalDirectory\Controllers\Tenant;

use FestivalDirectory\Models\Festival;
use FestivalDirectory\Models\FestivalType;
use FestivalDirectory\Models\FestivalCategory;
use FestivalDirectory\Models\FestivalTag;
use FestivalDirectory\Models\FestivalSubmissionLink;
use FestivalDirectory\Services\FestivalScraper;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Services\AuditLogger;

class FestivalScraperController
{
    private function getTenantId(): int
    {
        $tenantId = TenantManager::currentTenantId();
        if ($tenantId === null) {
            flash('error', 'Tenant no identificado.');
            header('Location: /' . admin_path() . '/dashboard');
            exit;
        }
        return $tenantId;
    }

    /**
     * Scraper dashboard — main page.
     */
    public function index()
    {
        $tenantId = $this->getTenantId();
        $sources = FestivalScraper::getSources();

        echo festival_render_admin('tenant.scraper.index', [
            'title'   => 'Importar Festivales',
            'sources' => $sources,
        ]);
    }

    /**
     * Search/browse festivals from a source (AJAX).
     * GET /admin/festivals/scraper/search?source=festhome&page=1
     */
    public function search()
    {
        $tenantId = $this->getTenantId();
        $source = $_GET['source'] ?? 'festhome';
        $page   = max(1, (int)($_GET['page'] ?? 1));

        header('Content-Type: application/json');

        // Dispatch to source
        if ($source === 'festhome') {
            $result = FestivalScraper::festhomeSearch($page);
        } elseif ($source === 'escritores') {
            $result = FestivalScraper::escritoresSearch($page);
        } else {
            echo json_encode(['festivals' => [], 'error' => 'Fuente no soportada.']);
            return;
        }

        // Check which are already imported (all sources)
        if (!empty($result['festivals'])) {
            foreach ($result['festivals'] as &$fest) {
                $duplicate = FestivalScraper::findDuplicate($tenantId, $fest['name']);
                $fest['already_imported'] = $duplicate !== null;
                $fest['duplicate'] = $duplicate;
            }
        }

        echo json_encode($result);
    }

    /**
     * Get detailed data for a festival from source (AJAX).
     * GET /admin/festivals/scraper/detail?source=festhome&id=7803
     */
    public function detail()
    {
        $source = $_GET['source'] ?? 'festhome';
        $id     = (int)($_GET['id'] ?? 0);

        header('Content-Type: application/json');

        if (!$id) {
            echo json_encode(['error' => 'ID requerido.']);
            return;
        }

        if ($source === 'festhome') {
            $data = FestivalScraper::festhomeDetail($id);
        } elseif ($source === 'escritores') {
            $data = FestivalScraper::escritoresDetail($id);
        } else {
            $data = null;
        }

        if ($data) {
            echo json_encode(['success' => true, 'festival' => $data]);
        } else {
            echo json_encode(['error' => 'No se pudo obtener datos.']);
        }
    }

    /**
     * Import a festival from scraped data (AJAX POST).
     * POST /admin/festivals/scraper/import
     */
    public function import()
    {
        $tenantId = $this->getTenantId();

        header('Content-Type: application/json');

        $data = $_POST;
        $forceImport = !empty($data['force_import']);

        if (empty($data['name'])) {
            echo json_encode(['success' => false, 'error' => 'Nombre requerido.']);
            return;
        }

        $source = $data['source'] ?? 'manual';

        // Check for duplicate
        $duplicate = FestivalScraper::findDuplicate($tenantId, $data['name']);

        if ($duplicate && !$forceImport) {
            // Duplicate found — offer merge or force import
            echo json_encode([
                'success'   => false,
                'duplicate' => true,
                'existing'  => $duplicate,
                'message'   => 'Festival similar encontrado: "' . $duplicate['name'] . '" (' . $duplicate['match_type'] . ', ' . ($duplicate['similarity'] ?? 100) . '% similar).',
                'actions'   => ['merge', 'force'],
            ]);
            return;
        }

        // If merge requested (duplicate exists and user chose merge)
        if (!empty($data['merge_into'])) {
            $mergeId = (int)$data['merge_into'];
            $existing = Festival::where('id', $mergeId)->where('tenant_id', $tenantId)->first();
            if (!$existing) {
                echo json_encode(['success' => false, 'error' => 'Festival destino no encontrado.']);
                return;
            }

            // Add submission link from this source
            $this->addSubmissionLinkFromSource($mergeId, $source, $data);

            echo json_encode([
                'success'  => true,
                'merged'   => true,
                'message'  => 'Link de submission añadido al festival existente "' . $existing->name . '".',
                'id'       => $mergeId,
                'edit_url' => festival_admin_url($mergeId . '/edit'),
            ]);
            return;
        }

        // Create new festival
        $slug = festival_slugify($data['name']);

        // Ensure unique slug
        $slugCheck = Festival::where('tenant_id', $tenantId)->where('slug', $slug)->first();
        if ($slugCheck) {
            $slug .= '-' . time();
        }

        // Download logo to local server
        $localLogo = null;
        if (!empty($data['logo'])) {
            $localLogo = $this->downloadLogo($data['logo'], $slug, $tenantId);
        }

        // Description: only use the short factual description, NOT full rules/bases
        // This avoids copyright issues — festival can fill in details via claim
        $rawDesc = trim($data['description'] ?? '');
        // Truncate to first ~500 chars (factual summary, not legal text)
        $description = mb_substr($rawDesc, 0, 500);
        if (mb_strlen($rawDesc) > 500) {
            // Cut at last sentence boundary
            $lastDot = mb_strrpos($description, '.');
            if ($lastDot > 200) {
                $description = mb_substr($description, 0, $lastDot + 1);
            }
        }

        $festivalData = [
            'tenant_id'         => $tenantId,
            'name'              => trim($data['name']),
            'slug'              => $slug,
            'short_description' => mb_substr($description, 0, 300),
            'description'       => $description,
            'logo'              => $localLogo ?? ($data['logo'] ?? null),
            'type'              => $data['type'] ?? 'film_festival',
            'country'           => trim($data['country'] ?? 'Desconocido'),
            'city'              => trim($data['city'] ?? ''),
            'venue'             => trim($data['venue'] ?? ''),
            'address'           => trim($data['address'] ?? ''),
            'start_date'        => !empty($data['start_date']) ? $data['start_date'] : null,
            'end_date'          => !empty($data['end_date']) ? $data['end_date'] : null,
            'deadline_date'     => !empty($data['deadline_date']) ? $data['deadline_date'] : null,
            'website_url'       => $data['website_url'] ?? null,
            'email'             => $data['email'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'social_facebook'   => $data['social_facebook'] ?? null,
            'social_instagram'  => $data['social_instagram'] ?? null,
            'social_twitter'    => $data['social_twitter'] ?? null,
            'social_youtube'    => $data['social_youtube'] ?? null,
            'social_vimeo'      => $data['social_vimeo'] ?? null,
            'submission_status' => $data['submission_status'] ?? 'closed',
            'status'            => 'draft',
            'claim_token'       => bin2hex(random_bytes(32)),
            'base_locale'       => 'es',
        ];

        $festival = Festival::create($festivalData);

        if ($festival && $festival->id) {
            // Register slug
            try {
                $pdo = Database::connect();
                $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, ?, ?)")
                    ->execute(['festivals', $festival->id, $slug, $tenantId, 'festivals']);
            } catch (\Exception $e) {}

            // Add submission link from source
            $this->addSubmissionLinkFromSource($festival->id, $source, $data);

            // Auto-assign categories and tags from scraped data
            $this->autoAssignTaxonomy($festival, $tenantId, $data);

            if (class_exists(AuditLogger::class)) {
                AuditLogger::log('festival.imported', 'festival', $festival->id, [
                    'name'      => $festivalData['name'],
                    'source'    => $source,
                    'source_id' => $data['source_id'] ?? null,
                ]);
            }

            echo json_encode([
                'success'  => true,
                'message'  => 'Festival "' . $festivalData['name'] . '" importado correctamente.',
                'id'       => $festival->id,
                'edit_url' => festival_admin_url($festival->id . '/edit'),
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al crear el festival.']);
        }
    }

    /**
     * Add submission link from scraper source to a festival.
     */
    /**
     * Download a remote logo and save it locally.
     * Returns local path or null on failure.
     */
    private function downloadLogo(string $remoteUrl, string $slug, int $tenantId): ?string
    {
        try {
            // Use tenant-aware upload path: /public/assets/uploads/festivals/{tenant_id}/
            $uploadSubdir = 'festivals/' . $tenantId;
            $dir = APP_ROOT . '/public/assets/uploads/' . $uploadSubdir;
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $ch = curl_init($remoteUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_USERAGENT      => 'Mozilla/5.0',
            ]);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpCode !== 200 || !$imageData || strlen($imageData) < 100) {
                return null;
            }

            // Determine extension from content type
            $ext = 'jpg';
            if (strpos($contentType, 'png') !== false) $ext = 'png';
            elseif (strpos($contentType, 'webp') !== false) $ext = 'webp';
            elseif (strpos($contentType, 'gif') !== false) $ext = 'gif';

            // Clean filename: festival-slug_logo.ext
            $filename = preg_replace('/[^a-z0-9\-]/', '', $slug) . '_logo.' . $ext;
            $filepath = $dir . '/' . $filename;

            file_put_contents($filepath, $imageData);

            // Return web-accessible path (works for any tenant)
            return '/assets/uploads/' . $uploadSubdir . '/' . $filename;
        } catch (\Exception $e) {
            error_log("Logo download error for {$slug}: " . $e->getMessage());
            return null;
        }
    }

    private function addSubmissionLinkFromSource(int $festivalId, string $source, array $data): void
    {
        // Map source to platform + URL
        $links = [];

        if ($source === 'festhome' && !empty($data['submission_festhome_url'])) {
            $links[] = ['platform' => 'festhome', 'url' => $data['submission_festhome_url']];
        }
        if (!empty($data['submission_filmfreeway_url'])) {
            $links[] = ['platform' => 'filmfreeway', 'url' => $data['submission_filmfreeway_url']];
        }
        if (!empty($data['submission_festgate_url'])) {
            $links[] = ['platform' => 'festgate', 'url' => $data['submission_festgate_url']];
        }

        foreach ($links as $link) {
            FestivalSubmissionLink::addLink($festivalId, $link['platform'], $link['url']);
        }
    }

    /**
     * Auto-assign categories and tags based on scraped data.
     */
    private function autoAssignTaxonomy(Festival $festival, int $tenantId, array $data): void
    {
        try {
            // Get auto-detected slugs from scraper
            $catSlugs = $data['auto_categories'] ?? [];
            $tagSlugs = $data['auto_tags'] ?? [];

            // If scraper didn't provide them, detect now
            if (empty($catSlugs)) {
                $catSlugs = FestivalScraper::detectCategories($data['name'] ?? '', $data['description'] ?? '');
            }
            if (empty($tagSlugs)) {
                $tagSlugs = FestivalScraper::detectTags($data['name'] ?? '', $data['description'] ?? '', $data);
            }

            if (empty($catSlugs) && empty($tagSlugs)) return;

            $pdo = Database::connect();

            // Resolve category slugs to IDs
            if (!empty($catSlugs)) {
                $placeholders = implode(',', array_fill(0, count($catSlugs), '?'));
                $stmt = $pdo->prepare("SELECT id FROM festival_categories WHERE tenant_id = ? AND slug IN ({$placeholders})");
                $stmt->execute(array_merge([$tenantId], $catSlugs));
                $catIds = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'id');

                if (!empty($catIds)) {
                    $festival->syncCategories($catIds);
                }
            }

            // Resolve tag slugs to IDs
            if (!empty($tagSlugs)) {
                $placeholders = implode(',', array_fill(0, count($tagSlugs), '?'));
                $stmt = $pdo->prepare("SELECT id FROM festival_tags WHERE tenant_id = ? AND slug IN ({$placeholders})");
                $stmt->execute(array_merge([$tenantId], $tagSlugs));
                $tagIds = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'id');

                if (!empty($tagIds)) {
                    $festival->syncTags($tagIds);
                }
            }
        } catch (\Exception $e) {
            error_log("FestivalScraper autoAssignTaxonomy error: " . $e->getMessage());
        }
    }
}
