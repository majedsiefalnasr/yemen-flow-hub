<script setup lang="ts">
import { AlertTriangle, Home, Lock, RefreshCcw, ServerCrash, ShieldOff } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

type ErrorCode = 401 | 403 | 404 | 500 | 503 | number

const props = withDefaults(defineProps<{
  code?: ErrorCode
  /** Override the default title */
  title?: string
  /** Override the default description */
  description?: string
  /** Show or hide the "try again" action */
  showRetry?: boolean
  /** Redirect path for the primary "back to safety" action (defaults to /dashboard) */
  backTo?: string
  /** Label for the back action */
  backLabel?: string
}>(), {
  code: 500,
  showRetry: true,
  backTo: '/dashboard',
})

const config = computed(() => {
  const defaults: Record<number, { title: string; description: string; icon: typeof AlertTriangle }> = {
    401: {
      title: 'غير مصرح',
      description: 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.',
      icon: Lock,
    },
    403: {
      title: 'ممنوع الوصول',
      description: 'ليس لديك صلاحية للوصول إلى هذا المورد.',
      icon: ShieldOff,
    },
    404: {
      title: 'الصفحة غير موجودة',
      description: 'تعذر العثور على الصفحة المطلوبة. ربما تم نقلها أو حذفها.',
      icon: AlertTriangle,
    },
    500: {
      title: 'خطأ في الخادم',
      description: 'حدث خطأ غير متوقع. يمكنك إعادة المحاولة أو العودة للوحة التحكم.',
      icon: ServerCrash,
    },
    503: {
      title: 'الخدمة غير متاحة',
      description: 'الخدمة متوقفة مؤقتاً للصيانة. يُرجى المحاولة لاحقاً.',
      icon: ServerCrash,
    },
  }
  return defaults[props.code] ?? defaults[500]!
})

const resolvedTitle = computed(() => props.title ?? config.value!.title)
const resolvedDescription = computed(() => props.description ?? config.value!.description)
const IconComponent = computed(() => config.value!.icon)
const backLabelResolved = computed(() => props.backLabel ?? 'العودة للوحة التحكم')

function reload() {
  if (import.meta.client) window.location.reload()
}
</script>

<template>
  <div class="grid min-h-[60vh] place-items-center p-6">
    <section class="w-full max-w-md text-center">
      <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-destructive/10 text-destructive">
        <component :is="IconComponent" class="h-7 w-7" />
      </div>

      <p class="mt-6 text-sm font-medium text-muted-foreground">
        {{ code }}
      </p>
      <h2 class="mt-2 text-2xl font-bold">
        {{ resolvedTitle }}
      </h2>
      <p class="mt-3 text-sm leading-7 text-muted-foreground">
        {{ resolvedDescription }}
      </p>

      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <Button v-if="showRetry && code >= 500" @click="reload">
          <RefreshCcw class="ms-1 h-4 w-4" />
          إعادة المحاولة
        </Button>
        <Button :variant="showRetry && code >= 500 ? 'outline' : 'default'" as-child>
          <NuxtLink :to="backTo">
            <Home class="ms-1 h-4 w-4" />
            {{ backLabelResolved }}
          </NuxtLink>
        </Button>
      </div>

      <slot />
    </section>
  </div>
</template>
