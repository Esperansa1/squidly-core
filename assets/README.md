# Squidly Core Frontend Assets

This directory contains all frontend assets for the Squidly Core WordPress plugin, including React components with RTL (Right-to-Left) Hebrew support.

## Structure

```
assets/
├── src/                    # Source files
│   ├── components/         # React components
│   │   └── MenuManagement.jsx
│   ├── styles/            # CSS and Tailwind styles
│   │   └── main.css
│   └── menu-management.jsx # Entry point for Menu Management
├── dist/                  # Built files (generated)
│   ├── main.css
│   └── menuManagement.js
└── README.md
```

## Features

### 🌍 RTL Support
- Full Right-to-Left text support for Hebrew
- RTL-aware layouts and components  
- Proper text alignment and spacing

### 🎨 Theme Configuration
- Primary color: `#D12525` (Red)
- Secondary color: `#F2F2F2` (Light gray)
- Easily configurable color scheme
- CSS custom properties for theme values

### ⚛️ React Components
- **MenuManagement**: Complete menu management interface
- Responsive design with Tailwind CSS
- Modern React 18 with hooks

## Development Setup

### Prerequisites
```bash
# Install dependencies
npm install
```

### Development Commands

```bash
# Build CSS (development with watch)
npm run build-css

# Build CSS (production, minified)  
npm run build-css-prod

# Build React components (development)
npm run dev

# Build React components (production)
npm run build

# Build everything (production)
node build.js
```

### WordPress Integration

The components automatically integrate with WordPress admin:

1. **Menu Management Page**: Available in WordPress admin under "ניהול תפריט"
2. **Auto-loading**: Components load when their container elements are present
3. **WordPress data**: Access to admin AJAX, nonces, and REST API

## Component Usage

### MenuManagement Component

The main Menu Management interface includes:

- **Branch Selector**: Dropdown to select store branches
- **Tab Navigation**: Switch between Groups, Ingredients, and Products
- **Product Groups Section**: Manage product group categories
- **Ingredient Groups Section**: Manage ingredient group categories
- **Action Buttons**: Add, Edit, Delete functionality
- **Status Indicators**: Visual active/inactive status display

### Theme Customization

Colors are defined in both CSS and JavaScript for consistency:

```css
:root {
  --primary-color: #D12525;
  --secondary-color: #F2F2F2;
}
```

```javascript
const COLORS = {
  primary: '#D12525',
  secondary: '#F2F2F2',
};
```

## File Specifications

### React Components
- **Language**: Modern JavaScript (ES6+)
- **Framework**: React 18
- **Styling**: Tailwind CSS with custom components
- **Build Tool**: Vite for fast development and building

### CSS
- **Framework**: Tailwind CSS 3.3+
- **Preprocessor**: PostCSS with autoprefixer
- **RTL Support**: Built-in direction utilities
- **Custom Components**: Themed component classes

## Browser Support

- Modern browsers with ES6+ support
- RTL text rendering support
- CSS Grid and Flexbox support

## Production Notes

- All assets are minified in production builds
- Source maps available for debugging
- WordPress admin integration handles loading and initialization
- Fallback loading indicators for better UX