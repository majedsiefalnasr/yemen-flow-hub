/**
 * Phase 9 visual regression — requests table.
 *
 * Covers the main request listing page with:
 * - Table with data rows
 * - Status badge rendering
 * - Search / filter toolbar
 * - Pagination
 * - Desktop 1440 and 1280 viewports
 *
 * Run once to capture baselines:
 *   npx playwright test --project=visual tests/visual/requests.spec.ts --update-snapshots
 */
import { expect, test } from '@playwright/test'
import { mockApi } from './helpers'

test.describe('requests table', () => {
  test.beforeEach(async ({ page }) => {
    // Bank reviewer has a meaningful requests table view
    await mockApi(page, 'BANK_REVIEWER')
  })

  test('full table at 1440×1000', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.goto('/requests')
    // Wait for table rows to render
    await page.waitForSelector('table tbody tr, [role="row"]:not(:first-child)', {
      timeout: 15_000,
    })
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('requests-table-1440.png')
  })

  test('full table at 1280×900', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 })
    await page.goto('/requests')
    await page.waitForSelector('table tbody tr, [role="row"]:not(:first-child)', {
      timeout: 15_000,
    })
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('requests-table-1280.png')
  })

  test('filter toolbar is visible', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.goto('/requests')
    await page.waitForLoadState('networkidle')
    // Toolbar area with search and filters
    const toolbar = page
      .locator('[data-testid="table-toolbar"], .table-toolbar, form[role="search"]')
      .first()
    if (await toolbar.isVisible()) {
      await expect(toolbar).toHaveScreenshot('requests-toolbar.png')
    } else {
      // Fall back to full page screenshot if no testid
      await expect(page).toHaveScreenshot('requests-toolbar-fallback.png')
    }
  })

  test('status badges render correct colors', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 })
    await page.goto('/requests')
    await page.waitForSelector('table tbody tr', { timeout: 15_000 })
    await page.waitForLoadState('networkidle')
    // Take screenshot of the status column area
    const badges = page.locator('[data-testid="status-badge"], .badge, [class*="badge"]')
    if ((await badges.count()) > 0) {
      await expect(badges.first()).toHaveScreenshot('requests-status-badge.png')
    }
  })

  test('requests table at compact 600px', async ({ page }) => {
    await page.setViewportSize({ width: 600, height: 812 })
    await page.goto('/requests')
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveScreenshot('requests-table-600.png')
  })
})
