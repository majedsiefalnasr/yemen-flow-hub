import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  snapshotDir: './tests/screenshots',
  expect: {
    toHaveScreenshot: {
      pathTemplate: '{snapshotDir}/{arg}-{platform}{ext}',
    },
  },
  use: {
    baseURL: process.env['PLAYWRIGHT_BASE_URL'] ?? 'http://localhost:3000',
    locale: 'ar',
  },
  // Start the Nuxt dev server for visual tests. Dev mode matches the
  // environment baselines were captured in (DEVTOOLS_MASK targets Nuxt
  // devtools elements that only exist in dev). In CI, Playwright owns the
  // process lifecycle; locally we reuse a running dev server if one exists.
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env['CI'],
    timeout: 180_000,
    stdout: 'ignore',
    stderr: 'pipe',
  },
  projects: [
    // ── Existing e2e suite (unchanged behaviour) ───────────────────────────
    {
      name: 'e2e',
      testDir: './tests/e2e',
      use: { ...devices['Desktop Chrome'] },
    },

    // ── Visual regression lock (Story 9.5) ────────────────────────────────
    {
      name: 'visual',
      testDir: './tests/visual',
      snapshotDir: './tests/visual/__screenshots__',
      expect: {
        toHaveScreenshot: {
          // Tuned after test-the-test exercise (AC7/AC8):
          // 200px tolerates OS-level font-rendering noise (macOS vs Ubuntu)
          // while still catching a deliberate 4px padding change (~300–600px diff).
          maxDiffPixels: 200,
          // Path template: keep default Playwright layout under __screenshots__/
          pathTemplate: '{snapshotDir}/{testFilePath}/{arg}{ext}',
        },
      },
      use: {
        ...devices['Desktop Chrome'],
        // Disable animations so screenshots are deterministic.
        reducedMotion: 'reduce',
      },
    },
  ],
})
