// @vitest-environment jsdom
import { mount } from '@vue/test-utils'
import { markRaw } from 'vue'
import { describe, expect, it } from 'vitest'
import AnalyticsCard from '../../../components/shared/dashboard/AnalyticsCard.vue'
import MetricCard from '../../../components/shared/dashboard/MetricCard.vue'
import { analyticsStateFixtures, metricFixtures } from '../fixtures/shared-dashboard'

const TestIcon = markRaw({
  template: '<svg aria-hidden="true" />',
})

describe('shared dashboard components', () => {
  it('MetricCard emits click on mouse and keyboard interaction', async () => {
    const wrapper = mount(MetricCard, {
      props: {
        label: metricFixtures.positive.label,
        value: metricFixtures.positive.value,
      },
    })

    const trigger = wrapper.get('[role="button"]')
    await trigger.trigger('click')
    await trigger.trigger('keydown.enter')
    await trigger.trigger('keydown.space')

    expect(wrapper.emitted('click')).toHaveLength(3)
  })

  it('MetricCard does not emit when clickable is false', async () => {
    const wrapper = mount(MetricCard, {
      props: {
        label: metricFixtures.neutral.label,
        value: metricFixtures.neutral.value,
        clickable: false,
      },
    })

    await wrapper.get('.shadow').trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('MetricCard applies semantic tone classes', () => {
    const wrapper = mount(MetricCard, {
      props: {
        label: metricFixtures.negative.label,
        value: metricFixtures.negative.value,
        tone: metricFixtures.negative.tone,
        icon: TestIcon,
      },
    })

    expect(wrapper.html()).toContain('text-[var(--severity-red)]')
  })

  it('AnalyticsCard renders actions/footer slots', () => {
    const wrapper = mount(AnalyticsCard, {
      props: {
        title: 'مؤشرات الامتثال',
      },
      slots: {
        actions: '<button data-testid="act">Action</button>',
        default: '<div data-testid="body">Body</div>',
        footer: '<div data-testid="footer">Footer</div>',
      },
    })

    expect(wrapper.find('[data-testid="act"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="body"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="footer"]').exists()).toBe(true)
  })

  it('AnalyticsCard supports loading/empty/error states', () => {
    const loading = mount(AnalyticsCard, {
      props: {
        title: 'اختبار التحميل',
        ...analyticsStateFixtures.loading,
      },
    })
    expect(loading.html()).toContain('role="status"')

    const empty = mount(AnalyticsCard, {
      props: {
        title: 'اختبار الفراغ',
        ...analyticsStateFixtures.empty,
      },
    })
    expect(empty.text()).toContain('لا توجد عناصر لعرضها')

    const error = mount(AnalyticsCard, {
      props: {
        title: 'اختبار الخطأ',
        ...analyticsStateFixtures.error,
      },
    })
    expect(error.text()).toContain('حدث خطأ أثناء التحميل')
  })
})
