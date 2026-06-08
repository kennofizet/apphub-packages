<template>
  <div
    ref="desktopRoot"
    class="apphub-desktop"
    :class="{
      'apphub-desktop--drop-target': isMainScreen,
      'apphub-desktop--light': activeTheme === 'light',
    }"
    @click="onDesktopClick"
    @dragenter.capture.prevent="onDesktopDragEnter"
    @dragover.capture.prevent="onDesktopDragOver"
    @dragleave="onDesktopDragLeave"
    @drop.capture.prevent="onDesktopDrop"
  >
    <div class="apphub-desktop__wallpaper" :class="{ 'apphub-desktop__wallpaper--drop': dropInstall.state.dragActive }" />

    <AppHubDesktopDropLayer
      v-show="isMainScreen && dropInstall.state.dragActive"
      :hint="labels.drop_hint"
    />

    <div
      ref="iconsLayerRef"
      class="apphub-desktop__icons-layer"
      :class="{
        'apphub-desktop__icons-layer--drop-target': isMainScreen,
        'apphub-desktop__icons-layer--grid': desktopSettings.snapToGrid,
      }"
    >
      <template v-for="item in desktopLayout" :key="item.id">
        <AppHubDesktopIconGroup
          v-if="item.type === 'group'"
          :apps="item.apps"
          :x="item.x"
          :y="item.y"
          :label="groupLabel(item.apps, item.x, item.y)"
          :title="`${groupLabel(item.apps, item.x, item.y)} — ${labels.desktop_icon_hold_hint}`"
          :dragging="isGroupDragging(item.apps)"
          :holding="isGroupHolding(item.apps)"
          :drop-highlight="isDropTargetCell(item.x, item.y)"
          @pointer-down="onGroupPointerDown(item, $event)"
          @click="onGroupClick(item)"
          @context-menu="onGroupContextMenu(item, $event)"
        />

        <button
          v-else
          type="button"
          class="apphub-desktop__icon apphub-desktop__icon--placed"
          :class="{
            'apphub-desktop__icon--dragging': iconDrag.isDragging(item.app.id),
            'apphub-desktop__icon--holding': iconDrag.isHolding(item.app.id),
            'apphub-desktop__icon--drop-target': isDropTargetCell(item.x, item.y),
          }"
          :style="{ left: `${item.x}px`, top: `${item.y}px` }"
          :title="`${item.app.name} — ${labels.desktop_icon_move_hint}`"
          @mousedown.stop="onPlacedIconPointerDown(item.app, $event)"
          @dblclick.stop="onOpenIcon(item.app)"
          @contextmenu.prevent.stop="onIconContextMenu(item.app, $event)"
        >
          <span class="apphub-desktop__icon-img">{{ item.app.icon }}</span>
          <span class="apphub-desktop__icon-label" :title="item.app.name">{{ item.app.name }}</span>
        </button>
      </template>

      <AppHubDesktopIconFolder
        v-if="dragFolderPreview"
        :open="true"
        preview
        :x="dragFolderPreview.x"
        :y="dragFolderPreview.y"
        :apps="dragFolderPreview.apps"
        :title="groupLabel(dragFolderPreview.apps, dragFolderPreview.x, dragFolderPreview.y)"
        :count-label="formatLabel('group_folder_count', { count: dragFolderPreview.apps.length })"
        :hint="labels.group_drop_hint"
        :preview-new-ids="dragPreviewNewIds"
      />

      <AppHubDesktopIconFolder
        :open="openFolder.open"
        :x="openFolder.x"
        :y="openFolder.y"
        :apps="openFolder.apps"
        :title="openFolder.title"
        :count-label="openFolder.countLabel"
        :hint="labels.desktop_icon_move_hint"
        :is-dragging="iconDrag.isDragging"
        :is-holding="iconDrag.isHolding"
        @item-pointer-down="onFolderItemPointerDown"
        @open-app="onFolderOpenApp"
        @item-context-menu="onFolderItemContextMenu"
      />

      <AppHubDropInstallBadge
        v-for="job in dropInstall.state.jobs"
        :key="job.id"
        :job="job"
        :loading-label="labels.drop_installing"
        :error-label="labels.drop_error"
        :method-label="methodLabel(job)"
      />

      <AppHubDesktopIconContextMenu
        :open="iconContextMenu.open"
        :x="iconContextMenu.x"
        :y="iconContextMenu.y"
        :can-rename="contextMenuCanRename"
        :open-label="contextMenuOpenLabel"
        :rename-label="labels.icon_context_rename"
        :properties-label="labels.icon_context_properties"
        @open="onContextMenuOpen"
        @rename="onContextMenuRename"
        @info="onContextMenuInfo"
      />
    </div>

    <AppHubDesktopIconInfoDialog
      :open="iconInfoDialog.open"
      :title="iconInfoDialogTitle"
      :app="iconInfoDialogApp"
      :rows="iconInfoRows"
      :close-label="labels.icon_info_close"
      @close="iconInfoDialog.open = false; iconInfoDialog.group = null"
    />

    <AppHubDesktopIconRenameDialog
      :open="iconRenameDialog.open"
      :title="renameDialogTitle"
      :name-label="renameDialogNameLabel"
      :initial-name="renameDialogInitialName"
      :save-label="labels.icon_rename_save"
      :cancel-label="labels.icon_rename_cancel"
      :error="iconRenameDialog.error"
      @save="onIconRenameSave"
      @cancel="iconRenameDialog.open = false; iconRenameDialog.group = null"
    />

    <AppHubDuplicateAppDialog
      :open="duplicateDialog.open"
      :title="labels.duplicate_app_title"
      :message="duplicateMessage"
      :hint="duplicateHint"
      :replace-label="labels.duplicate_app_replace"
      :keep-label="labels.duplicate_app_keep"
      :cancel-label="labels.duplicate_app_cancel"
      @replace="onDuplicateReplace"
      @keep="onDuplicateKeep"
      @cancel="onDuplicateCancel"
    />

    <div ref="workAreaRef" class="apphub-desktop__workarea">
      <AppHubWindowFrame
        v-for="win in visibleWindows"
        :key="win.id"
        :window="win"
        :active="win.id === wm.state.activeId"
        @session-change="persistSession"
      />
    </div>

    <AppHubStartMenu
      :open="shell.state.startOpen"
      :apps="startMenuApps"
      :search-placeholder="labels.start_menu_search"
      :pinned-label="labels.start_menu_pinned"
      :suggested-label="labels.start_menu_suggested"
      :empty-label="labels.start_menu_empty"
      :snap-to-grid="desktopSettings.snapToGrid"
      :snap-label="labels.settings_snap_grid"
      :theme="activeTheme"
      :theme-label="labels.settings_light_mode"
      :show-theme-toggle="showThemeToggle"
      @close="shell.state.startOpen = false"
      @open-app="onStartMenuOpenApp"
      @update:snap-to-grid="onSnapGridChange"
      @update:theme="onThemeChange"
    />

    <footer class="apphub-desktop__taskbar" @click.stop>
      <AppHubStartButton
        :active="shell.state.startOpen"
        :title="labels.desktop_start"
        @toggle="onToggleStart"
      />

      <div class="apphub-desktop__tasks">
        <button
          v-for="win in taskbarWindows"
          :key="win.id"
          type="button"
          class="apphub-desktop__task"
          :class="{ active: win.id === wm.state.activeId, minimized: win.minimized }"
          @click="onTaskClick(win)"
        >
          {{ win.icon }} {{ win.title }}
        </button>
      </div>
      <span class="apphub-desktop__clock">{{ shell.state.clock }}</span>
    </footer>
  </div>
