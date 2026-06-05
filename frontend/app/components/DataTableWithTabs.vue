<script setup lang="ts">
import { ref, computed } from 'vue'
import { ChevronDown, LayoutGrid, Plus, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuCheckboxItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Label } from '@/components/ui/label'

interface TableData {
  id: number
  header: string
  type: string
  status: string
  target: string
  limit: string
  reviewer: string
}

const props = defineProps<{
  data: TableData[]
}>()

// State management
const selectedRows = ref<Set<number>>(new Set())
const visibleColumns = ref<Set<string>>(
  new Set(['header', 'type', 'status', 'target', 'limit', 'reviewer']),
)
const pageSize = ref(10)
const currentPage = ref(0)
const sortColumn = ref<string | null>(null)
const sortDirection = ref<'asc' | 'desc'>('asc')
const activeTab = ref('outline')

// Computed properties
const sortedData = computed(() => {
  if (!sortColumn.value) return props.data

  return [...props.data].sort((a, b) => {
    const aVal = a[sortColumn.value as keyof TableData] ?? ''
    const bVal = b[sortColumn.value as keyof TableData] ?? ''

    const comparison = String(aVal).localeCompare(String(bVal))
    return sortDirection.value === 'asc' ? comparison : -comparison
  })
})

const paginatedData = computed(() => {
  const start = currentPage.value * pageSize.value
  const end = start + pageSize.value
  return sortedData.value.slice(start, end)
})

const totalPages = computed(() => Math.ceil(sortedData.value.length / pageSize.value))

const allColumnsVisible = [
  { key: 'header', label: 'Header' },
  { key: 'type', label: 'Section Type' },
  { key: 'status', label: 'Status' },
  { key: 'target', label: 'Target' },
  { key: 'limit', label: 'Limit' },
  { key: 'reviewer', label: 'Reviewer' },
]

// Methods
const toggleRowSelection = (id: number) => {
  if (selectedRows.value.has(id)) {
    selectedRows.value.delete(id)
  } else {
    selectedRows.value.add(id)
  }
}

const toggleAllRows = () => {
  if (selectedRows.value.size === paginatedData.value.length) {
    selectedRows.value.clear()
  } else {
    paginatedData.value.forEach((row) => selectedRows.value.add(row.id))
  }
}

const toggleColumnVisibility = (column: string) => {
  if (visibleColumns.value.has(column)) {
    visibleColumns.value.delete(column)
  } else {
    visibleColumns.value.add(column)
  }
}

const setSort = (column: string) => {
  if (sortColumn.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortColumn.value = column
    sortDirection.value = 'asc'
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value - 1) {
    currentPage.value++
  }
}

const prevPage = () => {
  if (currentPage.value > 0) {
    currentPage.value--
  }
}
</script>

