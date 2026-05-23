<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useNotifications } from '../composables/useNotifications'
import { useNotificationsStore } from '../stores/notifications.store'
import Icon from '../components/ui/Icon.vue'
import type { IconName } from '../components/ui/icon-map'
import { formatRelativeTime } from '../utils/formatRelativeTime'

definePageMeta({ middleware: 'auth' })

const {
  notifications,
  pagination,
  loading,
  error,
  fetchNotifications,
  markRead,
  markAllRead,
} = useNotifications()

const store = useNotificationsStore()

type FilterTab = 'all' | 'unread' | 'read'
const activeFilter = ref<FilterTab>('all')
const searchQuery = ref('')

const allList = computed(() => notifications.value ?? [])
const unreadCount = computed(() => allList.value.filter(n => !n.read_at).length)
const readCount = computed(() => allList.value.filter(n => !!n.read_at).length)

const filteredList = computed(() => {
  let list = allList.value
  if (activeFilter.value === 'unread') list = list.filter(n => !n.read_at)
  else if (activeFilter.value === 'read') list = list.filter(n => !!n.read_at)
  const q = searchQuery.value.trim().toLowerCase()
  if (q) list = list.filter(n => (n.data?.message ?? '').toLowerCase().includes(q))
  return list
})

const totalDisplayCount = computed(() => `${allList.value.length} إشعار`)
const isFiltered = computed(() =>
  activeFilter.value !== 'all' || searchQuery.value.trim() !== '',
)
const emptyTitle = computed(() =>
  isFiltered.value && allList.value.length > 0
    ? 'لا توجد نتائج مطابقة'
    : 'لا توجد إشعارات',
)
const emptySub = computed(() =>
  isFiltered.value && allList.value.length > 0
    ? 'جرب تعديل الفلتر أو البحث'
    : 'ستظهر إشعاراتك هنا عند وصولها',
)

onMounted(async () => {
  await fetchNotifications()
  await store.refreshUnreadCount()
})

async function handleMarkRead(id: string) {
  const success = await markRead(id)
  if (success) await store.refreshUnreadCount()
}

async function handleMarkAllRead() {
  const success = await markAllRead()
  if (success) await store.refreshUnreadCount()
}

async function goToPage(page: number) {
  await fetchNotifications(page)
}

function iconName(type?: string | null): IconName {
  switch (type) {
    case 'request_submitted': return 'file-text'
    case 'request_approved': return 'check-circle'
    case 'request_rejected': return 'x-circle'
    case 'request_returned': return 'rotate-ccw'
    case 'swift_upload_requested': return 'upload-cloud'
    case 'voting_opened': return 'vote'
    case 'customs_issued': return 'stamp'
    case 'claim_released': return 'alert-triangle'
    default: return 'bell'
  }
}

function notifAccentClass(type?: string | null): string {
  return type === 'claim_released' ? 'notif-amber' : ''
}

function notifLink(data?: { type?: string; request_id?: number | null } | null): string | null {
  if (data?.type === 'claim_released' && data.request_id) return `/requests/${data.request_id}`
  return null
}

function handleNotificationClick(data?: { type?: string; request_id?: number | null } | null) {
  const link = notifLink(data)
  if (link) navigateTo(link)
}
</script>

<template>
  <div class="notifications-page" dir="rtl">
    <!-- Page header -->
    <div class="page-header">
      <div class="page-title-group">
        <h1 class="page-title">مركز الإشعارات</h1>
        <span class="page-count">{{ totalDisplayCount }}</span>
      </div>
      <div class="header-actions">
        <button
          v-if="unreadCount > 0"
          class="mark-all-btn"
          :disabled="loading"
          @click="handleMarkAllRead"
        >
          تحديد الكل كمقروء
        </button>
      </div>
    </div>

    <!-- Filter tabs + search row -->
    <div class="filter-row">
      <div class="filter-tabs" role="tablist" aria-label="فلتر الإشعارات">
        <button
          role="tab"
          :aria-selected="activeFilter === 'all'"
          :class="['filter-tab', { active: activeFilter === 'all' }]"
          @click="activeFilter = 'all'"
        >
          الكل <span class="tab-count">{{ allList.length }}</span>
        </button>
        <button
          role="tab"
          :aria-selected="activeFilter === 'unread'"
          :class="['filter-tab', { active: activeFilter === 'unread' }]"
          @click="activeFilter = 'unread'"
        >
          غير مقروء <span class="tab-count">{{ unreadCount }}</span>
        </button>
        <button
          role="tab"
          :aria-selected="activeFilter === 'read'"
          :class="['filter-tab', { active: activeFilter === 'read' }]"
          @click="activeFilter = 'read'"
        >
          مقروء <span class="tab-count">{{ readCount }}</span>
        </button>
      </div>
      <div class="search-wrap">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <input
          v-model="searchQuery"
          type="text"
          class="search-input"
          placeholder="ابحث في الإشعارات..."
          dir="rtl"
          aria-label="البحث في الإشعارات"
        >
      </div>
    </div>

    <!-- Error state -->
    <div v-if="error" class="error-banner" role="alert">
      {{ error }}
    </div>

    <!-- Loading state -->
    <div v-if="loading && allList.length === 0" class="loading-state">
      <div class="spinner" aria-label="جاري التحميل..." />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!loading && filteredList.length === 0"
      class="empty-state"
      data-testid="notifications-empty"
    >
      <div class="empty-icon" aria-hidden="true">
        <Icon name="bell" />
      </div>
      <p class="empty-title">{{ emptyTitle }}</p>
      <p class="empty-sub">{{ emptySub }}</p>
    </div>

    <!-- Notification list -->
    <ul
      v-else
      class="notifications-list"
      aria-label="قائمة الإشعارات"
      data-testid="notifications-list"
    >
      <li
        v-for="notif in filteredList"
        :key="notif.id"
        class="notification-item"
        :class="[{ unread: !notif.read_at }, notifAccentClass(notif.data?.type)]"
        :style="notifLink(notif.data) ? 'cursor: pointer' : ''"
        @click="handleNotificationClick(notif.data)"
      >
        <div class="notif-icon-wrap" :class="notifAccentClass(notif.data?.type)" aria-hidden="true">
          <Icon :name="iconName(notif.data?.type)" />
        </div>
        <div class="notif-content">
          <p class="notif-message">{{ notif.data?.message }}</p>
          <time class="notif-time" :datetime="notif.created_at">
            {{ formatRelativeTime(notif.created_at) }}
          </time>
        </div>
        <div class="notif-actions">
          <button
            v-if="!notif.read_at"
            class="read-icon-btn"
            aria-label="تحديد كمقروء"
            @click.stop="handleMarkRead(notif.id)"
          >
            <Icon name="check" />
          </button>
        </div>
      </li>
    </ul>

    <!-- Pagination -->
    <div
      v-if="pagination.lastPage > 1"
      class="pagination"
      role="navigation"
      aria-label="تنقل الصفحات"
    >
      <button
        class="page-btn"
        :disabled="pagination.currentPage <= 1"
        @click="goToPage(pagination.currentPage - 1)"
      >
        السابق
      </button>
      <span class="page-info">
        {{ pagination.currentPage }} / {{ pagination.lastPage }}
      </span>
      <button
        class="page-btn"
        :disabled="pagination.currentPage >= pagination.lastPage"
        @click="goToPage(pagination.currentPage + 1)"
      >
        التالي
      </button>
    </div>
  </div>
