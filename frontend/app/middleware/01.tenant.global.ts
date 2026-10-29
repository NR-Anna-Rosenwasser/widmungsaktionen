import type { ResolveResponse } from '#shared/types/api'

/**
 * Works out which tenant a request belongs to:
 * - custom domain → that tenant, no slug in the path
 * - platform domain with /<slug> → that tenant
 * - bare platform domain → redirect to the only tenant, or show the picker
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const ctx = useStorefrontContext()
  const host = useRequestURL().host

  let resolve: ResolveResponse
  if (ctx.value?.host === host) {
    resolve = { mode: ctx.value.mode, tenant: ctx.value.resolved }
  }
  else {
    resolve = await useApi()<ResolveResponse>('/api/resolve', { query: { host } })
  }

  const param = typeof to.params.tenant === 'string' ? to.params.tenant : null

  if (resolve.mode === 'custom_domain') {
    if (param) {
      throw createError({ statusCode: 404, statusMessage: 'Not found' })
    }
    ctx.value = { host, ...resolve, resolved: resolve.tenant, slug: resolve.tenant, base: '' }
    return
  }

  if (!param) {
    if (resolve.mode === 'redirect') {
      return navigateTo(`/${resolve.tenant}${to.path === '/' ? '' : to.path}`, { redirectCode: 302 })
    }
    if (to.path !== '/') {
      // e.g. /bestellen on the platform domain without a tenant
      return navigateTo('/', { redirectCode: 302 })
    }
  }

  ctx.value = { host, mode: resolve.mode, resolved: resolve.tenant, slug: param, base: param ? `/${param}` : '' }
})
