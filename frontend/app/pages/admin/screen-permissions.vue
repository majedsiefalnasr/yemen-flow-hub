<script setup lang="ts">
import type { ScreenCapability } from '@/types/models'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  Table,
  TableBody,
  TableCell,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Spinner } from '@/components/ui/spinner'
import { AlertCircle, Save, Shield } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

definePageMeta({
  middleware: ['auth', 'screen'],
  requiredScreen: 'screen_permissions',
  requiredCapability: 'VIEW',
})

const ALL_CAPABILITIES: ScreenCapability[] = [
  'VIEW',
  'CREATE',
  'UPDATE',
  'DELETE',
  'EXPORT',
  'MANAGE',
]

const { screens, loading, saving, error, fetchScreens, fetchRoleGrants, saveRoleGrants } =
  useScreenPermissionsAdmin()
const { roles, fetchRoles } = useGovernanceRoles()

const selectedRoleId = ref<string>('')
const grants = ref<Record<string, ScreenCapability[]>>({})
const dirty = ref(false)

const { can } = useScreenPermissions()
const canEdit = computed(() => can('screen_permissions', 'MANAGE'))

async function loadRoleGrants() {
  if (!selectedRoleId.value) return
  const data = await fetchRoleGrants(Number(selectedRoleId.value))
  if (data) {
    grants.value = { ...data.grants }
  } else {
    grants.value = {}
  }
  dirty.value = false
}

function toggleCapability(screenKey: string, cap: ScreenCapability) {
  if (!canEdit.value) return
  const current = grants.value[screenKey] ?? []
  if (current.includes(cap)) {
    const filtered = current.filter((c) => c !== cap)
    if (filtered.length === 0) {
      const { [screenKey]: _, ...rest } = grants.value
      grants.value = rest
    } else {
      grants.value[screenKey] = filtered
    }
  } else {
    grants.value[screenKey] = [...current, cap]
  }
  dirty.value = true
}

function hasCap(screenKey: string, cap: ScreenCapability): boolean {
  return grants.value[screenKey]?.includes(cap) ?? false
}

async function save() {
  if (!selectedRoleId.value) return
  const ok = await saveRoleGrants(Number(selectedRoleId.value), grants.value)
  if (ok) {
    toast.success('تم حفظ الصلاحيات بنجاح')
    dirty.value = false
  } else {
    toast.error(error.value ?? 'فشل في حفظ الصلاحيات')
  }
}

onMounted(async () => {
  await Promise.all([fetchScreens(), fetchRoles()])
})

watch(selectedRoleId, () => {
  if (selectedRoleId.value) {
    loadRoleGrants()
  }
})
</script>

<template>
  <div class="mx-auto max-w-[1600px] space-y-6 p-6">
    <div class="flex items-center gap-3">
      <Shield class="text-primary h-6 w-6" aria-hidden="true" />
      <h1 class="text-xl font-semibold">صلاحيات الشاشات</h1>
    </div>

    <Card class="border-0 shadow">
      <CardHeader class="pb-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <CardTitle class="text-sm font-semibold">مصفوفة الصلاحيات</CardTitle>
          <div class="flex items-center gap-3">
            <Select v-model="selectedRoleId">
              <SelectTrigger class="w-64">
                <SelectValue placeholder="اختر الدور" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="r in roles" :key="r.id" :value="String(r.id)">
                  {{ r.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <Button v-if="canEdit" :disabled="!dirty || saving" size="sm" @click="save">
              <Spinner v-if="saving" class="me-2 h-4 w-4" />
              <Save v-else class="me-2 h-4 w-4" />
              حفظ
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent class="p-0">
        <Alert v-if="error" variant="destructive" class="mx-4 mb-4">
          <AlertCircle class="h-4 w-4" />
          <AlertTitle>خطأ</AlertTitle>
          <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <div v-if="loading" class="space-y-2 p-4">
          <Skeleton v-for="n in 6" :key="n" class="h-10 w-full rounded-md" />
        </div>

        <div v-else-if="!selectedRoleId" class="text-muted-foreground p-8 text-center text-sm">
          اختر دوراً لعرض صلاحياته
        </div>

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="w-48 text-right">الشاشة</TableHead>
              <TableHead v-for="cap in ALL_CAPABILITIES" :key="cap" class="text-center">
                {{ cap }}
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="screen in screens" :key="screen.key">
              <TableCell class="font-medium">{{ screen.label }}</TableCell>
              <TableCell v-for="cap in ALL_CAPABILITIES" :key="cap" class="text-center">
                <Checkbox
                  :checked="hasCap(screen.key, cap)"
                  :disabled="!canEdit"
                  @update:checked="toggleCapability(screen.key, cap)"
                />
              </TableCell>
            </TableRow>
            <TableEmpty v-if="screens.length === 0" :columns="7"> لا توجد شاشات مسجلة </TableEmpty>
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  </div>
</template>
