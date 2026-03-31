---
phase: theme-customization
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/api/AdminApiBootstrap.php
  - includes/api/PublicApiBootstrap.php
  - admin-app/src/services/api.js
  - admin-app/src/main.jsx
  - customer-app/src/main.jsx
  - admin-app/src/components/Settings.jsx
  - admin-app/src/components/settings/AppearanceSection.jsx
autonomous: false
requirements:
  - THEME-01
  - THEME-02
  - THEME-03

must_haves:
  truths:
    - "Admin can open Settings > מראה tab and see a form with restaurant name, primary color, secondary color, accent color, and logo upload fields"
    - "Admin can change primary color using a native color picker and save — the admin app UI updates immediately without page reload"
    - "Admin can upload a logo image and see a preview in the form after upload"
    - "Customer app reads branding from /public/config at startup and applies primary_color and secondary_color as CSS variables"
    - "Saved branding values persist across page refresh (stored in squidly_branding WP option)"
  artifacts:
    - path: "includes/api/AdminApiBootstrap.php"
      provides: "Settings GET/PUT endpoints + logo upload endpoint + dynamic theme in admin/config"
      exports: ["GET squidly/v1/settings", "PUT squidly/v1/settings", "POST squidly/v1/settings/logo"]
    - path: "includes/api/PublicApiBootstrap.php"
      provides: "Dynamic theme block in public/config"
      contains: "get_option('squidly_branding')"
    - path: "admin-app/src/services/api.js"
      provides: "getSettings(), updateSettings(data), uploadLogo(file) API methods"
    - path: "admin-app/src/components/settings/AppearanceSection.jsx"
      provides: "Appearance settings form component"
    - path: "admin-app/src/components/Settings.jsx"
      provides: "מראה tab wired to AppearanceSection"
  key_links:
    - from: "admin-app/src/components/settings/AppearanceSection.jsx"
      to: "squidly/v1/settings"
      via: "api.getSettings() on mount, api.updateSettings(data) on save"
      pattern: "api\\.updateSettings"
    - from: "admin-app/src/components/settings/AppearanceSection.jsx"
      to: "squidly/v1/settings/logo"
      via: "api.uploadLogo(file) on file input change"
      pattern: "api\\.uploadLogo"
    - from: "customer-app/src/main.jsx"
      to: "publicApi.config.theme"
      via: "publicApi.init() then generateCSSVariables + applyTheme"
      pattern: "generateCSSVariables"
    - from: "admin-app/src/main.jsx"
      to: "api.getTheme()"
      via: "api.init() result.theme passed to applyTheme"
      pattern: "applyTheme.*getTheme"
---

<objective>
Allow restaurant admins to customize theme colors, restaurant name, and logo through the Settings > מראה tab. The customer-facing app reads and applies these settings at startup.

Purpose: Restaurants need to brand the customer ordering experience with their own colors and identity without touching code.
Output: Three REST endpoints, an AppearanceSection form component, Settings tab wiring, and theme-application fixes in both app entry points.
</objective>

<execution_context>
Follow all patterns in CLAUDE.md: functional components, TailwindCSS, Hebrew labels, use pre-created UI atoms (FormField, Button, Card, Toast), no TypeScript, build both apps after changes.
</execution_context>

<context>
@.planning/phase-theme-customization/RESEARCH.md

Key facts extracted from source files:

AdminApiBootstrap.php:
- `get_admin_config()` returns hardcoded theme block at lines 131-137 (primary_color, secondary_color, success_color, danger_color)
- `admin_permissions_check()` at line 171 uses `current_user_can('manage_options')` — reuse for settings endpoints
- `register_routes()` at line 21 is where new routes must be registered

PublicApiBootstrap.php:
- `get_public_config()` returns hardcoded theme block at lines 104-110
- Pattern: uses `get_option('squidly_currency', 'ILS')` — follow same pattern for branding

