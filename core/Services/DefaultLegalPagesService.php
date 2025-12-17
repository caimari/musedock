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
    ];

    /**
     * Check if a slug is a legal page slug
     */
    public static function isLegalPageSlug(string $slug): bool
    {
        return in_array($slug, self::LEGAL_SLUGS, true);
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
        $siteName = site_setting('site_name', 'Nuestro Sitio');
        $contactEmail = site_setting('contact_email', 'info@example.com');
        $contactAddress = site_setting('contact_address', '');
        $lastUpdated = date('d/m/Y');

        $pages = self::getPageTemplates($locale);

        if (!isset($pages[$slug])) {
            return null;
        }

        $page = $pages[$slug];

        // Replace placeholders with actual values
        $replacements = [
            '{{site_name}}' => $siteName,
            '{{contact_email}}' => $contactEmail,
            '{{contact_address}}' => $contactAddress,
            '{{last_updated}}' => $lastUpdated,
            '{{current_year}}' => date('Y'),
        ];

        $page['title'] = str_replace(array_keys($replacements), array_values($replacements), $page['title']);
        $page['content'] = str_replace(array_keys($replacements), array_values($replacements), $page['content']);

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
<h2>¿Qué son las cookies?</h2>
<p>Las cookies son pequeños archivos de texto que los sitios web almacenan en tu dispositivo (ordenador, tablet o móvil) cuando los visitas. Estas cookies permiten que el sitio web recuerde tus acciones y preferencias durante un período de tiempo, para que no tengas que volver a introducirlas cada vez que vuelvas al sitio o navegues de una página a otra.</p>

<h2>¿Qué tipos de cookies utilizamos?</h2>

<h3>Cookies estrictamente necesarias</h3>
<p>Estas cookies son esenciales para que puedas navegar por el sitio web y utilizar sus funciones. Sin estas cookies, los servicios que has solicitado no pueden ser proporcionados. Incluyen, por ejemplo, cookies que te permiten iniciar sesión en áreas seguras de nuestro sitio web.</p>

<h3>Cookies de análisis/rendimiento</h3>
<p>Nos permiten reconocer y contar el número de visitantes y ver cómo los visitantes se mueven por nuestro sitio web cuando lo utilizan. Esto nos ayuda a mejorar la forma en que funciona nuestro sitio web, por ejemplo, asegurándonos de que los usuarios encuentren fácilmente lo que buscan.</p>

<h2>¿Cómo gestionar las cookies?</h2>
<p>Puedes gestionar tus preferencias de cookies en cualquier momento haciendo clic en el enlace "Configuración de cookies" en el pie de página de nuestro sitio web. También puedes configurar tu navegador para que rechace todas las cookies o para que te avise cuando se envíe una cookie.</p>

<h2>Más información</h2>
<p>Si tienes alguna pregunta sobre nuestra política de cookies, puedes contactarnos en:</p>
<ul>
<li>Email: <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

<p><em>Última actualización: {{last_updated}}</em></p>
HTML
            ],

            'terms-and-conditions' => [
                'title' => 'Términos y Condiciones',
                'content' => <<<HTML
<h2>1. Introducción</h2>
<p>Bienvenido a <strong>{{site_name}}</strong>. Al acceder y utilizar este sitio web, aceptas cumplir con estos términos y condiciones de uso. Si no estás de acuerdo con alguno de estos términos, te rogamos que no utilices nuestro sitio.</p>

<h2>2. Uso del sitio web</h2>
<p>El contenido de las páginas de este sitio web es solo para tu información general y uso. Está sujeto a cambios sin previo aviso.</p>
<p>Ni nosotros ni terceros proporcionamos ninguna garantía en cuanto a la exactitud, puntualidad, rendimiento, integridad o idoneidad de la información y materiales encontrados u ofrecidos en este sitio web para ningún propósito particular.</p>

<h2>3. Propiedad intelectual</h2>
<p>Este sitio web contiene material que es propiedad nuestra o de nuestros licenciantes. Este material incluye, entre otros, el diseño, la disposición, el aspecto, la apariencia y los gráficos. La reproducción está prohibida salvo de conformidad con el aviso de copyright.</p>

<h2>4. Enlaces a otros sitios web</h2>
<p>De vez en cuando, este sitio web también puede incluir enlaces a otros sitios web. Estos enlaces se proporcionan para tu comodidad y para proporcionar más información. No significan que respaldemos el/los sitio(s) web. No tenemos responsabilidad por el contenido del/los sitio(s) web enlazado(s).</p>

<h2>5. Limitación de responsabilidad</h2>
<p>El uso de cualquier información o materiales en este sitio web es enteramente bajo tu propio riesgo, por lo cual no seremos responsables. Será tu propia responsabilidad asegurarte de que cualquier producto, servicio o información disponible a través de este sitio web cumpla con tus requisitos específicos.</p>

<h2>6. Ley aplicable</h2>
<p>El uso de este sitio web y cualquier disputa que surja de dicho uso del sitio web está sujeto a las leyes vigentes.</p>

<h2>7. Contacto</h2>
<p>Si tienes alguna pregunta sobre estos términos y condiciones, puedes contactarnos en:</p>
<ul>
<li>Email: <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

<p><em>Última actualización: {{last_updated}}</em></p>
HTML
            ],

            'privacy' => [
                'title' => 'Política de Privacidad',
                'content' => <<<HTML
<h2>1. Información que recopilamos</h2>
<p><strong>{{site_name}}</strong> se compromete a proteger tu privacidad. Esta política de privacidad explica qué información recopilamos, cómo la usamos y las medidas que tomamos para protegerla.</p>

<h3>Información que nos proporcionas</h3>
<p>Podemos recopilar información personal que nos proporcionas directamente, como:</p>
<ul>
<li>Nombre y apellidos</li>
<li>Dirección de correo electrónico</li>
<li>Número de teléfono</li>
<li>Cualquier otra información que decidas proporcionarnos</li>
</ul>

<h3>Información recopilada automáticamente</h3>
<p>Cuando visitas nuestro sitio web, podemos recopilar automáticamente cierta información, incluyendo:</p>
<ul>
<li>Dirección IP</li>
<li>Tipo de navegador y dispositivo</li>
<li>Páginas visitadas y tiempo de permanencia</li>
<li>Información de cookies (consulta nuestra Política de Cookies)</li>
</ul>

<h2>2. Cómo utilizamos tu información</h2>
<p>Utilizamos la información recopilada para:</p>
<ul>
<li>Proporcionar y mantener nuestros servicios</li>
<li>Mejorar y personalizar tu experiencia</li>
<li>Comunicarnos contigo sobre actualizaciones o cambios</li>
<li>Cumplir con obligaciones legales</li>
</ul>

<h2>3. Compartir información</h2>
<p>No vendemos, alquilamos ni compartimos tu información personal con terceros, excepto:</p>
<ul>
<li>Con tu consentimiento expreso</li>
<li>Para cumplir con obligaciones legales</li>
<li>Para proteger nuestros derechos o los de otros usuarios</li>
</ul>

<h2>4. Seguridad de los datos</h2>
<p>Implementamos medidas de seguridad técnicas y organizativas para proteger tu información personal contra acceso no autorizado, alteración, divulgación o destrucción.</p>

<h2>5. Tus derechos</h2>
<p>Tienes derecho a:</p>
<ul>
<li>Acceder a tus datos personales</li>
<li>Rectificar datos inexactos</li>
<li>Solicitar la eliminación de tus datos</li>
<li>Oponerte al procesamiento de tus datos</li>
<li>Solicitar la portabilidad de tus datos</li>
</ul>

<h2>6. Contacto</h2>
<p>Para ejercer tus derechos o si tienes preguntas sobre esta política de privacidad, contacta con nosotros:</p>
<ul>
<li>Email: <a href="mailto:{{contact_email}}">{{contact_email}}</a></li>
</ul>

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
