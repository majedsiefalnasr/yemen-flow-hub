import { describe, it, expect } from 'vitest'
import { step2Schema, CUSTOMS_BY_PORT, ARRIVAL_PORTS } from '../../../../schemas/wizard.schema'

const VALID = {
  supplier_name: 'Cargill Trading Inc.',
  invoice_number: 'INV-2025-001',
  origin_country: 'الولايات المتحدة',
  invoice_date: '2025-01-15',
  arrival_port: 'ميناء عدن',
  shipping_port: '',
  customs_office: '',
  bl_number: '',
}

function validate(data: object) {
  return step2Schema.safeParse(data)
}

function errorsFor(data: object): string[] {
  const r = validate(data)
  if (r.success) return []
  return r.error.issues.map(i => i.path[0] as string)
}

describe('WizardStep2 — supplier_name', () => {
  it('required', () => expect(errorsFor({ ...VALID, supplier_name: '' })).toContain('supplier_name'))
  it('accepts valid name', () => expect(validate(VALID).success).toBe(true))
  it('max 255 chars', () => expect(errorsFor({ ...VALID, supplier_name: 'x'.repeat(256) })).toContain('supplier_name'))
})

describe('WizardStep2 — invoice_number', () => {
  it('required', () => expect(errorsFor({ ...VALID, invoice_number: '' })).toContain('invoice_number'))
  it('max 100 chars', () => expect(errorsFor({ ...VALID, invoice_number: 'x'.repeat(101) })).toContain('invoice_number'))
})

describe('WizardStep2 — origin_country', () => {
  it('required', () => expect(errorsFor({ ...VALID, origin_country: '' })).toContain('origin_country'))
  it('accepts any non-empty string', () => expect(validate({ ...VALID, origin_country: 'السعودية' }).success).toBe(true))
})

describe('WizardStep2 — invoice_date', () => {
  it('required', () => expect(errorsFor({ ...VALID, invoice_date: '' })).toContain('invoice_date'))
  it('accepts past date', () => expect(validate({ ...VALID, invoice_date: '2023-06-01' }).success).toBe(true))
  it('accepts future date', () => expect(validate({ ...VALID, invoice_date: '2028-01-01' }).success).toBe(true))
})

describe('WizardStep2 — arrival_port', () => {
  it('required', () => expect(errorsFor({ ...VALID, arrival_port: '' })).toContain('arrival_port'))
  it('three ports defined', () => expect(ARRIVAL_PORTS.length).toBe(3))
  it('accepts ميناء عدن', () => expect(validate({ ...VALID, arrival_port: 'ميناء عدن' }).success).toBe(true))
})

describe('WizardStep2 — optional fields', () => {
  it('shipping_port optional', () => expect(validate({ ...VALID, shipping_port: '' }).success).toBe(true))
  it('customs_office optional', () => expect(validate({ ...VALID, customs_office: '' }).success).toBe(true))
  it('bl_number optional', () => expect(validate({ ...VALID, bl_number: '' }).success).toBe(true))
})

describe('WizardStep2 — auto-fill customs_office', () => {
  it('CUSTOMS_BY_PORT covers all 3 ports', () => {
    for (const port of ARRIVAL_PORTS) {
      expect(CUSTOMS_BY_PORT[port]).toBeDefined()
    }
  })
})