admin-app/src/services/api.js:
- `uploadAvatar()` at line 416 is the exact pattern for `uploadLogo()`: no Content-Type header, raw FormData body, uses `this.nonce` header directly
- `getTheme()` at line 434 already exists: `return this.config?.theme || DEFAULT_THEME`
- New methods go in the "UTILITY METHODS" section after line 428

admin-app/src/config/theme.js:
- `generateCSSVariables(theme)` at line 70 maps theme keys to `--theme-{key}` CSS vars
- `applyTheme` is defined locally in admin-app/src/main.jsx at lines 12-19 — it calls `generateCSSVariables` then sets properties on `document.documentElement`
- Customer app has NO equivalent utility — must inline the same logic

admin-app/src/main.jsx:
- `applyTheme(DEFAULT_THEME)` called at line 34 before app renders
- `api.init()` is NOT called here — theme update after init must be added
- App renders synchronously after DOM ready — theme re-apply needs to happen after `api.init()` resolves

customer-app/src/main.jsx:
- No `publicApi.init()` call — theme wiring is entirely absent
- `setBackgroundImage()` sets a single CSS var from `window.wpConfig.assetsUrl`
- Pattern to follow: same `generateCSSVariables` + `applyTheme` inline logic

admin-app/src/components/Settings.jsx:
- Tabs array at line 32: `['הפרופיל שלי', 'ניהול משתמשים']` — add `'מראה'`
- `activeTab` state drives which section renders (lines 45-53) — add a new `{activeTab === 'מראה' && <AppearanceSection />}` block
- Loading state is only for `currentUser` — AppearanceSection manages its own loading

admin-app/src/components/settings/ProfileSection.jsx (UI reference pattern):
- Import: `import { Card, FormField, Button, Toast } from '../ui/index'`
- Toast pattern: `displayToast(message, type)` sets state, `{showToast && <Toast ... />}` at bottom
- Form: `onSubmit={handleSubmit}`, `handleChange` updates formData by name, errors per field
- Save button: `<Button type="submit" variant="primary" loading={saving}>שמור שינויים</Button>`
</context>

<tasks>

<task type="auto">
  <name>Task 1: Backend — settings endpoints + dynamic theme in config responses</name>
  <files>includes/api/AdminApiBootstrap.php, includes/api/PublicApiBootstrap.php</files>
  <action>
**AdminApiBootstrap.php — three changes:**

1. In `register_routes()`, after the existing `/admin/config` route block (after line 71), add three new routes:

```php
// Settings GET endpoint
register_rest_route('squidly/v1', '/settings', [
    'methods'             => \WP_REST_Server::READABLE,
    'callback'            => [self::class, 'get_settings'],
    'permission_callback' => [self::class, 'admin_permissions_check'],
]);

// Settings PUT endpoint
register_rest_route('squidly/v1', '/settings', [
    'methods'             => \WP_REST_Server::EDITABLE,
    'callback'            => [self::class, 'update_settings'],
    'permission_callback' => [self::class, 'admin_permissions_check'],
]);

// Logo upload endpoint
register_rest_route('squidly/v1', '/settings/logo', [
    'methods'             => \WP_REST_Server::CREATABLE,
    'callback'            => [self::class, 'upload_logo'],
    'permission_callback' => [self::class, 'admin_permissions_check'],
]);
```

2. Replace the hardcoded `'theme'` block in `get_admin_config()` (lines 133-137) with a dynamic read:

```php
'theme' => self::get_branding_theme(),
```

3. Add the three new static methods and one private helper before the closing `}` of the class:

