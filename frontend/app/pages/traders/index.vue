<script setup lang="ts">
import { Eye, PlusCircle, Search } from 'lucide-vue-next'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useAuthStore } from '@/stores/auth.store'
import { useTradersStore } from '@/stores/traders'
import { canManageTraders, TRADER_MANAGEMENT_ROLES } from '@/types/trader'

definePageMeta({
  middleware: ['auth', 'role'],
  requiredRoles: [...TRADER_MANAGEMENT_ROLES],
})

const router = useRouter()
const authStore = useAuthStore()
const tradersStore = useTradersStore()
const taxNumberQuery = ref('')
const traderNameQuery = ref('')

const canWrite = computed(() => canManageTraders(authStore.user?.role))

async function loadTraders(page = 1): Promise<void> {
  await tradersStore.loadTraders({
    tax_number: taxNumberQuery.value.trim() || undefined,
    trader_name: traderNameQuery.value.trim() || undefined,
    page,
    per_page: tradersStore.meta?.per_page ?? 20,
  })
}

function submitSearch(): void {
  void loadTraders(1)
}

onMounted(() => {
  void loadTraders()
})
</script>

<template>
  <div class="mx-auto flex max-w-[1600px] flex-col gap-6 p-6" dir="rtl">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="font-heading text-foreground text-2xl font-semibold">إدارة التجار</h1>
        <p class="text-muted-foreground mt-1 text-sm">
          إدارة بيانات التجار والشركات والملاك المرتبطين بهم.
        </p>
      </div>
      <Button v-if="canWrite" @click="router.push('/traders/new')">
        <PlusCircle class="me-2 h-4 w-4" aria-hidden="true" />
        إضافة تاجر
      </Button>
    </div>

    <Card class="border shadow">
      <CardHeader>
        <CardTitle>قائمة التجار</CardTitle>
        <CardDescription>فلتر بالرقم الضريبي أو اسم التاجر.</CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <form class="flex flex-wrap items-center gap-3" @submit.prevent="submitSearch">
          <InputGroup class="max-w-md">
            <InputGroupAddon align="inline-start">
              <Search class="text-muted-foreground h-4 w-4" aria-hidden="true" />
            </InputGroupAddon>
            <InputGroupInput v-model="taxNumberQuery" placeholder="بحث بالرقم الضريبي..." />
          </InputGroup>
          <InputGroup class="max-w-md">
            <InputGroupAddon align="inline-start">
              <Search class="text-muted-foreground h-4 w-4" aria-hidden="true" />
            </InputGroupAddon>
            <InputGroupInput v-model="traderNameQuery" placeholder="بحث باسم التاجر..." />
          </InputGroup>
          <Button type="submit" variant="outline">بحث</Button>
        </form>

        <Alert v-if="tradersStore.error" variant="destructive" role="alert">
          <AlertTitle>خطأ في التحميل</AlertTitle>
          <AlertDescription>{{ tradersStore.error }}</AlertDescription>
          <AlertAction>
            <Button variant="outline" size="sm" @click="loadTraders()">إعادة المحاولة</Button>
          </AlertAction>
        </Alert>

        <div v-if="tradersStore.loading" class="space-y-2">
          <Skeleton v-for="row in 5" :key="row" class="h-12 w-full rounded-md" />
        </div>

        <Empty v-else-if="!tradersStore.error && tradersStore.traders.length === 0">
          <EmptyMedia variant="icon">
            <Search />
          </EmptyMedia>
          <EmptyHeader>
            <EmptyTitle>لا توجد بيانات تجار</EmptyTitle>
            <EmptyDescription>ابدأ بإضافة تاجر أو غيّر معايير البحث.</EmptyDescription>
          </EmptyHeader>
          <EmptyContent>
            <Button v-if="canWrite" @click="router.push('/traders/new')">إضافة تاجر</Button>
          </EmptyContent>
        </Empty>

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="text-right">الرقم الضريبي</TableHead>
              <TableHead class="text-right">اسم التاجر</TableHead>
              <TableHead class="text-right">الشركات</TableHead>
              <TableHead class="text-right">الملاك</TableHead>
              <TableHead class="text-right">إجراء</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow
              v-for="trader in tradersStore.traders"
              :key="trader.id"
              class="cursor-pointer"
              @click="router.push(`/traders/${trader.id}`)"
            >
              <TableCell class="text-primary font-mono">{{ trader.tax_number }}</TableCell>
              <TableCell>{{ trader.trader_name }}</TableCell>
              <TableCell>{{ trader.companies_count ?? trader.companies.length }}</TableCell>
              <TableCell>{{ trader.owners_count ?? trader.owners.length }}</TableCell>
              <TableCell @click.stop>
                <Button size="sm" variant="outline" @click="router.push(`/traders/${trader.id}`)">
                  <Eye class="me-2 h-4 w-4" aria-hidden="true" />
                  عرض
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>

        <div v-if="tradersStore.meta" class="flex items-center justify-between gap-3">
          <span class="text-muted-foreground text-sm">
            إجمالي النتائج: {{ tradersStore.totalCount }}
          </span>
          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              :disabled="!tradersStore.hasPrevPage || tradersStore.loading"
              @click="tradersStore.prevPage()"
            >
              السابق
            </Button>
            <span class="text-muted-foreground text-sm">
              صفحة {{ tradersStore.currentPage }} من {{ tradersStore.meta.last_page }}
            </span>
            <Button
              variant="outline"
              size="sm"
              :disabled="!tradersStore.hasNextPage || tradersStore.loading"
              @click="tradersStore.nextPage()"
            >
              التالي
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
