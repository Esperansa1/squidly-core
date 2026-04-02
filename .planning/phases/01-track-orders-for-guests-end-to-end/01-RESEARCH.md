## RESEARCH COMPLETE

# Phase 1: Track Orders for Guests End-to-End — Research

## Executive Summary

Most infrastructure is already in place. The four gaps are small and well-defined. No new architecture needed — this is connection/completion work.

---

## What Already Exists (Do Not Rebuild)

### Backend
- **`Order` model** (`includes/domains/orders/models/Order.php`): Has `tracking_token` field, all status constants, `fromWordPress()` loads all meta.
- **`OrderRepository::create()`** (line ~762): Generates `tk_` + 16 random hex bytes, saves as `_tracking_token` meta.
- **`OrderRepository::updateStatus()`** (line 182–189): Updates `_status` meta — just one line. No timestamp saving.
- **Public status endpoint** (`includes/domains/orders/rest/PublicOrderRestController.php`, `get_order_status()`, line ~295): Returns full order data including `estimated_time => $order->estimated_ready_time ?? null`. `estimated_ready_time` is never set on the model so this is always null.

### Frontend
- **`OrderTracker.jsx`** (`customer-app/src/components/orders/OrderTracker.jsx`): Token input form. On mount reads `squidly_order_id` + `squidly_tracking_token` from sessionStorage and auto-starts tracking if both present.
- **`OrderTracking.jsx`** (`customer-app/src/components/orders/OrderTracking.jsx`): Live status display. Renders `order.cancelled_at` (line 283) if present but no other timestamp fields are rendered.
- **`useOrderPolling.js`** (`customer-app/src/hooks/useOrderPolling.js`): Polls `getOrderStatus()` every 15s until `completed` or `cancelled`.
- **`publicApi.getOrderStatus(orderId, token)`** (`customer-app/src/services/publicApi.js`): Calls `orders/{id}/status?token={token}`.
- **`App.jsx`**: Has `currentView` state (`'menu'` | `'tracking'`). `OrderTracker` is already rendered at `currentView === 'tracking'`. Has payment-return detection that sets `currentView = 'tracking'` from sessionStorage (lines 30–41). **Missing: `onTrackOrder` prop not passed to `MenuLayout`.**

### i18n
- `trackOrder` key exists in Hebrew (`עקוב אחר ההזמנה שלך`), English, and Arabic.
- `enterTrackingDetails` and related keys all present.

---

## Gap Analysis (4 Gaps)

### Gap 1: Post-Checkout → Tracking Handoff

**Problem:** After checkout succeeds, `CheckoutModal.jsx` already saves `squidly_tracking_token` + `squidly_order_id` to sessionStorage (lines 448–449) and calls `setProcessingStage('complete')`. But it never calls `setCurrentView('tracking')` because it has no way to — it only receives `onClose` prop.

**Fix:**
- Add `onOrderComplete` prop to `CheckoutModal` signature: `{ isOpen, onClose, onEditOrderDetails, onOrderComplete }`
- In `handleSubmit` after `setProcessingStage('complete')` (line 452), call `onOrderComplete?.()` — or call it from the "Track Order" button in `ProcessingState`.
- In `App.jsx`, pass `onOrderComplete={() => { setCheckoutOpen(false); setCurrentView('tracking'); }}` to `CheckoutModal`.
- The `ProcessingState` success screen (line 280) currently just shows a "Continue Shopping" close button. Replace/augment with a "Track My Order" button that calls `onOrderComplete`.

**Files:**
- `customer-app/src/components/checkout/CheckoutModal.jsx` — add prop + call
- `customer-app/src/App.jsx` — pass handler

### Gap 2: Status Transition Timestamps

**Problem:** `OrderRepository::updateStatus()` (line 182) only calls `update_post_meta($id, '_status', $status)`. `OrderTracking.jsx` checks `order.cancelled_at` (line 283) but the API never returns it because `Order::fromWordPress()` never loads these meta fields and `get_order_status()` never includes them in the response.

