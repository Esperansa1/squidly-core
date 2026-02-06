import React from 'react';
import theme from '../../config/theme';
import { useIsMobile } from '../../hooks/useMediaQuery';

/**
 * HeroBanner - Responsive hero image banner at top of menu
 * Displays branch banner image when available, gradient fallback otherwise
 */
export default function HeroBanner({ imageUrl }) {
  const isMobile = useIsMobile();

  return (
    <div
      style={{
        width: '100%',
        minHeight: isMobile ? '100px' : '120px',
        height: isMobile ? '100px' : '15vh',
        maxHeight: isMobile ? '120px' : '180px',
        backgroundColor: '#1F2937',
        borderRadius: theme.borderRadius.xl,
        overflow: 'hidden',
        position: 'relative',
        flexShrink: 0,
      }}
    >
      {imageUrl ? (
        <img
          src={imageUrl}
          alt="באנר"
          style={{
            width: '100%',
            height: '100%',
            objectFit: 'cover',
          }}
        />
      ) : (
        <div
          style={{
            width: '100%',
            height: '100%',
            background: 'linear-gradient(135deg, #DC2626 0%, #EA580C 100%)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            color: 'white',
            fontSize: '2rem',
            fontWeight: 'bold',
          }}
        >
          תמונת באנר
        </div>
      )}
    </div>
  );
}
