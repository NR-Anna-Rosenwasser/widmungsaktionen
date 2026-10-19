import type { Breadcrumb, ErrorEvent, TransactionEvent } from '@sentry/core'

/**
 * Strips customer data from everything sent to Sentry: email addresses and
 * order tokens (they open an order with name and address). Used by the
 * client and server config; request bodies are never attached.
 */
export function scrubText(text: string): string {
  return text
    .replace(/[^\s@<>"'(),;:]+@[^\s@<>"'(),;:]+\.[a-z]{2,}/gi, '[email]')
    .replace(/((?:orders|bestellung)\/)[A-Za-z0-9]{20,}/g, '$1[token]')
    .replace(/([?&]t=)[A-Za-z0-9]{20,}/g, '$1[token]')
}

function scrub<T>(value: T): T {
  if (typeof value === 'string') return scrubText(value) as T
  if (Array.isArray(value)) return value.map(scrub) as T
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value).map(([key, v]) => [key, scrub(v)])) as T
  }
  return value
}

export function scrubEvent<T extends ErrorEvent | TransactionEvent>(event: T): T {
  if (event.request) {
    delete event.request.data
    delete event.request.cookies
    event.request = scrub(event.request)
  }
  if (event.message) event.message = scrubText(event.message)
  if (event.transaction) event.transaction = scrubText(event.transaction)
  for (const exception of event.exception?.values ?? []) {
    if (exception.value) exception.value = scrubText(exception.value)
  }
  if (event.breadcrumbs) event.breadcrumbs = event.breadcrumbs.map(scrubBreadcrumb)
  if (event.extra) event.extra = scrub(event.extra)
  if (event.contexts) event.contexts = scrub(event.contexts)
  if (event.spans) event.spans = scrub(event.spans)
  return event
}

export function scrubBreadcrumb(breadcrumb: Breadcrumb): Breadcrumb {
  return scrub(breadcrumb)
}
