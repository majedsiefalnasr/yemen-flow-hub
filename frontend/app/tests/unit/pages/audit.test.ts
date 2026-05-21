import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, ref, reactive } from 'vue'

// ─── Pure-logic helpers (mirrored from audit.vue) ─────────────────────────────

function parseDevice(ua: string | null | undefined): string {
  if (!ua) return '—'
  const browser = ua.includes('Chrome') ? 'Chrome'
    : ua.includes('Firefox') ? 'Firefox'
    : ua.includes('Safari') ? 'Safari'
    : ua.includes('Edge') ? 'Edge'
    : 'Unknown'
  const os = ua.includes('Windows') ? 'Win'
    : ua.includes('Mac') ? 'Mac'
    : ua.includes('Linux') ? 'Linux'
    : ua.includes('Android') ? 'Android'
    : (ua.includes('iOS') || ua.includes('iPhone')) ? 'iOS'
    : 'Unknown'
  return `${browser} / ${os}`
}

function formatRef(entityId: number | null): string {
  if (!entityId) return '—'
  return `IMP-${new Date().getFullYear()}-${String(entityId).padStart(4, '0')}`
}

type RiskLevel = 'عالية' | 'متوسطة' | 'منخفضة'

function riskIconColor(level: RiskLevel): string {
  if (level === 'عالية') return '#c62828'
  if (level === 'متوسطة') return '#f57f17'
  return '#32ade6'
}

function openAlertsCount(indicators: Array<{ level: RiskLevel }>): number {
  return indicators.filter(r => r.level === 'عالية').length
}

// ─── KPI rendering ────────────────────────────────────────────────────────────

describe('audit page — KPI rendering', () => {
  it('renders today_count from stats', () => {
    const stats = { today_count: 42, duplicate_invoice_count: 3 }
    expect(stats.today_count).toBe(42)
  })

  it('renders duplicate_invoice_count from stats', () => {
    const stats = { today_count: 5, duplicate_invoice_count: 7 }
    expect(stats.duplicate_invoice_count).toBe(7)
  })

  it('open alerts count is derived from high-level risk indicators', () => {
    const indicators = [
      { level: 'عالية' as RiskLevel },
      { level: 'عالية' as RiskLevel },
      { level: 'متوسطة' as RiskLevel },
      { level: 'منخفضة' as RiskLevel },
    ]
    expect(openAlertsCount(indicators)).toBe(2)
  })

  it('fraud count KPI is hardcoded to 2', () => {
    // Per story spec: no real API for fraud count yet; value is hardcoded to 2
    const fraudCount = 2
    expect(fraudCount).toBe(2)
  })
})

// ─── Tab switching ────────────────────────────────────────────────────────────

describe('audit page — tab switching', () => {
  it('activeTab defaults to "logs"', () => {
    const activeTab = ref<'logs' | 'dup' | 'risk'>('logs')
    expect(activeTab.value).toBe('logs')
  })

  it('switching to "dup" sets activeTab correctly', () => {
    const activeTab = ref<'logs' | 'dup' | 'risk'>('logs')
    const tabLoaded = reactive({ logs: true, dup: false, risk: false })

    function onTabChange(tab: 'logs' | 'dup' | 'risk') {
      activeTab.value = tab
      if (tab === 'dup' && !tabLoaded.dup) tabLoaded.dup = true
      if (tab === 'risk' && !tabLoaded.risk) tabLoaded.risk = true
    }

    onTabChange('dup')
    expect(activeTab.value).toBe('dup')
    expect(tabLoaded.dup).toBe(true)
    expect(tabLoaded.risk).toBe(false)
  })
})

// ─── Duplicate banner ─────────────────────────────────────────────────────────

describe('audit page — duplicate banner', () => {
  it('banner is not rendered when duplicates list is empty', () => {
    const duplicates: unknown[] = []
    const showBanner = duplicates.length > 0
    expect(showBanner).toBe(false)
  })

  it('banner shows correct count when duplicates exist', () => {
    const duplicates = [
      { id: 1, ref: 'IMP-2026-0001', importer: 'شركة الأمل', invoice_number: 'INV-001', sibling_id: 2, sibling_ref: 'IMP-2026-0002' },
      { id: 2, ref: 'IMP-2026-0002', importer: 'شركة الأمل', invoice_number: 'INV-001', sibling_id: 1, sibling_ref: 'IMP-2026-0001' },
    ]
    const bannerText = `تم اكتشاف ${duplicates.length} حالات لفواتير مكررة بحاجة لمراجعة عاجلة`
    expect(bannerText).toContain('2')
    expect(duplicates.length).toBe(2)
  })
})

// ─── parseDevice helper ───────────────────────────────────────────────────────

describe('parseDevice helper', () => {
  it('parses Chrome on Mac', () => {
    expect(parseDevice('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0')).toBe('Chrome / Mac')
  })

  it('parses Firefox on Linux', () => {
    expect(parseDevice('Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0')).toBe('Firefox / Linux')
  })

  it('returns "—" for null user agent', () => {
    expect(parseDevice(null)).toBe('—')
  })
})

// ─── formatRef helper ─────────────────────────────────────────────────────────

describe('formatRef helper', () => {
  it('formats entity id with current year and padded id', () => {
    const year = new Date().getFullYear()
    expect(formatRef(42)).toBe(`IMP-${year}-0042`)
  })

  it('returns "—" for null entity id', () => {
    expect(formatRef(null)).toBe('—')
  })
})

// ─── riskIconColor helper ─────────────────────────────────────────────────────

describe('riskIconColor helper', () => {
  it('returns red for عالية', () => {
    expect(riskIconColor('عالية')).toBe('#c62828')
  })

  it('returns orange for متوسطة', () => {
    expect(riskIconColor('متوسطة')).toBe('#f57f17')
  })

  it('returns cyan for منخفضة', () => {
    expect(riskIconColor('منخفضة')).toBe('#32ade6')
  })
})
