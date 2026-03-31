# Phase: Theme/Branding Customization - Research

**Researched:** 2026-03-30
**Domain:** WordPress Options API, React CSS Variables, Media Library uploads, REST API design
**Confidence:** HIGH

---

## Summary

This phase adds admin-editable theme settings (restaurant name, logo, and three brand colors) that persist via WordPress Options API and are consumed at runtime by both the admin app and customer app. The infrastructure for this already exists in skeletal form: both config endpoints already return a hardcoded `theme` object, `admin-app/src/config/theme.js` already has `generateCSSVariables()` and `getThemeConfig()`, and `admin-app/src/main.jsx` already calls `applyTheme(DEFAULT_THEME)` on startup. The customer app has its own `theme.js` with a different structure (a nested object rather than flat key/value pairs) and no runtime CSS-variable application today.

The cleanest path is: (1) a single serialized WordPress option `squidly_branding` to store all settings, (2) a dedicated REST endpoint `squidly/v1/settings/branding` for read/write, (3) logo upload via the existing `media_handle_upload()` pattern already used in `AdminUserRestController.php`, (4) a native `<input type="color">` wrapped in the existing `FormField`/`Input` atom (no new library needed), and (5) the customer app calling `/public/config` on init, reading the returned `theme` and `branding` blocks, and applying CSS variables using a utility mirroring the admin app's `generateCSSVariables`.

**Primary recommendation:** Store all branding in a single serialized option `squidly_branding`. Expose it via a new `/settings/branding` REST endpoint (admin) and include it in the existing `/public/config` response (customer). Apply it as CSS custom properties at app startup in both apps.

---

## Project Constraints (from CLAUDE.md)

- **No TypeScript** — all frontend code is plain JavaScript/JSX
- **Always use pre-created UI components** — use `FormField`, `Button`, `Input`, `Divider`, `Modal` atoms; never raw `<input>`, `<button>`, etc.
- **TailwindCSS only** — no inline styles except CSS variable application via `element.style.setProperty()`, which is the established pattern (`admin-app/src/main.jsx` lines 12-19)
- **Build after every change** — `cd admin-app && npm run build` and `cd customer-app && npm run build`
- **Meta field prefix** — `_` prefix convention applies to post meta, not WordPress options; options use `squidly_` prefix
- **Commit each logical unit** — do not batch unrelated changes; push after every commit
- **Settings page already exists** at `admin-app/src/components/Settings.jsx` — add a new tab, do not replace it

---

## Q1: WordPress Options Storage

**Finding:** HIGH confidence.

Use a **single serialized option** `squidly_branding` containing all branding fields:

```php
// Shape stored in the DB:
[
  'restaurant_name' => 'My Restaurant',
  'logo_url'        => 'https://example.com/wp-content/uploads/logo.png',
  'logo_attachment_id' => 42,
  'primary_color'   => '#D12525',
  'secondary_color' => '#F2F2F2',
  'accent_color'    => '#10B981',
]
```

**Rationale:**
- All existing Squidly options (`squidly_currency`, `squidly_allow_guest_checkout`, etc.) in `PublicApiBootstrap.php` use the `squidly_` prefix — this follows that convention.
- A single serialized option means one DB read on every request vs. five separate `get_option()` calls. WordPress autoloads options by default; a single small option has negligible overhead.
- Avoids schema-drift risk: if you later add `font_family` or `favicon_url`, you add it to the array without a new DB column or option name.
- The alternative — five separate options — works but is harder to migrate atomically and produces more query overhead.

**Read pattern:**
```php
$branding = get_option('squidly_branding', []);
$defaults = [
    'restaurant_name' => get_bloginfo('name'),
    'logo_url'        => '',
    'logo_attachment_id' => 0,
    'primary_color'   => '#D12525',
    'secondary_color' => '#F2F2F2',
    'accent_color'    => '#10B981',
];
$branding = wp_parse_args($branding, $defaults);
```

**Write pattern:**
```php
update_option('squidly_branding', $validated_data);
```

**Sanitization required per field:**
| Field | Sanitizer |
|-------|-----------|
| `restaurant_name` | `sanitize_text_field()` |
| `logo_url` | `esc_url_raw()` |
| `logo_attachment_id` | `absint()` |
| `primary_color` | `sanitize_hex_color()` (WP core, validates `#RRGGBB`) |
| `secondary_color` | `sanitize_hex_color()` |
| `accent_color` | `sanitize_hex_color()` |

