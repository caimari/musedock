@extends('Customer.layout')

@section('styles')
<style>
    .management-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 20px;
    }
    .management-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px 15px 0 0 !important;
        padding: 20px 25px;
    }
    .management-card .card-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .management-card .card-body {
        padding: 25px;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        font-weight: 600;
        color: #4b5563;
        font-size: 0.95rem;
    }
    .info-value {
        color: #1f2937;
        font-size: 0.95rem;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .status-active {
        background: #d1fae5;
        color: #065f46;
    }
    .status-locked {
        background: #fef3c7;
        color: #92400e;
    }
    .status-unlocked {
        background: #dbeafe;
        color: #1e40af;
    }
    .quick-action-btn {
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s ease;
        margin-bottom: 10px;
    }
    .quick-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .auth-code-display {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        font-family: monospace;
        font-size: 1.1rem;
        font-weight: 600;
        color: #495057;
        margin-top: 10px;
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .3s;
        border-radius: 26px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    input:checked + .toggle-slider {
        background-color: #10b981;
    }
    input:checked + .toggle-slider:before {
        transform: translateX(24px);
    }
    .renewal-select {
        min-width: 150px;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #d1d5db;
        font-size: 0.9rem;
    }
</style>
@endsection

@section('content')
<?php
    $domain = $order['full_domain'] ?? trim(($order['domain'] ?? '') . (!empty($order['extension']) ? '.' . $order['extension'] : ''), '.');
    $opId = $order['openprovider_domain_id'] ?? null;

    // Domain info from OpenProvider API
    $status = $domainInfo['status'] ?? 'unknown';
    $isLocked = $domainInfo['is_locked'] ?? false;
    $autorenew = $domainInfo['autorenew'] ?? 'default';
    $isPrivateWhois = $domainInfo['is_private_whois_enabled'] ?? false;
    $expirationDate = $domainInfo['expiration_date'] ?? null;
    $renewalDate = $domainInfo['renewal_date'] ?? null;

    // Format dates
    $expirationFormatted = $expirationDate ? date('d/m/Y', strtotime($expirationDate)) : 'N/A';
    $renewalFormatted = $renewalDate ? date('d/m/Y', strtotime($renewalDate)) : 'N/A';

    // Calculate days until expiration
    $daysUntilExpiration = null;
    if ($expirationDate) {
        $now = new DateTime();
        $expiry = new DateTime($expirationDate);
        $interval = $now->diff($expiry);
        $daysUntilExpiration = $interval->days;
        if ($expiry < $now) {
            $daysUntilExpiration = -$daysUntilExpiration;
        }
    }

    // Status labels
    $statusLabels = [
        'ACT' => 'Activo',
        'active' => 'Activo',
        'FAI' => 'Error',
        'PEN' => 'Pendiente'
    ];
    $statusLabel = $statusLabels[$status] ?? ucfirst($status);
?>

<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-gear-fill me-2"></i>Administrar Dominio</h4>
                <p class="text-muted mb-0">Configuración avanzada de <strong><?= htmlspecialchars($domain) ?></strong></p>
            </div>
            <a href="/customer/dashboard" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        <div class="row">
            <!-- Left Column: Domain Overview -->
            <div class="col-lg-6">
                <!-- Domain Information -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-info-circle me-2"></i>Información del Dominio</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Estado</span>
                            <span class="info-value">
                                <span class="status-badge status-active"><?= htmlspecialchars($statusLabel) ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Fecha de Expiración</span>
                            <span class="info-value">
                                <?= htmlspecialchars($expirationFormatted) ?>
                                <?php if ($daysUntilExpiration !== null): ?>
                                    <small class="text-muted ms-2">
                                        (<?= $daysUntilExpiration > 0 ? "faltan {$daysUntilExpiration} días" : "expiró hace " . abs($daysUntilExpiration) . " días" ?>)
                                    </small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Próxima Renovación</span>
                            <span class="info-value"><?= htmlspecialchars($renewalFormatted) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Bloqueo de Transferencia</span>
                            <span class="info-value">
                                <span class="status-badge <?= $isLocked ? 'status-locked' : 'status-unlocked' ?>">
                                    <?= $isLocked ? '🔒 Bloqueado' : '🔓 Desbloqueado' ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-shield-lock me-2"></i>Seguridad</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div>
                                <span class="info-label d-block">Bloqueo de Transferencia</span>
                                <small class="text-muted">Protege contra transferencias no autorizadas</small>
                            </div>
                            <div>
                                <button class="btn btn-sm <?= $isLocked ? 'btn-danger' : 'btn-success' ?>"
                                        onclick="toggleLock('<?= $isLocked ? 'unlock' : 'lock' ?>')">
                                    <i class="bi bi-<?= $isLocked ? 'unlock' : 'lock' ?> me-1"></i>
                                    <?= $isLocked ? 'Desbloquear' : 'Bloquear' ?>
                                </button>
                            </div>
                        </div>
                        <?php
                            // TLDs que soportan WHOIS Privacy Protection (WPP) según OpenProvider
                            // Fuente: OpenProvider TLD Reference Sheet (columna AJ) - Actualizado: Dic 2025
                            // Total: 537 TLDs con WPP configurable
                            $tldsSupportingWpp = [
                                'ac', 'ac.mu', 'academy', 'accountant', 'accountants', 'actor', 'adult', 'africa', 'agency',
                                'ai', 'alsace', 'amsterdam', 'apartments', 'app', 'archi', 'army', 'art', 'associates', 'audio',
                                'auto', 'autos', 'band', 'bar', 'bargains', 'basketball', 'bayern', 'beauty', 'beer', 'berlin',
                                'best', 'bet', 'bible', 'bid', 'bike', 'bingo', 'bio', 'biz', 'black', 'blackfriday', 'blog',
                                'blue', 'boats', 'bond', 'boo', 'boston', 'boutique', 'brussels', 'build', 'builders', 'business',
                                'buzz', 'bzh', 'cab', 'cafe', 'camera', 'camp', 'capetown', 'capital', 'car', 'cards', 'care',
                                'career', 'careers', 'cars', 'casa', 'case', 'cash', 'casino', 'catering', 'cc', 'center', 'ceo',
                                'cfd', 'channel', 'charity', 'chat', 'cheap', 'christmas', 'church', 'city', 'claims', 'cleaning',
                                'click', 'clinic', 'clothing', 'club', 'co', 'co.mu', 'co.uk', 'coach', 'codes', 'coffee', 'college',
                                'cologne', 'com', 'com.co', 'com.mu', 'community', 'company', 'compare', 'computer', 'condos',
                                'construction', 'consulting', 'contact', 'contractors', 'cooking', 'cool', 'corsica', 'country',
                                'coupons', 'courses', 'credit', 'creditcard', 'cricket', 'cruises', 'cx', 'cymru', 'cyou', 'dad',
                                'dance', 'date', 'dating', 'day', 'de', 'dealer', 'deals', 'delivery', 'democrat', 'dental', 'dentist',
                                'design', 'diamonds', 'diet', 'digital', 'direct', 'directory', 'discount', 'diy', 'doctor', 'dog',
                                'domains', 'download', 'durban', 'earth', 'eco', 'education', 'email', 'energy', 'engineer',
                                'engineering', 'enterprises', 'equipment', 'esq', 'estate', 'events', 'exchange', 'expert', 'exposed',
                                'express', 'fail', 'faith', 'family', 'fan', 'fans', 'farm', 'fashion', 'feedback', 'film', 'finance',
                                'financial', 'fish', 'fishing', 'fit', 'fitness', 'flights', 'florist', 'flowers', 'fm', 'foo', 'food',
                                'football', 'forum', 'foundation', 'fr', 'frl', 'fun', 'fund', 'furniture', 'futbol', 'fyi', 'gallery',
                                'game', 'games', 'garden', 'gay', 'gdn', 'gent', 'gift', 'gifts', 'giving', 'glass', 'global', 'gmbh',
                                'gold', 'golf', 'graphics', 'gratis', 'green', 'gripe', 'group', 'guide', 'guitars', 'guru', 'hair',
                                'hamburg', 'haus', 'health', 'healthcare', 'help', 'hiphop', 'hiv', 'hockey', 'holdings', 'holiday',
                                'homes', 'horse', 'hospital', 'host', 'hosting', 'house', 'how', 'icu', 'immo', 'immobilien', 'inc',
                                'industries', 'info', 'ing', 'ink', 'institute', 'insure', 'international', 'investments', 'io',
                                'irish', 'ist', 'istanbul', 'jetzt', 'jewelry', 'joburg', 'juegos', 'kaufen', 'kids', 'kim', 'kitchen',
                                'kiwi', 'koeln', 'krd', 'kyoto', 'land', 'lat', 'lease', 'legal', 'lgbt', 'life', 'lifestyle',
                                'lighting', 'limited', 'limo', 'link', 'live', 'living', 'loan', 'loans', 'lol', 'london', 'love',
                                'ltd', 'ltda', 'luxe', 'luxury', 'maison', 'makeup', 'management', 'market', 'marketing', 'markets',
                                'mba', 'me', 'media', 'melbourne', 'meme', 'memorial', 'men', 'menu', 'miami', 'mn', 'moda', 'moe',
                                'mom', 'money', 'monster', 'moscow', 'motorcycles', 'mov', 'movie', 'mu', 'music', 'nagoya', 'net',
                                'net.co', 'net.mu', 'network', 'new', 'news', 'nexus', 'ngo', 'ninja', 'nom.co', 'nrw', 'observer',
                                'okinawa', 'one', 'ong', 'onl', 'online', 'ooo', 'or.mu', 'org', 'org.mu', 'organic', 'osaka', 'page',
                                'paris', 'partners', 'parts', 'party', 'pet', 'phd', 'photo', 'photography', 'photos', 'physio',
                                'pics', 'pictures', 'pink', 'pizza', 'place', 'plumbing', 'plus', 'pm', 'poker', 'porn', 'press',
                                'productions', 'prof', 'promo', 'properties', 'property', 'protection', 'pub', 'pw', 'qpon', 'quebec',
                                'quest', 'racing', 're', 'realestate', 'realty', 'recipes', 'red', 'reise', 'reisen', 'ren', 'rent',
                                'rentals', 'repair', 'report', 'republican', 'rest', 'restaurant', 'review', 'reviews', 'rich', 'rip',
                                'rocks', 'rsvp', 'ruhr', 'run', 'ryukyu', 'sale', 'salon', 'sarl', 'sbs', 'school', 'schule',
                                'science', 'security', 'select', 'services', 'sex', 'sexy', 'sh', 'shiksha', 'shoes', 'shop',
                                'shopping', 'show', 'singles', 'site', 'skin', 'soccer', 'social', 'software', 'solar', 'solutions',
                                'soy', 'spa', 'space', 'srl', 'storage', 'store', 'stream', 'studio', 'study', 'style', 'su', 'sucks',
                                'supplies', 'supply', 'support', 'surf', 'surgery', 'sydney', 'systems', 'taipei', 'tatar', 'tattoo',
                                'tax', 'taxi', 'team', 'tech', 'technology', 'tennis', 'tf', 'theater', 'theatre', 'tickets', 'tienda',
                                'tips', 'tires', 'tirol', 'today', 'tokyo', 'tools', 'top', 'tours', 'town', 'toys', 'trade',
                                'training', 'tv', 'uk', 'university', 'uno', 'vacations', 'vana', 'vegas', 'ventures', 'viajes',
                                'video', 'villas', 'vin', 'vip', 'vision', 'vlaanderen', 'vodka', 'voting', 'voyage', 'vuelos',
                                'wales', 'wang', 'watch', 'watches', 'webcam', 'website', 'wedding', 'wf', 'whoswho', 'wien', 'wiki',
                                'win', 'wine', 'work', 'works', 'world', 'wtf', 'xyz', 'yachts', 'yoga', 'yokohama', 'yt', 'zip', 'zone'
                            ];

                            // Países de la Unión Europea (28 países)
                            $euCountries = [
                                'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
                                'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
                                'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'EU'
                            ];

                            $extension = $order['extension'] ?? '';
                            $supportsWpp = in_array(strtolower($extension), $tldsSupportingWpp);

                            // Obtener país del owner desde domainInfo
                            $ownerCountry = strtoupper($domainInfo['owner']['country_code'] ?? '');
                            $isEuOwner = in_array($ownerCountry, $euCountries);
                        ?>

                        <div class="info-row">
                            <div class="flex-grow-1">
                                <span class="info-label d-block">Protección WHOIS</span>
                                <?php if ($supportsWpp): ?>
                                    <small class="text-muted">
                                        <?= $isPrivateWhois ? 'Activado' : 'Desactivado' ?> - Oculta tus datos de contacto en el WHOIS público
                                    </small>
                                    <?php if ($isEuOwner): ?>
                                        <small class="text-muted d-block">
                                            <i class="bi bi-info-circle"></i>
                                            Titular de la UE: GDPR también protege tus datos automáticamente
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($isEuOwner): ?>
                                        <small class="text-muted">
                                            <i class="bi bi-shield-check text-success"></i>
                                            Protegido por <strong>GDPR</strong> (titular de la UE)
                                        </small>
                                        <small class="text-muted d-block">
                                            El TLD <strong>.<?= htmlspecialchars($extension) ?></strong> no soporta WHOIS Privacy configurable,
                                            pero tus datos aparecen como "REDACTED FOR PRIVACY" por regulación europea.
                                        </small>
                                    <?php else: ?>
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <strong>Sin protección WHOIS</strong>
                                        </small>
                                        <small class="text-muted d-block">
                                            El TLD <strong>.<?= htmlspecialchars($extension) ?></strong> no soporta WHOIS Privacy.
                                            Tus datos de contacto son públicos en el WHOIS.
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($supportsWpp): ?>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="whoisPrivacyToggle"
                                               <?= $isPrivateWhois ? 'checked' : '' ?>
                                               onchange="toggleWhoisPrivacy(this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                <?php else: ?>
                                    <?php if ($isEuOwner): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-shield-fill-check"></i> GDPR Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-eye"></i> Público
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Settings -->
            <div class="col-lg-6">
                <!-- Quick Actions -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-lightning-charge me-2"></i>Acciones Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-outline-primary quick-action-btn w-100" onclick="getAuthCode()">
                            <i class="bi bi-key me-2"></i>Ver Código de Autorización (Auth Code)
                        </button>
                        <button class="btn btn-outline-warning quick-action-btn w-100" onclick="regenerateAuthCode()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Regenerar Código de Autorización
                        </button>
                        <div id="authCodeDisplay" style="display: none;" class="mt-3">
                            <div class="auth-code-display" id="authCodeValue"></div>
                            <small class="text-muted d-block mt-2 text-center">
                                Usa este código para transferir tu dominio a otro registrador
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Renewal Settings -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-arrow-repeat me-2"></i>Renovación</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="flex-grow-1">
                                <span class="info-label d-block">Auto-Renovación</span>
                                <small class="text-muted d-block mb-1">Renovación automática del dominio al vencimiento</small>
                                <small class="text-warning d-block">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Temporalmente deshabilitado hasta implementar sistema de pagos de clientes
                                </small>
                            </div>
                            <div>
                                <select class="renewal-select" id="autorenewSelect" disabled title="Disponible cuando se implemente el sistema de pagos">
                                    <option value="off" selected>Desactivada</option>
                                    <option value="on">Activada</option>
                                    <option value="default">Por defecto</option>
                                </select>
                            </div>
                        </div>
                        <?php if ($daysUntilExpiration !== null && $daysUntilExpiration > 0 && $daysUntilExpiration <= 30): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Atención:</strong> Tu dominio expira en <?= $daysUntilExpiration ?> días
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Service Type Management -->
                <?php
                    $hostingType = $order['hosting_type'] ?? 'musedock_hosting';
                    $useCloudflareNs = $order['use_cloudflare_ns'] ?? true;
                    $cloudflareZoneId = $order['cloudflare_zone_id'] ?? null;
                    $tenantDomain = $order['tenant_domain'] ?? null;
                ?>
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-hdd-stack me-2"></i>Tipo de Servicio</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="flex-grow-1">
                                <span class="info-label d-block">Servicio Actual</span>
                                <small class="text-muted">
                                    <?php if ($hostingType === 'musedock_hosting'): ?>
                                        <i class="bi bi-hdd-stack text-primary"></i> DNS + Hosting MuseDock CMS
                                    <?php else: ?>
                                        <i class="bi bi-globe text-secondary"></i> Solo Gestión DNS
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($hostingType === 'musedock_hosting'): ?>
                                    <span class="badge bg-primary">
                                        <i class="bi bi-check-circle"></i> CMS Activo
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-dash-circle"></i> DNS Solo
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($hostingType === 'dns_only'): ?>
                        <!-- Upgrade to CMS/Hosting -->
                        <div class="alert alert-info mt-3 mb-0">
                            <h6 class="mb-2"><i class="bi bi-info-circle me-1"></i>Activar MuseDock CMS</h6>
                            <p class="mb-2 small">Puedes activar el hosting y CMS de MuseDock para este dominio. Esto incluye:</p>
                            <ul class="small mb-2">
                                <li>Tenant dedicado con panel de administración</li>
                                <li>CMS completo con gestor de contenidos</li>
                                <li>Certificado SSL automático</li>
                                <li>CDN y protección DDoS con Cloudflare</li>
                            </ul>
                            <p class="small text-warning mb-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Requisito:</strong> El dominio debe usar nameservers de Cloudflare
                            </p>
                            <button class="btn btn-sm btn-primary w-100" onclick="upgradeToCMS()" <?= !$useCloudflareNs ? 'disabled title="Primero debes usar nameservers de Cloudflare"' : '' ?>>
                                <i class="bi bi-arrow-up-circle me-1"></i>Activar CMS + Hosting
                            </button>
                        </div>
                        <?php else: ?>
                        <!-- Downgrade to DNS Only -->
                        <div class="alert alert-warning mt-3 mb-0">
                            <h6 class="mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Desactivar CMS</h6>
                            <p class="mb-2 small">Si desactivas el CMS, se eliminará:</p>
                            <ul class="small mb-2">
                                <li>El tenant y todos sus datos</li>
                                <li>Los registros DNS @ y www → mortadelo.musedock.com</li>
                                <li>El acceso al panel de administración</li>
                            </ul>
                            <p class="small text-muted mb-2">
                                <i class="bi bi-info-circle me-1"></i>
                                La zona de Cloudflare se mantendrá para gestión DNS
                            </p>
                            <button class="btn btn-sm btn-outline-danger w-100" onclick="downgradeToDNS()">
                                <i class="bi bi-arrow-down-circle me-1"></i>Desactivar CMS (Solo DNS)
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="card management-card">
                    <div class="card-header">
                        <h5><i class="bi bi-compass me-2"></i>Navegación</h5>
                    </div>
                    <div class="card-body">
                        <a href="/customer/domain/<?= $order['id'] ?>/manage" class="btn btn-success quick-action-btn w-100">
                            <i class="bi bi-gear-fill me-2"></i>Administrar Dominio
                        </a>
                        <a href="/customer/domain/<?= $order['id'] ?>/dns" class="btn btn-outline-primary quick-action-btn w-100">
                            <i class="bi bi-hdd-network me-2"></i>Gestionar DNS
                        </a>
                        <a href="/customer/domain/<?= $order['id'] ?>/contacts" class="btn btn-outline-secondary quick-action-btn w-100">
                            <i class="bi bi-person-lines-fill me-2"></i>Administrar Contactos
                        </a>
                        <?php if ($hostingType === 'musedock_hosting' && !empty($tenantDomain)): ?>
                        <a href="https://<?= htmlspecialchars($tenantDomain) ?>/<?= \Screenart\Musedock\Env::get('ADMIN_PATH_TENANT', 'admin') ?>"
                           class="btn btn-primary quick-action-btn w-100" target="_blank">
                            <i class="bi bi-box-arrow-up-right me-2"></i>Ir al Panel CMS
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const orderId = <?= $order['id'] ?>;
const csrfToken = '<?= $csrf_token ?>';

function toggleLock(action) {
    const actionText = action === 'lock' ? 'bloquear' : 'desbloquear';

    Swal.fire({
        title: `¿${actionText.charAt(0).toUpperCase() + actionText.slice(1)} dominio?`,
        text: action === 'lock'
            ? 'El dominio estará protegido contra transferencias no autorizadas'
            : 'El dominio podrá ser transferido a otro registrador',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: action === 'lock' ? '#10b981' : '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Sí, ${actionText}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);
            formData.append('action', action);

            Swal.fire({
                title: 'Procesando...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/customer/domain/${orderId}/toggle-lock`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'No se pudo cambiar el estado del bloqueo', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

function getAuthCode() {
    Swal.fire({
        title: 'Obteniendo código...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/auth-code`)
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                document.getElementById('authCodeValue').textContent = data.auth_code;
                document.getElementById('authCodeDisplay').style.display = 'block';

                // Scroll to auth code
                document.getElementById('authCodeDisplay').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                Swal.fire('Error', data.error || 'No se pudo obtener el auth code', 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
}

function regenerateAuthCode() {
    Swal.fire({
        title: '¿Regenerar Auth Code?',
        text: 'Se generará un nuevo código de autorización. El anterior dejará de funcionar.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, regenerar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            Swal.fire({
                title: 'Generando nuevo código...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/customer/domain/${orderId}/regenerate-auth-code`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('authCodeValue').textContent = data.auth_code;
                    document.getElementById('authCodeDisplay').style.display = 'block';

                    Swal.fire({
                        icon: 'success',
                        title: data.message,
                        text: 'Nuevo código: ' + data.auth_code,
                        confirmButtonText: 'Entendido'
                    });

                    // Scroll to auth code
                    document.getElementById('authCodeDisplay').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    Swal.fire('Error', data.error || 'No se pudo regenerar el auth code', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

function updateAutoRenew(value) {
    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);
    formData.append('autorenew', value);

    Swal.fire({
        title: 'Actualizando...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/toggle-autorenew`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: data.message,
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo actualizar la auto-renovación', 'error');
            // Revert select
            document.getElementById('autorenewSelect').value = '<?= $autorenew ?>';
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Error de conexión', 'error');
        document.getElementById('autorenewSelect').value = '<?= $autorenew ?>';
    });
}

function toggleWhoisPrivacy(enabled) {
    // Verificar si el TLD soporta WPP
    const supportsWpp = <?= $supportsWpp ? 'true' : 'false' ?>;
    const isEuOwner = <?= $isEuOwner ? 'true' : 'false' ?>;

    if (!supportsWpp) {
        <?php if ($isEuOwner): ?>
        Swal.fire({
            icon: 'info',
            title: 'WHOIS Privacy no disponible',
            html: 'El TLD <strong>.<?= htmlspecialchars($extension) ?></strong> no soporta WHOIS Privacy configurable.<br><br>' +
                  'Sin embargo, como titular de la Unión Europea, tus datos están protegidos automáticamente por <strong>GDPR</strong> ' +
                  'y aparecen como "REDACTED FOR PRIVACY" en el WHOIS público.',
            confirmButtonText: 'Entendido'
        });
        <?php else: ?>
        Swal.fire({
            icon: 'warning',
            title: 'WHOIS Privacy no disponible',
            html: 'El TLD <strong>.<?= htmlspecialchars($extension) ?></strong> no soporta WHOIS Privacy configurable.<br><br>' +
                  '<strong>⚠️ Tus datos de contacto son públicos</strong> en el WHOIS ya que el titular no es de la Unión Europea.',
            confirmButtonText: 'Entendido'
        });
        <?php endif; ?>
        return;
    }

    const formData = new FormData();
    formData.append('_csrf_token', csrfToken);
    formData.append('enabled', enabled ? '1' : '0');

    Swal.fire({
        title: 'Actualizando...',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/customer/domain/${orderId}/toggle-whois-privacy`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: data.message,
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.error || 'No se pudo cambiar la protección WHOIS', 'error');
            // Revert toggle
            const toggle = document.getElementById('whoisPrivacyToggle');
            if (toggle) toggle.checked = <?= $isPrivateWhois ? 'true' : 'false' ?>;
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Error de conexión', 'error');
        const toggle = document.getElementById('whoisPrivacyToggle');
        if (toggle) toggle.checked = <?= $isPrivateWhois ? 'true' : 'false' ?>;
    });
}

function upgradeToCMS() {
    Swal.fire({
        title: '¿Activar MuseDock CMS?',
        html: 'Se creará un tenant con panel de administración completo para tu dominio.<br><br>' +
              '<strong>Se configurará:</strong><br>' +
              '• Tenant dedicado con CMS<br>' +
              '• Registros DNS @ y www → mortadelo.musedock.com<br>' +
              '• Certificado SSL automático<br>' +
              '• CDN y protección DDoS',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, activar CMS',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            Swal.fire({
                title: 'Activando CMS...',
                html: 'Esto puede tardar unos segundos...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/customer/domain/${orderId}/upgrade-to-cms`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡CMS Activado!',
                        html: data.message + (data.admin_url ? '<br><br><a href="' + data.admin_url + '" class="btn btn-sm btn-primary mt-2" target="_blank">Ir al Panel CMS</a>' : ''),
                        confirmButtonText: 'Entendido'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'No se pudo activar el CMS', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}

function downgradeToDNS() {
    Swal.fire({
        title: '¿Desactivar CMS?',
        html: '<strong class="text-danger">⚠️ ADVERTENCIA:</strong><br>' +
              'Esta acción eliminará permanentemente:<br><br>' +
              '• El tenant y todos sus datos<br>' +
              '• Los registros DNS @ y www<br>' +
              '• El acceso al panel de administración<br><br>' +
              '<strong>Esta acción NO se puede deshacer.</strong>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, desactivar CMS',
        cancelButtonText: 'Cancelar',
        input: 'checkbox',
        inputPlaceholder: 'Entiendo que se eliminarán todos los datos',
        inputValidator: (result) => {
            return !result && 'Debes confirmar que entiendes las consecuencias'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('_csrf_token', csrfToken);

            Swal.fire({
                title: 'Desactivando CMS...',
                html: 'Eliminando tenant y registros DNS...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/customer/domain/${orderId}/downgrade-to-dns`, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'CMS Desactivado',
                        text: data.message,
                        confirmButtonText: 'Entendido'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error || 'No se pudo desactivar el CMS', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }
    });
}
</script>
@endsection
