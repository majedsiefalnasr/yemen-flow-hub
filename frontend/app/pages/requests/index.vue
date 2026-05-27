<script setup lang="ts">
import type { VisibilityState } from '@tanstack/vue-table'
import { computed, onMounted, ref, watch } from 'vue'
import { AlertCircle, ChevronDown, Columns3, Download, FilePlus2, Printer, Search, X } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import RequestsDataTable from '@/components/requests/RequestsDataTable.vue'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { RequestStatus, UserRole } from '@/types/enums'
import {
  ROLE_BUCKETS,
  BANK_ROLES,
  CBY_BANK_FILTER_ROLES,
  ROUTE_ROLE_MAP,
  type StageBucket,
} from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequestsStore } from '@/stores/requests.store'
import { useBanks } from '@/composables/useBanks'
import type { Bank } from '@/types/models'
import {
  Tabs,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests'],
})

const authStore = useAuthStore()
const store = useRequestsStore()
const { fetchBanks } = useBanks()
const route = useRoute()
const router = useRouter()

const user = computed(() => authStore.user)
const filter = ref('all')
const query = ref('')
const bankFilter = ref('all')
const banks = ref<Bank[]>([])

// Column visibility — owned here so the dropdown can live alongside the search bar
const columnVisibility = ref<VisibilityState>({})
const hidableColumns = [
  { id: 'merchant', label: 'التاجر / البنك' },
  { id: 'goods_description', label: 'نوع البضاعة' },
  { id: 'amount', label: 'المبلغ' },
  { id: 'status', label: 'الحالة' },
]

onMounted(async () => {
  await store.loadRequests({ per_page: 200 })
  if (user.value && CBY_BANK_FILTER_ROLES.includes(user.value.role)) {
    banks.value = await fetchBanks()
  }
})

const roleBuckets = computed((): StageBucket[] => {
  if (!user.value) return []
  return ROLE_BUCKETS[user.value.role] ?? []
})

const isBankScoped = computed(() => user.value ? BANK_ROLES.includes(user.value.role) : false)
const showBankFilter = computed(() => user.value ? CBY_BANK_FILTER_ROLES.includes(user.value.role) : false)
const tabOptions = computed(() => [
  ...roleBuckets.value.map(bucket => ({
    key: bucket.key,
    label: bucket.label,
    count: countForBucket(bucket.key),
  })),
  { key: 'all', label: 'الكل', count: countForBucket('all') },
])

const tabKeys = computed(() => new Set(tabOptions.value.map(tab => tab.key)))

function bucketMatchesRequest(bucket: StageBucket | undefined, req: typeof store.requests[number]): boolean {
  if (!bucket) return false
  if (bucket.matches) return bucket.matches(req, user.value?.id ?? null)
  return bucket.statuses.includes(req.status)
}

const filteredRequests = computed(() => {
  return store.requests.filter((req) => {
    const bucketMatches = filter.value === 'all'
      || bucketMatchesRequest(roleBuckets.value.find(b => b.key === filter.value), req)

    const bankMatches = isBankScoped.value
      || bankFilter.value === 'all'
      || String(req.bank_id) === bankFilter.value

    const q = query.value.trim().toLowerCase()
    const queryMatches = !q
      || req.reference_number.toLowerCase().includes(q)
      || (req.merchant?.name ?? '').toLowerCase().includes(q)
      || (req.invoice_number ?? '').toLowerCase().includes(q)

    return bucketMatches && bankMatches && queryMatches
  })
})

function countForBucket(key: string) {
  if (key === 'all') return store.requests.length
  const bucket = roleBuckets.value.find(b => b.key === key)
  return store.requests.filter(r => bucketMatchesRequest(bucket, r)).length
}

function isColumnVisible(id: string) {
  return columnVisibility.value[id] !== false
}

function toggleColumn(id: string, value: boolean) {
  columnVisibility.value = { ...columnVisibility.value, [id]: value }
}

const canCreateRequest = computed(() => user.value?.role === UserRole.DATA_ENTRY)

const isCbyAdmin = computed(() => user.value?.role === UserRole.CBY_ADMIN)
const isDirector = computed(() => user.value?.role === UserRole.COMMITTEE_DIRECTOR)

const cbySmartSummary = computed(() => {
  if (!isCbyAdmin.value) return []
  const reqs = store.requests
  const needsAttention = reqs.filter(r =>
    roleBuckets.value.find(b => b.key === 'needs_attention')?.statuses.includes(r.status),
  ).length
  const voting = reqs.filter(r =>
    roleBuckets.value.find(b => b.key === 'executive_voting')?.statuses.includes(r.status),
  ).length
  const fxPending = reqs.filter(r =>
    roleBuckets.value.find(b => b.key === 'fx_pending')?.statuses.includes(r.status),
  ).length
  const now = Date.now()
  const stalledCount = reqs.filter((r) => {
    const updated = new Date(r.updated_at).getTime()
    const ageDays = (now - updated) / (1000 * 60 * 60 * 24)
    return ageDays > 2
      && r.status !== RequestStatus.COMPLETED
      && r.status !== RequestStatus.BANK_REJECTED
      && r.status !== RequestStatus.SUPPORT_REJECTED
      && r.status !== RequestStatus.EXECUTIVE_REJECTED
  }).length
  const items: Array<{ label: string; count: number; tab: string; color: string }> = []
  if (needsAttention > 0) items.push({ label: 'يحتاج متابعة', count: needsAttention, tab: 'needs_attention', color: 'var(--severity-amber)' })
  if (voting > 0) items.push({ label: 'تصويت نشط', count: voting, tab: 'executive_voting', color: '#5856d6' })
  if (fxPending > 0) items.push({ label: 'انتظار تأكيد المصارفة', count: fxPending, tab: 'fx_pending', color: 'var(--severity-red)' })
  if (stalledCount > 0) items.push({ label: 'طلبات متوقفة > 48 ساعة', count: stalledCount, tab: 'active', color: 'var(--severity-amber)' })
  return items
})

