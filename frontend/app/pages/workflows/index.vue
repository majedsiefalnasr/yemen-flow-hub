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
import { CheckCircle2, FileText, AlertCircle } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const store = useEngineRequestsStore()
const view = ref<'queue' | 'all'>('queue')

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
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <div class="flex items-center justify-between">
      <h1 class="text-foreground text-lg font-semibold">سير العمل الديناميكي</h1>
      <Button @click="navigateTo('/workflows/new')">
        <FileText class="me-2 h-4 w-4" aria-hidden="true" />
        طلب جديد
      </Button>
    </div>

    <Tabs v-model="view">
      <TabsList variant="line">
        <TabsTrigger value="queue">طابوري</TabsTrigger>
        <TabsTrigger value="all">جميع الطلبات</TabsTrigger>
      </TabsList>
    </Tabs>

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
              v-for="instance in rows"
              :key="instance.id"
              class="cursor-pointer"
              @click="navigateTo(`/workflows/instances/${instance.id}`)"
            >
              <TableCell class="text-primary font-mono">{{ instance.reference }}</TableCell>
              <TableCell>{{ instance.current_stage?.name ?? '—' }}</TableCell>
              <TableCell>{{ instance.status }}</TableCell>
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
            <TableEmpty v-if="rows.length === 0" :columns="4">
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
