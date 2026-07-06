<!-- app/pages/workflows/new.vue -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useScreenPermissions } from '@/composables/useScreenPermissions'
import PageHeader from '@/components/layout/PageHeader.vue'
import { Card, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Empty, EmptyMedia, EmptyHeader, EmptyTitle, EmptyDescription } from '@/components/ui/empty'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog'
import { Inbox, ShieldAlert } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const store = useEngineRequestsStore()
const { can } = useScreenPermissions()

const canCreate = computed(() => can('requests', 'CREATE'))

onMounted(async () => {
  if (!canCreate.value) return
  await store.loadAvailableWorkflows()
})

async function startWorkflow(versionId: number) {
  const instance = await store.createInstance({ workflow_version_id: versionId, data: {} })
  await navigateTo(`/workflows/instances/${instance.id}?mode=wizard`)
}

function onDialogOpenChange(open: boolean) {
  if (!open) onCancel()
}

async function onCancel() {
  await navigateTo('/workflows')
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6 py-2" dir="rtl">
    <PageHeader
      title="طلب تمويل جديد"
      subtitle="اختر مسار العمل لبدء طلب تمويل جديد وإدخال بياناته خطوة بخطوة."
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/dashboard' },
        { label: 'طلبات التمويل', to: '/workflows' },
        { label: 'طلب جديد' },
      ]"
    />

    <Dialog :open="true" @update:open="onDialogOpenChange">
      <DialogContent class="max-w-2xl">
        <DialogHeader>
          <DialogTitle>اختر مسار العمل</DialogTitle>
          <DialogDescription>حدد مسار العمل والنسخة المنشورة لبدء طلب جديد</DialogDescription>
        </DialogHeader>

        <Empty v-if="!canCreate">
          <EmptyMedia variant="icon"><ShieldAlert /></EmptyMedia>
          <EmptyHeader>
            <EmptyTitle>غير مصرح بإنشاء الطلبات</EmptyTitle>
            <EmptyDescription>
              إنشاء طلبات التمويل مقصور على موظفي الإدخال. دورك الحالي لا يسمح بذلك.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>

        <div v-else-if="store.loading" class="grid gap-4 sm:grid-cols-2">
          <Skeleton v-for="n in 2" :key="n" class="h-32 w-full rounded-xl" />
        </div>

        <Empty v-else-if="store.availableWorkflows.length === 0">
          <EmptyMedia variant="icon"><Inbox /></EmptyMedia>
          <EmptyHeader>
            <EmptyTitle>لا توجد مسارات عمل متاحة</EmptyTitle>
            <EmptyDescription
              >لا يوجد مسار عمل منشور يمكنك بدء طلب جديد ضمنه حالياً.</EmptyDescription
            >
          </EmptyHeader>
        </Empty>

        <div v-else class="grid gap-4 sm:grid-cols-2">
          <Card
            v-for="workflow in store.availableWorkflows"
            :key="workflow.version_id"
            class="border-0 shadow"
          >
            <CardHeader>
              <CardTitle class="text-sm font-semibold">{{ workflow.name }}</CardTitle>
              <CardDescription class="text-xs"
                >{{ workflow.code }} — الإصدار {{ workflow.version_number }}</CardDescription
              >
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

        <DialogFooter>
          <DialogClose as-child>
            <Button variant="outline" @click="onCancel">إلغاء</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
