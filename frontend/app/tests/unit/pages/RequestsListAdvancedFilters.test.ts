/**
 * Story 8.9 — Advanced Request List Filters
 * Tests for URL persistence logic and filter state behavior.
 */
import { describe, it, expect } from 'vitest'
import { UserRole } from '../../../types/enums'

// ── Mirror: CBY_BANK_FILTER_ROLES constant ──────────────────────────────────

const CBY_BANK_FILTER_ROLES: UserRole[] = [
  UserRole.SUPPORT_COMMITTEE,
  UserRole.EXECUTIVE_MEMBER,
  UserRole.COMMITTEE_DIRECTOR,
  UserRole.CBY_ADMIN,
]

function reviewerFilterEnabled(role: UserRole | undefined): boolean {
  return !!role && CBY_BANK_FILTER_ROLES.includes(role)
}

// ── Mirror: hasAdvancedFilters computed ─────────────────────────────────────

interface AdvancedFilterState {
  selectedFromDate: string
  selectedToDate: string
  selectedAmountMin: number | ''
  selectedAmountMax: number | ''
  selectedReviewerId: number | ''
}

function hasAdvancedFilters(state: AdvancedFilterState): boolean {
  return (
    state.selectedFromDate !== ''
    || state.selectedToDate !== ''
    || state.selectedAmountMin !== ''
    || state.selectedAmountMax !== ''
    || state.selectedReviewerId !== ''
  )
}

// ── Mirror: URL hydration from route.query ──────────────────────────────────

interface FilterState {
  search: string
  selectedBankId: number | ''
  selectedCurrency: string
  selectedBucket: string
  selectedFromDate: string
  selectedToDate: string
  selectedAmountMin: number | ''
  selectedAmountMax: number | ''
  selectedReviewerId: number | ''
}

function hydrateFromQuery(query: Record<string, string>): FilterState {
  const state: FilterState = {
    search: '',
    selectedBankId: '',
    selectedCurrency: '',
    selectedBucket: 'all',
    selectedFromDate: '',
    selectedToDate: '',
    selectedAmountMin: '',
    selectedAmountMax: '',
    selectedReviewerId: '',
  }
  if (query.search) state.search = query.search
  if (query.bank_id) state.selectedBankId = Number(query.bank_id)
  if (query.currency) state.selectedCurrency = query.currency
  if (query.bucket) state.selectedBucket = query.bucket
  if (query.created_from ?? query.from_date) state.selectedFromDate = query.created_from ?? query.from_date
  if (query.created_to ?? query.to_date) state.selectedToDate = query.created_to ?? query.to_date
  if (query.amount_min !== undefined && query.amount_min !== '') state.selectedAmountMin = Number(query.amount_min)
  if (query.amount_max !== undefined && query.amount_max !== '') state.selectedAmountMax = Number(query.amount_max)
  if (query.assigned_reviewer_id ?? query.reviewer_id) state.selectedReviewerId = Number(query.assigned_reviewer_id ?? query.reviewer_id)
  return state
}

