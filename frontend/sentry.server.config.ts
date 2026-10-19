import * as Sentry from '@sentry/nuxt'
import { scrubBreadcrumb, scrubEvent } from './shared/sentry'

// Error tracking for server-side rendering. Same DSN as the browser;
// disabled unless NUXT_PUBLIC_SENTRY_DSN is set.
const dsn = process.env.NUXT_PUBLIC_SENTRY_DSN

if (dsn) {
  Sentry.init({
    dsn,
    environment: process.env.NUXT_PUBLIC_SENTRY_ENVIRONMENT || 'production',
    release: process.env.NUXT_PUBLIC_SENTRY_RELEASE || undefined,
    sendDefaultPii: false,
    tracesSampleRate: Number(process.env.NUXT_PUBLIC_SENTRY_TRACES_SAMPLE_RATE) || 0,
    beforeSend: scrubEvent,
    beforeSendTransaction: scrubEvent,
    beforeBreadcrumb: scrubBreadcrumb,
  })
}
