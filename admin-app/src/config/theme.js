/**
 * Theme Configuration
 *
 * Centralized color and theme configuration for the admin interface.
 * All colors should be imported from here to ensure easy swapping and consistency.
 */

// Static fallback values used when CSS variables are not yet set
const THEME_DEFAULTS = {
  primary_color: '#D12525',      // Main brand color (red)
  secondary_color: '#F2F2F2',    // Background color (light gray)
  accent_color: '#D12525',       // Accent color (same as primary by default)
  success_color: '#10B981',      // Success states (green)
  danger_color: '#EF4444',       // Error/danger states (red)
  warning_color: '#F59E0B',      // Warning states (yellow)
  info_color: '#3B82F6',         // Info states (blue)

  // Text colors
  text_primary: '#111827',       // Primary text (dark gray)
  text_secondary: '#6B7280',     // Secondary text (medium gray)
  text_muted: '#9CA3AF',         // Muted text (light gray)

  // Border and divider colors
  border_color: '#E5E7EB',       // Standard borders
  divider_color: '#F3F4F6',      // Section dividers

  // Background variations
  bg_white: '#FFFFFF',           // Pure white backgrounds
  bg_gray_50: '#F9FAFB',         // Very light gray
  bg_gray_100: '#F3F4F6',        // Light gray hover states
  bg_hover_light: 'rgba(249, 250, 251, 1)', // Hover states

  // Additional colors for components
  text_disabled: '#374151',      // Disabled text color
  border_light: '#d1d5db',       // Light borders

  // Shadow and overlay colors
  shadow_light: 'rgba(0, 0, 0, 0.1)',
  shadow_medium: 'rgba(0, 0, 0, 0.05)',

  // Scrollbar colors
  scrollbar_track: '#f1f1f1',
  scrollbar_thumb: '#c1c1c1',
  scrollbar_thumb_hover: '#a8a8a8',

  // Error/Success message backgrounds with opacity
  error_bg: 'rgba(239, 68, 68, 0.1)',
  error_border: 'rgba(239, 68, 68, 0.2)',
  success_bg: 'rgba(16, 185, 129, 0.1)',
  success_border: 'rgba(16, 185, 129, 0.2)'
};

/**
 * Convert a theme key (snake_case) to its CSS variable name.
 * e.g. "primary_color" → "--theme-primary-color"
 */
const toCssVar = (key) => `--theme-${key.replace(/_/g, '-')}`;

/**
 * Read the live CSS variable value from the document root.
 * Returns the trimmed string, or null if not set / DOM not available.
 */
const readCssVar = (key) => {
  if (typeof document === 'undefined') return null;
  const val = getComputedStyle(document.documentElement)
    .getPropertyValue(toCssVar(key))
    .trim();
  return val || null;
};

/**
 * DEFAULT_THEME is a Proxy that reads live CSS variable values at access time.
 * This means every component that does `const theme = DEFAULT_THEME; theme.primary_color`
 * will always get the currently-applied theme color — including after a settings save —
 * without requiring any component changes.
 *
 * Falls back to THEME_DEFAULTS when CSS variables have not yet been set (e.g. SSR or
 * very early boot before applyTheme() runs).
 */
export const DEFAULT_THEME = new Proxy(THEME_DEFAULTS, {
  get(target, key) {
    // Only intercept own string keys (not Symbol, not prototype methods)
    if (typeof key === 'string' && Object.prototype.hasOwnProperty.call(target, key)) {
      const live = readCssVar(key);
      return live !== null ? live : target[key];
    }
    return target[key];
  }
});

/**
 * Get theme configuration from API or use defaults
 * @param {Object} apiTheme - Theme object from API
 * @returns {Object} Complete theme configuration
 */
export const getThemeConfig = (apiTheme = {}) => {
  return {
    ...DEFAULT_THEME,
    ...apiTheme
  };
};

/**
 * Generate CSS custom properties for theme colors
 * @param {Object} theme - Theme configuration
 * @returns {Object} CSS custom properties object
 */
/**
 * Convert a hex color to rgba with given opacity (0-1).
 * Returns null if the value is not a parseable 6-digit hex.
 */
const hexToRgba = (hex, opacity) => {
  const clean = (hex || '').replace('#', '');
  if (clean.length !== 6) return null;
  const r = parseInt(clean.substring(0, 2), 16);
  const g = parseInt(clean.substring(2, 4), 16);
  const b = parseInt(clean.substring(4, 6), 16);
  if (isNaN(r) || isNaN(g) || isNaN(b)) return null;
  return `rgba(${r}, ${g}, ${b}, ${opacity})`;
};

export const generateCSSVariables = (theme) => {
  const cssVars = {};

  Object.entries(theme).forEach(([key, value]) => {
    cssVars[`--theme-${key.replace(/_/g, '-')}`] = value;
  });

  // Generate pre-computed opacity variants for primary_color so components
  // can use var(--theme-primary-color-10), var(--theme-primary-color-20), etc.
  // instead of interpolating the hex value directly (which breaks live updates).
  const primary = theme.primary_color;
  if (primary) {
    const opacities = { 10: 0.1, 20: 0.12, 25: 0.25, 40: 0.25, 50: 0.5 };
    Object.entries(opacities).forEach(([suffix, opacity]) => {
      const rgba = hexToRgba(primary, opacity);
      if (rgba) cssVars[`--theme-primary-color-${suffix}`] = rgba;
    });
  }

  return cssVars;
};

/**
 * Color utility functions
 */
export const colorUtils = {
  /**
   * Add opacity to a hex color
   * @param {string} hexColor - Hex color code
   * @param {number} opacity - Opacity value (0-1)
   * @returns {string} RGBA color string
   */
  addOpacity: (hexColor, opacity) => {
    const hex = hexColor.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
  },
  
  /**
   * Generate focus ring color with opacity
   * @param {string} baseColor - Base color for focus ring
   * @returns {string} Focus ring color with opacity
   */
  getFocusRingColor: (baseColor) => colorUtils.addOpacity(baseColor, 0.25),
  
  /**
   * Generate hover color with opacity
   * @param {string} baseColor - Base color for hover state
   * @returns {string} Hover color with opacity
   */
  getHoverColor: (baseColor) => colorUtils.addOpacity(baseColor, 0.1),
};

export default DEFAULT_THEME;