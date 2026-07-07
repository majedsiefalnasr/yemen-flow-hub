import { vi, beforeEach } from 'vitest'
import { ref, computed, reactive, watch, watchEffect, onMounted, onUnmounted, nextTick } from 'vue'
import { setActivePinia, createPinia } from 'pinia'

// process.env.NODE_ENV is needed by createPinia and composables like useInactivityTimer
// Must be set BEFORE beforeEach so Pinia can initialize
if (typeof process === 'undefined') {
  // @ts-expect-error test setup installs a minimal process shim.
  globalThis.process = { env: { NODE_ENV: 'test' } }
} else if (!process.env.NODE_ENV) {
  process.env.NODE_ENV = 'test'
}

// Create a fresh Pinia before each test so stores work without explicit plugin install
beforeEach(() => {
  setActivePinia(createPinia())
})

// Stub Nuxt composables that are auto-imported in pages/composables
// but not available in the Vitest environment.
vi.stubGlobal('useState', (_key: string, init?: () => unknown) => ref(init?.()))
vi.stubGlobal('useRuntimeConfig', () => ({
  public: { apiBase: 'http://localhost', demoMode: false, auditLegacyWidgets: false },
}))
vi.stubGlobal('useRoute', () => ({ params: {}, query: {}, path: '/' }))
vi.stubGlobal('useRouter', () => ({ push: vi.fn(), replace: vi.fn(), back: vi.fn() }))
// This harness never installs a real Vue Router instance (no <router-view>
// ancestor), so the real vue-router `onBeforeRouteLeave` would silently no-op
// anyway (it injects the active route record and bails when none is found).
// Stub it as a no-op here purely so components using it (Nuxt auto-import)
// mount without throwing; it intentionally does not register or invoke the
// guard. Real navigation interception is verified via a live browser pass.
vi.stubGlobal('onBeforeRouteLeave', vi.fn())
vi.stubGlobal('definePageMeta', vi.fn())
vi.stubGlobal('navigateTo', vi.fn())
vi.stubGlobal('useHead', vi.fn())
vi.stubGlobal('useSeoMeta', vi.fn())
vi.stubGlobal('useNuxtApp', () => ({ $router: null }))
vi.stubGlobal('computed', computed)
vi.stubGlobal('ref', ref)
vi.stubGlobal('reactive', reactive)
vi.stubGlobal('watch', watch)
vi.stubGlobal('watchEffect', watchEffect)
vi.stubGlobal('onMounted', onMounted)
vi.stubGlobal('onUnmounted', onUnmounted)
vi.stubGlobal('nextTick', nextTick)
// Stub useSidebar so pages with PageHeader/SidebarTrigger don't require a Sidebar provider
vi.mock('@/components/ui/sidebar/utils', async (importOriginal) => {
  const real = await importOriginal<typeof import('@/components/ui/sidebar/utils')>()
  return {
    ...real,
    useSidebar: () => ({
      state: { value: 'expanded' },
      open: { value: true },
      setOpen: vi.fn(),
      isMobile: { value: false },
      openMobile: { value: false },
      setOpenMobile: vi.fn(),
      toggleSidebar: vi.fn(),
    }),
    provideSidebarContext: vi.fn(),
  }
})

vi.stubGlobal('useToast', () => ({
  toasts: ref([]),
  dismiss: vi.fn(),
  notify: vi.fn(),
  error: vi.fn(),
  success: vi.fn(),
  info: vi.fn(),
  toast: { default: vi.fn(), success: vi.fn(), error: vi.fn(), info: vi.fn() },
}))
