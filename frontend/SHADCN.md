# shadcn-vue Component Reference — Yemen Flow Hub

> **AI INSTRUCTION:** Read this file in full before writing any Vue template code. Every component listed here is already installed at `frontend/app/components/ui/`. Using raw HTML instead of these components is a violation. Import always from `@/components/ui/<name>`.

---

## Quick Decision Table

| Need                                             | Component to use                                                                           | Never write                             |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------ | --------------------------------------- |
| Any button / clickable action                    | `<Button>`                                                                                 | `<button class="...">`                  |
| Multi-line nav/action tile (icon + title + desc) | `<Card role="button" tabindex="0">`                                                        | `<button class="flex flex-col ...">`    |
| Data table (any table)                           | `<Table>` + `<TableHeader>` + `<TableBody>` + `<TableRow>` + `<TableHead>` + `<TableCell>` | `<table><thead><tbody><tr><th><td>`     |
| Full table with sorting/filtering/pagination     | TanStack `useVueTable` + shadcn `Table` primitives                                         | Custom table implementation             |
| Status chip / label                              | `<Badge>`                                                                                  | `<span class="rounded-full px-2 ...">`  |
| Loading placeholder                              | `<Skeleton>`                                                                               | `<div class="animate-pulse ...">`       |
| Spinner (inline loading)                         | `<Spinner>`                                                                                | `<div class="animate-spin ...">`        |
| Error / info message                             | `<Alert>` + `<AlertDescription>`                                                           | `<div class="rounded border ...">`      |
| Overlay dialog                                   | `<Dialog>`                                                                                 | custom modal div                        |
| Destructive confirmation                         | `<AlertDialog>`                                                                            | `<Dialog>` or `window.confirm()`        |
| Slide-in side panel                              | `<Sheet>`                                                                                  | custom drawer div                       |
| Contextual menu                                  | `<DropdownMenu>`                                                                           | custom `ul/li` popup                    |
| Hover tooltip                                    | `<Tooltip>` + `<TooltipTrigger>` + `<TooltipContent>`                                      | `title` attribute or custom div         |
| Tab navigation                                   | `<Tabs>` + `<TabsList>` + `<TabsTrigger>` + `<TabsContent>`                                | custom button tab row                   |
| Form field + label + validation                  | `<FormField>` + `<FormItem>` + `<FormLabel>` + `<FormControl>` + `<FormMessage>`           | raw `<label><input>`                    |
| Text input                                       | `<Input>`                                                                                  | `<input class="...">`                   |
| Textarea                                         | `<Textarea>`                                                                               | `<textarea class="...">`                |
| Select / dropdown                                | `<Select>` + sub-components                                                                | `<select class="...">`                  |
| Searchable select                                | `<Combobox>` + sub-components                                                              | custom input+list                       |
| Checkbox                                         | `<Checkbox>`                                                                               | `<input type="checkbox">`               |
| Toggle switch                                    | `<Switch>`                                                                                 | `<input type="checkbox" role="switch">` |
| Card container                                   | `<Card>` + `<CardHeader>` + `<CardContent>` + `<CardFooter>`                               | `<div class="rounded border p-4">`      |
| Collapsible section                              | `<Collapsible>` + `<CollapsibleTrigger>` + `<CollapsibleContent>`                          | custom show/hide div                    |
| Progress bar                                     | `<Progress>`                                                                               | `<div style="width: X%">`               |
| Separator line                                   | `<Separator>`                                                                              | `<hr>` or `<div class="border-t">`      |
| Keyboard shortcut label                          | `<Kbd>`                                                                                    | `<span class="...">`                    |
| Empty / zero state                               | `<Empty>` + `<EmptyMedia>` + `<EmptyTitle>` + `<EmptyDescription>`                         | `<div class="text-center ...">`         |
| User avatar                                      | `<Avatar>` + `<AvatarImage>` + `<AvatarFallback>`                                          | `<img class="rounded-full">`            |
| Stepped wizard                                   | `<Stepper>` + `<StepperItem>` + `<StepperTrigger>` + `<StepperIndicator>`                  | custom step indicator                   |
| Pagination controls                              | `<Pagination>` + sub-components                                                            | custom prev/next buttons                |
| Scrollable area                                  | `<ScrollArea>`                                                                             | `<div class="overflow-auto">`           |
| Input with icon/addon                            | `<InputGroup>` + `<InputGroupInput>` + `<InputGroupAddon>`                                 | `<div class="relative"><input>`         |
| Grouped buttons                                  | `<ButtonGroup>` + `<Button>` items                                                         | `<div class="flex">` of raw buttons     |
| Accordion                                        | `<Accordion>` + `<AccordionItem>` + `<AccordionTrigger>` + `<AccordionContent>`            | custom show/hide                        |
| Toast notification                               | `<Sonner>` (via `toast()`)                                                                 | custom notification div                 |
| Field layout (non-form)                          | `<Field>` + `<FieldLabel>` + `<FieldError>`                                                | raw label+div                           |