function buildQuery(state: FilterState): Record<string, string> {
  const query: Record<string, string> = {}
  if (state.search) query.search = state.search
  if (state.selectedBankId !== '') query.bank_id = String(state.selectedBankId)
  if (state.selectedCurrency) query.currency = state.selectedCurrency
  if (state.selectedBucket && state.selectedBucket !== 'all') query.bucket = state.selectedBucket
  if (state.selectedFromDate) query.created_from = state.selectedFromDate
  if (state.selectedToDate) query.created_to = state.selectedToDate
  if (state.selectedAmountMin !== '') query.amount_min = String(state.selectedAmountMin)
  if (state.selectedAmountMax !== '') query.amount_max = String(state.selectedAmountMax)
  if (state.selectedReviewerId !== '') query.assigned_reviewer_id = String(state.selectedReviewerId)
  return query
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('Story 8.9 — reviewer filter visibility', () => {
  it('is enabled for CBY_ADMIN', () => {
    expect(reviewerFilterEnabled(UserRole.CBY_ADMIN)).toBe(true)
  })

  it('is enabled for SUPPORT_COMMITTEE', () => {
    expect(reviewerFilterEnabled(UserRole.SUPPORT_COMMITTEE)).toBe(true)
  })

  it('is enabled for COMMITTEE_DIRECTOR', () => {
    expect(reviewerFilterEnabled(UserRole.COMMITTEE_DIRECTOR)).toBe(true)
  })

  it('is disabled for BANK_REVIEWER', () => {
    expect(reviewerFilterEnabled(UserRole.BANK_REVIEWER)).toBe(false)
  })

  it('is disabled for DATA_ENTRY', () => {
    expect(reviewerFilterEnabled(UserRole.DATA_ENTRY)).toBe(false)
  })

  it('is disabled for BANK_ADMIN', () => {
    expect(reviewerFilterEnabled(UserRole.BANK_ADMIN)).toBe(false)
  })

  it('is disabled for undefined role', () => {
    expect(reviewerFilterEnabled(undefined)).toBe(false)
  })
})

describe('Story 8.9 — hasAdvancedFilters', () => {
  const empty: AdvancedFilterState = {
    selectedFromDate: '',
    selectedToDate: '',
    selectedAmountMin: '',
    selectedAmountMax: '',
    selectedReviewerId: '',
  }

  it('returns false when all advanced filters are empty', () => {
    expect(hasAdvancedFilters(empty)).toBe(false)
  })

  it('returns true when created_from is set', () => {
    expect(hasAdvancedFilters({ ...empty, selectedFromDate: '2026-01-01' })).toBe(true)
  })

  it('returns true when created_to is set', () => {
    expect(hasAdvancedFilters({ ...empty, selectedToDate: '2026-12-31' })).toBe(true)
  })

  it('returns true when amount_min is set', () => {
    expect(hasAdvancedFilters({ ...empty, selectedAmountMin: 1000 })).toBe(true)
  })

  it('returns true when amount_max is set', () => {
    expect(hasAdvancedFilters({ ...empty, selectedAmountMax: 99999 })).toBe(true)
  })

  it('returns true when assigned_reviewer_id is set', () => {
    expect(hasAdvancedFilters({ ...empty, selectedReviewerId: 7 })).toBe(true)
  })
})

describe('Story 8.9 — URL persistence: hydrateFromQuery', () => {
  it('restores amount_min from URL', () => {
    const state = hydrateFromQuery({ amount_min: '5000' })
    expect(state.selectedAmountMin).toBe(5000)
  })

  it('restores amount_max from URL', () => {
    const state = hydrateFromQuery({ amount_max: '50000' })
    expect(state.selectedAmountMax).toBe(50000)
  })

  it('restores assigned_reviewer_id from URL', () => {
    const state = hydrateFromQuery({ assigned_reviewer_id: '42' })
    expect(state.selectedReviewerId).toBe(42)
  })

  it('restores full advanced filter state from URL', () => {
    const state = hydrateFromQuery({
      created_from: '2026-01-01',
      created_to: '2026-12-31',
      amount_min: '1000',
      amount_max: '50000',
      assigned_reviewer_id: '7',
    })
    expect(state.selectedFromDate).toBe('2026-01-01')
    expect(state.selectedToDate).toBe('2026-12-31')
    expect(state.selectedAmountMin).toBe(1000)
    expect(state.selectedAmountMax).toBe(50000)
    expect(state.selectedReviewerId).toBe(7)
  })

  it('leaves amount_min empty when not in URL', () => {
    const state = hydrateFromQuery({})
    expect(state.selectedAmountMin).toBe('')
  })

  it('ignores empty amount_min string in URL', () => {
    const state = hydrateFromQuery({ amount_min: '' })
    expect(state.selectedAmountMin).toBe('')
  })

  it('still hydrates legacy query aliases for backward compatibility', () => {
    const state = hydrateFromQuery({
      from_date: '2026-02-01',
      to_date: '2026-02-28',
      reviewer_id: '8',
    })
    expect(state.selectedFromDate).toBe('2026-02-01')
    expect(state.selectedToDate).toBe('2026-02-28')
    expect(state.selectedReviewerId).toBe(8)
  })
})

describe('Story 8.9 — URL persistence: buildQuery', () => {
  const emptyState: FilterState = {
    search: '',
    selectedBankId: '',
    selectedCurrency: '',
    selectedBucket: 'all',
    selectedFromDate: '',
    selectedToDate: '',
    selectedAmountMin: '',
    selectedAmountMax: '',
    selectedReviewerId: '',
  }

  it('serializes amount_min into URL', () => {
    const q = buildQuery({ ...emptyState, selectedAmountMin: 1000 })
    expect(q.amount_min).toBe('1000')
  })

  it('serializes amount_max into URL', () => {
    const q = buildQuery({ ...emptyState, selectedAmountMax: 50000 })
    expect(q.amount_max).toBe('50000')
  })

  it('serializes assigned_reviewer_id into URL', () => {
    const q = buildQuery({ ...emptyState, selectedReviewerId: 7 })
    expect(q.assigned_reviewer_id).toBe('7')
  })

  it('omits amount_min when empty', () => {
    const q = buildQuery({ ...emptyState, selectedAmountMin: '' })
    expect(q.amount_min).toBeUndefined()
  })

  it('omits assigned_reviewer_id when empty', () => {
    const q = buildQuery({ ...emptyState, selectedReviewerId: '' })
    expect(q.assigned_reviewer_id).toBeUndefined()
  })

  it('omits bucket when value is "all"', () => {
    const q = buildQuery({ ...emptyState, selectedBucket: 'all' })
    expect(q.bucket).toBeUndefined()
  })

  it('round-trips: buildQuery → hydrateFromQuery restores original state', () => {
    const original: FilterState = {
      ...emptyState,
      selectedFromDate: '2026-03-01',
      selectedAmountMin: 2500,
      selectedAmountMax: 75000,
      selectedReviewerId: 12,
      selectedCurrency: 'USD',
    }
    const q = buildQuery(original)
    const restored = hydrateFromQuery(q)
    expect(restored.selectedFromDate).toBe(original.selectedFromDate)
    expect(restored.selectedAmountMin).toBe(original.selectedAmountMin)
    expect(restored.selectedAmountMax).toBe(original.selectedAmountMax)
    expect(restored.selectedReviewerId).toBe(original.selectedReviewerId)
    expect(restored.selectedCurrency).toBe(original.selectedCurrency)
  })
})
