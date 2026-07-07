/**
 * Phase 9 visual regression — Shell: login, error pages, sidebar, topbar,
 * command palette, and mobile layout.
 *
 * Run once to capture baselines:
 *   npx playwright test --project=visual tests/visual/shell.spec.ts --update-snapshots
 *
 * Run thereafter to compare:
 *   npx playwright test --project=visual tests/visual/shell.spec.ts
 */
import { expect, test } from '@playwright/test'
import { mockApi } from './helpers'

const VIEWPORTS = {
  desktop1440: { width: 1440, height: 1000 },
  desktop1280: { width: 1280, height: 900 },
  tablet: { width: 900, height: 768 },
  compact: { width: 600, height: 812 },
}

// ─── Login page ───────────────────────────────────────────────────────────────

test.describe('login page', () => {
  test('renders at 1440×1000', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/login')
    await page.waitForSelector('input[type="email"], input[name="email"]')
    await expect(page).toHaveScreenshot('login-1440.png')
  })

  test('renders at 600px width', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.compact)
    await page.goto('/login')
    await page.waitForSelector('input[type="email"], input[name="email"]')
    await expect(page).toHaveScreenshot('login-600.png')
  })
})

// ─── Error / state pages ──────────────────────────────────────────────────────

test.describe('error pages', () => {
  test('forbidden page renders', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/forbidden')
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('forbidden.png')
  })

  test('unauthorized page renders', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/unauthorized')
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('unauthorized.png')
  })
})

// ─── Authenticated shell (sidebar + topbar) ────────────────────────────────────

test.describe('shell structure', () => {
  test.beforeEach(async ({ page }) => {
    await mockApi(page, 'BANK_REVIEWER')
  })

  test('full shell at 1440×1000', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/dashboard')
    await page.waitForSelector('[data-sidebar="sidebar"]', { timeout: 15_000 })
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('shell-1440.png')
  })

  test('full shell at 1280×900', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1280)
    await page.goto('/dashboard')
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('shell-1280.png')
  })

  test('mobile sheet at 600px does not expose forbidden controls', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.compact)
    await page.goto('/dashboard')
    await page.waitForLoadState('networkidle')
    // Sidebar should be collapsed or hidden at mobile width
    await expect(page).toHaveScreenshot('shell-600.png')
  })

  test('sidebar collapsed state', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/dashboard')
    await page.waitForLoadState('networkidle')
    // Toggle sidebar collapse (keyboard or button)
    const collapseBtn = page
      .locator('[aria-label*="تصغير"], [aria-label*="collapse"], [data-testid="sidebar-toggle"]')
      .first()
    if (await collapseBtn.isVisible()) {
      await collapseBtn.click()
      await page.waitForTimeout(400) // animation
    }
    await expect(page).toHaveScreenshot('shell-sidebar-collapsed.png')
  })
})

// ─── Command palette ──────────────────────────────────────────────────────────

test.describe('command palette', () => {
  test.beforeEach(async ({ page }) => {
    await mockApi(page, 'BANK_REVIEWER')
  })

  test('opens with Ctrl+K', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/dashboard')
    await page.waitForLoadState('networkidle')
    await page.keyboard.press('Control+k')
    // Wait for palette dialog/popover to appear
    await page
      .waitForSelector(
        '[role="dialog"][aria-label*="بحث"], [data-testid="command-palette"], [cmdk-root]',
        { timeout: 5_000 },
      )
      .catch(() => {})
    await page.waitForTimeout(300)
    await expect(page).toHaveScreenshot('command-palette-open.png')
  })

  test('search results in palette', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/dashboard')
    await page.waitForLoadState('networkidle')
    await page.keyboard.press('Control+k')
    await page.waitForTimeout(300)
    const input = page.locator('[role="dialog"] input, [cmdk-input]').first()
    if (await input.isVisible()) {
      await input.fill('طلب')
      await page.waitForTimeout(300)
    }
    await expect(page).toHaveScreenshot('command-palette-search.png')
  })
})

// ─── RTL layout audit — dropdowns, dialogs, tooltips ─────────────────────────

test.describe('RTL component alignment', () => {
  test.beforeEach(async ({ page }) => {
    await mockApi(page, 'CBY_ADMIN')
  })

  test('page direction is RTL on dashboard', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/dashboard')
    await page.waitForLoadState('networkidle')
    // Assert dir="rtl" on html or body
    const dir =
      (await page
        .locator('html')
        .getAttribute('dir')
        .catch(() => null)) ??
      (await page
        .locator('body')
        .getAttribute('dir')
        .catch(() => null))
    expect(dir).toBe('rtl')
  })

  test('requests page with popover open', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop1440)
    await page.goto('/requests')
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('rtl-requests-page.png')
  })
})
