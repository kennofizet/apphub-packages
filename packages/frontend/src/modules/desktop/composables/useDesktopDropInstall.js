import { reactive } from 'vue'
import { defaultAppStoreCatalog } from '../../app-store/data/defaultCatalog.js'
import { normalizeCatalogApp } from '../../app-store/utils/normalizeCatalogApp.js'
import { resolvePublisherTestVersion } from '../../../utils/publisherTestVersion.js'
import { resolveAppPermissions } from '../../../utils/resolveAppPermissions.js'
import { parseApiError } from '../../notifications/utils/parseApiError.js'
import { parseDropFiles } from '../utils/dropPackageParser.js'
import { simulateInstallProgress } from './simulateInstallProgress.js'

let jobSeq = 0

/**
 * Desktop drag-drop — local install, app store slug, or hosted publish (zip → draft API).
 */
export function createDesktopDropInstall(options = {}) {
  const onInstalled = options.onInstalled ?? (() => {})
  const onPersist = options.onPersist ?? (() => {})
  const onNotify = options.onNotify ?? (() => {})
  const getLabels = options.getLabels ?? (() => ({}))
  const getAppStore = options.getAppStore ?? (() => null)
  const getHostApi = options.getHostApi ?? (() => null)
  const onAfterPublish = options.onAfterPublish ?? (async () => {})
  const onPublishRegistered = options.onPublishRegistered ?? (() => {})

  const state = reactive({
    dragActive: false,
    dragDepth: 0,
    jobs: [],
  })

  function label(key, fallback = '') {
    const labels = getLabels()
    return labels[key] ?? fallback
  }

  function notify(payload) {
    onNotify(payload)
  }

  function failJob(job, err) {
    const message = parseApiError(err, label('errorGeneric', 'Something went wrong.'))
    job.status = 'error'
    job.errorMessage = message
    notify({
      type: 'error',
      title: job.name || label('errorTitle', 'App Hub'),
      message,
    })
    setTimeout(() => {
      const idx = state.jobs.findIndex((j) => j.id === job.id)
      if (idx !== -1) state.jobs.splice(idx, 1)
    }, 2000)
  }

  function canAcceptDrop(hasOpenWindows) {
    return !hasOpenWindows
  }

  function onDragEnter(event, hasOpenWindows) {
    if (!canAcceptDrop(hasOpenWindows) || !hasFiles(event)) return
    event.preventDefault()
    state.dragDepth += 1
    state.dragActive = true
  }

  function onDragLeave(event, hasOpenWindows) {
    if (!canAcceptDrop(hasOpenWindows)) return
    const root = event.currentTarget
    if (event.relatedTarget && root?.contains?.(event.relatedTarget)) return
    state.dragDepth = Math.max(0, state.dragDepth - 1)
    if (state.dragDepth === 0) state.dragActive = false
  }

  function resetDrag() {
    state.dragDepth = 0
    state.dragActive = false
  }

  function onDragOver(event, hasOpenWindows) {
    if (!canAcceptDrop(hasOpenWindows) || !hasFiles(event)) return
    event.preventDefault()
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy'
    state.dragActive = true
  }

  async function onDrop(event, hasOpenWindows, pointer) {
    state.dragDepth = 0
    state.dragActive = false
    if (!canAcceptDrop(hasOpenWindows)) return
    event.preventDefault()

    const intent = await parseDropFiles(event.dataTransfer)
    if (!intent) return

    const job = {
      id: `drop-${++jobSeq}`,
      x: pointer.x,
      y: pointer.y,
      progress: 0,
      status: 'installing',
      icon: intent.icon,
      name: intent.name,
      method: intent.method,
      intent,
    }
    state.jobs.push(job)
    await runInstall(job)
  }

  async function submitHostedPublish(job) {
    const api = getHostApi()
    if (!api?.registerApp) {
      const err = new Error('no_api')
      err.code = 'no_api'
      throw err
    }

    if (job.intent.publishMode === 'iframe') {
      const res = await api.registerApp(job.intent.manifest)
      return res?.data?.data ?? null
    }

    const body = new FormData()
    body.append('bundle', job.intent.zipFile)

    const res = await api.registerApp(body)
    return res?.data?.data ?? null
  }

  async function runInstall(job) {
    const appStore = getAppStore()
    try {
      if (job.method === 'publish') {
        await simulateInstallProgress(job, (value) => {
          job.progress = value
        })

        const registered = await submitHostedPublish(job)
        const catalogApp = normalizeCatalogApp(registered) ?? {
          slug: registered?.slug ?? job.intent.slug,
          name: registered?.name ?? job.intent.name,
          icon: registered?.icon ?? job.intent.icon,
          description: job.intent.description ?? '',
          status: registered?.status ?? 'draft',
          runtime_type: registered?.runtime_type ?? 'hosted',
          entry_url: null,
          healthcheck_url: null,
        }

        const pinVersion = resolvePublisherTestVersion(catalogApp)
        const result = await onInstalled(
          {
            slug: catalogApp.slug,
            name: catalogApp.name,
            icon: catalogApp.icon,
            description: catalogApp.description,
            status: catalogApp.status,
            runtime_type: catalogApp.runtime_type,
            version: pinVersion,
            pending_version: catalogApp.pending_version ?? null,
            catalog_version: catalogApp.version ?? null,
            rejected_version: null,
            entry_url: catalogApp.entry_url,
            healthcheck_url: catalogApp.healthcheck_url,
            permissions: mergePermissionLists(
              catalogApp.permissions,
              job.intent.permissions,
            ),
          },
          { x: job.x, y: job.y, method: 'publish' },
        )

        if (result === 'cancelled') {
          const message = label('installCancelled', 'Install cancelled.')
          job.status = 'error'
          job.errorMessage = message
          notify({
            type: 'warning',
            title: catalogApp.name,
            message,
          })
          setTimeout(() => {
            const idx = state.jobs.findIndex((j) => j.id === job.id)
            if (idx !== -1) state.jobs.splice(idx, 1)
          }, 800)
          return
        }

        onPublishRegistered(catalogApp)

        appStore?.installApp?.(catalogApp.slug)

        job.status = 'done'
        job.progress = 100
        job.name = catalogApp.name
        job.icon = catalogApp.icon
        job.publishSubmitted = true

        await onAfterPublish(catalogApp)

        notify({
          type: 'success',
          title: catalogApp.name,
          message: result === 'updated'
            ? label('publishUpgradeSuccess', 'New version submitted and pinned on your desktop.')
            : label('publishSuccess', 'Draft submitted and installed on your desktop.'),
        })

        setTimeout(() => {
          const idx = state.jobs.findIndex((j) => j.id === job.id)
          if (idx !== -1) state.jobs.splice(idx, 1)
          onPersist()
        }, 2200)
        return
      }

      await simulateInstallProgress(job, (value) => {
        job.progress = value
      })

      let app
      if (job.method === 'appstore') {
        const catalogItem = defaultAppStoreCatalog.find((a) => a.slug === job.intent.slug)
          ?? appStore?.findCatalogItem?.(job.intent.slug)
        if (catalogItem) {
          app = catalogItem
        } else {
          app = {
            slug: job.intent.slug,
            name: job.intent.name,
            icon: job.intent.icon,
            description: job.intent.description ?? '',
            permissions: job.intent.permissions ?? [],
          }
        }
      } else {
        app = {
          slug: job.intent.slug,
          name: job.intent.name,
          icon: job.intent.icon,
          description: job.intent.description ?? '',
          local: true,
          permissions: job.intent.permissions ?? [],
        }
      }

      job.status = 'done'
      job.progress = 100
      job.name = app.name
      job.icon = app.icon

      const result = await onInstalled(app, { x: job.x, y: job.y, method: job.method })
      if (result === 'cancelled') {
        const message = label('installCancelled', 'Install cancelled.')
        job.status = 'error'
        job.errorMessage = message
        notify({
          type: 'warning',
          title: app.name,
          message,
        })
        setTimeout(() => {
          const idx = state.jobs.findIndex((j) => j.id === job.id)
          if (idx !== -1) state.jobs.splice(idx, 1)
        }, 800)
        return
      }

      if (job.method === 'appstore' && app?.slug) {
        appStore?.installApp?.(app.slug)
      }

      setTimeout(() => {
        const idx = state.jobs.findIndex((j) => j.id === job.id)
        if (idx !== -1) state.jobs.splice(idx, 1)
        onPersist()
      }, 1200)
    } catch (err) {
      failJob(job, err)
    }
  }

  return {
    state,
    onDragEnter,
    onDragLeave,
    onDragOver,
    onDrop,
    canAcceptDrop,
    resetDrag,
  }
}

function hasFiles(event) {
  if (!event.dataTransfer) return false
  const types = [...event.dataTransfer.types]
  return types.includes('Files') || types.includes('application/x-moz-file')
}

/** Prefer zip-declared scopes when catalog still reflects the live (approved) version. */
function mergePermissionLists(catalogPermissions, intentPermissions) {
  const merged = [
    ...resolveAppPermissions({ permissions: catalogPermissions }),
    ...resolveAppPermissions({ permissions: intentPermissions }),
  ]
  return [...new Set(merged)]
}
