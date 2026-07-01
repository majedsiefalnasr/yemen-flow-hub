<script setup lang="ts">
import { CheckCircle2, FileSignature, PackageCheck, RefreshCw, Truck } from 'lucide-vue-next'
import PageHeader from '@/components/layout/PageHeader.vue'
import type { EngineRequest } from '@/types/models'
import { ROUTE_ROLE_MAP } from '@/constants/workflow'
import { useEngineRequests } from '@/composables/useEngineRequests'
import { Skeleton } from '@/components/ui/skeleton'
import LoadErrorAlert from '@/components/shared/LoadErrorAlert.vue'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from '@/components/ui/empty'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: ROUTE_ROLE_MAP['/customs'],
})

// Two separate composable instances to avoid shared state between ready/issued panels
const readyComposable = useEngineRequests()
const issuedComposable = useEngineRequests()

const ready = ref<EngineRequest[]>([])
const loadingReady = ref(false)
const readyError = ref<string | null>(null)

const issued = ref<EngineRequest[]>([])
const loadingIssued = ref(false)
const issuedError = ref<string | null>(null)
const issuedPage = ref(1)
const issuedHasMore = ref(false)
const ISSUED_PER_PAGE = 20

async function fetchReady() {
  loadingReady.value = true
  readyError.value = null
  try {
    await readyComposable.fetchQueue({ per_page: 50 })
    ready.value = readyComposable.queue.value
  } catch {
    readyError.value = 'تعذّر تحميل الطلبات الجاهزة.'
  } finally {
    loadingReady.value = false
  }
}

async function fetchIssued(page: number) {
  loadingIssued.value = true
  issuedError.value = null
  try {
    await issuedComposable.fetchList({ status: 'CLOSED', per_page: ISSUED_PER_PAGE, page })
    if (page === 1) {
      issued.value = issuedComposable.instances.value
    } else {
      issued.value.push(...issuedComposable.instances.value)
    }
    const meta = issuedComposable.instancesMeta.value
    if (meta) {
      issuedPage.value = meta.current_page
      issuedHasMore.value = meta.current_page < meta.last_page
    }
  } catch {
    issuedError.value = 'تعذّر تحميل التأكيدات الصادرة.'
  } finally {
    loadingIssued.value = false
  }
}

async function loadMoreIssued() {
  await fetchIssued(issuedPage.value + 1)
}

onMounted(() => {
  Promise.all([fetchReady(), fetchIssued(1)])
})
</script>

