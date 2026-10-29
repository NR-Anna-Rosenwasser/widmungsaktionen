import tailwindcss from "@tailwindcss/vite";

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: "2025-07-15",
  devtools: { enabled: true },

  modules: ["@nuxtjs/i18n", "@sentry/nuxt/module"],

  css: ["~/assets/css/main.css"],

  app: {
    head: {
      meta: [
        { name: "viewport", content: "width=device-width, initial-scale=1" },
      ],
      link: [{ rel: "icon", href: "/favicon.svg", type: "image/svg+xml" }],
    },
    pageTransition: { name: "page", mode: "out-in" },
  },

  runtimeConfig: {
    // Where the Nuxt server reaches Laravel (never exposed to the browser).
    apiInternalUrl: "http://127.0.0.1",
    public: {
      // Optional analytics; only loaded after consent.
      umamiSrc: "",
      umamiWebsiteId: "",
      matomoUrl: "",
      matomoSiteId: "",
      // Error tracking (sentry.client.config.ts / sentry.server.config.ts); empty DSN = off.
      sentry: {
        dsn: "",
        environment: "production",
        release: "",
        tracesSampleRate: 0,
      },
    },
  },

  // Source maps are generated for Sentry but not served to visitors.
  sourcemap: { client: "hidden" },

  // Uploads source maps during `nuxt build` when SENTRY_AUTH_TOKEN is set (release CI).
  sentry: {
    org: process.env.SENTRY_ORG,
    project: process.env.SENTRY_FRONTEND_PROJECT,
    authToken: process.env.SENTRY_AUTH_TOKEN,
    telemetry: false,
    sourcemaps: {
      filesToDeleteAfterUpload: [".output/public/**/*.map"],
    },
  },

  i18n: {
    strategy: "no_prefix",
    // Each shop offers a subset and picks its own default (API: tenant.locales,
    // tenant.default_locale); middleware/02.locale.global.ts applies it.
    defaultLocale: "de",
    locales: [
      { code: "de", language: "de-CH", name: "Deutsch", file: "de.json" },
      { code: "fr", language: "fr-CH", name: "Français", file: "fr.json" },
      { code: "it", language: "it-CH", name: "Italiano", file: "it.json" },
      { code: "en", language: "en", name: "English", file: "en.json" },
    ],
    // Visitors without a choice always get the shop's default, crawlers too.
    detectBrowserLanguage: false,
  },

  vite: {
    plugins: [tailwindcss()],
    server: {
      // Behind the DDEV router / reverse proxy.
      allowedHosts: true,
    },
  },
});