</template>

<script setup>
import { computed, inject, nextTick, onMounted, onUnmounted, provide, reactive, ref, watch } from 'vue'
import { useAppStore } from '../../app-store/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { isThemeLocked, resolveTheme } from '../../../i18n/resolveTheme.js'
import { t } from '../../../i18n/index.js'
import { AppHubWindowFrame, useWindowManager } from '../../window-manager/index.js'
import AppHubDesktopDropLayer from './AppHubDesktopDropLayer.vue'
import AppHubStartButton from './AppHubStartButton.vue'
import AppHubStartMenu from './AppHubStartMenu.vue'
import AppHubDuplicateAppDialog from './AppHubDuplicateAppDialog.vue'
import AppHubDesktopIconContextMenu from './AppHubDesktopIconContextMenu.vue'
import AppHubDesktopIconInfoDialog from './AppHubDesktopIconInfoDialog.vue'
import AppHubDesktopIconRenameDialog from './AppHubDesktopIconRenameDialog.vue'
import AppHubDropInstallBadge from './AppHubDropInstallBadge.vue'
import AppHubDesktopIconGroup from './AppHubDesktopIconGroup.vue'
import AppHubDesktopIconFolder from './AppHubDesktopIconFolder.vue'
import { createDesktopDropInstall } from '../composables/useDesktopDropInstall.js'
import { createDesktopShell } from '../composables/useDesktopShell.js'
import { useDesktopIconDrag } from '../composables/useDesktopIconDrag.js'
import { buildDesktopItems, getGroupDisplayName, migrateGroupDisplayName, setGroupDisplayName } from '../utils/desktopIconGroups.js'
import {
  buildDesktopSession,
  loadDesktopSession,
  saveDesktopSession,
} from '../utils/desktopSession.js'
import { clampPointToLayer, nextIconGridSlot, snapPoint } from '../utils/desktopGrid.js'
import { applyDesktopSettings, loadDesktopSettings, saveDesktopSettings } from '../utils/desktopSettings.js'
import { nextDuplicateName } from '../utils/duplicateAppUtils.js'

