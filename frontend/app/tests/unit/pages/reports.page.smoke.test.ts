import { describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { createPinia } from 'pinia'
import { renderToString } from 'vue/server-renderer'

vi.stubGlobal('definePageMeta', vi.fn())

vi.mock('../../../composables/useReports', () => ({
  useReports: () => ({
    fetchWorkflowReport: vi.fn().mockResolvedValue({
      counts_by_status: {},
      throughput: { approved: 0, rejected: 0 },
      total_financing_value: 0,
      duplicate_invoice_count: 0,
      monthly_trend: [],
      category_distribution: [],
      amount_by_currency: [],
      bank_breakdown: [],
      sla_performance: [],
      voting_analytics: [],
      swift_stats: { uploaded: 0, avg_upload_hours: null, pending: 0 },
      fx_stats: { completed: 0, pending: 0 },
      compliance: { on_time_rate: null, sla_violations: 0, returned_count: 0 },
      audit_summary: { total_events: 0, auth_failures: 0 },
    }),
    exportReport: vi.fn(),
  }),
}))

async function renderPage(component: unknown): Promise<string> {
  const app = createSSRApp(component as any)
  app.use(createPinia())
  return renderToString(app)
}

describe('/reports page smoke', () => {
  it('renders reports shell', async () => {
    const page = await import('../../../pages/reports.vue')
    const html = await renderPage(page.default)
    expect(html).toContain('التقارير والتحليلات المتقدمة')
  })
})
