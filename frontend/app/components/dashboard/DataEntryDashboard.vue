<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircle2, Clock, RotateCcw, FileText, PlusCircle, Zap, Bell, AlertCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import { useNotificationsStore } from '../../stores/notifications.store'
import { useAuthStore } from '../../stores/auth.store'
import { UserRole } from '../../types/enums'
import type { DataEntryDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import ActionRequiredStrip from '../shared/ActionRequiredStrip.vue'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '../ui/table'

const router = useRouter()
const store = useDashboardStore()
const notificationsStore = useNotificationsStore()
const authStore = useAuthStore()

const authUser = computed(() => authStore.user)

const stats = computed(() => store.stats as DataEntryDashboardStats | null)
const unreadCount = computed(() => notificationsStore.unreadCount)

const hasAnyRequests = computed(() =>
  stats.value !== null && (
    (stats.value.completed ?? 0) > 0
    || (stats.value.under_cby_processing ?? 0) > 0
    || returnedCount.value > 0
    || (stats.value.draft ?? 0) > 0
    || (stats.value.recent_requests?.length ?? 0) > 0
    || (stats.value.draft_requests?.length ?? 0) > 0
  ),
)

// Prefer the backend's aggregate `returned` count when present; only fall back
// to `returned_requests.length` (a sample slice, max 5) when the aggregate is
// unset. Adding the two double-counted the same items in the action strip.
const returnedCount = computed(() =>
  stats.value?.returned ?? stats.value?.returned_requests?.length ?? 0,
)

const actionRequiredCount = computed(() => returnedCount.value)

// First returned request — used for the correction strip reference + reason snippet
const firstReturnedRequest = computed(() => stats.value?.returned_requests?.[0] ?? null)
const returnReasonSnippet = computed(() => {
  const req = firstReturnedRequest.value
  if (!req) return ''
  // bank_return_comment for BANK_RETURNED; support_return_comment for SUPPORT_RETURNED
  const reason = req.bank_return_comment ?? req.support_return_comment ?? req.notes ?? ''
  return reason.length > 80 ? reason.slice(0, 80) + '…' : reason
})

const actionStripDetail = computed(() => {
  if (!firstReturnedRequest.value) return undefined
  const ref = firstReturnedRequest.value.reference_number
  return returnReasonSnippet.value ? `${ref} · ${returnReasonSnippet.value}` : ref
})

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function getKpiIconColor(variant: string): string {
  const colors: Record<string, string> = {
    green: 'text-[var(--severity-green)] bg-[var(--severity-green)]/10',
    blue: 'text-primary bg-primary/10',
    amber: 'text-[var(--severity-amber)] bg-[var(--severity-amber)]/10',
    gray: 'text-muted-foreground bg-muted',
  }
  return colors[variant] ?? colors.gray!
}

const kpiConfig = computed(() => [
  {
    icon: CheckCircle2,
    value: stats.value?.completed ?? 0,
    label: 'مكتمل / صدر التأكيد',
    variant: 'green',
    tab: 'completed',
  },
  {
    icon: Clock,
    value: stats.value?.under_cby_processing ?? 0,
    label: 'قيد معالجة CBY',
    variant: 'blue',
    tab: 'processing',
  },
  {
    icon: RotateCcw,
    value: returnedCount.value,
    label: 'بحاجة تعديل',
    variant: returnedCount.value > 0 ? 'amber' : 'gray',
    tab: 'returned',
  },
  {
    icon: FileText,
    value: stats.value?.draft ?? 0,
    label: 'مسودات',
    variant: 'gray',
    tab: 'draft',
  },
])

onMounted(() => {
  store.loadStats()
  notificationsStore.refreshUnreadCount()
})
</script>

<template>
  <div class="flex flex-col gap-6" >

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4" aria-busy="true" aria-label="جارٍ تحميل الإحصائيات">
      <div v-for="n in 4" :key="n" class="border-0 p-4 shadow" aria-hidden="true">
        <Skeleton class="h-3.5 w-[60px] mb-3" />
        <Skeleton class="h-8 w-[40px]" />
      </div>
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-destructive bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <AlertCircle class="w-4.5 h-4.5 flex-shrink-0 text-[var(--severity-red)]" aria-hidden="true" />
        <span class="text-[var(--severity-red)] flex-1">{{ store.error }}</span>
        <Button variant="outline" size="sm" class="text-[var(--severity-red)] border-destructive" @click="store.loadStats()">
          إعادة المحاولة
        </Button>
      </CardContent>
    </Card>

    <!-- Empty state — no requests at all: hide KPI grid entirely -->
    <template v-else-if="stats && !hasAnyRequests">
      <div class="flex flex-col items-center justify-center py-20 gap-4 text-center">
        <FileText class="h-12 w-12 text-muted-foreground" aria-hidden="true" />
        <p class="text-sm text-muted-foreground">لم تبدأ بعد. ابدأ بأول طلب تمويل واردات.</p>
        <Button @click="router.push('/requests/new')">+ طلب جديد</Button>
      </div>
    </template>

    <template v-else-if="stats">

      <!-- Action-required strip (above KPI grid, hidden when count = 0) -->
      <ActionRequiredStrip
        :count="actionRequiredCount"
        message="طلبات تحتاج تعديل"
        cta-label="ابدأ التعديل"
        cta-route="/requests?tab=returned"
        severity="amber"
        :detail="actionStripDetail"
      />

      <!-- KPI grid: 4 clickable cards -->
      <div class="grid grid-cols-4 max-lg:grid-cols-2 max-md:grid-cols-1 gap-4">
        <template v-for="kpi in kpiConfig" :key="kpi.label">
          <Card
            class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            :class="{ 'border-s-4 border-s-[var(--severity-amber)]': kpi.variant === 'amber' && kpi.value > 0 }"
            role="button"
            tabindex="0"
            :aria-label="`${kpi.label}: ${kpi.value}`"
            @click="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.enter="router.push(`/requests?tab=${kpi.tab}`)"
            @keydown.space.prevent="router.push(`/requests?tab=${kpi.tab}`)"
          >
            <div class="h-9 w-9 rounded flex items-center justify-center flex-shrink-0" :class="getKpiIconColor(kpi.variant)">
              <component :is="kpi.icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <span
              class="text-2xl font-semibold leading-none"
              :class="{
                'text-[var(--severity-amber)]': kpi.variant === 'amber' && kpi.value > 0,
                'text-[var(--severity-green)]': kpi.variant === 'green',
                'text-foreground': kpi.variant !== 'amber' && kpi.variant !== 'green',
              }"
            >
              {{ kpi.value }}
            </span>
            <span class="text-xs text-muted-foreground">{{ kpi.label }}</span>
          </Card>
        </template>
      </div>

      <!-- Quick actions -->
      <section aria-labelledby="qa-heading">
        <h2 id="qa-heading" class="flex items-center gap-2 text-sm font-semibold text-foreground mb-3">
          <Zap class="h-4 w-4" aria-hidden="true" />
          إجراءات سريعة
        </h2>
        <div class="grid grid-cols-3 max-md:grid-cols-1 gap-3">
          <!-- إنشاء طلب جديد -->
          <Card
            class="flex flex-col items-start gap-1 p-4 bg-primary text-primary-foreground border-0 rounded-2xl cursor-pointer hover:opacity-90 transition-opacity focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="إنشاء طلب جديد"
            @click="router.push('/requests/new')"
            @keydown.enter="router.push('/requests/new')"
            @keydown.space.prevent="router.push('/requests/new')"
          >
            <FileText class="h-5 w-5 flex-shrink-0 mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">إنشاء طلب جديد</span>
            <span class="text-xs opacity-75">لبدء طلب تمويل جديد</span>
          </Card>

          <!-- متابعة طلباتي -->
          <Card
            class="flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="متابعة طلباتي"
            @click="router.push('/requests')"
            @keydown.enter="router.push('/requests')"
            @keydown.space.prevent="router.push('/requests')"
          >
            <FileText class="h-5 w-5 flex-shrink-0 text-primary mb-1" aria-hidden="true" />
            <span class="text-sm font-semibold">متابعة طلباتي</span>
            <span class="text-xs text-muted-foreground">كل ما قدّمت رأيناه</span>
          </Card>

          <!-- الإشعارات — with unread badge -->
          <Card
            class="relative flex flex-col items-start gap-1 p-4 bg-background border border-border text-foreground rounded-2xl cursor-pointer hover:border-primary hover:shadow-md transition-all focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
            role="button"
            tabindex="0"
            aria-label="الإشعارات"
            @click="router.push('/notifications')"
            @keydown.enter="router.push('/notifications')"
            @keydown.space.prevent="router.push('/notifications')"
          >
            <div class="relative mb-1">
              <Bell class="h-5 w-5 flex-shrink-0 text-primary" aria-hidden="true" />
              <span
                v-if="unreadCount > 0"
                class="absolute -top-1.5 -end-1.5 min-w-4 h-4 px-0.5 bg-destructive text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none"
                :aria-label="`${unreadCount} إشعار غير مقروء`"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </div>
            <span class="text-sm font-semibold">الإشعارات</span>
            <span class="text-xs text-muted-foreground">آخر التحديثات على طلباتك</span>
          </Card>
        </div>
      </section>

      <!-- Two-column: مسوداتي | آخر نشاطي -->
      <div class="grid grid-cols-2 max-lg:grid-cols-1 gap-4">

        <!-- مسوداتي (draft requests — hidden when empty, no placeholder) -->
        <Card v-if="stats.draft_requests?.length > 0" class="border-0 shadow" aria-labelledby="drafts-heading">
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="drafts-heading" class="text-sm font-semibold text-foreground">مسوداتي</h2>
              <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests?tab=draft')">عرض الكل</Button>
            </div>
            <Table aria-label="مسوداتي">
              <TableHeader>
                <TableRow>
                  <TableHead>المرجع</TableHead>
                  <TableHead>التاجر</TableHead>
                  <TableHead>المبلغ</TableHead>
                  <TableHead>إجراء</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="req in stats.draft_requests.slice(0, 5)"
                  :key="req.id"
                  class="cursor-pointer"
                  @click="router.push(`/requests/${req.id}/edit`)"
                >
                  <TableCell>
                    <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}/edit`" @click.prevent="router.push(`/requests/${req.id}/edit`)">
                      {{ req.reference_number }}
                    </a>
                  </TableCell>
                  <TableCell>{{ req.supplier_name }}</TableCell>
                  <TableCell class="direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</TableCell>
                  <TableCell>
                    <Button size="sm" @click.stop="router.push(`/requests/${req.id}/edit`)">
                      متابعة
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <!-- آخر نشاطي (recent requests) -->
        <Card
          class="border-0 shadow"
          :class="{ 'col-span-2 max-lg:col-span-1': !stats.draft_requests?.length }"
          aria-labelledby="recent-heading"
        >
          <CardContent class="p-4">
            <div class="flex items-center justify-between mb-4">
              <h2 id="recent-heading" class="text-sm font-semibold text-foreground">آخر نشاطي</h2>
              <Button variant="link" size="sm" class="text-xs h-auto p-0" @click="router.push('/requests')">عرض الكل</Button>
            </div>
            <Table aria-label="آخر نشاطي">
              <TableHeader>
                <TableRow>
                  <TableHead>المرجع</TableHead>
                  <TableHead>التاجر</TableHead>
                  <TableHead>المبلغ</TableHead>
                  <TableHead>الحالة</TableHead>
                  <TableHead>إجراء</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableEmpty v-if="!stats.recent_requests?.length" :colspan="5">
                  لا توجد طلبات بعد
                </TableEmpty>
                <TableRow
                  v-for="req in stats.recent_requests.slice(0, 5)"
                  :key="req.id"
                  class="cursor-pointer"
                  @click="router.push(`/requests/${req.id}`)"
                >
                  <TableCell>
                    <a class="font-mono text-primary hover:underline" :href="`/requests/${req.id}`" @click.prevent="router.push(`/requests/${req.id}`)">
                      {{ req.reference_number }}
                    </a>
                  </TableCell>
                  <TableCell>{{ req.supplier_name }}</TableCell>
                  <TableCell class="direction-ltr font-tabular-nums">{{ formatAmount(req.amount, req.currency) }}</TableCell>
                  <TableCell><StatusBadge :status="req.status" :role="UserRole.DATA_ENTRY" /></TableCell>
                  <TableCell>
                    <Button size="sm" variant="outline" @click.stop="router.push(`/requests/${req.id}`)">
                      عرض
                    </Button>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>

      </div>

    </template>
  </div>
</template>