```php
/**
 * Get branding as a theme array (merges saved values with defaults)
 */
private static function get_branding_theme(): array
{
    $saved = get_option('squidly_branding', []);
    return [
        'primary_color'   => $saved['primary_color']   ?? '#D12525',
        'secondary_color' => $saved['secondary_color'] ?? '#F2F2F2',
        'accent_color'    => $saved['accent_color']    ?? '#D12525',
        'success_color'   => '#10B981',
        'danger_color'    => '#EF4444',
    ];
}

/**
 * Get branding settings
 */
public static function get_settings($request)
{
    $saved = get_option('squidly_branding', []);
    return new \WP_REST_Response([
        'restaurant_name' => $saved['restaurant_name'] ?? get_bloginfo('name'),
        'logo_url'        => $saved['logo_url']        ?? '',
        'primary_color'   => $saved['primary_color']   ?? '#D12525',
        'secondary_color' => $saved['secondary_color'] ?? '#F2F2F2',
        'accent_color'    => $saved['accent_color']    ?? '#D12525',
    ], 200);
}

/**
 * Update branding settings
 */
public static function update_settings($request)
{
    $params  = $request->get_json_params();
    $current = get_option('squidly_branding', []);

    $allowed_keys = ['restaurant_name', 'logo_url', 'primary_color', 'secondary_color', 'accent_color'];
    foreach ($allowed_keys as $key) {
        if (isset($params[$key])) {
            $current[$key] = sanitize_text_field($params[$key]);
        }
    }

    update_option('squidly_branding', $current);

    return new \WP_REST_Response(['success' => true, 'data' => $current], 200);
}

/**
 * Upload restaurant logo
 */
public static function upload_logo($request)
{
    if (!function_exists('media_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    if (empty($_FILES['logo'])) {
        return new \WP_REST_Response(['error' => 'No file uploaded'], 400);
    }

    $attachment_id = media_handle_upload('logo', 0);

    if (is_wp_error($attachment_id)) {
        return new \WP_REST_Response(['error' => $attachment_id->get_error_message()], 500);
    }

    $logo_url = wp_get_attachment_url($attachment_id);

    // Persist in branding option
    $current              = get_option('squidly_branding', []);
    $current['logo_url']  = esc_url_raw($logo_url);
    update_option('squidly_branding', $current);

    return new \WP_REST_Response(['logo_url' => $logo_url], 200);
}
```

**PublicApiBootstrap.php — one change:**

Replace the hardcoded `'theme'` block in `get_public_config()` (lines 104-110) with a dynamic read. Replace those seven lines with:

