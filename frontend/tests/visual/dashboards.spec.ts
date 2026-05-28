/**
 * Phase 9 visual regression — one dashboard screenshot per role.
 *
 * Verifies that each role's dashboard renders its queue-first content and
 * does not expose role-inappropriate controls.
 *
 * Run once to capture baselines:
 *   npx playwright test --project=visual tests/visual/dashboards.spec.ts --update-snapshots
 */
import { expect, test } from '@playwright/test'
import { type VisualRole, mockApi } from './helpers'

const VIEWPORT = { width: 1440, height: 1000 }

const ROLES: Array<{ role: VisualRole; label: string }> = [
  { role: 'DATA_ENTRY', label: 'data-entry' },
  { role: 'BANK_REVIEWER', label: 'bank-reviewer' },
  { role: 'BANK_ADMIN', label: 'bank-admin' },
  { role: 'SUPPORT_COMMITTEE', label: 'support-committee' },
  { role: 'SWIFT_OFFICER', label: 'swift-officer' },
  { role: 'EXECUTIVE_MEMBER', label: 'executive-member' },
  { role: 'COMMITTEE_DIRECTOR', label: 'committee-director' },
  { role: 'CBY_ADMIN', label: 'cby-admin' },
]

for (const { role, label } of ROLES) {
  test(`dashboard — ${label}`, async ({ page }) => {
    await page.setViewportSize(VIEWPORT)
    await mockApi(page, role)
    await page.goto('/dashboard')
    // Wait for role-specific dashboard content (KPI cards, action strips, etc.)
    await page.waitForSelector(
      '[data-testid="kpi-card"], [data-testid="action-strip"], .dashboard-kpi, h1, main',
      { timeout: 15_000 },
    )
    await page.waitForLoadState('networkidle')
    // Mask dynamic timestamps so screenshots are deterministic
    await page.addStyleTag({ content: 'time, [data-testid="timestamp"], .text-muted-foreground:not([class*="badge"]) { color: transparent !important; }' })
    await expect(page).toHaveScreenshot(`dashboard-${label}.png`)
  })
}

// ─── Tablet snapshot ──────────────────────────────────────────────────────────

test('CBY_ADMIN dashboard at tablet width', async ({ page }) => {
  await page.setViewportSize({ width: 900, height: 768 })
  await mockApi(page, 'CBY_ADMIN')
  await page.goto('/dashboard')
  await page.waitForLoadState('networkidle')
  await expect(page).toHaveScreenshot('dashboard-cby-admin-tablet.png')
})
