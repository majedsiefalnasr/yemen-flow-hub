<script setup lang="ts">
import { useEngineRequestsStore } from '@/stores/engineRequests.store'
import { useEngineFormSchema } from '@/composables/useEngineFormSchema'
import { useEngineRequestActions } from '@/composables/useEngineRequestActions'
import DynamicForm from '@/components/workflow/DynamicForm.vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { Field, FieldLabel } from '@/components/ui/field'
import { AlertTriangle } from 'lucide-vue-next'

definePageMeta({ middleware: ['auth', 'screen'], requiredScreen: 'requests' })

const route = useRoute()
const requestId = computed(() => Number(route.params.id))

const store = useEngineRequestsStore()
const { fieldGroups, fetchSchema } = useEngineFormSchema()
const { executeAction, conflictError } = useEngineRequestActions()

const formRef = ref<InstanceType<typeof DynamicForm> | null>(null)
const formData = ref<Record<string, unknown>>({})
const comment = ref('')

async function load() {
  await store.loadInstance(requestId.value)
  await fetchSchema(requestId.value)
  formData.value = store.current?.data ?? {}
}

onMounted(load)

const availableActions = computed(() => {
  if (!store.current?.current_stage || !store.graph) {
    return []
  }
  return store.graph.edges.filter((edge) => edge.from_stage_id === store.current!.current_stage!.id)
})

async function runAction(transitionId: number, requiresComment: boolean) {
  if (requiresComment && !comment.value.trim()) {
    return
  }
  const validation = await formRef.value?.validate()
  if (validation && !validation.valid) {
    return
  }
  try {
    await executeAction(
      requestId.value,
      transitionId,
      comment.value || null,
      validation?.values ?? formData.value,
      store.current!.version,
    )
    comment.value = ''
    await load()
  } catch {
    // conflictError / fieldErrors already updated by the composable
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 p-6" dir="rtl">
    <div v-if="store.loading">
      <Skeleton class="mb-4 h-8 w-64" />
      <Skeleton class="h-48 w-full" />
    </div>

    <template v-else-if="store.current">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-foreground text-lg font-semibold">{{ store.current.reference }}</h1>
          <Badge variant="outline" class="mt-1">
            {{ store.current.current_stage?.name ?? '—' }}
          </Badge>
        </div>
      </div>

      <Alert v-if="conflictError" variant="destructive" role="alert">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>تعارض في التحديث</AlertTitle>
        <AlertDescription>
          تم تحديث الطلب من مستخدم آخر. تم تحديث البيانات المعروضة.
        </AlertDescription>
      </Alert>

      <Tabs default-value="form" dir="rtl">
        <TabsList>
          <TabsTrigger value="form">النموذج</TabsTrigger>
          <TabsTrigger value="history">السجل</TabsTrigger>
          <TabsTrigger value="documents">المرفقات</TabsTrigger>
        </TabsList>

        <TabsContent value="form" class="mt-4">
          <Card class="border-0 shadow">
            <CardContent class="p-4">
              <DynamicForm
                ref="formRef"
                v-model="formData"
                :field-groups="fieldGroups"
                mode="edit"
                :request-id="requestId"
              />

              <Field class="mt-4">
                <FieldLabel for="comment">ملاحظات</FieldLabel>
                <Textarea id="comment" v-model="comment" placeholder="أضف ملاحظاتك هنا…" rows="3" />
              </Field>

              <div class="mt-4 flex gap-2">
                <Button
                  v-for="action in availableActions"
                  :key="action.id"
                  @click="runAction(action.id, action.requires_comment)"
                >
                  {{ action.action_name ?? action.action_code }}
                </Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="history" class="mt-4">
          <Card class="border-0 shadow">
            <CardHeader>
              <CardTitle class="text-sm font-semibold">سجل الإجراءات</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-2">
              <div
                v-for="entry in store.history"
                :key="entry.id"
                class="border-border border-b pb-2 last:border-0"
              >
                <p class="text-sm">
                  {{ entry.action_code }} — {{ entry.performed_by?.name ?? '—' }}
                </p>
                <p v-if="entry.comments" class="text-muted-foreground text-xs">
                  {{ entry.comments }}
                </p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="documents" class="mt-4">
          <Card class="border-0 shadow">
            <CardHeader>
              <CardTitle class="text-sm font-semibold">المرفقات</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-2">
              <div v-for="doc in store.documents" :key="doc.id" class="text-sm">
                {{ doc.original_name }}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </template>
  </div>
</template>