<template>
  <div>
    <PageHeader
      title="تأكيد المصارفة الخارجية"
      subtitle="إصدار وطباعة تأكيدات المصارفة الخارجية للطلبات المعتمدة من اللجنة التنفيذية"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'تأكيد المصارفة الخارجية' }]"
    />

    <Card
      class="bg-primary/5 mb-6 border-0 p-5 shadow"
      role="note"
      aria-labelledby="fx-confirmation-steps"
    >
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
          <h2 id="fx-confirmation-steps" class="text-foreground text-sm font-semibold">
            مسار إتمام التأكيد
          </h2>
          <p class="text-muted-foreground mt-1 text-sm">
            هذه الصفحة تحتفظ بعنوان URL القديم فقط. جميع الإجراءات هنا تخص تأكيد المصارفة الخارجية.
          </p>
        </div>
        <ol
          class="grid flex-1 gap-3 text-sm sm:grid-cols-3"
          aria-label="خطوات تأكيد المصارفة الخارجية"
        >
          <li class="border-primary/15 bg-background rounded-lg border px-3 py-2">
            <span class="text-primary block text-xs font-semibold">1. تحميل النموذج</span>
            <span class="text-muted-foreground">مراجعة ملف التأكيد المولد من بيانات الطلب.</span>
          </li>
          <li class="border-primary/15 bg-background rounded-lg border px-3 py-2">
            <span class="text-primary block text-xs font-semibold">2. التوقيع والختم</span>
            <span class="text-muted-foreground">توقيع المستند خارج النظام حسب إجراء اللجنة.</span>
          </li>
          <li class="border-primary/15 bg-background rounded-lg border px-3 py-2">
            <span class="text-primary block text-xs font-semibold">3. الإتمام</span>
            <span class="text-muted-foreground">إصدار النسخة النهائية وحفظها كأثر تدقيق دائم.</span>
          </li>
        </ol>
      </div>
    </Card>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Ready for issuance -->
      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 flex items-center gap-2 font-semibold">
          <PackageCheck class="h-5 w-5 text-[var(--severity-green)]" />
          طلبات جاهزة للإصدار
          <span v-if="!loadingReady" class="text-muted-foreground font-normal"
            >({{ ready.length }})</span
          >
        </h3>

        <!-- Loading skeleton -->
        <div v-if="loadingReady" class="space-y-3" aria-busy="true" aria-label="جارٍ التحميل">
          <Skeleton class="h-16 w-full rounded-lg" />
          <Skeleton class="h-16 w-full rounded-lg" />
          <Skeleton class="h-16 w-3/4 rounded-lg" />
        </div>

        <!-- Error state -->
        <LoadErrorAlert
          v-else-if="readyError"
          :message="readyError"
          title="تعذّر تحميل طلبات الإصدار"
          @retry="fetchReady"
        />

        <!-- Empty -->
        <Empty v-else-if="ready.length === 0" class="py-6">
          <EmptyHeader>
            <PackageCheck class="text-muted-foreground/50 h-8 w-8" />
          </EmptyHeader>
          <EmptyContent>
            <EmptyTitle>لا توجد طلبات جاهزة</EmptyTitle>
            <EmptyDescription
              >لا توجد طلبات معتمدة بانتظار إصدار تأكيد المصارفة حالياً.</EmptyDescription
            >
          </EmptyContent>
        </Empty>

        <!-- List -->
        <div v-else class="space-y-3">
          <div
            v-for="instance in ready"
            :key="instance.id"
            class="flex items-center gap-3 rounded-lg border p-3 transition-colors hover:border-[var(--severity-green)]/20"
          >
            <div
              class="grid h-11 w-11 place-items-center rounded-lg bg-[var(--severity-green)]/10 text-[var(--severity-green)]"
            >
              <Truck class="h-5 w-5" />
            </div>

            <div class="min-w-0 flex-1">
              <div class="font-mono text-sm font-semibold">
                {{ instance.reference }}
              </div>
              <div class="text-muted-foreground truncate text-xs">
                {{ instance.merchant?.name ?? '—' }}
              </div>
            </div>

            <Button as="a" size="sm" :href="`/workflows/instances/${instance.id}`">
              <FileSignature class="ms-1 h-3.5 w-3.5" />
              إصدار تأكيد مصارفة خارجية
            </Button>
          </div>
        </div>
      </Card>

      <!-- Recently issued -->
      <Card class="border-0 p-5 shadow">
        <h3 class="mb-4 flex items-center gap-2 font-semibold">
          <CheckCircle2 class="h-5 w-5 text-[var(--severity-green)]" />
          تأكيدات صادرة مؤخراً
          <span v-if="!loadingIssued || issued.length > 0" class="text-muted-foreground font-normal"
            >({{ issued.length }}{{ issuedHasMore ? '+' : '' }})</span
          >
        </h3>

        <!-- Loading skeleton (first load only) -->
        <div
          v-if="loadingIssued && issued.length === 0"
          class="space-y-3"
          aria-busy="true"
          aria-label="جارٍ التحميل"
        >
          <Skeleton class="h-14 w-full rounded-lg" />
          <Skeleton class="h-14 w-full rounded-lg" />
          <Skeleton class="h-14 w-2/3 rounded-lg" />
        </div>

        <!-- Error state (first load) -->
        <LoadErrorAlert
          v-else-if="issuedError && issued.length === 0"
          :message="issuedError"
          title="تعذّر تحميل التأكيدات الصادرة"
          @retry="fetchIssued(1)"
        />

        <!-- Empty -->
        <Empty v-else-if="!loadingIssued && issued.length === 0" class="py-6">
          <EmptyHeader>
            <CheckCircle2 class="text-muted-foreground/50 h-8 w-8" />
          </EmptyHeader>
          <EmptyContent>
            <EmptyTitle>لا توجد تأكيدات بعد</EmptyTitle>
            <EmptyDescription>لم تُصدَر أي تأكيدات مصارفة خارجية حتى الآن.</EmptyDescription>
          </EmptyContent>
        </Empty>

        <!-- List -->
        <div v-else class="space-y-3">
          <div
            v-for="instance in issued"
            :key="instance.id"
            class="flex items-center gap-3 rounded-lg border p-3"
          >
            <div class="min-w-0 flex-1">
              <div class="font-mono text-sm font-semibold">
                {{ instance.reference }}
              </div>
              <div class="text-muted-foreground truncate text-xs">
                {{ instance.merchant?.name ?? '—' }}
              </div>
            </div>

            <Badge variant="secondary" class="text-xs leading-none">
              {{ instance.current_stage?.name ?? 'مكتمل' }}
            </Badge>

            <Button as="a" size="sm" variant="outline" :href="`/workflows/instances/${instance.id}`">
              عرض/طباعة
            </Button>
          </div>

          <!-- Load-more error (for subsequent pages) -->
          <LoadErrorAlert
            v-if="issuedError && issued.length > 0"
            :message="issuedError"
            title="تعذّر تحميل المزيد"
            @retry="loadMoreIssued"
          />

          <!-- Load more -->
          <div v-if="issuedHasMore && !issuedError" class="pt-1">
            <Button
              variant="ghost"
              size="sm"
              class="text-muted-foreground w-full"
              :disabled="loadingIssued"
              @click="loadMoreIssued"
            >
              <RefreshCw v-if="loadingIssued" class="h-3.5 w-3.5 animate-spin" />
              {{ loadingIssued ? 'جارٍ تحميل المزيد...' : 'تحميل المزيد من التأكيدات' }}
            </Button>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