---

## Component Recipes — Copy-Paste Ready

### Button

```vue
<script setup>
import { Button } from '@/components/ui/button'
</script>

<!-- Primary CTA -->
<Button @click="submit">إرسال الطلب</Button>

<!-- Outline / secondary -->
<Button variant="outline" @click="cancel">إلغاء</Button>

<!-- Ghost / tertiary -->
<Button variant="ghost" size="sm" @click="refresh">تحديث</Button>

<!-- Destructive -->
<Button variant="destructive" @click="deleteItem">حذف</Button>

<!-- Icon-only (always add aria-label) -->
<Button variant="ghost" size="icon" aria-label="تحديث">
  <RefreshCw class="h-4 w-4" />
</Button>

<!-- Small row-action button -->
<Button size="sm" variant="outline" @click="viewRequest">عرض</Button>

<!-- Loading state — disable during async -->
<Button :disabled="loading" @click="submit">
  <Spinner v-if="loading" class="me-2 h-4 w-4" />
  {{ loading ? 'جارٍ الإرسال…' : 'إرسال' }}
</Button>

<!-- Custom color override (voting, severity) -->
<Button class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90" @click="openVoting">
  فتح جلسة التصويت
</Button>
```

**Variants:** `default` | `outline` | `secondary` | `ghost` | `destructive` | `link`  
**Sizes:** `default` | `sm` | `lg` | `xs` | `icon` | `icon-sm` | `icon-xs` | `icon-lg`

---

### ButtonGroup (grouped actions)

```vue
<script setup>
import { ButtonGroup, ButtonGroupSeparator } from '@/components/ui/button-group'
import { Button } from '@/components/ui/button'
</script>

<ButtonGroup>
  <Button variant="outline" size="sm">موافقة</Button>
  <ButtonGroupSeparator />
  <Button variant="outline" size="sm">رفض</Button>
  <ButtonGroupSeparator />
  <Button variant="outline" size="sm">إعادة</Button>
</ButtonGroup>
```

---

### Card (container)

```vue
<script setup>
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  CardFooter,
  CardAction,
} from '@/components/ui/card'
</script>

<!-- Standard section card -->
<Card class="border-0 shadow" aria-labelledby="section-heading">
  <CardHeader class="pb-2">
    <CardTitle id="section-heading" class="text-sm font-semibold">عنوان القسم</CardTitle>
    <CardDescription class="text-xs">وصف إضافي اختياري</CardDescription>
  </CardHeader>
  <CardContent class="p-4">
    <!-- content -->
  </CardContent>
  <CardFooter class="pt-2">
    <Button size="sm">إجراء</Button>
  </CardFooter>
</Card>

<!-- KPI card (clickable, navigates to filtered list) -->
<Card
  class="flex cursor-pointer flex-col gap-1.5 border-0 p-4 shadow transition-shadow hover:shadow-md"
  role="button"
  tabindex="0"
  aria-label="`${label}: ${value}`"
  @click="router.push(`/requests?tab=${tab}`)"
  @keydown.enter="router.push(`/requests?tab=${tab}`)"
  @keydown.space.prevent="router.push(`/requests?tab=${tab}`)"
>
  <div class="h-9 w-9 rounded flex items-center justify-center bg-[var(--severity-green)]/10">
    <CheckCircle2 class="h-5 w-5 text-[var(--severity-green)]" aria-hidden="true" />
  </div>
  <span class="text-2xl font-semibold leading-none text-[var(--severity-green)]">{{ value }}</span>
  <span class="text-xs text-muted-foreground">{{ label }}</span>
</Card>

<!-- Quick-action tile (multi-line: icon + title + description) -->
<!-- MUST use Card, not Button — Button doesn't support multi-line slot layout -->
<Card
  class="bg-primary text-primary-foreground flex cursor-pointer flex-col items-start gap-1 border-0 p-4 shadow transition-shadow hover:shadow-md"
  role="button"
  tabindex="0"
  @click="router.push('/requests/new')"
  @keydown.enter="router.push('/requests/new')"
  @keydown.space.prevent="router.push('/requests/new')"
>
  <FileText class="h-5 w-5 mb-1" aria-hidden="true" />
  <span class="text-sm font-semibold">إنشاء طلب جديد</span>
  <span class="text-xs opacity-75">لبدء طلب تمويل جديد</span>
</Card>

<!-- Quick-action tile (secondary style) -->
<Card
  class="flex cursor-pointer flex-col items-start gap-1 border-0 p-4 shadow transition-shadow hover:shadow-md"
  role="button"
  tabindex="0"
  @click="router.push('/requests')"
  @keydown.enter="router.push('/requests')"
  @keydown.space.prevent="router.push('/requests')"
>
  <FileText class="h-5 w-5 mb-1 text-primary" aria-hidden="true" />
  <span class="text-sm font-semibold">متابعة طلباتي</span>
  <span class="text-xs text-muted-foreground">كل ما قدّمت</span>
</Card>
```

