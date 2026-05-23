# shadcn-vue Component Migration Guide

**Objective:** Replace all custom HTML markup and non-shadcn-vue patterns with shadcn-vue components throughout the frontend app.

**Total Components:** 388 Vue files in `frontend/app/components/`  
**Priority Files:** 4 files with immediate custom styling issues  
**Available shadcn-vue Components:** 50+ primitives ready to use

---

## Priority Refactoring (High Impact)

### 1. RequestForm.vue — Field Structure
**Issue:** Uses custom `.field-group`, `.field-error`, `.required-mark`, `.retry-inline-btn` classes

**Current Pattern:**
```vue
<div class="field-group">
  <label>المستورد <span class="required-mark">*</span></label>
  <ComboboxMerchants v-model="values.merchant_id" />
  <span v-if="errors.merchant_id" class="field-error">{{ errors.merchant_id }}</span>
</div>
```

**Target Pattern:**
```vue
<FormField v-slot="{ componentField }" name="merchant_id">
  <FormItem>
    <FormLabel>
      المستورد
      <span class="text-red-600">*</span>
    </FormLabel>
    <FormControl>
      <ComboboxMerchants v-bind="componentField" v-model="values.merchant_id" />
    </FormControl>
    <FormMessage />
  </FormItem>
</FormField>
```

**Why:** Use shadcn-vue `FormField/FormItem/FormLabel/FormControl/FormMessage` instead of custom divs. VeeValidate integration is already built-in.

---

### 2. Wizard Steps (WizardStep1.vue, WizardStep2.vue)
**Issue:** Inherits field styling from RequestForm

**Action:** Wrap with `FormField` component structure. Replace custom `.field-row` divs with Grid or Flex layout from shadcn (or use Tailwind directly on FormItem).

---

### 3. MerchantCard.vue
**Issue:** Custom `.merchant-*` classes for card layout and actions

**Current:**
```vue
<div class="merchant-card">
  <div class="merchant-card-header">{{ merchant.name }}</div>
  <button class="suspend-btn">Suspend</button>
</div>
```

**Target:**
```vue
<Card class="p-4">
  <div class="flex justify-between items-center">
    <h3 class="text-base font-semibold">{{ merchant.name }}</h3>
    <Button variant="destructive" size="sm">Suspend</Button>
  </div>
</Card>
```

**Why:** Card + Button from shadcn-vue. Add custom layout with Tailwind on the Card.

---

## Component Mapping Reference

### Forms & Inputs
| Need | Use | Notes |
|------|-----|-------|
| Field wrapper with label, input, error | `FormField/FormItem/FormLabel/FormControl/FormMessage` | VeeValidate integrated |
| Text input | `Input` | Add class prop for Tailwind customization |
| Select dropdown | `Select` | Replaces custom `<select>` |
| Textarea | `Textarea` | For multi-line fields |
| Checkbox | `Checkbox` | Styled radio alternatives |
| Radio group | `RadioGroup` | For exclusive selections |
| Multi-select | `TagsInput` | For comma-separated or tag lists |
| Date picker | `Calendar` | Integrated into date fields |
| Number input | `NumberField` | Type-safe numeric input |
| OTP/PIN input | `InputOtp` or `PinInput` | For verification codes |
| Combobox | `Combobox` | Searchable select (already in use) |

### Layout & Structure
| Need | Use | Notes |
|------|-----|-------|
| Card container | `Card/CardContent/CardHeader/CardTitle/CardDescription` | Primary wrapper for grouped content |
| Flex/Grid spacing | Tailwind classes | Don't create custom layout divs |
| Sidebar sections | `Sidebar` component suite | Already integrated |
| Tabs | `Tabs/TabsList/TabsTrigger/TabsContent` | Use for multi-section views |
| Accordion | `Accordion` | For collapsible sections |
| Dialog/Modal | `Dialog` | For modal windows |
| Drawer | `Drawer` | For side panels (mobile-friendly) |
| Empty state | `Empty` | No results / placeholder state |

