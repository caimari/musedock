<?php

namespace Screenart\Musedock\Services;

/**
 * Service to provide default legal pages content when user hasn't created custom ones.
 * Supports: cookie-policy, terms-and-conditions, privacy
 * Languages: es, en
 */
class DefaultLegalPagesService
{
    /**
     * Supported legal page slugs
     */
    public const LEGAL_SLUGS = [
        'cookie-policy',
        'terms-and-conditions',
        'privacy',
        'aviso-legal',
    ];

    /**
     * Mapeo de variantes de slug a su slug canónico.
     * Permite que URLs como /politica-de-cookies muestren la página legal por defecto.
     */
    private const SLUG_ALIASES = [
        // Cookies
        'cookies'              => 'cookie-policy',
        'politica-de-cookies'  => 'cookie-policy',
        'politica-cookies'     => 'cookie-policy',
        // Privacy
        'privacidad'                => 'privacy',
        'politica-de-privacidad'    => 'privacy',
        'politica-privacidad'       => 'privacy',
        // Terms
        'terminos-y-condiciones'         => 'terms-and-conditions',
        'terminos-y-condiciones-de-uso'  => 'terms-and-conditions',
        'terminos'                       => 'terms-and-conditions',
        'terms'                          => 'terms-and-conditions',
        'condiciones-de-uso'             => 'terms-and-conditions',
        // Legal
        'legal'       => 'aviso-legal',
        'aviso_legal' => 'aviso-legal',
    ];

    /**
     * Check if a slug is a legal page slug (including aliases)
     */
    public static function isLegalPageSlug(string $slug): bool
    {
        return in_array($slug, self::LEGAL_SLUGS, true) || isset(self::SLUG_ALIASES[$slug]);
    }

    /**
     * Resolve a slug alias to its canonical slug
     */
    public static function resolveSlug(string $slug): string
    {
        return self::SLUG_ALIASES[$slug] ?? $slug;
    }

