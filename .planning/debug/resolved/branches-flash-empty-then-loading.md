---
status: resolved
trigger: "On the admin Branches page, the UI briefly shows nothing (empty state), then shows a loading spinner, then finally shows the branches list — in the wrong order."
created: 2026-03-27T00:00:00Z
updated: 2026-03-27T00:00:00Z
---

## Current Focus

hypothesis: CONFIRMED — DataTable received `tableLoading` (starts false) instead of `loading` (starts true)
test: Applied fix: DataTable now receives `loading={loading || tableLoading}`
expecting: Spinner shows immediately on mount, then branches appear when API responds
next_action: Awaiting human verification

## Symptoms

expected: Loading spinner shown immediately, then branches list once data loads
actual: (1) blank/empty for ~1-2 seconds, (2) loading spinner appears, (3) branches data loads
errors: None reported
reproduction: Go to admin → Branches section, observe first couple of seconds
started: Unknown — may be a state initialization ordering issue

## Eliminated

- hypothesis: loading state starts as false
  evidence: `loading` is initialized to `true` (line 21). The problem was it was never passed to DataTable.
  timestamp: 2026-03-27

## Evidence

- timestamp: 2026-03-27
  checked: BranchManagement.jsx state declarations and DataTable props
  found: Two loading states: `loading` (starts true, for initial load) and `tableLoading` (starts false, for pagination). DataTable only received `tableLoading`. The `loading` state was declared and managed but never surfaced in the render output.
  implication: On first render, DataTable sees loading=false + data=[] → shows empty state. Spinner never appeared for initial load (the "spinner" the user reported second was from a different re-render cycle triggered by the useEffect setState calls).

- timestamp: 2026-03-27
  checked: DataTable.jsx render logic
  found: Lines 38-45: loading overlay is rendered as `{loading && (<div>spinner</div>)}`. Line 97: empty state is shown when `!data || data.length === 0` with no loading guard.
  implication: Empty state shows whenever data is empty AND loading is false — exactly what happened on first render.

## Resolution

root_cause: In BranchManagement.jsx line 330, DataTable received `loading={tableLoading}` where `tableLoading` initializes to `false`. The component has a separate `loading` state (initialized to `true`) used for initial page load, but this was never passed to DataTable. Result: first render always showed empty state because DataTable saw loading=false with empty data.
fix: Changed `loading={tableLoading}` to `loading={loading || tableLoading}` so that both the initial full-page load and pagination-only reloads correctly activate the spinner overlay.
verification: Build and navigate to Branches section — spinner should appear immediately on mount.
files_changed: [admin-app/src/components/BranchManagement.jsx]