```php
'theme' => (function () {
    $saved = get_option('squidly_branding', []);
    return [
        'primary_color'   => $saved['primary_color']   ?? '#D12525',
        'secondary_color' => $saved['secondary_color'] ?? '#F2F2F2',
        'accent_color'    => $saved['accent_color']    ?? '#D12525',
        'success_color'   => '#10B981',
        'warning_color'   => '#F59E0B',
        'danger_color'    => '#EF4444',
        'restaurant_name' => $saved['restaurant_name'] ?? get_bloginfo('name'),
        'logo_url'        => $saved['logo_url']        ?? '',
    ];
})(),
```
  </action>
  <verify>
    <automated>curl -s "http://squidly.local/wp-json/squidly/v1/public/config" | python3 -c "import sys,json; d=json.load(sys.stdin); assert 'theme' in d; print('OK:', d['theme'])"</automated>
  </verify>
  <done>
    - GET /squidly/v1/public/config returns theme object (no hardcoded #D12525 literal in PHP anymore)
    - GET /squidly/v1/settings returns {restaurant_name, logo_url, primary_color, secondary_color, accent_color}
    - PUT /squidly/v1/settings with JSON body saves to squidly_branding option and returns {success: true}
    - POST /squidly/v1/settings/logo accepts multipart file and returns {logo_url}
    - Unauthorized requests to /settings return 403
  </done>
</task>

<task type="auto">
  <name>Task 2: API service methods — getSettings, updateSettings, uploadLogo</name>
  <files>admin-app/src/services/api.js</files>
  <action>
In `admin-app/src/services/api.js`, add three new methods in the `// ===== UTILITY METHODS =====` section (after line 428, before `getConfig()`):

```javascript
// ===== SETTINGS API =====

async getSettings() {
  return await this.fetch('settings');
}

async updateSettings(data) {
  return await this.fetch('settings', {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

async uploadLogo(file) {
  const formData = new FormData();
  formData.append('logo', file);
  return await this.fetch('settings/logo', {
    method: 'POST',
    headers: { 'X-WP-Nonce': this.nonce },
    body: formData,
  });
}
```

Note: `uploadLogo` deliberately omits `Content-Type` header (same pattern as `uploadAvatar` at line 416) so the browser sets the correct multipart boundary automatically.
  </action>
  <verify>
    <automated>cd "C:/Users/oresp/Local Sites/squidly/app/public/wp-content/plugins/squidly-core/admin-app" && npm run lint 2>&1 | tail -5</automated>
  </verify>
  <done>
    - api.getSettings(), api.updateSettings(data), api.uploadLogo(file) all exist and follow the same patterns as adjacent methods
    - No linting errors introduced
  </done>
</task>

<task type="auto">
  <name>Task 3: AppearanceSection component + Settings tab + theme re-apply wiring</name>
  <files>
    admin-app/src/components/settings/AppearanceSection.jsx,
    admin-app/src/components/Settings.jsx,
    admin-app/src/main.jsx,
    customer-app/src/main.jsx
  </files>
  <action>
**A. Create `admin-app/src/components/settings/AppearanceSection.jsx`**

Model after ProfileSection.jsx. The component:
- Fetches current settings via `api.getSettings()` on mount
- Shows a form with five fields: restaurant name (text), primary color (color), secondary color (color), accent color (color), logo (file upload with preview)
- On save, calls `api.updateSettings(formData)` then immediately calls `applyTheme` with the new colors so the admin sees the change live (no reload needed)
- On logo file pick, calls `api.uploadLogo(file)` and updates `logo_url` in state for the preview
- Uses `Card`, `FormField`, `Button`, `Toast` from `'../ui/index'`
- All labels in Hebrew, `dir="rtl"` inherited from parent

```jsx
import { useState, useEffect, useRef } from 'react';
import { PhotoIcon } from '@heroicons/react/24/outline';
import { Card, FormField, Button, Toast } from '../ui/index';
import { DEFAULT_THEME, generateCSSVariables } from '../../config/theme.js';
import api from '../../services/api.js';

const applyTheme = (theme) => {
  const cssVars = generateCSSVariables({ ...DEFAULT_THEME, ...theme });
  const root = document.documentElement;
  Object.entries(cssVars).forEach(([prop, val]) => root.style.setProperty(prop, val));
};

const AppearanceSection = () => {
  const theme = DEFAULT_THEME;
  const [formData, setFormData] = useState({
    restaurant_name: '',
    primary_color: '#D12525',
    secondary_color: '#F2F2F2',
    accent_color: '#D12525',
    logo_url: '',
  });
  const [loading, setLoading]           = useState(true);
  const [saving, setSaving]             = useState(false);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const [showToast, setShowToast]       = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType]       = useState('success');
  const fileInputRef = useRef(null);

  useEffect(() => {
    api.getSettings()
      .then((data) => setFormData(data))
      .catch(() => displayToast('שגיאה בטעינת הגדרות המראה', 'error'))
      .finally(() => setLoading(false));
  }, []);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      setSaving(true);
      await api.updateSettings({
        restaurant_name: formData.restaurant_name,
        primary_color:   formData.primary_color,
        secondary_color: formData.secondary_color,
        accent_color:    formData.accent_color,
        logo_url:        formData.logo_url,
      });
      applyTheme(formData);
      displayToast('הגדרות המראה נשמרו בהצלחה');
    } catch (error) {
      console.error('Error saving appearance settings:', error);
      displayToast('שגיאה בשמירת הגדרות', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleLogoClick = () => fileInputRef.current?.click();

  const handleLogoUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      displayToast('יש להעלות קובץ תמונה בלבד', 'error');
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      displayToast('גודל הקובץ לא יכול לעבור 2MB', 'error');
      return;
    }
    try {
      setUploadingLogo(true);
      const result = await api.uploadLogo(file);
      setFormData((prev) => ({ ...prev, logo_url: result.logo_url }));
      displayToast('הלוגו עודכן בהצלחה');
    } catch (error) {
      console.error('Error uploading logo:', error);
      displayToast('שגיאה בהעלאת הלוגו', 'error');
    } finally {
      setUploadingLogo(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const displayToast = (message, type = 'success') => {
    setToastMessage(message);
    setToastType(type);
    setShowToast(true);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-gray-500">טוען...</div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto mt-6 space-y-4">
      <Card className="p-6">
        <h2 className="text-xl font-semibold mb-4" style={{ color: theme.text_primary }}>
          מראה המסעדה
        </h2>
        <form onSubmit={handleSubmit} className="space-y-4">

          {/* Restaurant Name */}
          <FormField
            label="שם המסעדה"
            fieldType="input"
            type="text"
            name="restaurant_name"
            value={formData.restaurant_name}
            onChange={handleChange}
            fullWidth
          />

          {/* Color pickers row */}
          <div className="grid grid-cols-3 gap-3">
            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
                צבע ראשי
              </label>
              <input
                type="color"
                name="primary_color"
                value={formData.primary_color}
                onChange={handleChange}
                className="h-10 w-full rounded border border-gray-300 cursor-pointer p-0.5"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
                צבע משני
              </label>
              <input
                type="color"
                name="secondary_color"
                value={formData.secondary_color}
                onChange={handleChange}
                className="h-10 w-full rounded border border-gray-300 cursor-pointer p-0.5"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
                צבע הדגשה
              </label>
              <input
                type="color"
                name="accent_color"
                value={formData.accent_color}
                onChange={handleChange}
                className="h-10 w-full rounded border border-gray-300 cursor-pointer p-0.5"
              />
            </div>
          </div>

          {/* Logo upload */}
          <div className="flex flex-col gap-2">
            <label className="text-sm font-medium" style={{ color: theme.text_primary }}>
              לוגו המסעדה
            </label>
            <div className="flex items-center gap-4">
              <button
                type="button"
                onClick={handleLogoClick}
                disabled={uploadingLogo}
                className="relative w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center hover:border-gray-400 transition-colors overflow-hidden"
              >
                {formData.logo_url ? (
                  <img
                    src={formData.logo_url}
                    alt="לוגו"
                    className="w-full h-full object-cover"
                  />
                ) : (
                  <PhotoIcon className="w-8 h-8 text-gray-400" />
                )}
                {uploadingLogo && (
                  <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-white" />
                  </div>
                )}
              </button>
              <div className="text-sm text-gray-500">
                <p>לחץ להעלאת לוגו</p>
                <p>PNG, JPG עד 2MB</p>
              </div>
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={handleLogoUpload}
              className="hidden"
            />
          </div>

          <div className="flex justify-end pt-2">
            <Button type="submit" variant="primary" loading={saving} disabled={saving}>
              שמור שינויים
            </Button>
          </div>
        </form>
      </Card>

      {showToast && (
        <Toast message={toastMessage} type={toastType} onClose={() => setShowToast(false)} />
      )}
    </div>
  );
};

export default AppearanceSection;
```

**B. Wire AppearanceSection into Settings.jsx**

In `admin-app/src/components/Settings.jsx`:

1. Add import after existing imports:
```javascript
import AppearanceSection from './settings/AppearanceSection.jsx';
```

2. Change the `tabs` array from:
```javascript
tabs={['הפרופיל שלי', 'ניהול משתמשים']}
```
to:
```javascript
tabs={['הפרופיל שלי', 'ניהול משתמשים', 'מראה']}
```

3. Inside the conditional render block (after the `UserManagement` block), add:
```jsx
{activeTab === 'מראה' && (
  <AppearanceSection />
)}
```

**C. Re-apply theme after api.init() in admin main.jsx**

In `admin-app/src/main.jsx`, the `initializeApp` function currently renders the app synchronously. The admin app must re-apply the server-saved theme after `api.init()` resolves. Add an import for `api` and call `api.init()` then `applyTheme` before mounting.

Import `api` at the top of the file (after existing imports):
```javascript
import api from './services/api.js';
```

In the `initializeApp` function, BEFORE the `createRoot` / `root.render` call, add an async init that applies the server theme then mounts the app. Replace the existing mount block:

```javascript
// Apply server-saved theme, then mount (non-blocking: falls back to DEFAULT_THEME on error)
api.init()
  .then(() => {
    applyTheme(api.getTheme());
  })
  .catch(() => {
    // DEFAULT_THEME already applied at line 34 — nothing to do
  })
  .finally(() => {
    const container =
      document.getElementById('squidly-admin-root') ||
      document.getElementById('squidly-admin');
    if (container) {
      const root = createRoot(container);
      root.render(<App />);
    }
  });
```

Remove the old synchronous mount block (the `let container = ...` block at lines 44-52) since it is replaced by the `.finally()` above. The `applyTheme(DEFAULT_THEME)` call at line 34 stays — it keeps the UI unstyled-flash-free.

**D. Wire theme application in customer-app main.jsx**

`customer-app/src/main.jsx` has no config utility imports. Add theme application after the background image is set.

Add these lines after the existing `setBackgroundImage()` call (line 19), before the `ReactDOM.createRoot(...)` line:

```javascript
import publicApi from './services/publicApi.js';
import { DEFAULT_THEME, generateCSSVariables } from './config/theme.js';
```

Wait — this is a module, so imports must be at the top. Move the imports to the top of the file (after existing imports). Then add the theme apply logic after `setBackgroundImage()`:

Final shape of `customer-app/src/main.jsx`:

```jsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App.jsx';
import './index.css';
import publicApi from './services/publicApi.js';
import { DEFAULT_THEME, generateCSSVariables } from './config/theme.js';

// Apply theme CSS variables to document root
const applyTheme = (theme) => {
  const merged = { ...DEFAULT_THEME, ...theme };
  const cssVars = generateCSSVariables(merged);
  const root = document.documentElement;
  Object.entries(cssVars).forEach(([prop, val]) => root.style.setProperty(prop, val));
};

// Set background image from WordPress config
const setBackgroundImage = () => {
  const config = window.wpConfig;
  const assetsUrl = config?.assetsUrl || config?.pluginUrl;
  if (assetsUrl) {
    const backgroundUrl = `${assetsUrl}customer-app/assets/background.png`;
    document.documentElement.style.setProperty('--background-image-url', `url('${backgroundUrl}')`);
  }
};

// Apply defaults immediately to prevent flash
applyTheme({});
setBackgroundImage();

// Fetch server-saved branding and re-apply (non-blocking)
publicApi.init()
  .then((config) => {
    if (config?.theme) applyTheme(config.theme);
  })
  .catch(() => {
    // DEFAULT_THEME already applied above — no action needed
  });

ReactDOM.createRoot(document.getElementById('squidly-customer-app')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
```

Note: `customer-app/src/config/theme.js` EXISTS but uses a different schema (nested `theme.colors.primary` object, no `DEFAULT_THEME` or `generateCSSVariables` exports). Do NOT import from it as-is — it will cause a build error. Instead, add the following two exports at the top of that file (copying the flat structure from admin-app):

```javascript
// Flat theme defaults used for CSS variable generation
export const DEFAULT_THEME = {
  primary_color:   '#D12525',
  secondary_color: '#F2F2F2',
  accent_color:    '#D12525',
};

// Generates { '--theme-primary_color': '#D12525', ... } for document.documentElement
export const generateCSSVariables = (theme) => {
  return Object.fromEntries(
    Object.entries(theme).map(([key, value]) => [`--theme-${key}`, value])
  );
};
```

Add these exports at the top of `customer-app/src/config/theme.js` before its existing `export default theme` line, leaving the rest of the file unchanged.
  </action>
  <verify>
    <automated>
cd "C:/Users/oresp/Local Sites/squidly/app/public/wp-content/plugins/squidly-core/admin-app" && npm run build 2>&1 | tail -10 && cd ../customer-app && npm run build 2>&1 | tail -10
    </automated>
  </verify>
  <done>
    - Both `npm run build` commands exit 0 with no errors
    - Settings.jsx shows three tabs: הפרופיל שלי, ניהול משתמשים, מראה
    - Clicking מראה tab shows AppearanceSection form with restaurant name field, three color pickers, and logo upload area
    - admin-app/src/main.jsx calls api.init() before mounting and applies the server theme on success
    - customer-app/src/main.jsx applies DEFAULT_THEME immediately and re-applies server theme after publicApi.init() resolves
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>
    - Three REST endpoints: GET/PUT /squidly/v1/settings, POST /squidly/v1/settings/logo
    - Dynamic theme in both /admin/config and /public/config (reads from squidly_branding WP option)
    - AppearanceSection form component (restaurant name, 3 color pickers, logo upload)
    - Settings page מראה tab wired to AppearanceSection
    - Admin app re-applies server theme on startup
    - Customer app applies server theme on startup
    Both apps rebuilt.
  </what-built>
  <how-to-verify>
    1. Open the WordPress admin and navigate to the Squidly admin panel
    2. Go to Settings > מראה tab — confirm you see the form with restaurant name, primary/secondary/accent color pickers, and logo upload area
    3. Change the primary color to something visibly different (e.g. blue #1E40AF) and click "שמור שינויים"
       - Expected: a success toast appears and the admin app's primary-colored elements (buttons, borders) update immediately without page reload
    4. Refresh the admin page — Expected: the blue primary color is still applied (persisted in WP option)
    5. Open the customer app in a new tab — Expected: the customer app's primary-colored buttons/elements also reflect the blue color
    6. Go back to Settings > מראה, change primary color back to the original red (#D12525), save, and confirm both apps update
    7. Test logo upload: click the logo area, pick any PNG/JPG under 2MB — Expected: a preview of the image appears in the form, and a success toast shows
    8. Verify unauthorized access: in browser console run:
       `fetch('/wp-json/squidly/v1/settings', {method:'PUT', body: JSON.stringify({primary_color:'#000'}), headers:{'Content-Type':'application/json'}}).then(r => console.log(r.status))`
       Expected: 403 (not logged-in user gets forbidden)
  </how-to-verify>
  <resume-signal>Type "approved" if all steps pass, or describe any issue found</resume-signal>
</task>

</tasks>

<verification>
End-to-end verification:
1. `curl http://squidly.local/wp-json/squidly/v1/public/config | jq .theme` — returns object with primary_color/secondary_color read from DB, not hardcoded
2. Both `npm run build` commands succeed with exit code 0
3. Visual: admin settings מראה tab renders form, saves colors, applies them live
4. Visual: customer app reflects saved colors on next load
5. Security: unauthenticated PUT /settings returns 403
</verification>

<success_criteria>
- Admin can save restaurant name, primary/secondary/accent colors, and logo through Settings > מראה
- Changes to colors apply immediately in the admin app (no reload)
- Customer app reads and applies saved theme colors on startup
- Logo uploads are stored in the WordPress media library and the URL is persisted
- All settings survive page refresh (stored in squidly_branding WP option)
- Only users with manage_options capability can modify settings (others receive 403)
</success_criteria>

<output>
After completion, create `.planning/phase-theme-customization/theme-customization-01-SUMMARY.md` with:
- What was built (files changed, endpoints added)
- Any decisions made during implementation (e.g. whether customer-app/src/config/theme.js was copied or already existed)
- Known limitations or follow-up items
</output>