    /**
     * Get default legal page content
     *
     * @param string $slug The page slug (cookie-policy, terms-and-conditions, privacy)
     * @param string $locale The language code (es, en)
     * @return array|null Array with 'title' and 'content', or null if not found
     */
    public static function getDefaultPage(string $slug, string $locale = 'es'): ?array
    {
        // Resolver alias (ej: 'politica-de-cookies' → 'cookie-policy')
        $slug = self::resolveSlug($slug);

        $siteName = site_setting('site_name', 'Nuestro Sitio');
        $lastUpdated = date('d/m/Y');

        // Legal data (fallback to contact data if legal-specific fields are empty)
        $legalName = site_setting('legal_name', '') ?: $siteName;
        $legalEmail = site_setting('legal_email', '') ?: site_setting('contact_email', 'info@example.com');
        $legalAddress = site_setting('legal_address', '') ?: site_setting('contact_address', '');
        $legalNif = site_setting('legal_nif', '');
        $legalRegistryData = site_setting('legal_registry_data', '');
        $entityType = site_setting('legal_entity_type', 'personal');
        $jurisdiction = site_setting('legal_jurisdiction', 'ES');
        $hasEconomicActivity = site_setting('site_has_economic_activity', '0') === '1';
        $targetsEU = site_setting('legal_targets_eu', '0') === '1';

        // Supervisory authority (with smart defaults)
        $supervisoryAuthority = site_setting('legal_supervisory_authority', '');
        if (empty($supervisoryAuthority)) {
            $defaultAuthorities = [
                'ES' => 'Agencia Española de Protección de Datos (AEPD) — www.aepd.es',
                'BR' => 'Autoridade Nacional de Proteção de Dados (ANPD) — www.gov.br/anpd',
                'MX' => 'Instituto Nacional de Transparencia (INAI) — www.inai.org.mx',
                'AR' => 'Agencia de Acceso a la Información Pública (AAIP) — www.argentina.gob.ar/aaip',
            ];
            $supervisoryAuthority = $defaultAuthorities[$jurisdiction] ?? '';
        }

        // Determine if GDPR applies (EU/EEE jurisdiction or targets EU users)
        $isEU = in_array($jurisdiction, ['ES', 'EU']);
        $gdprApplies = $isEU || $targetsEU;
        // LGPD (Brazil) has similar data protection requirements
        $lgpdApplies = ($jurisdiction === 'BR');
        // Feature toggles
        $usesAnalyticsCookies = site_setting('site_uses_analytics_cookies', '0') === '1';
        $hasUserRegistration = site_setting('site_has_user_registration', '0') === '1';
        $hasPaidServices = site_setting('site_has_paid_services', '0') === '1';

        $pages = self::getPageTemplates($locale);

        if (!isset($pages[$slug])) {
            return null;
        }

        $page = $pages[$slug];

        // Replace placeholders with actual values
        $replacements = [
            '{{site_name}}' => $siteName,
            '{{legal_name}}' => $legalName,
            '{{contact_email}}' => $legalEmail,
            '{{legal_email}}' => $legalEmail,
            '{{contact_address}}' => $legalAddress,
            '{{legal_address}}' => $legalAddress,
            '{{legal_nif}}' => $legalNif,
            '{{legal_registry_data}}' => $legalRegistryData,
            '{{supervisory_authority}}' => $supervisoryAuthority,
            '{{last_updated}}' => $lastUpdated,
            '{{current_year}}' => date('Y'),
        ];

        $page['title'] = str_replace(array_keys($replacements), array_values($replacements), $page['title']);
        $page['content'] = str_replace(array_keys($replacements), array_values($replacements), $page['content']);

        // Remove address block if no address
        if (empty($legalAddress)) {
            $page['content'] = preg_replace('/<li><strong>[^<]*direcci[^<]*<\/strong>\s*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>[^<]*domicilio[^<]*<\/strong>\s*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>Address[^<]*<\/strong>\s*<\/li>\s*/ui', '', $page['content']);
        }

        // Remove NIF/EIN block if empty or personal type
        if (empty($legalNif) || $entityType === 'personal') {
            $page['content'] = preg_replace('/<li><strong>[^<]*NIF[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>[^<]*CIF[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>[^<]*EIN[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>[^<]*Tax ID[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
        }

        // Remove registry data block if not empresa type or empty
        if (empty($legalRegistryData) || $entityType !== 'empresa') {
            $page['content'] = preg_replace('/<li><strong>[^<]*registr[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>[^<]*inscrit[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>[^<]*State of[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li><strong>[^<]*Filing[^<]*<\/strong>[^<]*<\/li>\s*/ui', '', $page['content']);
        }

        // Remove LSSI-specific content if not Spanish jurisdiction or personal without activity
        if ($jurisdiction !== 'ES' || ($entityType === 'personal' && !$hasEconomicActivity)) {
            $page['content'] = preg_replace('/<p[^>]*>[^<]*LSSI[^<]*<\/p>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<p[^>]*>[^<]*Ley 34\/2002[^<]*<\/p>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<p[^>]*>[^<]*LSSI-CE[^<]*<\/p>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<p[^>]*>[^<]*servicios de la sociedad[^<]*<\/p>\s*/ui', '', $page['content']);
        }

        // Remove supervisory authority reference if empty (no applicable authority)
        if (empty($supervisoryAuthority)) {
            $page['content'] = preg_replace('/<li>[^<]*supervisory_authority[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li>[^<]*autoridad de control[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li>[^<]*AEPD[^<]*<\/li>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<li>[^<]*Agencia Española de Protección[^<]*<\/li>\s*/ui', '', $page['content']);
        }

        // Remove RGPD-specific content if neither GDPR nor LGPD applies
        if (!$gdprApplies && !$lgpdApplies) {
            $page['content'] = preg_replace('/<p[^>]*>[^<]*RGPD[^<]*<\/p>\s*/ui', '', $page['content']);
            $page['content'] = preg_replace('/<p[^>]*>[^<]*Reglamento General de Protección[^<]*<\/p>\s*/ui', '', $page['content']);
        }

        // Remove LOPD-GDD references if not Spanish jurisdiction
        if ($jurisdiction !== 'ES') {
            $page['content'] = preg_replace('/<p[^>]*>[^<]*LOPD[^<]*<\/p>\s*/ui', '', $page['content']);
        }

        // Cookie policy: toggle analytics section based on setting
        if (!$usesAnalyticsCookies) {
            // Remove the analytics cookies section, keep the conditional future notice
            $page['content'] = preg_replace('/<!-- analytics-cookies-start -->.*?<!-- analytics-cookies-end -->/s', '', $page['content']);
        } else {
            // Remove the "no analytics" conditional notice
            $page['content'] = preg_replace('/<!-- no-analytics-notice-start -->.*?<!-- no-analytics-notice-end -->/s', '', $page['content']);
        }

        // Remove LSSI cookie references if not Spanish jurisdiction
        if ($jurisdiction !== 'ES') {
            $page['content'] = preg_replace('/art\.\s*22\.2\s*LSSI-CE/ui', 'la normativa aplicable', $page['content']);
        }

        // Terms & Conditions: conditional sections
        if (!$hasUserRegistration) {
            $page['content'] = preg_replace('/<!-- registration-start -->.*?<!-- registration-end -->/s', '', $page['content']);
        }
        if (!$hasPaidServices) {
            $page['content'] = preg_replace('/<!-- paid-services-start -->.*?<!-- paid-services-end -->/s', '', $page['content']);
        }

        return $page;
    }

    /**
     * Get all page templates for a given locale
     */
    private static function getPageTemplates(string $locale): array
    {
        if ($locale === 'en') {
            return self::getEnglishTemplates();
        }

        // Default to Spanish
        return self::getSpanishTemplates();
    }

    /**
     * Spanish legal page templates
     */
    private static function getSpanishTemplates(): array
    {
        return [
            'cookie-policy' => [
                'title' => 'Política de Cookies',
                'content' => <<<HTML
<h2>1. Responsable del uso de cookies</h2>
<p>El responsable del uso de cookies en este sitio web es <strong>{{legal_name}}</strong>, titular de <strong>{{site_name}}</strong>. Para más información, consulta nuestro <a href="/aviso-legal">Aviso Legal</a> y nuestra <a href="/privacy">Política de Privacidad</a>.</p>

<h2>2. ¿Qué son las cookies?</h2>
<p>Las cookies son pequeños archivos de texto que los sitios web almacenan en tu dispositivo (ordenador, tablet o móvil) cuando los visitas. Permiten que el sitio web recuerde tus acciones y preferencias durante un período de tiempo.</p>

<h2>3. Base legal para el uso de cookies</h2>
<p>Las cookies técnicamente necesarias se instalan en base al interés legítimo del responsable o la necesidad de prestar el servicio solicitado por el usuario (art. 6.1.f RGPD). No requieren consentimiento previo.</p>
<!-- analytics-cookies-start -->
<p>Las cookies de análisis y de terceros requieren el consentimiento previo e informado del usuario (art. 6.1.a RGPD, art. 22.2 LSSI-CE). Este consentimiento se recaba a través del banner de cookies que se muestra en la primera visita al sitio web.</p>
<!-- analytics-cookies-end -->

<h2>4. Cookies que utilizamos</h2>

<h3>Cookies técnicas (estrictamente necesarias)</h3>
<p>Estas cookies son esenciales para el funcionamiento del sitio web. No requieren consentimiento y no pueden desactivarse.</p>
<table>
<thead>
<tr><th>Cookie</th><th>Titular</th><th>Finalidad</th><th>Duración</th></tr>
</thead>
<tbody>
<tr><td><code>PHPSESSID</code></td><td>Propia</td><td>Identificador de sesión del servidor. Necesaria para mantener la sesión del usuario.</td><td>Sesión</td></tr>
<tr><td><code>musedock_lang</code></td><td>Propia</td><td>Almacena la preferencia de idioma seleccionada por el usuario.</td><td>1 año</td></tr>
<tr><td><code>musedock_cookies_accepted</code></td><td>Propia</td><td>Registra si el usuario ha interactuado con el aviso de cookies.</td><td>1 año</td></tr>
<tr><td><code>musedock_cookie_analytics</code></td><td>Propia</td><td>Almacena la preferencia del usuario sobre cookies de analítica.</td><td>1 año</td></tr>
</tbody>
</table>

<!-- analytics-cookies-start -->
<h3>Cookies de análisis (requieren consentimiento)</h3>
<p>Estas cookies permiten reconocer y contar el número de visitantes y analizar cómo navegan por el sitio web. Solo se instalan si el usuario las acepta expresamente a través del banner de consentimiento.</p>
<table>
<thead>
<tr><th>Cookie</th><th>Titular</th><th>Finalidad</th><th>Duración</th></tr>
</thead>
<tbody>
<tr><td><code>_ga</code></td><td>Google (tercero)</td><td>Distingue usuarios únicos mediante un identificador aleatorio.</td><td>2 años</td></tr>
<tr><td><code>_ga_*</code></td><td>Google (tercero)</td><td>Almacena el estado de la sesión en Google Analytics.</td><td>2 años</td></tr>
<tr><td><code>_gid</code></td><td>Google (tercero)</td><td>Distingue usuarios y limita el porcentaje de solicitudes.</td><td>24 horas</td></tr>
</tbody>
</table>
<p>Las cookies de Google Analytics pueden implicar transferencias internacionales de datos a servidores de Google LLC (EE.UU.), amparadas en las cláusulas contractuales tipo de la Comisión Europea. Para más información: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Política de Privacidad de Google</a>.</p>
<!-- analytics-cookies-end -->

<!-- no-analytics-notice-start -->
<h3>Cookies de análisis</h3>
<p>Actualmente este sitio web <strong>no utiliza cookies de análisis ni de terceros</strong>. Las estadísticas de uso se obtienen mediante análisis de los registros del servidor, sin instalar cookies adicionales en el navegador del usuario.</p>
<p>En caso de que se activen cookies de análisis o rendimiento en el futuro, se informará al usuario y se solicitará su consentimiento previo antes de su instalación. La presente política será actualizada en consecuencia.</p>
<!-- no-analytics-notice-end -->

<h2>5. ¿Cómo gestionar las cookies?</h2>
<p>Puedes gestionar tus preferencias de cookies en cualquier momento:</p>
<ul>
<li><strong>Desde este sitio web:</strong> haz clic en "Configuración de Cookies" en el pie de página para revisar y modificar tus preferencias.</li>
<li><strong>Desde tu navegador:</strong> puedes configurarlo para rechazar cookies, eliminar las existentes o recibir un aviso antes de que se instalen.</li>
</ul>
<!-- analytics-cookies-start -->
<p>Puedes revocar tu consentimiento sobre cookies no esenciales en cualquier momento con el mismo nivel de facilidad con que lo otorgaste. La retirada del consentimiento no afecta a la licitud del tratamiento previo a dicha retirada.</p>
<!-- analytics-cookies-end -->
<p>Ten en cuenta que la eliminación de cookies técnicas puede afectar al correcto funcionamiento del sitio web.</p>

<h2>6. Contacto</h2>
<p>Para cualquier consulta sobre esta política de cookies:</p>
<ul>
<li>Email: <a href="mailto:{{legal_email}}">{{legal_email}}</a></li>
</ul>

<p><em>Última actualización: {{last_updated}}</em></p>
HTML
            ],

            'terms-and-conditions' => [
                'title' => 'Términos y Condiciones de Uso',
                'content' => <<<HTML
<style>.legal-autonumber{counter-reset:legal-section}.legal-autonumber>h2::before{counter-increment:legal-section;content:counter(legal-section) ". "}</style>
<div class="legal-autonumber">
<p>Los presentes Términos y Condiciones regulan el acceso y uso del sitio web <strong>{{site_name}}</strong>, titularidad de <strong>{{legal_name}}</strong>. Al acceder y utilizar este sitio web, el usuario acepta quedar vinculado por estos términos. Si no estás de acuerdo, te rogamos que no utilices el sitio.</p>
<p>Para información sobre el tratamiento de datos personales, consulta nuestra <a href="/privacy">Política de Privacidad</a>. Para datos identificativos del titular, consulta el <a href="/aviso-legal">Aviso Legal</a>.</p>

<h2>Uso del sitio web</h2>
<p>El usuario se compromete a utilizar el sitio web de conformidad con la ley, estos Términos y Condiciones, y las buenas prácticas generalmente aceptadas en Internet. Queda prohibido:</p>
<ul>
<li>Utilizar el sitio web con fines ilícitos o contrarios al orden público.</li>
<li>Intentar acceder a áreas restringidas del sitio web sin autorización.</li>
<li>Introducir virus, malware o cualquier código malicioso.</li>
<li>Realizar actividades que puedan dañar, sobrecargar o impedir el funcionamiento normal del sitio web.</li>
</ul>

<h2>Propiedad intelectual e industrial</h2>
<p>Los derechos de propiedad intelectual e industrial sobre los contenidos de este sitio web corresponden a <strong>{{legal_name}}</strong> o a sus legítimos titulares, conforme a lo detallado en el <a href="/aviso-legal">Aviso Legal</a>. Queda prohibida su reproducción, distribución o transformación sin autorización expresa.</p>

<!-- registration-start -->
<h2>Registro de usuarios y cuentas</h2>

<h3>Creación de cuenta</h3>
<p>Para acceder a determinadas funcionalidades del sitio web, puede ser necesario crear una cuenta de usuario. El usuario se compromete a:</p>
<ul>
<li>Proporcionar información veraz, actualizada y completa durante el registro.</li>
<li>Mantener la confidencialidad de sus credenciales de acceso.</li>
<li>Notificar de inmediato cualquier uso no autorizado de su cuenta.</li>
</ul>

<h3>Uso aceptable</h3>
<p>El usuario es responsable de toda la actividad realizada desde su cuenta. Queda prohibido:</p>
<ul>
<li>Crear cuentas con datos falsos o suplantando la identidad de terceros.</li>
<li>Compartir credenciales de acceso con terceros.</li>
<li>Utilizar la cuenta para enviar contenido ilegal, ofensivo o que vulnere derechos de terceros.</li>
</ul>

<h3>Suspensión y cancelación</h3>
<p><strong>{{legal_name}}</strong> se reserva el derecho de suspender o cancelar cuentas de usuario que incumplan estos términos, previa notificación salvo en casos de urgencia o incumplimiento grave. El usuario puede solicitar la cancelación de su cuenta en cualquier momento contactando con nosotros.</p>
<!-- registration-end -->

<!-- paid-services-start -->
<h2>Condiciones de contratación</h2>

<h3>Precios y facturación</h3>
<p>Los precios de los servicios o productos ofrecidos se indican en el sitio web e incluyen los impuestos aplicables, salvo que se indique lo contrario. <strong>{{legal_name}}</strong> se reserva el derecho de modificar los precios en cualquier momento, sin que ello afecte a los pedidos ya confirmados.</p>

<h3>Proceso de compra</h3>
<p>La contratación de servicios se formaliza mediante la aceptación del pedido por parte del usuario y la confirmación de pago. Se enviará un justificante de compra al correo electrónico proporcionado.</p>

<h3>Política de reembolso</h3>
<p>Los reembolsos se gestionarán conforme a la normativa aplicable. En caso de servicios digitales, el usuario acepta que, una vez iniciada la prestación del servicio con su consentimiento expreso, puede perder el derecho de desistimiento.</p>

<h3>Derecho de desistimiento</h3>
<p>De conformidad con la normativa europea de protección de consumidores, el usuario que tenga la condición de consumidor dispone de un plazo de 14 días naturales desde la contratación para ejercer su derecho de desistimiento, sin necesidad de justificación. En particular, conforme al artículo 103.m) del Real Decreto Legislativo 1/2007, el derecho de desistimiento no será aplicable al suministro de contenido digital que no se preste en un soporte material cuando la ejecución haya comenzado con el consentimiento previo y expreso del consumidor, con el conocimiento de que al hacerlo pierde su derecho de desistimiento. Para ejercer este derecho en los casos en que proceda, contacta con nosotros en <a href="mailto:{{legal_email}}">{{legal_email}}</a>.</p>
<!-- paid-services-end -->

<h2>Enlaces a terceros</h2>
<p>Este sitio web puede contener enlaces a sitios de terceros. <strong>{{legal_name}}</strong> no controla dichos sitios ni se hace responsable de sus contenidos o políticas de privacidad. Para más detalle, consulta el <a href="/aviso-legal">Aviso Legal</a>.</p>

<h2>Limitación de responsabilidad</h2>
<p><strong>{{legal_name}}</strong> no garantiza la disponibilidad ininterrumpida del sitio web ni la ausencia de errores en sus contenidos. El uso de la información y materiales publicados es responsabilidad exclusiva del usuario. En ningún caso <strong>{{legal_name}}</strong> será responsable de daños indirectos, incidentales o consecuentes derivados del uso del sitio web.</p>

<h2>Modificaciones</h2>
<p>Nos reservamos el derecho a modificar estos Términos y Condiciones en cualquier momento. Las modificaciones entrarán en vigor desde su publicación en el sitio web. El uso continuado del sitio tras la publicación de cambios implica la aceptación de los nuevos términos.</p>

<h2>Legislación aplicable y jurisdicción</h2>
<p>Los presentes Términos y Condiciones se rigen por la legislación española. Para la resolución de cualquier controversia, las partes se someten a los Juzgados y Tribunales competentes conforme a derecho, sin perjuicio de lo dispuesto en la normativa aplicable en materia de consumidores y usuarios.</p>

<h2>Contacto</h2>
<p>Para cualquier consulta relacionada con estos términos:</p>
<ul>
<li>Email: <a href="mailto:{{legal_email}}">{{legal_email}}</a></li>
</ul>
</div>

<p><em>Última actualización: {{last_updated}}</em></p>
HTML
            ],

            'aviso-legal' => [
                'title' => 'Aviso Legal',
                'content' => <<<HTML
<h2>1. Datos identificativos</h2>
<p>En cumplimiento del artículo 10 de la Ley 34/2002, de 11 de julio, de Servicios de la Sociedad de la Información y de Comercio Electrónico (LSSI-CE), se informa a los usuarios de los datos del titular de este sitio web:</p>
<ul>
<li><strong>Denominación:</strong> {{legal_name}}</li>
<li><strong>NIF/CIF:</strong> {{legal_nif}}</li>
<li><strong>Domicilio:</strong> {{legal_address}}</li>
<li><strong>Correo electrónico:</strong> <a href="mailto:{{legal_email}}">{{legal_email}}</a></li>
<li><strong>Inscrita en:</strong> {{legal_registry_data}}</li>
</ul>

<h2>2. Objeto y ámbito de aplicación</h2>
<p>El presente Aviso Legal regula el acceso y uso del sitio web <strong>{{site_name}}</strong>. El acceso al sitio web implica la aceptación plena y sin reservas de las presentes condiciones.</p>

<h2>3. Propiedad intelectual e industrial</h2>
<p>Todos los contenidos del sitio web (textos, imágenes, diseño, código fuente, logotipos, marcas, etc.) son propiedad de <strong>{{legal_name}}</strong> o de sus legítimos titulares, y están protegidos por la legislación vigente en materia de propiedad intelectual e industrial.</p>
<p>Queda prohibida la reproducción, distribución, comunicación pública o transformación de dichos contenidos sin la autorización expresa del titular.</p>

<h2>4. Responsabilidad</h2>
<p><strong>{{legal_name}}</strong>, titular de <strong>{{site_name}}</strong>, no se hace responsable de los daños y perjuicios que pudieran derivarse del uso de los contenidos del sitio web, ni de la falta de disponibilidad o continuidad del servicio. El usuario es el único responsable del uso que haga del sitio web.</p>

<h2>5. Política de enlaces</h2>
<p>El sitio web puede contener enlaces a sitios web de terceros. <strong>{{legal_name}}</strong> no controla dichos sitios y no se hace responsable de sus contenidos. La inclusión de un enlace no implica aprobación ni recomendación del sitio enlazado.</p>

<h2>6. Legislación aplicable y jurisdicción</h2>
<p>Las presentes condiciones se rigen por la legislación española vigente. Para la resolución de cualquier controversia derivada del acceso o uso de este sitio web, las partes se someten a los Juzgados y Tribunales competentes conforme a derecho, sin perjuicio de lo dispuesto en la normativa aplicable en materia de consumidores y usuarios.</p>

<h2>7. Contacto</h2>
<p>Para cualquier consulta relacionada con este Aviso Legal, puede contactar con nosotros en:</p>
<ul>
<li>Email: <a href="mailto:{{legal_email}}">{{legal_email}}</a></li>
</ul>

<p><em>Última actualización: {{last_updated}}</em></p>
HTML
            ],

            'privacy' => [
                'title' => 'Política de Privacidad',
                'content' => <<<HTML
<p>La presente Política de Privacidad tiene por objeto informar al usuario sobre el tratamiento de sus datos personales realizado a través del sitio web <strong>{{site_name}}</strong>.</p>

<h2>1. Responsable del tratamiento</h2>
<ul>
<li><strong>Responsable:</strong> {{legal_name}}</li>
<li><strong>NIF/CIF:</strong> {{legal_nif}}</li>
<li><strong>Domicilio:</strong> {{legal_address}}</li>
<li><strong>Correo electrónico:</strong> <a href="mailto:{{legal_email}}">{{legal_email}}</a></li>
<li><strong>Inscrita en:</strong> {{legal_registry_data}}</li>
</ul>
<p>Para más información sobre el titular, consulta nuestro <a href="/aviso-legal">Aviso Legal</a>.</p>

<h2>2. Datos que recopilamos</h2>

<h3>Datos facilitados por el usuario</h3>
<p>Podemos recopilar los datos personales que nos proporcionas directamente a través de formularios de contacto, suscripciones o registros:</p>
<ul>
<li>Nombre y apellidos</li>
<li>Dirección de correo electrónico</li>
<li>Número de teléfono (si se facilita)</li>
<li>Cualquier otra información que decidas proporcionarnos voluntariamente</li>
</ul>

<h3>Datos recopilados automáticamente</h3>
<p>Al navegar por este sitio web, podemos recopilar automáticamente cierta información técnica:</p>
<ul>
<li>Dirección IP (anonimizada cuando sea posible)</li>
<li>Tipo de navegador y sistema operativo</li>
<li>Páginas visitadas, tiempo de permanencia y patrones de navegación</li>
<li>Datos de cookies (consulta nuestra <a href="/cookie-policy">Política de Cookies</a>)</li>
</ul>
<p>Nuestro servidor registra datos de acceso (dirección IP, fecha/hora, páginas solicitadas, agente de usuario) con fines de seguridad y análisis estadístico interno, sin instalar cookies adicionales en el navegador del usuario.</p>

<h2>3. Finalidades y bases legales del tratamiento</h2>
<p>Tratamos tus datos personales para las siguientes finalidades, amparándonos en las bases legales indicadas conforme al artículo 6 del RGPD:</p>
<table>
<thead>
<tr><th>Finalidad</th><th>Base legal</th></tr>
</thead>
<tbody>
<tr><td>Responder a consultas y solicitudes de contacto</td><td>Consentimiento del interesado (art. 6.1.a)</td></tr>
<tr><td>Gestión de suscripciones y envío de comunicaciones</td><td>Consentimiento del interesado (art. 6.1.a)</td></tr>
<tr><td>Análisis de uso del sitio web y mejora del servicio</td><td>Interés legítimo del responsable (art. 6.1.f)</td></tr>
<tr><td>Cumplimiento de obligaciones fiscales y legales</td><td>Obligación legal (art. 6.1.c)</td></tr>
<tr><td>Prevención del fraude y seguridad del sitio web</td><td>Interés legítimo del responsable (art. 6.1.f)</td></tr>
</tbody>
</table>

<h2>4. Plazo de conservación de los datos</h2>
<p>Los datos personales se conservarán durante el tiempo necesario para cumplir con la finalidad para la que fueron recabados y, posteriormente, durante los plazos de prescripción legal aplicables:</p>
<ul>
<li><strong>Datos de contacto:</strong> mientras se mantenga la relación con el usuario o hasta que solicite su supresión.</li>
<li><strong>Datos de navegación:</strong> según la duración de las cookies utilizadas (consulta la Política de Cookies).</li>
<li><strong>Datos fiscales:</strong> durante los plazos exigidos por la normativa tributaria aplicable.</li>
</ul>

<h2>5. Destinatarios y categorías de encargados del tratamiento</h2>
<p>No vendemos ni alquilamos tus datos personales. Podemos compartir tus datos con las siguientes categorías de destinatarios, en la medida necesaria para las finalidades indicadas:</p>
<ul>
<li><strong>Proveedores de alojamiento web:</strong> para el almacenamiento y servicio del sitio web.</li>
<li><strong>Proveedores de servicios de correo electrónico:</strong> para el envío de comunicaciones.</li>
<li><strong>Herramientas de analítica web:</strong> para el análisis estadístico del uso del sitio (datos anonimizados cuando sea posible).</li>
<li><strong>Autoridades públicas:</strong> cuando exista una obligación legal.</li>
</ul>

<h2>6. Transferencias internacionales de datos</h2>
<p>Algunos de los proveedores mencionados pueden estar ubicados fuera del Espacio Económico Europeo (EEE). En estos casos, nos aseguramos de que dichas transferencias se realicen con las garantías adecuadas, tales como cláusulas contractuales tipo aprobadas por la Comisión Europea o decisiones de adecuación.</p>

<h2>7. Seguridad de los datos</h2>
<p>Implementamos medidas de seguridad técnicas y organizativas apropiadas para proteger tus datos personales contra acceso no autorizado, alteración, divulgación o destrucción accidental o ilícita. No obstante, ningún sistema es completamente seguro y no podemos garantizar la seguridad absoluta de los datos.</p>

<h2>8. Tus derechos</h2>
<p>De acuerdo con el RGPD y la normativa aplicable, tienes derecho a:</p>
<ul>
<li><strong>Acceso:</strong> obtener confirmación de si se tratan tus datos y acceder a ellos.</li>
<li><strong>Rectificación:</strong> solicitar la corrección de datos inexactos o incompletos.</li>
<li><strong>Supresión:</strong> solicitar la eliminación de tus datos (derecho al olvido) cuando ya no sean necesarios.</li>
<li><strong>Limitación:</strong> solicitar la limitación del tratamiento en determinadas circunstancias.</li>
<li><strong>Oposición:</strong> oponerte al tratamiento basado en interés legítimo.</li>
<li><strong>Portabilidad:</strong> recibir tus datos en un formato estructurado y de uso común.</li>
<li><strong>Retirar el consentimiento:</strong> cuando el tratamiento se base en tu consentimiento, puedes retirarlo en cualquier momento sin que ello afecte a la licitud del tratamiento previo.</li>
<li><strong>Reclamación:</strong> presentar una reclamación ante la autoridad de control competente: {{supervisory_authority}}.</li>
</ul>
<p>Para ejercer cualquiera de estos derechos, contacta con nosotros en <a href="mailto:{{legal_email}}">{{legal_email}}</a>, indicando el derecho que deseas ejercer y acompañando copia de tu documento de identidad.</p>

<h2>9. Modificaciones de esta política</h2>
<p>Nos reservamos el derecho a modificar esta Política de Privacidad en cualquier momento. Las modificaciones entrarán en vigor desde su publicación en este sitio web. Te recomendamos revisar esta página periódicamente.</p>

<p><em>Última actualización: {{last_updated}}</em></p>
HTML
            ],
        ];
    }

