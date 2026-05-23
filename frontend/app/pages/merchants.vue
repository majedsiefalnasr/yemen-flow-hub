<script setup lang="ts">
import PageHeader from '@/components/layout/PageHeader.vue'
import MerchantDialog from '@/components/merchants/MerchantDialog.vue'
import type { MerchantFormData } from '@/components/merchants/MerchantDialog.vue'
import { Building2, Edit, Eye, Plus, Search, Trash2 } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth.store'
import { useMerchants } from '@/composables/useMerchants'
import { useBanks } from '@/composables/useBanks'
import { UserRole } from '@/types/enums'
import type { Merchant } from '@/types/models'

const authStore = useAuthStore()
const user = computed(() => authStore.user)
const { fetchMerchants, createMerchant, updateMerchant, suspendMerchant } = useMerchants()
const { fetchBanks } = useBanks()
const { toast } = useToast()

const merchants = ref<Merchant[]>([])
const banks = ref<import('@/types/models').Bank[]>([])
const query = ref('')
const statusFilter = ref<'all' | 'active' | 'suspended'>('all')
const bankFilter = ref<number | 'all'>('all')
const createOpen = ref(false)
const editing = ref<Merchant | null>(null)
const viewing = ref<Merchant | null>(null)

onMounted(async () => {
  const [merchantsResult, banksResult] = await Promise.allSettled([
    fetchMerchants(),
    fetchBanks(),
  ])
  if (merchantsResult.status === 'fulfilled') merchants.value = merchantsResult.value
  if (banksResult.status === 'fulfilled') banks.value = banksResult.value
})

const isCbyAdmin = computed(() => user.value?.role === UserRole.CBY_ADMIN)
const isBankAdmin = computed(() => user.value?.role === UserRole.BANK_ADMIN)
const canManage = computed(() => isBankAdmin.value || isCbyAdmin.value)

function bankName(id?: number | null) {
  return banks.value.find(b => b.id === id)?.name_ar ?? '—'
}

const scoped = computed(() => {
  if (isBankAdmin.value && user.value?.bank_id) {
    return merchants.value.filter(m => m.bank_id === user.value?.bank_id)
  }
  return merchants.value
})

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  return scoped.value.filter((m) => {
    if (statusFilter.value !== 'all' && (statusFilter.value === 'active') !== m.is_active) return false
    if (isCbyAdmin.value && bankFilter.value !== 'all' && m.bank_id !== bankFilter.value) return false
    if (!q) return true
    return [m.name, m.commercial_register, m.tax_number, bankName(m.bank_id)].some(v => (v ?? '').toLowerCase().includes(q))
  })
})

const stats = computed(() => ({
  total: scoped.value.length,
  active: scoped.value.filter(m => m.is_active).length,
  suspended: scoped.value.filter(m => !m.is_active).length,
}))

function merchantToForm(m: Merchant): MerchantFormData {
  return {
    name: m.name,
    commercial_register: m.commercial_register ?? '',
    tax_number: m.tax_number ?? '',
    address: m.address ?? '',
    phone: m.phone ?? '',
    business_type: m.business_type ?? '',
    is_active: m.is_active,
    bank_id: m.bank_id,
  }
}

async function saveNew(data: MerchantFormData) {
  const created = await createMerchant({ ...data, bank_id: data.bank_id ?? undefined })
  merchants.value = [created, ...merchants.value]
  createOpen.value = false
  toast({ title: `تم تسجيل التاجر "${created.name}"` })
}

async function saveEdit(data: MerchantFormData) {
  if (!editing.value) return
  const updated = await updateMerchant(editing.value.id, data)
  merchants.value = merchants.value.map(m => m.id === updated.id ? updated : m)
  editing.value = null
  toast({ title: 'تم تحديث بيانات التاجر' })
}

async function toggleStatus(merchant: Merchant) {
  const updated = await suspendMerchant(merchant.id, !merchant.is_active)
  merchants.value = merchants.value.map(m => m.id === updated.id ? updated : m)
}
</script>

