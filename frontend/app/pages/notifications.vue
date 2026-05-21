<script setup lang="ts">
import { onMounted } from 'vue'
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

onMounted(async () => {
  await fetchNotifications()
  await store.refreshUnreadCount()
})

async function handleMarkRead(id: string) {
  const success = await markRead(id)
  if (success) {
    await store.refreshUnreadCount()
  }
}

async function handleMarkAllRead() {
  const success = await markAllRead()
  if (success) {
    await store.refreshUnreadCount()
  }
}

async function goToPage(page: number) {
  await fetchNotifications(page)
}

function iconName(type?: string): IconName {
  switch (type) {
    case 'request_submitted':
      return 'file-text'
    case 'request_approved':
      return 'check-circle'
    case 'request_rejected':
      return 'x-circle'
    case 'request_returned':
      return 'rotate-ccw'
    case 'swift_upload_requested':
      return 'upload-cloud'
    case 'voting_opened':
      return 'vote'
    case 'customs_issued':
      return 'stamp'
    case 'claim_released':
      return 'alert-triangle'
    default:
      return 'bell'
  }
}

function notifAccentClass(type?: string): string {
  return type === 'claim_released' ? 'notif-amber' : ''
}

function notifLink(data: { type?: string; request_id?: number | null }): string | null {
  if (data.request_id) return `/requests/${data.request_id}`
  return null
}
</script>

<template>
  <div class="notifications-page">
    <div class="page-header">
      <h1 class="page-title">الإشعارات</h1>
      <button
        v-if="(notifications?.length ?? 0) > 0"
        class="mark-all-btn"
        :disabled="loading"
        @click="handleMarkAllRead"
      >
        تحديد الكل كمقروء
      </button>
    </div>

    <!-- Error state -->
    <div v-if="error" class="error-banner" role="alert">
      {{ error }}
    </div>

    <!-- Loading state -->
    <div v-if="loading && (notifications?.length ?? 0) === 0" class="loading-state">
      <div class="spinner" aria-label="جاري التحميل..." />
    </div>

    <!-- Empty state -->
    <div v-else-if="!loading && (notifications?.length ?? 0) === 0" class="empty-state">
      <p class="empty-text">لا توجد إشعارات بعد</p>
    </div>

    <!-- Notification list -->
    <ul v-else class="notifications-list" aria-label="قائمة الإشعارات">
      <li
        v-for="notif in (notifications ?? [])"
        :key="notif.id"
        class="notification-item"
        :class="[{ unread: !notif.read_at }, notifAccentClass(notif.data.type)]"
        :style="notifLink(notif.data) ? 'cursor: pointer' : ''"
        @click="notifLink(notif.data) ? navigateTo(notifLink(notif.data)!) : undefined"
      >
        <div class="notif-icon" :class="notifAccentClass(notif.data.type)" aria-hidden="true">
          <Icon :name="iconName(notif.data.type)" />
        </div>
        <div class="notif-content">
          <p class="notif-message">{{ notif.data.message }}</p>
          <time class="notif-time" :datetime="notif.created_at">
            {{ formatRelativeTime(notif.created_at) }}
          </time>
        </div>
        <button
          v-if="!notif.read_at"
          class="read-btn"
          :aria-label="`تحديد كمقروء`"
          @click="handleMarkRead(notif.id)"
        >
          تحديد كمقروء
        </button>
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
  gap: 24px;
  max-width: 720px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 28px;
  font-weight: 500;
  color: var(--color-text-primary, #1d1d1f);
  margin: 0;
}

.mark-all-btn {
  font-size: 13px;
  color: var(--color-primary, #0071e3);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 6px 12px;
  border-radius: 6px;
}

.mark-all-btn:hover {
  background-color: var(--color-background, #f5f5f7);
}

.mark-all-btn:disabled {
  opacity: 0.5;
  cursor: default;
}

.error-banner {
  background-color: #fff2f2;
  border: 1px solid var(--color-rejected, #ff3b30);
  border-radius: 8px;
  padding: 12px 16px;
  color: var(--color-rejected, #ff3b30);
  font-size: 14px;
}

.loading-state {
  display: flex;
  justify-content: center;
  padding: 48px 0;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--color-border, #d2d2d7);
  border-top-color: var(--color-primary, #0071e3);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  background-color: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 12px;
  padding: 48px;
  text-align: center;
}

.empty-text {
  font-size: 15px;
  color: var(--color-text-secondary, #6e6e73);
  margin: 0;
}

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
  gap: 16px;
  background-color: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 12px;
  padding: 16px;
  /* RTL: unread indicator on the right */
  border-inline-end: 4px solid transparent;
}

.notification-item.unread {
  border-inline-end-color: var(--color-primary, #0071e3);
}

.notif-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.notif-icon {
  width: 20px;
  height: 20px;
  color: var(--color-text-secondary, #6e6e73);
  flex-shrink: 0;
  margin-top: 2px;
}

.notif-message {
  font-size: 14px;
  color: var(--color-text-primary, #1d1d1f);
  margin: 0;
  line-height: 1.5;
}

.notif-time {
  font-size: 12px;
  color: var(--color-text-secondary, #6e6e73);
}

.read-btn {
  font-size: 12px;
  color: var(--color-primary, #0071e3);
  background: transparent;
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 6px;
  padding: 4px 10px;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}

.read-btn:hover {
  background-color: var(--color-background, #f5f5f7);
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 8px 0;
}

.page-btn {
  font-size: 13px;
  color: var(--color-primary, #0071e3);
  background: var(--color-surface, #ffffff);
  border: 1px solid var(--color-border, #d2d2d7);
  border-radius: 6px;
  padding: 6px 14px;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

.page-info {
  font-size: 13px;
  color: var(--color-text-secondary, #6e6e73);
}

/* claim_released: warning amber accent (#f57f17) */
.notification-item.notif-amber.unread {
  border-inline-end-color: #f57f17;
}

.notif-icon.notif-amber {
  color: #f57f17;
}
</style>
