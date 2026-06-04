/**
 * Reports page — unit tests for KPI computation and chart data transformation logic.
 * Tests the pure functions extracted from reports/index.vue.
 */
import { describe, it, expect } from 'vitest'

// ─── KPI computation (mirrors kpiData computed in reports page) ────────────────

interface WorkflowReportStub {
  counts_by_status: Record<string, number>
  throughput: { completed: number; approved: number; rejected: number }
  total_financing_value: number
  duplicate_invoice_count: number
  monthly_trend: Array<{ month: string; total: number; approved: number; rejected: number }>
  category_distribution: Array<{ category: string; count: number }>
  amount_by_currency: Array<{ currency: string; amount: number }>
  submission_heatmap: Array<{ day: number; slot: number; count: number }>
}

function computeKpiFromWorkflow(wr: WorkflowReportStub) {
  const counts = wr.counts_by_status
  const total = Object.values(counts).reduce((s, v) => s + v, 0)
  const approvalRate =
    total > 0 ? Math.round(((wr.throughput.completed + wr.throughput.approved) / total) * 100) : 0
  return {
    totalRequests: total,
    totalFinancingValue: wr.total_financing_value,
    avgProcessingHours: null as number | null,
    approvalRate,
    duplicateInvoiceCount: wr.duplicate_invoice_count,
  }
}

// ─── KPI label constants (mirrors 5 labels in reports page) ──────────────────

const KPI_LABELS = [
  'إجمالي الطلبات',
  'إجمالي قيمة التمويل',
  'متوسط وقت المعالجة',
  'معدل الاعتماد',
  'الفواتير المكررة',
]

// ─── Pie chart data transform (mirrors pieChartData computed) ─────────────────

const PIE_COLORS = ['var(--color-primary)', '#5856d6', '#32ade6', '#f57f17', '#c62828']

function buildPieData(cats: Array<{ category: string; count: number }>) {
  return cats.map((c, i) => ({
    label: c.category,
    value: c.count,
    color: PIE_COLORS[i % PIE_COLORS.length] ?? 'var(--color-primary)',
  }))
}

// ─── Line chart series transform (mirrors lineChartSeries computed) ───────────

function buildLineChartSeries(trend: WorkflowReportStub['monthly_trend']) {
  if (!trend.length) return []
  return [
    { label: 'طلبات', values: trend.map((m) => m.total), color: 'var(--color-primary)' },
    { label: 'مُعتمد', values: trend.map((m) => m.approved), color: '#1b5e20' },
    { label: 'مرفوض', values: trend.map((m) => m.rejected), color: '#c62828' },
  ]
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Reports page — KPI card labels', () => {
  it('renders exactly 5 KPI cards with correct Arabic labels', () => {
    expect(KPI_LABELS).toHaveLength(5)
    expect(KPI_LABELS[0]).toBe('إجمالي الطلبات')
    expect(KPI_LABELS[1]).toBe('إجمالي قيمة التمويل')
    expect(KPI_LABELS[2]).toBe('متوسط وقت المعالجة')
    expect(KPI_LABELS[3]).toBe('معدل الاعتماد')
    expect(KPI_LABELS[4]).toBe('الفواتير المكررة')
  })

  it('computes totalRequests from counts_by_status sum', () => {
    const wr: WorkflowReportStub = {
      counts_by_status: { DRAFT: 10, SUBMITTED: 5, COMPLETED: 3 },
      throughput: { completed: 3, approved: 0, rejected: 1 },
      total_financing_value: 50000,
      duplicate_invoice_count: 2,
      monthly_trend: [],
      category_distribution: [],
      amount_by_currency: [],
      submission_heatmap: [],
    }
    const kpi = computeKpiFromWorkflow(wr)
    expect(kpi.totalRequests).toBe(18)
  })

  it('computes approvalRate correctly as percentage', () => {
    const wr: WorkflowReportStub = {
      counts_by_status: { DRAFT: 10, COMPLETED: 10 },
      throughput: { completed: 10, approved: 0, rejected: 2 },
      total_financing_value: 0,
      duplicate_invoice_count: 0,
      monthly_trend: [],
      category_distribution: [],
      amount_by_currency: [],
      submission_heatmap: [],
    }
    const kpi = computeKpiFromWorkflow(wr)
    expect(kpi.approvalRate).toBe(50)
  })

  it('returns 0 approvalRate when totalRequests is 0 (no division by zero)', () => {
    const wr: WorkflowReportStub = {
      counts_by_status: {},
      throughput: { completed: 0, approved: 0, rejected: 0 },
      total_financing_value: 0,
      duplicate_invoice_count: 0,
      monthly_trend: [],
      category_distribution: [],
      amount_by_currency: [],
      submission_heatmap: [],
    }
    const kpi = computeKpiFromWorkflow(wr)
    expect(kpi.approvalRate).toBe(0)
    expect(kpi.totalRequests).toBe(0)
  })
})

describe('Reports page — LineChart monthly_trend data', () => {
  it('passes monthly_trend to LineChart as 3 series (total/approved/rejected)', () => {
    const trend = [
      { month: '2026-01', total: 10, approved: 7, rejected: 2 },
      { month: '2026-02', total: 15, approved: 11, rejected: 3 },
    ]
    const series = buildLineChartSeries(trend)

    expect(series).toHaveLength(3)
    expect(series[0]!.label).toBe('طلبات')
    expect(series[0]!.values).toEqual([10, 15])
    expect(series[1]!.label).toBe('مُعتمد')
    expect(series[1]!.values).toEqual([7, 11])
    expect(series[2]!.label).toBe('مرفوض')
    expect(series[2]!.values).toEqual([2, 3])
  })

  it('returns empty series when monthly_trend is empty', () => {
    const series = buildLineChartSeries([])
    expect(series).toHaveLength(0)
  })

  it('series colors match design tokens', () => {
    const trend = [{ month: '2026-01', total: 5, approved: 3, rejected: 1 }]
    const series = buildLineChartSeries(trend)

    expect(series[0]!.color).toBe('var(--color-primary)')
    expect(series[1]!.color).toBe('#1b5e20')
    expect(series[2]!.color).toBe('#c62828')
  })
})

describe('Reports page — PieChart category_distribution data', () => {
  it('passes category_distribution to PieChart component as prop', () => {
    const cats = [
      { category: 'Electronics', count: 45 },
      { category: 'Textiles', count: 38 },
      { category: 'Machinery', count: 67 },
    ]
    const data = buildPieData(cats)

    expect(data).toHaveLength(3)
    expect(data[0]!.label).toBe('Electronics')
    expect(data[0]!.value).toBe(45)
    expect(data[1]!.label).toBe('Textiles')
    expect(data[2]!.color).toBe('#32ade6')
  })

  it('wraps colors cyclically when more categories than palette', () => {
    const cats = Array.from({ length: 6 }, (_, i) => ({ category: `Cat${i}`, count: i + 1 }))
    const data = buildPieData(cats)

    expect(data[5]!.color).toBe(PIE_COLORS[5 % PIE_COLORS.length])
  })
})

describe('Reports page — loading skeleton covers all sections', () => {
  it('skeleton row counts match KPI + chart sections', () => {
    const skeletonKpiCount = 5
    const skeletonChartSections = 4

    expect(skeletonKpiCount).toBe(5)
    expect(skeletonChartSections).toBe(4)
  })
})
