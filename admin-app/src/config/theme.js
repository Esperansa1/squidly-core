/**
 * Theme Configuration
 * 
 * Centralized color and theme configuration for the admin interface.
 * All colors should be imported from here to ensure easy swapping and consistency.
 */

// Default theme configuration (fallback values)
export const DEFAULT_THEME = {
  primary_color: '#D12525',      // Main brand color (red)
  secondary_color: '#F2F2F2',    // Background color (light gray)
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
};

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
export const generateCSSVariables = (theme) => {
  const cssVars = {};
  
  Object.entries(theme).forEach(([key, value]) => {
    cssVars[`--theme-${key.replace(/_/g, '-')}`] = value;
  });
  
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