import { useState, useEffect } from 'react';

/**
 * Hook to detect media query breakpoints
 * Returns boolean indicating if query matches
 *
 * @param {string} query - Media query string (e.g., '(max-width: 599px)')
 * @returns {boolean} - True if query matches
 *
 * @example
 * const isMobile = useMediaQuery('(max-width: 599px)');
 */
export function useMediaQuery(query) {
  const [matches, setMatches] = useState(() => {
    if (typeof window !== 'undefined') {
      return window.matchMedia(query).matches;
    }
    return false;
  });

  useEffect(() => {
    const media = window.matchMedia(query);

    const listener = (e) => setMatches(e.matches);

    // Modern browsers
    if (media.addEventListener) {
      media.addEventListener('change', listener);
      return () => media.removeEventListener('change', listener);
    }
    // Fallback for older browsers
    media.addListener(listener);
    return () => media.removeListener(listener);
  }, [query]);

  return matches;
}

/**
 * Preset breakpoint hooks for convenience
 * Mobile: 0-599px
 * Tablet: 600-1023px
 * Desktop: 1024px+
 */

/**
 * Check if viewport is mobile (0-599px)
 * @returns {boolean}
 */
export function useIsMobile() {
  return useMediaQuery('(max-width: 599px)');
}

/**
 * Check if viewport is tablet (600-1023px)
 * @returns {boolean}
 */
export function useIsTablet() {
  return useMediaQuery('(min-width: 600px) and (max-width: 1023px)');
}

/**
 * Check if viewport is desktop (1024px+)
 * @returns {boolean}
 */
export function useIsDesktop() {
  return useMediaQuery('(min-width: 1024px)');
}

/**
 * Check if device supports touch
 * @returns {boolean}
 */
export function useIsTouchDevice() {
  return useMediaQuery('(hover: none) and (pointer: coarse)');
}
