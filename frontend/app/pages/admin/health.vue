<script setup lang="ts">
import { Activity, AlertTriangle, Clock, Mail, Server } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import LoadErrorAlert from '@/components/shared/LoadErrorAlert.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useApi } from '@/composables/useApi'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/admin/health'],
})

type SchedulerRow = {
  command: string
  last_ran_at: string | null
  status: string | null
  stale: boolean
}

type HealthPayload = {
  scheduler: SchedulerRow[]
  queue: {
    failed_jobs_count: number
    recent_failures: Array<{
      id: number
      connection: string
      queue: string
      failed_at: string
    }>
  }
  retention: {
    last_runs: Record<string, string | null>
  }
  mail: {
    driver: string
  }
}

const { get } = useApi()
const loading = ref(true)
const loadError = ref<string | null>(null)
const health = ref<HealthPayload | null>(null)

const staleSchedulerCount = computed(
  () => health.value?.scheduler.filter((row) => row.stale).length ?? 0,
)

async function loadHealth(): Promise<void> {
  loading.value = true
  loadError.value = null

  try {
    const response = await get<{ success: boolean; data: HealthPayload }>('/admin/health')
    health.value = response.data
  } catch {
    loadError.value = 'تعذر تحميل حالة النظام التشغيلية.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadHealth()
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="صحة النظام"
      description="مراقبة المجدول، الطوابير، ومهام الاحتفاظ — للمسؤولين فقط."
    />

    <LoadErrorAlert v-if="loadError" :message="loadError" @retry="loadHealth" />

    <template v-else-if="health">
      <MetricGrid>
        <MetricCard
          title="أوامر مجدولة متأخرة"
          :value="String(staleSchedulerCount)"
          :icon="Clock"
          :loading="loading"
        />
        <MetricCard
          title="مهام فاشلة في الطابور"
          :value="String(health.queue.failed_jobs_count)"
          :icon="AlertTriangle"
          :loading="loading"
        />
        <MetricCard
          title="نقل البريد"
          :value="health.mail.driver"
          :icon="Mail"
          :loading="loading"
        />
      </MetricGrid>

      <section class="rounded-xl border border-[var(--outline-variant)] bg-[var(--surface)] p-4">
        <div class="mb-4 flex items-center gap-2">
          <Server class="size-5 text-[var(--primary)]" />
          <h2 class="text-lg font-semibold text-[var(--on-surface)]">المجدول</h2>
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>الأمر</TableHead>
              <TableHead>آخر تشغيل</TableHead>
              <TableHead>الحالة</TableHead>
              <TableHead>تأخر</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="row in health.scheduler" :key="row.command">
              <TableCell class="font-mono text-sm">{{ row.command }}</TableCell>
              <TableCell>{{ row.last_ran_at ?? '—' }}</TableCell>
              <TableCell>{{ row.status ?? '—' }}</TableCell>
              <TableCell>
                <Badge :variant="row.stale ? 'destructive' : 'secondary'">
                  {{ row.stale ? 'متأخر' : 'سليم' }}
                </Badge>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </section>

      <section class="rounded-xl border border-[var(--outline-variant)] bg-[var(--surface)] p-4">
        <div class="mb-4 flex items-center gap-2">
          <Activity class="size-5 text-[var(--primary)]" />
          <h2 class="text-lg font-semibold text-[var(--on-surface)]">آخر تشغيل لمهام الاحتفاظ</h2>
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>الأمر</TableHead>
              <TableHead>آخر تشغيل</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="(ranAt, command) in health.retention.last_runs" :key="command">
              <TableCell class="font-mono text-sm">{{ command }}</TableCell>
              <TableCell>{{ ranAt ?? '—' }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </section>

      <section
        v-if="health.queue.recent_failures.length > 0"
        class="rounded-xl border border-[var(--outline-variant)] bg-[var(--surface)] p-4"
      >
        <div class="mb-4 flex items-center gap-2">
          <AlertTriangle class="size-5 text-[var(--severity-red)]" />
          <h2 class="text-lg font-semibold text-[var(--on-surface)]">أحدث الإخفاقات</h2>
        </div>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>المعرف</TableHead>
              <TableHead>الاتصال</TableHead>
              <TableHead>الطابور</TableHead>
              <TableHead>وقت الإخفاق</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="failure in health.queue.recent_failures" :key="failure.id">
              <TableCell>{{ failure.id }}</TableCell>
              <TableCell>{{ failure.connection }}</TableCell>
              <TableCell>{{ failure.queue }}</TableCell>
              <TableCell>{{ failure.failed_at }}</TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </section>
    </template>
  </div>
</template>
