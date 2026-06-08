/** Shared with other KNF packages on the same host (e.g. workpoint). */
export const ZONE_STORAGE_KEY = 'selected_zone'

export function parseZonesFromResponse(resp) {
  const data = resp?.data?.datas ?? resp?.data?.data ?? resp?.data ?? {}
  if (Array.isArray(data.zones)) return data.zones
  if (Array.isArray(data)) return data
  return []
}

export function parseZonesMeta(resp) {
  const data = resp?.data?.datas ?? resp?.data?.data ?? resp?.data ?? {}
  return {
    timezone: data.timezone ?? null,
    isManager: !!data.is_manager,
  }
}

export function loadStoredZone() {
  try {
    const raw = localStorage.getItem(ZONE_STORAGE_KEY)
    if (!raw) return null
    const zone = JSON.parse(raw)
    if (zone && zone.id != null) return zone
  } catch {
    /* ignore */
  }
  return null
}

export function saveStoredZone(zone) {
  if (!zone?.id) return
  try {
    localStorage.setItem(ZONE_STORAGE_KEY, JSON.stringify({ id: zone.id, name: zone.name ?? '' }))
  } catch {
    /* ignore */
  }
}
