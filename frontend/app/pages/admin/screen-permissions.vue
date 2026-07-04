<script setup lang="ts">
import type { ScreenCapability } from '@/types/models'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Empty, EmptyTitle } from '@/components/ui/empty'
import PageHeader from '@/components/layout/PageHeader.vue'
import { AlertCircle, Check, Info, SlidersHorizontal, Workflow, X } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import type { MatrixRoleRow, MatrixScreen } from '@/composables/useScreenPermissionsAdmin'

definePageMeta({
  middleware: ['auth', 'screen'],
  requiredScreen: 'screen_permissions',
  requiredCapability: 'VIEW',
})

const REQUESTS_KEY = 'requests'
const REQUEST_CAPS: { cap: 'view' | 'add' | 'edit'; label: string }[] = [
  { cap: 'view', label: 'عرض' },
  { cap: 'add', label: 'إنشاء' },
  { cap: 'edit', label: 'تنفيذ' },
]

const CAP_LABELS: Record<string, string> = {
  VIEW: 'عرض',
  MANAGE: 'إدارة',
  EXPORT: 'تصدير',
}

const { matrix, loading, saving, error, fetchMatrix, saveRoleGrants } = useScreenPermissionsAdmin()

const { can } = useScreenPermissions()
const canEdit = computed(() => can('screen_permissions', 'MANAGE'))

const manualScreens = computed(
  () => matrix.value?.screens.filter((s) => s.key !== REQUESTS_KEY) ?? [],
)

// Flat list of all manual column headers: [{ screen, cap, label }]
const manualColumns = computed(() => {
  const cols: { screen: MatrixScreen; cap: ScreenCapability; label: string }[] = []
  for (const screen of manualScreens.value) {
    for (const cap of screen.capabilities) {
      cols.push({ screen, cap, label: CAP_LABELS[cap] ?? cap })
    }
  }
  return cols
})

// Per-role pending grants cache for optimistic UI during auto-save.
const pending = ref<Map<number, Record<string, ScreenCapability[]>>>(new Map())

function roleCaps(role: MatrixRoleRow, screenKey: string): ScreenCapability[] {
  const local = pending.value.get(role.id)
  if (local) return local[screenKey] ?? []
  return role.manual[screenKey] ?? []
}

function manualCan(role: MatrixRoleRow, screenKey: string, cap: ScreenCapability): boolean {
  return roleCaps(role, screenKey).includes(cap)
}

function isForced(
  role: MatrixRoleRow,
  screenKey: string,
  cap: ScreenCapability,
  screen: MatrixScreen,
): boolean {
  if (cap !== 'VIEW') return false
  if (!screen.capabilities.includes('MANAGE' as ScreenCapability)) return false
  return manualCan(role, screenKey, 'MANAGE' as ScreenCapability)
}

async function toggleManual(
  role: MatrixRoleRow,
  screenKey: string,
  cap: ScreenCapability,
  enabled: boolean,
  screen: MatrixScreen,
) {
  let currentCaps = [...(role.manual[screenKey] ?? [])]

  if (enabled) {
    if (!currentCaps.includes(cap)) currentCaps.push(cap)
    // MANAGE implies VIEW
    if (cap === 'MANAGE' && !currentCaps.includes('VIEW' as ScreenCapability)) {
      currentCaps.push('VIEW' as ScreenCapability)
    }
  } else {
    currentCaps = currentCaps.filter((c) => c !== cap)
    // Removing MANAGE does not remove VIEW (user can still have view-only)
  }

  // Filter to only capabilities this screen supports
  const validCaps = screen.capabilities
  currentCaps = currentCaps.filter((c) => validCaps.includes(c))

  // Build full grants object: preserve other screens, update this one
  const base = Object.fromEntries(Object.entries(role.manual).filter(([k]) => k !== screenKey))
  if (currentCaps.length > 0) {
    base[screenKey] = currentCaps
  }

  // Optimistic local override
  const optimistic = new Map(pending.value)
  optimistic.set(role.id, base)
  pending.value = optimistic

  const ok = await saveRoleGrants(role.id, base)
  const cleared = new Map(pending.value)
  cleared.delete(role.id)
  pending.value = cleared

  if (ok) {
    role.manual = base
    toast.success(`تم تحديث صلاحية ${CAP_LABELS[cap] ?? cap} لـ ${role.name} على ${screenKey}`)
  } else {
    toast.error(error.value ?? 'فشل في حفظ الصلاحية')
  }
}

// Count colspan for each screen header
function screenColspan(screen: MatrixScreen): number {
  return screen.capabilities.length
}

