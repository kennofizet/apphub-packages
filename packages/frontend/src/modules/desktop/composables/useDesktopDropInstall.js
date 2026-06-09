import { reactive } from 'vue'
import { defaultAppStoreCatalog } from '../../app-store/data/defaultCatalog.js'
import { parseDropFiles } from '../utils/dropPackageParser.js'
import { simulateInstallProgress } from './simulateInstallProgress.js'

let jobSeq = 0

/**
 * Desktop drag-drop install — highlight zone, progress badge, app store vs local.
 */
export function createDesktopDropInstall(options = {}) {
  const onInstalled = options.onInstalled ?? (() => {})
  const onPersist = options.onPersist ?? (() => {})
  const getAppStore = options.getAppStore ?? (() => null)
  const resolveDuplicate = options.resolveDuplicate ?? (async () => 'keep')

  const state = reactive({
    dragActive: false,
    dragDepth: 0,
    jobs: [],
  })

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

  async function runInstall(job) {
    const appStore = getAppStore()
    try {
      await simulateInstallProgress(job, (value) => {
        job.progress = value
      })

      let app
      if (job.method === 'appstore') {
        const catalogItem = defaultAppStoreCatalog.find((a) => a.slug === job.intent.slug)
          ?? appStore?.findCatalogItem?.(job.intent.slug)
        if (catalogItem) {
          appStore?.installApp?.(catalogItem.slug)
          app = catalogItem
        } else {
          app = {
            slug: job.intent.slug,
            name: job.intent.name,
            icon: job.intent.icon,
            description: job.intent.description ?? '',
          }
        }
      } else {
        app = {
          slug: job.intent.slug,
          name: job.intent.name,
          icon: job.intent.icon,
          description: job.intent.description ?? '',
          local: true,
        }
      }

      job.status = 'done'
      job.progress = 100
      job.name = app.name
      job.icon = app.icon

      const result = await onInstalled(app, { x: job.x, y: job.y, method: job.method })
      if (result === 'cancelled') {
        job.status = 'error'
        setTimeout(() => {
          const idx = state.jobs.findIndex((j) => j.id === job.id)
          if (idx !== -1) state.jobs.splice(idx, 1)
        }, 800)
        return
      }

      setTimeout(() => {
        const idx = state.jobs.findIndex((j) => j.id === job.id)
        if (idx !== -1) state.jobs.splice(idx, 1)
        onPersist()
      }, 1200)
    } catch {
      job.status = 'error'
      setTimeout(() => {
        const idx = state.jobs.findIndex((j) => j.id === job.id)
        if (idx !== -1) state.jobs.splice(idx, 1)
      }, 2000)
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
