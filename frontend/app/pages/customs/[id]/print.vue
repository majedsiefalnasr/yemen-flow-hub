<script setup lang="ts">
import {
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  Download,
  FileSignature,
  FileText,
  Lock,
  Printer,
  ShieldCheck,
  Stamp,
  ZoomIn,
  ZoomOut,
} from 'lucide-vue-next'
import PrintablePermit from '@/components/customs/PrintablePermit.vue'
import { RequestStatus, UserRole } from '@/types/enums'
import { getBusinessStatus } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequestsStore } from '@/stores/requests.store'

const route = useRoute()
const authStore = useAuthStore()
const store = useRequestsStore()
const { notify, error: toastError } = useToast()

const user = computed(() => authStore.user)
const request = computed(() => store.currentRequest)

const zoom = ref(0.85)
const confirmIssueOpen = ref(false)

const id = computed(() => Number(route.params.id))

onMounted(async () => {
  await store.loadRequest(id.value)
})

const issued = computed(() => Boolean(request.value?.customs_declaration))

const canIssueNow = computed(() => {
  if (!request.value || !user.value) return false
  const canIssueRoles = [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR]
  return (
    canIssueRoles.includes(user.value.role) &&
    request.value.status === RequestStatus.EXECUTIVE_APPROVED
  )
})

const canView = computed(() => {
  if (!request.value || !user.value) return false
  const viewRoles = [UserRole.CBY_ADMIN, UserRole.COMMITTEE_DIRECTOR, UserRole.EXECUTIVE_MEMBER]
  return issued.value || canIssueNow.value || viewRoles.includes(user.value.role)
})

const stageBlocked = computed(() => Boolean(request.value && !issued.value && !canIssueNow.value))

const stageStatus = computed(() => {
  if (!request.value || !user.value) return null
  return getBusinessStatus(request.value.status, user.value.role)
})

function setZoom(nextZoom: number) {
  zoom.value = Math.min(1.5, Math.max(0.5, nextZoom))
}

function formatDate(value?: string | null) {
  return value ? new Date(value).toLocaleString('ar-EG') : 'غير متاح'
}

function formatDay(value?: string | null) {
  return value ? new Date(value).toLocaleDateString('ar-EG') : 'غير متاح'
}

function printPage() {
  if (import.meta.client) window.print()
}

async function performIssue() {
  if (!request.value || !canIssueNow.value) return
  try {
    await store.issueCustomsDeclaration(request.value.id)
    notify(
      `تم إصدار تأكيد مصارفة خارجية رقم ${request.value.customs_declaration?.declaration_number} بنجاح`,
    )
  } catch {
    toastError('فشل إصدار تأكيد مصارفة خارجية')
  } finally {
    confirmIssueOpen.value = false
  }
}
</script>

