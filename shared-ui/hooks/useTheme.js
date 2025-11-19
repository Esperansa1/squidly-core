/**
 * Theme Hook
 * 
 * Custom hook for accessing and managing theme configuration
 */

import { useMemo } from 'react';
import { getThemeConfig, generateCSSVariables, colorUtils } from '../config/theme.js';

/**
 * Custom hook for theme management
 * @param {Object} apiTheme - Theme configuration from API
 * @returns {Object} Theme utilities and configuration
 */
export const useTheme = (apiTheme = {}) => {
  const theme = useMemo(() => getThemeConfig(apiTheme), [apiTheme]);
  
  const cssVariables = useMemo(() => generateCSSVariables(theme), [theme]);
  
  const themeUtils = useMemo(() => ({
    ...colorUtils,
    
    // Quick access to common colors
    colors: {
      primary: theme.primary_color,
      secondary: theme.secondary_color,
      success: theme.success_color,
      danger: theme.danger_color,
      warning: theme.warning_color,
      info: theme.info_color,
    },
    
    // Specialized color functions
    getPrimaryWithOpacity: (opacity) => colorUtils.addOpacity(theme.primary_color, opacity),
    getSecondaryWithOpacity: (opacity) => colorUtils.addOpacity(theme.secondary_color, opacity),
    getDangerWithOpacity: (opacity) => colorUtils.addOpacity(theme.danger_color, opacity),
    
    // Focus and hover states
    getPrimaryFocus: () => colorUtils.getFocusRingColor(theme.primary_color),
    getPrimaryHover: () => colorUtils.getHoverColor(theme.primary_color),
    
    // Complete theme object
    theme,
    cssVariables,
  }), [theme, cssVariables]);
  
  return themeUtils;
};

export default useTheme;