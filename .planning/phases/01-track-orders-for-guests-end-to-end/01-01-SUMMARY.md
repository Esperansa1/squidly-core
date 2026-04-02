---
phase: 01-track-orders-for-guests-end-to-end
plan: "01"
subsystem: orders-backend
tags: [orders, tracking, timestamps, public-api]
dependency_graph:
  requires: []
  provides: [order-status-timestamps, estimated-ready-time]
  affects: [PublicOrderRestController, OrderRepository, Order]
tech_stack:
  added: []
  patterns: [postmeta-timestamps, computed-api-fields]
key_files:
  created: []
  modified:
    - includes/domains/orders/models/Order.php
    - includes/domains/orders/repositories/OrderRepository.php
    - includes/domains/orders/rest/PublicOrderRestController.php
decisions:
  - "estimated_time computed as order_date + 30min — simple fixed offset, no per-branch config needed for Phase 1"
  - "Timestamp save is conditional on update_post_meta success to avoid orphaned timestamps on failures"
metrics:
  duration: "~8 minutes"
  completed: "2026-04-02T21:32:45Z"
  tasks_completed: 2
  tasks_total: 2
  files_changed: 3
---

# Phase 01 Plan 01: Order Status Timestamps and Estimated Time Summary

Add 5 status transition timestamps and a computed estimated ready time to the order tracking backend so the guest tracking UI receives real data.

## What Was Built

The public order status endpoint previously returned `estimated_time: null` always and never included timestamps for when each status transition happened. The frontend `OrderTracking.jsx` already rendered `order[`${step.key}_at`]` and `order.estimated_time` but the backend never supplied them.

### Task 1: Order model + repository

**Order.php** — Added 5 nullable timestamp properties, loaded them from postmeta in `fromWordPress()`, and exposed them in `toArray()`:
- `confirmed_at`, `preparing_at`, `ready_at`, `completed_at`, `cancelled_at`

**OrderRepository.php** — Updated `updateStatus()` to save `_{status}_at` postmeta after a successful status update (conditional on `$result` being true).

### Task 2: Public API response

**PublicOrderRestController.php** — In `get_order_status()`:
- Added `$estimated_time` computation: `order_date + 30 minutes` for `pending/confirmed/preparing` orders; `null` for `ready/completed/cancelled`
- Replaced the nonexistent `$order->estimated_ready_time ?? null` reference with `$estimated_time`
- Added all 5 timestamp fields to the response array

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 971df39 | feat(01-01): add status transition timestamps to Order model and repository |
| 2 | 1e3e5dd | feat(01-01): add timestamps and estimated_time to public order status API |

## Decisions Made

1. **Fixed 30-minute estimated time:** Computed as `order_date + 30 minutes`. No per-branch configuration needed for Phase 1 — a fixed offset is sufficient for the guest tracking display.
2. **Conditional timestamp save:** `update_post_meta($id, "_{$status}_at", ...)` only runs when `$result` is true to avoid orphaned timestamp writes when the status update itself fails.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all 5 timestamps are loaded from real postmeta, and `estimated_time` is computed from real `order_date` data.

## Self-Check: PASSED

- `includes/domains/orders/models/Order.php` — modified, contains `confirmed_at` in 3 places (property, fromWordPress, toArray)
- `includes/domains/orders/repositories/OrderRepository.php` — modified, contains `_{$status}_at` timestamp save
- `includes/domains/orders/rest/PublicOrderRestController.php` — modified, no `estimated_ready_time` reference, 5 timestamp fields present
- Commit 971df39 exists
- Commit 1e3e5dd exists
- PHP lint passes on all 3 files
