import React from 'react';
import theme from '../../config/theme';

/**
 * HeroBanner - Large hero image banner at top of menu
 * Default image with food photography
 */
export default function HeroBanner() {
  return (
    <div
      style={{
        width: '100%',
        minHeight: '120px',
        height: '15vh',
        maxHeight: '180px',
        backgroundColor: '#1F2937',
        borderRadius: theme.borderRadius.xl,
        overflow: 'hidden',
        position: 'relative',
        flexShrink: 0,
      }}
    >
      {/* Placeholder gradient until actual image is provided */}
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

      {/* TODO: Replace with actual image when provided */}
      {/* <img
        src="/path/to/hero-image.jpg"
        alt="תפריט"
        style={{
          width: '100%',
          height: '100%',
          objectFit: 'cover',
        }}
      /> */}
    </div>
  );
}
