# GSD Debug Knowledge Base

Resolved debug sessions. Used by `gsd-debugger` to surface known-pattern hypotheses at the start of new investigations.

---

## deleted-groups-reappear — Deleted product groups reappear in admin UI after tab navigation

- **Date:** 2026-03-27
- **Error patterns:** deleted, reappear, groups, stale, tab navigation, product groups, dropdown, menu management
- **Root cause:** TabContent.jsx passed hardcoded `onProductGroupChange={() => {}}` to ProductGroupSection instead of forwarding the `onGroupChange` prop from MenuManagement. This broke the callback chain after a delete: DataSection called `onItemChange()`, which reached the dead-end empty callback. MenuManagement's `handleGroupChange → loadData()` was never called, so `productGroups` and `ingredientGroups` state remained stale — causing deleted groups to persist in the list and in the product form's group dropdown.
- **Fix:** In TabContent.jsx, replace `onProductGroupChange={() => {}}` with `onProductGroupChange={onGroupChange}`. Apply the same pattern to `onProductChange` and `onIngredientChange` for full consistency.
- **Files changed:** admin-app/src/components/ui/organisms/TabContent.jsx
---

## product-edit-hooks-violation — React error #310 when opening product edit modal
- **Date:** 2026-03-27
- **Error patterns:** hooks violation, error #310, rendered more hooks, useCallback, ProductModal, isOpen, early return, modal
- **Root cause:** In ProductModal.jsx, `if (!isOpen) return null` was placed after 2 useEffect calls but before 7 useCallback calls. When isOpen is false React counted 6 hooks; when isOpen transitioned to true React saw 13 hooks, violating the Rules of Hooks and triggering error #310.
- **Fix:** Moved the `if (!isOpen) return null` guard to after all hook declarations and plain helper functions, immediately before the JSX return. All hooks now run unconditionally on every render.
- **Files changed:** admin-app/src/components/ui/ProductModal.jsx
---

## branches-flash-empty-then-loading — Admin Branches page shows empty state before loading spinner on mount
- **Date:** 2026-03-27
- **Error patterns:** empty state, flash, blank, loading spinner, branches, DataTable, tableLoading, mount
- **Root cause:** In BranchManagement.jsx, DataTable received `loading={tableLoading}` where `tableLoading` initializes to `false`. A separate `loading` state (initialized to `true`) handled initial page load but was never passed to DataTable. First render always showed empty state because DataTable saw loading=false with empty data.
- **Fix:** Changed `loading={tableLoading}` to `loading={loading || tableLoading}` so both initial load and pagination reloads correctly activate the spinner overlay.
- **Files changed:** admin-app/src/components/BranchManagement.jsx
---

