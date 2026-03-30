/**
 * Utilidades para el tema React Modern
 */

import type { AppDataProps } from '@/types/index';

/**
 * Lee los datos pasados desde Blade mediante data-* attributes
 * @param elementId ID del elemento HTML que contiene los datos
 * @returns Datos parseados o null si no existen
 */
export function getAppData(elementId: string = 'react-app'): AppDataProps | null {
  const element = document.getElementById(elementId);
  if (!element) {
    console.warn(`Element with id "${elementId}" not found`);
    return null;
  }

  try {
    const settings = element.dataset.settings ? JSON.parse(element.dataset.settings) : {};
    const currentLang = element.dataset.currentLang || 'es';
    const languages = element.dataset.languages ? JSON.parse(element.dataset.languages) : [];
    const menu = element.dataset.menu ? JSON.parse(element.dataset.menu) : undefined;
    const page = element.dataset.page ? JSON.parse(element.dataset.page) : undefined;

    return {
      settings,
      currentLang,
      languages,
      menu,
      page,
    };
  } catch (error) {
    console.error('Error parsing app data:', error);
    return null;
  }
}

/**
 * Combina clases CSS condicionalmente (similar a clsx)
 * @param classes Array de clases o condiciones
 * @returns String con las clases concatenadas
 */
export function cn(...classes: (string | boolean | undefined | null)[]): string {
  return classes.filter(Boolean).join(' ');
}

/**
 * Formatea una URL relativa o absoluta
 * @param url URL a formatear
 * @returns URL formateada
 */
export function formatUrl(url: string): string {
  if (!url) return '/';
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }
  return url.startsWith('/') ? url : `/${url}`;
}

/**
 * Obtiene la URL de un asset del tema
 * @param path Ruta del asset
 * @returns URL completa del asset
 */
export function asset(path: string): string {
  const basePath = '/assets/themes/react-modern';
  return `${basePath}/${path.replace(/^\//, '')}`;
}

/**
 * Trunca un texto a una longitud específica
 * @param text Texto a truncar
 * @param maxLength Longitud máxima
 * @param suffix Sufijo a añadir (por defecto '...')
 * @returns Texto truncado
 */
export function truncate(text: string, maxLength: number, suffix: string = '...'): string {
  if (text.length <= maxLength) return text;
  return text.substring(0, maxLength - suffix.length) + suffix;
}

/**
 * Debounce function para evitar múltiples ejecuciones
 * @param func Función a ejecutar
 * @param wait Tiempo de espera en ms
 * @returns Función con debounce
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: ReturnType<typeof setTimeout> | null = null;

  return function executedFunction(...args: Parameters<T>) {
    const later = () => {
      timeout = null;
      func(...args);
    };

    if (timeout) {
      clearTimeout(timeout);
    }
    timeout = setTimeout(later, wait);
  };
}

/**
 * Detecta si el usuario está en un dispositivo móvil
 * @returns true si es móvil
 */
export function isMobile(): boolean {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
    navigator.userAgent
  );
}

/**
 * Scroll suave a un elemento
 * @param elementId ID del elemento destino
 * @param offset Offset desde la parte superior (px)
 */
export function scrollToElement(elementId: string, offset: number = 0): void {
  const element = document.getElementById(elementId);
  if (!element) return;

  const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
  const offsetPosition = elementPosition - offset;

  window.scrollTo({
    top: offsetPosition,
    behavior: 'smooth',
  });
}

/**
 * Formatea una fecha
 * @param date Fecha a formatear (string o Date)
 * @param locale Locale a usar (por defecto 'es')
 * @returns Fecha formateada
 */
export function formatDate(
  date: string | Date,
  locale: string = 'es'
): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;
  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(dateObj);
}

/**
 * Sanitiza HTML básico para prevenir XSS
 * Nota: Para producción, considera usar una librería como DOMPurify
 * @param html HTML a sanitizar
 * @returns HTML sanitizado
 */
export function sanitizeHtml(html: string): string {
  const div = document.createElement('div');
  div.textContent = html;
  return div.innerHTML;
}
