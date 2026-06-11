import { computed, reactive } from 'vue'
import {
  loadStoredZone,
  parseZonesFromResponse,
  parseZonesMeta,
  saveStoredZone,
} from '../utils/zoneContext.js'

function parseUserFromBootstrap(resp) {
  const data = resp?.data?.data ?? resp?.data?.datas ?? resp?.data ?? {}
  const user = data.user
  if (!user || user.id == null) return null
  return {
    id: user.id,
    name: user.name ?? String(user.id),
  }
}

/**
 * Hub session — user (apphub bootstrap) + zones (packages-core).
 */
export function createZoneContextState(getCoreApi, getHostApi, hooks = {}) {
  const state = reactive({
    user: { id: null, name: null },
    zones: [],
    selectedZoneId: null,
    viewAllZones: false,
    loading: false,
    error: '',
    timezone: null,
    isManager: false,
    authOk: false,
  })

  const selectedZone = computed(() =>
    state.zones.find((z) => z.id === state.selectedZoneId) ?? null,
  )

  const activeZoneIds = computed(() => {
    if (state.viewAllZones) {
      return state.zones.map((z) => z.id).filter((id) => id != null)
    }
    return state.selectedZoneId != null ? [state.selectedZoneId] : []
  })

  function selectZone(zoneOrId) {
    const id = typeof zoneOrId === 'object' ? zoneOrId?.id : zoneOrId
    const zone = state.zones.find((z) => z.id === id)
    if (!zone) return
    state.selectedZoneId = zone.id
    state.viewAllZones = false
    saveStoredZone(zone)
  }

  function setViewAllZones(enabled) {
    state.viewAllZones = !!enabled
    if (!state.viewAllZones && state.selectedZoneId == null && state.zones.length) {
      selectZone(state.zones[0])
    }
  }

  function resolveInitialZone(list) {
    const stored = loadStoredZone()
    if (stored?.id && list.some((z) => z.id === stored.id)) {
      state.selectedZoneId = stored.id
      return
    }
    if (list.length === 1) {
      selectZone(list[0])
    } else if (list.length > 1 && state.selectedZoneId == null) {
      selectZone(list[0])
    }
  }

  async function refreshUser() {
    if (hooks.ensureBootstrapSession) {
      await hooks.ensureBootstrapSession()
      return
    }

    const hostApi = getHostApi?.()
    if (!hostApi?.bootstrap) return

    try {
      const res = await hostApi.bootstrap()
      hooks.onBootstrap?.(res)
      const user = parseUserFromBootstrap(res)
      if (user) {
        state.user.id = user.id
        state.user.name = user.name
      }
    } catch {
      /* keep previous user */
    }
  }

  async function refreshZones() {
    const api = getCoreApi?.()
    if (!api?.getPlayerZones) {
      state.error = 'no_core_api'
      state.zones = []
      return
    }

    state.error = ''
    try {
      if (api.authCheck) {
        try {
          const authRes = await api.authCheck()
          state.authOk = authRes?.data?.success !== false
        } catch {
          state.authOk = false
        }
      }

      const resp = await api.getPlayerZones()
      const list = parseZonesFromResponse(resp)
      const meta = parseZonesMeta(resp)
      state.zones = list
      state.timezone = meta.timezone
      state.isManager = meta.isManager
      resolveInitialZone(list)
    } catch {
      state.error = 'load_failed'
      state.zones = []
    }
  }

  async function refresh(options = {}) {
    state.loading = true
    try {
      if (options.skipBootstrap) {
        await refreshZones()
      } else {
        await Promise.all([refreshUser(), refreshZones()])
      }
    } finally {
      state.loading = false
    }
  }

  function getZoneHeaderId() {
    if (state.viewAllZones || state.selectedZoneId == null) return null
    return String(state.selectedZoneId)
  }

  return {
    state,
    selectedZone,
    activeZoneIds,
    selectZone,
    setViewAllZones,
    refresh,
    getZoneHeaderId,
  }
}
