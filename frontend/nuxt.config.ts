import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: ['@nuxtjs/i18n'],

  css: ['~/assets/css/main.css'],

  app: {
    head: {
      htmlAttrs: { lang: 'de-CH' },
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
      link: [{ rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' }],
    },
    pageTransition: { name: 'page', mode: 'out-in' },
  },

  runtimeConfig: {
    // Where the Nuxt server reaches Laravel (never exposed to the browser).
    apiInternalUrl: 'http://127.0.0.1',
    public: {
      // Optional analytics; only loaded after consent.
      umamiSrc: '',
      umamiWebsiteId: '',
      matomoUrl: '',
      matomoSiteId: '',
    },
  },

  i18n: {
    strategy: 'no_prefix',
    defaultLocale: 'de',
    locales: [
      { code: 'de', language: 'de-CH', name: 'Deutsch', file: 'de.json' },
      { code: 'en', language: 'en', name: 'English', file: 'en.json' },
    ],
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'lang',
      redirectOn: 'root',
      fallbackLocale: 'de',
    },
  },

  vite: {
    plugins: [tailwindcss()],
    server: {
      // Behind the DDEV router / reverse proxy.
      allowedHosts: true,
    },
  },
})
