/**
 * Theme Configuration for Customer App
 * Centralized colors, spacing, and styling values
 */

export const theme = {
  colors: {
    // Primary brand colors
    primary: '#DC2626',        // Red - main buttons, selected states
    primaryHover: '#B91C1C',   // Darker red for hover
    secondary: '#EA580C',      // Orange-red for add buttons
    secondaryHover: '#C2410C', // Darker orange for hover

    // Background colors
    background: '#F3F4F6',     // Light gray page background
    cardBg: '#FFFFFF',         // White for cards/panels

    // Text colors
    text: {
      primary: '#111827',      // Dark gray/black for headings
      secondary: '#6B7280',    // Medium gray for body text
      muted: '#9CA3AF',        // Light gray for descriptions
      white: '#FFFFFF',        // White text
    },

    // UI element colors
    border: '#E5E7EB',         // Light gray borders
    divider: '#D1D5DB',        // Divider lines

    // Status colors
    success: '#10B981',
    warning: '#F59E0B',
    error: '#EF4444',
    info: '#3B82F6',
  },

  spacing: {
    xs: '0.25rem',    // 4px
    sm: '0.5rem',     // 8px
    md: '1rem',       // 16px
    lg: '1.5rem',     // 24px
    xl: '2rem',       // 32px
    '2xl': '3rem',    // 48px
  },

  borderRadius: {
    sm: '0.375rem',   // 6px
    md: '0.5rem',     // 8px
    lg: '0.75rem',    // 12px
    xl: '1rem',       // 16px
    full: '9999px',   // Circle
  },

  shadows: {
    sm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
    md: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
    lg: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
    card: '0 2px 8px rgba(0, 0, 0, 0.08)',
  },

  layout: {
    sidebarWidth: '250px',     // Right navigation sidebar
    cartPanelWidth: '320px',   // Left cart panel
    maxContentWidth: '1400px', // Maximum content width
  },

  breakpoints: {
    mobile: '640px',
    tablet: '768px',
    desktop: '1024px',
    wide: '1280px',
  },

  fonts: {
    primary: "'LiaDiplomat', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif",
  },
};

export default theme;