---

## Q2: Logo Upload

**Finding:** HIGH confidence.

**Use the existing `media_handle_upload()` pattern** already established in `AdminUserRestController.php` (lines 362–381). This is the correct WordPress approach for file uploads via REST API:

```php
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$attachment_id = media_handle_upload('logo', 0);

if (is_wp_error($attachment_id)) {
    return new WP_REST_Response(['error' => $attachment_id->get_error_message()], 400);
}

$logo_url = wp_get_attachment_url($attachment_id);
```

**Tradeoffs:**

| Approach | Pros | Cons |
|----------|------|------|
| `media_handle_upload()` (recommended) | Already used in codebase. Uploads go to WP Media Library. Can be reused as attachment. WP validates MIME type. | Requires `wp-admin` includes. Files persist in Media Library even if setting is cleared. |
| `wp.media` JS API | Native WP media picker; re-uses existing uploads | Requires WP admin JS scripts to be enqueued. Adds complexity. Overkill for a single logo. |
| Custom multipart endpoint | Full control | Hand-rolling validation, storage, MIME-checking — violates "Don't Hand-Roll" principle |

**Frontend file upload pattern** (already used in `ProfileSection.jsx`):
```javascript
const handleLogoUpload = async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  const formData = new FormData();
  formData.append('logo', file);
  // POST to /squidly/v1/settings/logo (no Content-Type header — browser sets multipart boundary)
  const result = await api.fetch('settings/logo', {
    method: 'POST',
    headers: { 'X-WP-Nonce': api.nonce },  // omit Content-Type for FormData
    body: formData,
  });
};
```

Note: `api.fetch()` already handles nonce headers. When using `FormData`, do **not** set `Content-Type: application/json` — let the browser set the multipart boundary automatically. The current `api.fetch()` always sets `Content-Type: application/json` in its defaults, so the logo upload must override headers the same way `uploadAvatar()` does (line 420–424 of `api.js`).

---

## Q3: REST API Design

**Finding:** HIGH confidence, based on reading both bootstrap files.

**Recommendation: Dedicated `/squidly/v1/settings/branding` endpoint, plus extend the existing config endpoints.**

**Why not extend `/admin/config`:**
- `get_admin_config()` in `AdminApiBootstrap.php` is a GET-only endpoint (line 67: `WP_REST_Server::READABLE`). Theme customization requires GET and PUT.
- Mixing config-read with settings-write in the same endpoint creates an overloaded callback with confusing semantics.
- The existing `/admin/config` already hardcodes theme values (lines 133–137). These should be replaced to read from `squidly_branding`, but the endpoint stays read-only.

**Recommended endpoint structure:**

```
# Admin (authenticated, manage_options capability)
GET  /squidly/v1/settings/branding          → Read current branding settings
PUT  /squidly/v1/settings/branding          → Update branding settings
POST /squidly/v1/settings/logo              → Upload logo (multipart)
```

**The existing config endpoints should be updated to read from `squidly_branding`:**
- `AdminApiBootstrap::get_admin_config()` — replace hardcoded theme values with `get_option('squidly_branding', $defaults)`
- `PublicApiBootstrap::get_public_config()` — same; also add a `branding` key with `restaurant_name` and `logo_url`

**This is the minimal surface area change** — no new routing infrastructure, just one new controller class and two small updates to existing callbacks.

**New controller:** `includes/api/controllers/SettingsRestController.php` following the same namespace pattern:

```php
namespace SquidlyCore\Api\Controllers; // OR plain class — existing pattern is mixed
$this->namespace = 'squidly/v1';
$this->rest_base = 'settings';
```

Register it in `AdminApiBootstrap::register_routes()`.

---

## Q4: Color Picker

**Finding:** HIGH confidence.

**No color picker library is installed.** `admin-app/package.json` dependencies are: `@heroicons/react`, `react`, `react-dom`, `recharts`. No `react-colorful`, `react-color`, or similar.

**Recommendation: Use `<input type="color">` wrapped in the existing `Input` atom.**

`<input type="color">` is supported in all modern browsers (Chrome 20+, Firefox 29+, Safari 12.1+, Edge 14+). It renders the OS native color picker, which is entirely adequate for an admin settings form.

