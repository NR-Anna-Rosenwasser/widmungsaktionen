import * as Sentry from '@sentry/nuxt'
import { scrubBreadcrumb, scrubEvent } from './shared/sentry'

// Error tracking in the browser. Disabled unless NUXT_PUBLIC_SENTRY_DSN is set.
// No cookies, no session replay, no personal data (see shared/sentry.ts).
const { sentry } = useRuntimeConfig().public

if (sentry.dsn) {
  Sentry.init({
    dsn: sentry.dsn,
    environment: sentry.environment,
    release: sentry.release || undefined,
    sendDefaultPii: false,
    tracesSampleRate: Number(sentry.tracesSampleRate) || 0,
    beforeSend: scrubEvent,
    beforeSendTransaction: scrubEvent,
    beforeBreadcrumb: scrubBreadcrumb,
  })
}
