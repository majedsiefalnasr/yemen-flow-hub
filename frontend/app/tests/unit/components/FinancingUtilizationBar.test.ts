// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FinancingUtilizationBar from '@/components/workflow/FinancingUtilizationBar.vue'
import { FINANCING_ADVISORY_MESSAGE } from '@/composables/useFinancingLedger'

function mountBar(props: {
  usedPercent?: number | null
  remainingPercent?: number | null
  blocked?: boolean
  loading?: boolean
  error?: string | null
}) {
  return mount(FinancingUtilizationBar, {
    props: {
      usedPercent: props.usedPercent ?? null,
      remainingPercent: props.remainingPercent ?? null,
      blocked: props.blocked ?? false,
      loading: props.loading ?? false,
      error: props.error ?? null,
    },
    attrs: { dir: 'rtl' },
  })
}

describe('FinancingUtilizationBar', () => {
  it('renders used and remaining percentages', () => {
    const wrapper = mountBar({ usedPercent: 60, remainingPercent: 40 })

    expect(wrapper.text()).toContain('المستخدم 60%')
    expect(wrapper.text()).toContain('المتبقي 40%')
  })

  it('shows the low-capacity warning when remaining is exactly the threshold (20)', () => {
    const wrapper = mountBar({ usedPercent: 80, remainingPercent: 20 })

    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('منخفضة')
  })

  it('does not show the low-capacity warning when remaining is just above the threshold (21)', () => {
    const wrapper = mountBar({ usedPercent: 79, remainingPercent: 21 })

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows the blocked advisory message without hiding the percentages', () => {
    const wrapper = mountBar({ usedPercent: 100, remainingPercent: 0, blocked: true })

    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.text()).toContain(FINANCING_ADVISORY_MESSAGE)
    // Blocked takes precedence over the low-capacity warning, but the raw
    // percentages must still be visible — the bar is advisory, not blocking.
    expect(wrapper.text()).toContain('المستخدم 100%')
  })

  it('shows a muted note (not a destructive alert) when the ledger fails to load', () => {
    const wrapper = mountBar({ error: 'تعذّر تحميل مؤشر التمويل. يمكنك متابعة تعبئة النموذج.' })

    expect(wrapper.text()).toContain('يمكنك متابعة تعبئة النموذج')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('renders a loading skeleton while fetching', () => {
    const wrapper = mountBar({ loading: true })

    expect(wrapper.find('.animate-pulse').exists()).toBe(true)
  })

  it('renders nothing when there is no data, not loading, and no error', () => {
    const wrapper = mountBar({})

    expect(wrapper.html().trim()).toBe('<!--v-if-->')
  })
})
