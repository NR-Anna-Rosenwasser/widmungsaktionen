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
