import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  snapshotDir: './tests/screenshots',
  expect: {
    toHaveScreenshot: {
      pathTemplate: '{snapshotDir}/{arg}-{platform}{ext}',
    },
  },
  use: {
    baseURL: 'http://localhost:3000',
    locale: 'ar',
  },
})
