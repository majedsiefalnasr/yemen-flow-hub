<script setup lang="ts">
import { toast } from 'vue-sonner'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
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

const route = useRoute()
const router = useRouter()
const tradersStore = useTradersStore()
const serverErrors = ref<Record<string, string | undefined>>({})
const traderId = computed(() => Number(route.params.id))
const isValidTraderId = computed(() => Number.isInteger(traderId.value) && traderId.value > 0)
const routeError = computed(() =>
  isValidTraderId.value ? null : 'رابط التاجر غير صالح. ارجع إلى قائمة التجار.',
)

async function loadTrader(): Promise<void> {
  if (routeError.value) return
  await tradersStore.loadTrader(traderId.value)
}

async function handleSubmit(payload: CreateTraderPayload | UpdateTraderPayload): Promise<void> {
  serverErrors.value = {}
  if (routeError.value || !tradersStore.currentTrader) {
    toast.error('لا يمكن تحديث تاجر غير محمّل أو برابط غير صالح.')
    return
  }

  try {
    await tradersStore.updateTrader(traderId.value, payload as UpdateTraderPayload)
    toast.success('تم تحديث بيانات التاجر بنجاح')
    await router.push(`/traders/${traderId.value}`)
  } catch (err) {
    serverErrors.value = extractApiFieldErrors(err)
    toast.error('تعذّر تحديث بيانات التاجر. تحقق من الحقول وحاول مجدداً.')
  }
}

onMounted(() => {
  void loadTrader()
})
</script>

<template>
  <div class="mx-auto flex max-w-[1600px] flex-col gap-6 p-6" dir="rtl">
    <div>
      <h1 class="font-heading text-foreground text-2xl font-semibold">تعديل بيانات التاجر</h1>
      <p class="text-muted-foreground mt-1 text-sm">تحديث بيانات التاجر والشركات والملاك.</p>
    </div>

    <Alert v-if="routeError" variant="destructive" role="alert">
      <AlertTitle>تعذّر فتح صفحة التعديل</AlertTitle>
      <AlertDescription>{{ routeError }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="router.push('/traders')">
          العودة إلى قائمة التجار
        </Button>
      </AlertAction>
    </Alert>

    <div v-else-if="tradersStore.loading" class="space-y-4">
      <Skeleton class="h-32 w-full rounded-xl" />
      <Skeleton class="h-64 w-full rounded-xl" />
    </div>

    <Alert v-else-if="tradersStore.error" variant="destructive" role="alert">
      <AlertTitle>تعذّر تحميل بيانات التاجر</AlertTitle>
      <AlertDescription>{{ tradersStore.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="loadTrader()">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <TraderForm
      v-else-if="tradersStore.currentTrader"
      mode="edit"
      :trader="tradersStore.currentTrader"
      :submitting="tradersStore.saving"
      :server-errors="serverErrors"
      @submit="handleSubmit"
    />

    <Alert v-else variant="destructive" role="alert">
      <AlertTitle>تعذّر فتح صفحة التعديل</AlertTitle>
      <AlertDescription>لم يتم تحميل بيانات التاجر المطلوبة للتعديل.</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="loadTrader()">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>
  </div>
</template>
