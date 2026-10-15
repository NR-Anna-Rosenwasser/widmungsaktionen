export type ConsentCategory = 'analytics' | 'marketing'

export interface TrackingProvider {
  name: string
  category: ConsentCategory
  /** Inject the provider's script. Called once, after consent. */
  load: () => void
  track: (event: string, data?: Record<string, string | number | boolean>) => void
}

const providers: TrackingProvider[] = []
const loaded = new Set<string>()
const granted = new Set<ConsentCategory>()

export function registerTrackingProvider(provider: TrackingProvider) {
  providers.push(provider)
}

export function hasTrackingProviders() {
  return providers.length > 0
}

/** Called by the consent plugin whenever consent changes. */
export function applyTrackingConsent(categories: ConsentCategory[]) {
  granted.clear()
  categories.forEach(category => granted.add(category))
  for (const provider of providers) {
    if (granted.has(provider.category) && !loaded.has(provider.name)) {
      provider.load()
      loaded.add(provider.name)
    }
  }
}

/**
 * Provider-agnostic event tracking. Events only reach providers whose
 * consent category the visitor accepted. No-op on the server.
 */
export function useTracking() {
  return {
    track(event: string, data?: Record<string, string | number | boolean>) {
      if (import.meta.server) return
      for (const provider of providers) {
        if (granted.has(provider.category) && loaded.has(provider.name)) {
          try {
            provider.track(event, data)
          }
          catch {
            // Tracking must never break the order flow.
          }
        }
      }
    },
  }
}