const DESKTOP_HOST_KEY = 'apphubDesktopHost'

const props = defineProps({
  language: { type: String, default: 'vi' },
  openAppStoreOnMount: { type: Boolean, default: true },
  /** 'dark' | 'light' | 'auto' — auto uses saved user preference */
  theme: { type: String, default: 'auto' },
  /** Show Light mode in Start menu. Default: hidden when theme prop locks appearance */
  themeToggle: { type: Boolean, default: undefined },
})

const moduleOptions = inject('apphubOptions', {})

const lang = computed(() => resolveLang(moduleOptions?.language, props.language))

const activeTheme = computed(() => {
  const locked = resolveTheme(props.theme) ?? resolveTheme(moduleOptions?.theme)
  if (locked) return locked
  return desktopSettings.theme === 'light' ? 'light' : 'dark'
})

const showThemeToggle = computed(() => {
  if (props.themeToggle === true) return true
  if (props.themeToggle === false) return false
  if (moduleOptions.themeToggle === true) return true
  if (moduleOptions.themeToggle === false) return false
  return !isThemeLocked(props.theme, moduleOptions?.theme)
})

const wm = useWindowManager()
const appStore = useAppStore()
const desktopRoot = ref(null)
const workAreaRef = ref(null)
const iconsLayerRef = ref(null)

provide(DESKTOP_HOST_KEY, workAreaRef)

const desktopSettings = reactive(loadDesktopSettings())

const builtinPlacements = reactive({})

const duplicateDialog = reactive({
  open: false,
  app: null,
  existing: null,
})
let duplicateResolve = null

const iconContextMenu = reactive({ open: false, x: 0, y: 0, app: null, group: null })
const iconInfoDialog = reactive({ open: false, app: null, group: null })
const iconRenameDialog = reactive({ open: false, app: null, group: null, error: '' })

function formatLabel(key, params = {}) {
  return t(key, lang.value, params)
}

const labels = computed(() => ({
  desktop_start: t('desktop_start', lang.value),
  desktop_app_store: t('desktop_app_store', lang.value),
  desktop_app_store_hint: t('desktop_app_store_hint', lang.value),
  guide_app_name: t('guide_app_name', lang.value),
  guide_app_hint: t('guide_app_hint', lang.value),
  guide_app_title: t('guide_app_title', lang.value),
  app_store_title: t('app_store_title', lang.value),
  drop_hint: t('drop_hint', lang.value),
  drop_installing: t('drop_installing', lang.value),
  drop_error: t('drop_error', lang.value),
  drop_method_appstore: t('drop_method_appstore', lang.value),
  drop_method_local: t('drop_method_local', lang.value),
  settings_snap_grid: t('settings_snap_grid', lang.value),
  settings_light_mode: t('settings_light_mode', lang.value),
  duplicate_app_title: t('duplicate_app_title', lang.value),
  duplicate_app_replace: t('duplicate_app_replace', lang.value),
  duplicate_app_keep: t('duplicate_app_keep', lang.value),
  duplicate_app_cancel: t('duplicate_app_cancel', lang.value),
  desktop_icon_move_hint: t('desktop_icon_move_hint', lang.value),
  desktop_icon_hold_hint: t('desktop_icon_hold_hint', lang.value),
  group_label: t('group_label', lang.value),
  group_drop_hint: t('group_drop_hint', lang.value),
  group_folder_title: t('group_folder_title', lang.value),
  group_folder_count: t('group_folder_count', lang.value),
  group_context_open: t('group_context_open', lang.value),
  group_rename_title: t('group_rename_title', lang.value),
  group_rename_label: t('group_rename_label', lang.value),
  group_info_title: t('group_info_title', lang.value),
  group_info_count: t('group_info_count', lang.value),
  group_info_apps: t('group_info_apps', lang.value),
  group_info_type_group: t('group_info_type_group', lang.value),
  start_menu_search: t('start_menu_search', lang.value),
  start_menu_pinned: t('start_menu_pinned', lang.value),
  start_menu_suggested: t('start_menu_suggested', lang.value),
  start_menu_empty: t('start_menu_empty', lang.value),
  icon_context_open: t('icon_context_open', lang.value),
  icon_context_rename: t('icon_context_rename', lang.value),
  icon_context_properties: t('icon_context_properties', lang.value),
  icon_info_title: t('icon_info_title', lang.value),
  icon_info_name: t('icon_info_name', lang.value),
  icon_info_slug: t('icon_info_slug', lang.value),
  icon_info_created: t('icon_info_created', lang.value),
  icon_info_source: t('icon_info_source', lang.value),
  icon_info_source_appstore: t('icon_info_source_appstore', lang.value),
  icon_info_source_local: t('icon_info_source_local', lang.value),
  icon_info_description: t('icon_info_description', lang.value),
  icon_info_position: t('icon_info_position', lang.value),
  icon_info_type: t('icon_info_type', lang.value),
  icon_info_type_builtin: t('icon_info_type_builtin', lang.value),
  icon_info_date_unknown: t('icon_info_date_unknown', lang.value),
  icon_info_close: t('icon_info_close', lang.value),
  icon_rename_title: t('icon_rename_title', lang.value),
  icon_rename_label: t('icon_rename_label', lang.value),
  icon_rename_save: t('icon_rename_save', lang.value),
  icon_rename_cancel: t('icon_rename_cancel', lang.value),
  icon_rename_error_empty: t('icon_rename_error_empty', lang.value),
  icon_rename_error_duplicate: t('icon_rename_error_duplicate', lang.value),
}))

