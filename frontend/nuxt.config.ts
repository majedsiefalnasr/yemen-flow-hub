import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-05-15',
  devtools: { enabled: true },

  // Nuxt 4: app/ is the default srcDir
  future: {
    compatibilityVersion: 4,
  },

  modules: [
    '@pinia/nuxt',
    '@vueuse/nuxt',
  ],

  vite: {
    plugins: [tailwindcss()],
  },

  css: [
    '~/assets/css/main.css',
  ],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000',
    },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },
})
