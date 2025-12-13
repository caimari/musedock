import { useState, useEffect } from 'react';
import { useScrollPosition } from '@hooks/useScrollPosition';
import { useIsMobile } from '@hooks/useMediaQuery';
import { cn, formatUrl } from '@utils/index';
import type { Menu, SiteSettings } from '@/types/index';

interface HeaderProps {
  menu?: Menu;
  settings: SiteSettings;
  transparent?: boolean;
  sticky?: boolean;
}

export default function Header({ menu, settings, transparent = false, sticky = true }: HeaderProps) {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const { isScrolled } = useScrollPosition();
  const isMobile = useIsMobile();

  // Cerrar menú móvil cuando se cambia a desktop
  useEffect(() => {
    if (!isMobile && isMobileMenuOpen) {
      setIsMobileMenuOpen(false);
    }
  }, [isMobile]);

  // Prevenir scroll cuando el menú móvil está abierto
  useEffect(() => {
    if (isMobileMenuOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    return () => {
      document.body.style.overflow = '';
    };
  }, [isMobileMenuOpen]);

  const headerClasses = cn(
    'w-full z-50 transition-all duration-500',
    sticky && 'sticky top-0',
    transparent && !isScrolled
      ? 'bg-transparent'
      : 'backdrop-blur-xl bg-white/80 shadow-lg border-b border-gray-100/50'
  );

  const textColor = transparent && !isScrolled ? 'text-white drop-shadow-lg' : '';

  // Estilos inline para colores personalizados
  const headerStyle = transparent && !isScrolled ? {} : {
    backgroundColor: settings.header_bg_color ? `${settings.header_bg_color}dd` : 'rgba(255, 255, 255, 0.85)',
    color: settings.header_text_color || '#1f2937'
  };

  return (
    <header className={headerClasses} style={headerStyle}>
      <div className="container-custom">
        <div className="flex items-center justify-between h-20">
          {/* Logo */}
          <div className="flex-shrink-0">
            <a href="/" className="flex items-center space-x-3 group">
              {settings.show_logo && settings.site_logo && (
                <img
                  src={settings.site_logo}
                  alt={settings.site_name || 'Logo'}
                  className="h-10 w-auto transition-transform duration-300 group-hover:scale-110"
                />
              )}
              {settings.show_site_title && (
                <span className={cn(
                  'text-2xl font-bold bg-gradient-to-r from-primary-500 via-secondary-500 to-accent-500 bg-clip-text text-transparent',
                  'transition-all duration-300 group-hover:tracking-wide',
                  textColor && 'from-white via-white to-white'
                )}>
                  {settings.site_name || 'MuseDock'}
                </span>
              )}
            </a>
          </div>

          {/* Desktop Navigation */}
          {menu && menu.items && menu.items.length > 0 && (
            <nav className="hidden md:flex items-center space-x-1">
              {menu.items.map((item, index) => (
                <div key={item.id} className="relative group">
                  <a
                    href={formatUrl(item.url)}
                    target={item.target || '_self'}
                    className={cn(
                      'px-4 py-2 rounded-lg font-medium transition-all duration-300',
                      'hover:bg-gradient-to-r hover:from-primary-500/10 hover:to-secondary-500/10',
                      'hover:text-primary-600 relative overflow-hidden group',
                      'animate-slide-in-left',
                      textColor
                    )}
                    style={{ animationDelay: `${index * 0.1}s` }}
                  >
                    <span className="relative z-10 flex items-center">
                      {item.icon && <i className={`${item.icon} mr-2`} />}
                      {item.title}
                    </span>
                    <span className="absolute inset-0 bg-gradient-to-r from-primary-500 to-secondary-500 opacity-0 group-hover:opacity-10 transition-opacity duration-300"></span>
                  </a>

                  {/* Submenu con efecto glassmorphism */}
                  {item.children && item.children.length > 0 && (
                    <div className="absolute left-0 mt-2 w-56 backdrop-blur-xl bg-white/90 rounded-2xl shadow-2xl border border-gray-100/50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform group-hover:translate-y-0 -translate-y-2 overflow-hidden">
                      <div className="py-2">
                        {item.children.map((subItem) => (
                          <a
                            key={subItem.id}
                            href={formatUrl(subItem.url)}
                            target={subItem.target || '_self'}
                            className="block px-5 py-3 text-gray-700 hover:bg-gradient-to-r hover:from-primary-50 hover:to-secondary-50 hover:text-primary-600 transition-all duration-200 group/item"
                          >
                            <span className="flex items-center">
                              {subItem.icon && <i className={`${subItem.icon} mr-2 transition-transform duration-200 group-hover/item:scale-110`} />}
                              {subItem.title}
                            </span>
                          </a>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </nav>
          )}

          {/* Mobile menu button */}
          <button
            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
            className={cn(
              'md:hidden p-3 rounded-xl transition-all duration-300',
              'hover:bg-gradient-to-r hover:from-primary-500/10 hover:to-secondary-500/10',
              'hover:scale-110 active:scale-95',
              textColor
            )}
            aria-label="Toggle menu"
          >
            {isMobileMenuOpen ? (
              <svg className="w-6 h-6 transition-transform duration-300 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
              </svg>
            ) : (
              <svg className="w-6 h-6 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            )}
          </button>
        </div>
      </div>

      {/* Mobile Menu */}
      {isMobileMenuOpen && menu && menu.items && (
        <div className="md:hidden backdrop-blur-xl bg-white/95 border-t border-gray-100/50 shadow-xl animate-slide-down">
          <nav className="container-custom py-6 space-y-2">
            {menu.items.map((item, index) => (
              <div key={item.id} className="animate-slide-in-left" style={{ animationDelay: `${index * 0.05}s` }}>
                <a
                  href={formatUrl(item.url)}
                  target={item.target || '_self'}
                  className="block px-5 py-3.5 text-gray-700 hover:bg-gradient-to-r hover:from-primary-50 hover:to-secondary-50 hover:text-primary-600 rounded-xl transition-all duration-300 font-medium group"
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  <span className="flex items-center">
                    {item.icon && <i className={`${item.icon} mr-3 transition-transform duration-200 group-hover:scale-110`} />}
                    {item.title}
                  </span>
                </a>

                {/* Mobile Submenu */}
                {item.children && item.children.length > 0 && (
                  <div className="ml-6 mt-2 space-y-1 pl-4 border-l-2 border-primary-200">
                    {item.children.map((subItem, subIndex) => (
                      <a
                        key={subItem.id}
                        href={formatUrl(subItem.url)}
                        target={subItem.target || '_self'}
                        className="block px-4 py-2.5 text-sm text-gray-600 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-all duration-200 animate-slide-in-left"
                        style={{ animationDelay: `${(index * 0.05) + (subIndex * 0.03)}s` }}
                        onClick={() => setIsMobileMenuOpen(false)}
                      >
                        {subItem.icon && <i className={`${subItem.icon} mr-2`} />}
                        {subItem.title}
                      </a>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </nav>
        </div>
      )}
    </header>
  );
}
