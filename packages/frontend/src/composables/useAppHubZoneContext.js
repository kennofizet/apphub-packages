import { inject } from 'vue'

export const APPHUB_ZONE_CONTEXT_KEY = 'apphubZoneContext'

export function useAppHubZoneContext() {
  const ctx = inject(APPHUB_ZONE_CONTEXT_KEY, null)
  if (!ctx) {
    throw new Error('useAppHubZoneContext() requires installAppHubModule()')
  }
  return ctx
}
