import { ref, computed, onBeforeUnmount, getCurrentInstance } from 'vue'
import { useAuthStore } from '../stores/auth.store'

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'pointerdown', 'scroll'] as const
const XHR_EVENTS = ['xhr:loadstart', 'xhr:progress', 'xhr:finished'] as const

let xhrPatchInstalled = false

function installXhrPatch() {
  if (typeof XMLHttpRequest === 'undefined') return
  if (xhrPatchInstalled) return
  xhrPatchInstalled = true

  const proto = XMLHttpRequest.prototype
  const originalOpen = proto.open
  proto.open = function (this: XMLHttpRequest, ...args: Parameters<typeof originalOpen>) {
    this.addEventListener('loadstart', () => window.dispatchEvent(new CustomEvent('xhr:loadstart')))
    this.addEventListener('progress', () => window.dispatchEvent(new CustomEvent('xhr:progress')))
    this.addEventListener('loadend', () => window.dispatchEvent(new CustomEvent('xhr:finished')))
    return originalOpen.apply(this, args as Parameters<typeof originalOpen>)
  }
}

function debounce<T extends (...args: unknown[]) => void>(fn: T, delay: number): T {
  let timer: ReturnType<typeof setTimeout> | null = null
  return function (this: unknown, ...args: Parameters<T>) {
    if (timer !== null) clearTimeout(timer)
    timer = setTimeout(() => fn.apply(this, args), delay)
  } as T
}

export function useInactivityTimer() {
  const config = useRuntimeConfig()
  const timeoutMs = (config.public.inactivityTimeoutMs as number | undefined) ?? 900_000
  const warningMs = (config.public.inactivityWarningMs as number | undefined) ?? 120_000

  const lastActivity = ref<number>(Date.now())
  const now = ref<number>(Date.now())

  const remainingMs = computed<number>(() =>
    Math.max(0, timeoutMs - (now.value - lastActivity.value)),
  )

  const isWarning = computed<boolean>(() => remainingMs.value < warningMs)

  function bump() {
    lastActivity.value = Date.now()
  }

  const debouncedBump = debounce(bump, 250)

  let ticker: ReturnType<typeof setInterval> | null = null

  function start() {
    if (!process.client) return

    installXhrPatch()

    for (const event of ACTIVITY_EVENTS) {
      window.addEventListener(event, debouncedBump, { passive: true })
    }
    for (const event of XHR_EVENTS) {
      window.addEventListener(event, debouncedBump)
    }

    ticker = setInterval(() => {
      now.value = Date.now()
      if (remainingMs.value <= 0) {
        stop()
        const auth = useAuthStore()
        auth.forceLogout()
      }
    }, 1_000)
  }

  function stop() {
    if (!process.client) return

    for (const event of ACTIVITY_EVENTS) {
      window.removeEventListener(event, debouncedBump)
    }
    for (const event of XHR_EVENTS) {
      window.removeEventListener(event, debouncedBump)
    }

    if (ticker !== null) {
      clearInterval(ticker)
      ticker = null
    }
  }

  async function extend(): Promise<void> {
    const auth = useAuthStore()
    await auth.extendSession()
    lastActivity.value = Date.now()
  }

  start()

  if (getCurrentInstance()) {
    onBeforeUnmount(stop)
  }

  return { lastActivity, remainingMs, isWarning, extend, stop }
}
