---
status: resolved
trigger: "Opening the product edit modal crashes with React Minified Error #310 — Rendered more hooks than during the previous render."
created: 2026-03-27T00:00:00Z
updated: 2026-03-27T00:02:00Z
---

## Current Focus

hypothesis: CONFIRMED — early return `if (!isOpen) return null` at line 130 of ProductModal.jsx causes React to skip 7 useCallback hooks when isOpen is false, then see them when isOpen becomes true.
test: Remove the early return and instead conditionally render the JSX.
expecting: Fix eliminates the hooks-count mismatch entirely.
next_action: Apply fix to ProductModal.jsx

## Symptoms

expected: The product edit modal should open and allow editing product fields including product group assignment.
actual: Opening a product for editing throws React Minified Error #310: "Rendered more hooks than during the previous render." The error is associated with: const handleInputChange = useCallback(...)
errors: Minified React error #310 — Rendered more hooks than during the previous render.
reproduction: 1) Go to admin → Menu Management → Products tab. 2) Click edit on a product. 3) React error #310 fires immediately.
started: After product groups were created/added. Likely a conditional hook call introduced during group-related changes.

## Eliminated

(none yet)

## Evidence

- timestamp: 2026-03-27T00:01:00Z
  checked: ProductModal.jsx full read
  found: Line 130 `if (!isOpen) return null;` is placed after 2 useEffect calls but before 7 useCallback calls (handleInputChange, handleAvailabilityChange, handleSelectAllChange, handleProductGroupAdd, handleProductGroupRemove, handleImageUpload, handleRemoveImage). When isOpen is false React renders 4 useState + 2 useEffect = 6 hooks. When isOpen transitions to true React encounters all 6 + 7 useCallback = 13 hooks, triggering error #310.
  implication: The fix must move the early return to wrap the returned JSX rather than short-circuit before the hooks.

## Resolution

root_cause: In ProductModal.jsx, `if (!isOpen) return null` was placed at line 130 — after 2 useEffect calls but before 7 useCallback calls. When isOpen is false React sees 6 hooks total; when isOpen transitions to true React sees 13 hooks, violating the Rules of Hooks and triggering error #310.
fix: Moved the `if (!isOpen) return null` guard to line 337, after all hook calls and after the validateForm/handleSubmit plain functions, immediately before the `const isEditMode` line and the JSX return. All hooks now run unconditionally on every render.
verification: Admin app builds successfully. Confirmed by user — product edit modal opens without error.
files_changed: [admin-app/src/components/ui/ProductModal.jsx]
