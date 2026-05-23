<script setup lang="ts">
import { AlertTriangle, Home, RotateCcw } from 'lucide-vue-next'

defineProps<{
  error: {
    statusCode?: number
    statusMessage?: string
    message?: string
  }
}>()

function reload() {
  if (import.meta.client) {
    window.location.reload()
  }
}

function goHome() {
  clearError({ redirect: '/' })
}
</script>

<template>
  <main
    dir="rtl"
    class="grid min-h-screen place-items-center bg-background p-6 text-foreground"
  >
    <section class="w-full max-w-md text-center">
      <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-destructive/10 text-destructive">
        <AlertTriangle class="h-7 w-7" />
      </div>
      <p class="mt-6 text-sm font-medium text-muted-foreground">
        {{ error.statusCode ?? 500 }}
      </p>
      <h1 class="mt-2 text-2xl font-bold">
        تعذر تحميل الصفحة
      </h1>
      <p class="mt-3 text-sm leading-7 text-muted-foreground">
        حدث خطأ غير متوقع. يمكنك إعادة المحاولة أو العودة إلى الرئيسية.
      </p>
      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <Button @click="reload">
          <RotateCcw class="ms-1 h-4 w-4" />
          إعادة المحاولة
        </Button>
        <Button
          variant="outline"
          @click="goHome"
        >
          <Home class="ms-1 h-4 w-4" />
          العودة للرئيسية
        </Button>
      </div>
    </section>
  </main>
</template>
