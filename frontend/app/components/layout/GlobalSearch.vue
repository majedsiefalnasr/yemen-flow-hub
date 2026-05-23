<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Search, Loader2, Clock, FileText, User, Building2, Stamp } from 'lucide-vue-next'
import { useSearch } from '../../composables/useSearch'
import { Input } from '../ui/input'
import type { SearchEntityType } from '../../types/models'

const router = useRouter()
const props = withDefaults(defineProps<{ mobile?: boolean }>(), {
  mobile: false,
})

const { results, recentSearches, loading, activeFilter, search, fetchRecent } = useSearch()

const inputValue = ref('')
const isOpen = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)
const wrapperRef = ref<HTMLElement | null>(null)

const hasResults = computed(() =>
  results.value.requests.length > 0
  || results.value.users.length > 0
  || results.value.banks.length > 0
  || results.value.customs.length > 0,
)

const showDropdown = computed(() =>
  isOpen.value && (
    inputValue.value.length >= 2
    || (inputValue.value.length === 0 && recentSearches.value.length > 0)
  ),
)

const availableChips = computed(() => {
  const chips: Array<{ key: SearchEntityType | 'all'; label: string }> = [{ key: 'all', label: 'الكل' }]
  if (results.value.requests.length > 0) chips.push({ key: 'requests', label: 'الطلبات' })
  if (results.value.users.length > 0) chips.push({ key: 'users', label: 'المستخدمون' })
  if (results.value.banks.length > 0) chips.push({ key: 'banks', label: 'البنوك' })
  if (results.value.customs.length > 0) chips.push({ key: 'customs', label: 'البيانات الجمركية' })
  return chips
})

const filteredResults = computed(() => {
  if (activeFilter.value === 'all') return results.value
  return {
    requests: activeFilter.value === 'requests' ? results.value.requests : [],
    users: activeFilter.value === 'users' ? results.value.users : [],
    banks: activeFilter.value === 'banks' ? results.value.banks : [],
    customs: activeFilter.value === 'customs' ? results.value.customs : [],
  }
})

function onInput(e: Event) {
  const val = (e.target as HTMLInputElement).value
  inputValue.value = val
  activeFilter.value = 'all'
  search(val)
  isOpen.value = true
}

function onFocus() {
  isOpen.value = true
  if (inputValue.value.length === 0) {
    void fetchRecent()
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    isOpen.value = false
    inputRef.value?.blur()
  }
}

function selectRecent(term: string) {
  inputValue.value = term
  search(term)
  isOpen.value = true
}

function navigateTo(path: string) {
  isOpen.value = false
  inputValue.value = ''
  void router.push(path)
}

function onClickOutside(e: MouseEvent) {
  if (wrapperRef.value && !wrapperRef.value.contains(e.target as Node)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onClickOutside)
  void fetchRecent()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside)
})
</script>

