<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import SwiftUploadForm from '@/components/workflow/SwiftUploadForm.vue'
import { Lock } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { RequestStatus, UserRole } from '@/types/enums'
import { getBusinessStatus, ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequests } from '@/composables/useRequests'
import { Skeleton } from '@/components/ui/skeleton'
import LoadErrorAlert from '@/components/shared/LoadErrorAlert.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/requests/:id/swift'],
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { fetchRequest, uploadSwift } = useRequests()

const loading = ref(true)
const loadError = ref<string | null>(null)
const uploading = ref(false)
const request = ref<import('@/types/models').ImportRequest | null>(null)
const lockedStateError = ref('')
const uploadError = ref('')
const completed = ref(false)

async function loadRequest(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    request.value = await fetchRequest(Number(route.params.id))
  } catch {
    loadError.value = 'تعذّر تحميل بيانات الطلب. تحقق من الاتصال وأعد المحاولة.'
    request.value = null
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadRequest()
})

const hasSwiftRole = computed(() => user.value?.role === UserRole.SWIFT_OFFICER)
const isWaitingForSwift = computed(() => request.value?.status === RequestStatus.WAITING_FOR_SWIFT)

const canAccessPage = computed(() => hasSwiftRole.value && !!request.value)
const canUpload = computed(() => canAccessPage.value && isWaitingForSwift.value)

const statusLabel = computed(() => {
  if (!request.value || !user.value) return ''
  return getBusinessStatus(request.value.status, user.value.role).label
})

const deniedMessage = computed(() => {
  if (!request.value || !user.value) return 'هذه الصفحة غير متاحة حاليا.'
  if (user.value.role !== UserRole.SWIFT_OFFICER)
    return 'تسليم وثائق السويفت متاح لموظف السويفت فقط.'
  if (request.value.status !== RequestStatus.WAITING_FOR_SWIFT)
    return 'هذا الطلب ليس في مرحلة انتظار السويفت.'
  return 'هذه الصفحة غير متاحة حاليا.'
})

