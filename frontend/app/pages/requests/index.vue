<script setup lang="ts">
import type { VisibilityState } from '@tanstack/vue-table'
import { computed, onMounted, ref, watch } from 'vue'
import {
  AlertCircle, CheckCircle2, ChevronDown, ClipboardList, Columns3,
  Download, Edit, Eye, FilePlus2, Filter, Lock, Printer,
  Search, SlidersHorizontal, Upload, User, Vote, X,
} from 'lucide-vue-next'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
  SheetClose,
} from '@/components/ui/sheet'
import { Separator } from '@/components/ui/separator'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import type { ImportRequest } from '@/types/models'

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
const hidableColumns = computed(() => {
  const base = [
    { id: 'merchant', label: 'التاجر / البنك' },
    { id: 'goods_description', label: 'نوع البضاعة' },
    { id: 'amount', label: 'المبلغ' },
    { id: 'status', label: 'الحالة' },
    { id: 'last_activity', label: 'النشاط الأخير' },
  ]
  if (isCbyAdmin.value) {
    return [
      ...base,
      { id: 'cby_age', label: 'العمر' },
      { id: 'cby_sla', label: 'SLA' },
      { id: 'cby_voting', label: 'التصويت' },
      { id: 'cby_fx', label: 'المصارفة' },
      { id: 'cby_risk', label: 'المخاطر' },
    ]
  }
  if (isDirector.value) {
    return [
      ...base,
      { id: 'director_ready_to_close', label: 'جاهز للإغلاق' },
      { id: 'director_fx_state', label: 'حالة المصارفة' },
    ]
  }
  return base
})

// Created-by-me toggle — BANK_REVIEWER only
const createdByMeOnly = ref(false)
const isBankReviewer = computed(() => user.value?.role === UserRole.BANK_REVIEWER)

// CBY Admin: advanced filters drawer
const advancedFiltersOpen = ref(false)
const advFilters = ref({
  bank: 'all',
  stage: '',
  sla: '',
  voting: '',
  fx: '',
  high_value: false,
})

function clearAdvancedFilters() {
  advFilters.value = { bank: 'all', stage: '', sla: '', voting: '', fx: '', high_value: false }
}

const advancedFilterCount = computed(() => {
  let n = 0
  if (advFilters.value.bank !== 'all') n++
  if (advFilters.value.stage) n++
  if (advFilters.value.sla) n++
  if (advFilters.value.voting) n++
  if (advFilters.value.fx) n++
  if (advFilters.value.high_value) n++
  return n
})

// Quick preview dialog — available for all roles via reference-number click
const previewRequest = ref<ImportRequest | null>(null)
const previewOpen = ref(false)

function openPreview(request: ImportRequest) {
  previewRequest.value = request
  previewOpen.value = true
}

function relativeAge(isoDate: string | null | undefined): string {
  if (!isoDate) return '—'
  const ms = Date.now() - new Date(isoDate).getTime()
  const hrs = Math.floor(ms / 3600000)
  if (hrs < 24) return `${hrs} ساعة`
  return `${Math.floor(hrs / 24)} يوم`
}