### Status & Feedback
| Need | Use | Notes |
|------|-----|-------|
| Alert/banner | `Alert/AlertTitle/AlertDescription` | For warnings, errors, info |
| Badge | `Badge` | Status indicators, labels |
| Progress bar | `Progress` | Visual progress indicator |
| Skeleton | `Skeleton` | Loading placeholder |
| Spinner | `Spinner` | Loading animation |
| Tooltip | `Tooltip` | Hover help text |
| Toast notification | `Sonner` | System notifications (configured) |

### Actions
| Need | Use | Notes |
|------|-----|-------|
| Button | `Button` | Variants: default, destructive, outline, ghost, secondary |
| Button group | `ButtonGroup` | For related button actions |
| Toggle button | `Toggle` | Button with on/off state |
| Toggle group | `ToggleGroup` | Group of toggles (radio-like) |
| Dropdown menu | `DropdownMenu` | Contextual menu |
| Pagination | `Pagination` | Table/list pagination |

---

## Style Customization Pattern

**Rule:** Use shadcn-vue components as the base structure, then add Tailwind classes for customization.

```vue
<!-- ❌ Don't do this -->
<div class="custom-card border-2 rounded-lg p-6">
  Content
</div>

<!-- ✅ Do this -->
<Card class="border-2 p-6">
  Content
</Card>
```

All shadcn components accept a `class` prop to merge Tailwind classes.

---

## CSS File Cleanup

### Remove Custom Class Files
These should be deleted or reduced as components are refactored:
- Any `*.module.css` or `*.css` files with custom `.field-group`, `.merchant-*`, `.required-mark`, etc.
- Move any semantic color/spacing logic into Tailwind or shadcn component variants

### Keep Semantic Tailwind
Keep utility classes like:
- `.sr-only` (screen reader only)
- `.rtl:` variants (RTL support)
- Custom spacing scales defined in `tailwind.config.ts`

---

## Workflow by Component Type

### 1. Form Components
- Use `FormField` wrapper from shadcn-vue
- Integrate with VeeValidate validator
- Replace `<div class="field-error">` with `<FormMessage />`
- Example: RequestForm.vue, all form-based pages

### 2. Layout/Container Components
- Replace `<div class="custom-container">` with `<Card>` or `<div class="...">` (Tailwind classes only)
- No custom wrapper divs — use Flex/Grid Tailwind classes directly
- Example: MerchantCard.vue, dashboard KPI cards

### 3. Data Display Components
- Use `Table` for tabular data
- Use `Card` for grouped displays
- Use `Badge` for status labels
- Example: any table or data list

### 4. Navigation/Menu Components
- Use `DropdownMenu` for contextual actions
- Use `Tabs` for section navigation
- Use `Sidebar` (already implemented)
- Example: action menus, multi-step wizards

---

## Testing & Validation

After each refactor:
1. **Visual regression:** Run Playwright screenshots, compare baselines
2. **Functionality:** Test form submission, input validation, error display
3. **Accessibility:** Verify keyboard navigation, ARIA labels
4. **RTL:** Confirm right-to-left layout works (no LTR hardcoding)

---

## Next Steps

1. Start with RequestForm.vue (most visible, highest impact)
2. Refactor WizardStep1.vue, WizardStep2.vue to match
3. Update MerchantCard.vue and related merchant components
4. Scan remaining 384 components for other custom class patterns
5. Run full test suite + Playwright screenshots to validate

---

## Available shadcn-vue Components (Full List)

All components in `frontend/app/components/ui/`:
- accordion, alert, alert-dialog, aspect-ratio, avatar, badge
- breadcrumb, button, button-group, calendar, card, carousel, chart
- checkbox, collapsible, combobox, command, context-menu, dialog, drawer
- empty, field, form, hover-card, input, input-group, input-otp
- item, kbd, label, menubar, native-select, navigation-menu, number-field
- pagination, pin-input, popover, progress, radio-group, range-calendar, resizable
- scroll-area, select, separator, sheet, sidebar, skeleton, slider
- sonner, spinner, stepper, switch, table, tabs, tags-input, textarea
- toggle, toggle-group, tooltip

**Total: 50+ production-ready components**