async function handleUpload(payload: {
  swiftReference: string
  swiftFile: File
  fxRequestFile: File
}): Promise<void> {
  if (!request.value) return

  lockedStateError.value = ''
  uploadError.value = ''
  uploading.value = true
  try {
    await uploadSwift(request.value.id, payload)
    completed.value = true
    await loadRequest()
  } catch (error: any) {
    const message = error instanceof Error ? error.message : ''
    if (message.includes('WORKFLOW_LOCKED_STATE') || message.includes('403')) {
      lockedStateError.value = 'تم تغيير حالة الطلب أثناء العمل. حدّث الصفحة للمتابعة.'
    } else {
      uploadError.value = message || 'تعذّر تسليم وثائق السويفت. تحقق من الملفات وأعد المحاولة.'
    }
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div v-if="loading" class="p-6" aria-busy="true" aria-label="جارٍ التحميل">
    <Skeleton class="h-8 w-64 rounded" />
    <Skeleton class="mt-4 h-48 w-full rounded-xl" />
  </div>

  <div v-else-if="loadError" class="p-6">
    <LoadErrorAlert :message="loadError" title="تعذّر تحميل الطلب" @retry="loadRequest()" />
  </div>

  <div v-else-if="request && canAccessPage">
    <PageHeader
      title="تسليم وثائق السويفت"
      subtitle="أدخل مرجع السويفت وارفع وثيقة السويفت وطلب تأكيد المصارفة الخارجية."
      :breadcrumbs="[
        { label: 'الرئيسية', to: '/' },
        { label: 'الطلبات', to: '/requests' },
        { label: request.reference_number, to: `/requests/${request.id}` },
        { label: 'السويفت' },
      ]"
    >
      <template #actions>
        <Badge :class="cn('px-3 py-1.5 text-sm')">
          {{ statusLabel }}
        </Badge>
      </template>
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-[0.95fr_1.35fr]">
      <aside class="border-border bg-background sticky top-4 h-fit rounded-2xl border p-5">
        <h2 class="font-heading text-foreground mb-4 text-base leading-6 font-semibold">
          ملخص بيانات الطلب للقراءة فقط
        </h2>
        <div class="space-y-3 text-sm">
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">المرجع</p>
              <p class="text-foreground font-mono text-sm leading-6 tabular-nums">
                {{ request.reference_number }}
              </p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
                المستورد
              </p>
              <p class="text-foreground text-sm leading-6">
                {{ request.merchant?.name ?? 'غير محدد' }}
              </p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">المورد</p>
              <p class="text-foreground text-sm leading-6">{{ request.supplier_name }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">المبلغ</p>
              <p class="text-foreground font-mono text-sm leading-6 tabular-nums">
                {{ request.amount.toLocaleString('en-US') }} {{ request.currency }}
              </p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
                شروط الدفع
              </p>
              <p class="text-foreground text-sm leading-6">
                {{ request.payment_terms ?? 'غير محدد' }}
              </p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
                رقم/تاريخ الفاتورة
              </p>
              <p class="text-foreground text-sm leading-6">
                {{ request.invoice_number ?? 'غير محدد' }} /
                {{ request.invoice_date ?? 'غير محدد' }}
              </p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
                ميناء الوصول
              </p>
              <p class="text-foreground text-sm leading-6">
                {{ request.arrival_port ?? request.port_of_entry ?? 'غير محدد' }}
              </p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="text-muted-foreground mt-0.5 h-4 w-4" />
            <div>
              <p class="font-section text-muted-foreground text-xs leading-5 font-medium">
                رقم بوليصة الشحن
              </p>
              <p class="text-foreground text-sm leading-6">
                {{ request.bl_number ?? 'غير محدد' }}
              </p>
            </div>
          </div>
        </div>

        <Button
          variant="outline"
          class="mt-5 w-full"
          @click="router.push(`/requests/${request.id}`)"
        >
          عرض كامل بيانات الطلب
        </Button>
      </aside>

      <section class="border-border bg-background rounded-2xl border p-5">
        <LoadErrorAlert
          v-if="lockedStateError"
          class="mb-4"
          :message="lockedStateError"
          title="تغيّرت حالة الطلب"
          retry-label="تحديث الصفحة"
          @retry="loadRequest"
        />

        <LoadErrorAlert
          v-if="uploadError"
          class="mb-4"
          :message="uploadError"
          title="تعذّر تسليم الوثائق"
          :show-retry="false"
        />

        <div
          v-if="completed"
          class="rounded-xl border border-[var(--color-border-success)] bg-[var(--color-surface-success)] p-4"
        >
          <p class="font-section text-sm leading-5 font-semibold text-[var(--color-text-success)]">
            تم تسليم وثائق السويفت وانتقل الطلب للمرحلة التالية.
          </p>
          <Button class="mt-3" @click="router.push('/requests?tab=pending_swift')">
            العودة إلى الطابور
          </Button>
        </div>

        <div v-else-if="canUpload">
          <SwiftUploadForm :request="request" :uploading="uploading" @upload="handleUpload" />
        </div>

        <div
          v-else
          class="border-border bg-muted/20 text-muted-foreground rounded-xl border p-5 text-sm"
        >
          لا يمكن رفع وثائق السويفت لهذا الطلب لأن حالته لم تعد بانتظار السويفت.
        </div>
      </section>
    </div>
  </div>

  <Card v-else class="border-0 p-8 text-center shadow">
    <Lock class="text-muted-foreground mx-auto h-10 w-10" />
    <h2 class="font-heading text-foreground mt-4 text-base leading-6 font-semibold">
      لا يمكن فتح صفحة السويفت
    </h2>
    <p class="text-muted-foreground mt-1 text-sm">{{ deniedMessage }}</p>
    <Button class="mt-4" variant="outline" @click="router.push('/requests')">
      العودة إلى الطلبات
    </Button>
  </Card>
</template>
