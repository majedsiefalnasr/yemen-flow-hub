<script setup lang="ts">
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { Card, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import { Inbox } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const store = useEngineRequestsStore()

onMounted(() => {
  store.loadAvailableWorkflows()
})

async function startWorkflow(versionId: number) {
  const instance = await store.createInstance({ workflow_version_id: versionId, data: {} })
  await navigateTo(`/workflows/instances/${instance.id}`)
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <h1 class="text-foreground text-lg font-semibold">إنشاء طلب جديد</h1>

    <div v-if="store.loading" class="grid grid-cols-2 gap-4">
      <Skeleton v-for="n in 2" :key="n" class="h-32 w-full rounded-xl" />
    </div>

    <Empty v-else-if="store.availableWorkflows.length === 0">
      <EmptyMedia variant="icon"><Inbox /></EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>لا توجد مسارات عمل متاحة</EmptyTitle>
        <EmptyDescription>
          لا يوجد مسار عمل منشور يمكنك بدء طلب جديد ضمنه حالياً.
        </EmptyDescription>
      </EmptyHeader>
    </Empty>

    <div v-else class="grid grid-cols-2 gap-4">
      <Card
        v-for="workflow in store.availableWorkflows"
        :key="workflow.version_id"
        class="border-0 shadow"
      >
        <CardHeader>
          <CardTitle class="text-sm font-semibold">{{ workflow.name }}</CardTitle>
          <CardDescription class="text-xs">
            {{ workflow.code }} — الإصدار {{ workflow.version_number }}
          </CardDescription>
        </CardHeader>
        <CardFooter>
          <Button
            :data-testid="`create-instance-${workflow.id}`"
            @click="startWorkflow(workflow.version_id)"
          >
            بدء الطلب
          </Button>
        </CardFooter>
      </Card>
    </div>
  </div>
</template>