const duplicateMessage = computed(() =>
  duplicateDialog.app
    ? formatLabel('duplicate_app_message', { name: duplicateDialog.app.name })
    : '',
)

const duplicateHint = computed(() =>
  duplicateDialog.app
    ? formatLabel('duplicate_app_keep_as', {
        name: nextDuplicateName(duplicateDialog.app.name, shell.state.userApps),
      })
    : t('duplicate_app_hint', lang.value),
)

const visibleWindows = computed(() =>
  (wm.visibleWindows?.value ?? wm.visibleWindows ?? []).filter((w) => w?.id),
)
const taskbarWindows = computed(() =>
  (wm.taskbarWindows?.value ?? wm.taskbarWindows ?? []).filter((w) => w?.id),
)

const contextMenuOpenLabel = computed(() =>
  iconContextMenu.group ? labels.value.group_context_open : labels.value.icon_context_open,
)

const contextMenuCanRename = computed(() => {
  if (iconContextMenu.group) return true
  return !iconContextMenu.app?.builtin
})

const iconInfoDialogTitle = computed(() =>
  iconInfoDialog.group ? labels.value.group_info_title : labels.value.icon_info_title,
)

const iconInfoDialogApp = computed(() => {
  if (iconInfoDialog.group) {
    return { icon: '📂', name: groupLabel(iconInfoDialog.group.apps, iconInfoDialog.group.x, iconInfoDialog.group.y) }
  }
  return iconInfoDialog.app
})

const renameDialogTitle = computed(() =>
  iconRenameDialog.group ? labels.value.group_rename_title : labels.value.icon_rename_title,
)

const renameDialogNameLabel = computed(() =>
  iconRenameDialog.group ? labels.value.group_rename_label : labels.value.icon_rename_label,
)

const renameDialogInitialName = computed(() => {
  if (iconRenameDialog.group) return groupLabel(iconRenameDialog.group.apps, iconRenameDialog.group.x, iconRenameDialog.group.y)
  return iconRenameDialog.app?.name ?? ''
})

const isMainScreen = computed(() => visibleWindows.value.length === 0)

const iconInfoRows = computed(() => {
  const group = iconInfoDialog.group
  if (group) {
    const L = labels.value
    const apps = group.apps ?? []
    return [
      { label: L.icon_info_name, value: groupLabel(apps, group.x, group.y) },
      { label: L.icon_info_type, value: L.group_info_type_group },
      { label: L.group_info_count, value: String(apps.length) },
      { label: L.group_info_apps, value: apps.map((a) => a.name).join(', ') },
      { label: L.icon_info_position, value: `${group.x}, ${group.y}` },
    ]
  }

  const app = iconInfoDialog.app
  if (!app) return []
  const L = labels.value
  const rows = [{ label: L.icon_info_name, value: app.name }]
  if (app.builtin) {
    rows.push({ label: L.icon_info_type, value: L.icon_info_type_builtin })
    if (app.hint) rows.push({ label: L.icon_info_description, value: app.hint })
    return rows
  }
  rows.push({ label: L.icon_info_slug, value: app.slug })
  rows.push({ label: L.icon_info_created, value: formatAppCreatedAt(app.createdAt) })
  rows.push({ label: L.icon_info_source, value: resolveAppInstallSource(app) })
  if (app.hint) rows.push({ label: L.icon_info_description, value: app.hint })
  if (app.desktopX != null && app.desktopY != null) {
    rows.push({ label: L.icon_info_position, value: `${app.desktopX}, ${app.desktopY}` })
  }
  return rows
})