---

### Table (dashboard queue — no sorting/filtering)

Use shadcn Table primitives directly for small dashboard queues (5–8 rows).

```vue
<script setup>
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  TableEmpty,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import StatusBadge from '@/components/shared/StatusBadge.vue'
</script>

<Card class="border-0 shadow" aria-labelledby="queue-heading">
  <CardHeader class="pb-2">
    <div class="flex items-center justify-between">
      <CardTitle id="queue-heading" class="text-sm font-semibold">طابور العمل</CardTitle>
      <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests')">عرض الكل</Button>
    </div>
  </CardHeader>
  <CardContent class="p-0">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead class="text-right">المرجع</TableHead>
          <TableHead class="text-right">المورد</TableHead>
          <TableHead class="text-right">المبلغ</TableHead>
          <TableHead class="text-right">الحالة</TableHead>
          <TableHead class="text-right">إجراء</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <TableRow
          v-for="req in queue.slice(0, 8)"
          :key="req.id"
          class="cursor-pointer"
          @click="router.push(`/requests/${req.id}`)"
        >
          <TableCell class="font-mono text-primary">{{ req.reference_number }}</TableCell>
          <TableCell>{{ req.supplier_name }}</TableCell>
          <TableCell class="font-mono">{{ formatAmount(req.amount, req.currency) }}</TableCell>
          <TableCell><StatusBadge :status="req.status" :role="userRole" /></TableCell>
          <TableCell @click.stop>
            <Button size="sm" variant="outline" @click="router.push(`/requests/${req.id}`)">عرض</Button>
          </TableCell>
        </TableRow>
        <TableEmpty v-if="queue.length === 0" :columns="5">
          لا توجد طلبات في الطابور حالياً ✓
        </TableEmpty>
      </TableBody>
    </Table>
  </CardContent>
</Card>
```

### Table (full data table — with sorting, filtering, pagination)

Use TanStack `useVueTable` + shadcn Table primitives. See `RequestsDataTable.vue` as the reference implementation.

```vue
<script setup>
import {
  useVueTable,
  getCoreRowModel,
  getSortedRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  FlexRender,
} from '@tanstack/vue-table'
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  TableEmpty,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
</script>
```

---

### Badge

```vue
<script setup>
import { Badge } from '@/components/ui/badge'
</script>

<!-- Default (primary blue) -->
<Badge>نشط</Badge>

<!-- Secondary (muted) -->
<Badge variant="secondary">معلّق</Badge>

<!-- Destructive (red) -->
<Badge variant="destructive">مرفوض</Badge>

<!-- Outline -->
<Badge variant="outline">مسودة</Badge>

<!-- Custom semantic color (when StatusBadge is not appropriate) -->
<Badge class="border border-[var(--voting)]/30 bg-[var(--voting)]/10 text-[var(--voting)]">
  تصويت مفتوح
</Badge>
<Badge
  class="border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
>
  مكتمل
</Badge>
<Badge
  class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]"
>
  قيد المراجعة
</Badge>
```

---

### Skeleton

```vue
<script setup>
import { Skeleton } from '@/components/ui/skeleton'
</script>

<!-- KPI card skeleton -->
<div class="grid grid-cols-4 gap-4">
  <Skeleton v-for="n in 4" :key="n" class="h-24 w-full rounded-xl" />
</div>

<!-- Table row skeleton -->
<TableRow v-for="n in 5" :key="n">
  <TableCell><Skeleton class="h-4 w-24" /></TableCell>
  <TableCell><Skeleton class="h-4 w-32" /></TableCell>
  <TableCell><Skeleton class="h-4 w-20" /></TableCell>
</TableRow>

<!-- Card content skeleton -->
<CardContent class="p-4">
  <Skeleton class="h-4 w-1/3 mb-2" />
  <Skeleton class="h-8 w-16" />
</CardContent>
```

---

### Alert (error / info banners)

