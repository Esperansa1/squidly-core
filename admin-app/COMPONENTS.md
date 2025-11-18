# Squidly Admin Component Library

## Overview

This document provides comprehensive documentation for the refactored component library following atomic design principles. All components are theme-aware and fully integrated with the centralized theme system.

## Architecture

### Atomic Design Hierarchy

```
atoms/          → Basic building blocks (Button, Input, Card)
molecules/      → Simple combinations (FormField, ActionButtons)
organisms/      → Complex components (DataSection, DataTable)
templates/      → Page layouts (coming soon)
```

### Theme Integration

All components use the centralized theme from `@admin-app/src/config/theme.js`:
- Primary colors, secondary colors, success/error/warning states
- Text colors (primary, secondary, muted)
- Border and background colors
- Consistent spacing and sizing

---

## Atoms

### Button
Enhanced button with icon support, loading states, and multiple variants.

**Props:**
- `variant`: 'primary' | 'secondary' | 'outline' | 'ghost' | 'success' | 'warning' | 'error'
- `size`: 'xs' | 'sm' | 'md' | 'lg' | 'icon'
- `loading`: boolean - Shows spinner when true
- `fullWidth`: boolean - Expands to fill container
- `leftIcon`: Icon component
- `rightIcon`: Icon component
- `disabled`: boolean

**Example:**
```jsx
import { Button } from '@/components/ui/atoms';
import { PlusIcon } from '@heroicons/react/24/outline';

<Button
  variant="primary"
  size="md"
  leftIcon={PlusIcon}
  onClick={handleClick}
>
  Create New
</Button>
```

---

### Card
Content container with header/footer slots, loading overlay, and hover effects.

**Props:**
- `variant`: 'default' | 'outlined' | 'elevated' | 'flat'
- `padding`: 'none' | 'sm' | 'md' | 'lg'
- `header`: ReactNode - Optional header content
- `footer`: ReactNode - Optional footer content
- `loading`: boolean - Shows overlay spinner
- `hoverable`: boolean - Adds hover effects

**Example:**
```jsx
import { Card } from '@/components/ui/atoms';

<Card
  variant="elevated"
  padding="md"
  header={<h2>Title</h2>}
  footer={<button>Action</button>}
>
  Card content
</Card>
```

---

### Input
Theme-aware text input with icon support and error states.

**Props:**
- `type`: string - HTML input type
- `value`: string
- `onChange`: function
- `placeholder`: string
- `error`: boolean - Shows error styling
- `disabled`: boolean
- `fullWidth`: boolean
- `size`: 'sm' | 'md' | 'lg'
- `leftIcon`: Icon component
- `rightIcon`: Icon component

**Example:**
```jsx
import { Input } from '@/components/ui/atoms';
import { MagnifyingGlassIcon } from '@heroicons/react/24/outline';

<Input
  type="text"
  value={searchTerm}
  onChange={(e) => setSearchTerm(e.target.value)}
  placeholder="Search..."
  rightIcon={MagnifyingGlassIcon}
/>
```

---

### Textarea
Multi-line text input with character count.

**Props:**
- `value`: string
- `onChange`: function
- `rows`: number
- `maxLength`: number
- `showCharCount`: boolean
- `resize`: 'none' | 'vertical' | 'horizontal' | 'both'
- `error`: boolean
- `disabled`: boolean

---

### Checkbox
Theme-aware checkbox with label support.

**Props:**
- `checked`: boolean
- `onChange`: function
- `label`: string
- `disabled`: boolean
- `error`: boolean
- `size`: 'sm' | 'md' | 'lg'

---

### Select
Dropdown select with theme styling.

**Props:**
- `value`: string
- `onChange`: function
- `options`: Array<{value, label, disabled?}>
- `placeholder`: string
- `error`: boolean
- `disabled`: boolean
- `size`: 'sm' | 'md' | 'lg'

---

### Label
Form label with required indicator.

**Props:**
- `htmlFor`: string
- `required`: boolean
- `disabled`: boolean
- `size`: 'sm' | 'md' | 'lg'

---

### Badge
Status indicators with theme colors.

**Props:**
- `variant`: 'default' | 'primary' | 'success' | 'warning' | 'error' | 'info'
- `size`: 'sm' | 'md' | 'lg'
- `rounded`: boolean

**Example:**
```jsx
import { Badge } from '@/components/ui/atoms';

<Badge variant="success" size="sm">
  Active
</Badge>
```

---

### Spinner
Loading indicator.

**Props:**
- `size`: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
- `color`: 'primary' | 'success' | 'warning' | 'error' | 'info' | 'white'

---

### IconButton
Icon-only button with proper sizing.

