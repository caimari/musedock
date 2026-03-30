import { useState, useEffect } from 'react';

/**
 * Hook personalizado para detectar la posición del scroll
 * Útil para headers sticky, animaciones al scroll, etc.
 */
export function useScrollPosition() {
  const [scrollPosition, setScrollPosition] = useState(0);
  const [scrollDirection, setScrollDirection] = useState<'up' | 'down' | null>(null);

  useEffect(() => {
    let lastScrollY = window.pageYOffset;

    const updateScrollPosition = () => {
      const currentScrollY = window.pageYOffset;

      setScrollPosition(currentScrollY);
      setScrollDirection(currentScrollY > lastScrollY ? 'down' : 'up');

      lastScrollY = currentScrollY;
    };

    window.addEventListener('scroll', updateScrollPosition, { passive: true });

    return () => {
      window.removeEventListener('scroll', updateScrollPosition);
    };
  }, []);

  return {
    scrollPosition,
    scrollDirection,
    isScrolled: scrollPosition > 0,
    isScrolledPast: (threshold: number) => scrollPosition > threshold,
  };
}
