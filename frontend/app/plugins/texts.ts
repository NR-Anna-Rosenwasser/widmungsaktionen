import type { StorefrontResponse } from '#shared/types/api'

type Params = Record<string, string | number | null | undefined>

/**
 * The shop's texts, edited in the admin (Einstellungen → Texte) and delivered
 * with the storefront API as tenant.texts[locale][key]:
 *
 *   $text('order.books.title')                plain text
 *   $textHtml('order.books.lead', { max })    Markdown texts, as HTML for <RichText>
 *
 * Placeholder values are escaped. Pages without a shop (platform picker,
 * unknown shop) fall back to the i18n messages, which therefore keep the
 * nav, footer, 404 and cookie banner texts.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const i18n = nuxtApp.$i18n
  const ctx = useStorefrontContext()

  function texts(): Record<string, string> | undefined {
    const slug = ctx.value?.slug
    if (!slug) return undefined
    const key = `storefront:${slug}`
    // Same lookup as useNuxtData(), without registering a dependency per call.
    const data = (nuxtApp._asyncData[key]?.data.value ?? nuxtApp.payload.data[key]) as StorefrontResponse | undefined
    return data?.tenant.texts?.[i18n.locale.value] ?? data?.tenant.texts?.[data.tenant.default_locale]
  }

  const fill = (text: string, params: Params, escape = (value: string) => value) =>
    text.replace(/\{(\w+)\}/g, (match, name: string) => (name in params ? escape(String(params[name] ?? '')) : match))

  function text(key: string, params: Params = {}): string {
    const value = texts()?.[key]
    return value === undefined ? i18n.t(key, params) : fill(value, params)
  }

  function textHtml(key: string, params: Params = {}): string {
    const value = texts()?.[key]
    return value === undefined ? escapeHtml(i18n.t(key, params)) : fill(value, params, escapeHtml)
  }

  return { provide: { text, textHtml } }
})