<template>
  <Tabs v-model="activeTab" class="w-full">
    <!-- Header with controls -->
    <div class="flex flex-col gap-4 px-4 py-4 lg:px-6">
      <div class="flex items-center justify-between">
        <!-- Mobile: View selector -->
        <Select v-model="activeTab" class="lg:hidden">
          <SelectTrigger class="w-fit">
            <SelectValue placeholder="Select view" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="outline">Outline</SelectItem>
            <SelectItem value="past-performance">Past Performance</SelectItem>
            <SelectItem value="key-personnel">Key Personnel</SelectItem>
            <SelectItem value="focus-documents">Focus Documents</SelectItem>
          </SelectContent>
        </Select>

        <!-- Desktop: Tab list -->
        <TabsList class="hidden lg:flex">
          <TabsTrigger value="outline">Outline</TabsTrigger>
          <TabsTrigger value="past-performance">
            Past Performance
            <Badge variant="secondary" class="ms-2">3</Badge>
          </TabsTrigger>
          <TabsTrigger value="key-personnel">
            Key Personnel
            <Badge variant="secondary" class="ms-2">2</Badge>
          </TabsTrigger>
          <TabsTrigger value="focus-documents">Focus Documents</TabsTrigger>
        </TabsList>

        <!-- Controls -->
        <div class="flex gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <Button variant="outline" size="sm">
                <LayoutGrid class="size-4" data-icon="inline-start" />
                <span class="hidden lg:inline">Customize Columns</span>
                <span class="lg:hidden">Columns</span>
                <ChevronDown class="size-4" data-icon="inline-end" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
              <template v-for="column in allColumnsVisible" :key="column.key">
                <DropdownMenuCheckboxItem
                  :checked="visibleColumns.has(column.key)"
                  @update:checked="toggleColumnVisibility(column.key)"
                >
                  {{ column.label }}
                </DropdownMenuCheckboxItem>
              </template>
            </DropdownMenuContent>
          </DropdownMenu>

          <Button variant="outline" size="sm">
            <Plus class="size-4" data-icon="inline-start" />
            <span class="hidden lg:inline">Add Section</span>
          </Button>
        </div>
      </div>
    </div>

    <!-- Outline tab with table -->
    <TabsContent value="outline" class="flex flex-col gap-4 px-4 lg:px-6">
      <!-- Data table -->
      <div class="overflow-auto rounded-lg border">
        <Table>
          <TableHeader class="bg-muted sticky top-0">
            <TableRow>
              <!-- Checkbox column -->
              <TableHead class="w-12">
                <Checkbox
                  :checked="selectedRows.size === paginatedData.length && paginatedData.length > 0"
                  aria-label="Select all rows"
                  @update:checked="toggleAllRows"
                />
              </TableHead>

              <!-- Visible columns -->
              <TableHead
                v-for="column in allColumnsVisible"
                v-show="visibleColumns.has(column.key)"
                :key="column.key"
                class="hover:bg-muted-foreground/5 cursor-pointer transition-colors"
                @click="setSort(column.key)"
              >
                <div class="flex items-center gap-2">
                  {{ column.label }}
                  <span v-if="sortColumn === column.key" class="text-xs">
                    {{ sortDirection === 'asc' ? '↑' : '↓' }}
                  </span>
                </div>
              </TableHead>

              <!-- Actions column -->
              <TableHead />
            </TableRow>
          </TableHeader>

          <TableBody>
            <template v-if="paginatedData.length">
              <TableRow v-for="row in paginatedData" :key="row.id">
                <!-- Checkbox -->
                <TableCell>
                  <Checkbox
                    :checked="selectedRows.has(row.id)"
                    :aria-label="`Select row ${row.id}`"
                    @update:checked="toggleRowSelection(row.id)"
                  />
                </TableCell>

                <!-- Data cells -->
                <TableCell v-show="visibleColumns.has('header')">
                  {{ row.header }}
                </TableCell>
                <TableCell v-show="visibleColumns.has('type')">
                  <Badge variant="outline">{{ row.type }}</Badge>
                </TableCell>
                <TableCell v-show="visibleColumns.has('status')">
                  <div class="flex items-center gap-2">
                    <span
                      class="size-2 rounded-full"
                      :class="
                        row.status === 'Done'
                          ? 'bg-emerald-500'
                          : 'bg-muted-foreground animate-spin'
                      "
                    />
                    {{ row.status }}
                  </div>
                </TableCell>
                <TableCell v-show="visibleColumns.has('target')">
                  <code class="text-xs">{{ row.target }}</code>
                </TableCell>
                <TableCell v-show="visibleColumns.has('limit')">
                  <code class="text-xs">{{ row.limit }}</code>
                </TableCell>
                <TableCell v-show="visibleColumns.has('reviewer')">
                  {{ row.reviewer }}
                </TableCell>

                <!-- Actions -->
                <TableCell class="text-right">
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="ghost" size="sm">
                        <span class="sr-only">فتح قائمة الإجراءات</span>
                        ⋯
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem>تعديل</DropdownMenuItem>
                      <DropdownMenuItem>إنشاء نسخة</DropdownMenuItem>
                      <DropdownMenuItem>إضافة إلى المفضلة</DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem class="text-destructive"> حذف </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
            </template>
            <TableRow v-else>
              <TableCell :colspan="allColumnsVisible.length + 2" class="h-24 text-center">
                لا توجد نتائج مطابقة.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <!-- Pagination -->
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <!-- Selection info -->
        <div class="text-muted-foreground hidden text-sm lg:block">
          {{ selectedRows.size }} of {{ sortedData.length }} row(s) selected.
        </div>

        <!-- Pagination controls -->
        <div class="flex w-full flex-col gap-4 lg:w-fit lg:flex-row lg:items-center">
          <!-- Rows per page -->
          <div class="hidden items-center gap-2 lg:flex">
            <Label for="rows-per-page" class="text-sm font-medium"> Rows per page </Label>
            <Select v-model="pageSize" as-child>
              <SelectTrigger id="rows-per-page" class="w-20">
                <SelectValue :placeholder="`${pageSize}`" />
              </SelectTrigger>
              <SelectContent side="top">
                <SelectItem value="10">10</SelectItem>
                <SelectItem value="20">20</SelectItem>
                <SelectItem value="30">30</SelectItem>
                <SelectItem value="40">40</SelectItem>
                <SelectItem value="50">50</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <!-- Page indicator -->
          <div class="text-sm font-medium">Page {{ currentPage + 1 }} of {{ totalPages || 1 }}</div>

          <!-- Navigation buttons -->
          <div class="flex gap-2">
            <Button
              variant="outline"
              size="icon"
              :disabled="currentPage === 0"
              class="hidden lg:flex"
              @click="currentPage = 0"
            >
              <span class="sr-only">First page</span>
              «
            </Button>
            <Button variant="outline" size="icon" :disabled="currentPage === 0" @click="prevPage">
              <span class="sr-only">Previous page</span>
              <ChevronLeft class="size-4" />
            </Button>
            <Button
              variant="outline"
              size="icon"
              :disabled="currentPage >= totalPages - 1"
              @click="nextPage"
            >
              <span class="sr-only">Next page</span>
              <ChevronRight class="size-4" />
            </Button>
            <Button
              variant="outline"
              size="icon"
              :disabled="currentPage >= totalPages - 1"
              class="hidden lg:flex"
              @click="currentPage = totalPages - 1"
            >
              <span class="sr-only">Last page</span>
              »
            </Button>
          </div>
        </div>
      </div>
    </TabsContent>

    <!-- Other tabs (placeholder) -->
    <TabsContent value="past-performance" class="px-4 py-4 lg:px-6">
      <div class="aspect-video w-full rounded-lg border border-dashed" />
    </TabsContent>
    <TabsContent value="key-personnel" class="px-4 py-4 lg:px-6">
      <div class="aspect-video w-full rounded-lg border border-dashed" />
    </TabsContent>
    <TabsContent value="focus-documents" class="px-4 py-4 lg:px-6">
      <div class="aspect-video w-full rounded-lg border border-dashed" />
    </TabsContent>
  </Tabs>
</template>