</template>

<style scoped>
.notifications-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 760px;
}

/* Header */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.page-title-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-title {
  font-size: 28px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.page-count {
  font-size: 13px;
  color: #6c757d;
  background: #f5f5f7;
  border: 1px solid #cccccc;
  border-radius: 20px;
  padding: 3px 10px;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.mark-all-btn {
  font-size: 13px;
  color: #0066cc;
  background: transparent;
  border: 1px solid #0066cc;
  border-radius: 8px;
  padding: 6px 14px;
  cursor: pointer;
  white-space: nowrap;
}

.mark-all-btn:disabled {
  opacity: 0.5;
  cursor: default;
}

/* Filter row */
.filter-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.filter-tabs {
  display: flex;
  gap: 4px;
  background: #f5f5f7;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 4px;
}

.filter-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  color: #6c757d;
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
}

.filter-tab.active {
  background: #ffffff;
  color: #1c222b;
  font-weight: 500;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tab-count {
  font-size: 11px;
  background: #cccccc;
  color: #1c222b;
  border-radius: 20px;
  padding: 1px 6px;
  min-width: 18px;
  text-align: center;
}

.filter-tab.active .tab-count {
  background: #e8f0fe;
  color: #0066cc;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #8e8e93;
  pointer-events: none;
}

.search-input {
  width: 100%;
  height: 40px;
  padding: 0 38px 0 12px;
  border: 1px solid #cccccc;
  border-radius: 12px;
  font-size: 14px;
  color: #1c222b;
  background: #ffffff;
  outline: none;
  box-sizing: border-box;
  font-family: inherit;
}

.search-input:focus {
  border-color: #0066cc;
}

/* Error */
.error-banner {
  background-color: #fff2f2;
  border: 1px solid #c62828;
  border-radius: 8px;
  padding: 12px 16px;
  color: #c62828;
  font-size: 14px;
}

/* Loading */
.loading-state {
  display: flex;
  justify-content: center;
  padding: 48px 0;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #cccccc;
  border-top-color: #0066cc;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Empty state */
.empty-state {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 56px 32px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  background: #f5f5f7;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #8e8e93;
  margin-bottom: 8px;
}

.empty-title {
  font-size: 16px;
  font-weight: 600;
  color: #1c222b;
  margin: 0;
}

.empty-sub {
  font-size: 13px;
  color: #6c757d;
  margin: 0;
}

/* Notifications list */
.notifications-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 12px;
  padding: 14px 16px;
  border-inline-end: 4px solid transparent;
}

.notification-item.unread {
  border-inline-end-color: #0066cc;
}

.notif-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: #e8f0fe;
  color: #0066cc;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notif-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.notif-message {
  font-size: 14px;
  color: #1c222b;
  margin: 0;
  line-height: 1.5;
}

.notif-time {
  font-size: 12px;
  color: #6c757d;
}

.notif-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.read-icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: none;
  border-radius: 6px;
  background: transparent;
  cursor: pointer;
  color: #6c757d;
}

.read-icon-btn:hover {
  background: #e6f9ec;
  color: #1b5e20;
}

/* Pagination */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 8px 0;
}

.page-btn {
  font-size: 13px;
  color: #0066cc;
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 8px;
  padding: 6px 16px;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

.page-info {
  font-size: 13px;
  color: #6c757d;
}

/* Amber accent for claim_released */
.notification-item.notif-amber.unread {
  border-inline-end-color: #f57f17;
}

.notif-icon-wrap.notif-amber {
  background: #fff3e0;
  color: #f57f17;
}
</style>
