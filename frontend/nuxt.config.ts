import tailwindcss from '@tailwindcss/vite'
import type { NuxtConfig } from 'nuxt/schema'

export default {
  compatibilityDate: '2025-05-15',
  devtools: { enabled: true },

  // Nuxt 4: app/ is the default srcDir
  future: {
    compatibilityVersion: 4,
  },

  modules: [
    '@pinia/nuxt',
    '@vueuse/nuxt',
    'shadcn-nuxt',
  ],

  shadcn: {
    prefix: '',
    componentDir: './app/components/ui',
  },

  vite: {
    plugins: [tailwindcss()],
  },

  css: [
    '~/assets/css/main.css',
  ],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      demoMode: process.env.NUXT_PUBLIC_DEMO_MODE === 'true',
      inactivityTimeoutMs: Number(process.env.NUXT_PUBLIC_INACTIVITY_TIMEOUT_MS) || 900_000,
      inactivityWarningMs: Number(process.env.NUXT_PUBLIC_INACTIVITY_WARNING_MS) || 120_000,
    },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },
} satisfies NuxtConfig