function slaState(request: ImportRequest): { label: string; color: string } {
  const hrs = (Date.now() - new Date(request.created_at).getTime()) / 3600000
  if (hrs > 120) return { label: 'انتهاك SLA', color: 'var(--severity-red)' }
  if (hrs > 72) return { label: 'خطر SLA', color: 'var(--severity-amber)' }
  return { label: 'ضمن SLA', color: 'var(--severity-green)' }
}

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

    const createdByMeMatches = !createdByMeOnly.value
      || req.created_by === user.value?.id

    // Support Committee: hide requests already claimed by someone else
    const hideOthersMatches = !hideOthers.value
      || !req.is_claimed
      || req.is_claimed_by_me === true

    // CBY Admin advanced filters
    let advBankMatches = true
    let advStageMatches = true
    let advSlaMatches = true
    let advVotingMatches = true
    let advFxMatches = true
    let advHighValueMatches = true
    if (isCbyAdmin.value) {
      if (advFilters.value.bank !== 'all' && advFilters.value.bank) {
        advBankMatches = String(req.bank_id) === advFilters.value.bank
      }
      if (advFilters.value.stage) {
        advStageMatches = req.current_owner_role === advFilters.value.stage
      }
      if (advFilters.value.sla) {
        const hrs = (Date.now() - new Date(req.created_at).getTime()) / 3600000
        if (advFilters.value.sla === 'breach') advSlaMatches = hrs > 120
        else if (advFilters.value.sla === 'risk') advSlaMatches = hrs > 72 && hrs <= 120
        else if (advFilters.value.sla === 'ok') advSlaMatches = hrs <= 72
      }
      if (advFilters.value.voting) {
        const hasVoting = req.voting_session_status != null
        advVotingMatches = advFilters.value.voting === 'active' ? hasVoting : !hasVoting
      }
      if (advFilters.value.fx) {
        const hasFx = req.has_fx_request_document === true
        advFxMatches = advFilters.value.fx === 'uploaded' ? hasFx : !hasFx
      }
      if (advFilters.value.high_value) {
        advHighValueMatches = req.amount >= 1_000_000
      }
    }

    return bucketMatches && bankMatches && queryMatches && createdByMeMatches && hideOthersMatches
      && advBankMatches && advStageMatches && advSlaMatches && advVotingMatches && advFxMatches && advHighValueMatches
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
const isBankAdmin = computed(() => user.value?.role === UserRole.BANK_ADMIN)
const isSupportCommittee = computed(() => user.value?.role === UserRole.SUPPORT_COMMITTEE)
const isDataEntry = computed(() => user.value?.role === UserRole.DATA_ENTRY)
const isSwiftOfficer = computed(() => user.value?.role === UserRole.SWIFT_OFFICER)
const isExecutiveMember = computed(() => user.value?.role === UserRole.EXECUTIVE_MEMBER)

function formatDate(isoDate: string | null | undefined): string {
  if (!isoDate) return '—'
  return new Date(isoDate).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' })
}

// Support Committee: hide-claimed-by-others toggle — persisted via ?hide_others=1
const hideOthers = ref(false)

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

// Sync created-by-me toggle with ?my=1 query param
watch(() => route.query.my, (val) => {
  createdByMeOnly.value = val === '1'
}, { immediate: true })
watch(createdByMeOnly, (val) => {
  const nextQuery = { ...route.query }
  if (val) nextQuery.my = '1'
  else delete nextQuery.my
  if ((route.query.my === '1') === val) return
  router.replace({ query: nextQuery })
})

