---
status: resolved
trigger: "Deleted product groups reappear in the admin menu management UI after navigating away and back to the groups tab."
created: 2026-03-27T00:00:00Z
updated: 2026-03-27T12:00:00Z
---

## Current Focus

hypothesis: Two separate issues: (1) TabContent passes an empty `onProductGroupChange` callback `() => {}` instead of the parent's `onGroupChange`, so deletes in the Groups tab never trigger a data reload in MenuManagement. (2) DataSection's `branchDataCache` is populated on first load and is only cleared inside `handleDeleteConfirm`/`handleItemSubmit` — but the cache belongs to the *DataSection instance for groups*, and after tab switch, a new DataSection instance mounts and re-reads the stale `data` prop (which is stale because `handleGroupChange` was never called). Both issues stem from the broken callback chain.
test: Trace the callback chain from delete → onItemChange → onProductGroupChange → onGroupChange → loadData in MenuManagement
expecting: onProductGroupChange is hardcoded to `() => {}` in TabContent — breaking the chain
next_action: Fix TabContent to pass onGroupChange through to ProductGroupSection

## Symptoms

expected: After deleting a group, it should no longer appear in the groups list, even after switching to another tab and back. Deleted groups should not appear as candidates when assigning a group to a product.
actual: Deleted groups still show in the groups list after navigating away (e.g. to Products tab) and back to Groups tab. They also appear as selectable group options in the product form.
errors: No error messages reported.
reproduction: 1) Go to admin → Menu Management → Groups tab. 2) Delete a group. 3) Navigate to Products tab. 4) Navigate back to Groups tab → deleted group is still visible. Also: open product form → deleted group appears in group dropdown.
started: Unknown — likely a stale state/cache issue in React.

## Eliminated

- hypothesis: DataSection cache not cleared on delete
  evidence: DataSection.handleDeleteConfirm() correctly calls branchDataCache.current.clear() and fetchData(true). The cache IS cleared — the problem is that the parent MenuManagement state never gets updated.
  timestamp: 2026-03-27

## Evidence

- timestamp: 2026-03-27
  checked: TabContent.jsx line 41
  found: `onProductGroupChange={() => {}}` — hardcoded empty callback, completely ignoring the `onGroupChange` prop received from MenuManagement
  implication: When DataSection calls `onItemChange()` after a delete (DataSection.jsx line 175), it reaches ProductGroupSection's `onProductGroupChange`, which is `() => {}`. MenuManagement's `handleGroupChange → loadData()` is NEVER called. `productGroups` and `ingredientGroups` state in MenuManagement stays stale.

- timestamp: 2026-03-27
  checked: TabContent.jsx renderProductsContent() line 62
  found: `onProductChange={() => {}}` — same pattern: empty callback for products change
  implication: Same problem would apply to product CRUD — changes don't propagate up.

- timestamp: 2026-03-27
  checked: MenuManagement.jsx handleGroupChange (line 84)
  found: Calls `loadData()` which re-fetches productGroups and ingredientGroups from API and updates state.
  implication: The fix is correct — if called. The callback just needs to be wired through.

- timestamp: 2026-03-27
  checked: DataSection.jsx fetchData (lines 56-103)
  found: When `data` prop changes (after MenuManagement reloads), DataSection checks `lastFetchedBranchId === selectedBranchId && branchDataCache.current.has(selectedBranchId)` — if true, skips refetch. However since tab switch unmounts and remounts the DataSection, a new instance is created with fresh state (cache is empty), so it WILL read the `data` prop on remount. This means: if MenuManagement state was updated, re-mount would show correct data. But since the callback is broken, MenuManagement state is never updated.
  implication: Fixing the callback chain in TabContent is sufficient to fix both symptoms (immediate stale list AND stale data after tab navigation).

## Resolution

root_cause: TabContent.jsx passes hardcoded `onProductGroupChange={() => {}}` to ProductGroupSection instead of forwarding the `onGroupChange` prop received from MenuManagement. This breaks the callback chain: delete → DataSection.onItemChange → ProductGroupSection.onProductGroupChange → [dead end]. MenuManagement's `handleGroupChange → loadData()` is never called, so `productGroups` and `ingredientGroups` state never updates. Since ProductSection also receives `productGroups` from MenuManagement state, the stale list also appears in the product form's group dropdown.
fix: In TabContent.jsx, replace `onProductGroupChange={() => {}}` with `onProductGroupChange={onGroupChange}`. Also fix `onProductChange` and `onIngredientChange` for consistency.
verification: User confirmed fix resolved both symptoms — deleted groups no longer reappear after tab navigation, and product form group dropdown no longer shows deleted groups.
files_changed: [admin-app/src/components/ui/organisms/TabContent.jsx]