<template>
  <div v-if="user && request" class="space-y-4">
    <div v-if="!canView" class="mx-auto max-w-md p-8 text-center">
      <Card class="border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5 p-8">
        <Lock class="mx-auto mb-3 h-10 w-10 text-[var(--severity-amber)]" />
        <h2 class="mb-1 text-lg font-bold">غير مصرح بمعاينة التأكيد</h2>
        <p class="text-muted-foreground mb-4 text-sm">
          معاينة وإصدار تأكيد مصارفة خارجية متاح لأعضاء اللجنة التنفيذية أو إدارة المنصة فقط.
        </p>
        <Button as="a" variant="outline" :href="`/requests/${request.id}`">
          <ArrowRight class="ms-1 h-4 w-4" />
          العودة للطلب
        </Button>
      </Card>
    </div>

    <template v-else>
      <div class="print:hidden">
        <Card class="border-0 p-4 shadow">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <h1 class="flex items-center gap-2 text-lg font-bold">
                <FileText class="text-accent h-5 w-5" />
                {{ issued ? 'تأكيد مصارفة خارجية' : 'معاينة تأكيد مصارفة خارجية' }}
              </h1>
              <p class="text-muted-foreground mt-0.5 text-xs">
                طلب {{ request.reference_number }}، {{ request.merchant?.name ?? 'غير متاح' }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <Button as="a" variant="outline" :href="`/requests/${request.id}`">
                <ArrowRight class="ms-1 h-4 w-4" />
                العودة للطلب
              </Button>
              <Button v-if="issued" @click="printPage()">
                <Printer class="ms-1 h-4 w-4" />
                طباعة / تنزيل PDF
              </Button>
              <Button
                v-else-if="canIssueNow"
                class="bg-accent hover:bg-accent/90"
                :disabled="store.issuingCustoms"
                @click="confirmIssueOpen = true"
              >
                <FileSignature class="ms-1 h-4 w-4" />
                إصدار تأكيد مصارفة خارجية رسمياً
              </Button>
            </div>
          </div>

          <div
            v-if="issued"
            class="mt-4 flex items-center gap-3 rounded-lg border border-[var(--severity-green)]/20 bg-[var(--severity-green)]/5 p-3"
          >
            <CheckCircle2 class="h-5 w-5 shrink-0 text-[var(--severity-green)]" />
            <div class="flex-1 text-sm">
              <div class="font-semibold text-[var(--severity-green)]">
                تم إصدار تأكيد مصارفة خارجية بنجاح
              </div>
              <div class="text-muted-foreground text-xs">
                رقم الوثيقة
                <span class="font-mono font-semibold">{{
                  request.customs_declaration?.declaration_number
                }}</span>
                ، بواسطة {{ request.customs_declaration?.issuer?.name ?? user?.name }}،
                {{ formatDate(request.customs_declaration?.issued_at) }}
              </div>
            </div>
          </div>

          <div
            v-else-if="stageBlocked"
            class="mt-4 flex items-center gap-3 rounded-lg border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5 p-3"
          >
            <AlertTriangle class="h-5 w-5 shrink-0 text-[var(--severity-amber)]" />
            <div class="flex-1 text-sm">
              <div class="font-semibold">لا يمكن إصدار تأكيد مصارفة خارجية حالياً</div>
              <div class="text-muted-foreground text-xs">
                الطلب في مرحلة
                <span class="font-medium">{{ stageStatus?.label }}</span
                >. يجب اعتماد التصويت التنفيذي أولاً.
              </div>
            </div>
          </div>

          <div
            v-else
            class="border-info/30 bg-info/5 mt-4 flex items-center gap-3 rounded-lg border p-3"
          >
            <ShieldCheck class="text-info h-5 w-5 shrink-0" />
            <div class="flex-1 text-sm">
              <div class="font-semibold">جاهز للإصدار</div>
              <div class="text-muted-foreground text-xs">
                راجع المعاينة أدناه ثم اضغط "إصدار تأكيد مصارفة خارجية رسمياً" لتوقيع وإغلاق الطلب
                نهائياً.
              </div>
            </div>
          </div>
        </Card>
      </div>

      <div class="overflow-hidden rounded-xl border bg-[oklch(0.22_0.02_260)] shadow print:hidden">
        <div
          class="flex items-center justify-between bg-[oklch(0.18_0.02_260)] px-4 py-2 text-xs text-white"
        >
          <div class="flex items-center gap-2">
            <FileText class="h-4 w-4 text-[var(--severity-red)]" />
            <span class="font-mono"
              >{{
                request.customs_declaration?.declaration_number ??
                `DRAFT-${request.reference_number}`
              }}.pdf</span
            >
            <span
              v-if="issued"
              class="inline-flex items-center gap-1 rounded bg-[var(--severity-green)]/10 px-2 py-0.5 text-[10px] text-[var(--severity-green)]"
            >
              <ShieldCheck class="h-3 w-3" />
              موقّع إلكترونياً
            </span>
          </div>

          <div class="flex items-center gap-1">
            <Button
              size="icon"
              variant="ghost"
              class="h-7 w-7 text-white hover:bg-white/10"
              @click="setZoom(zoom - 0.1)"
            >
              <ZoomOut class="h-3.5 w-3.5" />
            </Button>
            <span class="w-12 text-center tabular-nums">{{ Math.round(zoom * 100) }}%</span>
            <Button
              size="icon"
              variant="ghost"
              class="h-7 w-7 text-white hover:bg-white/10"
              @click="setZoom(zoom + 0.1)"
            >
              <ZoomIn class="h-3.5 w-3.5" />
            </Button>
            <span class="mx-2 h-4 w-px bg-white/20" />
            <Button
              size="icon"
              variant="ghost"
              class="h-7 w-7 text-white hover:bg-white/10"
              @click="printPage()"
            >
              <Download class="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>

        <div
          class="grid max-h-[80vh] place-items-start justify-center overflow-auto bg-[oklch(0.25_0.02_260)] px-2 py-6"
        >
          <div :style="{ transform: `scale(${zoom})`, transformOrigin: 'top center' }">
            <PrintablePermit
              :request="request"
              :watermark="!issued"
              :format-date="formatDate"
              :format-day="formatDay"
            />
          </div>
        </div>
      </div>

      <div class="hidden print:block">
        <PrintablePermit
          :request="request"
          :watermark="false"
          :format-date="formatDate"
          :format-day="formatDay"
        />
      </div>

      <Dialog v-model:open="confirmIssueOpen">
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle class="flex items-center gap-2">
              <Stamp class="text-accent h-5 w-5" />
              تأكيد إصدار المصارفة الخارجية
            </DialogTitle>
            <DialogDescription class="space-y-2 text-start">
              <span class="block">
                سيتم توقيع وإصدار تأكيد مصارفة خارجية للطلب
                <span class="font-mono font-semibold">{{ request.reference_number }}</span>
                بشكل نهائي ولن يمكن التراجع.
              </span>
              <span
                class="text-foreground block rounded border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/5 p-2 text-xs"
              >
                سيتم إكمال دورة الطلب وإغلاقها فور الإصدار.
              </span>
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" @click="confirmIssueOpen = false"> إلغاء </Button>
            <Button
              class="bg-accent hover:bg-accent/90"
              :disabled="store.issuingCustoms"
              @click="performIssue"
            >
              <FileSignature class="ms-1 h-4 w-4" />
              تأكيد الإصدار
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </template>
  </div>

  <div v-else-if="user" class="mx-auto max-w-md p-8 text-center">
    <Card class="border-destructive/30 bg-[var(--severity-red)]/5 p-8">
      <AlertTriangle class="mx-auto mb-3 h-10 w-10 text-[var(--severity-red)]" />
      <h2 class="mb-1 text-lg font-bold">الطلب غير موجود</h2>
      <p class="text-muted-foreground mb-4 text-sm">رقم الطلب {{ id }} غير معروف.</p>
      <Button as="a" variant="outline" href="/customs">
        <ArrowRight class="ms-1 h-4 w-4" />
        العودة لقائمة التأكيدات
      </Button>
    </Card>
  </div>
</template>

<style>
@media print {
  body {
    background: white !important;
  }

  aside,
  header,
  footer,
  nav,
  .print\:hidden {
    display: none !important;
  }

  main {
    max-width: none !important;
    padding: 0 !important;
  }

  @page {
    size: A4;
    margin: 0;
  }
}
</style>
