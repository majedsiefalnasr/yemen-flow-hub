<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useSearch } from '../../composables/useSearch'
import SidebarIcon from './SidebarIcon.vue'
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
  <div ref="wrapperRef" class="global-search" :class="{ 'mobile-visible': props.mobile }">
    <!-- Input -->
    <div class="search-input-wrap">
      <SidebarIcon name="search" class="search-icon" />
      <input
        ref="inputRef"
        type="search"
        class="search-input"
        placeholder="بحث..."
        aria-label="بحث في النظام"
        autocomplete="off"
        :value="inputValue"
        @input="onInput"
        @focus="onFocus"
        @keydown="onKeydown"
      />
      <span v-if="loading" class="search-spinner" aria-hidden="true" />
    </div>

    <!-- Dropdown -->
    <div v-if="showDropdown" class="search-dropdown" role="listbox" aria-label="نتائج البحث">

      <!-- Recent searches (when query is empty) -->
      <template v-if="inputValue.length === 0 && recentSearches.length > 0">
        <div class="search-section-header">عمليات البحث الأخيرة</div>
        <button
          v-for="term in recentSearches"
          :key="term"
          class="search-recent-item"
          type="button"
          @click="selectRecent(term)"
        >
          <SidebarIcon name="clock" class="result-icon" />
          <span>{{ term }}</span>
        </button>
      </template>

      <!-- Results (when query ≥ 2 chars) -->
      <template v-else-if="inputValue.length >= 2">
        <!-- Filter chips -->
        <div v-if="hasResults" class="search-chips">
          <button
            v-for="chip in availableChips"
            :key="chip.key"
            class="search-chip"
            :class="{ active: activeFilter === chip.key }"
            type="button"
            @click="activeFilter = chip.key"
          >
            {{ chip.label }}
          </button>
        </div>

        <!-- Requests group -->
        <template v-if="filteredResults.requests.length > 0">
          <div class="search-section-header">الطلبات</div>
          <button
            v-for="req in filteredResults.requests"
            :key="req.id"
            class="search-result-item"
            type="button"
            @click="navigateTo(`/requests/${req.id}`)"
          >
            <SidebarIcon name="file-text" class="result-icon" />
            <div class="result-content">
              <span class="result-primary">{{ req.reference_number }}</span>
              <span class="result-secondary">{{ req.supplier_name }}</span>
            </div>
          </button>
        </template>

        <!-- Users group -->
        <template v-if="filteredResults.users.length > 0">
          <div class="search-section-header">المستخدمون</div>
          <button
            v-for="user in filteredResults.users"
            :key="user.id"
            class="search-result-item"
            type="button"
            @click="navigateTo('/users')"
          >
            <SidebarIcon name="user" class="result-icon" />
            <div class="result-content">
              <span class="result-primary">{{ user.name }}</span>
              <span class="result-secondary">{{ user.role_label }}</span>
            </div>
          </button>
        </template>

        <!-- Banks group -->
        <template v-if="filteredResults.banks.length > 0">
          <div class="search-section-header">البنوك</div>
          <button
            v-for="bank in filteredResults.banks"
            :key="bank.id"
            class="search-result-item"
            type="button"
            @click="navigateTo('/banks')"
          >
            <SidebarIcon name="bank" class="result-icon" />
            <div class="result-content">
              <span class="result-primary">{{ bank.name }}</span>
              <span class="result-secondary">{{ bank.code }}</span>
            </div>
          </button>
        </template>

        <!-- Customs group -->
        <template v-if="filteredResults.customs.length > 0">
          <div class="search-section-header">البيانات الجمركية</div>
          <button
            v-for="customs in filteredResults.customs"
            :key="customs.id"
            class="search-result-item"
            type="button"
            @click="navigateTo(`/requests/${customs.request_id}`)"
          >
            <SidebarIcon name="stamp" class="result-icon" />
            <div class="result-content">
              <span class="result-primary">{{ customs.declaration_number }}</span>
              <span class="result-secondary">{{ customs.reference_number }}</span>
            </div>
          </button>
        </template>

        <!-- Empty state -->
        <div v-if="!hasResults && !loading" class="search-empty">
          <SidebarIcon name="search" class="empty-icon" />
          <span>لا توجد نتائج لـ «{{ inputValue }}»</span>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.global-search {
  position: relative;
  width: 100%;
  max-width: 480px;
}

.search-input-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  background-color: var(--color-background);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  padding: 0 12px;
  height: 36px;
}

.search-icon {
  color: var(--color-text-secondary);
  flex-shrink: 0;
  width: 16px;
  height: 16px;
}

.search-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 14px;
  color: var(--color-text-primary);
  outline: none;
  direction: rtl;
  min-width: 0;
}

.search-input::placeholder {
  color: var(--color-text-secondary);
}

/* Remove browser default clear button */
.search-input::-webkit-search-cancel-button {
  display: none;
}

.search-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  flex-shrink: 0;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.search-dropdown {
  position: absolute;
  top: calc(100% + 6px);
  inset-inline-start: 0;
  inset-inline-end: 0;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  z-index: 100;
  max-height: 400px;
  overflow-y: auto;
  padding: 8px 0;
}

.search-section-header {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 6px 14px 2px;
}

.search-chips {
  display: flex;
  gap: 6px;
  padding: 8px 12px;
  flex-wrap: wrap;
}

.search-chip {
  padding: 4px 10px;
  border-radius: 20px;
  border: 1px solid var(--color-border);
  background-color: transparent;
  font-size: 12px;
  color: var(--color-text-secondary);
  cursor: pointer;
  transition: background-color 0.15s, color 0.15s;
}

.search-chip.active,
.search-chip:hover {
  background-color: var(--color-primary);
  color: #ffffff;
  border-color: var(--color-primary);
}

.search-result-item,
.search-recent-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 14px;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: start;
  direction: rtl;
  transition: background-color 0.1s;
}

.search-result-item:hover,
.search-recent-item:hover {
  background-color: var(--color-background);
}

.result-icon {
  color: var(--color-text-secondary);
  flex-shrink: 0;
  width: 16px;
  height: 16px;
}

.result-content {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.result-primary {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.result-secondary {
  font-size: 11px;
  color: var(--color-text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.search-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 24px 16px;
  color: var(--color-text-secondary);
  font-size: 13px;
}

.empty-icon {
  width: 24px;
  height: 24px;
  opacity: 0.4;
}

/* Mobile: hide on small screens — handled via AppHeader */
@media (max-width: 600px) {
  .global-search:not(.mobile-visible) {
    display: none;
  }
}
</style>
