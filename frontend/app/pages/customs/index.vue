<script setup lang="ts">
import { CheckCircle2, FileSignature, PackageCheck, Truck } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { RequestStatus } from '@/types/enums'
import type { ImportRequest } from '@/types/models'
import { getBusinessStatus, ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequests } from '@/composables/useRequests'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/customs'],
})

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { fetchRequests } = useRequests()

const allRequests = ref<ImportRequest[]>([])

onMounted(async () => {
  const result = await fetchRequests({ per_page: 200 })
  allRequests.value = result.data
})

const ready = computed(() =>
  allRequests.value.filter(r =>
    r.status === RequestStatus.EXECUTIVE_APPROVED || r.status === RequestStatus.FX_CONFIRMATION_PENDING,
  ),
)

const issued = computed(() =>
  allRequests.value.filter(r =>
    r.status === RequestStatus.CUSTOMS_DECLARATION_ISSUED || r.status === RequestStatus.COMPLETED,
  ),
)
</script>

<template>
  <div v-if="user">
    <PageHeader
      title="إصدار تأكيد مصارفة خارجية"
      subtitle="إصدار وطباعة تأكيدات المصارفة الخارجية للطلبات المعتمدة من اللجنة التنفيذية"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'تأكيد مصارفة خارجية' }]"
    />

    <div class="grid gap-6 lg:grid-cols-2">
      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 flex items-center gap-2 font-semibold">
          <PackageCheck class="h-5 w-5 text-[var(--severity-green)]" />
          طلبات جاهزة للإصدار ({{ ready.length }})
        </h3>

        <div class="space-y-3">
          <div
            v-if="ready.length === 0"
            class="text-sm text-muted-foreground"
          >
            لا توجد طلبات جاهزة حالياً.
          </div>

          <div
            v-for="request in ready"
            :key="request.id"
            class="flex items-center gap-3 rounded-lg border p-3 transition-colors hover:border-[var(--severity-green)]/20"
          >
            <div class="grid h-11 w-11 place-items-center rounded-lg bg-[var(--severity-green)]/10 text-[var(--severity-green)]">
              <Truck class="h-5 w-5" />
            </div>

            <div class="min-w-0 flex-1">
              <div class="font-mono text-sm font-semibold">
                {{ request.reference_number }}
              </div>
              <div class="truncate text-xs text-muted-foreground">
                {{ request.merchant?.name }} · {{ request.port_of_entry }}
              </div>
            </div>

            <Button
              as="a"
              size="sm"
              :href="`/customs/${request.id}/print`"
            >
              <FileSignature class="ms-1 h-3.5 w-3.5" />
              إصدار تأكيد مصارفة خارجية
            </Button>
          </div>
        </div>
      </Card>

      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 flex items-center gap-2 font-semibold">
          <CheckCircle2 class="h-5 w-5 text-[var(--severity-green)]" />
          تأكيدات صادرة مؤخراً ({{ issued.length }})
        </h3>

        <div class="space-y-3">
          <div
            v-if="issued.length === 0"
            class="text-sm text-muted-foreground"
          >
            لم تُصدَر أي تأكيدات بعد.
          </div>

          <div
            v-for="request in issued"
            :key="request.id"
            class="flex items-center gap-3 rounded-lg border p-3"
          >
            <div class="min-w-0 flex-1">
              <div class="font-mono text-sm font-semibold">
                {{ request.customs_declaration?.declaration_number ?? request.reference_number }}
              </div>
              <div class="truncate text-xs text-muted-foreground">
                {{ request.merchant?.name }}
              </div>
            </div>

            <Badge variant="secondary" class="text-[10px]">
              {{ user ? getBusinessStatus(request.status, user.role).label : request.status }}
            </Badge>

            <Button
              as="a"
              size="sm"
              variant="outline"
              :href="`/customs/${request.id}/print`"
            >
              عرض/طباعة
            </Button>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
