# Roadmap: Squidly Core

## Overview

WordPress restaurant management plugin with decoupled React interfaces. Manages products, ingredients, orders, customers, branches, and WooCommerce payment integration.

## Milestones

- ✅ **v1.0 Foundation** — Core plugin, theme customization
- 🚧 **v1.1 Guest Experience** — Order tracking, guest features

## Phases

<details>
<summary>✅ v1.0 Foundation — SHIPPED</summary>

### Phase 1: Theme Customization
**Goal:** Admin can configure branding (colors, logo, fonts) and customer app applies theme dynamically
**Plans:** 1 plan

Plans:
- [x] theme-customization-01: Theme settings UI + CSS variable injection

</details>

### 🚧 v1.1 Guest Experience

**Milestone Goal:** Guests can track their orders and manage their experience without an account

### Phase 1: Track orders for guests end to end

**Goal:** Guest can complete checkout and track their order status in real time, with timestamps for each status transition and navigation entry points to reach the tracking view
**Requirements:** [TRACK-01, TRACK-02, TRACK-03, TRACK-04, TRACK-05]
**Depends on:** Phase 0
**Plans:** 2 plans

Plans:
- [x] 01-01-PLAN.md — Backend: status transition timestamps + estimated ready time in API
- [ ] 01-02-PLAN.md — Frontend: post-checkout tracking handoff + Track Order nav entries

---