const directorSmartSummary = computed(() => {
  if (!isDirector.value) return []
  const reqs = filteredRequests.value
  const activeVoting = reqs.filter(r => r.status === RequestStatus.EXECUTIVE_VOTING_OPEN).length
  const pendingTieBreak = reqs.filter(r => r.status === RequestStatus.EXECUTIVE_VOTING_OPEN && r.is_tie).length
  const pendingFx = reqs.filter(r => r.status === RequestStatus.EXECUTIVE_APPROVED).length

  const now = Date.now()
  const weekAgo = now - (7 * 24 * 60 * 60 * 1000)
  const finalizedThisWeek = reqs.filter((r) => {
    if (!(r.status === RequestStatus.EXECUTIVE_APPROVED || r.status === RequestStatus.EXECUTIVE_REJECTED)) return false
    const updated = new Date(r.updated_at).getTime()
    return !Number.isNaN(updated) && updated >= weekAgo
  }).length

  return [
    { key: 'active_voting', label: 'جلسات نشطة', count: activeVoting, color: '#5856d6' },
    { key: 'tie_break', label: 'تعادل يحتاج حسماً', count: pendingTieBreak, color: 'var(--severity-amber)' },
    { key: 'fx_pending', label: 'بانتظار تأكيد المصارفة', count: pendingFx, color: 'var(--severity-amber)' },
    { key: 'finalized', label: 'مُنهاة هذا الأسبوع', count: finalizedThisWeek, color: 'var(--severity-green)' },
  ]
})

const selectedCount = ref(0)
const dataTableRef = ref<{ clearSelection: () => void } | null>(null)

function openRequest(id: number) {
  navigateTo(`/requests/${id}`)
}

function clearBulkSelection() {
  dataTableRef.value?.clearSelection()
}

// Soft-migration for deep-links built before the Story 12.2 bucket rename.
// Old keys point at the closest current bucket so a bookmarked URL still lands
// somewhere meaningful instead of silently collapsing to "all".
const LEGACY_TAB_ALIASES: Record<string, string> = {
  bank_stage: 'active',
  support_stage: 'active',
  swift_stage: 'active',
  voting_stage: 'executive_voting',
}

function syncTabFromRoute() {
  // Wait for ROLE_BUCKETS to resolve so a deep-link like ?tab=needs_attention
  // is not silently dropped to "all" on the first render before tabOptions
  // populates.
  if (tabKeys.value.size === 0) return
  const raw = typeof route.query.tab === 'string' ? route.query.tab : 'all'
  const resolved = tabKeys.value.has(raw) ? raw : LEGACY_TAB_ALIASES[raw] ?? 'all'
  if (filter.value !== resolved) filter.value = resolved
}

watch([() => route.query.tab, tabKeys], syncTabFromRoute, { immediate: true })

watch(filter, (tab) => {
  const nextQuery = { ...route.query }
  if (tab === 'all') delete nextQuery.tab
  else nextQuery.tab = tab
  // Skip the replace if the query is already in sync — otherwise the route
  // → filter → route watchers chase each other on every navigation.
  const currentTab = typeof route.query.tab === 'string' ? route.query.tab : undefined
  const desiredTab = tab === 'all' ? undefined : tab
  if (currentTab === desiredTab) return
  router.replace({ query: nextQuery })
})
</script>

