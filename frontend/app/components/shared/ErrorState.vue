<script setup lang="ts">
import { computed } from 'vue'
import { Ban, SearchX, ServerCrash, WifiOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

interface ErrorAction {
  label: string
  variant?: 'default' | 'outline' | 'ghost' | 'destructive'
  onClick: () => void
}

const props = withDefaults(defineProps<{
  code?: number | string
  title?: string
  description?: string
  icon?: any
  actions?: ErrorAction[]
}>(), {
  code: 500,
})

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
  if (code === 401) return 'غير مصرح'
  if (code === 403) return 'ممنوع'
  if (code === 404) return 'الصفحة غير موجودة'
  if (code === 503) return 'الخدمة غير متاحة'
  return 'حدث خطأ غير متوقع'
})

const defaultDescription = computed(() => {
  const code = Number(props.code)
  if (code === 401) return 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.'
  if (code === 403) return 'ليس لديك صلاحية للوصول إلى هذه الصفحة. تواصل مع مدير النظام.'
  if (code === 404) return 'الصفحة التي تبحث عنها غير موجودة أو تم نقلها.'
  if (code === 503) return 'الخدمة غير متاحة حاليًا. يرجى المحاولة لاحقًا.'
  return 'حدث خطأ في الخادم. يمكنك إعادة المحاولة أو العودة إلى لوحة التحكم.'
})

const resolvedIcon = computed(() => props.icon || defaultIcon.value)
const resolvedTitle = computed(() => props.title || defaultTitle.value)
const resolvedDescription = computed(() => props.description || defaultDescription.value)
</script>

<template>
  <div class="flex min-h-[60vh] flex-col items-center justify-center gap-6 text-center" >
    <div class="rounded-full bg-muted p-6">
      <component :is="resolvedIcon" class="h-12 w-12 text-muted-foreground" />
    </div>
    <div v-if="code" class="text-7xl font-bold text-muted-foreground/30">{{ code }}</div>
    <div class="space-y-2">
      <h1 class="text-2xl font-bold tracking-tight">{{ resolvedTitle }}</h1>
      <p class="max-w-sm text-sm text-muted-foreground">{{ resolvedDescription }}</p>
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
