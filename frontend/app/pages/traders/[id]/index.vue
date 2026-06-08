<script setup lang="ts">
import { AlertCircle, Edit, ShieldCheck } from 'lucide-vue-next'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { useAuthStore } from '@/stores/auth.store'
import { useTradersStore } from '@/stores/traders'
import { canManageTraders, isMajorOwner, TRADER_MANAGEMENT_ROLES } from '@/types/trader'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [...TRADER_MANAGEMENT_ROLES],
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const tradersStore = useTradersStore()

const traderId = computed(() => Number(route.params.id))
const trader = computed(() => tradersStore.currentTrader)
const canWrite = computed(() => canManageTraders(authStore.user?.role))

async function loadTrader(): Promise<void> {
  if (!Number.isFinite(traderId.value)) return
  await tradersStore.loadTrader(traderId.value)
}

onMounted(() => {
  void loadTrader()
})
</script>

<template>
  <div class="mx-auto flex max-w-[1600px] flex-col gap-6 p-6" dir="rtl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="font-heading text-foreground text-2xl font-semibold">تفاصيل التاجر</h1>
        <p class="text-muted-foreground mt-1 text-sm">عرض بيانات التاجر والشركات والملاك.</p>
      </div>
      <Button
        v-if="canWrite && trader"
        variant="outline"
        @click="router.push(`/traders/${trader.id}/edit`)"
      >
        <Edit class="me-2 h-4 w-4" aria-hidden="true" />
        تعديل
      </Button>
    </div>

    <div v-if="tradersStore.loading" class="grid gap-4 md:grid-cols-2">
      <Skeleton v-for="item in 4" :key="item" class="h-28 w-full rounded-xl" />
    </div>

    <Alert v-else-if="tradersStore.error" variant="destructive" role="alert">
      <AlertCircle class="h-4 w-4" aria-hidden="true" />
      <AlertTitle>تعذّر تحميل بيانات التاجر</AlertTitle>
      <AlertDescription>{{ tradersStore.error }}</AlertDescription>
      <AlertAction>
        <Button variant="outline" size="sm" @click="loadTrader()">إعادة المحاولة</Button>
      </AlertAction>
    </Alert>

    <Empty v-else-if="!trader">
      <EmptyMedia variant="icon">
        <AlertCircle />
      </EmptyMedia>
      <EmptyHeader>
        <EmptyTitle>التاجر غير موجود</EmptyTitle>
        <EmptyDescription>تحقق من الرابط أو عد إلى قائمة التجار.</EmptyDescription>
      </EmptyHeader>
    </Empty>

    <template v-else>
      <Card class="border shadow">
        <CardHeader>
          <CardTitle>{{ trader.trader_name }}</CardTitle>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-2">
          <div>
            <p class="text-muted-foreground text-sm">الرقم الضريبي</p>
            <p class="text-foreground font-mono">{{ trader.tax_number }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-sm">رقم السجل التجاري</p>
            <p class="text-foreground">{{ trader.commercial_registration_number }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-sm">انتهاء البطاقة الضريبية</p>
            <p class="text-foreground">{{ trader.tax_card_expiry || 'غير محدد' }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-sm">انتهاء السجل التجاري</p>
            <p class="text-foreground">{{ trader.commercial_registration_expiry || 'غير محدد' }}</p>
          </div>
        </CardContent>
      </Card>

      <div class="grid gap-6 lg:grid-cols-2">
        <Card class="border shadow">
          <CardHeader>
            <CardTitle>الشركات المرتبطة</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <p v-if="trader.companies.length === 0" class="text-muted-foreground text-sm">
              لا توجد شركات مرتبطة.
            </p>
            <div
              v-for="company in trader.companies"
              :key="company.id ?? company.company_name"
              class="border-border rounded-lg border p-3"
            >
              {{ company.company_name }}
            </div>
          </CardContent>
        </Card>

        <Card class="border shadow">
          <CardHeader>
            <CardTitle>الملاك</CardTitle>
          </CardHeader>
          <CardContent class="space-y-2">
            <p v-if="trader.owners.length === 0" class="text-muted-foreground text-sm">
              لا توجد بيانات ملاك.
            </p>
            <div
              v-for="owner in trader.owners"
              :key="owner.id ?? owner.full_name"
              class="border-border rounded-lg border p-3"
            >
              <div class="flex items-center justify-between gap-3">
                <span class="text-foreground font-medium">{{ owner.full_name }}</span>
                <Badge
                  v-if="isMajorOwner(owner)"
                  class="border border-[var(--severity-amber)]/30 bg-[var(--severity-amber)]/10 text-[var(--severity-amber)]"
                >
                  <ShieldCheck class="me-1 h-3.5 w-3.5" aria-hidden="true" />
                  {{ owner.ownership_percentage }}%
                </Badge>
                <Badge v-else variant="secondary">{{ owner.ownership_percentage }}%</Badge>
              </div>
              <p class="text-muted-foreground mt-1 text-sm">
                {{ owner.nationality || 'الجنسية غير محددة' }} ·
                {{ owner.identification_number || 'رقم الهوية غير محدد' }}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </template>
  </div>
</template>
