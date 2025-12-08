/**
 * Tipos TypeScript para el tema React Modern de MuseDock
 * Estos tipos representan los datos que vienen de PHP/Blade
 */

export interface MenuItem {
  id: number;
  title: string;
  url: string;
  target?: '_blank' | '_self';
  icon?: string;
  children?: MenuItem[];
}

export interface Menu {
  id: number;
  title: string;
  location: string;
  items: MenuItem[];
}

export interface SiteSettings {
  site_name?: string;
  site_description?: string;
  site_logo?: string;
  site_favicon?: string;
  show_logo?: boolean;
  show_site_title?: boolean;
  language?: string;

  // Redes sociales
  social_facebook?: string;
  social_twitter?: string;
  social_instagram?: string;
  social_linkedin?: string;
  social_youtube?: string;
  social_pinterest?: string;

  // Contacto
  contact_email?: string;
  contact_phone?: string;
  contact_address?: string;
  contact_whatsapp?: string;

  // Footer
  footer_short_description?: string;
  footer_col4_title?: string;
  site_credit?: string;

  // Theme customization
  primary_color?: string;
  secondary_color?: string;
  accent_color?: string;
  header_bg_color?: string;
  header_text_color?: string;
  footer_bg_color?: string;
  footer_text_color?: string;
  header_transparent?: boolean;
  header_sticky?: boolean;
  custom_css?: string;
  custom_js?: string;
}

export interface Language {
  code: string;
  name: string;
  active: boolean;
}

export interface Page {
  id: number;
  title: string;
  slug: string;
  content: string;
  featured_image?: string;
  meta_description?: string;
  published_at?: string;
}

export interface Widget {
  id: number;
  type: string;
  title?: string;
  config: Record<string, any>;
  order: number;
}

export interface WidgetArea {
  slug: string;
  widgets: Widget[];
}

/**
 * Props que se pasan de Blade a React mediante data-* attributes
 */
export interface AppDataProps {
  settings: SiteSettings;
  currentLang: string;
  languages: Language[];
  menu?: Menu;
  page?: Page;
}

/**
 * Configuración del tema desde theme.json
 */
export interface ThemeConfig {
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  header_transparent: boolean;
  header_sticky: boolean;
}
