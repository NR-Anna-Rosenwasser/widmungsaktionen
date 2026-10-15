import type { StorefrontResponse } from '#shared/types/api'

export interface StorefrontContext {
  host: string
  mode: 'custom_domain' | 'redirect' | 'picker'
  /** Tenant the host resolves to (custom domain, or the only tenant). */
  resolved: string | null
  /** Tenant slug of the current page, null on the platform picker. */
  slug: string | null
  /** Path prefix for storefront links: '' on custom domains, '/<slug>' otherwise. */
  base: string
}

export function useStorefrontContext() {
  return useState<StorefrontContext | null>('storefront-context', () => null)
}

/** Build a storefront path that works on custom domains and the platform domain. */
export function useStorefrontPath() {
  const ctx = useStorefrontContext()
  return (path = '/') => {
    const base = ctx.value?.base ?? ''
    const clean = path === '/' ? '' : path
    return `${base}${clean}` || '/'
  }
}

export function useStorefront() {
  const ctx = useStorefrontContext()
  const api = useApi()

  return useAsyncData(
    () => `storefront:${ctx.value?.slug}`,
    () => api<StorefrontResponse>(`/api/tenants/${ctx.value!.slug}`),
    { dedupe: 'defer' },
  )
}
