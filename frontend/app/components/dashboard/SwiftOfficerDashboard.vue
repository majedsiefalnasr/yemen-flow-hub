// @parity-exempt — dashboard sub-component; parity evidence captured at dashboards/swift-officer page level
<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, CheckCircle2, Clock3, UploadCloud, XCircle } from 'lucide-vue-next'
import { useDashboardStore } from '../../stores/dashboard.store'
import type { SwiftOfficerDashboardStats } from '../../composables/useDashboard'
import StatusBadge from '../shared/StatusBadge.vue'
import { UserRole } from '../../types/enums'
import { Card, CardContent } from '../ui/card'
import { Button } from '../ui/button'
import { Skeleton } from '../ui/skeleton'
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '../ui/table'

const router = useRouter()
const store = useDashboardStore()

const stats = computed<SwiftOfficerDashboardStats | null>(() => {
  const raw = store.stats as Partial<SwiftOfficerDashboardStats> | null
  if (!raw) return null
  return {
    pending_swift_upload: raw.pending_swift_upload ?? 0,
    uploaded: raw.uploaded ?? 0,
    final_approved: raw.final_approved ?? 0,
    final_rejected: raw.final_rejected ?? 0,
    swift_queue: Array.isArray(raw.swift_queue) ? raw.swift_queue : [],
  }
})

const queue = computed(() =>
  [...(stats.value?.swift_queue ?? [])].sort((a, b) => {
    const at = new Date(a.updated_at ?? a.created_at ?? 0).getTime()
    const bt = new Date(b.updated_at ?? b.created_at ?? 0).getTime()
    return at - bt
  }),
)

const oldestPending = computed(() => queue.value[0] ?? null)

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-YE', { style: 'currency', currency, minimumFractionDigits: 0 }).format(amount)
}

function hoursInStage(updatedAt: string): number {
  const updated = new Date(updatedAt).getTime()
  if (Number.isNaN(updated)) return 0
  return Math.max(0, Math.floor((Date.now() - updated) / (1000 * 60 * 60)))
}

onMounted(() => { store.loadStats() })
</script>

