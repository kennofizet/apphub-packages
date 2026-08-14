<script setup>
/**
 * Themed desktop cursor — spring follow, trail, and collision FX with wallpaper orbs / background.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  root: { type: Object, default: null },
  skin: { type: String, default: 'classic' },
  moving: { type: Boolean, default: false },
})

const visible = ref(false)
const mode = ref('idle') // idle | hover | move | action | collide
const trail = ref([])
const impacts = ref([])

const displayX = ref(0)
const displayY = ref(0)
const targetX = ref(0)
const targetY = ref(0)
const velX = ref(0)
const velY = ref(0)
const speed = ref(0)

let actionTimer = null
let rootEl = null
let rafId = 0
let lastTs = 0
let lastTrailAt = 0
let lastBgSplashAt = 0
let magnetEl = null
/** @type {Set<string>} */
const orbHits = new Set()
/** @type {Map<string, number>} */
const orbGrazeAt = new Map()
let impactSeq = 0

const INTERACTIVE =
  'button, a, [role="button"], .apphub-desktop__icon, .apphub-win__titlebar, .apphub-start-btn, .apphub-taskbar-pins__btn, input, select, textarea'

const styleVars = computed(() => ({
  '--ah-cursor-x': `${displayX.value}px`,
  '--ah-cursor-y': `${displayY.value}px`,
  '--ah-cursor-vx': `${Math.max(-1.2, Math.min(1.2, velX.value / 900))}`,
  '--ah-cursor-vy': `${Math.max(-1.2, Math.min(1.2, velY.value / 900))}`,
  '--ah-cursor-speed': `${Math.min(1.6, speed.value / 1200)}`,
  '--ah-cursor-angle': `${Math.atan2(velY.value, velX.value) * (180 / Math.PI)}deg`,
}))

function setMode(next) {
  if (props.moving) {
    mode.value = 'move'
    return
  }
  mode.value = next
}

function pushTrail(nx, ny, sp) {
  const now = performance.now()
  if (now - lastTrailAt < 10) return
  lastTrailAt = now
  const id = `${Math.round(nx)}-${Math.round(ny)}-${now}`
  const scale = 0.25 + Math.min(1, sp / 1200) * 0.7
  trail.value = [...trail.value.slice(-18), { x: nx, y: ny, id, scale }]
}

function spawnImpact(x, y, kind, strength = 1) {
  const id = `imp-${++impactSeq}-${Math.round(x)}-${Math.round(y)}`
  impacts.value = [
    ...impacts.value.slice(-8),
    { id, x, y, kind, strength: Math.max(0.45, Math.min(1.4, strength)) },
  ]
  window.setTimeout(() => {
    impacts.value = impacts.value.filter((item) => item.id !== id)
  }, 620)
  if (mode.value !== 'action' && !props.moving) {
    mode.value = 'collide'
    window.setTimeout(() => {
      if (mode.value === 'collide') mode.value = 'idle'
    }, 220)
  }
}

function checkOrbCollisions() {
  if (!rootEl || !visible.value) return
  const orbs = rootEl.querySelectorAll('.apphub-desktop__fx-orb')
  if (!orbs.length) return

  const rootRect = rootEl.getBoundingClientRect()
  // Chase cursor (visible) + mouse target — hits feel fair while the spring lags
  const probes = [
    { x: displayX.value, y: displayY.value },
    { x: targetX.value, y: targetY.value },
  ]
  const cursorR = 18
  const active = new Set()
  const now = performance.now()

  orbs.forEach((orb, index) => {
    const r = orb.getBoundingClientRect()
    const ox = r.left + r.width / 2 - rootRect.left
    const oy = r.top + r.height / 2 - rootRect.top
    // Blurred glow — collide with inner core (not full 48vmin box)
    const orbR = Math.max(48, Math.max(r.width, r.height) * 0.26)
    let dist = Infinity
    let hitX = displayX.value
    let hitY = displayY.value
    for (const p of probes) {
      const d = Math.hypot(p.x - ox, p.y - oy)
      if (d < dist) {
        dist = d
        hitX = p.x
        hitY = p.y
      }
    }
    const key = orb.classList?.contains('apphub-desktop__fx-orb--a')
      ? 'a'
      : orb.classList?.contains('apphub-desktop__fx-orb--b')
        ? 'b'
        : orb.classList?.contains('apphub-desktop__fx-orb--c')
          ? 'c'
          : `orb-${index}`

    if (dist >= cursorR + orbR) return

    active.add(key)
    const entered = !orbHits.has(key)
    const lastGraze = orbGrazeAt.get(key) || 0

    if (entered) {
      spawnImpact(ox, oy, 'orb', 1 + Math.min(0.4, speed.value / 1200))
      spawnImpact(hitX, hitY, 'wake', 0.75)
      orbGrazeAt.set(key, now)
      return
    }

    // Keep sparks while cutting through the glow
    if (speed.value > 90 && now - lastGraze > 140) {
      orbGrazeAt.set(key, now)
      const t = Math.min(1, dist / (cursorR + orbR))
      const gx = ox + (hitX - ox) * (0.3 + t * 0.5)
      const gy = oy + (hitY - oy) * (0.3 + t * 0.5)
      spawnImpact(gx, gy, 'orb', 0.5 + Math.min(0.6, speed.value / 1500))
    }
  })

  for (const key of [...orbHits]) {
    if (!active.has(key)) {
      orbHits.delete(key)
      orbGrazeAt.delete(key)
    }
  }
  for (const key of active) orbHits.add(key)
}

