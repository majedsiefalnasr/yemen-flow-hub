<script setup lang="ts">
type LockedBannerVariant = 'locked' | 'readonly' | 'pending'

const props = defineProps<{
  variant: LockedBannerVariant
}>()

const VARIANT_CONFIG: Record<LockedBannerVariant, { icon: string; message: string; mod: string }> = {
  locked: {
    icon: '🔒',
    message: 'هذا الطلب مقفل ولا يمكن اتخاذ أي إجراء عليه',
    mod: 'locked-banner--locked',
  },
  readonly: {
    icon: '👁',
    message: 'هذا الطلب في وضع القراءة فقط',
    mod: 'locked-banner--readonly',
  },
  pending: {
    icon: '🕐',
    message: 'هذا الطلب قيد المراجعة — لا يمكن إجراء تعديلات حتى اكتمال المرحلة الحالية',
    mod: 'locked-banner--pending',
  },
}

const config = VARIANT_CONFIG[props.variant]
</script>

<template>
  <div class="locked-banner" :class="config.mod" role="alert" aria-live="polite" dir="rtl">
    <span class="locked-icon" aria-hidden="true">{{ config.icon }}</span>
    <span class="locked-message">{{ config.message }}</span>
  </div>
</template>

<style scoped>
.locked-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f5f5f7;
  border: 1px solid #d2d2d7;
  border-radius: 12px;
  color: #8e8e93;
  font-size: 15px;
  font-weight: 500;
}

.locked-banner--locked {
  background: #f5f5f7;
  border-color: #d2d2d7;
  color: #8e8e93;
}

.locked-banner--readonly {
  background: #f0f7ff;
  border-color: #b3d4f5;
  color: #0066cc;
}

.locked-banner--pending {
  background: #fffbf0;
  border-color: #f5d78a;
  color: #a05a00;
}

.locked-icon {
  font-size: 18px;
  flex-shrink: 0;
}

.locked-message {
  flex: 1;
}
</style>
