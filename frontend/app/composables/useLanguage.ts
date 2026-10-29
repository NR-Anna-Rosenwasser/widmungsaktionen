import type { StorefrontResponse } from '#shared/types/api'

/** The visitor's explicit language choice (switcher or ?lang= link). */
export function useLanguageCookie() {
  return useCookie<string | null>('lang', { maxAge: 60 * 60 * 24 * 365, sameSite: 'lax', path: '/' })
}

/**
 * Languages of the current shop and its default; every language on the
 * platform picker. Reads the storefront data the page already loaded.
 */
export function useShopLanguages() {
  const nuxtApp = useNuxtApp()
  const ctx = useStorefrontContext()

  return computed(() => {
    const key = `storefront:${ctx.value?.slug}`
    const tenant = ctx.value?.slug
      ? ((nuxtApp._asyncData[key]?.data.value ?? nuxtApp.payload.data[key]) as StorefrontResponse | undefined)?.tenant
      : undefined

    return {
      /** False until the shop's data is loaded (first page of a visit). */
      loaded: !ctx.value?.slug || !!tenant,
      locales: tenant?.locales ?? nuxtApp.$i18n.availableLocales,
      defaultLocale: tenant?.default_locale ?? 'de',
    }
  })
}
