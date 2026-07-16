import { ref } from 'vue'
import { clampPointToLayer, snapPoint } from '../utils/desktopGrid.js'
import { findAppsAtCell, moveAppsToCell, previewGroupAtCell } from '../utils/desktopIconGroups.js'

const DRAG_THRESHOLD = 4
const HOLD_MS = 380

/**
 * Pointer drag for desktop app icons with iPhone-style grouping.
 * Single icons drag immediately; group icons need a brief hold (tap opens folder).
 */
export function useDesktopIconDrag(options) {
  const drag = ref(null)
  const dropTarget = ref(null)
  const lastWasDrag = ref(false)

  let holdTimer = null
  let activePointerId = null

  function getLayerRect() {
    return options.getLayerEl()?.getBoundingClientRect() ?? null
  }

  function resolvePosition(x, y) {
    const layer = options.getLayerEl()
    if (!layer) return { x, y }
    const clamped = clampPointToLayer(x, y, layer.clientWidth, layer.clientHeight)
    return snapPoint(clamped.x, clamped.y, options.getSnapToGrid())
  }

  function getApps(ids) {
    return ids.map((id) => options.findApp(id)).filter(Boolean)
  }

  function ensurePlaced(app, event) {
    if (app.desktopX != null && app.desktopY != null) return
    const layerRect = getLayerRect()
    const el = event.currentTarget
    if (!layerRect || !el) return
    const rect = el.getBoundingClientRect()
    const pos = resolvePosition(rect.left - layerRect.left, rect.top - layerRect.top)
    app.desktopX = pos.x
    app.desktopY = pos.y
  }

  function clearHoldTimer() {
    if (holdTimer) {
      clearTimeout(holdTimer)
      holdTimer = null
    }
  }

  function removeListeners() {
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', onPointerUp)
    window.removeEventListener('pointercancel', onPointerCancel)
  }

  function updateDropTarget(pos, draggedApps) {
    if (!drag.value?.moved) {
      dropTarget.value = null
      return
    }

    const allApps = options.getDesktopApps?.() ?? []
    const draggedIds = draggedApps.map((a) => a.id)
    const atCell = findAppsAtCell(allApps, pos.x, pos.y, draggedIds)

    if (atCell.length > 0) {
      const preview = previewGroupAtCell(allApps, draggedApps, pos.x, pos.y)
      dropTarget.value = { x: pos.x, y: pos.y, apps: preview, merging: true }
      return
    }

    if (draggedApps.length >= 2) {
      dropTarget.value = { x: pos.x, y: pos.y, apps: draggedApps }
      return
    }

    dropTarget.value = null
  }

  /**
   * @param {object|object[]} appOrApps - single app or all apps in a group
   * @param {object} meta - { mode: 'single'|'group'|'folder', onTap?: fn }
   */
  function onPointerDown(appOrApps, event, meta = {}) {
    const apps = Array.isArray(appOrApps) ? appOrApps : [appOrApps]
    const primary = apps[0]
    if (!primary || event.button !== 0) return

    for (const app of apps) ensurePlaced(app, event)

    const ids = apps.map((a) => a.id)
    const mode = meta.mode ?? 'single'
    const requiresHold = meta.requiresHold ?? mode === 'group'

    drag.value = {
      ids,
      mode,
      requiresHold,
      startX: event.clientX,
      startY: event.clientY,
      anchorX: primary.desktopX ?? 0,
      anchorY: primary.desktopY ?? 0,
      moved: false,
      ready: !requiresHold,
      onTap: meta.onTap ?? null,
    }
    activePointerId = event.pointerId

    if (requiresHold) {
      holdTimer = setTimeout(() => {
        if (drag.value) drag.value.ready = true
      }, HOLD_MS)
    }

    event.currentTarget?.setPointerCapture?.(event.pointerId)
    window.addEventListener('pointermove', onPointerMove)
    window.addEventListener('pointerup', onPointerUp)
    window.addEventListener('pointercancel', onPointerCancel)
  }

  function onPointerMove(event) {
    if (event.pointerId !== activePointerId) return
    if (!drag.value?.ready) return
    event.preventDefault()

    const dx = event.clientX - drag.value.startX
    const dy = event.clientY - drag.value.startY
    const dist = Math.abs(dx) + Math.abs(dy)

    if (!drag.value.moved && dist < DRAG_THRESHOLD) return

    drag.value.moved = true
    const layerRect = getLayerRect()
    if (!layerRect) return

    const rawX = drag.value.anchorX + dx
    const rawY = drag.value.anchorY + dy
    const fallbackPos = resolvePosition(rawX, rawY)
    const pos = options.resolvePointerPosition?.(event, fallbackPos) ?? fallbackPos
    const draggedApps = getApps(drag.value.ids)
    const externalDrop = options.resolveExternalDrop?.(event, draggedApps, drag.value) ?? null
    drag.value.externalDrop = externalDrop

    if (externalDrop) {
      for (const app of draggedApps) {
        app.desktopX = drag.value.anchorX
        app.desktopY = drag.value.anchorY
      }
      dropTarget.value = null
      return
    }

    for (const app of draggedApps) {
      app.desktopX = pos.x
      app.desktopY = pos.y
    }

    updateDropTarget(pos, draggedApps)
  }

  function finalizeDrop() {
    if (!drag.value?.moved) return

    const draggedApps = getApps(drag.value.ids)
    if (!draggedApps.length) return

    const primary = draggedApps[0]
    const pos = resolvePosition(primary.desktopX ?? 0, primary.desktopY ?? 0)
    moveAppsToCell(draggedApps, pos.x, pos.y)
  }

  function onPointerUp(event) {
    if (event?.pointerId !== activePointerId) return
    clearHoldTimer()
    removeListeners()

    const session = drag.value
    const wasDrag = session?.moved
    lastWasDrag.value = !!wasDrag

    if (wasDrag && session.externalDrop) {
      options.onExternalDrop?.(session.externalDrop, getApps(session.ids), session)
    } else if (wasDrag) {
      finalizeDrop()
      const draggedApps = getApps(session.ids)
      const primary = draggedApps[0]
      options.onMoved?.({
        fromCell: { x: session.anchorX, y: session.anchorY },
        toCell: primary
          ? { x: primary.desktopX, y: primary.desktopY }
          : null,
      })
    } else if (session?.onTap) {
      session.onTap()
    }

    drag.value = null
    dropTarget.value = null
    activePointerId = null
    options.onDragEnd?.()
  }

  function onPointerCancel(event) {
    if (event?.pointerId !== activePointerId) return
    clearHoldTimer()
    removeListeners()
    drag.value = null
    dropTarget.value = null
    activePointerId = null
    options.onDragEnd?.()
  }

  function isDragging(appId) {
    return drag.value?.ids?.includes(appId) && drag.value.moved
  }

  function isHolding(appId) {
    return drag.value?.ids?.includes(appId) && drag.value.requiresHold && drag.value.ready && !drag.value.moved
  }

  function cleanup() {
    clearHoldTimer()
    removeListeners()
    drag.value = null
    dropTarget.value = null
    activePointerId = null
    options.onDragEnd?.()
  }

  return {
    drag,
    dropTarget,
    lastWasDrag,
    onPointerDown,
    isDragging,
    isHolding,
    cleanup,
    resolvePosition,
  }
}
