<script setup lang="ts">
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  TableEmpty,
} from '@/components/ui/table'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import {
  Empty,
  EmptyMedia,
  EmptyHeader,
  EmptyTitle,
  EmptyDescription,
  EmptyContent,
} from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertTitle, AlertDescription, AlertAction } from '@/components/ui/alert'
import PageHeader from '@/components/layout/PageHeader.vue'
import MetricCard from '@/components/shared/dashboard/MetricCard.vue'
import MetricGrid from '@/components/shared/dashboard/MetricGrid.vue'
import { BriefcaseBusiness, FileText, Search, Timer, CheckCircle2, AlertCircle } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const store = useEngineRequestsStore()
const view = ref<'queue' | 'all'>('queue')
const query = ref('')

function load() {
  if (view.value === 'queue') {
    store.loadQueue()
  } else {
    store.loadList()
  }
}

onMounted(load)

watch(view, load)

const rows = computed(() => (view.value === 'queue' ? store.queue : store.instances))

const filteredRows = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter((item) => item.reference.toLowerCase().includes(q))
})

const stats = computed(() => ({
  queue: store.queue.length,
  all: store.instances.length,
  waiting: rows.value.filter((item) => item.status === 'ACTIVE').length,
}))

function statusLabel(status: string) {
  if (status === 'ACTIVE') return 'نشط'
  if (status === 'COMPLETED') return 'مكتمل'
  if (status === 'CANCELLED') return 'ملغى'
  return status
}
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-6 p-6" dir="rtl">
    <PageHeader
      title="سير العمل الديناميكي"
      subtitle="متابعة طلبات محرك سير العمل والطلبات التي تنتظر إجراءك"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'سير العمل' }]"
    >
      <template #actions>
        <Button @click="navigateTo('/workflows/new')">
          <FileText class="me-2 h-4 w-4" aria-hidden="true" />
          طلب جديد
        </Button>
      </template>
    </PageHeader>

    <MetricGrid :columns="3">
      <MetricCard label="طابوري" :value="stats.queue" :icon="BriefcaseBusiness" />
      <MetricCard label="جميع الطلبات" :value="stats.all" :icon="FileText" tone="info" />
      <MetricCard label="بانتظار الإجراء" :value="stats.waiting" :icon="Timer" tone="warning" />
    </MetricGrid>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <Tabs v-model="view">
        <TabsList variant="line">
          <TabsTrigger value="queue">طابوري</TabsTrigger>
          <TabsTrigger value="all">جميع الطلبات</TabsTrigger>
        </TabsList>
      </Tabs>
      <div class="relative w-full sm:w-72">
        <Search
          class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2"
          aria-hidden="true"
        />
        <Input v-model="query" class="ps-9" placeholder="بحث بالمرجع..." />
      </div>
    </div>

    <Alert v-if="store.error" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ في التحميل</AlertTitle>
      <AlertDescription>{{ store.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="load">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <Card v-else class="border-0 shadow">
      <CardHeader class="pb-2">
        <CardTitle class="text-sm font-semibold">
          {{ view === 'queue' ? 'طابور العمل' : 'جميع الطلبات' }}
        </CardTitle>
      </CardHeader>
      <CardContent class="p-0">
        <template v-if="store.loading">
          <div class="flex flex-col gap-2 p-4">
            <Skeleton v-for="n in 5" :key="n" class="h-10 w-full" />
          </div>
        </template>
        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="text-right">المرجع</TableHead>
              <TableHead class="text-right">المرحلة الحالية</TableHead>
              <TableHead class="text-right">الحالة</TableHead>
              <TableHead class="text-right">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="instance in filteredRows"
              :key="instance.id"
              class="cursor-pointer"
              @click="navigateTo(`/workflows/instances/${instance.id}`)"
            >
              <TableCell class="text-primary font-mono">{{ instance.reference }}</TableCell>
              <TableCell>{{ instance.current_stage?.name ?? '—' }}</TableCell>
              <TableCell>
                <Badge variant="secondary">{{ statusLabel(instance.status) }}</Badge>
              </TableCell>
              <TableCell @click.stop>
                <Button
                  size="sm"
                  variant="outline"
                  @click="navigateTo(`/workflows/instances/${instance.id}`)"
                >
                  عرض
                </Button>
              </TableCell>
            </TableRow>
            <TableEmpty v-if="filteredRows.length === 0" :columns="4">
              <Empty>
                <EmptyMedia variant="icon"><CheckCircle2 /></EmptyMedia>
                <EmptyHeader>
                  <EmptyTitle>
                    {{ view === 'queue' ? 'الطابور فارغ' : 'لا توجد طلبات' }}
                  </EmptyTitle>
                  <EmptyDescription>
                    {{
                      view === 'queue'
                        ? 'لا توجد طلبات في انتظار إجرائك حالياً ✓'
                        : 'لم يتم إنشاء أي طلبات بعد.'
                    }}
                  </EmptyDescription>
                </EmptyHeader>
                <EmptyContent v-if="view === 'all'">
                  <Button @click="navigateTo('/workflows/new')">إنشاء طلب جديد</Button>
                </EmptyContent>
              </Empty>
            </TableEmpty>
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  </div>
</template>
