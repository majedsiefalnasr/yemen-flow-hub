<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import SwiftUploadForm from '@/components/workflow/SwiftUploadForm.vue'
import { Lock } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { RequestStatus, UserRole } from '@/types/enums'
import { getBusinessStatus, ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequests } from '@/composables/useRequests'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests/:id/swift'],
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { fetchRequest, uploadSwift } = useRequests()
const uploading = ref(false)

const request = ref<import('@/types/models').ImportRequest | null>(null)

onMounted(async () => {
  const id = Number(route.params.id)
  if (!id) return
  try {
    request.value = await fetchRequest(id)
  }
  catch { /* handled by showing locked state */ }
})

const allowed = computed(() => {
  if (!request.value || !user.value) return false
  const swiftRoles = [UserRole.SWIFT_OFFICER]
  if (!swiftRoles.includes(user.value.role)) return false
  return (
    request.value.status === RequestStatus.WAITING_FOR_SWIFT
    || request.value.status === RequestStatus.SWIFT_UPLOADED
  )
})

const statusLabel = computed(() => {
  if (!request.value || !user.value) return ''
  return getBusinessStatus(request.value.status, user.value.role).label
})

async function handleUpload(file: File, swiftReference: string) {
  if (!request.value) return
  uploading.value = true
  try {
    await uploadSwift(request.value.id, file)
    request.value = await fetchRequest(request.value.id)
  }
  catch { /* errors surfaced by API layer */ }
  finally {
    uploading.value = false
  }
  void swiftReference
}
</script>

<template>
  <div v-if="request && user && allowed">
    <PageHeader
      title="إرفاق وثيقة السويفت"
      :subtitle="`الطلب ${request.reference_number} · بيانات الطلب مقفلة — يُسمح فقط برفع وثيقة السويفت`"
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/' },
        { label: 'الطلبات', to: '/requests' },
        { label: request.reference_number, to: `/requests/${request.id}` },
        { label: 'السويفت' },
      ]"
    >
      <template #actions>
        <Badge :class="cn('py-1.5 px-3 text-sm')">
          {{ statusLabel }}
        </Badge>
      </template>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-3">
      <Card class="border-0 p-6 shadow lg:col-span-2">
        <div class="mb-4 flex items-center gap-2 text-sm text-muted-foreground">
          <Lock class="h-4 w-4" />
          البيانات أدناه للاطلاع فقط ولا يمكن تعديلها في هذه المرحلة
        </div>

        <fieldset
          disabled
          class="space-y-4 opacity-90"
        >
          <div class="grid gap-4 md:grid-cols-2">
            <div
              v-for="[key, value] in [
                ['التاجر', request.merchant?.name ?? '—'],
                ['البنك', request.bank_name ?? '—'],
                ['المبلغ', `${request.amount.toLocaleString('en-US')} ${request.currency}`],
                ['نوع البضاعة', request.goods_description],
                ['المورد', request.supplier_name],
                ['رقم الفاتورة', request.invoice_number ?? '—'],
                ['الميناء', request.port_of_entry],
              ]"
              :key="key"
              class="space-y-1"
            >
              <Label class="text-xs text-muted-foreground">
                {{ key }}
              </Label>
              <Input
                :model-value="value"
                readonly
                class="bg-muted/40"
              />
            </div>
          </div>
        </fieldset>

        <div class="mt-6 space-y-4 border-t pt-6">
          <h3 class="font-semibold">
            رفع وثيقة السويفت
          </h3>
          <SwiftUploadForm
            :request="request"
            :uploading="uploading"
            @upload="handleUpload"
          />
        </div>
      </Card>
    </div>
  </div>

  <Card
    v-else
    class="border-0 p-8 text-center shadow"
  >
    <Lock class="mx-auto h-10 w-10 text-muted-foreground" />
    <h2 class="mt-4 text-lg font-bold">
      غير مصرح
    </h2>
    <p class="mt-1 text-sm text-muted-foreground">
      لا تملك صلاحية رفع السويفت لهذا الطلب، أو الطلب ليس في مرحلة اعتماد المساندة.
    </p>
    <Button
      class="mt-4"
      variant="outline"
      @click="router.push(request ? `/requests/${request.id}` : '/requests')"
    >
      العودة للطلب
    </Button>
  </Card>
</template>
