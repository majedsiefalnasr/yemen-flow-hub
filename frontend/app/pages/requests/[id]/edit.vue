<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import type { NavigationGuardNext } from 'vue-router'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/layout/PageHeader.vue'
import RequestFormTabs from '@/components/request/RequestFormTabs.vue'
import LockedBanner from '@/components/banners/LockedBanner.vue'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
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
import { RequestStatus } from '@/types/enums'
import { useRequestsStore } from '@/stores/requests.store'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests/:id/edit'],
})

const route = useRoute()
const router = useRouter()
const requestsStore = useRequestsStore()

const rawId = route.params.id
const id = Number(Array.isArray(rawId) ? rawId[0] : rawId)

const formDirty = ref(false)
const submitted = ref(false)
const showLeaveDialog = ref(false)
const pendingLeaveNext = ref<NavigationGuardNext | null>(null)

const request = computed(() => requestsStore.currentRequest)
const isEditable = computed(() => {
  const status = request.value?.status
  return (
    status === RequestStatus.DRAFT ||
    status === RequestStatus.DRAFT_REJECTED_INTERNAL ||
    status === RequestStatus.BANK_RETURNED ||
    status === RequestStatus.SUPPORT_RETURNED
  )
})

onMounted(async () => {
  if (Number.isNaN(id)) {
    await router.replace('/requests')
    return
  }

  await requestsStore.loadRequest(id)
  if (requestsStore.error || !request.value) {
    await router.replace('/requests')
    return
  }
  if (!isEditable.value) await router.replace(`/requests/${id}`)
})

onBeforeRouteLeave((_to, _from, next) => {
  if (!formDirty.value || submitted.value) return next()
  pendingLeaveNext.value = next
  showLeaveDialog.value = true
})

function cancelLeave() {
  pendingLeaveNext.value?.(false)
  pendingLeaveNext.value = null
  showLeaveDialog.value = false
}

function confirmLeave() {
  submitted.value = true
  pendingLeaveNext.value?.()
  pendingLeaveNext.value = null
  showLeaveDialog.value = false
}

function onBeforeUnload(e: BeforeUnloadEvent) {
  if (formDirty.value && !submitted.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onUnmounted(() => window.removeEventListener('beforeunload', onBeforeUnload))
</script>

<template>
  <div class="flex flex-col gap-4">
    <PageHeader
      title="تعديل طلب تمويل الواردات"
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/' },
        { label: 'طلبات التمويل', to: '/requests' },
        { label: 'تعديل' },
      ]"
    />

    <template v-if="requestsStore.loadingRequest">
      <Card class="space-y-4 border-0 p-6 shadow">
        <Skeleton class="h-6 w-48" />
        <Skeleton class="h-4 w-full" />
        <Skeleton class="h-4 w-3/4" />
      </Card>
    </template>

    <RequestFormTabs
      v-else-if="request && isEditable"
      :request-id="id"
      :initial-values="request"
      @dirty="formDirty = true"
      @clean="formDirty = false"
      @submitted="submitted = true"
    />

    <LockedBanner v-else-if="request && !isEditable" variant="readonly" />

    <AlertDialog :open="showLeaveDialog">
      <AlertDialogContent @escape-key-down="cancelLeave">
        <AlertDialogHeader>
          <AlertDialogTitle>مغادرة صفحة التعديل؟</AlertDialogTitle>
          <AlertDialogDescription>
            لديك تعديلات غير محفوظة على هذا الطلب. احفظ التعديلات قبل المغادرة إذا أردت الاحتفاظ
            بها.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="cancelLeave">البقاء في الصفحة</AlertDialogCancel>
          <AlertDialogAction
            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            @click="confirmLeave"
          >
            مغادرة دون حفظ
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