<template>
  <div v-if="user && canManage">
    <PageHeader
      title="إدارة التجار"
      :subtitle="isCbyAdmin ? 'عرض جميع التجار المسجّلين على المنصّة مع البنوك التابعة لها' : 'تسجيل ومتابعة التجار والمستوردين المرتبطين بالبنك'"
      :breadcrumbs="[{ label: 'الرئيسية', to: '/' }, { label: 'التجار' }]"
    >
      <template #actions>
        <Button @click="createOpen = true">
          <Plus class="ms-1 h-4 w-4" />
          تاجر جديد
        </Button>
      </template>
    </PageHeader>

    <div class="mb-4 grid grid-cols-3 gap-3">
      <Card class="border-0 p-4 shadow-card">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-primary/10 text-primary">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">
          {{ stats.total }}
        </div>
        <div class="text-xs text-muted-foreground">
          إجمالي
        </div>
      </Card>
      <Card class="border-0 p-4 shadow-card">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-success/10 text-success">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">
          {{ stats.active }}
        </div>
        <div class="text-xs text-muted-foreground">
          نشط
        </div>
      </Card>
      <Card class="border-0 p-4 shadow-card">
        <div class="grid h-9 w-9 place-items-center rounded-lg bg-destructive/10 text-destructive">
          <Building2 class="h-4 w-4" />
        </div>
        <div class="mt-2 text-2xl font-bold tabular-nums">
          {{ stats.suspended }}
        </div>
        <div class="text-xs text-muted-foreground">
          موقوف
        </div>
      </Card>
    </div>

    <Card class="mb-4 flex flex-col items-stretch gap-3 border-0 p-4 shadow-card sm:flex-row sm:items-center">
      <div class="relative flex-1">
        <Search class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          placeholder="بحث برقم السجل، الرقم الضريبي، أو الاسم..."
          class="pe-10"
        />
      </div>

      <Select
        v-if="isCbyAdmin"
        :model-value="bankFilter === 'all' ? 'all' : bankFilter.toString()"
        @update:model-value="v => bankFilter = v === 'all' ? 'all' : Number(v)"
      >
        <SelectTrigger class="w-full sm:w-56">
          <SelectValue placeholder="البنك" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">
            كل البنوك
          </SelectItem>
          <SelectItem
            v-for="bank in banks"
            :key="bank.id"
            :value="bank.id.toString()"
          >
            {{ bank.name_ar }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="statusFilter">
        <SelectTrigger class="w-full sm:w-44">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">
            كل الحالات
          </SelectItem>
          <SelectItem value="active">
            نشط فقط
          </SelectItem>
          <SelectItem value="suspended">
            موقوف فقط
          </SelectItem>
        </SelectContent>
      </Select>
    </Card>

    <Card
      v-if="isCbyAdmin"
      class="overflow-hidden border-0 shadow-card"
    >
      <div class="overflow-x-auto">
        <Table class="w-full text-sm">
          <TableHeader class="bg-muted/40 text-end text-xs text-muted-foreground">
            <TableRow>
              <TableHead class="p-3 font-semibold">
                التاجر
              </TableHead>
              <TableHead class="p-3 font-semibold">
                السجل التجاري
              </TableHead>
              <TableHead class="p-3 font-semibold">
                الرقم الضريبي
              </TableHead>
              <TableHead class="p-3 font-semibold">
                القطاع
              </TableHead>
              <TableHead class="p-3 font-semibold">
                البنك التابع له
              </TableHead>
              <TableHead class="p-3 font-semibold">
                الحالة
              </TableHead>
              <TableHead class="p-3 font-semibold">
                المعاملات
              </TableHead>
              <TableHead class="w-12 p-3 font-semibold" />
            </TableRow>
          </TableHeader>
          <TableBody class="divide-y">
            <TableRow
              v-for="merchant in filtered"
              :key="merchant.id"
              class="hover:bg-muted/30"
            >
              <TableCell class="p-3 font-medium">
                {{ merchant.name }}
              </TableCell>
              <TableCell class="p-3 text-muted-foreground">
                {{ merchant.commercial_register ?? '—' }}
              </TableCell>
              <TableCell class="p-3 tabular-nums text-muted-foreground">
                {{ merchant.tax_number ?? '—' }}
              </TableCell>
              <TableCell class="p-3 text-muted-foreground">
                {{ merchant.business_type ?? '—' }}
              </TableCell>
              <TableCell class="p-3">
                <Badge
                  variant="outline"
                  class="font-normal"
                >
                  <Building2 class="ms-1 h-3 w-3" />
                  {{ bankName(merchant.bank_id) }}
                </Badge>
              </TableCell>
              <TableCell class="p-3">
                <Badge :class="merchant.is_active ? 'border-0 bg-success/15 text-success' : 'border-0 bg-destructive/15 text-destructive'">
                  {{ merchant.is_active ? 'نشط' : 'موقوف' }}
                </Badge>
              </TableCell>
              <TableCell class="p-3 font-semibold tabular-nums">
                {{ merchant.transaction_count ?? 0 }}
              </TableCell>
              <TableCell class="p-3">
                <Button
                  size="icon"
                  variant="ghost"
                  class="h-8 w-8"
                  @click="viewing = merchant"
                >
                  <Eye class="h-4 w-4" />
                </Button>
              </TableCell>
            </TableRow>
            <TableRow v-if="filtered.length === 0">
              <TableCell
                colspan="8"
                class="p-8 text-center text-muted-foreground"
              >
                لا توجد نتائج مطابقة.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </Card>

    <div
      v-else
      class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
    >
      <Card
        v-for="merchant in filtered"
        :key="merchant.id"
        class="flex flex-col border-0 p-5 shadow-card transition-shadow hover:shadow-soft"
      >
        <div class="mb-3 flex items-start justify-between">
          <div class="grid h-12 w-12 place-items-center rounded-xl bg-primary text-primary-foreground">
            <Building2 class="h-6 w-6" />
          </div>
          <Badge :class="merchant.is_active ? 'border-0 bg-success/15 text-success' : 'border-0 bg-destructive/15 text-destructive'">
            {{ merchant.is_active ? 'نشط' : 'موقوف' }}
          </Badge>
        </div>
        <div class="text-base font-semibold">
          {{ merchant.name }}
        </div>
        <div class="text-xs text-muted-foreground">
          {{ merchant.business_type ?? '—' }}
        </div>
        <div class="mt-4 space-y-1.5 text-xs">
          <div class="flex justify-between gap-2">
            <span class="text-muted-foreground">السجل التجاري</span>
            <span class="font-medium">{{ merchant.commercial_register ?? '—' }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-muted-foreground">الرقم الضريبي</span>
            <span class="font-medium">{{ merchant.tax_number ?? '—' }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-muted-foreground">البنك</span>
            <span class="font-medium">{{ bankName(merchant.bank_id) }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-muted-foreground">العنوان</span>
            <span class="text-end font-medium">{{ merchant.address ?? '—' }}</span>
          </div>
          <div class="flex justify-between gap-2">
            <span class="text-muted-foreground">هاتف</span>
            <span class="font-medium">{{ merchant.phone ?? '—' }}</span>
          </div>
        </div>
        <div class="mt-auto flex items-center justify-between border-t pt-4">
          <div class="text-xs">
            <span class="text-muted-foreground">المعاملات: </span>
            <span class="font-bold tabular-nums">{{ merchant.transaction_count ?? 0 }}</span>
          </div>
          <div class="flex gap-1">
            <Button
              size="sm"
              variant="ghost"
              class="h-8"
              @click="toggleStatus(merchant)"
            >
              {{ merchant.is_active ? 'إيقاف' : 'تفعيل' }}
            </Button>
            <Button
              size="icon"
              variant="ghost"
              class="h-8 w-8"
              @click="editing = merchant"
            >
              <Edit class="h-3.5 w-3.5" />
            </Button>
          </div>
        </div>
      </Card>

      <Card
        v-if="filtered.length === 0"
        class="col-span-full border-0 p-8 text-center text-sm text-muted-foreground shadow-card"
      >
        لا توجد نتائج مطابقة.
      </Card>
    </div>

    <Dialog v-model:open="createOpen">
      <MerchantDialog
        title="تسجيل تاجر جديد"
        :banks="banks"
        :default-bank-id="user?.bank_id"
        :lock-bank="Boolean(user?.bank_id && !isCbyAdmin)"
        @save="saveNew"
      />
    </Dialog>

    <Dialog :open="Boolean(editing)" @update:open="v => !v && (editing = null)">
      <MerchantDialog
        v-if="editing"
        title="تعديل بيانات التاجر"
        :banks="banks"
        :initial="merchantToForm(editing)"
        :default-bank-id="user?.bank_id"
        :lock-bank="false"
        @save="saveEdit"
      />
    </Dialog>

    <Dialog :open="Boolean(viewing)" @update:open="v => !v && (viewing = null)">
      <DialogContent
        v-if="viewing"
        class="sm:max-w-lg"
      >
        <DialogHeader>
          <DialogTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            {{ viewing.name }}
          </DialogTitle>
          <DialogDescription>تفاصيل التاجر — عرض فقط</DialogDescription>
        </DialogHeader>
        <div class="grid gap-3 py-2 text-sm sm:grid-cols-2">
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">
              السجل التجاري
            </div>
            <div class="font-medium">
              {{ viewing.commercial_register ?? '—' }}
            </div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">
              الرقم الضريبي
            </div>
            <div class="font-medium">
              {{ viewing.tax_number ?? '—' }}
            </div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">
              القطاع
            </div>
            <div class="font-medium">
              {{ viewing.business_type ?? '—' }}
            </div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">
              الحالة
            </div>
            <div class="font-medium">
              {{ viewing.is_active ? 'نشط' : 'موقوف' }}
            </div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">
              البنك التابع له
            </div>
            <div class="font-medium">
              {{ bankName(viewing.bank_id) }}
            </div>
          </div>
          <div class="space-y-0.5">
            <div class="text-xs text-muted-foreground">
              عدد المعاملات
            </div>
            <div class="font-medium">
              {{ viewing.transaction_count ?? 0 }}
            </div>
          </div>
          <div class="space-y-0.5 sm:col-span-2">
            <div class="text-xs text-muted-foreground">
              العنوان
            </div>
            <div class="font-medium">
              {{ viewing.address ?? '—' }}
            </div>
          </div>
          <div class="space-y-0.5 sm:col-span-2">
            <div class="text-xs text-muted-foreground">
              هاتف التواصل
            </div>
            <div class="font-medium">
              {{ viewing.phone ?? '—' }}
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  </div>

  <div v-else>
    <PageHeader
      title="إدارة التجار"
      subtitle="هذه الصفحة متاحة لمسؤول النظام أو مسؤول البنك فقط."
    />
    <Card class="border-0 p-6 shadow-card">
      <div class="text-sm text-muted-foreground">
        لا تملك صلاحية إدارة التجار.
      </div>
    </Card>
  </div>
</template>
