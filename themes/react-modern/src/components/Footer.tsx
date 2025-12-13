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
    <footer style={footerStyle} className="relative overflow-hidden">
      {/* Fondo decorativo con gradientes */}
      <div className="absolute inset-0 bg-gradient-to-br from-primary-950/50 via-transparent to-secondary-950/50 opacity-30"></div>
      <div className="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-primary-500/10 to-secondary-500/10 rounded-full blur-3xl"></div>
      <div className="absolute bottom-0 left-0 w-96 h-96 bg-gradient-to-tr from-accent-500/10 to-primary-500/10 rounded-full blur-3xl"></div>

      {/* Main Footer Content */}
      <div className="container-custom py-12 md:py-16 relative z-10">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-12">
          {/* Brand Column */}
          <div className="space-y-5 animate-slide-in-left">
            <div className="flex items-center space-x-3 group cursor-pointer">
              {settings.site_logo ? (
                <img
                  src={settings.site_logo}
                  alt={settings.site_name || 'Logo'}
                  className="h-10 w-auto brightness-0 invert transition-transform duration-300 group-hover:scale-110"
                />
              ) : (
                <span className="text-2xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">
                  {settings.site_name || 'MuseDock'}
                </span>
              )}
            </div>

            {settings.footer_short_description && (
              <p className="text-gray-300 text-sm leading-relaxed max-w-xs">
                {settings.footer_short_description}
              </p>
            )}

            {/* Language Selector */}
            {languages.length > 0 && (
              <div className="pt-2">
                <label htmlFor="footer-lang-select" className="block text-sm font-medium mb-2 text-gray-300">
                  <i className="fas fa-globe mr-2"></i>Idioma / Language
                </label>
                <select
                  id="footer-lang-select"
                  value={currentLang}
                  onChange={(e) => handleLanguageChange(e.target.value)}
                  className="w-full bg-white/10 backdrop-blur-sm text-white border border-white/20 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 hover:bg-white/20 cursor-pointer"
                >
                  {languages.map((lang) => (
                    <option key={lang.code} value={lang.code} className="bg-gray-800">
                      {lang.name}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {/* Social Links */}
            <div className="flex items-center gap-3 pt-4">
              {socialLinks.map(({ key, icon, label }) => {
                const url = settings[key as keyof SiteSettings];
                if (!url) return null;

                return (
                  <a
                    key={key}
                    href={formatUrl(url as string)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="w-11 h-11 flex items-center justify-center bg-white/10 backdrop-blur-sm hover:bg-gradient-to-br hover:from-primary-500 hover:to-secondary-500 rounded-xl transition-all duration-300 hover:scale-110 hover:-translate-y-1 group border border-white/10"
                    aria-label={label}
                  >
                    <i className={`${icon} text-lg group-hover:scale-110 transition-transform duration-300`} />
                  </a>
                );
              })}
            </div>
          </div>

          {/* Footer Column 1 - Renderizado por Blade */}
          <div id="footer-col-1" className="space-y-4 animate-slide-in-left" style={{ animationDelay: '0.1s' }}>
            {/* Este contenido será inyectado por Blade */}
          </div>

          {/* Footer Column 2 - Renderizado por Blade */}
          <div id="footer-col-2" className="space-y-4 animate-slide-in-left" style={{ animationDelay: '0.2s' }}>
            {/* Este contenido será inyectado por Blade */}
          </div>

          {/* Contact Column */}
          <div className="space-y-4 animate-slide-in-right">
            <h4 className="text-lg font-bold mb-5 bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">
              {settings.footer_col4_title || 'Contacto'}
            </h4>
            <ul className="space-y-3.5 text-gray-300">
              {settings.contact_phone && (
                <li>
                  <a
                    href={`tel:${settings.contact_phone}`}
                    className="flex items-center hover:text-primary-400 transition-all duration-300 group hover:translate-x-1"
                  >
                    <span className="w-9 h-9 flex items-center justify-center bg-white/10 rounded-lg mr-3 group-hover:bg-primary-500/20 transition-colors">
                      <i className="fas fa-phone text-sm" />
                    </span>
                    <span className="text-sm">{settings.contact_phone}</span>
                  </a>
                </li>
              )}

              {settings.contact_email && (
                <li>
                  <a
                    href={`mailto:${settings.contact_email}`}
                    className="flex items-center hover:text-primary-400 transition-all duration-300 group hover:translate-x-1"
                  >
                    <span className="w-9 h-9 flex items-center justify-center bg-white/10 rounded-lg mr-3 group-hover:bg-primary-500/20 transition-colors">
                      <i className="fas fa-envelope text-sm" />
                    </span>
                    <span className="text-sm break-all">{settings.contact_email}</span>
                  </a>
                </li>
              )}

              {settings.contact_address && (
                <li className="flex items-start group">
                  <span className="w-9 h-9 flex items-center justify-center bg-white/10 rounded-lg mr-3 flex-shrink-0">
                    <i className="fas fa-map-marker-alt text-sm" />
                  </span>
                  <span className="text-sm">{settings.contact_address}</span>
                </li>
              )}

              {settings.contact_whatsapp && (
                <li>
                  <a
                    href={`https://wa.me/${settings.contact_whatsapp.replace(/[^0-9]/g, '')}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center hover:text-success-400 transition-all duration-300 group hover:translate-x-1"
                  >
                    <span className="w-9 h-9 flex items-center justify-center bg-white/10 rounded-lg mr-3 group-hover:bg-success-500/20 transition-colors">
                      <i className="fab fa-whatsapp text-sm" />
                    </span>
                    <span className="text-sm">{settings.contact_whatsapp}</span>
                  </a>
                </li>
              )}
            </ul>
          </div>
        </div>
      </div>

      {/* Footer Bottom */}
      <div className="border-t border-white/10 relative z-10">
        <div className="container-custom py-6">
          <div className="flex flex-col md:flex-row items-center justify-between text-sm text-gray-300 gap-3">
            <p className="flex items-center gap-2">
              <span className="inline-block w-1.5 h-1.5 bg-gradient-to-r from-primary-500 to-secondary-500 rounded-full animate-pulse"></span>
              © {currentYear} {settings.site_name || 'MuseDock'}.{' '}
              {settings.site_credit || 'Todos los derechos reservados.'}
            </p>

            <p className="flex items-center gap-2">
              <span className="text-gray-400">Powered by</span>
              <a
                href="https://musedock.com"
                target="_blank"
                rel="noopener noreferrer"
                className="font-medium bg-gradient-to-r from-primary-400 via-secondary-400 to-accent-400 bg-clip-text text-transparent hover:from-primary-300 hover:via-secondary-300 hover:to-accent-300 transition-all duration-300 hover:tracking-wide"
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
