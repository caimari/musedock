<?php

namespace FestivalDirectory\Services;

/**
 * Festival Scraper Service
 *
 * Scrapes festival data from public directories (Festhome, etc.)
 * Designed to be respectful: rate-limited, 10 results at a time.
 *
 * FilmFreeway: blocked by Cloudflare — not supported via server-side scraping.
 * Festhome: works via AJAX endpoints, no anti-bot protection.
 */
class FestivalScraper
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const TIMEOUT = 15;

    /**
     * Available scraper sources.
     */
    public static function getSources(): array
    {
        return [
            'festhome' => [
                'name'    => 'Festhome',
                'icon'    => 'bi-globe',
                'enabled' => true,
                'note'    => 'Directorio público de festivales. Datos completos.',
            ],
            'filmfreeway' => [
                'name'    => 'FilmFreeway',
                'icon'    => 'bi-film',
                'enabled' => false,
                'note'    => 'Protegido por Cloudflare. No disponible para scraping automático.',
            ],
            'escritores' => [
                'name'    => 'Escritores.org',
                'icon'    => 'bi-book',
                'enabled' => true,
                'note'    => 'RSS público. Concursos literarios, certámenes, premios.',
            ],
        ];
    }

    // ─── FESTHOME ────────────────────────────────

    /**
     * Search festivals on Festhome (paginated, AJAX endpoint).
     *
     * @param int $page Page number (1-based)
     * @return array ['festivals' => [...], 'hasMore' => bool]
     */
    public static function festhomeSearch(int $page = 1): array
    {
        $url = "https://festhome.com/festivales/listado/page:{$page}";
        $html = self::fetch($url);

        if (!$html) {
            return ['festivals' => [], 'hasMore' => false, 'error' => 'No se pudo conectar con Festhome.'];
        }

        $festivals = [];

        // Extract IDs and names using regex (faster + more reliable than DOM for this HTML)
        // Pattern: <div id="festival_card-container-{ID}" ... followed by festival-card-title with name
        preg_match_all(
            '/festival_card-container-(\d+).*?festival-card-title[^>]*>([^<]+)</s',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $seen = [];
        foreach ($matches as $m) {
            $id = (int)$m[1];
            if (isset($seen[$id])) continue; // Deduplicate
            $seen[$id] = true;

            $name = trim(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
            $logo = "https://festhomedocs.com/festivals/{$id}/logo-{$id}.jpg";
            $banner = "https://festhomedocs.com/festivals/{$id}/banner_big-{$id}.jpg";

            $festivals[] = [
                'source'       => 'festhome',
                'source_id'    => $id,
                'name'         => $name,
                'date_text'    => '', // Not available in list view
                'logo'         => $logo,
                'banner'       => $banner,
                'detail_url'   => "https://filmmakers.festhome.com/festival/{$id}",
                'festhome_url' => "https://festhome.com/festival/{$id}",
            ];
        }

        return [
            'festivals' => $festivals,
            'hasMore'   => count($festivals) >= 8,
            'page'      => $page,
        ];
    }

    /**
     * Get detailed festival data from Festhome.
     *
     * @param int $festivalId Festhome festival ID
     * @return array|null Festival data or null on error
     */
    public static function festhomeDetail(int $festivalId): ?array
    {
        $url = "https://filmmakers.festhome.com/festival/{$festivalId}";
        $html = self::fetch($url);

        if (!$html) return null;

        $data = [
            'source'    => 'festhome',
            'source_id' => $festivalId,
        ];

        // 1. Extract JSON-LD via regex (most reliable — DOMDocument can't parse it properly)
        if (preg_match('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#s', $html, $ldMatch)) {
            // Clean control characters that break json_decode (newlines in description etc.)
            $cleanJson = preg_replace('/[\x00-\x1f\x7f]/', ' ', $ldMatch[1]);
            $json = json_decode(trim($cleanJson), true);
            if ($json && ($json['@type'] ?? '') === 'Event') {
                $data['name'] = $json['name'] ?? '';
                $data['description'] = $json['description'] ?? '';
                $data['start_date'] = $json['startDate'] ?? null;
                $data['end_date'] = $json['endDate'] ?? null;

                if (!empty($json['location']['address'])) {
                    $addr = $json['location']['address'];
                    $data['city'] = $addr['addressLocality'] ?? '';
                    $data['country'] = $addr['addressCountry'] ?? '';
                    $data['address'] = $addr['streetAddress'] ?? '';
                }
                $data['venue'] = $json['location']['name'] ?? '';

                if (!empty($json['image'])) {
                    $data['logo'] = is_array($json['image']) ? ($json['image'][0] ?? '') : $json['image'];
                }

                // Deadline from offers.availabilityEnds
                if (!empty($json['offers']['availabilityEnds'])) {
                    $data['deadline_date'] = $json['offers']['availabilityEnds'];
                }
            }
        }

        // 2. Extract social links — only those that belong to the festival, not Festhome
        // Look for links with specific festival social patterns (exclude festhome's own socials)
        preg_match_all('#href=["\']?(https?://(?:www\.)?(?:facebook|instagram|twitter|x|youtube|vimeo)\.com/[^"\'>\s]+)#i', $html, $socialMatches);
        if (!empty($socialMatches[1])) {
            foreach ($socialMatches[1] as $href) {
                // Skip Festhome's own social links
                if (preg_match('#/(festhome|endearu)#i', $href)) continue;

                if (strpos($href, 'facebook.com') !== false && !isset($data['social_facebook'])) {
                    $data['social_facebook'] = $href;
                } elseif (strpos($href, 'instagram.com') !== false && !isset($data['social_instagram'])) {
                    $data['social_instagram'] = $href;
                } elseif ((strpos($href, 'twitter.com') !== false || strpos($href, 'x.com') !== false) && !isset($data['social_twitter'])) {
                    $data['social_twitter'] = $href;
                } elseif (strpos($href, 'youtube.com') !== false && !isset($data['social_youtube'])) {
                    $data['social_youtube'] = $href;
                } elseif (strpos($href, 'vimeo.com') !== false && !isset($data['social_vimeo'])) {
                    $data['social_vimeo'] = $href;
                }
            }
        }

        // 3. Extract email (from mailto links, skip festhome emails)
        preg_match_all('#mailto:([^"\'>\s]+)#i', $html, $emailMatches);
        foreach ($emailMatches[1] ?? [] as $email) {
            if (!preg_match('#festhome|endearu#i', $email)) {
                $data['email'] = $email;
                break;
            }
        }

        // 4. Extract website URL (external links that are not social/festhome)
        preg_match_all('#href=["\']?(https?://[^"\'>\s]+)["\']?[^>]*>[^<]*(web|official|oficial|sitio)[^<]*<#i', $html, $webMatches);
        foreach ($webMatches[1] ?? [] as $href) {
            if (!preg_match('#(festhome|facebook|instagram|twitter|youtube|vimeo|linkedin|x\.com|endearu)#i', $href)) {
                $data['website_url'] = $href;
                break;
            }
        }

        // 5. Festhome submission URL
        $data['submission_festhome_url'] = "https://festhome.com/festival/{$festivalId}";

        // 6. Logo fallback
        if (empty($data['logo'])) {
            $data['logo'] = "https://festhomedocs.com/festivals/{$festivalId}/logo-{$festivalId}.jpg";
        }

        // Clean up
        $data['name'] = html_entity_decode($data['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $data['description'] = html_entity_decode($data['description'] ?? '', ENT_QUOTES, 'UTF-8');

        // Generate slug
        if (!empty($data['name'])) {
            $data['slug'] = self::slugify($data['name']);
        }

        $data['type'] = 'film_festival'; // Default for Festhome
        $data['status'] = 'draft';
        $data['submission_status'] = 'closed';

        // Auto-detect categories and tags from name + description
        $data['auto_categories'] = self::detectCategories($data['name'] ?? '', $data['description'] ?? '');
        $data['auto_tags'] = self::detectTags($data['name'] ?? '', $data['description'] ?? '', $data);

        return $data;
    }

    /**
     * Auto-detect category slugs from festival name + description.
     * Returns array of category slugs that match.
     */
    public static function detectCategories(string $name, string $description): array
    {
        $text = mb_strtolower($name . ' ' . $description, 'UTF-8');
        $found = [];

        // Format detection
        $formatMap = [
            'cortometraje'  => ['short film', 'shortfilm', 'cortometraje', 'corto', 'shorts', 'short-film', 'court métrage', 'curta'],
            'largometraje'  => ['feature film', 'feature-length', 'largometraje', 'largo', 'full-length', 'long métrage', 'longa'],
            'mediometraje'  => ['medium-length', 'mediometraje', 'medio', 'medium length'],
        ];

        // Genre detection
        $genreMap = [
            'ficcion'         => ['fiction', 'ficción', 'narrative', 'narrativo', 'drama'],
            'documental'      => ['documentary', 'documental', 'documentaire', 'documentário', 'non-fiction'],
            'animacion'       => ['animation', 'animación', 'animated', 'animado', 'anime'],
            'experimental'    => ['experimental', 'avant-garde', 'vanguardia', 'art film'],
            'videoclip'       => ['music video', 'videoclip', 'vídeo musical', 'video musical', 'music clip'],
            'terror'          => ['horror', 'terror', 'thriller', 'suspense', 'scary', 'dark', 'fantástico'],
            'comedia'         => ['comedy', 'comedia', 'comédie', 'humor'],
            'ciencia-ficcion' => ['sci-fi', 'science fiction', 'ciencia ficción', 'fantasy', 'fantasía'],
            'lgbtq'           => ['lgbtq', 'lgbt', 'queer', 'gay', 'lesbian', 'trans', 'pride'],
            'medioambiente'   => ['environment', 'ecological', 'green', 'nature', 'medio ambiente', 'ecología', 'climate'],
            'derechos-humanos'=> ['human rights', 'derechos humanos', 'social justice', 'justicia social', 'activism'],
            'infantil'        => ['children', 'kids', 'family', 'infantil', 'niños', 'familiar', 'youth', 'young audience'],
            'estudiantil'     => ['student', 'university', 'school', 'estudiantil', 'universitario', 'escuela'],
            'mujer'           => ['women', 'female', 'mujer', 'femenino', 'woman director', 'directora'],
            'opera-prima'     => ['first film', 'debut', 'ópera prima', 'opera prima', 'first-time'],
            'web-series'      => ['web series', 'webseries', 'serie web', 'digital series', 'online series'],
            'vr-inmersivo'    => ['virtual reality', 'vr', 'immersive', 'xr', '360', 'realidad virtual'],
        ];

        foreach (array_merge($formatMap, $genreMap) as $slug => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($text, $kw) !== false) {
                    $found[] = $slug;
                    break;
                }
            }
        }

        return array_unique($found);
    }

    /**
     * Auto-detect tag slugs from festival data.
     */
    public static function detectTags(string $name, string $description, array $data): array
    {
        $text = mb_strtolower($name . ' ' . $description, 'UTF-8');
        $found = [];

        // Scope
        if (preg_match('/\binternational\b|\binternacional\b/i', $text)) $found[] = 'internacional';
        elseif (preg_match('/\bnational\b|\bnacional\b/i', $text)) $found[] = 'nacional';

        // Format
        if (preg_match('/\bonline\b|\bvirtual\b|\bdigital\b/i', $text)) {
            $found[] = 'online';
            $found[] = 'virtual';
        }
        if (preg_match('/\bhybrid\b|\bhíbrido\b/i', $text)) $found[] = 'hibrido';

        // Competition
        if (preg_match('/\bpriz(e|es)\b|\bpremio\b|\baward\b|\bgrant\b/i', $text)) $found[] = 'competitivo';
        if (preg_match('/\€|\$|EUR|USD|cash|metálico|dinero/i', $text)) $found[] = 'con-premios-en-metalico';

        // Fee
        if (preg_match('/\bfree\b|\bgratis\b|\bno fee\b|\bsin coste\b|waiver/i', $text)) $found[] = 'inscripcion-gratuita';

        // Accreditation
        if (preg_match('/\boscar\b|\bacademy award/i', $text)) $found[] = 'qualifying-oscar';
        if (preg_match('/\bbafta\b/i', $text)) $found[] = 'qualifying-bafta';
        if (preg_match('/\bgoya\b/i', $text)) $found[] = 'qualifying-goya';

        // Platforms
        if (!empty($data['submission_festhome_url'])) $found[] = 'acepta-festhome';
        if (!empty($data['submission_filmfreeway_url'])) $found[] = 'acepta-filmfreeway';

        // Region from country
        $country = mb_strtolower($data['country'] ?? '', 'UTF-8');
        $europeCountries = ['spain', 'españa', 'france', 'francia', 'italy', 'italia', 'germany', 'alemania', 'uk', 'united kingdom', 'portugal', 'netherlands', 'belgium', 'austria', 'switzerland', 'poland', 'czech', 'sweden', 'norway', 'denmark', 'finland', 'greece', 'ireland', 'croatia', 'romania', 'hungary'];
        $latamCountries = ['mexico', 'méxico', 'argentina', 'brazil', 'brasil', 'chile', 'colombia', 'peru', 'perú', 'uruguay', 'venezuela', 'ecuador', 'bolivia', 'paraguay', 'cuba', 'costa rica', 'guatemala', 'honduras', 'el salvador', 'nicaragua', 'panama', 'panamá', 'dominican', 'puerto rico'];
        $asiaCountries = ['japan', 'japón', 'china', 'korea', 'corea', 'india', 'thailand', 'tailandia', 'indonesia', 'philippines', 'filipinas', 'vietnam', 'taiwan', 'hong kong', 'singapore', 'singapur', 'malaysia', 'malasia'];

        foreach ($europeCountries as $c) { if (strpos($country, $c) !== false) { $found[] = 'europa'; break; } }
        foreach ($latamCountries as $c) { if (strpos($country, $c) !== false) { $found[] = 'latinoamerica'; break; } }
        foreach ($asiaCountries as $c) { if (strpos($country, $c) !== false) { $found[] = 'asia'; break; } }
        if (in_array($country, ['united states', 'usa', 'estados unidos', 'canada', 'canadá'])) $found[] = 'norteamerica';

        // Industry events
        if (preg_match('/\bmarket\b|\bmercado\b|\bindustry\b|\bindustria\b/i', $text)) $found[] = 'mercado-audiovisual';
        if (preg_match('/\bpitch\b|\bpitching\b/i', $text)) $found[] = 'pitching';
        if (preg_match('/\bnetwork\b|\bnetworking\b/i', $text)) $found[] = 'networking';
        if (preg_match('/\bmasterclass\b|\bworkshop\b|\btaller\b/i', $text)) $found[] = 'masterclass';

        return array_unique($found);
    }

    // ─── ESCRITORES.ORG ────────────────────────

    private const ESCRITORES_RSS = 'https://www.escritores.org/recursos/escritores.xml';

    /**
     * Fetch literary contests from escritores.org RSS feed.
     * Returns all items at once (RSS has no pagination), sliced by page.
     *
     * @param int $page Page number (we slice 20 items per page from the full feed)
     */
    public static function escritoresSearch(int $page = 1): array
    {
        static $cachedItems = null;

        // Fetch and parse RSS once, then paginate in memory
        if ($cachedItems === null) {
            $xml = self::fetch(self::ESCRITORES_RSS);
            if (!$xml) {
                return ['festivals' => [], 'hasMore' => false, 'error' => 'No se pudo conectar con escritores.org'];
            }

            // Suppress XML errors
            libxml_use_internal_errors(true);
            $rss = simplexml_load_string($xml);
            libxml_clear_errors();

            if (!$rss || !isset($rss->channel)) {
                return ['festivals' => [], 'hasMore' => false, 'error' => 'RSS inválido.'];
            }

            $cachedItems = [];
            foreach ($rss->channel->item as $item) {
                $parsed = self::parseEscritoresItem($item);
                if ($parsed) {
                    $cachedItems[] = $parsed;
                }
            }
        }

        // Paginate: 20 per page
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($cachedItems, $offset, $perPage);

        return [
            'festivals' => $slice,
            'hasMore'   => ($offset + $perPage) < count($cachedItems),
            'page'      => $page,
            'total'     => count($cachedItems),
        ];
    }

    /**
     * Parse a single RSS item from escritores.org.
     * Title format: "NOMBRE DEL CONCURSO (País)"
     * Description format: "BASES - (DD:MM:YYYY / Género / Premio / Abierto a: elegibilidad)"
     */
    private static function parseEscritoresItem(\SimpleXMLElement $item): ?array
    {
        $rawTitle = trim((string)$item->title);
        $link = trim((string)$item->link);
        $rawDesc = trim((string)$item->description);

        if (empty($rawTitle)) return null;

        // Extract country from title: "NOMBRE (España)"
        $name = $rawTitle;
        $country = '';
        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $rawTitle, $m)) {
            $name = trim($m[1]);
            $country = trim($m[2]);
        }

        // Parse description: "BASES - (DD:MM:YYYY / Género / Premio / Abierto a: ...)"
        $deadline = null;
        $genre = '';
        $prize = '';
        $eligibility = '';
        $description = $rawDesc;

        if (preg_match('/\((?:Fallo:\s*)?(\d{2}):(\d{2}):(\d{4})\s*\/\s*([^\/]+)\/\s*([^\/]+?)(?:\s*\/\s*Abierto a:\s*(.+?))?\)/', $rawDesc, $dm)) {
            $deadline = $dm[3] . '-' . $dm[2] . '-' . $dm[1]; // YYYY-MM-DD
            $genre = trim($dm[4]);
            $prize = trim($dm[5]);
            $eligibility = trim($dm[6] ?? '');

            // Build a clean description
            $description = '';
            if ($genre) $description .= "Género: {$genre}. ";
            if ($prize) $description .= "Premio: {$prize}. ";
            if ($eligibility) $description .= "Abierto a: {$eligibility}.";
        }

        // Generate a unique ID from the link (hash)
        $sourceId = crc32($link);

        // Detect type from title/genre
        $type = 'certamen'; // default for literary
        $lowerName = mb_strtolower($name, 'UTF-8');
        if (strpos($lowerName, 'premio') !== false) $type = 'award';
        elseif (strpos($lowerName, 'concurso') !== false) $type = 'contest';

        $slug = self::slugify($name);

        return [
            'source'       => 'escritores',
            'source_id'    => abs($sourceId),
            'name'         => $name,
            'date_text'    => $deadline ? date('d/m/Y', strtotime($deadline)) : '',
            'logo'         => '',
            'detail_url'   => $link,
            'festhome_url' => '', // not applicable
            // Detail fields (already available from RSS, no need for separate detail call)
            'country'           => $country,
            'city'              => '',
            'deadline_date'     => $deadline,
            'description'       => $description,
            'genre'             => $genre,
            'prize'             => $prize,
            'eligibility'       => $eligibility,
            'website_url'       => $link,
            'type'              => $type,
            'slug'              => $slug,
            'status'            => 'draft',
            'submission_status' => $deadline && strtotime($deadline) > time() ? 'open' : 'closed',
            'submission_other_url' => $link,
            'auto_categories'   => self::detectLiteraryCategories($genre, $name),
            'auto_tags'         => self::detectLiteraryTags($name, $genre, $prize, $eligibility, $country),
        ];
    }

    /**
     * Get detail for an escritores.org contest.
     * Since all data comes from the RSS, we just find the matching item.
     */
    public static function escritoresDetail(int $sourceId): ?array
    {
        $result = self::escritoresSearch(1); // loads full feed
        // Search across all cached items
        $xml = self::fetch(self::ESCRITORES_RSS);
        if (!$xml) return null;

        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($xml);
        libxml_clear_errors();

        if (!$rss) return null;

        foreach ($rss->channel->item as $item) {
            $link = trim((string)$item->link);
            $id = abs(crc32($link));
            if ($id === $sourceId) {
                $parsed = self::parseEscritoresItem($item);
                if ($parsed) {
                    // For escritores, the RSS already has all the data
                    $parsed['source_id'] = $sourceId;
                    return $parsed;
                }
            }
        }

        return null;
    }

    /**
     * Detect categories for literary contests.
     */
    private static function detectLiteraryCategories(string $genre, string $name): array
    {
        $text = mb_strtolower($genre . ' ' . $name, 'UTF-8');
        $found = [];

        $map = [
            'narrativa'           => ['narrativa', 'novela', 'relato', 'cuento', 'narración', 'ficción', 'prosa', 'narración corta'],
            'poesia'              => ['poesía', 'poesia', 'verso', 'haiku'],
            'ensayo'              => ['ensayo', 'no ficción', 'crónica', 'investigación', 'divulgación'],
            'guion'               => ['guion', 'guión', 'screenplay', 'largometraje'],
            'teatro'              => ['teatro', 'dramaturgia', 'monólogo', 'obra teatral'],
            'literatura-infantil' => ['infantil', 'juvenil', 'álbum ilustrado'],
            'microrrelato'        => ['microrrelato', 'flash fiction', 'relato corto', 'microcuento'],
        ];

        foreach ($map as $slug => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($text, $kw) !== false) {
                    $found[] = $slug;
                    break;
                }
            }
        }

        return array_unique($found);
    }

    /**
     * Detect tags for literary contests.
     */
    private static function detectLiteraryTags(string $name, string $genre, string $prize, string $eligibility, string $country): array
    {
        $found = [];
        $text = mb_strtolower($name . ' ' . $genre . ' ' . $eligibility, 'UTF-8');

        // Scope
        if (preg_match('/internacional/i', $text)) $found[] = 'internacional';
        elseif (preg_match('/nacional/i', $text)) $found[] = 'nacional';

        // Fee
        $found[] = 'inscripcion-gratuita'; // escritores.org contests are typically free

        // Competition
        $found[] = 'competitivo';
        if (!empty($prize) && preg_match('/\d/', $prize)) {
            $found[] = 'con-premios-en-metalico';
        }

        // Region
        if (mb_strtolower($country) === 'españa' || mb_strtolower($country) === 'spain') $found[] = 'europa';
        $latam = ['méxico', 'argentina', 'colombia', 'chile', 'perú', 'cuba', 'uruguay', 'venezuela', 'ecuador', 'bolivia'];
        foreach ($latam as $c) {
            if (mb_strpos(mb_strtolower($country), $c) !== false) { $found[] = 'latinoamerica'; break; }
        }

        // Eligibility
        if (preg_match('/sin restricciones/i', $eligibility)) $found[] = 'internacional';
        if (preg_match('/residentes en España/i', $eligibility)) $found[] = 'nacional';

        return array_unique($found);
    }

    // ─── Duplicate Detection ────────────────────

    /**
     * Find a potential duplicate festival in the database.
     * Uses normalized name comparison (fuzzy matching).
     *
     * @return array|null ['id' => int, 'name' => string, 'match_type' => 'exact'|'fuzzy'] or null
     */
    public static function findDuplicate(int $tenantId, string $name): ?array
    {
        $pdo = \Screenart\Musedock\Database::connect();
        $slug = self::slugify($name);
        $normalizedName = self::normalizeName($name);

        // 1. Exact slug match
        $stmt = $pdo->prepare("SELECT id, name FROM festivals WHERE tenant_id = ? AND slug = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return ['id' => (int)$row['id'], 'name' => $row['name'], 'match_type' => 'exact'];
        }

        // 2. Fuzzy: normalized name match (removes "festival", "international", editions, etc.)
        $stmt = $pdo->prepare("SELECT id, name, slug FROM festivals WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$tenantId]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $existingNorm = self::normalizeName($row['name']);
            // Check similarity
            $similarity = 0;
            similar_text($normalizedName, $existingNorm, $similarity);
            if ($similarity >= 85) {
                return ['id' => (int)$row['id'], 'name' => $row['name'], 'match_type' => 'fuzzy', 'similarity' => round($similarity)];
            }
        }

        return null;
    }

    /**
     * Normalize festival name for comparison.
     * Removes common words, editions, numbers.
     */
    private static function normalizeName(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        // Remove edition numbers: "(24)", "24th", "XXIV", etc.
        $name = preg_replace('/\(\d+\)/', '', $name);
        $name = preg_replace('/\b\d+(st|nd|rd|th|ª|º)?\b/i', '', $name);
        // Remove common noise words
        $noise = ['international', 'internacional', 'festival', 'de', 'del', 'the', 'of', 'and', 'y', 'film', 'cine', 'edition', 'edición'];
        $name = preg_replace('/\b(' . implode('|', $noise) . ')\b/i', '', $name);
        // Transliterate and clean
        $name = self::slugify($name);
        return $name;
    }

    // ─── Proxy Support ───────────────────────────

    /** @var string|null Proxy URL (e.g., "socks5://user:pass@host:port" or "http://host:port") */
    private static ?string $proxy = null;

    /**
     * Set proxy for all scraper requests.
     * Supports HTTP, HTTPS, SOCKS5 proxies.
     *
     * @param string|null $proxy Proxy URL or null to disable
     */
    public static function setProxy(?string $proxy): void
    {
        self::$proxy = $proxy;
    }

    /**
     * Get current proxy setting from tenant plugin settings or env.
     */
    public static function getConfiguredProxy(): ?string
    {
        // Check env first
        $proxy = \Screenart\Musedock\Env::get('SCRAPER_PROXY');
        if ($proxy) return $proxy;

        // Check tenant plugin settings
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        if ($tenantId) {
            try {
                $pdo = \Screenart\Musedock\Database::connect();
                $stmt = $pdo->prepare("SELECT settings FROM tenant_plugins WHERE tenant_id = ? AND slug = 'festival-directory'");
                $stmt->execute([$tenantId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['settings']) {
                    $settings = json_decode($row['settings'], true);
                    return $settings['scraper_proxy'] ?? null;
                }
            } catch (\Exception $e) {}
        }

        return null;
    }

    // ─── HTTP Fetch ──────────────────────────────

    private static function fetch(string $url): ?string
    {
        // Auto-load proxy from config if not set
        if (self::$proxy === null) {
            $configured = self::getConfiguredProxy();
            if ($configured) {
                self::$proxy = $configured;
            }
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9',
                'Accept-Language: en-US,en;q=0.9,es;q=0.8',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '', // Accept gzip/brotli
        ];

        // Apply proxy if configured
        if (!empty(self::$proxy)) {
            $opts[CURLOPT_PROXY] = self::$proxy;
            if (str_starts_with(self::$proxy, 'socks5')) {
                $opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
            }
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            error_log("FestivalScraper: HTTP {$httpCode} for {$url}" . ($error ? " — {$error}" : ''));
            return null;
        }

        return $response;
    }

    // ─── String Utilities ────────────────────────

    private static function parseDate(string $text): ?string
    {
        $ts = strtotime($text);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
                'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','â'=>'a','ê'=>'e',
                'î'=>'i','ô'=>'o','û'=>'u','ä'=>'a','ö'=>'o','ç'=>'c','ß'=>'ss'];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