async function handleInstallUserApp(app, position, method = 'local') {
  let result = shell.installUserApp(app, position, method)
  while (result?.needsDuplicateChoice) {
    const choice = await askDuplicateChoice(result.app, result.existing)
    if (choice === 'cancel') return 'cancelled'
    result = shell.installUserApp(result.app, result.position, result.method, choice)
  }
  assignDefaultIconPositions()
  schedulePersist()
  return result
}

const shell = createDesktopShell({
  language: lang,
  getLabels: () => labels.value,
  handleInstall: handleInstallUserApp,
})

const iconList = shell.iconList

const builtinIcons = computed(() => iconList.filter((a) => a?.builtin))
const startMenuApps = computed(() => iconList.filter((a) => a?.id))

function getBuiltinPlacedApp(app) {
  const pos = builtinPlacements[app.id]
  if (!pos) return null
  return {
    ...app,
    desktopX: pos.x,
    desktopY: pos.y,
  }
}

function getAllPlacedApps() {
  const builtins = builtinIcons.value
    .map((app) => getBuiltinPlacedApp(app))
    .filter(Boolean)
  const users = shell.state.userApps.filter((a) => a.desktopX != null && a.desktopY != null)
  return [...builtins, ...users]
}

const desktopLayout = computed(() => buildDesktopItems(getAllPlacedApps()))

const dragFolderPreview = computed(() => {
  const target = iconDrag.dropTarget.value
  if (!target?.merging || !target.apps?.length) return null
  return target
})

const dragPreviewNewIds = computed(() => {
  if (!iconDrag.dropTarget.value?.merging || !iconDrag.drag.value?.moved) return []
  return iconDrag.drag.value.ids
})

const openFolder = reactive({
  open: false,
  x: 0,
  y: 0,
  apps: [],
  title: '',
  countLabel: '',
})

let clockTimer = null
let resizeObserver = null
let persistTimer = null

function persistSession() {
  saveDesktopSession(buildDesktopSession(shell, wm, appStore, desktopSettings))
  saveDesktopSettings(desktopSettings)
}

function schedulePersist() {
  if (persistTimer) clearTimeout(persistTimer)
  persistTimer = setTimeout(persistSession, 150)
}

const iconDrag = useDesktopIconDrag({
  getLayerEl: () => iconsLayerRef.value,
  getSnapToGrid: () => desktopSettings.snapToGrid,
  getDesktopApps: () => getAllPlacedApps(),
  findApp: (id) => findAppForDrag(id),
  onMoved: (details) => {
    if (details?.fromCell && details?.toCell) {
      migrateGroupDisplayName(
        desktopSettings,
        details.fromCell.x,
        details.fromCell.y,
        details.toCell.x,
        details.toCell.y,
      )
    }
    syncBuiltinPositionsToSettings()
    closeOpenFolder()
    schedulePersist()
  },
})

function findAppForDrag(id) {
  const user = shell.findUserApp(id)
  if (user) return user

  const meta = shell.findDesktopApp(id)
  if (!meta?.builtin || !builtinPlacements[id]) return null

  return {
    ...meta,
    get desktopX() {
      return builtinPlacements[id].x
    },
    set desktopX(value) {
      builtinPlacements[id].x = value
    },
    get desktopY() {
      return builtinPlacements[id].y
    },
    set desktopY(value) {
      builtinPlacements[id].y = value
    },
  }
}

function syncBuiltinPositionsToSettings() {
  desktopSettings.builtinPositions = { ...builtinPlacements }
}

function groupLabel(apps, x, y) {
  const n = apps?.length ?? 0
  if (n <= 1) return apps?.[0]?.name ?? labels.value.group_label
  return getGroupDisplayName(desktopSettings, x, y, labels.value, n)
}

function isDropTargetCell(x, y) {
  const target = iconDrag.dropTarget.value
  return target && target.x === x && target.y === y
}

function isGroupDragging(apps) {
  return apps.some((a) => iconDrag.isDragging(a.id))
}

function isGroupHolding(apps) {
  return apps.some((a) => iconDrag.isHolding(a.id))
}

function closeOpenFolder() {
  openFolder.open = false
  openFolder.apps = []
}