function maybeBackgroundSplash() {
  if (!visible.value || props.moving || !rootEl) return
  if (mode.value === 'hover' || mode.value === 'action') return
  const now = performance.now()
  // Wake when chase cursor skims empty wallpaper / FX layer
  if (speed.value < 140) return
  if (now - lastBgSplashAt < 85) return

  const rootRect = rootEl.getBoundingClientRect()
  const el = document.elementFromPoint?.(rootRect.left + displayX.value, rootRect.top + displayY.value)
  const onUi =
    el?.closest?.(INTERACTIVE) ||
    el?.closest?.('.apphub-win, .apphub-start, .apphub-desktop__taskbar, .apphub-start-menu')
  if (onUi) return

  lastBgSplashAt = now
  spawnImpact(displayX.value, displayY.value, 'wake', 0.4 + Math.min(0.75, speed.value / 1400))
}

function tick(ts) {
  if (!lastTs) lastTs = ts
  const dt = Math.min(0.032, (ts - lastTs) / 1000)
  lastTs = ts

  const stiffness = props.moving ? 52 : mode.value === 'hover' ? 32 : 22
  const damping = props.moving ? 14 : mode.value === 'hover' ? 15 : 17
  const dx = targetX.value - displayX.value
  const dy = targetY.value - displayY.value
  velX.value += dx * stiffness * dt
  velY.value += dy * stiffness * dt
  velX.value *= Math.exp(-damping * dt)
  velY.value *= Math.exp(-damping * dt)
  displayX.value += velX.value * dt
  displayY.value += velY.value * dt
  speed.value = Math.hypot(velX.value, velY.value)

  if (visible.value && (speed.value > 28 || props.moving || Math.hypot(dx, dy) > 0.5)) {
    pushTrail(displayX.value, displayY.value, speed.value)
  } else if (trail.value.length && speed.value < 18) {
    if (performance.now() - lastTrailAt > 28) {
      trail.value = trail.value.slice(1)
      lastTrailAt = performance.now()
    }
  }

  checkOrbCollisions()
  maybeBackgroundSplash()

  rafId = requestAnimationFrame(tick)
}

function magnetOffset(e) {
  magnetEl = e.target?.closest?.(INTERACTIVE) || null
  if (!magnetEl || !rootEl) return { x: 0, y: 0 }
  const rootRect = rootEl.getBoundingClientRect()
  const r = magnetEl.getBoundingClientRect()
  const cx = r.left + r.width / 2 - rootRect.left
  const cy = r.top + r.height / 2 - rootRect.top
  const px = e.clientX - rootRect.left
  const py = e.clientY - rootRect.top
  const pull = 0.18
  return { x: (cx - px) * pull, y: (cy - py) * pull }
}

function onPointerMove(e) {
  const rect = rootEl?.getBoundingClientRect?.()
  if (!rect) return
  const mag = props.moving ? { x: 0, y: 0 } : magnetOffset(e)
  targetX.value = e.clientX - rect.left + mag.x
  targetY.value = e.clientY - rect.top + mag.y
  if (!visible.value) {
    visible.value = true
    displayX.value = targetX.value
    displayY.value = targetY.value
    velX.value = 0
    velY.value = 0
  }

  if (props.moving) {
    mode.value = 'move'
    return
  }
  const over = e.target?.closest?.(INTERACTIVE)
  setMode(over ? 'hover' : mode.value === 'collide' ? 'collide' : 'idle')
}

