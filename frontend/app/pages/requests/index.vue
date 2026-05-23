<script setup lang="ts">
import { AlertTriangle, ChevronLeft, Download, FilePlus2, Filter, Lock, Search, Vote } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import { RequestStatus, UserRole } from '@/types/enums'
import {
  ROLE_BUCKETS,
  BANK_ROLES,
  CBY_BANK_FILTER_ROLES,
  type StageBucket,
} from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequestsStore } from '@/stores/requests.store'
import { useBanks } from '@/composables/useBanks'
import type { Bank } from '@/types/models'

const authStore = useAuthStore()
const store = useRequestsStore()
const { fetchBanks } = useBanks()

const user = computed(() => authStore.user)
const filter = ref('all')
const query = ref('')
const bankFilter = ref<number | 'all'>('all')
const banks = ref<Bank[]>([])

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

const filteredRequests = computed(() => {
  return store.requests.filter((req) => {
    const bucketMatches = filter.value === 'all'
      || roleBuckets.value.find(b => b.key === filter.value)?.statuses.includes(req.status)

    const bankMatches = isBankScoped.value || bankFilter.value === 'all' || req.bank_id === bankFilter.value

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
  return store.requests.filter(r => bucket?.statuses.includes(r.status)).length
}

const canCreateRequest = computed(() => user.value?.role === UserRole.DATA_ENTRY)
</script>

<template>
  <div v-if="user">
    <!-- Page header -->
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <nav class="mb-1.5 flex items-center gap-1 text-xs text-muted-foreground">
          <NuxtLink to="/" class="hover:text-foreground">الرئيسية</NuxtLink>
          <span>/</span>
          <span>الطلبات</span>
        </nav>
        <h1 class="text-2xl font-bold tracking-tight">
          طلبات تمويل الواردات
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ isBankScoped ? 'طلبات جهتك فقط' : 'جميع الطلبات المقدمة عبر المنصة مع حالاتها ومراحل المعالجة' }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm">
          <Download class="ms-1.5 h-4 w-4" />
          تصدير
        </Button>
        <Button v-if="canCreateRequest" as="a" href="/requests/new" size="sm">
          <FilePlus2 class="ms-1.5 h-4 w-4" />
          طلب جديد
        </Button>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="mb-4 flex items-center gap-0 border-b border-border">
      <button
        type="button"
        :class="[
          'flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
          filter === 'all'
            ? 'border-primary text-primary'
            : 'border-transparent text-muted-foreground hover:text-foreground',
        ]"
        @click="filter = 'all'"
      >
        الكل
        <span
          :class="[
            'rounded-full px-1.5 py-0.5 text-[11px] tabular-nums leading-none',
            filter === 'all' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
          ]"
        >
          {{ countForBucket('all') }}
        </span>
      </button>
      <button
        v-for="bucket in roleBuckets"
        :key="bucket.key"
        type="button"
        :class="[
          'flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
          filter === bucket.key
            ? 'border-primary text-primary'
            : 'border-transparent text-muted-foreground hover:text-foreground',
        ]"
        @click="filter = bucket.key"
      >
        {{ bucket.label }}
        <span
          :class="[
            'rounded-full px-1.5 py-0.5 text-[11px] tabular-nums leading-none',
            filter === bucket.key ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
          ]"
        >
          {{ countForBucket(bucket.key) }}
        </span>
      </button>
    </div>

    <!-- Search + filters row -->
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <div class="relative min-w-[240px] flex-1">
        <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          placeholder="بحث برقم الطلب، التاجر، أو رقم الفاتورة..."
          class="h-9 pe-10 text-sm"
        />
      </div>
      <Select v-if="showBankFilter" v-model="bankFilter">
        <SelectTrigger class="h-9 w-48 text-sm">
          <SelectValue placeholder="جميع البنوك" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">
            جميع البنوك
          </SelectItem>
          <SelectItem v-for="bank in banks" :key="bank.id" :value="bank.id">
            {{ bank.name_ar }}
          </SelectItem>
        </SelectContent>
      </Select>
      <Button variant="outline" size="sm" class="h-9 gap-1.5 text-sm">
        <Filter class="h-3.5 w-3.5" />
        فلاتر
      </Button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
      <div class="overflow-x-auto">
        <Table class="w-full min-w-[700px]">
          <TableHeader>
            <TableRow class="border-b bg-muted/30 hover:bg-muted/30">
              <TableHead class="px-4 py-3 text-xs font-medium text-muted-foreground">
                المرجع
              </TableHead>
              <TableHead class="px-4 py-3 text-xs font-medium text-muted-foreground">
                التاجر / البنك
              </TableHead>
              <TableHead class="px-4 py-3 text-xs font-medium text-muted-foreground">
                نوع البضاعة
              </TableHead>
              <TableHead class="px-4 py-3 text-xs font-medium text-muted-foreground">
                المبلغ
              </TableHead>
              <TableHead class="px-4 py-3 text-xs font-medium text-muted-foreground">
                الحالة
              </TableHead>
              <TableHead class="w-8 px-2 py-3" />
            </TableRow>
          </TableHeader>

          <TableBody>
            <!-- Skeleton loading -->
            <template v-if="store.loadingList">
              <TableRow v-for="i in 7" :key="i">
                <TableCell class="px-4 py-4">
                  <div class="mb-1.5 h-3.5 w-32 animate-pulse rounded bg-muted" />
                  <div class="h-3 w-20 animate-pulse rounded bg-muted/60" />
                </TableCell>
                <TableCell class="px-4 py-4">
                  <div class="mb-1.5 h-3.5 w-36 animate-pulse rounded bg-muted" />
                  <div class="h-3 w-24 animate-pulse rounded bg-muted/60" />
                </TableCell>
                <TableCell class="px-4 py-4">
                  <div class="h-3.5 w-24 animate-pulse rounded bg-muted" />
                </TableCell>
                <TableCell class="px-4 py-4">
                  <div class="h-3.5 w-24 animate-pulse rounded bg-muted" />
                </TableCell>
                <TableCell class="px-4 py-4">
                  <div class="h-5 w-24 animate-pulse rounded-full bg-muted" />
                </TableCell>
                <TableCell class="px-2 py-4">
                  <div class="h-4 w-4 animate-pulse rounded bg-muted/40" />
                </TableCell>
              </TableRow>
            </template>

            <!-- Empty state -->
            <TableRow v-else-if="filteredRequests.length === 0">
              <TableCell colspan="6" class="py-16 text-center">
                <div class="flex flex-col items-center gap-3 text-muted-foreground">
                  <svg
                    class="h-12 w-12 opacity-25"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.25"
                    aria-hidden="true"
                  >
                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" />
                  </svg>
                  <div>
                    <p class="text-sm font-medium">
                      لا توجد طلبات مطابقة
                    </p>
                    <p v-if="query || filter !== 'all'" class="mt-0.5 text-xs">
                      جرّب تغيير الفلاتر أو البحث بكلمة مختلفة
                    </p>
                  </div>
                </div>
              </TableCell>
            </TableRow>

            <!-- Data rows -->
            <template v-else>
              <TableRow
                v-for="req in filteredRequests"
                :key="req.id"
                class="cursor-pointer border-t transition-colors hover:bg-muted/40"
                @click="navigateTo(`/requests/${req.id}`)"
              >
                <!-- Reference -->
                <TableCell class="px-4 py-3.5">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="font-mono text-[13px] font-semibold text-primary">
                      {{ req.reference_number }}
                    </span>
                    <span
                      v-if="req.duplicate_warnings?.length"
                      class="inline-flex items-center gap-0.5 rounded-full bg-destructive/10 px-1.5 py-0.5 text-[10px] font-medium text-destructive"
                    >
                      <AlertTriangle class="h-2.5 w-2.5" />
                      مكرر
                    </span>
                    <span
                      v-if="req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && (user.role === UserRole.EXECUTIVE_MEMBER || user.role === UserRole.COMMITTEE_DIRECTOR)"
                      class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                      style="background: rgba(88,86,214,0.1); color: #5856d6;"
                    >
                      <Vote class="h-2.5 w-2.5" />
                      التصويت مفتوح
                    </span>
                    <span
                      v-if="req.is_claimed && !req.is_claimed_by_me && user.role === UserRole.SUPPORT_COMMITTEE"
                      class="inline-flex items-center gap-0.5 rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-600"
                    >
                      <Lock class="h-2.5 w-2.5" />
                      محجوز: {{ req.claimed_by?.name ?? '—' }}
                    </span>
                    <span
                      v-else-if="req.is_claimed_by_me"
                      class="inline-flex items-center gap-0.5 rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-600"
                    >
                      <Lock class="h-2.5 w-2.5" />
                      محجوز لك
                    </span>
                  </div>
                  <div v-if="req.invoice_number" class="mt-0.5 text-[11px] text-muted-foreground">
                    {{ req.invoice_number }}
                  </div>
                </TableCell>

                <!-- Merchant / Bank -->
                <TableCell class="px-4 py-3.5">
                  <div class="truncate text-sm font-medium text-foreground">
                    {{ req.merchant?.name ?? '—' }}
                  </div>
                  <div class="truncate text-xs text-muted-foreground">
                    {{ req.bank_name ?? '—' }}
                  </div>
                </TableCell>

                <!-- Goods type -->
                <TableCell class="px-4 py-3.5">
                  <span class="line-clamp-1 max-w-[180px] text-sm text-muted-foreground">
                    {{ req.goods_description ?? '—' }}
                  </span>
                </TableCell>

                <!-- Amount -->
                <TableCell class="whitespace-nowrap px-4 py-3.5">
                  <span class="font-mono text-sm font-semibold tabular-nums text-foreground">
                    {{ req.amount.toLocaleString('en-US') }}
                  </span>
                  <span class="ms-1 text-xs text-muted-foreground">{{ req.currency }}</span>
                </TableCell>

                <!-- Status badge -->
                <TableCell class="px-4 py-3.5">
                  <StatusBadge :status="req.status" :role="user.role" />
                </TableCell>

                <!-- Row chevron -->
                <TableCell class="px-2 py-3.5">
                  <ChevronLeft class="h-4 w-4 text-muted-foreground/40" />
                </TableCell>
              </TableRow>
            </template>
          </TableBody>
        </Table>
      </div>

      <!-- Pagination -->
      <div
        v-if="store.meta && store.meta.last_page > 1"
        class="flex items-center justify-between border-t px-4 py-3"
      >
        <span class="text-xs text-muted-foreground">
          {{ store.meta.total }} طلب — صفحة {{ store.meta.current_page }} من {{ store.meta.last_page }}
        </span>
        <div class="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="!store.hasPrevPage || store.loadingList"
            @click="store.prevPage()"
          >
            السابق
          </Button>
          <Button
            variant="outline"
            size="sm"
            :disabled="!store.hasNextPage || store.loadingList"
            @click="store.nextPage()"
          >
            التالي
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
