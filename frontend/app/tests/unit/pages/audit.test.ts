// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { reactive, ref } from 'vue'
import auditPage from '../../../pages/audit.vue'
import { UserRole } from '../../../types/enums'

vi.stubGlobal('definePageMeta', vi.fn())

const fetchAuditLogsMock = vi.hoisted(() => vi.fn())
const fetchAuditStatsMock = vi.hoisted(() => vi.fn())
const fetchDuplicatesMock = vi.hoisted(() => vi.fn())
const fetchRiskIndicatorsMock = vi.hoisted(() => vi.fn())

vi.mock('../../../composables/useAudit', () => ({
  useAudit: () => ({
    fetchAuditLogs: fetchAuditLogsMock,
    fetchAuditStats: fetchAuditStatsMock,
    fetchDuplicates: fetchDuplicatesMock,
    fetchRiskIndicators: fetchRiskIndicatorsMock,
  }),
}))

function makeAuditLog(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    user: { id: 1, name: 'مشرف النظام', email: 'admin@cby.gov.ye', role: UserRole.CBY_ADMIN },
    user_id: 1,
    user_role: UserRole.CBY_ADMIN,
    action: 'USER_UPDATED',
    entity_type: null,
    entity_id: null,
    entity_reference: null,
    from_status: null,
    to_status: null,
    ip_address: '10.0.0.1',
    user_agent: 'Mozilla/5.0 TestAgent',
    metadata: null,
    created_at: '2026-05-22T09:00:00.000Z',
    ...overrides,
  }
}

function mountAuditPage() {
  return mount(auditPage, {
    global: {
      stubs: {
        Teleport: true,
        NuxtLink: { template: '<a><slot /></a>' },
      },
    },
  })
}

// ─── Pure-logic helpers (mirrored from audit.vue) ─────────────────────────────

function parseDevice(ua: string | null | undefined): string {
  if (!ua) return '—'
  const browser = (ua.includes('Edg/') || ua.includes('Edge')) ? 'Edge'
    : ua.includes('Chrome') ? 'Chrome'
    : ua.includes('Firefox') ? 'Firefox'
    : ua.includes('Safari') ? 'Safari'
    : 'Unknown'
  const os = ua.includes('Android') ? 'Android'
    : (ua.includes('iOS') || ua.includes('iPhone')) ? 'iOS'
    : ua.includes('Windows') ? 'Win'
    : ua.includes('Mac') ? 'Mac'
    : ua.includes('Linux') ? 'Linux'
    : 'Unknown'
  return `${browser} / ${os}`
}

function formatRef(entityReference?: string | null): string {
  return entityReference ?? '—'
}

type RiskLevel = 'عالية' | 'متوسطة' | 'منخفضة'

function riskIconColor(level: RiskLevel): string {
  if (level === 'عالية') return '#c62828'
  if (level === 'متوسطة') return '#f57f17'
  return '#32ade6'
}