onMounted(fetchMatrix)
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-6 py-2">
    <PageHeader
      title="صلاحيات ظهور الشاشات"
      subtitle="مصفوفة واحدة: كل دور في صف، وكل شاشة في مجموعة أعمدة. صلاحيات الطلبات مشتقة من مصمم سير العمل."
      :breadcrumbs="[{ label: 'الرئيسية', to: '/dashboard' }, { label: 'صلاحيات الشاشات' }]"
    >
      <template #actions>
        <Button variant="outline" @click="$router.push('/admin/workflows')">
          <Workflow class="me-2 h-4 w-4" />
          مصمم سير العمل
        </Button>
      </template>
    </PageHeader>

    <Card class="border-0 shadow">
      <CardContent class="flex items-start gap-3">
        <Info class="text-primary mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
        <div class="text-muted-foreground text-sm leading-relaxed">
          مسؤول النظام يملك كل الصلاحيات تلقائيًا (غير معروض). صلاحيات شاشة
          <strong class="text-foreground">الطلبات</strong> مشتقة إلزاميًا من إسنادات المراحل في مصمم
          سير العمل وتظهر للعرض فقط. الشاشات العامة (الإعدادات، الإشعارات) وشاشات الإدارة الحصرية لا
          تظهر في هذه المصفوفة لأنها غير قابلة للتخصيص.
        </div>
      </CardContent>
    </Card>

    <div class="text-muted-foreground mb-3 flex flex-wrap items-center gap-4 text-xs">
      <span class="flex items-center gap-1.5">
        <Check class="h-3.5 w-3.5 text-[var(--success)]" />
        مفعّلة (مشتقة)
      </span>
      <span class="flex items-center gap-1.5">
        <X class="text-muted-foreground/40 h-3.5 w-3.5" />
        غير مفعّلة
      </span>
      <span class="flex items-center gap-1.5">
        <SlidersHorizontal class="h-3.5 w-3.5" />
        مفتاح قابل للتبديل (يدوي)
      </span>
    </div>

    <Alert v-if="error" variant="destructive">
      <AlertCircle class="h-4 w-4" />
      <AlertTitle>خطأ</AlertTitle>
      <AlertDescription>{{ error }}</AlertDescription>
    </Alert>

    <Card class="overflow-hidden border-0 shadow py-0">
      <CardContent class="p-0">
        <div v-if="loading" class="space-y-2 p-4">
          <Skeleton v-for="n in 6" :key="n" class="h-10 w-full rounded-md" />
        </div>

        <div v-else-if="!matrix || matrix.roles.length === 0" class="p-8">
          <Empty>
            <EmptyTitle>لا توجد أدوار نشطة لعرضها</EmptyTitle>
          </Empty>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-muted/40 text-xs">
                <th
                  scope="col"
                  rowspan="2"
                  class="bg-muted/40 sticky inset-s-0 z-10 min-w-[180px] px-5 py-3 text-start align-bottom font-semibold"
                >
                  الدور
                </th>
                <th
                  scope="colgroup"
                  :colspan="REQUEST_CAPS.length"
                  class="border-border border-s px-4 py-2.5 text-center font-semibold"
                >
                  <div class="flex flex-col items-center gap-1">
                    <span class="text-foreground">الطلبات</span>
                    <Badge variant="secondary" class="gap-1 text-[9px] font-normal">
                      <Workflow class="h-2.5 w-2.5" />
                      مشتقة من المصمم
                    </Badge>
                  </div>
                </th>
                <th
                  v-for="screen in manualScreens"
                  :key="screen.key"
                  scope="colgroup"
                  :colspan="screenColspan(screen)"
                  class="border-border border-s px-4 py-2.5 text-center font-semibold"
                >
                  <div class="flex flex-col items-center gap-1">
                    <span class="text-foreground">{{ screen.label }}</span>
                    <span class="text-muted-foreground font-mono text-[9px]">{{ screen.key }}</span>
                  </div>
                </th>
              </tr>
              <tr class="bg-muted/20 text-muted-foreground text-xs">
                <th
                  v-for="c in REQUEST_CAPS"
                  :key="`requests-${c.cap}`"
                  scope="col"
                  class="border-border w-24 border-s px-4 py-2 text-center font-medium"
                >
                  {{ c.label }}
                </th>
                <th
                  v-for="col in manualColumns"
                  :key="`head-${col.screen.key}-${col.cap}`"
                  scope="col"
                  class="border-border w-24 border-s px-4 py-2 text-center font-medium"
                >
                  {{ col.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="role in matrix.roles"
                :key="role.id"
                class="hover:bg-muted/20 border-t transition-colors"
              >
                <th
                  scope="row"
                  class="bg-card sticky inset-s-0 z-10 px-5 py-3 text-start font-medium"
                >
                  <div class="text-foreground">{{ role.name }}</div>
                  <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    <Badge variant="secondary" class="text-xs font-normal">
                      {{ role.organization_name ?? `#${role.organization_id}` }}
                    </Badge>
                    <Badge v-if="role.is_system" variant="outline" class="text-[10px] font-normal">
                      نظامي
                    </Badge>
                  </div>
                </th>
                <td
                  v-for="c in REQUEST_CAPS"
                  :key="`cell-requests-${role.id}-${c.cap}`"
                  class="border-border border-s px-4 py-3 text-center"
                >
                  <span class="sr-only">
                    الطلبات، {{ c.label }}، {{ role.name }}:
                    {{ role.requests[c.cap] ? 'ممنوحة' : 'غير ممنوحة' }}
                  </span>
                  <Check
                    v-if="role.requests[c.cap]"
                    class="mx-auto h-4 w-4 text-[var(--success)]"
                    aria-hidden="true"
                  />
                  <X v-else class="text-muted-foreground/40 mx-auto h-4 w-4" aria-hidden="true" />
                </td>
                <td
                  v-for="col in manualColumns"
                  :key="`cell-${role.id}-${col.screen.key}-${col.cap}`"
                  class="border-border border-s px-4 py-3 text-center"
                >
                  <div class="flex justify-center">
                    <Switch
                      :checked="
                        manualCan(role, col.screen.key, col.cap) ||
                        isForced(role, col.screen.key, col.cap, col.screen)
                      "
                      :disabled="
                        !canEdit || saving || isForced(role, col.screen.key, col.cap, col.screen)
                      "
                      :aria-label="`${col.screen.label}، ${col.label}، ${role.name}`"
                      @update:checked="
                        (v: boolean) => toggleManual(role, col.screen.key, col.cap, v, col.screen)
                      "
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
