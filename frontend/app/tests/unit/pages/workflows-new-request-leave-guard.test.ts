// @vitest-environment jsdom
//
// The global test setup (app/tests/setup.ts) stubs onBeforeRouteLeave as a
// permanent no-op precisely because most page tests never install a real
// router, so the guard would silently never fire anyway. This file is the
// deliberate exception: it installs a real vue-router instance with
// createMemoryHistory + a <router-view> host, restores the REAL
// onBeforeRouteLeave for the duration of these tests, and drives actual
// router.push() navigations — proving the leave-guard genuinely intercepts
// navigation, not just that its handler functions are wired up correctly in
// isolation (see workflows-instance-detail.test.ts's older NOTE on why that
// distinction matters).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import {
  createRouter,
  createMemoryHistory,
  RouterView,
  onBeforeRouteLeave as realOnBeforeRouteLeave,
} from 'vue-router'
import WorkflowsNewVersionPage from '@/pages/workflows/new-request/[versionId].vue'

vi.mock('@/composables/useEngineFormSchema', () => ({
  useEngineFormSchema: () => ({
    fieldGroups: {
      value: [{ id: 1, name: 'main', label: 'المعلومات', sort_order: 0, fields: [] }],
    },
    loading: { value: false },
    error: { value: null },
    fetchSchema: vi.fn(),
    fetchInitialSchema: vi.fn().mockResolvedValue(undefined),
  }),
}))

const HomeStub = defineComponent({ name: 'Home', setup: () => () => h('div', 'home') })

// Mutable per-test so each `it` controls what the (stubbed) wizard reports
// without needing a different component definition each time.
const unsavedFlag = { value: false }
const submissionCompletedFlag = { value: false }

// shadcn AlertDialog's content renders inside a reka-ui Teleport target,
// which @vue/test-utils' mount() wrapper can't introspect (same issue and
// same fix as workflows-new.test.ts's Dialog passthrough). Per AGENTS.md,
// AlertDialog must not be downgraded to raw HTML in the SOURCE component —
// only the TEST replaces it with simple passthrough stubs. The root
// AlertDialog stub gates its slot on the v-model `open` prop, mirroring the
// real component's own open/closed rendering.
function passthrough(name: string) {
  return defineComponent({
    name,
    setup(_, { slots, attrs }) {
      return () => h('div', attrs, slots.default?.())
    },
  })
}

const AlertDialogRootStub = defineComponent({
  name: 'AlertDialog',
  props: { open: { type: Boolean, default: false } },
  setup(props, { slots }) {
    return () => (props.open ? h('div', slots.default?.()) : null)
  },
})

const stubs = {
  NuxtLink: true,
  PageHeader: true,
  EngineRequestWizard: {
    name: 'EngineRequestWizard',
    template: '<div data-stub="wizard" />',
    data: () => ({
      hasUnsavedChanges: unsavedFlag.value,
      submissionCompleted: submissionCompletedFlag.value,
    }),
  },
  AlertDialog: AlertDialogRootStub,
  AlertDialogContent: passthrough('AlertDialogContent'),
  AlertDialogHeader: passthrough('AlertDialogHeader'),
  AlertDialogTitle: passthrough('AlertDialogTitle'),
  AlertDialogDescription: passthrough('AlertDialogDescription'),
  AlertDialogFooter: passthrough('AlertDialogFooter'),
  AlertDialogCancel: passthrough('AlertDialogCancel'),
  AlertDialogAction: passthrough('AlertDialogAction'),
}

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: HomeStub },
      {
        path: '/workflows/new-request/:versionId',
        name: 'new-request',
        component: WorkflowsNewVersionPage,
      },
    ],
  })
}

describe('workflows/new-request/[versionId].vue — real router leave guard', () => {
  beforeEach(() => {
    unsavedFlag.value = false
    submissionCompletedFlag.value = false
    // Restore the REAL vue-router guard for this file only; every other
    // test file keeps using the global no-op stub from setup.ts.
    vi.stubGlobal('onBeforeRouteLeave', realOnBeforeRouteLeave)
  })

  afterEach(() => {
    vi.stubGlobal('onBeforeRouteLeave', vi.fn())
  })

  async function mountAtNewRequest() {
    const router = makeRouter()
    router.push('/workflows/new-request/10')
    await router.isReady()

    const RootStub = defineComponent({
      setup: () => () => h(RouterView),
    })
    const wrapper = mount(RootStub, { global: { plugins: [router], stubs } })
    await flushPromises()
    return { router, wrapper }
  }

  it('blocks navigation away and shows the confirmation dialog when the wizard has unsaved changes', async () => {
    unsavedFlag.value = true
    const { router, wrapper } = await mountAtNewRequest()

    router.push('/')
    await flushPromises()

    // Navigation must have been intercepted: still on the wizard route.
    expect(router.currentRoute.value.path).toBe('/workflows/new-request/10')
    expect(wrapper.text()).toContain('مغادرة الصفحة دون حفظ')
  })

  it('confirming the dialog completes the blocked navigation', async () => {
    unsavedFlag.value = true
    const { router, wrapper } = await mountAtNewRequest()

    router.push('/')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/workflows/new-request/10')

    const leaveButton = wrapper.findAll('div').find((el) => el.text() === 'مغادرة دون حفظ')
    expect(leaveButton).toBeTruthy()
    await leaveButton!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/')
  })

  it('allows navigation through without a dialog when the wizard has no unsaved changes', async () => {
    unsavedFlag.value = false
    const { router, wrapper } = await mountAtNewRequest()

    router.push('/')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/')
    expect(wrapper.text()).not.toContain('مغادرة الصفحة دون حفظ')
  })

  it('allows navigation through without a dialog once the submission has completed, even with unsaved-looking form data', async () => {
    // A just-completed submission still has non-empty formData (the wizard
    // never clears it), so hasUnsavedChanges alone can't distinguish "about
    // to lose data" from "already saved, navigating to the result" — the
    // guard must key off submissionCompleted specifically.
    unsavedFlag.value = true
    submissionCompletedFlag.value = true
    const { router, wrapper } = await mountAtNewRequest()

    router.push('/')
    await flushPromises()

    expect(router.currentRoute.value.path).toBe('/')
    expect(wrapper.text()).not.toContain('مغادرة الصفحة دون حفظ')
  })
})
