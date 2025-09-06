// Dynamic font loader using WordPress plugin URL
export function loadLiaDiplomatFonts() {
  if (!window.wpConfig || !window.wpConfig.pluginUrl) {
    console.error('WordPress config not available, cannot load fonts');
    return;
  }

  const baseUrl = window.wpConfig.pluginUrl + 'fonts/';
  
  // Create font face declarations dynamically
  const fontFaces = [
    {
      weight: 200,
      file: 'LiaDiplomat-ExtraLight.woff2'
    },
    {
      weight: 400,
      file: 'LiaDiplomat-Regular.woff2'
    },
    {
      weight: 600,
      file: 'LiaDiplomat-SemiBold.woff2'
    },
    {
      weight: 800,
      file: 'LiaDiplomat-ExtraBold.woff2'
    }
  ];

  // Create and inject font face CSS
  const style = document.createElement('style');
  style.textContent = fontFaces.map(font => `
    @font-face {
      font-family: 'LiaDiplomat';
      src: url('${baseUrl}${font.file}') format('woff2');
      font-weight: ${font.weight};
      font-style: normal;
      font-display: swap;
    }
  `).join('\n');

  document.head.appendChild(style);
  
}