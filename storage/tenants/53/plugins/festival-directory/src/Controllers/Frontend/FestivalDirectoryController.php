<?php

namespace FestivalDirectory\Controllers\Frontend;

use FestivalDirectory\Models\Festival;
use FestivalDirectory\Models\FestivalCategory;
use FestivalDirectory\Models\FestivalTag;
use FestivalDirectory\Models\FestivalClaim;
use FestivalDirectory\Requests\FestivalRequest;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Services\TenantManager;

class FestivalDirectoryController
{
    private function getTenantId(): ?int
    {
        return TenantManager::currentTenantId();
    }

    /**
     * Directory listing — /festivals
     */
    public function index()
    {
        $tenantId = $this->getTenantId();
        $perPage = 12;
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $query = Festival::query()
            ->whereRaw("(status IN ('published','verified','claimed'))")
            ->whereRaw("(deleted_at IS NULL)");

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        // Filters
        $typeFilter = $_GET['type'] ?? '';
        $countryFilter = $_GET['country'] ?? '';
        $submissionFilter = $_GET['submissions'] ?? '';

        if (!empty($typeFilter)) {
            $query->where('type', $typeFilter);
        }
        if (!empty($countryFilter)) {
            $query->whereRaw("LOWER(country) = ?", [strtolower($countryFilter)]);
        }
        if (!empty($submissionFilter)) {
            $query->where('submission_status', $submissionFilter);
        }

        $query->orderBy('featured', 'DESC')->orderBy('name', 'ASC');

        $totalCount = $query->count();
        $totalPages = $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1;
        $festivals = $query->limit($perPage)->offset($offset)->get();

        // Batch load categories
        $festivalCategories = $this->batchLoadCategories($festivals);

        // Load filter data
        $pdo = Database::connect();
        $params = $tenantId !== null ? [$tenantId] : [];
        $tenantWhere = $tenantId !== null ? "AND tenant_id = ?" : "";

        $countriesStmt = $pdo->prepare("SELECT DISTINCT country FROM festivals WHERE deleted_at IS NULL AND status IN ('published','verified','claimed') {$tenantWhere} ORDER BY country");
        $countriesStmt->execute($params);
        $countries = array_column($countriesStmt->fetchAll(\PDO::FETCH_ASSOC), 'country');

        $categories = FestivalCategory::query();
        if ($tenantId !== null) {
            $categories->where('tenant_id', $tenantId);
        }
        $categories = $categories->orderBy('name', 'ASC')->get();

        $pagination = [
            'current_page' => $currentPage,
            'total_pages'  => $totalPages,
            'total_posts'  => $totalCount,
            'per_page'     => $perPage,
        ];

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'FestivalNews') : 'FestivalNews';

        View::addGlobalData([
            '__page_title' => 'Directorio de Festivales | ' . $siteName,
            '__page_description' => 'Descubre festivales de cine, música, artes y más. Directorio completo con información actualizada.',
        ]);