// Sync hide-others toggle (Support Committee) with ?hide_others=1 query param
watch(() => route.query.hide_others, (val) => {
  hideOthers.value = val === '1'
}, { immediate: true })
watch(hideOthers, (val) => {
  const nextQuery = { ...route.query }
  if (val) nextQuery.hide_others = '1'
  else delete nextQuery.hide_others
  if ((route.query.hide_others === '1') === val) return
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

    <!-- BANK_ADMIN: read-only oversight chip -->
    <div v-if="isBankAdmin" class="mb-4">
      <Badge variant="outline" class="gap-1 rounded-full px-3 py-1 text-xs font-medium text-muted-foreground border-border">
        <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
        إدارة وعرض فقط
      </Badge>
    </div>

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

        <!-- BANK_REVIEWER: Created-by-me toggle -->
        <Button
          v-if="isBankReviewer"
          :variant="createdByMeOnly ? 'default' : 'outline'"
          size="sm"
          class="h-8 gap-1.5"
          :aria-pressed="createdByMeOnly"
          @click="createdByMeOnly = !createdByMeOnly"
        >
          <User class="h-4 w-4" />
          <span class="hidden lg:inline">طلباتي فقط</span>
        </Button>

        <!-- SUPPORT_COMMITTEE: Hide-claimed-by-others toggle -->
        <Button
          v-if="isSupportCommittee"
          :variant="hideOthers ? 'default' : 'outline'"
          size="sm"
          class="h-8 gap-1.5"
          :aria-pressed="hideOthers"
          data-testid="hide-others-toggle"
          @click="hideOthers = !hideOthers"
        >
          <User class="h-4 w-4" />
          <span class="hidden lg:inline">إخفاء المحجوزة</span>
        </Button>

        <!-- CBY Admin: advanced filters -->
        <Button
          v-if="isCbyAdmin"
          :variant="advancedFilterCount > 0 ? 'default' : 'outline'"
          size="sm"
          class="h-8 gap-1.5"
          @click="advancedFiltersOpen = true"
        >
          <SlidersHorizontal class="h-4 w-4" />
          <span class="hidden lg:inline">فلاتر متقدمة</span>
          <Badge v-if="advancedFilterCount > 0" variant="secondary" class="h-4 min-w-4 rounded-full px-1 text-[10px]">
            {{ advancedFilterCount }}
          </Badge>
        </Button>

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
        @preview-click="openPreview"
        @update:column-visibility="v => columnVisibility = v"
        @update:selected-count="count => selectedCount = count"
      />
    </Tabs>

    <!-- CBY Admin: Advanced Filters Drawer -->
    <Sheet v-if="isCbyAdmin" v-model:open="advancedFiltersOpen">
      <SheetContent side="right" class="w-[360px] sm:w-[400px]">
        <SheetHeader>
          <SheetTitle>فلاتر متقدمة</SheetTitle>
          <SheetDescription>تصفية سجل الطلبات الوطني</SheetDescription>
        </SheetHeader>

        <div class="mt-6 flex flex-col gap-5">
          <div class="flex flex-col gap-2">
            <Label>البنك</Label>
            <Select v-model="advFilters.bank">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="جميع البنوك" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">جميع البنوك</SelectItem>
                <SelectItem v-for="bank in banks" :key="bank.id" :value="String(bank.id)">
                  {{ bank.name_ar }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-2">
            <Label>مرحلة سير العمل</Label>
            <Select v-model="advFilters.stage">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="جميع المراحل" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">جميع المراحل</SelectItem>
                <SelectItem value="DATA_ENTRY">إدخال البيانات</SelectItem>
                <SelectItem value="BANK_REVIEWER">مراجعة البنك</SelectItem>
                <SelectItem value="SUPPORT_COMMITTEE">لجنة المساندة</SelectItem>
                <SelectItem value="SWIFT_OFFICER">ضابط SWIFT</SelectItem>
                <SelectItem value="EXECUTIVE_MEMBER">اللجنة التنفيذية</SelectItem>
                <SelectItem value="COMMITTEE_DIRECTOR">مدير اللجنة</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-2">
            <Label>حالة SLA</Label>
            <Select v-model="advFilters.sla">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="جميع الحالات" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">جميع الحالات</SelectItem>
                <SelectItem value="breach">انتهاك SLA (&gt;120 ساعة)</SelectItem>
                <SelectItem value="risk">خطر SLA (72-120 ساعة)</SelectItem>
                <SelectItem value="ok">ضمن SLA (&lt;72 ساعة)</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-2">
            <Label>حالة التصويت</Label>
            <Select v-model="advFilters.voting">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="الكل" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">الكل</SelectItem>
                <SelectItem value="active">تصويت نشط</SelectItem>
                <SelectItem value="none">بدون تصويت</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex flex-col gap-2">
            <Label>حالة المصارفة</Label>
            <Select v-model="advFilters.fx">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="الكل" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">الكل</SelectItem>
                <SelectItem value="uploaded">مرفوع</SelectItem>
                <SelectItem value="pending">لم يرفع</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex items-center gap-3 rounded-lg border px-3 py-2.5">
            <input
              id="high-value-toggle"
              v-model="advFilters.high_value"
              type="checkbox"
              class="size-4 rounded accent-primary"
            />
            <Label for="high-value-toggle" class="cursor-pointer text-sm">قيمة عالية فقط (≥ 1,000,000)</Label>
          </div>
        </div>

        <SheetFooter class="mt-6 flex gap-2">
          <Button variant="outline" class="flex-1" @click="clearAdvancedFilters">
            <X class="me-1.5 h-4 w-4" />
            مسح الفلاتر
          </Button>
          <SheetClose as-child>
            <Button class="flex-1">
              <Filter class="me-1.5 h-4 w-4" />
              تطبيق
            </Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>

    <!-- Quick Preview Dialog — all roles, triggered by reference-number click -->
    <Dialog v-model:open="previewOpen">
      <DialogContent v-if="previewRequest" dir="rtl" class="sm:max-w-lg">
        <DialogHeader class="pb-2">
          <DialogTitle class="flex items-center gap-2 text-base">
            <span class="font-mono text-lg font-bold text-primary">{{ previewRequest.reference_number }}</span>
            <Badge variant="outline" class="text-xs font-normal">معاينة سريعة</Badge>
          </DialogTitle>
          <DialogDescription class="text-xs">
            انقر على الطلب في أي وقت للوصول إلى الصفحة الكاملة وجميع الإجراءات
          </DialogDescription>
        </DialogHeader>

        <!-- Status row -->
        <div class="flex flex-wrap items-center gap-2 py-1">
          <StatusBadge :status="previewRequest.status" :role="user!.role" />

          <!-- SLA badge (CBY Admin / Director) -->
          <Badge
            v-if="isCbyAdmin || isDirector"
            class="border text-xs"
            :style="{
              backgroundColor: `${slaState(previewRequest).color}18`,
              color: slaState(previewRequest).color,
              borderColor: `${slaState(previewRequest).color}38`,
            }"
          >
            {{ slaState(previewRequest).label }}
          </Badge>

          <!-- Voting badge -->
          <Badge
            v-if="previewRequest.voting_session_status && (isExecutiveMember || isDirector || isCbyAdmin)"
            class="border border-[var(--voting)]/30 bg-[var(--voting)]/10 text-[var(--voting)] text-xs"
          >
            <Vote class="me-1 h-3 w-3" />
            {{ previewRequest.voting_session_status }}
          </Badge>

          <!-- Claim badge (Support Committee) -->
          <Badge
            v-if="isSupportCommittee && previewRequest.is_claimed"
            class="border text-xs"
            :class="previewRequest.is_claimed_by_me
              ? 'border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]'
              : 'border-border bg-muted text-muted-foreground'"
          >
            <Lock class="me-1 h-3 w-3" />
            {{ previewRequest.is_claimed_by_me ? 'محجوز لك' : 'محجوز' }}
          </Badge>

          <!-- Duplicate warning -->
          <Badge
            v-if="(previewRequest.duplicate_warnings?.length ?? 0) > 0"
            variant="destructive"
            class="rounded-full text-xs"
          >
            فاتورة مكررة
          </Badge>
        </div>

        <Separator />

        <!-- Key info grid -->
        <div class="grid grid-cols-2 gap-x-4 gap-y-3 py-1 text-sm">
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">البنك</p>
            <p class="font-medium">{{ previewRequest.bank_name ?? '—' }}</p>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">التاجر</p>
            <p class="font-medium">{{ previewRequest.merchant?.name ?? '—' }}</p>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">المبلغ</p>
            <p class="font-mono font-semibold">
              {{ previewRequest.amount.toLocaleString('en-US') }}
              <span class="text-muted-foreground">{{ previewRequest.currency }}</span>
            </p>
          </div>
          <div class="space-y-0.5">
            <p class="text-xs text-muted-foreground">تاريخ التقديم</p>
            <p class="font-medium">{{ formatDate(previewRequest.created_at) }}</p>
          </div>

          <!-- CBY Admin / Director extras -->
          <template v-if="isCbyAdmin || isDirector">
            <div class="space-y-0.5">
              <p class="text-xs text-muted-foreground">عمر الطلب</p>
              <p class="font-medium">{{ relativeAge(previewRequest.created_at) }}</p>
            </div>
            <div class="space-y-0.5">
              <p class="text-xs text-muted-foreground">المصارفة الخارجية</p>
              <p class="font-medium" :class="previewRequest.has_fx_request_document ? 'text-[var(--severity-green)]' : 'text-muted-foreground'">
                {{ previewRequest.has_fx_request_document ? 'مرفوعة' : 'لم ترفع بعد' }}
              </p>
            </div>
          </template>
        </div>

        <Separator />

        <!-- Role-specific quick actions -->
        <DialogFooter class="flex-wrap gap-2 sm:flex-nowrap">
          <!-- DATA_ENTRY: edit if in editable state -->
          <Button
            v-if="isDataEntry && [RequestStatus.DRAFT, RequestStatus.BANK_RETURNED].includes(previewRequest.status as RequestStatus)"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}/edit`)"
          >
            <Edit class="me-1.5 h-3.5 w-3.5" />
            تعديل الطلب
          </Button>

          <!-- BANK_REVIEWER: review action -->
          <Button
            v-if="isBankReviewer"
            size="sm"
            class="bg-[var(--severity-green)] text-white hover:bg-[var(--severity-green)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <CheckCircle2 class="me-1.5 h-3.5 w-3.5" />
            مراجعة واتخاذ قرار
          </Button>

          <!-- SUPPORT_COMMITTEE: claim if unclaimed -->
          <Button
            v-if="isSupportCommittee && !previewRequest.is_claimed"
            size="sm"
            class="bg-[var(--severity-amber)] text-white hover:bg-[var(--severity-amber)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Lock class="me-1.5 h-3.5 w-3.5" />
            حجز ومراجعة
          </Button>
          <Button
            v-else-if="isSupportCommittee && previewRequest.is_claimed_by_me"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            متابعة المراجعة
          </Button>

          <!-- SWIFT_OFFICER: upload if waiting -->
          <Button
            v-if="isSwiftOfficer && previewRequest.status === RequestStatus.WAITING_FOR_SWIFT"
            size="sm"
            class="bg-[var(--info)] text-white hover:bg-[var(--info)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Upload class="me-1.5 h-3.5 w-3.5" />
            رفع وثيقة SWIFT
          </Button>

          <!-- EXECUTIVE_MEMBER: vote if session open -->
          <Button
            v-if="isExecutiveMember && previewRequest.status === RequestStatus.EXECUTIVE_VOTING_OPEN"
            size="sm"
            class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Vote class="me-1.5 h-3.5 w-3.5" />
            التصويت الآن
          </Button>

          <!-- COMMITTEE_DIRECTOR: manage session -->
          <Button
            v-if="isDirector"
            size="sm"
            class="bg-[var(--voting)] text-white hover:bg-[var(--voting)]/90"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Vote class="me-1.5 h-3.5 w-3.5" />
            {{ previewRequest.status === RequestStatus.EXECUTIVE_VOTING_OPEN ? 'إغلاق الجلسة' : 'فتح جلسة التصويت' }}
          </Button>

          <!-- CBY_ADMIN: audit trail shortcut -->
          <Button
            v-if="isCbyAdmin"
            variant="outline"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <ClipboardList class="me-1.5 h-3.5 w-3.5" />
            سجل التدقيق
          </Button>

          <!-- Always: open full request -->
          <Button
            variant="outline"
            size="sm"
            @click="navigateTo(`/requests/${previewRequest.id}`)"
          >
            <Eye class="me-1.5 h-3.5 w-3.5" />
            فتح الطلب الكامل
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
