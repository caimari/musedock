import { formatUrl } from '@utils/index';
import type { SiteSettings, Language } from '@/types/index';

interface FooterProps {
  settings: SiteSettings;
  languages: Language[];
  currentLang: string;
}

export default function Footer({ settings, languages, currentLang }: FooterProps) {
  const currentYear = new Date().getFullYear();

  const socialLinks = [
    { key: 'social_facebook', icon: 'fab fa-facebook-f', label: 'Facebook' },
    { key: 'social_twitter', icon: 'fab fa-twitter', label: 'Twitter' },
    { key: 'social_instagram', icon: 'fab fa-instagram', label: 'Instagram' },
    { key: 'social_linkedin', icon: 'fab fa-linkedin-in', label: 'LinkedIn' },
    { key: 'social_youtube', icon: 'fab fa-youtube', label: 'YouTube' },
    { key: 'social_pinterest', icon: 'fab fa-pinterest-p', label: 'Pinterest' },
  ];

  const handleLanguageChange = (langCode: string) => {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', langCode);
    window.location.href = url.toString();
  };

  // Estilos inline para colores personalizados
  const footerStyle = {
    backgroundColor: settings.footer_bg_color || '#1f2937',
    color: settings.footer_text_color || '#ffffff'
  };

  return (
    <footer style={footerStyle}>
      {/* Main Footer Content */}
      <div className="container-custom py-12 md:py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12">
          {/* Brand Column */}
          <div className="space-y-4">
            <div className="flex items-center space-x-2">
              {settings.site_logo ? (
                <img
                  src={settings.site_logo}
                  alt={settings.site_name || 'Logo'}
                  className="h-10 w-auto brightness-0 invert"
                />
              ) : (
                <span className="text-2xl font-bold">
                  {settings.site_name || 'MuseDock'}
                </span>
              )}
            </div>

            {settings.footer_short_description && (
              <p className="text-gray-400 text-sm leading-relaxed">
                {settings.footer_short_description}
              </p>
            )}

            {/* Language Selector */}
            {languages.length > 0 && (
              <div className="pt-4">
                <label htmlFor="footer-lang-select" className="block text-sm font-medium mb-2">
                  Idioma / Language
                </label>
                <select
                  id="footer-lang-select"
                  value={currentLang}
                  onChange={(e) => handleLanguageChange(e.target.value)}
                  className="bg-gray-800 text-white border border-gray-700 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                  {languages.map((lang) => (
                    <option key={lang.code} value={lang.code}>
                      {lang.name}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* Social Links */}
            <div className="flex items-center space-x-4 pt-4">
              {socialLinks.map(({ key, icon, label }) => {
                const url = settings[key as keyof SiteSettings];
                if (!url) return null;

                return (
                  <a
                    key={key}
                    href={formatUrl(url as string)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="w-10 h-10 flex items-center justify-center bg-gray-800 hover:bg-primary-600 rounded-full transition-all duration-300 hover:scale-110"
                    aria-label={label}
                  >
                    <i className={icon} />
                  </a>
                );
              })}
            </div>
          </div>

          {/* Footer Column 1 - Renderizado por Blade */}
          <div id="footer-col-1" className="space-y-4">
            {/* Este contenido será inyectado por Blade */}
          </div>

          {/* Footer Column 2 - Renderizado por Blade */}
          <div id="footer-col-2" className="space-y-4">
            {/* Este contenido será inyectado por Blade */}
          </div>

          {/* Contact Column */}
          <div className="space-y-4">
            <h4 className="text-lg font-bold mb-4">
              {settings.footer_col4_title || 'Contacto'}
            </h4>
            <ul className="space-y-3 text-gray-400">
              {settings.contact_phone && (
                <li>
                  <a
                    href={`tel:${settings.contact_phone}`}
                    className="flex items-center hover:text-primary-400 transition-colors"
                  >
                    <i className="fas fa-phone mr-3" />
                    {settings.contact_phone}
                  </a>
                </li>
              )}

              {settings.contact_email && (
                <li>
                  <a
                    href={`mailto:${settings.contact_email}`}
                    className="flex items-center hover:text-primary-400 transition-colors"
                  >
                    <i className="fas fa-envelope mr-3" />
                    {settings.contact_email}
                  </a>
                </li>
              )}

              {settings.contact_address && (
                <li className="flex items-start">
                  <i className="fas fa-map-marker-alt mr-3 mt-1" />
                  <span>{settings.contact_address}</span>
                </li>
              )}

              {settings.contact_whatsapp && (
                <li>
                  <a
                    href={`https://wa.me/${settings.contact_whatsapp.replace(/[^0-9]/g, '')}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center hover:text-green-400 transition-colors"
                  >
                    <i className="fab fa-whatsapp mr-3" />
                    {settings.contact_whatsapp}
                  </a>
                </li>
              )}
            </ul>
          </div>
        </div>
      </div>

      {/* Footer Bottom */}
      <div className="border-t border-gray-800">
        <div className="container-custom py-6">
          <div className="flex flex-col md:flex-row items-center justify-between text-sm text-gray-400">
            <p>
              © {currentYear} {settings.site_name || 'MuseDock'}.{' '}
              {settings.site_credit || 'Todos los derechos reservados.'}
            </p>

            <p className="mt-2 md:mt-0">
              Powered by{' '}
              <a
                href="https://musedock.com"
                target="_blank"
                rel="noopener noreferrer"
                className="text-primary-400 hover:text-primary-300 transition-colors"
              >
                MuseDock CMS
              </a>
            </p>
          </div>
        </div>
      </div>
    </footer>
  );
}