function onPointerEnter() {
  visible.value = true
}

function onPointerLeave() {
  visible.value = false
  if (!props.moving) mode.value = 'idle'
  trail.value = []
  impacts.value = []
  orbHits.clear()
  orbGrazeAt.clear()
  magnetEl = null
}

function onPointerDown(e) {
  if (e.button !== 0) return
  mode.value = props.moving ? 'move' : 'action'
  spawnImpact(displayX.value, displayY.value, 'tap', 1)
  if (actionTimer) clearTimeout(actionTimer)
  actionTimer = setTimeout(() => {
    if (props.moving) {
      mode.value = 'move'
      return
    }
    const over = e.target?.closest?.(INTERACTIVE)
    mode.value = over ? 'hover' : 'idle'
  }, 320)
}

function bind(el) {
  unbind()
  rootEl = el
  if (!el) return
  el.addEventListener('pointermove', onPointerMove, { passive: true })
  el.addEventListener('pointerenter', onPointerEnter, { passive: true })
  el.addEventListener('pointerleave', onPointerLeave, { passive: true })
  el.addEventListener('pointerdown', onPointerDown, { passive: true })
  lastTs = 0
  if (!rafId) rafId = requestAnimationFrame(tick)
}

function unbind() {
  if (rootEl) {
    rootEl.removeEventListener('pointermove', onPointerMove)
    rootEl.removeEventListener('pointerenter', onPointerEnter)
    rootEl.removeEventListener('pointerleave', onPointerLeave)
    rootEl.removeEventListener('pointerdown', onPointerDown)
  }
  rootEl = null
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = 0
  }
  lastTs = 0
  orbHits.clear()
  orbGrazeAt.clear()
}

watch(
  () => props.root,
  (el) => bind(el),
  { immediate: true },
)

watch(
  () => props.moving,
  (m) => {
    if (m) mode.value = 'move'
    else if (mode.value === 'move') mode.value = 'idle'
  },
)

onMounted(() => bind(props.root))
onBeforeUnmount(() => {
  unbind()
  if (actionTimer) clearTimeout(actionTimer)
})
</script>

<template>
  <div
    class="apphub-cursor-fx"
    :class="[
      `apphub-cursor-fx--${skin || 'classic'}`,
      `apphub-cursor-fx--${mode}`,
      { 'apphub-cursor-fx--visible': visible },
    ]"
    :style="styleVars"
    aria-hidden="true"
  >
    <span
      v-for="(hit, i) in impacts"
      :key="hit.id"
      class="apphub-cursor-fx__impact"
      :class="`apphub-cursor-fx__impact--${hit.kind}`"
      :style="{
        transform: `translate3d(${hit.x}px, ${hit.y}px, 0) translate(-50%, -50%) scale(${hit.strength || 1})`,
        zIndex: 1 + i,
      }"
    >
      <i class="apphub-cursor-fx__impact-ring" />
      <i class="apphub-cursor-fx__impact-ring apphub-cursor-fx__impact-ring--late" />
      <i class="apphub-cursor-fx__impact-core" />
      <i class="apphub-cursor-fx__impact-spark apphub-cursor-fx__impact-spark--1" />
      <i class="apphub-cursor-fx__impact-spark apphub-cursor-fx__impact-spark--2" />
      <i class="apphub-cursor-fx__impact-spark apphub-cursor-fx__impact-spark--3" />
      <i class="apphub-cursor-fx__impact-spark apphub-cursor-fx__impact-spark--4" />
    </span>

    <span
      v-for="(dot, i) in trail"
      :key="dot.id"
      class="apphub-cursor-fx__trail"
      :style="{
        opacity: ((i + 1) / (trail.length + 1)) * 0.55,
        transform: `translate3d(${dot.x}px, ${dot.y}px, 0) translate(-50%, -50%) scale(${dot.scale || 0.35 + (i + 1) * 0.04})`,
      }"
    />
    <span class="apphub-cursor-fx__ring">
      <i class="apphub-cursor-fx__orbit" />
      <i class="apphub-cursor-fx__comet" />
      <i class="apphub-cursor-fx__core" />
      <i class="apphub-cursor-fx__pulse" />
      <i class="apphub-cursor-fx__spark apphub-cursor-fx__spark--a" />
      <i class="apphub-cursor-fx__spark apphub-cursor-fx__spark--b" />
      <i class="apphub-cursor-fx__spark apphub-cursor-fx__spark--c" />
    </span>
  </div>
</template>
