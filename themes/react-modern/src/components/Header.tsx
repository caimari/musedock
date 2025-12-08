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
    'w-full z-50 transition-all duration-300',
    sticky && 'sticky top-0',
    transparent && !isScrolled ? 'bg-transparent' : 'shadow-md'
  );

  const textColor = transparent && !isScrolled ? 'text-white' : '';

  // Estilos inline para colores personalizados
  const headerStyle = transparent && !isScrolled ? {} : {
    backgroundColor: settings.header_bg_color || '#ffffff',
    color: settings.header_text_color || '#1f2937'
  };

  return (
    <header className={headerClasses} style={headerStyle}>
      <div className="container-custom">
        <div className="flex items-center justify-between h-20">
          {/* Logo */}
          <div className="flex-shrink-0">
            <a href="/" className="flex items-center space-x-3">
              {settings.show_logo && settings.site_logo && (
                <img
                  src={settings.site_logo}
                  alt={settings.site_name || 'Logo'}
                  className="h-10 w-auto"
                />
              )}
              {settings.show_site_title && (
                <span className={cn('text-2xl font-bold text-gradient', textColor)}>
                  {settings.site_name || 'MuseDock'}
                </span>
              )}
            </a>
          </div>

          {/* Desktop Navigation */}
          {menu && menu.items && menu.items.length > 0 && (
            <nav className="hidden md:flex items-center space-x-8">
              {menu.items.map((item) => (
                <div key={item.id} className="relative group">
                  <a
                    href={formatUrl(item.url)}
                    target={item.target || '_self'}
                    className={cn(
                      'font-medium transition-colors hover:text-primary-600',
                      textColor
                    )}
                  >
                    {item.icon && <i className={`${item.icon} mr-2`} />}
                    {item.title}
                  </a>

                  {/* Submenu */}
                  {item.children && item.children.length > 0 && (
                    <div className="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                      <div className="py-2">
                        {item.children.map((subItem) => (
                          <a
                            key={subItem.id}
                            href={formatUrl(subItem.url)}
                            target={subItem.target || '_self'}
                            className="block px-4 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-600 transition-colors"
                          >
                            {subItem.icon && <i className={`${subItem.icon} mr-2`} />}
                            {subItem.title}
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
              'md:hidden p-2 rounded-lg transition-colors',
              textColor,
              'hover:bg-gray-100'
            )}
            aria-label="Toggle menu"
          >
            {isMobileMenuOpen ? (
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            ) : (
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            )}
          </button>
        </div>
      </div>

      {/* Mobile Menu */}
      {isMobileMenuOpen && menu && menu.items && (
        <div className="md:hidden bg-white border-t border-gray-200">
          <nav className="container-custom py-4 space-y-2">
            {menu.items.map((item) => (
              <div key={item.id}>
                <a
                  href={formatUrl(item.url)}
                  target={item.target || '_self'}
                  className="block px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-colors font-medium"
                  onClick={() => setIsMobileMenuOpen(false)}
                >
                  {item.icon && <i className={`${item.icon} mr-2`} />}
                  {item.title}
                </a>

                {/* Mobile Submenu */}
                {item.children && item.children.length > 0 && (
                  <div className="ml-4 mt-2 space-y-1">
                    {item.children.map((subItem) => (
                      <a
                        key={subItem.id}
                        href={formatUrl(subItem.url)}
                        target={subItem.target || '_self'}
                        className="block px-4 py-2 text-sm text-gray-600 hover:text-primary-600 transition-colors"
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