function openGroupFolder(item) {
  openFolder.x = item.x
  openFolder.y = item.y
  openFolder.apps = [...item.apps]
  openFolder.title = groupLabel(item.apps, item.x, item.y)
  openFolder.countLabel = formatLabel('group_folder_count', { count: item.apps.length })
  openFolder.open = true
}

function onPlacedIconPointerDown(app, event) {
  if (event.button !== 0) return
  event.preventDefault()
  closeOpenFolder()
  iconDrag.onPointerDown(app, event, { mode: 'single' })
}

function onGroupClick(item) {
  if (iconDrag.lastWasDrag.value) {
    iconDrag.lastWasDrag.value = false
    return
  }
  openGroupFolder(item)
}

function onGroupPointerDown(item, event) {
  if (event.button !== 0) return
  event.preventDefault()
  closeOpenFolder()
  iconDrag.onPointerDown(item.apps, event, { mode: 'group' })
}

function onFolderItemPointerDown(app, event) {
  if (event.button !== 0) return
  event.preventDefault()
  iconDrag.onPointerDown(app, event, {
    mode: 'folder',
    onTap: () => onOpenIcon(app),
  })
}

function onFolderOpenApp(app) {
  closeOpenFolder()
  onOpenIcon(app)
}

function onFolderItemContextMenu(app, event) {
  onIconContextMenu(app, event)
}

function onGroupContextMenu(item, event) {
  event.preventDefault()
  event.stopPropagation()
  if (isGroupDragging(item.apps)) return

  const layer = iconsLayerRef.value
  if (!layer) return
  const rect = layer.getBoundingClientRect()
  const menuW = 220
  const menuH = 132
  let x = event.clientX - rect.left
  let y = event.clientY - rect.top
  x = Math.max(4, Math.min(x, rect.width - menuW - 4))
  y = Math.max(4, Math.min(y, rect.height - menuH - 4))

  iconContextMenu.app = null
  iconContextMenu.group = item
  iconContextMenu.x = x
  iconContextMenu.y = y
  iconContextMenu.open = true
}

function syncBuiltinPlacementsFromSettings() {
  const saved = desktopSettings.builtinPositions
  if (!saved || typeof saved !== 'object') return
  for (const [id, pos] of Object.entries(saved)) {
    if (pos && Number.isFinite(pos.x) && Number.isFinite(pos.y)) {
      builtinPlacements[id] = { x: pos.x, y: pos.y }
    }
  }
}

function ensureBuiltinPositions() {
  builtinIcons.value.forEach((app, index) => {
    if (builtinPlacements[app.id]) return
    const saved = desktopSettings.builtinPositions?.[app.id]
    builtinPlacements[app.id] = saved
      ? { ...saved }
      : { x: 16, y: 16 + index * 96 }
  })
}

function assignDefaultIconPositions() {
  const layer = iconsLayerRef.value
  if (!layer) return
  const seen = new Set()
  const occupied = []
  for (const a of getAllPlacedApps()) {
    const key = `${a.desktopX},${a.desktopY}`
    if (seen.has(key)) continue
    seen.add(key)
    occupied.push({ x: a.desktopX, y: a.desktopY })
  }
  shell.state.userApps.forEach((app) => {
    if (app.desktopX != null) return
    const pos = nextIconGridSlot(occupied, layer.clientWidth, layer.clientHeight)
    app.desktopX = pos.x
    app.desktopY = pos.y
    occupied.push(pos)
  })
}

function askDuplicateChoice(app, existing) {
  duplicateDialog.app = app
  duplicateDialog.existing = existing
  duplicateDialog.open = true
  return new Promise((resolve) => {
    duplicateResolve = resolve
  })
}

function closeDuplicate(choice) {
  duplicateDialog.open = false
  duplicateResolve?.(choice)
  duplicateResolve = null
  duplicateDialog.app = null
  duplicateDialog.existing = null
}

function onDuplicateReplace() {
  closeDuplicate('replace')
}

function onDuplicateKeep() {
  closeDuplicate('keep')
}

function onDuplicateCancel() {
  closeDuplicate('cancel')
}

function onSnapGridChange(value) {
  desktopSettings.snapToGrid = value
  if (value) {
    shell.state.userApps.forEach((app) => {
      if (app.desktopX == null) return
      const pos = snapPoint(app.desktopX, app.desktopY, true)
      app.desktopX = pos.x
      app.desktopY = pos.y
    })
  }
  schedulePersist()
}

function onThemeChange(theme) {
  desktopSettings.theme = theme === 'light' ? 'light' : 'dark'
  schedulePersist()
}

