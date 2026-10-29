import * as CookieConsent from 'vanilla-cookieconsent'
import 'vanilla-cookieconsent/dist/cookieconsent.css'
import type { ConsentCategory } from '~/composables/useTracking'

declare global {
  interface Window {
    umami?: { track: (event: string, data?: Record<string, unknown>) => void }
    _paq?: unknown[][]
  }
}

function injectScript(src: string, attrs: Record<string, string> = {}) {
  const script = document.createElement('script')
  script.src = src
  script.defer = true
  Object.entries(attrs).forEach(([key, value]) => script.setAttribute(key, value))
  document.head.appendChild(script)
}

/**
 * Registers the configured analytics providers and asks for consent.
 * Add further providers (e.g. Google Analytics) here with their category.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig().public

  if (config.umamiSrc && config.umamiWebsiteId) {
    registerTrackingProvider({
      name: 'umami',
      category: 'analytics',
      load: () => injectScript(config.umamiSrc, { 'data-website-id': config.umamiWebsiteId }),
      track: (event, data) => window.umami?.track(event, data),
    })
  }

  if (config.matomoUrl && config.matomoSiteId) {
    registerTrackingProvider({
      name: 'matomo',
      category: 'analytics',
      load: () => {
        const base = config.matomoUrl.replace(/\/?$/, '/')
        window._paq = window._paq || []
        window._paq.push(['trackPageView'], ['enableLinkTracking'], ['setTrackerUrl', `${base}matomo.php`], ['setSiteId', config.matomoSiteId])
        injectScript(`${base}matomo.js`)
      },
      track: (event, data) => window._paq?.push(['trackEvent', 'widmungen', event, data ? JSON.stringify(data) : undefined]),
    })
  }

  // Without any provider there is nothing to consent to: no banner.
  if (!hasTrackingProviders()) return

  useState('consent-enabled').value = true

  const i18n = nuxtApp.$i18n as { locale: { value: string } }
  // The shop's banner texts (plugins/texts.ts), i18n messages on the picker.
  const t = (key: string) => nuxtApp.$text(key)
  const accepted = () => (['analytics', 'marketing'] as ConsentCategory[]).filter(c => CookieConsent.acceptedCategory(c))

  const translation = () => ({
    consentModal: {
      title: t('consent.title'),
      description: t('consent.description'),
      acceptAllBtn: t('consent.acceptAll'),
      acceptNecessaryBtn: t('consent.acceptNecessary'),
      showPreferencesBtn: t('consent.showPreferences'),
    },
    preferencesModal: {
      title: t('consent.preferencesTitle'),
      acceptAllBtn: t('consent.acceptAll'),
      acceptNecessaryBtn: t('consent.acceptNecessary'),
      savePreferencesBtn: t('consent.save'),
      sections: [
        { title: t('consent.necessaryTitle'), description: t('consent.necessaryText'), linkedCategory: 'necessary' },
        { title: t('consent.analyticsTitle'), description: t('consent.analyticsText'), linkedCategory: 'analytics' },
        { title: t('consent.marketingTitle'), description: t('consent.marketingText'), linkedCategory: 'marketing' },
      ],
    },
  })

  nuxtApp.hook('app:mounted', () => {
    CookieConsent.run({
      guiOptions: {
        consentModal: { layout: 'box', position: 'bottom left' },
        preferencesModal: { layout: 'box' },
      },
      categories: {
        necessary: { enabled: true, readOnly: true },
        analytics: {},
        marketing: {},
      },
      language: {
        default: i18n.locale.value,
        translations: Object.fromEntries(nuxtApp.$i18n.availableLocales.map(code => [code, async () => translation()])),
      },
      onConsent: () => applyTrackingConsent(accepted()),
      onChange: () => applyTrackingConsent(accepted()),
    })
  })

  nuxtApp.hook('i18n:localeSwitched', ({ newLocale }: { newLocale: string }) => {
    CookieConsent.setLanguage(newLocale, true)
  })

  // Page views for single-page navigation (Umami tracks them itself).
  nuxtApp.hook('page:finish', () => {
    if (CookieConsent.acceptedCategory('analytics')) {
      window._paq?.push(['setCustomUrl', window.location.href], ['trackPageView'])
    }
  })
})
