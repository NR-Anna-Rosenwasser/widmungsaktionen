import type { AddressSuggestion } from '#shared/types/api'

/**
 * Address suggestions for the order form (CH + LI). Laravel looks them up,
 * so the browser never talks to a third party. Browser only.
 */
export function useAddressSearch() {
  const ctx = useStorefrontContext()

  const search = (type: 'streets' | 'postcodes') => async (q: string, signal: AbortSignal) => {
    const res = await $fetch<{ data: AddressSuggestion[] }>(`/api/tenants/${ctx.value?.slug}/address/${type}`, { query: { q }, signal })
    return res.data
  }

  return { streets: search('streets'), postcodes: search('postcodes') }
}