<template>
  <div ref="wrapperRef" class="relative w-full max-w-sm" :class="{ 'block': props.mobile, 'hidden max-md:hidden': !props.mobile }">
    <!-- Input -->
    <div class="flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2">
      <Search class="h-4 w-4 flex-shrink-0 text-gray-500" />
      <input
        ref="inputRef"
        type="search"
        class="flex-1 border-none bg-transparent text-sm outline-none placeholder:text-gray-400"
        placeholder="بحث..."
        aria-label="بحث في النظام"
        autocomplete="off"
        dir="rtl"
        :value="inputValue"
        @input="onInput"
        @focus="onFocus"
        @keydown="onKeydown"
      />
      <Loader2 v-if="loading" class="h-4 w-4 flex-shrink-0 animate-spin text-blue-600" aria-hidden="true" />
    </div>

    <!-- Dropdown -->
    <div
      v-if="showDropdown"
      class="absolute top-full start-0 z-50 mt-1 w-full max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
      role="listbox"
      aria-label="نتائج البحث"
    >

      <!-- Recent searches (when query is empty) -->
      <template v-if="inputValue.length === 0 && recentSearches.length > 0">
        <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500">عمليات البحث الأخيرة</div>
        <button
          v-for="term in recentSearches"
          :key="term"
          class="flex w-full items-center gap-2.5 px-3 py-2 text-start transition-colors hover:bg-gray-100"
          type="button"
          @click="selectRecent(term)"
        >
          <Clock class="h-4 w-4 flex-shrink-0 text-gray-500" />
          <span class="text-sm text-gray-700">{{ term }}</span>
        </button>
      </template>

      <!-- Results (when query ≥ 2 chars) -->
      <template v-else-if="inputValue.length >= 2">
        <!-- Filter chips -->
        <div v-if="hasResults" class="flex flex-wrap gap-1.5 border-b border-gray-200 px-3 py-2">
          <button
            v-for="chip in availableChips"
            :key="chip.key"
            class="rounded-full border text-xs px-2.5 py-1 transition-all"
            :class="activeFilter === chip.key ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300 bg-transparent text-gray-700 hover:bg-gray-100'"
            type="button"
            @click="activeFilter = chip.key"
          >
            {{ chip.label }}
          </button>
        </div>

        <!-- Requests group -->
        <template v-if="filteredResults.requests.length > 0">
          <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500">الطلبات</div>
          <button
            v-for="req in filteredResults.requests"
            :key="req.id"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-start transition-colors hover:bg-gray-100"
            type="button"
            @click="navigateTo(`/requests/${req.id}`)"
          >
            <FileText class="h-4 w-4 flex-shrink-0 text-gray-500" />
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-gray-900">{{ req.reference_number }}</div>
              <div class="truncate text-xs text-gray-600">{{ req.supplier_name }}</div>
            </div>
          </button>
        </template>

        <!-- Users group -->
        <template v-if="filteredResults.users.length > 0">
          <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500">المستخدمون</div>
          <button
            v-for="user in filteredResults.users"
            :key="user.id"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-start transition-colors hover:bg-gray-100"
            type="button"
            @click="navigateTo('/users')"
          >
            <User class="h-4 w-4 flex-shrink-0 text-gray-500" />
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
              <div class="truncate text-xs text-gray-600">{{ user.role_label }}</div>
            </div>
          </button>
        </template>

        <!-- Banks group -->
        <template v-if="filteredResults.banks.length > 0">
          <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500">البنوك</div>
          <button
            v-for="bank in filteredResults.banks"
            :key="bank.id"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-start transition-colors hover:bg-gray-100"
            type="button"
            @click="navigateTo('/banks')"
          >
            <Building2 class="h-4 w-4 flex-shrink-0 text-gray-500" />
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-gray-900">{{ bank.name }}</div>
              <div class="truncate text-xs text-gray-600">{{ bank.code }}</div>
            </div>
          </button>
        </template>

        <!-- Customs group -->
        <template v-if="filteredResults.customs.length > 0">
          <div class="px-3 py-2 text-xs font-semibold uppercase text-gray-500">البيانات الجمركية</div>
          <button
            v-for="customs in filteredResults.customs"
            :key="customs.id"
            class="flex w-full items-center gap-2.5 px-3 py-2 text-start transition-colors hover:bg-gray-100"
            type="button"
            @click="navigateTo(`/requests/${customs.request_id}`)"
          >
            <Stamp class="h-4 w-4 flex-shrink-0 text-gray-500" />
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-gray-900">{{ customs.declaration_number }}</div>
              <div class="truncate text-xs text-gray-600">{{ customs.reference_number }}</div>
            </div>
          </button>
        </template>

        <!-- Empty state -->
        <div v-if="!hasResults && !loading" class="flex flex-col items-center gap-2 px-4 py-6 text-center">
          <Search class="h-6 w-6 opacity-30 text-gray-400" />
          <span class="text-sm text-gray-600">لا توجد نتائج لـ «{{ inputValue }}»</span>
        </div>
      </template>
    </div>

    <div v-if="showDropdown" class="fixed inset-0 z-40" aria-hidden="true" @click="isOpen = false" />
  </div>
</template>