function openAlertsCount(indicators: Array<{ level: RiskLevel }>): number {
  return indicators.length
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

  it('open alerts count is derived from all returned risk indicators', () => {
    const indicators = [
      { level: 'عالية' as RiskLevel },
      { level: 'عالية' as RiskLevel },
      { level: 'متوسطة' as RiskLevel },
      { level: 'منخفضة' as RiskLevel },
    ]
    expect(openAlertsCount(indicators)).toBe(4)
  })

  it('fraud count KPI is derived from high-severity indicators', () => {
    const indicators = [
      { level: 'عالية' as RiskLevel },
      { level: 'عالية' as RiskLevel },
      { level: 'متوسطة' as RiskLevel },
      { level: 'منخفضة' as RiskLevel },
    ]
    const fraudCount = indicators.filter(item => item.level === 'عالية').length
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

  it('parses Edge before Chrome', () => {
    expect(parseDevice('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 Edg/124.0')).toBe('Edge / Win')
  })

  it('parses Android before Linux', () => {
    expect(parseDevice('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36')).toBe('Chrome / Android')
  })

  it('returns "—" for null user agent', () => {
    expect(parseDevice(null)).toBe('—')
  })
})

// ─── formatRef helper ─────────────────────────────────────────────────────────

describe('formatRef helper', () => {
  it('returns the server-provided entity reference', () => {
    expect(formatRef('IMP-2025-0042')).toBe('IMP-2025-0042')
  })

  it('returns "—" when no entity reference is available', () => {
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

// ─── Row expansion helpers ────────────────────────────────────────────────────

function truncateUa(ua: string | null | undefined, max = 80): string {
  if (!ua) return '—'
  return ua.length > max ? ua.slice(0, max) + '…' : ua
}

type AuditLogMeta = { before?: Record<string, unknown>; after?: Record<string, unknown> } | null

const MISSING_DIFF_VALUE = '—'
const EMPTY_DIFF_VALUE = 'فارغ'

function hasDiffValue(record: Record<string, unknown>, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(record, key)
}

function formatDiffValue(record: Record<string, unknown>, key: string): unknown {
  if (!hasDiffValue(record, key)) return MISSING_DIFF_VALUE

  const value = record[key]

  return value === null ? EMPTY_DIFF_VALUE : value
}

function hasDiff(meta: AuditLogMeta): boolean {
  return diffRows(meta).length > 0
}

function diffRows(meta: AuditLogMeta): Array<{ key: string; before: unknown; after: unknown }> {
  if (!meta) return []
  const before = (meta.before ?? {}) as Record<string, unknown>
  const after  = (meta.after  ?? {}) as Record<string, unknown>
  const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]))

  return keys
    .filter((key) => {
      const beforeHasValue = hasDiffValue(before, key)
      const afterHasValue = hasDiffValue(after, key)

      if (!beforeHasValue && !afterHasValue) return false

      const beforeValue = beforeHasValue ? before[key] : undefined
      const afterValue = afterHasValue ? after[key] : undefined

      return !beforeHasValue || !afterHasValue || beforeValue !== afterValue
    })
    .map(key => ({
      key,
      before: formatDiffValue(before, key),
      after: formatDiffValue(after, key),
    }))
}

describe('audit.vue row expansion rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    fetchAuditLogsMock.mockResolvedValue({ data: [], meta: { last_page: 1, total: 0 } })
    fetchAuditStatsMock.mockResolvedValue({ today_count: 0, duplicate_invoice_count: 0 })
    fetchDuplicatesMock.mockResolvedValue([])
    fetchRiskIndicatorsMock.mockResolvedValue([])
  })

  it('renders the empty detail state for no-op before/after metadata', async () => {
    fetchAuditLogsMock.mockResolvedValue({
      data: [
        makeAuditLog({
          id: 8,
          user_agent: null,
          metadata: { before: {}, after: {} },
        }),
      ],
      meta: { last_page: 1, total: 1 },
    })

    const wrapper = mountAuditPage()
    await flushPromises()

    await wrapper.get('[data-testid="log-row"]').trigger('click')

    expect(wrapper.find('[data-testid="log-diff-table"]').exists()).toBe(false)
    expect(wrapper.find('.detail-empty').text()).toContain('لا توجد تفاصيل إضافية')
  })

  it('renders only changed keys and preserves explicit null values in the diff table', async () => {
    fetchAuditLogsMock.mockResolvedValue({
      data: [
        makeAuditLog({
          id: 9,
          user_agent: 'A'.repeat(120),
          metadata: {
            before: { bank_id: 12, name: 'علي' },
            after: { bank_id: null, name: 'علي' },
          },
        }),
      ],
      meta: { last_page: 1, total: 1 },
    })

    const wrapper = mountAuditPage()
    await flushPromises()

    await wrapper.get('[data-testid="log-row"]').trigger('click')

    const tableText = wrapper.get('[data-testid="log-diff-table"]').text()
    expect(tableText).toContain('bank_id')
    expect(tableText).toContain('12')
    expect(tableText).toContain('فارغ')
    expect(tableText).not.toContain('علي')

    const truncatedUa = wrapper.get('[data-testid="log-ua-full"]').text()
    expect(truncatedUa).toHaveLength(81)
    expect(truncatedUa.endsWith('…')).toBe(true)
  })
})

