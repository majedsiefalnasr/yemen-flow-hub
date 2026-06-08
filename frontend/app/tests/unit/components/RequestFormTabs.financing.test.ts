// @vitest-environment jsdom
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const source = readFileSync(
  resolve(process.cwd(), 'app/components/request/RequestFormTabs.vue'),
  'utf8',
)

describe('RequestFormTabs financing advisory wiring', () => {
  it('wires financing advisory block into submit handler and button disable state', () => {
    expect(source).toContain('financingAdvisoryBlocked')
    expect(source).toContain('FINANCING_ADVISORY_MESSAGE')
    expect(source).toContain("extractErrorCode(err) === 'FINANCING_LIMIT_EXCEEDED'")
    expect(source).toContain('@advisory-block="financingAdvisoryBlocked = $event"')
    expect(source).toContain('InvoiceTab')
  })

  it('documents advisory-only client block with authoritative backend fallback', () => {
    expect(source).toContain('Advisory only')
    expect(source).toContain('FINANCING_LIMIT_EXCEEDED')
  })
})