**Props:**
- `icon`: Icon component
- `variant`: Same as Button
- `size`: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
- `disabled`: boolean
- `loading`: boolean
- `tooltip`: string

**Example:**
```jsx
import { IconButton } from '@/components/ui/atoms';
import { TrashIcon } from '@heroicons/react/24/outline';

<IconButton
  icon={TrashIcon}
  variant="error"
  size="md"
  tooltip="Delete item"
  onClick={handleDelete}
/>
```

---

### Divider
Horizontal or vertical separator.

**Props:**
- `orientation`: 'horizontal' | 'vertical'
- `spacing`: 'none' | 'sm' | 'md' | 'lg' | 'xl'

---

## Molecules

### FormField
Complete form field with label, input/select/textarea, help text, and error display.

**Props:**
- `label`: string
- `fieldType`: 'input' | 'select' | 'textarea'
- `type`: string - For input type
- `name`: string
- `value`: string
- `onChange`: function
- `required`: boolean
- `error`: string | null
- `helpText`: string
- `options`: Array - For select
- `rows`: number - For textarea
- `maxLength`: number - For textarea
- `showCharCount`: boolean - For textarea

**Example:**
```jsx
import { FormField } from '@/components/ui/molecules';

<FormField
  label="Product Name"
  fieldType="input"
  type="text"
  name="product_name"
  value={formData.name}
  onChange={(e) => setFormData({...formData, name: e.target.value})}
  required
  error={errors.name}
  helpText="Enter a unique product name"
/>
```

---

### StatusBadge
Badge with icon for status display.

**Props:**
- `status`: 'active' | 'inactive' | 'pending' | 'success' | 'error' | 'warning' | 'info'
- `showIcon`: boolean
- `size`: 'sm' | 'md' | 'lg'

**Example:**
```jsx
import { StatusBadge } from '@/components/ui/molecules';

<StatusBadge status="active" showIcon>
  פעיל
</StatusBadge>
```

---

### EmptyState
Display for empty lists/tables.

**Props:**
- `icon`: string - Emoji or icon
- `title`: string
- `message`: string
- `action`: ReactNode - Optional action button

**Example:**
```jsx
import { EmptyState } from '@/components/ui/molecules';
import { Button } from '@/components/ui/atoms';

<EmptyState
  icon="📦"
  title="No Products"
  message="Create your first product to get started"
  action={<Button onClick={handleCreate}>Create Product</Button>}
/>
```

---

### LoadingState
Display while data is loading.

**Props:**
- `message`: string
- `spinnerSize`: 'sm' | 'md' | 'lg'

---

### ErrorState
Display for error states with retry option.

**Props:**
- `title`: string
- `message`: string
- `onRetry`: function | null
- `retryText`: string

---

### SearchInput
Search input with clear button.

**Props:**
- `value`: string
- `onChange`: function
- `placeholder`: string
- `disabled`: boolean
- `size`: 'sm' | 'md' | 'lg'
- `onClear`: function | null

---

### ActionButton
Single icon-based action button (used in CED operations).

**Props:**
- `icon`: Icon component
- `variant`: 'primary' | 'secondary' | 'error'
- `onClick`: function
- `disabled`: boolean
- `tooltip`: string
- `size`: 'sm' | 'md' | 'lg'

---

### ActionButtons
Group of CED (Create/Edit/Delete) action buttons.

**Props:**
- `onAdd`: function | null
- `onEdit`: function | null
- `onDelete`: function | null
- `addDisabled`: boolean
- `editDisabled`: boolean
- `deleteDisabled`: boolean
- `addTooltip`: string
- `editTooltip`: string
- `deleteTooltip`: string
- `size`: 'sm' | 'md' | 'lg'

**Example:**
```jsx
import { ActionButtons } from '@/components/ui/molecules';

<ActionButtons
  onAdd={handleCreate}
  onEdit={handleEdit}
  onDelete={handleDelete}
  editDisabled={!selectedItem}
  deleteDisabled={!selectedItem}
  addTooltip="Create new item"
  editTooltip="Edit selected item"
  deleteTooltip="Delete selected item"
/>
```

---

## Organisms

### DataSection
Complete CRUD section with search, table, and CED operations.

**Props:**
- `title`: string
- `data`: Array
- `selectedItem`: number | null
- `setSelectedItem`: function
- `strings`: object - Localization strings
- `loading`: boolean
- `error`: string | null
- `branches`: Array
- `selectedBranchId`: number
- `onItemChange`: function
- `columns`: Array - Column definitions
- `apiService`: object - API methods (getAll, create, update, delete)
- `Modal`: Component - Modal for create/edit
- `editingItemProp`: string - Prop name for editing item
- `itemIdProp`: string - ID property name
- `itemNameProp`: string - Name property name

