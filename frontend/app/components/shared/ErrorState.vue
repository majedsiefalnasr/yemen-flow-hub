<script setup lang="ts">
import { computed } from 'vue'
import { Ban, SearchX, ServerCrash, WifiOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

interface ErrorAction {
  label: string
  variant?: 'default' | 'outline' | 'ghost' | 'destructive'
  onClick: () => void
}

const props = withDefaults(
  defineProps<{
    code?: number | string
    title?: string
    description?: string
    icon?: any
    actions?: ErrorAction[]
  }>(),
  {
    code: 500,
  },
)

const defaultIcon = computed(() => {
  const code = Number(props.code)
  if (code === 401) return WifiOff
  if (code === 403) return Ban
  if (code === 404) return SearchX
  if (code === 503) return WifiOff
  return ServerCrash
})

const defaultTitle = computed(() => {
  const code = Number(props.code)
  if (code === 401) return 'تسجيل الدخول مطلوب'
  if (code === 403) return 'لا تملك صلاحية الوصول'
  if (code === 404) return 'الصفحة غير موجودة'
  if (code === 503) return 'الخدمة غير متاحة'
  return 'تعذّر فتح الصفحة'
})

const defaultDescription = computed(() => {
  const code = Number(props.code)
  if (code === 401) return 'سجل الدخول أولاً للوصول إلى هذه الصفحة.'
  if (code === 403)
    return 'لا تملك صلاحية الوصول إلى هذه الصفحة. تواصل مع مدير النظام إذا كنت تحتاج هذا الإجراء.'
  if (code === 404) return 'الصفحة التي تبحث عنها غير موجودة أو تم نقلها.'
  if (code === 503) return 'الخدمة غير متاحة حاليا. أعد المحاولة بعد قليل.'
  return 'تعذّر فتح الصفحة بسبب خطأ في الخادم. أعد المحاولة أو ارجع إلى لوحة التحكم.'
})

const resolvedIcon = computed(() => props.icon || defaultIcon.value)
const resolvedTitle = computed(() => props.title || defaultTitle.value)
const resolvedDescription = computed(() => props.description || defaultDescription.value)
</script>

<template>
  <div
    class="flex min-h-screen min-w-0 flex-col items-center justify-center gap-6 px-4 text-center"
  >
    <div class="bg-muted rounded-full p-6">
      <component :is="resolvedIcon" class="text-muted-foreground h-12 w-12" />
    </div>
    <div
      v-if="code"
      class="font-heading text-muted-foreground/30 text-6xl leading-none font-semibold tabular-nums"
    >
      {{ code }}
    </div>
    <div class="w-full max-w-sm min-w-0 space-y-2">
      <h1 class="font-heading text-foreground text-2xl leading-8 font-semibold break-words">
        {{ resolvedTitle }}
      </h1>
      <p class="text-muted-foreground text-sm leading-6 break-words">{{ resolvedDescription }}</p>
    </div>
    <div v-if="actions?.length" class="flex flex-wrap justify-center gap-2">
      <Button
        v-for="action in actions"
        :key="action.label"
        :variant="action.variant || 'default'"
        @click="action.onClick"
      >
        {{ action.label }}
      </Button>
    </div>
    <slot />
  </div>
</template>