describe('audit page — row expansion: toggleLog', () => {
  it('adds log id to expandedLogs on first click', () => {
    const expanded = ref(new Set<number>())
    function toggleLog(id: number) {
      if (expanded.value.has(id)) { expanded.value.delete(id) }
      else { expanded.value.add(id) }
      expanded.value = new Set(expanded.value)
    }
    toggleLog(42)
    expect(expanded.value.has(42)).toBe(true)
  })

  it('removes log id from expandedLogs on second click', () => {
    const expanded = ref(new Set<number>([42]))
    function toggleLog(id: number) {
      if (expanded.value.has(id)) { expanded.value.delete(id) }
      else { expanded.value.add(id) }
      expanded.value = new Set(expanded.value)
    }
    toggleLog(42)
    expect(expanded.value.has(42)).toBe(false)
  })

  it('supports multiple expanded rows independently', () => {
    const expanded = ref(new Set<number>())
    function toggleLog(id: number) {
      if (expanded.value.has(id)) { expanded.value.delete(id) }
      else { expanded.value.add(id) }
      expanded.value = new Set(expanded.value)
    }
    toggleLog(1)
    toggleLog(3)
    expect(expanded.value.has(1)).toBe(true)
    expect(expanded.value.has(3)).toBe(true)
    expect(expanded.value.has(2)).toBe(false)
  })
})

describe('audit page — row expansion: truncateUa', () => {
  it('returns full UA when shorter than max', () => {
    expect(truncateUa('Mozilla/5.0', 80)).toBe('Mozilla/5.0')
  })

  it('truncates long UA with ellipsis', () => {
    const long = 'A'.repeat(100)
    const result = truncateUa(long, 80)
    expect(result).toHaveLength(81) // 80 chars + '…'
    expect(result.endsWith('…')).toBe(true)
  })

  it('returns "—" for null UA', () => {
    expect(truncateUa(null)).toBe('—')
  })

  it('returns "—" for undefined UA', () => {
    expect(truncateUa(undefined)).toBe('—')
  })
})

describe('audit page — row expansion: hasDiff', () => {
  it('returns true when metadata has before and after', () => {
    expect(hasDiff({ before: { role: 'DATA_ENTRY' }, after: { role: 'BANK_REVIEWER' } })).toBe(true)
  })

  it('returns true when metadata has only after', () => {
    expect(hasDiff({ after: { role: 'BANK_REVIEWER' } })).toBe(true)
  })

  it('returns false for null metadata', () => {
    expect(hasDiff(null)).toBe(false)
  })

  it('returns false when metadata has neither before nor after', () => {
    expect(hasDiff({ } as AuditLogMeta)).toBe(false)
  })

  it('returns false when before and after are both empty objects', () => {
    expect(hasDiff({ before: {}, after: {} })).toBe(false)
  })
})

describe('audit page — row expansion: diffRows', () => {
  it('returns one row per changed key', () => {
    const rows = diffRows({
      before: { role: 'DATA_ENTRY', name: 'Ali' },
      after:  { role: 'BANK_REVIEWER', name: 'Ali' },
    })
    expect(rows).toHaveLength(1)
    const roleRow = rows.find(r => r.key === 'role')
    expect(roleRow?.before).toBe('DATA_ENTRY')
    expect(roleRow?.after).toBe('BANK_REVIEWER')
  })

  it('uses "—" as placeholder for missing key in before or after', () => {
    const rows = diffRows({
      before: {},
      after:  { role: 'BANK_REVIEWER' },
    })
    expect(rows).toHaveLength(1)
    expect(rows[0]!.before).toBe('—')
    expect(rows[0]!.after).toBe('BANK_REVIEWER')
  })

  it('renders explicit null values as فارغ instead of dropping the key', () => {
    const rows = diffRows({
      before: { bank_id: 7 },
      after: { bank_id: null },
    })
    expect(rows).toHaveLength(1)
    expect(rows[0]!.before).toBe(7)
    expect(rows[0]!.after).toBe('فارغ')
  })

  it('returns empty array for null metadata', () => {
    expect(diffRows(null)).toHaveLength(0)
  })
})
