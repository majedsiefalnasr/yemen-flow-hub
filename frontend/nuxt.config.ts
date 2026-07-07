import { fileURLToPath } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import type { NuxtConfig } from 'nuxt/schema'

export default defineNuxtConfig({
  compatibilityDate: '2025-05-15',
  devtools: { enabled: true },
  ssr: false,

  // Nuxt 4: app/ is the default srcDir
  future: {
    compatibilityVersion: 4,
  },

  modules: ['@nuxt/eslint', '@pinia/nuxt', '@vueuse/nuxt', 'shadcn-nuxt'],

  components: [
    {
      path: '~/components',
      extensions: ['vue'],
      ignore: ['**/ui/**', '**/.playwright-cli/**', '**/index.ts'],
    },
  ],

  shadcn: {
    prefix: '',
    componentDir: './app/components/ui',
  },

  vite: {
    plugins: [tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./app', import.meta.url)),
      },
    },
    optimizeDeps: {
      include: [
        '@tanstack/vue-table',
        '@vee-validate/zod',
        '@vue/devtools-core',
        '@vue/devtools-kit',
        'class-variance-authority',
        'clsx',
        'lucide-vue-next',
        'reka-ui',
        'tailwind-merge',
        'uqr',
        'vee-validate',
        'vue-boring-avatars',
        'vue-input-otp',
        'vue-sonner',
        'zod',
        '@unovis/vue',
      ],
    },
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
      demoMode: process.env.NUXT_PUBLIC_DEMO_MODE === 'true',
      visualBypass: process.env.NUXT_PUBLIC_VISUAL_BYPASS === 'true',
      visualBypassRole: process.env.NUXT_PUBLIC_VISUAL_BYPASS_ROLE || 'CBY_ADMIN',
      demoUserSwitch: process.env.NUXT_PUBLIC_DEMO_USER_SWITCH === 'true',
      demoEnabled: process.env.NUXT_PUBLIC_DEMO_USER_SWITCH === 'true',
      googleFontsApiKey: process.env.NUXT_PUBLIC_GOOGLE_FONTS_API_KEY || '',
      inactivityTimeoutMs: Number(process.env.NUXT_PUBLIC_INACTIVITY_TIMEOUT_MS) || 900_000,
      inactivityWarningMs: Number(process.env.NUXT_PUBLIC_INACTIVITY_WARNING_MS) || 120_000,
      auditLegacyWidgets: process.env.AUDIT_LEGACY_WIDGETS === 'true',
    },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },

  nitro: {
    prerender: {
      crawlLinks: false,
      routes: [],
    },
  },
}) satisfies NuxtConfig