function resolveDropPosition(x, y) {
  const layer = iconsLayerRef.value
  if (!layer) return snapPoint(x, y, desktopSettings.snapToGrid)
  const clamped = clampPointToLayer(x, y, layer.clientWidth, layer.clientHeight)
  return snapPoint(clamped.x, clamped.y, desktopSettings.snapToGrid)
}

const dropInstall = createDesktopDropInstall({
  getAppStore: () => appStore,
  async onInstalled(app, { x, y, method }) {
    const position = resolveDropPosition(x, y)
    return handleInstallUserApp(app, position, method)
  },
  onPersist: schedulePersist,
})

function measureWorkArea() {
  const work = workAreaRef.value
  if (!work) return
  wm.setWorkArea?.({ width: work.clientWidth, height: work.clientHeight })
  wm.relayoutWindows?.()
}

function pointerInIconsLayer(event) {
  const layer = iconsLayerRef.value ?? desktopRoot.value
  if (!layer) return resolveDropPosition(16, 16)
  const rect = layer.getBoundingClientRect()
  const rawX = event.clientX - rect.left - 44
  const rawY = event.clientY - rect.top - 44
  return resolveDropPosition(rawX, rawY)
}

function onDesktopDragEnter(event) {
  if (!isMainScreen.value) return
  dropInstall.onDragEnter(event, false)
}

function onDesktopDragOver(event) {
  if (!isMainScreen.value) return
  dropInstall.onDragOver(event, false)
}

function onDesktopDragLeave(event) {
  if (!isMainScreen.value) return
  dropInstall.onDragLeave(event, false)
}

async function onDesktopDrop(event) {
  if (!isMainScreen.value) return
  await dropInstall.onDrop(event, false, pointerInIconsLayer(event))
}

function onWindowDragEnd() {
  dropInstall.resetDrag()
}

function methodLabel(job) {
  return job.method === 'appstore' ? labels.value.drop_method_appstore : labels.value.drop_method_local
}

function onOpenIcon(app) {
  measureWorkArea()
  shell.openApp(app, wm)
  schedulePersist()
}

function onDesktopClick(event) {
  if (event.target.closest('.apphub-icon-folder')) return
  shell.state.startOpen = false
  closeIconContextMenu()
  closeOpenFolder()
}

function closeIconContextMenu() {
  iconContextMenu.open = false
  iconContextMenu.app = null
  iconContextMenu.group = null
}

function onIconContextMenu(app, event) {
  event.preventDefault()
  event.stopPropagation()
  if (iconDrag.isDragging(app.id)) return

  const layer = iconsLayerRef.value
  if (!layer) return
  const rect = layer.getBoundingClientRect()
  const menuW = 220
  const menuH = app.builtin ? 88 : 132
  let x = event.clientX - rect.left
  let y = event.clientY - rect.top
  x = Math.max(4, Math.min(x, rect.width - menuW - 4))
  y = Math.max(4, Math.min(y, rect.height - menuH - 4))

  iconContextMenu.group = null
  iconContextMenu.app = app
  iconContextMenu.x = x
  iconContextMenu.y = y
  iconContextMenu.open = true
}

function onContextMenuOpen() {
  const group = iconContextMenu.group
  const app = iconContextMenu.app
  closeIconContextMenu()
  if (group) {
    openGroupFolder(group)
    return
  }
  if (app) onOpenIcon(app)
}

function onContextMenuRename() {
  const group = iconContextMenu.group
  const app = iconContextMenu.app
  closeIconContextMenu()
  if (group) {
    iconRenameDialog.group = group
    iconRenameDialog.app = null
    iconRenameDialog.error = ''
    iconRenameDialog.open = true
    return
  }
  if (!app || app.builtin) return
  iconRenameDialog.app = app
  iconRenameDialog.group = null
  iconRenameDialog.error = ''
  iconRenameDialog.open = true
}

function onContextMenuInfo() {
  const group = iconContextMenu.group
  const app = iconContextMenu.app
  closeIconContextMenu()
  if (group) {
    iconInfoDialog.group = group
    iconInfoDialog.app = null
    iconInfoDialog.open = true
    return
  }
  if (!app) return
  iconInfoDialog.app = app
  iconInfoDialog.group = null
  iconInfoDialog.open = true
}

function formatAppCreatedAt(iso) {
  if (!iso) return labels.value.icon_info_date_unknown
  try {
    const locale = lang.value === 'vi' ? 'vi-VN' : undefined
    return new Date(iso).toLocaleString(locale)
  } catch {
    return labels.value.icon_info_date_unknown
  }
}

function resolveAppInstallSource(app) {
  const method = app.installMethod ?? (app.local ? 'local' : 'appstore')
  return method === 'local'
    ? labels.value.icon_info_source_local
    : labels.value.icon_info_source_appstore
}