**Fix:**
- In `OrderRepository::updateStatus()`, after updating `_status`, save a timestamp: `update_post_meta($id, "_{$status}_at", current_time('mysql'))`. This covers all statuses dynamically.
- Add timestamp properties to `Order` model: `?string $confirmed_at`, `?string $preparing_at`, `?string $ready_at`, `?string $completed_at`, `?string $cancelled_at`.
- In `Order::fromWordPress()`, load each: `$order->confirmed_at = get_post_meta($post->ID, '_confirmed_at', true) ?: null;` etc.
- In `PublicOrderRestController::get_order_status()`, add timestamp fields to the response array alongside the existing fields.
- In `OrderTracking.jsx`, surface these timestamps in the visual timeline where relevant.

**Files:**
- `includes/domains/orders/models/Order.php` — add 5 nullable string properties + load in `fromWordPress()`
- `includes/domains/orders/repositories/OrderRepository.php` — add timestamp save in `updateStatus()`
- `includes/domains/orders/rest/PublicOrderRestController.php` — add timestamps to response
- `customer-app/src/components/orders/OrderTracking.jsx` — render timestamps in timeline

### Gap 3: Estimated Ready Time

**Problem:** `get_order_status()` returns `'estimated_time' => $order->estimated_ready_time ?? null` but `estimated_ready_time` is never a property on `Order` and is never calculated.

**Fix:** Compute it directly in the controller. Simple rule: `order_date` + 30 minutes for pending/confirmed/preparing; once `ready` or beyond, don't show it.

```php
$estimated_time = null;
if (in_array($order->status, ['pending', 'confirmed', 'preparing'])) {
    $estimated_time = date('Y-m-d H:i:s', strtotime($order->order_date) + 30 * 60);
}
```

No model change needed — compute inline in controller.

**File:** `includes/domains/orders/rest/PublicOrderRestController.php`

### Gap 4: "Track Order" Entry Point in Nav

**Problem:** `OrderTracker` is accessible at `currentView === 'tracking'` in `App.jsx` but there's no way to get there from the menu UI other than after checkout. A guest who placed an order and returns to the page needs a nav link.

**Fix:**
- Add `onTrackOrder` prop to `MenuLayout`.
- Add a "Track Order" icon/link in `MobileUserHeader` (mobile) and in the desktop sidebar navigation area.
- In `App.jsx`, pass `onTrackOrder={() => setCurrentView('tracking')}` to `MenuLayout`.
- `MenuLayout` passes `onTrackOrder` to `MobileUserHeader`.

**Files:**
- `customer-app/src/App.jsx` — pass `onTrackOrder` to `MenuLayout`
- `customer-app/src/components/menu/MenuLayout.jsx` — accept + pass to header
- `customer-app/src/components/menu/MobileUserHeader.jsx` — add Track Order button

---

## Validation Architecture

The E2E flow can be validated manually:
1. Place order → confirm sessionStorage has `squidly_order_id` + `squidly_tracking_token`
2. Confirm `ProcessingState` shows "Track My Order" button and clicking it navigates to tracking view
3. Confirm admin status change → customer app shows updated status within 15s
4. Confirm status timeline shows timestamp for each transition
5. Confirm "Track Order" link in mobile header navigates to tracking view for returning guests

---

## File Inventory

| File | Purpose | Change Type |
|------|---------|-------------|
| `includes/domains/orders/models/Order.php` | Add 5 timestamp properties + load in `fromWordPress()` | Add fields |
| `includes/domains/orders/repositories/OrderRepository.php` | Save status timestamp on `updateStatus()` | 1-line addition |
| `includes/domains/orders/rest/PublicOrderRestController.php` | Add timestamps + estimated_time to response | Add fields to response array |
| `customer-app/src/components/checkout/CheckoutModal.jsx` | Add `onOrderComplete` prop + call it on success | Add prop + button action |
| `customer-app/src/App.jsx` | Pass `onOrderComplete` + `onTrackOrder` to children | Pass props |
| `customer-app/src/components/menu/MenuLayout.jsx` | Accept + forward `onTrackOrder` | Accept + pass prop |
| `customer-app/src/components/menu/MobileUserHeader.jsx` | Add Track Order button | New button |
| `customer-app/src/components/orders/OrderTracking.jsx` | Render status timestamps in timeline | Add timestamp display |

---

## Dependencies

- No new PHP dependencies
- No new npm packages
- No DB migrations (postmeta is schemaless)
- No changes to REST API namespace or authentication