        echo View::renderTheme('festivals/index', [
            'title'              => 'Directorio de Festivales',
            'festivals'          => $festivals,
            'festivalCategories' => $festivalCategories,
            'pagination'         => $pagination,
            'countries'          => $countries,
            'categories'         => $categories,
            'types'              => Festival::getTypes(),
            'typeFilter'         => $typeFilter,
            'countryFilter'      => $countryFilter,
            'submissionFilter'   => $submissionFilter,
        ]);
    }

    /**
     * Single festival profile — /festivals/{slug}
     */
    public function show($slug)
    {
        $tenantId = $this->getTenantId();

        $query = Festival::query()
            ->where('slug', $slug)
            ->whereRaw("(status IN ('published','verified','claimed'))")
            ->whereRaw("(deleted_at IS NULL)");

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $festival = $query->first();

        if (!$festival) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Festival no encontrado']);
            return;
        }

        if (is_array($festival) || $festival instanceof \stdClass) {
            $festival = new Festival($festival);
        }

        // Increment views
        $festival->incrementViewCount();

        // Load categories and tags
        $categories = $this->loadFestivalCategories($festival->id);
        $tags = $this->loadFestivalTags($festival->id);

        // Pending claims count
        $pdo = Database::connect();
        $claimStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM festival_claims WHERE festival_id = ? AND status = 'pending'");
        $claimStmt->execute([$festival->id]);
        $hasPendingClaim = (int)$claimStmt->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;

        // SEO
        $siteName = function_exists('site_setting') ? site_setting('site_name', 'FestivalNews') : 'FestivalNews';
        $seoTitle = $festival->seo_title ?: $festival->name;
        $seoDesc = $festival->seo_description ?: ($festival->short_description ?: mb_substr(strip_tags($festival->description ?? ''), 0, 160));
        $seoImage = $festival->seo_image ?: ($festival->featured_image ?: $festival->logo);

        // JSON-LD Event schema
        $eventLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'Event',
            'name'     => $festival->name,
            'description' => $seoDesc,
            'url'      => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . festival_url($festival->slug),
        ];

        if ($festival->start_date) {
            $eventLd['startDate'] = $festival->start_date;
        }
        if ($festival->end_date) {
            $eventLd['endDate'] = $festival->end_date;
        }
        if ($seoImage) {
            $eventLd['image'] = $seoImage;
        }
        if ($festival->country) {
            $eventLd['location'] = [
                '@type'   => 'Place',
                'name'    => $festival->venue ?: ($festival->city ?: $festival->country),
                'address' => [
                    '@type'            => 'PostalAddress',
                    'addressLocality'  => $festival->city ?? '',
                    'addressCountry'   => $festival->country,
                ],
            ];
        }
        if ($festival->website_url) {
            $eventLd['organizer'] = [
                '@type' => 'Organization',
                'name'  => $festival->name,
                'url'   => $festival->website_url,
            ];
        }

        // Breadcrumbs JSON-LD
        $breadcrumbLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => 'https://' . ($_SERVER['HTTP_HOST'] ?? '')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Festivales', 'item' => 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/festivals'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $festival->name],
            ],
        ];

        View::addGlobalData([
            '__page_title'       => $seoTitle . ' | ' . $siteName,
            '__page_description' => $seoDesc,
            '__og_title'         => $seoTitle,
            '__og_description'   => $seoDesc,
            '__og_image'         => $seoImage,
            '__jsonld_event'     => json_encode($eventLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            '__jsonld_breadcrumb' => json_encode($breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        echo View::renderTheme('festivals/single', [
            'festival'        => $festival,
            'categories'      => $categories,
            'tags'            => $tags,
            'types'           => Festival::getTypes(),
            'frequencies'     => Festival::getFrequencies(),
            'submissionStatuses' => Festival::getSubmissionStatuses(),
            'hasPendingClaim' => $hasPendingClaim,
            'seoTitle'        => $seoTitle,
            'seoDesc'         => $seoDesc,
            'seoImage'        => $seoImage,
        ]);
    }

    /**
     * Festivals by category — /festivals/category/{slug}
     */
    public function category($slug)
    {
        $tenantId = $this->getTenantId();

        $catQuery = FestivalCategory::where('slug', $slug);
        if ($tenantId !== null) {
            $catQuery->where('tenant_id', $tenantId);
        }
        $category = $catQuery->first();

        if (!$category) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Categoría no encontrada']);
            return;
        }

        $perPage = 12;
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $pdo = Database::connect();
        $tenantWhere = $tenantId !== null ? "AND f.tenant_id = ?" : "";
        $params = $tenantId !== null ? [$category->id, $tenantId] : [$category->id];

        $countStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT f.id) as cnt
            FROM festivals f
            INNER JOIN festival_category_pivot fcp ON fcp.festival_id = f.id
            WHERE fcp.category_id = ? AND f.status IN ('published','verified','claimed') AND f.deleted_at IS NULL {$tenantWhere}
        ");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        $stmt = $pdo->prepare("
            SELECT f.*
            FROM festivals f
            INNER JOIN festival_category_pivot fcp ON fcp.festival_id = f.id
            WHERE fcp.category_id = ? AND f.status IN ('published','verified','claimed') AND f.deleted_at IS NULL {$tenantWhere}
            ORDER BY f.featured DESC, f.name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $festivals = array_map(fn($r) => new Festival($r), $rows);

        $festivalCategories = $this->batchLoadCategories($festivals);

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'FestivalNews') : 'FestivalNews';

        View::addGlobalData([
            '__page_title' => ($category->seo_title ?: $category->name . ' — Festivales') . ' | ' . $siteName,
            '__page_description' => $category->seo_description ?: 'Festivales en la categoría ' . $category->name,
        ]);

        echo View::renderTheme('festivals/category', [
            'title'              => $category->name,
            'category'           => $category,
            'festivals'          => $festivals,
            'festivalCategories' => $festivalCategories,
            'pagination'         => [
                'current_page' => $currentPage,
                'total_pages'  => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
                'total_posts'  => $totalCount,
                'per_page'     => $perPage,
            ],
        ]);
    }

    /**
     * Festivals by tag — /festivals/tag/{slug}
     */
    public function tag($slug)
    {
        $tenantId = $this->getTenantId();

        $tagQuery = FestivalTag::where('slug', $slug);
        if ($tenantId !== null) {
            $tagQuery->where('tenant_id', $tenantId);
        }
        $tag = $tagQuery->first();

        if (!$tag) {
            http_response_code(404);
            echo View::renderTheme('errors/404', ['message' => 'Tag no encontrado']);
            return;
        }

        $perPage = 12;
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $pdo = Database::connect();
        $tenantWhere = $tenantId !== null ? "AND f.tenant_id = ?" : "";
        $params = $tenantId !== null ? [$tag->id, $tenantId] : [$tag->id];

        $countStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT f.id) as cnt
            FROM festivals f
            INNER JOIN festival_tag_pivot ftp ON ftp.festival_id = f.id
            WHERE ftp.tag_id = ? AND f.status IN ('published','verified','claimed') AND f.deleted_at IS NULL {$tenantWhere}
        ");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        $stmt = $pdo->prepare("
            SELECT f.*
            FROM festivals f
            INNER JOIN festival_tag_pivot ftp ON ftp.festival_id = f.id
            WHERE ftp.tag_id = ? AND f.status IN ('published','verified','claimed') AND f.deleted_at IS NULL {$tenantWhere}
            ORDER BY f.featured DESC, f.name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $festivals = array_map(fn($r) => new Festival($r), $rows);

        $festivalCategories = $this->batchLoadCategories($festivals);

        echo View::renderTheme('festivals/tag', [
            'title'              => 'Tag: ' . $tag->name,
            'tag'                => $tag,
            'festivals'          => $festivals,
            'festivalCategories' => $festivalCategories,
            'pagination'         => [
                'current_page' => $currentPage,
                'total_pages'  => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
                'total_posts'  => $totalCount,
                'per_page'     => $perPage,
            ],
        ]);
    }

    /**
     * Festivals by country — /festivals/country/{slug}
     */
    public function country($slug)
    {
        $tenantId = $this->getTenantId();

        // Decode country slug: "espana" -> match via LOWER
        $countrySlug = str_replace('-', ' ', strtolower($slug));

        $perPage = 12;
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($currentPage - 1) * $perPage;

        $query = Festival::query()
            ->whereRaw("(status IN ('published','verified','claimed'))")
            ->whereRaw("(deleted_at IS NULL)")
            ->whereRaw("LOWER(REPLACE(country, ' ', '-')) = ? OR LOWER(country) LIKE ?", [$slug, "%{$countrySlug}%"]);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $totalCount = $query->count();
        $festivals = $query->orderBy('featured', 'DESC')->orderBy('name', 'ASC')
            ->limit($perPage)->offset($offset)->get();

        $countryName = !empty($festivals) ? $festivals[0]->country : ucfirst($countrySlug);

        $festivalCategories = $this->batchLoadCategories($festivals);

        $siteName = function_exists('site_setting') ? site_setting('site_name', 'FestivalNews') : 'FestivalNews';

        View::addGlobalData([
            '__page_title' => 'Festivales en ' . $countryName . ' | ' . $siteName,
            '__page_description' => 'Descubre los festivales en ' . $countryName . '. Directorio completo y actualizado.',
        ]);

        echo View::renderTheme('festivals/country', [
            'title'              => 'Festivales en ' . $countryName,
            'countryName'        => $countryName,
            'countrySlug'        => $slug,
            'festivals'          => $festivals,
            'festivalCategories' => $festivalCategories,
            'pagination'         => [
                'current_page' => $currentPage,
                'total_pages'  => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1,
                'total_posts'  => $totalCount,
                'per_page'     => $perPage,
            ],
        ]);
    }

    /**
     * Submit a claim — POST /festivals/{slug}/claim
     */
    public function submitClaim($slug)
    {
        $tenantId = $this->getTenantId();

        $festival = Festival::where('slug', $slug)
            ->whereRaw("(deleted_at IS NULL)");

        if ($tenantId !== null) {
            $festival->where('tenant_id', $tenantId);
        }

        $festival = $festival->first();

        if (!$festival) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Festival no encontrado.']);
            return;
        }

        $data = $_POST;
        $errors = FestivalRequest::validateClaim($data);

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        // Check for existing pending claim from same email
        $existing = FestivalClaim::where('festival_id', $festival->id)
            ->where('user_email', $data['user_email'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Ya tienes una solicitud pendiente para este festival.']);
            return;
        }

        // Append authorization declaration to verification details
        $verificationDetails = trim($data['verification_details'] ?? '');
        $verificationDetails .= "\n\n--- Declaración legal ---\nEl usuario declaró ser organizador o representante autorizado de este festival. (IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida') . ", Fecha: " . date('Y-m-d H:i:s') . ")";

        FestivalClaim::create([
            'tenant_id'            => $tenantId ?? 0,
            'festival_id'          => $festival->id,
            'user_name'            => trim($data['user_name']),
            'user_email'           => trim($data['user_email']),
            'user_role'            => trim($data['user_role'] ?? ''),
            'verification_details' => $verificationDetails,
            'status'               => 'pending',
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Tu solicitud ha sido enviada. Te contactaremos pronto.',
        ]);
    }

    // ─── Private helpers ───────────────────────────

    private function batchLoadCategories(array $festivals): array
    {
        if (empty($festivals)) return [];

        $ids = array_map(fn($f) => is_object($f) ? $f->id : $f['id'], $festivals);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT fp.festival_id, c.id, c.name, c.slug, c.color
            FROM festival_category_pivot fp
            INNER JOIN festival_categories c ON c.id = fp.category_id
            WHERE fp.festival_id IN ({$placeholders})
        ");
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['festival_id']][] = $row;
        }
        return $result;
    }

    private function loadFestivalCategories(int $festivalId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT c.* FROM festival_categories c
            INNER JOIN festival_category_pivot fp ON fp.category_id = c.id
            WHERE fp.festival_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$festivalId]);
        return array_map(fn($r) => new FestivalCategory($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function loadFestivalTags(int $festivalId): array
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT t.* FROM festival_tags t
            INNER JOIN festival_tag_pivot fp ON fp.tag_id = t.id
            WHERE fp.festival_id = ?
            ORDER BY t.name
        ");
        $stmt->execute([$festivalId]);
        return array_map(fn($r) => new FestivalTag($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
