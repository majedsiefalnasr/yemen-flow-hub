<script setup lang="ts">
import { FileSignature, PackageCheck, Truck } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import { RequestStatus } from '@/types/enums'
import type { ImportRequest } from '@/types/models'
import { getBusinessStatus } from '@/constants/workflow'
import { useAuthStore } from '@/stores/auth.store'
import { useRequests } from '@/composables/useRequests'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { fetchRequests } = useRequests()

const allRequests = ref<ImportRequest[]>([])

onMounted(async () => {
  const result = await fetchRequests({ per_page: 200 })
  allRequests.value = result.data
})

const ready = computed(() =>
  allRequests.value.filter(r => r.status === RequestStatus.EXECUTIVE_APPROVED),
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
      title="إصدار إذن بيان جمركي"
      subtitle="إصدار وطباعة البيانات الجمركية للطلبات المعتمدة من اللجنة التنفيذية"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'إذن إصدار بيان جمركي' }]"
    />

    <div class="grid gap-6 lg:grid-cols-2">
      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 flex items-center gap-2 font-semibold">
          <PackageCheck class="h-5 w-5 text-green-700" />
          طلبات جاهزة للإصدار ({{ ready.length }})
        </h3>

        <div class="space-y-3">
          <div
            v-if="ready.length === 0"
            class="text-sm text-gray-600"
          >
            لا توجد طلبات جاهزة حالياً.
          </div>

          <div
            v-for="request in ready"
            :key="request.id"
            class="flex items-center gap-3 rounded-lg border p-3 transition-colors hover:border-green-200/40"
          >
            <div class="grid h-11 w-11 place-items-center rounded-lg bg-green-50/10 text-green-700">
              <Truck class="h-5 w-5" />
            </div>

            <div class="min-w-0 flex-1">
              <div class="font-mono text-sm font-semibold">
                {{ request.reference_number }}
              </div>
              <div class="truncate text-xs text-gray-600">
                {{ request.merchant?.name }} · {{ request.port_of_entry }}
              </div>
            </div>

            <Button
              as="a"
              size="sm"
              :href="`/customs/${request.id}/print`"
            >
              <FileSignature class="ms-1 h-3.5 w-3.5" />
              إصدار إذن بيان جمركي
            </Button>
          </div>
        </div>
      </Card>

      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 font-semibold">
          بيانات صادرة مؤخراً ({{ issued.length }})
        </h3>

        <div class="space-y-3">
          <div
            v-if="issued.length === 0"
            class="text-sm text-gray-600"
          >
            لم تُصدَر أي بيانات بعد.
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
              <div class="truncate text-xs text-gray-600">
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
