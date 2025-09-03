# Squidly Admin App

A completely decoupled React admin interface for the Squidly restaurant management system. Users will have no indication that this runs on WordPress - it's a standalone admin experience that communicates via REST API.

## 🎯 Key Features

### ⚡ **Completely Decoupled**
- No WordPress admin UI dependencies  
- Standalone React application
- Users see only the restaurant management interface
- Clean, modern admin experience

### 🌍 **RTL Hebrew Support**
- Full Right-to-Left text support
- Hebrew interface language
- RTL-aware layouts and components  
- Proper text alignment and spacing

### 🎨 **Configurable Theme**
- Primary color: `#D12525` (Red)
- Secondary color: `#F2F2F2` (Light gray)  
- Easily changeable via CSS custom properties
- Consistent theme across all components

### 🔗 **API-Driven Architecture**
- Communicates exclusively via REST API
- No direct WordPress dependencies in frontend
- Clean separation of concerns
- Scalable and maintainable

## 📁 Project Structure

```
admin-app/
├── src/
│   ├── components/          # React components
│   │   └── MenuManagement.jsx
│   ├── services/           # API communication
│   │   └── api.js
│   ├── styles/            # CSS and styling
│   │   └── admin.css
│   ├── App.jsx            # Main app component
│   └── main.jsx           # Entry point
├── public/
│   └── index.html         # Standalone HTML template
├── dist/                  # Built files (generated)
├── package.json
├── vite.config.js
├── tailwind.config.js
└── README.md
```

## 🚀 Getting Started

### Prerequisites
```bash
# Navigate to admin app directory
cd admin-app

# Install dependencies
npm install
```

### Development
```bash
# Start development server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

### Access the Admin Interface

#### Production Access:
Navigate to: `/wp-content/plugins/squidly-core/admin.php`

#### Development Access:
- Start dev server: `npm run dev`
- Open: `http://localhost:3000`
- API calls will go to your WordPress installation

## 🔧 API Integration

### REST API Endpoints

The admin app communicates with these endpoints:

```
GET  /wp-json/squidly/v1/auth/check           # Authentication check
GET  /wp-json/squidly/v1/admin/config         # Admin configuration
GET  /wp-json/squidly/v1/branches             # Store branches
GET  /wp-json/squidly/v1/product-groups       # Product groups
POST /wp-json/squidly/v1/product-groups       # Create product group
GET  /wp-json/squidly/v1/ingredient-groups    # Ingredient groups
POST /wp-json/squidly/v1/ingredient-groups    # Create ingredient group
```

### API Service Usage

```javascript
import api from './services/api.js';

// Initialize API
await api.init();

// Get data
const branches = await api.getBranches();
const productGroups = await api.getProductGroups();

// Create/Update/Delete
await api.createProductGroup(data);
await api.updateProductGroup(id, data);
await api.deleteProductGroup(id);
```

## 🎨 Theme Customization

### CSS Variables
Colors are defined as CSS custom properties for easy customization:

```css
:root {
  --squidly-primary: #D12525;      /* Main brand color */
  --squidly-secondary: #F2F2F2;    /* Background color */
  --squidly-success: #10B981;      /* Success states */
  --squidly-danger: #EF4444;       /* Error/delete states */
}
```

### Tailwind Configuration
Theme colors are also available in Tailwind:

```javascript
// tailwind.config.js
theme: {
  extend: {
    colors: {
      primary: {
        DEFAULT: '#D12525',
        // ... shades
      }
    }
  }
}
```

## 🔐 Security & Authentication

- **WordPress Session**: Uses WordPress user sessions
- **Nonce Verification**: All API requests include WordPress nonces  
- **Permission Checks**: Requires `manage_options` capability
- **CORS Support**: Configured for development environments

## 📱 Components

### MenuManagement Component

The main interface includes:

- **Branch Selector**: Dropdown for filtering by store branch
- **Tab Navigation**: Switch between Groups, Ingredients, Products  
- **Product Groups**: Manage product group categories
- **Ingredient Groups**: Manage ingredient group categories
- **Action Buttons**: Add, Edit, Delete with confirmation
- **Status Indicators**: Visual active/inactive status
- **Selection**: Radio buttons for group selection

### Features:
- Real-time API communication
- Loading states and error handling
- RTL layout and Hebrew text
- Responsive design
- Accessible interactions

## 🛠 Development Notes

### Building for Production

```bash
# Build the admin app
cd admin-app
npm run build

# Files are generated in dist/
# - main.js (JavaScript bundle)
# - main.css (Compiled styles)
```

### File Serving

The `admin.php` file:
- Checks for built assets in `dist/`
- Serves the standalone admin interface
- Handles authentication and configuration
- Provides fallback for development mode

### Error Handling

- Global error boundaries
- API error handling with user feedback
- Loading timeouts and retry mechanisms  
- Graceful degradation

## 🌐 Browser Support

- Modern browsers with ES6+ support
- RTL text rendering support
- CSS Grid and Flexbox support
- JavaScript fetch API

## 📚 Usage Patterns

### Adding New Screens

1. Create component in `src/components/`
2. Add API methods in `src/services/api.js`
3. Create corresponding REST controller in backend
4. Register routes in `AdminApiBootstrap.php`

### Theme Changes

1. Update CSS custom properties in `src/styles/admin.css`
2. Update Tailwind config in `tailwind.config.js`
3. Rebuild with `npm run build`

### API Extensions

1. Create new REST controller in `includes/domains/*/rest/`
2. Register in `AdminApiBootstrap.php`
3. Add methods to `api.js` service
4. Use in React components

## 🚀 Deployment

The admin app is completely self-contained:
- No WordPress admin dependencies
- Standalone HTML/JS/CSS files
- API-only backend communication
- Can be served from any domain (with CORS)

Perfect for creating a professional restaurant management interface that users will never know runs on WordPress!