**Example:**
```jsx
import { DataSection } from '@/components/ui';
import ProductModal from './ProductModal';
import api from '../services/api';

<DataSection
  title="Products"
  data={products}
  selectedItem={selectedProduct}
  setSelectedItem={setSelectedProduct}
  columns={columns}
  apiService={{
    getAll: () => api.getProducts(),
    create: (data) => api.createProduct(data),
    update: (id, data) => api.updateProduct(id, data),
    delete: (id) => api.deleteProduct(id)
  }}
  Modal={ProductModal}
  editingItemProp="product"
  itemIdProp="id"
  itemNameProp="name"
  onItemChange={handleRefresh}
/>
```

---

### DataTable
Reusable data table with selection and custom rendering.

**Props:**
- `columns`: Array<{key, label, width, render?, cellStyle?}>
- `data`: Array
- `selectedId`: number | null
- `onSelectionChange`: function
- `loading`: boolean
- `error`: string | null
- `emptyMessage`: string

---

## Usage Patterns

### Creating a New CRUD Page

1. **Define columns:**
```jsx
const columns = useMemo(() => [
  {
    key: 'name',
    label: 'Name',
    width: '200px',
    render: (name) => <span className="font-medium">{name}</span>
  },
  {
    key: 'status',
    label: 'Status',
    width: '100px',
    render: (_, item) => (
      <StatusBadge status={item.status}>
        {item.status}
      </StatusBadge>
    )
  }
], []);
```

2. **Use DataSection:**
```jsx
<DataSection
  title="My Items"
  data={items}
  selectedItem={selectedId}
  setSelectedItem={setSelectedId}
  columns={columns}
  apiService={myApiService}
  Modal={MyItemModal}
  onItemChange={handleRefresh}
/>
```

That's it! The DataSection handles:
- Search functionality
- CED buttons
- Table rendering
- Create/Edit modal
- Delete confirmation
- API calls
- Error handling
- Loading states

---

### Creating a Form

```jsx
import { FormField, Button } from '@/components/ui';

const MyForm = () => {
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    category: ''
  });
  const [errors, setErrors] = useState({});

  return (
    <form>
      <FormField
        label="Name"
        fieldType="input"
        name="name"
        value={formData.name}
        onChange={(e) => setFormData({...formData, name: e.target.value})}
        required
        error={errors.name}
      />

      <FormField
        label="Description"
        fieldType="textarea"
        name="description"
        value={formData.description}
        onChange={(e) => setFormData({...formData, description: e.target.value})}
        rows={4}
        maxLength={500}
        showCharCount
      />

      <FormField
        label="Category"
        fieldType="select"
        name="category"
        value={formData.category}
        onChange={(e) => setFormData({...formData, category: e.target.value})}
        options={[
          { value: 'cat1', label: 'Category 1' },
          { value: 'cat2', label: 'Category 2' }
        ]}
        required
      />

      <Button type="submit" variant="primary" fullWidth>
        Save
      </Button>
    </form>
  );
};
```

---

## Migration Guide

### Before (Old Pattern):
```jsx
// Inline button with custom styles
<button
  onClick={handleDelete}
  className="w-8 h-8 rounded-md"
  style={{ backgroundColor: theme.danger_color }}
>
  <TrashIcon className="w-4 h-4" />
</button>
```

### After (New Pattern):
```jsx
import { IconButton } from '@/components/ui/atoms';

<IconButton
  icon={TrashIcon}
  variant="error"
  size="sm"
  onClick={handleDelete}
  tooltip="Delete"
/>
```

---

## Benefits

1. **Consistency** - All components use the same theme system
2. **Rapid Development** - Create new CRUD pages in ~50 lines vs 300+
3. **Maintainability** - Single source of truth for UI patterns
4. **Accessibility** - Built-in ARIA labels and keyboard navigation
5. **Type Safety** - Clear prop interfaces with defaults
6. **RTL Support** - Full support maintained throughout
7. **Reduced Bundle Size** - Shared components reduce duplication

---

## Best Practices

1. **Always use theme colors** - Never hardcode colors
2. **Use atomic components** - Build up from atoms → molecules → organisms
3. **Provide tooltips** - Especially for icon-only buttons
4. **Handle loading states** - Use loading prop on buttons/cards
5. **Show errors gracefully** - Use error prop and ErrorState component
6. **Make it accessible** - Add aria-labels and proper focus management
7. **Keep components small** - Single responsibility principle

---

## Future Enhancements

- [ ] Page templates (ManagementLayout, CRUDPage, DashboardLayout)
- [ ] Advanced filtering components
- [ ] Pagination component
- [ ] Chart components for analytics
- [ ] Form validation hooks
- [ ] CSS custom properties for theme
- [ ] Dark mode support
- [ ] Animation library integration
