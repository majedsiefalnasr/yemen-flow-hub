<script setup lang="ts">
import { toast } from 'vue-sonner'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import TraderForm from '@/components/trader/TraderForm.vue'
import { useTradersStore } from '@/stores/traders'
import {
  TRADER_MANAGEMENT_ROLES,
  type CreateTraderPayload,
  type UpdateTraderPayload,
} from '@/types/trader'
import { extractApiFieldErrors } from '@/utils/apiErrors'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [...TRADER_MANAGEMENT_ROLES],
})

const router = useRouter()
const tradersStore = useTradersStore()
const serverErrors = ref<Record<string, string | undefined>>({})

async function handleSubmit(payload: CreateTraderPayload | UpdateTraderPayload): Promise<void> {
  serverErrors.value = {}

  try {
    const id = await tradersStore.createTrader(payload as CreateTraderPayload)
    toast.success('تم إنشاء التاجر بنجاح')
    await router.push(`/traders/${id}`)
  } catch (err) {
    serverErrors.value = extractApiFieldErrors(err)
    toast.error('تعذّر إنشاء التاجر. تحقق من الحقول وحاول مجدداً.')
  }
}
</script>

<template>
  <div class="mx-auto flex max-w-[1600px] flex-col gap-6 p-6" dir="rtl">
    <div>
      <h1 class="font-heading text-foreground text-2xl font-semibold">إضافة تاجر</h1>
      <p class="text-muted-foreground mt-1 text-sm">
        إنشاء سجل تاجر جديد لإعادة استخدامه في الطلبات.
      </p>
    </div>

    <Alert v-if="tradersStore.error" variant="destructive" role="alert">
      <AlertTitle>تعذّر الحفظ</AlertTitle>
      <AlertDescription>{{ tradersStore.error }}</AlertDescription>
    </Alert>

    <TraderForm
      mode="create"
      :submitting="tradersStore.saving"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />
  </div>
</template>
