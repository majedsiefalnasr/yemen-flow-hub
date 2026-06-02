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
  }
  catch {
    loadError.value = 'تعذّر تحميل بيانات الطلب. تحقق من الاتصال وأعد المحاولة.'
    request.value = null
  }
  finally {
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
  if (!request.value || !user.value) return 'غير متاح حالياً.'
  if (user.value.role !== UserRole.SWIFT_OFFICER) return 'هذه الصفحة مخصصة لموظفي السويفت بالبنك.'
  if (request.value.status !== RequestStatus.WAITING_FOR_SWIFT) return 'هذا الطلب ليس في مرحلة السويفت.'
  return 'غير متاح حالياً.'
})

async function handleUpload(payload: { swiftReference: string; swiftFile: File; fxRequestFile: File }): Promise<void> {
  if (!request.value) return

  lockedStateError.value = ''
  uploadError.value = ''
  uploading.value = true
  try {
    await uploadSwift(request.value.id, payload)
    completed.value = true
    await loadRequest()
  }
  catch (error: unknown) {
    const message = error instanceof Error ? error.message : ''
    if (message.includes('WORKFLOW_LOCKED_STATE') || message.includes('403')) {
      lockedStateError.value = 'تم تغيير حالة الطلب أثناء العمل. حدّث الصفحة للمتابعة.'
    }
    else {
      uploadError.value = message || 'تعذّر تسليم وثائق السويفت. حاول مرة أخرى.'
    }
  }
  finally {
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
    <LoadErrorAlert
      :message="loadError"
      title="تعذّر تحميل الطلب"
      @retry="loadRequest()"
    />
  </div>

  <div v-else-if="request && canAccessPage">
    <PageHeader
      title="تسليم وثائق السويفت"
      subtitle="صفحة تنفيذ متخصصة لوثائق السويفت وطلب تأكيد المصارفة الخارجية"
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

    <div class="grid gap-6 lg:grid-cols-[0.95fr_1.35fr]">
      <aside class="sticky top-4 h-fit rounded-2xl border border-border bg-background p-5">
        <h2 class="mb-4 text-sm font-semibold">ملخص بيانات الطلب (مقفلة)</h2>
        <div class="space-y-3 text-sm">
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">المرجع</p>
              <p class="font-mono">{{ request.reference_number }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">التاجر</p>
              <p>{{ request.merchant?.name ?? '—' }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">المورد</p>
              <p>{{ request.supplier_name }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">المبلغ</p>
              <p class="font-mono">{{ request.amount.toLocaleString('en-US') }} {{ request.currency }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">شروط الدفع</p>
              <p>{{ request.payment_terms ?? '—' }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">رقم/تاريخ الفاتورة</p>
              <p>{{ request.invoice_number ?? '—' }} / {{ request.invoice_date ?? '—' }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">ميناء الوصول</p>
              <p>{{ request.arrival_port ?? request.port_of_entry ?? '—' }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2">
            <Lock class="mt-0.5 h-4 w-4 text-muted-foreground" />
            <div>
              <p class="text-xs text-muted-foreground">رقم بوليصة الشحن</p>
              <p>{{ request.bl_number ?? '—' }}</p>
            </div>
          </div>
        </div>

        <Button variant="outline" class="mt-5 w-full" @click="router.push(`/requests/${request.id}`)">
          عرض كامل بيانات الطلب
        </Button>
      </aside>

      <section class="rounded-2xl border border-border bg-background p-5">
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

        <div v-if="completed" class="rounded-xl border border-[var(--color-border-success)] bg-[var(--color-surface-success)] p-4">
          <p class="text-sm font-semibold text-[var(--color-text-success)]">تم تسليم وثائق السويفت بنجاح</p>
          <Button class="mt-3" @click="router.push('/requests?tab=pending_swift')">
            العودة إلى الطابور
          </Button>
        </div>

        <div v-else-if="canUpload">
          <SwiftUploadForm
            :request="request"
            :uploading="uploading"
            @upload="handleUpload"
          />
        </div>

        <div v-else class="rounded-xl border border-border bg-muted/20 p-5 text-sm text-muted-foreground">
          تم تسليم السويفت بالفعل أو أن الطلب لم يعد في مرحلة الرفع.
        </div>
      </section>
    </div>
  </div>

  <Card v-else class="border-0 p-8 text-center shadow">
    <Lock class="mx-auto h-10 w-10 text-muted-foreground" />
    <h2 class="mt-4 text-lg font-bold">غير متاح حالياً</h2>
    <p class="mt-1 text-sm text-muted-foreground">{{ deniedMessage }}</p>
    <Button class="mt-4" variant="outline" @click="router.push('/requests')">
      العودة
    </Button>
  </Card>
</template>