    /**
     * English legal page templates
     */
    private static function getEnglishTemplates(): array
    {
        return [
            'cookie-policy' => [
                'title' => 'Cookie Policy',
                'content' => <<<HTML
<h2>What are cookies?</h2>
<p>Cookies are small text files that websites store on your device (computer, tablet, or mobile) when you visit them. These cookies allow the website to remember your actions and preferences over a period of time, so you don't have to re-enter them every time you return to the site or navigate from one page to another.</p>

<h2>What types of cookies do we use?</h2>

<h3>Strictly necessary cookies</h3>
<p>These cookies are essential for you to browse the website and use its features. Without these cookies, the services you have requested cannot be provided. They include, for example, cookies that allow you to log into secure areas of our website.</p>

<h3>Analytics/performance cookies</h3>
<p>They allow us to recognize and count the number of visitors and see how visitors move around our website when using it. This helps us improve the way our website works, for example, by ensuring that users easily find what they are looking for.</p>

<h2>How to manage cookies?</h2>
<p>You can manage your cookie preferences at any time by clicking the "Cookie Settings" link in the footer of our website. You can also set your browser to reject all cookies or to notify you when a cookie is sent.</p>

<h2>More information</h2>
<p>If you have any questions about our cookie policy, you can contact us at:</p>
<ul>
<li>Email: <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

<p><em>Last updated: {{last_updated}}</em></p>
HTML
            ],

            'terms-and-conditions' => [
                'title' => 'Terms and Conditions',
                'content' => <<<HTML
<h2>1. Introduction</h2>
<p>Welcome to <strong>{{site_name}}</strong>. By accessing and using this website, you agree to comply with these terms and conditions of use. If you do not agree with any of these terms, please do not use our site.</p>

<h2>2. Use of the website</h2>
<p>The content of the pages of this website is for your general information and use only. It is subject to change without notice.</p>
<p>Neither we nor any third parties provide any warranty as to the accuracy, timeliness, performance, completeness, or suitability of the information and materials found or offered on this website for any particular purpose.</p>

<h2>3. Intellectual property</h2>
<p>This website contains material that is owned by or licensed to us. This material includes, but is not limited to, the design, layout, look, appearance, and graphics. Reproduction is prohibited except in accordance with the copyright notice.</p>

<h2>4. Links to other websites</h2>
<p>From time to time, this website may also include links to other websites. These links are provided for your convenience to provide further information. They do not signify that we endorse the website(s). We have no responsibility for the content of the linked website(s).</p>

<h2>5. Limitation of liability</h2>
<p>Your use of any information or materials on this website is entirely at your own risk, for which we shall not be liable. It shall be your own responsibility to ensure that any products, services, or information available through this website meet your specific requirements.</p>

<h2>6. Governing law</h2>
<p>Your use of this website and any dispute arising out of such use of the website is subject to applicable laws.</p>

<h2>7. Contact</h2>
<p>If you have any questions about these terms and conditions, you can contact us at:</p>
<ul>
<li>Email: <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

<p><em>Last updated: {{last_updated}}</em></p>
HTML
            ],

            'aviso-legal' => [
                'title' => 'Legal Notice',
                'content' => <<<HTML
<h2>1. Identifying information</h2>
<p>In compliance with article 10 of Spanish Law 34/2002, of July 11, on Information Society Services and Electronic Commerce (LSSI-CE), users are informed of the data of the owner of this website:</p>
<ul>
<li><strong>Name:</strong> {{site_name}}</li>
<li><strong>Email:</strong> <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

<h2>2. Purpose and scope</h2>
<p>This Legal Notice governs access to and use of the website <strong>{{site_name}}</strong>. Accessing the website implies full and unreserved acceptance of these conditions.</p>

<h2>3. Intellectual and industrial property</h2>
<p>All contents of the website (texts, images, design, source code, logos, trademarks, etc.) are the property of <strong>{{site_name}}</strong> or its content providers, and are protected by applicable intellectual and industrial property legislation.</p>
<p>Reproduction, distribution, public communication or transformation of such content without the express authorisation of the owner is prohibited.</p>

<h2>4. Liability</h2>
<p><strong>{{site_name}}</strong> is not responsible for any damages arising from the use of website content, nor from the lack of availability or continuity of the service. The user is solely responsible for their use of the website.</p>

<h2>5. Links policy</h2>
<p>The website may contain links to third-party websites. <strong>{{site_name}}</strong> does not control such sites and is not responsible for their content. The inclusion of a link does not imply endorsement or recommendation of the linked site.</p>

<h2>6. Applicable law and jurisdiction</h2>
<p>These conditions are governed by applicable Spanish legislation. For the resolution of any dispute arising from access to or use of this website, the parties submit to the competent Courts and Tribunals.</p>

<h2>7. Contact</h2>
<p>For any queries related to this Legal Notice, you can contact us at:</p>
<ul>
<li>Email: <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

<p><em>Last updated: {{last_updated}}</em></p>
HTML
            ],

            'privacy' => [
                'title' => 'Privacy Policy',
                'content' => <<<HTML
<h2>1. Information we collect</h2>
<p><strong>{{site_name}}</strong> is committed to protecting your privacy. This privacy policy explains what information we collect, how we use it, and the measures we take to protect it.</p>

<h3>Information you provide to us</h3>
<p>We may collect personal information that you provide to us directly, such as:</p>
<ul>
<li>First and last name</li>
<li>Email address</li>
<li>Phone number</li>
<li>Any other information you choose to provide</li>
</ul>

<h3>Automatically collected information</h3>
<p>When you visit our website, we may automatically collect certain information, including:</p>
<ul>
<li>IP address</li>
<li>Browser and device type</li>
<li>Pages visited and time spent</li>
<li>Cookie information (see our Cookie Policy)</li>
</ul>

<h2>2. How we use your information</h2>
<p>We use the collected information to:</p>
<ul>
<li>Provide and maintain our services</li>
<li>Improve and personalize your experience</li>
<li>Communicate with you about updates or changes</li>
<li>Comply with legal obligations</li>
</ul>

<h2>3. Sharing information</h2>
<p>We do not sell, rent, or share your personal information with third parties, except:</p>
<ul>
<li>With your express consent</li>
<li>To comply with legal obligations</li>
<li>To protect our rights or those of other users</li>
</ul>

<h2>4. Data security</h2>
<p>We implement technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

<h2>5. Your rights</h2>
<p>You have the right to:</p>
<ul>
<li>Access your personal data</li>
<li>Rectify inaccurate data</li>
<li>Request deletion of your data</li>
<li>Object to processing of your data</li>
<li>Request portability of your data</li>
</ul>

<h2>6. Contact</h2>
<p>To exercise your rights or if you have questions about this privacy policy, contact us:</p>
<ul>
<li>Email: <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

<p><em>Last updated: {{last_updated}}</em></p>
HTML
            ],
        ];
    }
}