```vue
<script setup>
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { AlertCircle, Info } from 'lucide-vue-next'
</script>

<!-- Error state with retry -->
<Alert variant="destructive" role="alert">
  <AlertCircle class="h-4 w-4" />
  <AlertTitle>خطأ في التحميل</AlertTitle>
  <AlertDescription>{{ store.error }}</AlertDescription>
  <AlertAction>
    <Button variant="outline" size="sm" @click="store.loadStats()">إعادة المحاولة</Button>
  </AlertAction>
</Alert>

<!-- Info state -->
<Alert>
  <Info class="h-4 w-4" />
  <AlertTitle>ملاحظة</AlertTitle>
  <AlertDescription>هذا الطلب في انتظار موافقة المراجع.</AlertDescription>
</Alert>
```

---

### Dialog (non-destructive modal)

```vue
<script setup>
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

const open = ref(false)
</script>

<Dialog v-model:open="open">
  <DialogTrigger as-child>
    <Button>فتح النموذج</Button>
  </DialogTrigger>
  <DialogContent class="max-w-lg">
    <DialogHeader>
      <DialogTitle>إضافة تعليق</DialogTitle>
      <DialogDescription>أضف ملاحظة على هذا الطلب</DialogDescription>
    </DialogHeader>

    <!-- Form content -->
    <Textarea v-model="comment" placeholder="أدخل ملاحظاتك هنا…" />

    <DialogFooter>
      <DialogClose as-child>
        <Button variant="outline">إلغاء</Button>
      </DialogClose>
      <Button @click="save">حفظ</Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

---

### AlertDialog (destructive confirmation — MANDATORY for irreversible actions)

```vue
<script setup>
import {
  AlertDialog,
  AlertDialogTrigger,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
</script>

<AlertDialog>
  <AlertDialogTrigger as-child>
    <Button variant="destructive">رفض الطلب</Button>
  </AlertDialogTrigger>
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>تأكيد رفض الطلب</AlertDialogTitle>
      <AlertDialogDescription>
        سيتم رفض الطلب نهائياً ولا يمكن التراجع عن هذا القرار.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>إلغاء</AlertDialogCancel>
      <AlertDialogAction @click="confirmReject">تأكيد الرفض</AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

**Rule:** Always use `AlertDialog` (not `Dialog`) for: reject request, delete record, revoke claim, any irreversible action.

---

### Sheet (slide-in side panel)

```vue
<script setup>
import {
  Sheet,
  SheetTrigger,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
  SheetClose,
} from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
</script>

<Sheet>
  <SheetTrigger as-child>
    <Button variant="outline">عرض التفاصيل</Button>
  </SheetTrigger>
  <SheetContent side="right" class="w-[480px]">
    <SheetHeader>
      <SheetTitle>تفاصيل الطلب</SheetTitle>
      <SheetDescription>مراجعة معلومات الطلب الكاملة</SheetDescription>
    </SheetHeader>
    <!-- content -->
    <SheetFooter>
      <SheetClose as-child>
        <Button variant="outline">إغلاق</Button>
      </SheetClose>
    </SheetFooter>
  </SheetContent>
</Sheet>
```

Use Sheet for: document preview panels, request detail quick-view, filter panels.

---

### DropdownMenu

```vue
<script setup>
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import { MoreHorizontal } from 'lucide-vue-next'
</script>

<DropdownMenu>
  <DropdownMenuTrigger as-child>
    <Button variant="ghost" size="icon" aria-label="إجراءات">
      <MoreHorizontal class="h-4 w-4" />
    </Button>
  </DropdownMenuTrigger>
  <DropdownMenuContent align="end">
    <DropdownMenuLabel>الإجراءات</DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuItem @click="viewRequest">عرض الطلب</DropdownMenuItem>
    <DropdownMenuItem @click="editRequest">تعديل</DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem class="text-destructive" @click="openRejectDialog">رفض</DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>
```

Use DropdownMenu for: row action menus (…), user profile menus, bulk action selectors.

---

### Tabs

```vue
<script setup>
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
</script>

<!-- Standard tabs (request detail: info / documents / parties / voting) -->
<Tabs default-value="info" dir="rtl">
  <TabsList>
    <TabsTrigger value="info">المعلومات</TabsTrigger>
    <TabsTrigger value="documents">الوثائق</TabsTrigger>
    <TabsTrigger value="parties">الأطراف</TabsTrigger>
    <TabsTrigger value="voting">التصويت</TabsTrigger>
  </TabsList>
  <TabsContent value="info" class="mt-4"><!-- info panel --></TabsContent>
  <TabsContent value="documents" class="mt-4"><!-- docs panel --></TabsContent>
  <TabsContent value="parties" class="mt-4"><!-- parties panel --></TabsContent>
  <TabsContent value="voting" class="mt-4"><!-- voting panel --></TabsContent>
</Tabs>

<!-- Line variant (requests page filter tabs) -->
<Tabs v-model="activeTab">
  <TabsList variant="line">
    <TabsTrigger value="all">الكل</TabsTrigger>
    <TabsTrigger value="pending">قيد المراجعة</TabsTrigger>
    <TabsTrigger value="completed">مكتمل</TabsTrigger>
  </TabsList>
</Tabs>
```

---

### Form (VeeValidate + shadcn)

```vue
<script setup>
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import * as z from 'zod'
import {
  Form,
  FormField,
  FormItem,
  FormLabel,
  FormControl,
  FormMessage,
  FormDescription,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select'
import { Button } from '@/components/ui/button'

const schema = toTypedSchema(
  z.object({
    supplier_name: z.string().min(2, 'الاسم مطلوب'),
    amount: z.number().positive('المبلغ يجب أن يكون موجباً'),
    currency: z.string(),
    notes: z.string().optional(),
  }),
)

const form = useForm({ validationSchema: schema })
const onSubmit = form.handleSubmit(async (values) => {
  /* submit */
})
</script>

<form @submit="onSubmit" class="flex flex-col gap-4">
  <!-- Text input -->
  <FormField v-slot="{ componentField }" name="supplier_name">
    <FormItem>
      <FormLabel>اسم المورد</FormLabel>
      <FormControl>
        <Input v-bind="componentField" placeholder="أدخل اسم المورد" />
      </FormControl>
      <FormMessage />
    </FormItem>
  </FormField>

  <!-- Select -->
  <FormField v-slot="{ componentField }" name="currency">
    <FormItem>
      <FormLabel>العملة</FormLabel>
      <Select v-bind="componentField">
        <FormControl>
          <SelectTrigger>
            <SelectValue placeholder="اختر العملة" />
          </SelectTrigger>
        </FormControl>
        <SelectContent>
          <SelectItem value="USD">دولار أمريكي (USD)</SelectItem>
          <SelectItem value="EUR">يورو (EUR)</SelectItem>
          <SelectItem value="SAR">ريال سعودي (SAR)</SelectItem>
        </SelectContent>
      </Select>
      <FormMessage />
    </FormItem>
  </FormField>

  <!-- Textarea -->
  <FormField v-slot="{ componentField }" name="notes">
    <FormItem>
      <FormLabel>ملاحظات</FormLabel>
      <FormControl>
        <Textarea v-bind="componentField" placeholder="أضف ملاحظاتك هنا…" rows="4" />
      </FormControl>
      <FormDescription>اختياري — تظهر في سجل المراجعة</FormDescription>
      <FormMessage />
    </FormItem>
  </FormField>

  <Button type="submit" :disabled="form.isSubmitting.value">
    {{ form.isSubmitting.value ? 'جارٍ الحفظ…' : 'حفظ' }}
  </Button>
</form>
```

---

### Input with Icon (InputGroup)

```vue
<script setup>
import { InputGroup, InputGroupInput, InputGroupAddon } from '@/components/ui/input-group'
import { Search } from 'lucide-vue-next'
</script>

<!-- Search input with leading icon -->
<InputGroup>
  <InputGroupAddon align="inline-start">
    <Search class="h-4 w-4" />
  </InputGroupAddon>
  <InputGroupInput v-model="searchQuery" placeholder="بحث…" />
</InputGroup>

<!-- Input with trailing button (e.g. copy, clear) -->
<InputGroup>
  <InputGroupInput v-model="value" placeholder="أدخل القيمة" />
  <InputGroupAddon align="inline-end">
    <InputGroupButton @click="clearValue">
      <X class="h-3.5 w-3.5" />
    </InputGroupButton>
  </InputGroupAddon>
</InputGroup>
```

---

### Tooltip

```vue
<script setup>
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/components/ui/tooltip'
import { Button } from '@/components/ui/button'
</script>

<!-- Always wrap in TooltipProvider at app/layout level (already done in app.vue) -->
<Tooltip>
  <TooltipTrigger as-child>
    <Button variant="ghost" size="icon" aria-label="تحديث">
      <RefreshCw class="h-4 w-4" />
    </Button>
  </TooltipTrigger>
  <TooltipContent>
    <p>تحديث البيانات</p>
  </TooltipContent>
</Tooltip>
```

---

### Select (standalone, outside form)

```vue
<script setup>
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
  SelectGroup,
  SelectLabel,
  SelectSeparator,
} from '@/components/ui/select'

const selected = ref('')
</script>

<Select v-model="selected">
  <SelectTrigger class="w-48">
    <SelectValue placeholder="اختر الحالة" />
  </SelectTrigger>
  <SelectContent>
    <SelectGroup>
      <SelectLabel>حالات البنك</SelectLabel>
      <SelectItem value="BANK_REVIEW">قيد المراجعة</SelectItem>
      <SelectItem value="BANK_APPROVED">معتمد</SelectItem>
      <SelectItem value="BANK_REJECTED">مرفوض</SelectItem>
    </SelectGroup>
    <SelectSeparator />
    <SelectGroup>
      <SelectLabel>حالات CBY</SelectLabel>
      <SelectItem value="SUPPORT_REVIEW_PENDING">قيد المساندة</SelectItem>
    </SelectGroup>
  </SelectContent>
</Select>
```

---

### Combobox (searchable select)

```vue
<script setup>
import {
  Combobox,
  ComboboxAnchor,
  ComboboxInput,
  ComboboxList,
  ComboboxItem,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItemIndicator,
  ComboboxTrigger,
} from '@/components/ui/combobox'
import { Check, ChevronsUpDown } from 'lucide-vue-next'

const value = ref('')
const banks = ref([
  { value: 'yib', label: 'البنك اليمني الدولي' },
  { value: 'cac', label: 'بنك CAC' },
])
</script>

<Combobox v-model="value" by="value">
  <ComboboxAnchor>
    <ComboboxInput placeholder="اختر البنك…" class="w-full" />
    <ComboboxTrigger>
      <ChevronsUpDown class="h-4 w-4 text-muted-foreground" />
    </ComboboxTrigger>
  </ComboboxAnchor>
  <ComboboxList>
    <ComboboxEmpty>لا توجد نتائج</ComboboxEmpty>
    <ComboboxGroup>
      <ComboboxItem v-for="bank in banks" :key="bank.value" :value="bank">
        {{ bank.label }}
        <ComboboxItemIndicator>
          <Check class="h-4 w-4" />
        </ComboboxItemIndicator>
      </ComboboxItem>
    </ComboboxGroup>
  </ComboboxList>
</Combobox>
```

---

### Empty State

```vue
<script setup>
import {
  Empty,
  EmptyMedia,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyContent,
} from '@/components/ui/empty'
import { Button } from '@/components/ui/button'
import { InboxIcon } from 'lucide-vue-next'
</script>

<!-- Inside a card or table -->
<Empty>
  <EmptyMedia variant="icon">
    <InboxIcon />
  </EmptyMedia>
  <EmptyHeader>
    <EmptyTitle>لا توجد طلبات</EmptyTitle>
    <EmptyDescription>لم يتم تقديم أي طلبات بعد. ابدأ بإنشاء طلب جديد.</EmptyDescription>
  </EmptyHeader>
  <EmptyContent>
    <Button @click="router.push('/requests/new')">إنشاء طلب جديد</Button>
  </EmptyContent>
</Empty>

<!-- Healthy empty queue (no CTA needed) -->
<Empty>
  <EmptyMedia variant="icon">
    <CheckCircle2 />
  </EmptyMedia>
  <EmptyHeader>
    <EmptyTitle>الطابور فارغ</EmptyTitle>
    <EmptyDescription>لا توجد طلبات في انتظار المراجعة حالياً ✓</EmptyDescription>
  </EmptyHeader>
</Empty>
```

---

### Avatar

```vue
<script setup>
import {
  Avatar,
  AvatarImage,
  AvatarFallback,
  AvatarGroup,
  AvatarGroupCount,
} from '@/components/ui/avatar'
</script>

<!-- Single user avatar -->
<Avatar>
  <AvatarImage src="/avatar.jpg" alt="اسم المستخدم" />
  <AvatarFallback>م س</AvatarFallback>
</Avatar>

<!-- Small size -->
<Avatar data-size="sm">
  <AvatarFallback>أ</AvatarFallback>
</Avatar>

<!-- Group of voters -->
<AvatarGroup>
  <Avatar v-for="voter in voters" :key="voter.id">
    <AvatarFallback>{{ voter.initials }}</AvatarFallback>
  </Avatar>
  <AvatarGroupCount :count="remainingCount" />
</AvatarGroup>
```

---

### Stepper (multi-step wizard)

```vue
<script setup>
import {
  Stepper,
  StepperItem,
  StepperTrigger,
  StepperIndicator,
  StepperTitle,
  StepperDescription,
  StepperSeparator,
} from '@/components/ui/stepper'

const currentStep = ref(1)
const steps = [
  { step: 1, title: 'معلومات الطلب', description: 'البيانات الأساسية' },
  { step: 2, title: 'المستندات', description: 'رفع الوثائق المطلوبة' },
  { step: 3, title: 'المراجعة', description: 'مراجعة وإرسال' },
]
</script>

<Stepper v-model="currentStep" class="w-full">
  <StepperItem
    v-for="step in steps"
    :key="step.step"
    :step="step.step"
    class="flex-1"
  >
    <StepperTrigger>
      <StepperIndicator />
      <div class="flex flex-col gap-0.5 text-start">
        <StepperTitle>{{ step.title }}</StepperTitle>
        <StepperDescription>{{ step.description }}</StepperDescription>
      </div>
    </StepperTrigger>
    <StepperSeparator v-if="step.step < steps.length" />
  </StepperItem>
</Stepper>
```

---

### Pagination

```vue
<script setup>
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationPrevious,
  PaginationNext,
  PaginationLink,
  PaginationEllipsis,
} from '@/components/ui/pagination'

const currentPage = ref(1)
const totalPages = ref(10)
</script>

<Pagination
  v-model:page="currentPage"
  :total="totalPages * 10"
  :items-per-page="10"
  :sibling-count="1"
  show-edges
>
  <PaginationContent>
    <PaginationItem>
      <PaginationPrevious />
    </PaginationItem>
    <!-- PaginationLink items are rendered automatically -->
    <PaginationItem>
      <PaginationNext />
    </PaginationItem>
  </PaginationContent>
</Pagination>
```

---

### Switch (settings toggles)

```vue
<script setup>
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
</script>

<div class="flex items-center gap-3">
  <Switch id="notifications" v-model:checked="notificationsEnabled" />
  <Label for="notifications">تفعيل الإشعارات</Label>
</div>
```

---

### Checkbox

```vue
<script setup>
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
</script>

<div class="flex items-center gap-2">
  <Checkbox id="agree" v-model:checked="agreed" />
  <Label for="agree">أوافق على الشروط والأحكام</Label>
</div>
```

---

### Progress

```vue
<script setup>
import { Progress } from '@/components/ui/progress'
</script>

<!-- Workflow progress (% of stages completed) -->
<Progress :model-value="progressPercent" class="h-1.5" />

<!-- With label -->
<div class="flex items-center gap-2">
  <Progress :model-value="progressPercent" class="h-1.5 flex-1" />
  <span class="text-xs text-muted-foreground whitespace-nowrap">{{ progressPercent }}%</span>
</div>
```

---

### Collapsible (expandable section)

```vue
<script setup>
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible'
import { Button } from '@/components/ui/button'
import { ChevronDown } from 'lucide-vue-next'

const open = ref(false)
</script>

<Collapsible v-model:open="open">
  <div class="flex items-center justify-between">
    <h3 class="text-sm font-semibold">المعلومات الإضافية</h3>
    <CollapsibleTrigger as-child>
      <Button variant="ghost" size="icon">
        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" />
      </Button>
    </CollapsibleTrigger>
  </div>
  <CollapsibleContent class="mt-2">
    <!-- collapsed content -->
  </CollapsibleContent>
</Collapsible>
```

---

### Separator

```vue
<script setup>
import { Separator } from '@/components/ui/separator'
</script>

<!-- Horizontal divider between sections -->
<Separator class="my-4" />

<!-- Vertical divider between inline items -->
<div class="flex items-center gap-2">
  <span>مكتمل</span>
  <Separator orientation="vertical" class="h-4" />
  <span>{{ count }} طلب</span>
</div>
```

---

### ScrollArea (fixed-height scrollable list)

```vue
<script setup>
import { ScrollArea } from '@/components/ui/scroll-area'
</script>

<!-- Notification list, audit log, etc. -->
<ScrollArea class="h-64">
  <div v-for="item in items" :key="item.id" class="p-3 border-b border-border last:border-0">
    {{ item.message }}
  </div>
</ScrollArea>
```

---

### Accordion (FAQ, settings sections)

```vue
<script setup>
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
} from '@/components/ui/accordion'
</script>

<Accordion type="single" collapsible>
  <AccordionItem value="workflow">
    <AccordionTrigger>إعدادات سير العمل</AccordionTrigger>
    <AccordionContent>
      <!-- workflow settings -->
    </AccordionContent>
  </AccordionItem>
  <AccordionItem value="notifications">
    <AccordionTrigger>إعدادات الإشعارات</AccordionTrigger>
    <AccordionContent>
      <!-- notification settings -->
    </AccordionContent>
  </AccordionItem>
</Accordion>
```

---

### Sonner (toast notifications)

```vue
<script setup>
import { toast } from 'vue-sonner'
</script>

<!-- Success -->
toast.success('تم إرسال الطلب بنجاح')

<!-- Error -->
toast.error('فشل في إرسال الطلب. حاول مجدداً.')

<!-- Info -->
toast.info('تم تحديث حالة الطلب')

<!-- With action -->
toast('تم رفض الطلب', { description: 'يمكنك مراجعة سبب الرفض في تفاصيل الطلب', action: { label: 'عرض
التفاصيل', onClick: () => router.push(`/requests/${id}`), }, })
```

---

## Import Path Reference

Always import from `@/components/ui/<component-folder-name>`. Exact named exports:

```ts
// Buttons
import { Button } from '@/components/ui/button'
import { ButtonGroup, ButtonGroupSeparator } from '@/components/ui/button-group'

// Layout containers
import {
  Card,
  CardHeader,
  CardTitle,
  CardDescription,
  CardContent,
  CardFooter,
  CardAction,
} from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area'

// Data display
import { Badge } from '@/components/ui/badge'
import {
  Avatar,
  AvatarImage,
  AvatarFallback,
  AvatarGroup,
  AvatarGroupCount,
} from '@/components/ui/avatar'
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  TableEmpty,
  TableCaption,
  TableFooter,
} from '@/components/ui/table'
import { Progress } from '@/components/ui/progress'

// Feedback
import { Skeleton } from '@/components/ui/skeleton'
import { Spinner } from '@/components/ui/spinner'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import {
  Empty,
  EmptyMedia,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyContent,
} from '@/components/ui/empty'

// Overlays
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogTrigger,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
  AlertDialogAction,
} from '@/components/ui/alert-dialog'
import {
  Sheet,
  SheetTrigger,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
  SheetClose,
} from '@/components/ui/sheet'

// Menus & navigation
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
  DropdownMenuGroup,
} from '@/components/ui/dropdown-menu'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/components/ui/tooltip'

// Forms
import {
  Form,
  FormField,
  FormItem,
  FormLabel,
  FormControl,
  FormMessage,
  FormDescription,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
  SelectGroup,
  SelectLabel,
  SelectSeparator,
} from '@/components/ui/select'
import {
  Combobox,
  ComboboxAnchor,
  ComboboxInput,
  ComboboxList,
  ComboboxItem,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItemIndicator,
  ComboboxTrigger,
} from '@/components/ui/combobox'
import { Checkbox } from '@/components/ui/checkbox'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'
import {
  InputGroup,
  InputGroupInput,
  InputGroupAddon,
  InputGroupButton,
  InputGroupText,
} from '@/components/ui/input-group'

// Structure
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
} from '@/components/ui/accordion'
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/components/ui/collapsible'
import {
  Stepper,
  StepperItem,
  StepperTrigger,
  StepperIndicator,
  StepperTitle,
  StepperDescription,
  StepperSeparator,
} from '@/components/ui/stepper'
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationPrevious,
  PaginationNext,
  PaginationLink,
  PaginationEllipsis,
} from '@/components/ui/pagination'

// Field layout (non-vee-validate)
import {
  Field,
  FieldLabel,
  FieldError,
  FieldDescription,
  FieldGroup,
  FieldContent,
  FieldSet,
  FieldSeparator,
} from '@/components/ui/field'
```

---

## Absolute Rules for AI

1. **No raw HTML buttons.** Every `<button>` must be `<Button>`. No exceptions.
2. **No raw HTML tables.** Every `<table>/<thead>/<tbody>/<tr>/<th>/<td>` must be `<Table>/<TableHeader>/<TableBody>/<TableRow>/<TableHead>/<TableCell>`.
3. **No `animate-pulse` divs.** Every loading placeholder is `<Skeleton>`.
4. **No custom error divs.** Every error state is `<Alert variant="destructive">`.
5. **No raw `<select>`.** Use `<Select>` (short list) or `<Combobox>` (searchable).
6. **No custom empty state divs.** Use `<Empty>` + sub-components.
7. **Quick-action tiles are `<Card role="button">`, not `<Button>`.** Button cannot hold multi-line slot content with icon + title + description stacked vertically.
8. **Destructive confirmations are `<AlertDialog>`, not `<Dialog>`.** Use `<Dialog>` for forms and non-destructive modals only.
9. **Row action menus are `<DropdownMenu>` with `<MoreHorizontal>` trigger**, not inline button clusters when there are 3+ actions.
10. **Variants over inline classes.** Use the component's built-in `variant` and `size` props before adding Tailwind overrides. Only add Tailwind for spacing adjustments or semantic color tokens.
