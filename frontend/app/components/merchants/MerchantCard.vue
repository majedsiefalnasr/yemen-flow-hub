<script setup lang="ts">
import type { Merchant } from '../../types/models'

const props = defineProps<{
  merchant: Merchant
}>()

const emit = defineEmits<{
  edit: [merchant: Merchant]
  toggleStatus: [merchant: Merchant]
}>()
</script>

<template>
  <div class="merchant-card" dir="rtl">
    <div class="card-header">
      <div class="merchant-avatar" aria-hidden="true">
        {{ props.merchant.name.charAt(0) }}
      </div>
      <div class="merchant-identity">
        <span class="merchant-name">{{ props.merchant.name }}</span>
        <span
          :class="['status-badge', props.merchant.is_active ? 'badge-active' : 'badge-suspended']"
          role="status"
        >
          {{ props.merchant.is_active ? 'نشط' : 'موقوف' }}
        </span>
      </div>
    </div>

    <div class="card-divider" />

    <dl class="card-meta">
      <div class="meta-row">
        <dt class="meta-label">السجل التجاري</dt>
        <dd class="meta-value mono">{{ props.merchant.commercial_register ?? '—' }}</dd>
      </div>
      <div class="meta-row">
        <dt class="meta-label">الرقم الضريبي</dt>
        <dd class="meta-value mono">{{ props.merchant.tax_number ?? '—' }}</dd>
      </div>
      <div class="meta-row">
        <dt class="meta-label">العنوان</dt>
        <dd class="meta-value">{{ props.merchant.address ?? '—' }}</dd>
      </div>
    </dl>

    <div class="card-divider" />

    <div class="card-actions">
      <button
        class="action-btn action-edit"
        @click="emit('edit', props.merchant)"
      >
        تعديل
      </button>
      <button
        :class="['action-btn', props.merchant.is_active ? 'action-suspend' : 'action-activate']"
        @click="emit('toggleStatus', props.merchant)"
      >
        {{ props.merchant.is_active ? 'تعليق' : 'تفعيل' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.merchant-card {
  background: #ffffff;
  border: 1px solid #cccccc;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}

.merchant-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e8f0fe;
  color: #0066cc;
  font-size: 18px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.merchant-identity {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.merchant-name {
  font-size: 15px;
  font-weight: 600;
  color: #1c222b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.status-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
  width: fit-content;
}

.badge-active {
  background: #e8f5e9;
  color: #1b5e20;
}

.badge-suspended {
  background: #f5f5f7;
  color: #8e8e93;
}

.card-divider {
  height: 1px;
  background: #cccccc;
}

.card-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.meta-label {
  font-size: 13px;
  color: #6c757d;
  flex-shrink: 0;
}

.meta-value {
  font-size: 13px;
  color: #1c222b;
  text-align: left;
  direction: ltr;
}

.meta-value.mono {
  font-family: 'Inter', monospace;
}

.card-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  flex: 1;
  height: 36px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  border: 1px solid;
  transition: opacity 0.15s;
}

.action-btn:hover {
  opacity: 0.8;
}

.action-edit {
  background: transparent;
  color: #0066cc;
  border-color: #0066cc;
}

.action-suspend {
  background: transparent;
  color: #c62828;
  border-color: #c62828;
}

.action-activate {
  background: transparent;
  color: #1b5e20;
  border-color: #1b5e20;
}
</style>
