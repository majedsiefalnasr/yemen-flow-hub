import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

describe('admin screen-permissions page', () => {
  const source = readFileSync(
    resolve(process.cwd(), 'app/pages/admin/screen-permissions.vue'),
    'utf8',
  )

  it('does not render a requests column or its derived-capability markup', () => {
    // No standalone "الطلبات" column header, no per-capability sub-header labels
    // ("عرض"/"إنشاء"/"تنفيذ" as a dedicated requests block), and no "مشتقة من
    // المصمم" badge — request access is no longer role-keyed in this matrix.
    expect(source).not.toContain('REQUEST_CAPS')
    expect(source).not.toContain('role.requests')
    expect(source).not.toContain('مشتقة من المصمم')
    expect(source).not.toMatch(/<span class="text-foreground">الطلبات<\/span>/)
  })

  it('no longer describes request access as derived from the workflow designer inline', () => {
    expect(source).not.toContain('صلاحيات الطلبات مشتقة من مصمم سير العمل')
    expect(source).not.toContain('صلاحيات شاشة الطلبات مشتقة إلزاميًا من إسنادات المراحل')
  })

  it('points admins to the workflow designer stage-assignment view for request access', () => {
    expect(source).toContain('سير العملية التنظيمية')
    expect(source).toContain('صلاحيات شاشات النظام حسب الدور')
  })

  it('excludes requests and system_dashboard from manual grantable columns', () => {
    expect(source).toContain(
      "NON_GRANTABLE_SCREEN_KEYS = new Set(['requests', 'system_dashboard'])",
    )
    expect(source).toContain('!NON_GRANTABLE_SCREEN_KEYS.has(s.key)')
  })
})
