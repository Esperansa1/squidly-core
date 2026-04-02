import React from 'react';

/**
 * Button component for customer app.
 * Uses CSS variables for all brand colors — single source of truth.
 */
const Button = React.forwardRef(({
  variant = 'primary',
  size = 'md',
  className = '',
  children,
  disabled = false,
  loading = false,
  fullWidth = false,
  onClick,
  type = 'button',
  style: styleProp,
  ...props
}, ref) => {
  const getStyle = () => {
    const base = {
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      gap: '0.5rem',
      fontWeight: '600',
      cursor: disabled || loading ? 'not-allowed' : 'pointer',
      opacity: disabled || loading ? 0.6 : 1,
      transition: 'filter 0.15s ease',
      border: 'none',
      outline: 'none',
      width: fullWidth ? '100%' : undefined,
    };

    const sizeMap = {
      xs: { padding: '0.25rem 0.75rem', fontSize: '0.75rem', borderRadius: '0.375rem' },
      sm: { padding: '0.375rem 0.875rem', fontSize: '0.875rem', borderRadius: '0.375rem' },
      md: { padding: '0.5rem 1rem', fontSize: '1rem', borderRadius: '0.5rem' },
      lg: { padding: '0.75rem 1.5rem', fontSize: '1.125rem', borderRadius: '0.5rem' },
    };

    const variantMap = {
      primary: { backgroundColor: 'var(--theme-primary-color)', color: '#fff' },
      secondary: { backgroundColor: 'transparent', color: 'var(--theme-primary-color)', border: '1.5px solid var(--theme-primary-color)' },
      outline: { backgroundColor: '#fff', color: '#374151', border: '1.5px solid #D1D5DB' },
      ghost: { backgroundColor: 'transparent', color: '#6B7280' },
      error: { backgroundColor: 'var(--theme-danger-color, #EF4444)', color: '#fff' },
    };

    return { ...base, ...sizeMap[size], ...variantMap[variant], ...styleProp };
  };

  return (
    <button
      ref={ref}
      type={type}
      style={getStyle()}
      className={className}
      disabled={disabled || loading}
      onClick={onClick}
      onMouseEnter={e => { if (!disabled && !loading) e.currentTarget.style.filter = 'brightness(0.88)'; }}
      onMouseLeave={e => { e.currentTarget.style.filter = ''; }}
      {...props}
    >
      {loading ? (
        <span style={{ display: 'inline-block', width: '1em', height: '1em', border: '2px solid currentColor', borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 0.7s linear infinite' }} />
      ) : children}
    </button>
  );
});

Button.displayName = 'Button';
export default Button;