<template>
  <div v-if="user">
    <PageHeader
      title="طلبات تمويل الواردات"
      :subtitle="isBankScoped ? 'طلبات جهتك فقط' : 'جميع الطلبات المقدمة عبر المنصة مع حالاتها ومراحل المعالجة'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الطلبات' }]"
    />

    <!-- CBY_ADMIN: Smart Summary Bar — operational exceptions -->
    <div
      v-if="isCbyAdmin && cbySmartSummary.length > 0"
      class="mb-4 flex flex-wrap gap-2"
      role="region"
      aria-label="ملخص استثنائي"
      data-testid="cby-smart-summary"
    >
      <button
        v-for="item in cbySmartSummary"
        :key="item.tab"
        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium transition-colors hover:bg-muted/60 cursor-pointer"
        :style="{ borderColor: item.color, color: item.color }"
        @click="filter = item.tab"
      >
        <span class="font-bold">{{ item.count }}</span>
        {{ item.label }}
      </button>
    </div>

    <!-- COMMITTEE_DIRECTOR: Smart Summary Bar -->
    <div
      v-if="isDirector"
      class="mb-4 flex flex-wrap gap-2"
      role="region"
      aria-label="ملخص الحوكمة"
      data-testid="director-smart-summary"
    >
      <button
        v-for="item in directorSmartSummary"
        :key="item.key"
        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium transition-colors hover:bg-muted/60 cursor-pointer"
        :style="{ borderColor: item.color, color: item.color }"
        @click="filter = item.key"
      >
        <span class="font-bold">{{ item.count }}</span>
        {{ item.label }}
      </button>
    </div>

    <!-- Inline error state -->
    <Alert v-if="store.error" variant="destructive" role="alert" class="mb-4">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ في تحميل الطلبات</AlertTitle>
      <AlertDescription>{{ store.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="store.loadRequests({ per_page: 200 })">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <Tabs v-model="filter" class="flex w-full flex-col gap-4">
      <!-- Row 1: tabs (left) + page actions (right) -->
      <div class="flex items-center justify-between gap-4">
        <!-- Mobile: select dropdown -->
        <Label for="stage-selector" class="sr-only">المرحلة</Label>
        <Select v-model="filter">
          <SelectTrigger
            id="stage-selector"
            class="w-fit md:hidden"
            size="sm"
          >
            <SelectValue placeholder="اختر المرحلة" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem v-for="tab in tabOptions" :key="tab.key" :value="tab.key">
              {{ tab.label }} ({{ tab.count }})
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Desktop: tab pills -->
        <TabsList class="hidden h-auto gap-1 rounded-full bg-muted p-1 md:flex">
          <TabsTrigger
            v-for="tab in tabOptions"
            :key="tab.key"
            :value="tab.key"
            class="h-7 gap-1.5 rounded-full px-3 text-sm data-[state=active]:bg-background data-[state=active]:shadow-sm"
          >
            {{ tab.label }}
            <Badge
              variant="secondary"
              class="h-5 min-w-5 rounded-full px-1 text-xs"
            >
              {{ tab.count }}
            </Badge>
          </TabsTrigger>
        </TabsList>

        <!-- Export + New request -->
        <div class="flex items-center gap-2">
          <Button variant="outline" size="sm" class="h-8">
            <Download class="h-4 w-4" />
            <span class="hidden lg:inline">تصدير</span>
          </Button>
          <Button v-if="canCreateRequest" as="a" href="/requests/new" size="sm" class="h-8">
            <FilePlus2 class="h-4 w-4" />
            <span class="hidden lg:inline">طلب جديد</span>
          </Button>
        </div>
      </div>

      <!-- Row 2: bulk toolbar (when selected) OR search + columns (default) -->
      <div v-if="selectedCount > 0" class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2">
        <span class="text-sm font-medium text-primary">{{ selectedCount }} محدد</span>
        <div class="mx-2 h-4 w-px bg-border" />
        <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
          <Download class="h-3.5 w-3.5" />
          تصدير
        </Button>
        <Button variant="outline" size="sm" class="h-7 gap-1.5 text-xs">
          <Printer class="h-3.5 w-3.5" />
          طباعة
        </Button>
        <Button
          variant="ghost"
          size="sm"
          class="ms-auto h-7 gap-1 text-xs text-muted-foreground"
          @click="clearBulkSelection"
        >
          <X class="h-3.5 w-3.5" />
          إلغاء التحديد
        </Button>
      </div>

      <div v-else class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-[220px] flex-1">
          <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            v-model="query"
            placeholder="بحث برقم الطلب، التاجر، أو رقم الفاتورة..."
            class="h-8 rounded-md pe-9 text-sm"
          />
        </div>

        <Select v-if="showBankFilter" v-model="bankFilter">
          <SelectTrigger class="h-8 w-full rounded-md text-sm sm:w-48">
            <SelectValue placeholder="جميع البنوك" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">جميع البنوك</SelectItem>
            <SelectItem v-for="bank in banks" :key="bank.id" :value="String(bank.id)">
              {{ bank.name_ar }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Customize columns -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="ms-auto h-8">
              <Columns3 class="h-4 w-4" />
              <span class="hidden lg:inline">تخصيص الأعمدة</span>
              <ChevronDown class="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-52">
            <DropdownMenuCheckboxItem
              v-for="col in hidableColumns"
              :key="col.id"
              :model-value="isColumnVisible(col.id)"
              @update:model-value="(v) => toggleColumn(col.id, !!v)"
            >
              {{ col.label }}
            </DropdownMenuCheckboxItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>

      <!-- Table -->
      <RequestsDataTable
        v-if="user"
        ref="dataTableRef"
        :data="filteredRequests"
        :loading="store.loadingList"
        :no-data="!store.loadingList && store.requests.length === 0"
        :role="user.role"
        :column-visibility="columnVisibility"
        @row-click="openRequest"
        @update:column-visibility="v => columnVisibility = v"
        @update:selected-count="count => selectedCount = count"
      />
    </Tabs>
  </div>
</template>
