<script setup lang="ts">
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/layout/PageHeader.vue'
import RequestFormTabs from '@/components/request/RequestFormTabs.vue'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { UserRole } from '@/types/enums'
import { useAuthStore } from '@/stores/auth.store'
import { useRequests } from '@/composables/useRequests'
import type { NavigationGuardNext } from 'vue-router'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests/new'],
})

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const { cloneRequest } = useRequests()

const user = computed(() => authStore.user)
const cloneForbidden = ref(false)
const cloningInProgress = ref(false)

// Leave-guard state — the wizard sets wizardDirty via a provided key
// so the page knows whether to block navigation
const wizardDirty = ref(false)
const wizardSubmitted = ref(false)
const showLeaveDialog = ref(false)
const pendingLeaveNext = ref<NavigationGuardNext | null>(null)

const canCreate = computed(
  () => user.value?.role === UserRole.DATA_ENTRY || user.value?.role === UserRole.BANK_ADMIN,
)

onMounted(async () => {
  const cloneOf = route.query.clone_of
  if (!cloneOf) return

  const sourceId = Number(cloneOf)
  window.history.replaceState(window.history.state, '', route.path)
  cloningInProgress.value = true

  try {
    const newId = await cloneRequest(sourceId)
    await navigateTo(`/requests/${newId}/edit`, { replace: true })
  } catch (err: any) {
    if (err?.statusCode === 403) cloneForbidden.value = true
  } finally {
    cloningInProgress.value = false
  }
})

onBeforeRouteLeave((_to, _from, next) => {
  if (!wizardDirty.value || wizardSubmitted.value) return next()
  pendingLeaveNext.value = next
  showLeaveDialog.value = true
})

function cancelLeave() {
  pendingLeaveNext.value?.(false)
  pendingLeaveNext.value = null
  showLeaveDialog.value = false
}

function confirmLeave() {
  wizardSubmitted.value = true
  pendingLeaveNext.value?.()
  pendingLeaveNext.value = null
  showLeaveDialog.value = false
}
</script>

<template>
  <div v-if="cloneForbidden">
    <PageHeader
      title="لا يمكن نسخ هذا الطلب"
      subtitle="لا تملك صلاحية إنشاء نسخة من هذا الطلب. ارجع إلى قائمة الطلبات أو اختر طلبا ضمن نطاق صلاحياتك."
    />
    <Card class="border-0 p-6 shadow">
      <Button variant="outline" @click="router.push('/requests')">العودة لقائمة الطلبات</Button>
    </Card>
  </div>

  <div v-else-if="cloningInProgress">
    <PageHeader title="جارٍ إنشاء نسخة من الطلب..." />
  </div>

  <div v-else-if="user && canCreate" class="flex flex-col gap-4">
    <PageHeader
      title="تقديم طلب تمويل واردات جديد"
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/' },
        { label: 'طلبات التمويل', to: '/requests' },
        { label: 'طلب جديد' },
      ]"
    />
    <RequestFormTabs
      @dirty="wizardDirty = true"
      @clean="wizardDirty = false"
      @submitted="wizardSubmitted = true"
    />
  </div>

  <div v-else>
    <PageHeader
      title="لا يمكن إنشاء طلب جديد"
      subtitle="إنشاء طلبات تمويل الواردات متاح لمدخل البيانات أو مسؤول البنك فقط."
    />
    <Card class="border-0 p-6 shadow">
      <Button variant="outline" @click="router.push('/requests')">العودة لقائمة الطلبات</Button>
    </Card>
  </div>

  <AlertDialog :open="showLeaveDialog">
    <AlertDialogContent @escape-key-down="cancelLeave">
      <AlertDialogHeader>
        <AlertDialogTitle>مغادرة صفحة الطلب الجديد؟</AlertDialogTitle>
        <AlertDialogDescription>
          لديك بيانات غير محفوظة في هذا الطلب. احفظ الطلب كمسودة قبل المغادرة إذا أردت الاحتفاظ بما
          أدخلته.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel @click="cancelLeave">البقاء في الطلب</AlertDialogCancel>
        <AlertDialogAction
          class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          @click="confirmLeave"
        >
          مغادرة دون حفظ
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
