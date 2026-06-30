// @vitest-environment jsdom
import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import WorkflowHistoryTab from '../../../components/request/tabs/WorkflowHistoryTab.vue'

const fetchRequestHistory = vi.fn()

vi.mock('../../../composables/useRequests', () => ({
  useRequests: () => ({ fetchRequestHistory }),
}))

describe('WorkflowHistoryTab', () => {
  beforeEach(() => fetchRequestHistory.mockReset())

  it('renders the placeholder for new requests', () => {
    const wrapper = mount(WorkflowHistoryTab, {
      props: { requestId: null },
      global: {
        stubs: {
          Empty: { template: '<section><slot /></section>' },
          EmptyMedia: { template: '<div><slot /></div>' },
          EmptyHeader: { template: '<div><slot /></div>' },
          EmptyTitle: { template: '<h3><slot /></h3>' },
          EmptyDescription: { template: '<p><slot /></p>' },
          AuditTimeline: true,
        },
      },
    })

    expect(wrapper.text()).toContain('سيظهر سجل سير العمل بعد تقديم الطلب')
    expect(fetchRequestHistory).not.toHaveBeenCalled()
  })

  it('fetches history and renders AuditTimeline for existing requests', async () => {
    fetchRequestHistory.mockResolvedValue([{ id: 1, action: 'submit' }])
    const wrapper = mount(WorkflowHistoryTab, {
      props: { requestId: 42 },
      global: {
        stubs: {
          AuditTimeline: {
            template: '<div data-test="timeline">{{ entries.length }}</div>',
            props: ['entries'],
          },
        },
      },
    })

    await flushPromises()

    expect(fetchRequestHistory).toHaveBeenCalledWith(42)
    expect(wrapper.find('[data-test="timeline"]').text()).toBe('1')
  })
})
