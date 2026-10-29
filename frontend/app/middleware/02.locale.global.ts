/**
 * Picks the language after the shop is known (01.tenant.global.ts):
 * - ?lang=xx (language link, e.g. in emails): saved, then redirected to the
 *   same URL without it
 * - else the saved choice, if the shop offers it
 * - else the shop's default
 * No browser detection, so crawlers and link previews always get the
 * default. A fallback is not saved: on the platform domain the cookie is
 * shared by all shops.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const i18n = useNuxtApp().$i18n
  const cookie = useLanguageCookie()
  const languages = useShopLanguages()

  if (!languages.value.loaded) {
    // Loaded once per visit; later pages reuse it (no new request per page).
    await useStorefront()
  }

  const { locales, defaultLocale } = languages.value

  if (typeof to.query.lang === 'string') {
    if (locales.includes(to.query.lang)) {
      cookie.value = to.query.lang
    }
    const { lang: _, ...query } = to.query
    return navigateTo({ path: to.path, query, hash: to.hash }, { redirectCode: 302, replace: true })
  }

  const wanted = cookie.value && locales.includes(cookie.value) ? cookie.value : defaultLocale
  if (wanted !== i18n.locale.value) {
    await i18n.setLocale(wanted as typeof i18n.locale.value)
  }
})
