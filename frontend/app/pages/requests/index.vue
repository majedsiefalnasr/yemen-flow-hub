<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import { AlertTriangle, Download, FilePlus2, Filter, Lock, Search, Vote } from 'lucide-vue-next'
import { RequestStatus, UserRole } from '@/types/enums'
import {
  ROLE_BUCKETS,
  BANK_ROLES,
  CBY_BANK_FILTER_ROLES,
  getBusinessStatus,
  getStatusProgress,
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
  const role = user.value?.role
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

const canCreateRequest = computed(() =>
  user.value?.role === UserRole.DATA_ENTRY,
)
</script>

<template>
  <div v-if="user">
    <PageHeader
      title="طلبات تمويل الواردات"
      :subtitle="isBankScoped ? 'طلبات جهتك فقط' : 'جميع الطلبات المقدمة عبر المنصة مع حالاتها ومراحل المعالجة'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'الطلبات' }]"
    >
      <template #actions>
        <Button variant="outline">
          <Download class="ms-1 h-4 w-4" />
          تصدير
        </Button>
        <Button
          v-if="canCreateRequest"
          as="a"
          href="/requests/new"
        >
          <FilePlus2 class="ms-1 h-4 w-4" />
          طلب جديد
        </Button>
      </template>
    </PageHeader>

    <Card class="mb-4 overflow-x-auto border-0 p-2 shadow-card">
      <div class="flex min-w-max gap-1">
        <Button
          type="button"
          :class="[
            'h-auto gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-medium shadow-none',
            filter === 'all' ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'bg-transparent text-muted-foreground hover:bg-muted',
          ]"
          @click="filter = 'all'"
        >
          الكل
          <span
            :class="[
              'rounded-full px-1.5 text-[10px] tabular-nums',
              filter === 'all' ? 'bg-white/20' : 'bg-muted',
            ]"
          >
            {{ countForBucket('all') }}
          </span>
        </Button>
        <Button
          v-for="bucket in roleBuckets"
          :key="bucket.key"
          type="button"
          :class="[
            'h-auto gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-medium shadow-none',
            filter === bucket.key ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'bg-transparent text-muted-foreground hover:bg-muted',
          ]"
          @click="filter = bucket.key"
        >
          {{ bucket.label }}
          <span
            :class="[
              'rounded-full px-1.5 text-[10px] tabular-nums',
              filter === bucket.key ? 'bg-white/20' : 'bg-muted',
            ]"
          >
            {{ countForBucket(bucket.key) }}
          </span>
        </Button>
      </div>
    </Card>

    <Card class="mb-4 border-0 p-4 shadow-card">
      <div class="flex flex-wrap gap-3">
        <div class="relative min-w-[220px] flex-1">
          <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            v-model="query"
            placeholder="بحث برقم الطلب، التاجر، أو رقم الفاتورة..."
            class="pe-10"
          />
        </div>

        <Select
          v-if="showBankFilter"
          v-model="bankFilter"
        >
          <SelectTrigger class="w-56">
            <SelectValue placeholder="البنك" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">
              جميع البنوك
            </SelectItem>
            <SelectItem
              v-for="bank in banks"
              :key="bank.id"
              :value="bank.id"
            >
              {{ bank.name_ar }}
            </SelectItem>
          </SelectContent>
        </Select>

        <Button variant="outline">
          <Filter class="ms-1 h-4 w-4" />
          فلاتر متقدمة
        </Button>
      </div>
    </Card>

    <Card class="overflow-hidden border-0 shadow-card">
      <div class="overflow-x-auto">
        <Table class="w-full min-w-[850px] table-fixed text-sm">
          <TableHeader class="bg-muted/50 text-end text-xs text-muted-foreground">
            <TableRow>
              <TableHead class="px-4 py-3 font-medium">
                المرجع
              </TableHead>
              <TableHead class="px-4 py-3 font-medium">
                التاجر / البنك
              </TableHead>
              <TableHead class="px-4 py-3 font-medium">
                النوع
              </TableHead>
              <TableHead class="px-4 py-3 font-medium">
                المبلغ
              </TableHead>
              <TableHead class="px-4 py-3 font-medium">
                الحالة
              </TableHead>
              <TableHead class="px-4 py-3 font-medium">
                التقدم
              </TableHead>
              <TableHead class="sticky start-0 z-10 bg-muted/50 px-4 py-3 font-medium shadow-[6px_0_8px_-6px_rgba(0,0,0,0.12)]">
                إجراء
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="req in filteredRequests"
              :key="req.id"
              class="border-t transition-colors hover:bg-muted/30"
            >
              <TableCell class="px-4 py-3 align-top">
                <div class="flex flex-wrap items-center gap-2">
                  <NuxtLink
                    :to="`/requests/${req.id}`"
                    class="font-mono text-xs font-semibold text-accent hover:underline"
                  >
                    {{ req.reference_number }}
                  </NuxtLink>
                  <Badge
                    v-if="req.duplicate_warnings?.length"
                    variant="destructive"
                    class="gap-1 px-1.5 py-0 text-[10px]"
                  >
                    <AlertTriangle class="h-2.5 w-2.5" />
                    مكرر
                  </Badge>
                  <Badge
                    v-if="req.status === RequestStatus.EXECUTIVE_VOTING_OPEN && (user.role === UserRole.EXECUTIVE_MEMBER || user.role === UserRole.COMMITTEE_DIRECTOR)"
                    class="gap-1 bg-chart-5/15 px-1.5 py-0 text-[10px] text-chart-5"
                  >
                    <Vote class="h-2.5 w-2.5" />
                    باب التصويت مفتوح
                  </Badge>
                  <Badge
                    v-if="req.is_claimed && !req.is_claimed_by_me && user.role === UserRole.SUPPORT_COMMITTEE"
                    class="gap-1 bg-warning/15 px-1.5 py-0 text-[10px] text-warning"
                  >
                    <Lock class="h-2.5 w-2.5" />
                    محجوز: {{ req.claimed_by?.name ?? '—' }}
                  </Badge>
                  <Badge
                    v-if="req.is_claimed_by_me"
                    class="gap-1 bg-warning/15 px-1.5 py-0 text-[10px] text-warning"
                  >
                    <Lock class="h-2.5 w-2.5" />
                    محجوز لك
                  </Badge>
                </div>
                <div class="mt-0.5 truncate text-[10px] text-muted-foreground">
                  {{ req.invoice_number ?? '—' }}
                </div>
              </TableCell>
              <TableCell class="px-4 py-3 align-top">
                <div class="truncate font-medium">
                  {{ req.merchant?.name ?? '—' }}
                </div>
                <div class="truncate text-xs text-muted-foreground">
                  {{ req.bank_name ?? '—' }}
                </div>
              </TableCell>
              <TableCell class="truncate px-4 py-3 align-top text-xs">
                {{ req.goods_description }}
              </TableCell>
              <TableCell class="whitespace-nowrap px-4 py-3 align-top font-semibold tabular-nums">
                {{ req.amount.toLocaleString('en-US') }}
                <span class="me-1 text-xs text-muted-foreground">
                  {{ req.currency }}
                </span>
              </TableCell>
              <TableCell class="whitespace-nowrap px-4 py-3 align-top">
                <Badge variant="secondary" class="whitespace-nowrap font-normal">
                  {{ getBusinessStatus(req.status, user.role).label }}
                </Badge>
              </TableCell>
              <TableCell class="px-4 py-3 align-top">
                <Progress
                  class="h-1.5"
                  :model-value="getStatusProgress(req.status, user.role)"
                />
              </TableCell>
              <TableCell class="sticky start-0 z-10 bg-card px-4 py-3 align-top shadow-[6px_0_8px_-6px_rgba(0,0,0,0.12)]">
                <Button
                  as="a"
                  size="sm"
                  variant="outline"
                  :href="`/requests/${req.id}`"
                >
                  عرض
                </Button>
              </TableCell>
            </TableRow>
            <TableRow v-if="store.loadingList">
              <TableCell
                colspan="7"
                class="px-4 py-8 text-center text-muted-foreground"
              >
                جاري التحميل...
              </TableCell>
            </TableRow>
            <TableRow v-else-if="filteredRequests.length === 0">
              <TableCell
                colspan="7"
                class="px-4 py-8 text-center text-muted-foreground"
              >
                لا توجد طلبات.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <div
        v-if="store.meta && (store.meta.last_page > 1)"
        class="flex items-center justify-between border-t px-4 py-3 text-sm"
      >
        <span class="text-muted-foreground">
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
    </Card>
  </div>
</template>