function syncAppWindowTitle(app) {
  const win = wm.state.windows.find((w) => w.id === `win-${app.id}`)
  if (win) win.title = app.name
}

function onIconRenameSave(name) {
  const group = iconRenameDialog.group
  if (group) {
    const trimmed = String(name ?? '').trim()
    if (!trimmed) {
      iconRenameDialog.error = labels.value.icon_rename_error_empty
      return
    }
    setGroupDisplayName(
      desktopSettings,
      group.x,
      group.y,
      trimmed,
      labels.value,
      group.apps.length,
    )
    if (openFolder.open && openFolder.x === group.x && openFolder.y === group.y) {
      openFolder.title = groupLabel(group.apps, group.x, group.y)
    }
    iconRenameDialog.open = false
    iconRenameDialog.group = null
    iconRenameDialog.error = ''
    schedulePersist()
    return
  }

  const app = iconRenameDialog.app
  if (!app) return
  const result = shell.renameUserApp(app.id, name)
  if (!result.ok) {
    iconRenameDialog.error =
      result.error === 'duplicate'
        ? labels.value.icon_rename_error_duplicate
        : labels.value.icon_rename_error_empty
    return
  }
  syncAppWindowTitle(result.app)
  iconRenameDialog.open = false
  iconRenameDialog.app = null
  iconRenameDialog.group = null
  iconRenameDialog.error = ''
  schedulePersist()
}

function onDocumentPointerDown(event) {
  if (!iconContextMenu.open) return
  const root = desktopRoot.value
  if (root?.querySelector('.apphub-icon-menu')?.contains(event.target)) return
  closeIconContextMenu()
}

function onToggleStart() {
  closeOpenFolder()
  shell.state.startOpen = !shell.state.startOpen
}

function onStartMenuOpenApp(app) {
  shell.state.startOpen = false
  onOpenIcon(app)
}

function onOpenAppStore() {
  shell.state.startOpen = false
  measureWorkArea()
  shell.openBuiltinAppStore(wm)
  schedulePersist()
}

function onTaskClick(win) {
  if (win.minimized) wm.focusWindow(win.id)
  else if (wm.state.activeId === win.id) wm.minimizeWindow(win.id)
  else wm.focusWindow(win.id)
  schedulePersist()
}

function restoreInstalledApps(slugs) {
  if (!Array.isArray(slugs)) return
  slugs.forEach((slug) => appStore.installApp(slug))
}

watch(() => wm.state.windows, () => schedulePersist(), { deep: true })
watch(() => shell.state.userApps, () => schedulePersist(), { deep: true })
watch(() => appStore.state.installedSlugs, () => schedulePersist(), { deep: true })

function onDocumentDragOver(event) {
  if (!isMainScreen.value) return
  dropInstall.onDragOver(event, false)
}

onMounted(async () => {
  shell.tickClock()
  clockTimer = setInterval(shell.tickClock, 30_000)
  window.addEventListener('beforeunload', persistSession)
  window.addEventListener('dragend', onWindowDragEnd)
  document.addEventListener('dragover', onDocumentDragOver)
  document.addEventListener('mousedown', onDocumentPointerDown)

  await nextTick()
  measureWorkArea()

  if (typeof ResizeObserver !== 'undefined' && desktopRoot.value) {
    resizeObserver = new ResizeObserver(() => measureWorkArea())
    resizeObserver.observe(desktopRoot.value)
  } else {
    window.addEventListener('resize', measureWorkArea)
  }

  const session = loadDesktopSession()
  if (session) {
    if (session.settings) applyDesktopSettings(desktopSettings, session.settings)
    syncBuiltinPlacementsFromSettings()
    restoreInstalledApps(session.installedSlugs)
    shell.restoreSession(session, wm)
    ensureBuiltinPositions()
    assignDefaultIconPositions()
    schedulePersist()
    return
  }

  ensureBuiltinPositions()
  assignDefaultIconPositions()

  if (props.openAppStoreOnMount) {
    shell.openBuiltinAppStore(wm)
    schedulePersist()
  }
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
  if (persistTimer) clearTimeout(persistTimer)
  iconDrag.cleanup()
  resizeObserver?.disconnect()
  window.removeEventListener('resize', measureWorkArea)
  window.removeEventListener('beforeunload', persistSession)
  window.removeEventListener('dragend', onWindowDragEnd)
  document.removeEventListener('dragover', onDocumentDragOver)
  document.removeEventListener('mousedown', onDocumentPointerDown)
  persistSession()
})
</script>
