<script setup lang="ts">
import { Building2, Edit, Pause, Play } from 'lucide-vue-next'
import type { Merchant } from '../../types/models'

const props = defineProps<{
  merchant: Merchant
}>()

const emit = defineEmits<{
  edit: [merchant: Merchant]
  toggleStatus: [merchant: Merchant]
}>()

function metaVal(val: string | null | undefined): string {
  return val ?? '—'
}

function businessTypeLabel(type: string | null | undefined): string {
  const MAP: Record<string, string> = {
    import: 'استيراد',
    export: 'تصدير',
    retail: 'تجارة تجزئة',
    wholesale: 'تجارة جملة',
    manufacturing: 'تصنيع',
    services: 'خدمات',
  }
  return type ? (MAP[type] ?? type) : '—'
}
</script>

<template>
  <div class="merchant-card" dir="rtl">
    <!-- Header: icon tile + status badge -->
    <div class="card-top">
      <div class="icon-tile" aria-hidden="true">
        <Building2 :size="24" />
      </div>
      <span
        :class="['status-badge', props.merchant.is_active ? 'badge-active' : 'badge-suspended']"
        role="status"
      >
        {{ props.merchant.is_active ? 'نشط' : 'موقوف' }}
      </span>
    </div>

    <!-- Name + category -->
    <div class="card-identity">
      <span class="merchant-name">{{ props.merchant.name }}</span>
      <span class="merchant-category">{{ businessTypeLabel(props.merchant.business_type) }}</span>
    </div>

    <!-- Metadata rows -->
    <dl class="card-meta">
      <div class="meta-row">
        <dt class="meta-label">السجل التجاري</dt>
        <dd class="meta-value mono">{{ metaVal(props.merchant.commercial_register) }}</dd>
      </div>
      <div class="meta-row">
        <dt class="meta-label">الرقم الضريبي</dt>
        <dd class="meta-value mono">{{ metaVal(props.merchant.tax_number) }}</dd>
      </div>
      <div class="meta-row">
        <dt class="meta-label">البنك</dt>
        <dd class="meta-value">{{ metaVal(props.merchant.bank_name) }}</dd>
      </div>
      <div class="meta-row">
        <dt class="meta-label">العنوان</dt>
        <dd class="meta-value">{{ metaVal(props.merchant.address) }}</dd>
      </div>
      <div class="meta-row">
        <dt class="meta-label">هاتف</dt>
        <dd class="meta-value ltr">{{ metaVal(props.merchant.phone) }}</dd>
      </div>
    </dl>

    <!-- Footer: transaction count + actions -->
    <div class="card-footer">
      <div class="transaction-count">
        <span class="tx-label">المعاملات:</span>
        <span class="tx-value">{{ props.merchant.transaction_count ?? 0 }}</span>
      </div>
      <div class="card-actions">
        <button
          class="icon-btn"
          :class="props.merchant.is_active ? 'btn-suspend' : 'btn-activate'"
          :aria-label="props.merchant.is_active ? 'تعليق التاجر' : 'تفعيل التاجر'"
          :title="props.merchant.is_active ? 'تعليق' : 'تفعيل'"
          @click="emit('toggleStatus', props.merchant)"
        >
          <Pause v-if="props.merchant.is_active" :size="16" />
          <Play v-else :size="16" />
        </button>
        <button
          class="icon-btn btn-edit"
          aria-label="تعديل التاجر"
          title="تعديل"
          @click="emit('edit', props.merchant)"
        >
          <Edit :size="16" />
        </button>
      </div>
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
  gap: 12px;
  transition: box-shadow 0.15s;
}

.merchant-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.icon-tile {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #e8f0fe;
  color: #0066cc;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.status-badge {
  display: inline-block;
  padding: 3px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-active {
  background: #e8f5e9;
  color: #1b5e20;
}

.badge-suspended {
  background: #f5f5f7;
  color: #8e8e93;
}

.card-identity {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.merchant-name {
  font-size: 15px;
  font-weight: 600;
  color: #1c222b;
}

.merchant-category {
  font-size: 12px;
  color: #6c757d;
}

.card-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  border-top: 1px solid #f0f0f0;
  padding-top: 12px;
  font-size: 12px;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.meta-label {
  color: #6c757d;
  flex-shrink: 0;
}

.meta-value {
  color: #1c222b;
  text-align: left;
  direction: rtl;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 180px;
}

.meta-value.mono,
.meta-value.ltr {
  direction: ltr;
  font-family: 'Inter', monospace;
  text-align: right;
}

.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  border-top: 1px solid #f0f0f0;
  padding-top: 12px;
  margin-top: 4px;
}

.transaction-count {
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.tx-label {
  color: #6c757d;
}

.tx-value {
  font-weight: 700;
  color: #1c222b;
  font-family: 'Inter', monospace;
}

.card-actions {
  display: flex;
  gap: 4px;
}

.icon-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-edit {
  background: transparent;
  color: #6c757d;
}

.btn-edit:hover {
  background: #f5f5f7;
  color: #0066cc;
}

.btn-suspend {
  background: transparent;
  color: #6c757d;
}

.btn-suspend:hover {
  background: #fff3e0;
  color: #f57f17;
}

.btn-activate {
  background: transparent;
  color: #6c757d;
}

.btn-activate:hover {
  background: #e8f5e9;
  color: #1b5e20;
}
</style>
