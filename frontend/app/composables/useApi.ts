import * as Sentry from '@sentry/nuxt'

/**
 * Fetch for the Laravel API. In the browser, /api/ is same-origin (routed to
 * Laravel by the web server, so no CORS). During SSR, Nuxt calls Laravel
 * directly and forwards the visitor's IP for rate limiting.
 */
export function useApi() {
  if (import.meta.client) {
    return $fetch
  }

  const { apiInternalUrl } = useRuntimeConfig()
  // Set by the reverse proxy; Laravel trusts it from private networks only.
  const forwardedFor = useRequestHeaders(['x-forwarded-for'])['x-forwarded-for']

  return $fetch.create({
    baseURL: apiInternalUrl,
    headers: {
      accept: 'application/json',
      ...(forwardedFor ? { 'x-forwarded-for': forwardedFor } : {}),
    },
  })
}

export function apiErrorMessage(error: unknown, fallback: string): string {
  const data = (error as { data?: { message?: string } })?.data
  return data?.message || fallback
}

export function apiValidationErrors(error: unknown): Record<string, string[]> {
  const data = (error as { data?: { errors?: Record<string, string[]> } })?.data
  return data?.errors ?? {}
}

/**
 * Sends a failed API call to Sentry (no-op without DSN). Validation errors
 * (422) are reported as warnings with the field names only: the form checks
 * input first, so the server rejecting it points at a mismatch or a bug.
 */
export function reportApiError(error: unknown, area: string, context: Record<string, string> = {}) {
  const status = (error as { statusCode?: number })?.statusCode
  const fields = Object.keys(apiValidationErrors(error)).sort()

  Sentry.captureException(error, {
    level: status === 422 ? 'warning' : 'error',
    tags: { area, status: String(status ?? 'network'), ...context },
    extra: { fields },
    fingerprint: ['api', area, String(status ?? 'network'), ...Object.values(context), ...fields],
  })
}