The `Input` atom is at `admin-app/src/components/ui/atoms/Input.jsx`. Pass `type="color"` to it. The native color input returns a 6-character hex string (`#rrggbb`) which is exactly what `sanitize_hex_color()` expects on the PHP side.

```jsx
// Inside ThemeSection (new settings tab component)
<FormField
  label="צבע ראשי"
  fieldType="input"
  type="color"
  name="primary_color"
  value={formData.primary_color}
  onChange={handleChange}
  fullWidth
/>
```

**If the `Input` atom does not style `type="color"` well** (common with Tailwind's form reset), add a small wrapper class. Do not add a color-picker library for this use case — that would be an unnecessary dependency for a simple admin form.

**Alternatives considered and rejected:**

| Option | Reason rejected |
|--------|----------------|
| `react-colorful` (4.6KB) | No existing install; adds a dependency for something `<input type="color">` handles |
| `react-color` | Unmaintained as of 2023; large bundle |
| Custom hex text field | Poorer UX; requires hex validation in React |

---

## Q5 + Q6: Customer App Runtime Theme Loading

**Finding:** HIGH confidence.

### How the admin app currently applies theme

In `admin-app/src/main.jsx`:
1. `DEFAULT_THEME` is imported from `config/theme.js`
2. `generateCSSVariables(theme)` converts the flat object to `{ '--theme-primary-color': '#D12525', ... }` (replaces `_` with `-`, prepends `--theme-`)
3. These are applied to `document.documentElement` via `root.style.setProperty()` at startup (lines 12-19)
4. `applyTheme(DEFAULT_THEME)` is called synchronously at line 34 — before any API call
5. The admin API config (`/admin/config`) returns a `theme` object but it is **never used to update the applied theme** at runtime. `api.getTheme()` returns `this.config?.theme || DEFAULT_THEME` but nothing calls `applyTheme` again after `api.init()`.

**Gap:** The admin app applies defaults immediately but does not re-apply the API-fetched theme after init. This must be wired up.

### How the customer app currently loads theme

In `customer-app/src/main.jsx`:
- Only sets `--background-image-url` CSS variable (line 15)
- No call to `generateCSSVariables` or any CSS variable application
- `publicApi.init()` is called in `App.jsx`'s `AppContent` component (line 22), fetching `/public/config` which returns a `theme` block — but the returned theme values are **never applied to the DOM**

`customer-app/src/config/theme.js` uses a **different structure** than the admin app — it is a nested object (`theme.colors.primary`, `theme.spacing.xs`, etc.) not a flat key/value map. It has no `generateCSSVariables` utility.

### Recommended approach for customer app

1. Add a `generateCSSVariables()` utility to `customer-app/src/config/theme.js` (or duplicate the one from admin-app — they are identical in logic)
2. In `customer-app/src/main.jsx`, apply customer-side defaults immediately (same as admin pattern):
   ```javascript
   import { theme as DEFAULT_THEME } from './config/theme.js';
   // Apply flat color defaults as CSS vars at startup
   applyBrandColors({ primary: DEFAULT_THEME.colors.primary, ... });
   ```
3. In `customer-app/src/App.jsx` after `publicApi.init()` resolves, read `config.branding` and re-apply:
   ```javascript
   publicApi.init().then(config => {
     if (config?.branding) applyBrandColors(config.branding);
   });
   ```
4. The customer app's `theme.js` uses `colors.primary` for its primary brand color. The CSS variable `--theme-primary-color` (or a new `--brand-primary`) must be applied consistently so Tailwind-extended classes or inline style references pick it up.

**CSS variable naming:** The admin app generates `--theme-{key}` (e.g., `--theme-primary-color`). Use the same naming for the three customizable brand colors so the customer app can share the same Tailwind config reference.

---

## Standard Stack

### Core (all already installed)
| Library/Tool | Version | Purpose |
|---|---|---|
| WordPress Options API | core | Persistent settings storage |
| `sanitize_hex_color()` | WP core | Server-side hex color validation |
| `media_handle_upload()` | WP core | File upload to Media Library |
| React 18 | ^18.2.0 | Both apps |
| TailwindCSS | ^3.3.3 | Styling |
| `<input type="color">` | Browser native | Color picker UI |

### No new dependencies needed
Both `admin-app/package.json` and `customer-app/package.json` require no additions for this phase.

---

## Architecture Patterns

### Recommended project structure additions
```
includes/
└── api/
    └── controllers/
        └── SettingsRestController.php    # NEW: GET/PUT /settings/branding + POST /settings/logo

admin-app/src/
└── components/
    └── settings/
        └── BrandingSection.jsx           # NEW: tab content for theme customization

customer-app/src/
└── config/
    └── theme.js                          # MODIFY: add generateCSSVariables(), applyBrandColors()
```

### Pattern: Branding settings flow
```
Admin saves settings
  → PUT /squidly/v1/settings/branding
  → PHP sanitizes + update_option('squidly_branding', ...)
  → Response: updated branding object

Customer app startup
  → GET /squidly/v1/public/config
  → Response includes branding: { restaurant_name, logo_url, primary_color, ... }
  → JS calls applyBrandColors(config.branding)
  → document.documentElement CSS vars updated
```

### Pattern: CSS variable application (established in admin-app/src/main.jsx)
```javascript
const applyTheme = (theme) => {
  const cssVars = generateCSSVariables(theme);
  const root = document.documentElement;
  Object.entries(cssVars).forEach(([property, value]) => {
    root.style.setProperty(property, value);
  });
};
```
Call once with defaults synchronously, then re-call after API init resolves.

### Anti-Patterns to Avoid
- **Applying theme only after API init**: Always apply defaults synchronously first to avoid FOUC (flash of unstyled content). The admin app already does this correctly; mirror the pattern in customer app.
- **Storing colors as separate WordPress options**: Creates migration complexity and more DB reads.
- **Setting `Content-Type: application/json` on FormData uploads**: The browser must set the multipart boundary. Override only the nonce header as in the existing `uploadAvatar()` pattern.
- **Hardcoding theme colors in config endpoints**: Both `get_admin_config()` and `get_public_config()` currently hardcode colors. These must read from `squidly_branding` option.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead |
|---|---|---|
| Hex color validation | Custom regex | `sanitize_hex_color()` (WP core) |
| File upload to server | Custom multipart handler | `media_handle_upload()` (WP core) |
| CSS variable generation | New implementation | `generateCSSVariables()` already in `admin-app/src/config/theme.js` — copy/import |
| Color picker UI | Custom component | `<input type="color">` (browser native) |
| Option defaults/merge | Custom merge logic | `wp_parse_args($saved, $defaults)` (WP core) |

---

## Common Pitfalls

### Pitfall 1: Content-Type header on FormData uploads
**What goes wrong:** Setting `Content-Type: application/json` on a `FormData` POST prevents the browser from adding the multipart boundary, causing the server to receive an empty `$_FILES` array.
**Why it happens:** `api.js` `fetch()` always adds `Content-Type: application/json` in its default headers. `FormData` requires the browser to set its own boundary.
**How to avoid:** When calling `api.fetch()` for logo upload, pass `headers: { 'X-WP-Nonce': api.nonce }` (no Content-Type) and set `body: formData`. The existing `uploadAvatar()` in `api.js` (line 420–424) does this correctly — follow that exact pattern.

### Pitfall 2: FOUC (flash of unstyled/wrong-colored content)
**What goes wrong:** Customer app renders with hardcoded defaults (e.g., red `#DC2626`) before the API config returns custom colors (e.g., purple `#7C3AED`). Users see a color flash.
**Why it happens:** `publicApi.init()` is called inside a `useEffect` in `App.jsx` — it runs after first render.
**How to avoid:** Apply default CSS variables synchronously in `customer-app/src/main.jsx` before `ReactDOM.createRoot()`. After API init, call `applyBrandColors()` again. The first call sets safe defaults; the second applies customizations.

### Pitfall 3: `sanitize_hex_color()` rejects 8-character (RGBA) hex
**What goes wrong:** `sanitize_hex_color()` only accepts 3 or 6 character hex codes. The `DEFAULT_THEME` in admin-app has `secondary_color: '#f2f2f2ff'` (8 chars with alpha). Saving this value will fail silently — the function returns empty string for invalid input.
**Why it happens:** The WP built-in only validates `#RGB` and `#RRGGBB`.
**How to avoid:** Ensure the frontend color picker emits 6-character hex. Native `<input type="color">` always returns `#rrggbb` (6 chars). Strip alpha if any 8-char value appears.

### Pitfall 4: `/admin/config` theme block not used after init
**What goes wrong:** Admin app applies `DEFAULT_THEME` at startup, fetches config (which now has DB-stored colors), but never re-applies the fetched theme. Customizations are invisible until page reload.
**Why it happens:** `admin-app/src/main.jsx` calls `applyTheme(DEFAULT_THEME)` once and never calls it again. `api.init()` stores the config but nothing re-applies the theme.
**How to avoid:** After `api.init()` resolves, re-call `applyTheme(getThemeConfig(config.theme))`. This is a one-line addition to `admin-app/src/main.jsx` or `App.jsx`.

### Pitfall 5: Customer app theme.js structure mismatch
**What goes wrong:** Customer app `config/theme.js` uses a nested structure (`theme.colors.primary`) while the admin app uses a flat structure (`DEFAULT_THEME.primary_color`). The API will return a flat structure. If you try to apply the API response as if it were the customer theme object, properties will be on the wrong paths.
**Why it happens:** The two apps were developed independently with different theme structures.
**How to avoid:** Create a `applyBrandColors(branding)` function in the customer app that reads specific keys from the API response (`branding.primary_color`, `branding.logo_url`, etc.) and sets CSS variables directly — do not try to merge the API response into the nested `theme` object.

---

## Code Examples

### PHP: SettingsRestController skeleton
```php
// Source: based on AdminUserRestController.php pattern in this codebase
class SettingsRestController extends \WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'squidly/v1';
        $this->rest_base = 'settings';
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/branding', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_branding'],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_branding'],
                'permission_callback' => [$this, 'permissions_check'],
                'args'                => $this->get_branding_args(),
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/logo', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'upload_logo'],
            'permission_callback' => [$this, 'permissions_check'],
        ]);
    }

    public function permissions_check($request): bool {
        return current_user_can('manage_options');
    }

    private function get_defaults(): array {
        return [
            'restaurant_name'    => get_bloginfo('name'),
            'logo_url'           => '',
            'logo_attachment_id' => 0,
            'primary_color'      => '#D12525',
            'secondary_color'    => '#F2F2F2',
            'accent_color'       => '#10B981',
        ];
    }

    public function get_branding($request): \WP_REST_Response {
        $saved    = get_option('squidly_branding', []);
        $branding = wp_parse_args($saved, $this->get_defaults());
        return new \WP_REST_Response($branding, 200);
    }

    public function update_branding($request): \WP_REST_Response {
        $saved    = get_option('squidly_branding', []);
        $branding = wp_parse_args($saved, $this->get_defaults());

        $fields = ['restaurant_name', 'primary_color', 'secondary_color', 'accent_color'];
        foreach ($fields as $field) {
            if ($request->has_param($field)) {
                $value = $request->get_param($field);
                if (str_ends_with($field, '_color')) {
                    $value = sanitize_hex_color($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                if ($value !== '') {
                    $branding[$field] = $value;
                }
            }
        }

        update_option('squidly_branding', $branding);
        return new \WP_REST_Response($branding, 200);
    }

    public function upload_logo($request): \WP_REST_Response {
        $files = $request->get_file_params();
        if (empty($files['logo'])) {
            return new \WP_REST_Response(['error' => 'No file uploaded'], 400);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('logo', 0);
        if (is_wp_error($attachment_id)) {
            return new \WP_REST_Response(['error' => $attachment_id->get_error_message()], 400);
        }

        $logo_url = wp_get_attachment_url($attachment_id);

        // Persist to branding option
        $branding = wp_parse_args(get_option('squidly_branding', []), $this->get_defaults());
        $branding['logo_url']           = esc_url_raw($logo_url);
        $branding['logo_attachment_id'] = absint($attachment_id);
        update_option('squidly_branding', $branding);

        return new \WP_REST_Response(['logo_url' => $logo_url, 'attachment_id' => $attachment_id], 200);
    }

    private function get_branding_args(): array {
        return [
            'restaurant_name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'primary_color'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color'],
            'secondary_color' => ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color'],
            'accent_color'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color'],
        ];
    }
}
```

### PHP: Updating get_public_config to read from DB
```php
// Source: PublicApiBootstrap.php get_public_config() — replace hardcoded theme block
$branding_defaults = [
    'restaurant_name' => get_bloginfo('name'),
    'logo_url'        => '',
    'primary_color'   => '#D12525',
    'secondary_color' => '#F2F2F2',
    'accent_color'    => '#10B981',
];
$branding_saved = get_option('squidly_branding', []);
$branding       = wp_parse_args($branding_saved, $branding_defaults);

// In the response array:
'theme'    => [
    'primary_color'   => $branding['primary_color'],
    'secondary_color' => $branding['secondary_color'],
    'accent_color'    => $branding['accent_color'],
],
'branding' => [
    'restaurant_name' => $branding['restaurant_name'],
    'logo_url'        => $branding['logo_url'],
],
```

### JS: Customer app — applyBrandColors utility
```javascript
// customer-app/src/config/theme.js addition
// Applies API branding response as CSS custom properties
export const applyBrandColors = (branding = {}) => {
  const root = document.documentElement;
  if (branding.primary_color)   root.style.setProperty('--theme-primary-color',   branding.primary_color);
  if (branding.secondary_color) root.style.setProperty('--theme-secondary-color', branding.secondary_color);
  if (branding.accent_color)    root.style.setProperty('--theme-accent-color',    branding.accent_color);
};
```

### JS: Admin api.js additions
```javascript
// In ApiService class
async getBranding() {
  return await this.fetch('settings/branding');
}

async updateBranding(data) {
  return await this.fetch('settings/branding', {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

async uploadLogo(formData) {
  return await this.fetch('settings/logo', {
    method: 'POST',
    headers: { 'X-WP-Nonce': this.nonce },  // no Content-Type — FormData sets boundary
    body: formData,
  });
}
```

---

## Environment Availability

Step 2.6: SKIPPED — this phase is code/config changes to an existing WordPress plugin with no new external services or CLI tools required beyond what already runs.

---

## Validation Architecture

No test framework configuration was found for the frontend apps (`admin-app/` and `customer-app/` have no test scripts in their `package.json`). PHP test suites exist (`composer test`).

**Phase validation approach:**
- Manual smoke test: save branding in admin app, reload customer app, verify colors and logo appear
- PHP unit tests for `SettingsRestController` can be added following the existing `tests/unit/` pattern
- No automated frontend test infrastructure exists to add to

---

## Open Questions

1. **`accent_color` vs. `success_color`** — The phase description mentions "primary, secondary, accent" colors. The existing `DEFAULT_THEME` in admin-app uses `success_color` for green. Clarify whether `accent_color` is a new semantic slot or a rename of `success_color`. Recommendation: introduce `accent_color` as a new property; leave `success_color` hardcoded (it's a status color, not a brand color).

2. **Should the admin app also apply branding from the API?** — Currently `admin-app/src/main.jsx` applies `DEFAULT_THEME` at startup but never re-applies after `api.init()`. The admin app presumably uses the same primary/secondary colors in its own UI. Recommend: yes, re-apply after init so the admin app preview also reflects customizations.

3. **Logo size/format constraints** — Should the upload endpoint enforce image MIME types only? `media_handle_upload()` uses WordPress's built-in MIME check but does not enforce image-only. A type check (`if (!str_starts_with($mime_type, 'image/')) { return error; }`) should be added.

---

## Sources

### Primary (HIGH confidence)
- Direct codebase reading: `AdminApiBootstrap.php`, `PublicApiBootstrap.php`, `AdminUserRestController.php`, `admin-app/src/config/theme.js`, `admin-app/src/main.jsx`, `customer-app/src/main.jsx`, `customer-app/src/config/theme.js`, `admin-app/src/services/api.js`, `admin-app/package.json`, `customer-app/package.json`, `admin-app/src/components/Settings.jsx`, `admin-app/src/components/settings/ProfileSection.jsx`
- WordPress Codex: `sanitize_hex_color()`, `media_handle_upload()`, `update_option()`, `wp_parse_args()` — all WP core functions with stable APIs

### Secondary (MEDIUM confidence)
- Browser compatibility for `<input type="color">`: caniuse.com — supported in all modern browsers since 2015+

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new libraries; all patterns directly verified in codebase
- Architecture: HIGH — REST controller pattern, Options API usage, CSS variable application all directly read from existing code
- Pitfalls: HIGH — identified from direct code inspection, not speculation

**Research date:** 2026-03-30
**Valid until:** 2026-06-30 (stable domain — WordPress core APIs and React patterns change slowly)
