# Squidly Customer App

Customer-facing ordering interface for the Squidly restaurant management system.

## Tech Stack

- **React 18** - Component framework
- **Vite** - Build tool and dev server
- **TailwindCSS** - Utility-first CSS
- **Heroicons** - Icon library
- **Shared UI** - Reusable components from `../shared-ui/`

## Development

### Prerequisites

- Node.js 16+ and npm
- Dependencies installed (`npm install`)

### Development Server

```bash
# Start dev server on port 3001
npm run dev
```

Visit `http://localhost:3001` to view the app.

### Build for Production

```bash
# Build static files to dist/
npm run build
```

The built files will be in `dist/assets/`:
- `main-[hash].js` - JavaScript bundle
- `main-[hash].css` - CSS bundle

### Preview Production Build

```bash
npm run preview
```

### Linting

```bash
npm run lint
```

## Project Structure

```
customer-app/
├── src/
│   ├── components/
│   │   ├── products/        # Product browsing components
│   │   ├── cart/            # Shopping cart components
│   │   ├── checkout/        # Multi-step checkout
│   │   ├── branches/        # Branch selection
│   │   └── orders/          # Order tracking
│   ├── contexts/
│   │   ├── CartContext.jsx
│   │   ├── BranchContext.jsx
│   │   └── OrderContext.jsx
│   ├── hooks/               # Custom hooks
│   ├── services/
│   │   └── publicApi.js     # Public API service
│   ├── config/              # Configuration
│   ├── App.jsx              # Main component
│   ├── main.jsx             # Entry point
│   └── index.css            # Global styles
├── dist/                    # Production build output
├── package.json
├── vite.config.js
└── tailwind.config.js
```

## Shared UI Components

The app uses shared components from `../shared-ui/`:

```javascript
import { Button, Card, Input } from '@shared-ui/atoms';
import { FormField, StatusBadge } from '@shared-ui/molecules';
import { Modal } from '@shared-ui/organisms';
import { useTheme } from '@shared-ui/hooks';
```

## API Service

All backend communication goes through `publicApi.js`:

```javascript
import publicApi from './services/publicApi';

// Initialize
await publicApi.init();

// Get products
const products = await publicApi.getProducts({ branch_id: 1 });

// Create order
const order = await publicApi.createOrder(orderData);
```

## WordPress Integration

In production, the customer app is:
1. Built with `npm run build`
2. Enqueued by WordPress at `/orders` route
3. Rendered inside a WordPress page template

## Features Roadmap

### Phase 1 (Current)
- [x] Basic app structure
- [x] Shared UI integration
- [ ] Branch selection
- [ ] Product catalog with tabs
- [ ] Product customization

### Phase 2
- [ ] Shopping cart
- [ ] Multi-step checkout
- [ ] WooCommerce payment integration

### Phase 3
- [ ] Order tracking
- [ ] Real-time status updates
- [ ] Mobile optimization

## Development Notes

- **Port**: Dev server runs on port 3001 (admin-app uses 3000)
- **Mobile-First**: Design for mobile, enhance for desktop
- **RTL Support**: Fully supports right-to-left languages
- **Theme**: Customizable via shared theme configuration
