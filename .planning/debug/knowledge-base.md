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