<template>
  <div class="flex flex-col gap-6" dir="rtl">

    <!-- Skeleton -->
    <div v-if="store.loading" class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1" aria-busy="true">
      <Skeleton v-for="n in 4" :key="n" class="h-24 w-full rounded-xl" />
    </div>

    <!-- Error -->
    <Card v-else-if="store.error" class="border-0 border-s-4 border-s-[var(--severity-red)] bg-background" role="alert">
      <CardContent class="pt-6 flex items-center gap-3">
        <span class="text-destructive flex-1">{{ store.error }}</span>
        <Button variant="outline" size="sm" class="text-destructive border-destructive" @click="store.loadStats()">
          إعادة المحاولة
        </Button>
      </CardContent>
    </Card>

    <template v-else-if="stats">

      <!-- Action strip — pending uploads -->
      <Card
        v-if="stats.pending_swift_upload > 0"
        class="border-0 border-s-4 border-s-[var(--severity-amber)] bg-[var(--severity-amber)]/5 shadow-sm"
        role="alert"
      >
        <CardContent class="pt-4 pb-4 flex items-center gap-3">
          <AlertTriangle class="h-5 w-5 flex-shrink-0 text-[var(--severity-amber)]" aria-hidden="true" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-foreground">{{ stats.pending_swift_upload }} طلبات بانتظار رفع وثائق السويفت</p>
            <p v-if="oldestPending" class="truncate text-xs text-muted-foreground">
              {{ oldestPending.reference_number }} · منذ {{ hoursInStage(oldestPending.updated_at) }} ساعة
            </p>
          </div>
          <Button
            size="sm"
            class="flex-shrink-0 bg-[var(--severity-amber)] text-white hover:opacity-90"
            @click="router.push('/requests?tab=pending_swift')"
          >
            ابدأ الرفع
          </Button>
        </CardContent>
      </Card>

      <!-- KPI grid: 4 clickable cards -->
      <div class="grid grid-cols-4 gap-4 md:grid-cols-2 sm:grid-cols-1">
        <Card
          class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
          role="button"
          tabindex="0"
          :aria-label="`بانتظار رفع السويفت: ${stats.pending_swift_upload}`"
          @click="router.push('/requests?tab=pending_swift')"
          @keydown.enter="router.push('/requests?tab=pending_swift')"
          @keydown.space.prevent="router.push('/requests?tab=pending_swift')"
        >
          <div class="mb-2 h-9 w-9 flex items-center justify-center rounded bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]">
            <Clock3 class="h-5 w-5" aria-hidden="true" />
          </div>
          <p class="text-2xl font-semibold text-[var(--severity-amber)]">{{ stats.pending_swift_upload }}</p>
          <p class="text-xs text-muted-foreground">بانتظار رفع السويفت</p>
        </Card>

        <Card
          class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
          role="button"
          tabindex="0"
          :aria-label="`تم رفع السويفت: ${stats.uploaded}`"
          @click="router.push('/requests?tab=swift_done')"
          @keydown.enter="router.push('/requests?tab=swift_done')"
          @keydown.space.prevent="router.push('/requests?tab=swift_done')"
        >
          <div class="mb-2 h-9 w-9 flex items-center justify-center rounded bg-[var(--info)]/10 text-[var(--info)]">
            <UploadCloud class="h-5 w-5" aria-hidden="true" />
          </div>
          <p class="text-2xl font-semibold text-[var(--info)]">{{ stats.uploaded }}</p>
          <p class="text-xs text-muted-foreground">تم رفع السويفت</p>
        </Card>

        <Card
          class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
          role="button"
          tabindex="0"
          :aria-label="`مكتمل: ${stats.final_approved}`"
          @click="router.push('/requests?tab=completed')"
          @keydown.enter="router.push('/requests?tab=completed')"
          @keydown.space.prevent="router.push('/requests?tab=completed')"
        >
          <div class="mb-2 h-9 w-9 flex items-center justify-center rounded bg-[var(--severity-green)]/10 text-[var(--severity-green)]">
            <CheckCircle2 class="h-5 w-5" aria-hidden="true" />
          </div>
          <p class="text-2xl font-semibold text-[var(--severity-green)]">{{ stats.final_approved }}</p>
          <p class="text-xs text-muted-foreground">مكتمل</p>
        </Card>

        <Card
          class="border-0 p-4 shadow flex flex-col gap-1.5 cursor-pointer hover:shadow-md transition-shadow focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
          role="button"
          tabindex="0"
          :aria-label="`رُفض من اللجنة: ${stats.final_rejected}`"
          @click="router.push('/requests?tab=rejected')"
          @keydown.enter="router.push('/requests?tab=rejected')"
          @keydown.space.prevent="router.push('/requests?tab=rejected')"
        >
          <div class="mb-2 h-9 w-9 flex items-center justify-center rounded bg-[var(--severity-red)]/10 text-[var(--severity-red)]">
            <XCircle class="h-5 w-5" aria-hidden="true" />
          </div>
          <p class="text-2xl font-semibold text-[var(--severity-red)]">{{ stats.final_rejected }}</p>
          <p class="text-xs text-muted-foreground">رُفض من اللجنة</p>
        </Card>
      </div>

      <!-- SWIFT queue table -->
      <Card class="border-0 shadow" aria-labelledby="swift-queue-heading">
        <CardContent class="p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 id="swift-queue-heading" class="text-sm font-semibold text-foreground">طابور السويفت</h2>
          </div>

          <Table aria-label="طابور السويفت">
            <TableHeader>
              <TableRow>
                <TableHead>المرجع</TableHead>
                <TableHead>التاجر</TableHead>
                <TableHead>المبلغ</TableHead>
                <TableHead>الحالة</TableHead>
                <TableHead>العمر بالمرحلة</TableHead>
                <TableHead>المستندات</TableHead>
                <TableHead>إجراء</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableEmpty v-if="queue.length === 0" :colspan="7">
                لا توجد طلبات بانتظار رفع السويفت حالياً ✓
              </TableEmpty>
              <TableRow
                v-for="req in queue"
                :key="req.id"
                class="hover:bg-muted/20"
              >
                <TableCell class="font-mono text-primary">{{ req.reference_number }}</TableCell>
                <TableCell>{{ req.merchant?.name ?? '—' }}</TableCell>
                <TableCell class="font-mono">{{ formatAmount(req.amount, req.currency) }}</TableCell>
                <TableCell>
                  <StatusBadge :status="req.status" :role="UserRole.SWIFT_OFFICER" />
                </TableCell>
                <TableCell
                  :class="hoursInStage(req.updated_at) > 24 ? 'text-[var(--severity-amber)]' : 'text-muted-foreground'"
                >
                  {{ hoursInStage(req.updated_at) }} ساعة
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-1.5">
                    <span :class="req.has_swift_document ? 'rounded-full border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 px-2 py-0.5 text-xs text-[var(--severity-green)]' : 'rounded-full border border-border bg-muted px-2 py-0.5 text-xs text-muted-foreground'">السويفت</span>
                    <span :class="req.has_fx_request_document ? 'rounded-full border border-[var(--severity-green)]/30 bg-[var(--severity-green)]/10 px-2 py-0.5 text-xs text-[var(--severity-green)]' : 'rounded-full border border-border bg-muted px-2 py-0.5 text-xs text-muted-foreground'">طلب تأكيد المصارفة</span>
                  </div>
                </TableCell>
                <TableCell>
                  <div class="flex items-center gap-2">
                    <Button size="sm" class="bg-[var(--info)] text-white hover:opacity-90" @click="router.push(`/requests/${req.id}/swift`)">
                      رفع وثائق السويفت
                    </Button>
                    <Button size="sm" variant="outline" @click="router.push(`/requests/${req.id}`)">
                      تحميل النموذج
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>

    </template>
  </div>
</template>
