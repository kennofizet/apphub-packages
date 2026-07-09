<template>
  <AppHubOriginLoadingScreen v-if="originBootstrapLoading" />

  <AppHubOriginBlockScreen
    v-else-if="!originSafety.safe"
    :reason="originSafety.reason"
    :parent-origin="originSafety.parentOrigin"
    :expected-hub-origin="originSafety.expectedHubOrigin"
    :expected-runtime-origin="originSafety.expectedRuntimeOrigin"
    :labels="originBlockLabels"
  />

  <div
    v-else
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
          <span class="apphub-desktop__icon-img-wrap">
            <AppHubCatalogIcon
              :app="item.app"
              emoji-class="apphub-desktop__icon-img"
              img-class="apphub-desktop__icon-img apphub-desktop__icon-img--image"
            />
            <span
              v-if="iconShowsDraftStatus(item.app)"
              class="apphub-desktop__icon-flag"
              :title="labels.desktop_icon_draft_hint"
            >
              {{ labels.app_store_status_draft }}
            </span>
            <span
              v-else-if="iconShowsPendingTest(item.app)"
              class="apphub-desktop__icon-flag apphub-desktop__icon-flag--pending"
              :title="labels.desktop_icon_pending_hint"
            >
              {{ labels.publisher_pending_icon_badge }}
            </span>
            <span
              v-else-if="iconShowsRejectedTest(item.app)"
              class="apphub-desktop__icon-flag apphub-desktop__icon-flag--rejected"
              :title="iconRejectedHint(item.app)"
            >
              {{ labels.publisher_rejected_icon_badge }}
            </span>
          </span>
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
        :done-publish-label="labels.drop_done_publish"
      />

      <AppHubDesktopIconContextMenu
        :open="iconContextMenu.open"
        :x="iconContextMenu.x"
        :y="iconContextMenu.y"
        :can-rename="contextMenuCanRename"
        :show-pin="contextMenuShowPin"
        :show-favorite="contextMenuShowFavorite"
        :show-uninstall="contextMenuShowUninstall"
        :open-label="contextMenuOpenLabel"
        :pin-label="contextMenuPinLabel"
        :favorite-label="contextMenuFavoriteLabel"
        :uninstall-label="labels.icon_context_uninstall"
        :rename-label="labels.icon_context_rename"
        :properties-label="labels.icon_context_properties"
        @open="onContextMenuOpen"
        @pin="onContextMenuPin"
        @favorite="onContextMenuFavorite"
        @uninstall="onContextMenuUninstall"
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

    <AppHubInstallPermissionsDialog
      :open="installPermDialog.open"
      :theme="activeTheme"
      :title="installPermTitle"
      :message="installPermMessage"
      :hint="installPermHint"
      :accept-label="labels.install_perm_accept"
      :refuse-label="labels.install_perm_refuse"
      :permission-scopes="installPermDialog.permissions"
      :permission-labels="installPermLabels"
      :permission-pending="installPermPending"
      :pending-dev-label="labels.bridge_perm_pending_dev_review"
      :permission-section-title="installPermDialog.permissions.length ? labels.install_perm_permissions_title : ''"
      :api-urls="installPermDialog.apiUrls"
      :api-urls-section-title="installPermDialog.apiUrls.length ? labels.install_perm_api_urls_title : ''"
      :api-urls-hint="installPermDialog.apiUrls.length ? labels.install_perm_api_urls_hint : ''"
      @accept="onInstallPermAccept"
      @refuse="onInstallPermRefuse"
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
      :favorite-apps="startMenuFavoriteApps"
      :recent-apps="startMenuRecentApps"
      :suggested-apps="startMenuSuggestedApps"
      :catalog-apps="startMenuCatalogApps"
      :visible-in-start-ids="visibleInStartIds"
      :search-placeholder="labels.start_menu_search"
      :favorites-label="labels.start_menu_favorites"
      :recent-label="labels.start_menu_recent"
      :search-results-label="labels.start_menu_search_results"
      :suggested-label="labels.start_menu_suggested"
      :empty-label="labels.start_menu_empty"
      @close="shell.state.startOpen = false"
      @open-app="onStartMenuOpenApp"
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

      <AppHubTaskbarPins
        :apps="taskbarPinnedApps"
        :aria-label="labels.taskbar_pins"
        @open-app="onOpenIcon"
      />

      <button
        v-if="draftStoreApp"
        type="button"
        class="apphub-desktop__taskbar-draft-store"
        :title="draftStoreApp.hint || draftStoreApp.name"
        @click="onOpenIcon(draftStoreApp)"
      >
        <span class="apphub-desktop__taskbar-draft-store-icon" aria-hidden="true">{{ draftStoreApp.icon }}</span>
        <span class="apphub-desktop__taskbar-draft-store-label">{{ draftStoreApp.name }}</span>
      </button>

      <button
        v-if="devToolsApp"
        type="button"
        class="apphub-desktop__taskbar-draft-store apphub-desktop__taskbar-dev-tools"
        :title="devToolsApp.hint || devToolsApp.name"
        @click="onOpenIcon(devToolsApp)"
      >
        <span class="apphub-desktop__taskbar-draft-store-icon" aria-hidden="true">{{ devToolsApp.icon }}</span>
        <span class="apphub-desktop__taskbar-draft-store-label">{{ devToolsApp.name }}</span>
      </button>

      <div class="apphub-desktop__tray-clock">
        <span class="apphub-desktop__clock">{{ shell.state.clock }}</span>
        <span class="apphub-taskbar-tray__sep" aria-hidden="true" />
        <AppHubTaskbarNotificationBell />
      </div>
    </footer>

    <AppHubDesktopDevOriginBar placement="corner" />

    <AppHubDesktopNotifications />
    <AppHubNotificationDrawer />
  </div>
</template>

<script setup>
import { computed, getCurrentInstance, inject, nextTick, onMounted, onUnmounted, provide, reactive, ref, watch } from 'vue'
import { getHostApiForApp, isBackendReadyForApp } from '../../../composables/useAppHubHostApi.js'
import { getAppHubStore } from '../../../moduleStore.js'
import {
  CATALOG_MODE_DRAFT,
  CATALOG_MODE_PUBLISHER,
  CATALOG_MODE_STORE,
} from '../../app-store/constants/catalogModes.js'
import { useAppStore } from '../../app-store/index.js'
import { resolveLang } from '../../../i18n/resolveLang.js'
import { isThemeLocked, resolveTheme } from '../../../i18n/resolveTheme.js'
import { t } from '../../../i18n/index.js'
import {
  AppHubDesktopNotifications,
  createDesktopNotificationsState,
  DESKTOP_NOTIFICATIONS_KEY,
} from '../../notifications/index.js'
import {
  AppHubNotificationDrawer,
  AppHubTaskbarNotificationBell,
  createUserNotificationCenter,
  USER_NOTIFICATION_CENTER_KEY,
} from '../../user-notifications/index.js'
import { AppHubWindowFrame, useWindowManager } from '../../window-manager/index.js'
import AppHubDesktopDropLayer from './AppHubDesktopDropLayer.vue'
import AppHubStartButton from './AppHubStartButton.vue'
import AppHubStartMenu from './AppHubStartMenu.vue'
import AppHubTaskbarPins from './AppHubTaskbarPins.vue'
import AppHubDuplicateAppDialog from './AppHubDuplicateAppDialog.vue'
import AppHubInstallPermissionsDialog from './AppHubInstallPermissionsDialog.vue'
import AppHubDesktopIconContextMenu from './AppHubDesktopIconContextMenu.vue'
import AppHubDesktopIconInfoDialog from './AppHubDesktopIconInfoDialog.vue'
import AppHubDesktopIconRenameDialog from './AppHubDesktopIconRenameDialog.vue'
import AppHubDropInstallBadge from './AppHubDropInstallBadge.vue'
import AppHubDesktopIconGroup from './AppHubDesktopIconGroup.vue'
import AppHubDesktopIconFolder from './AppHubDesktopIconFolder.vue'
import AppHubOriginBlockScreen from './AppHubOriginBlockScreen.vue'
import AppHubOriginLoadingScreen from './AppHubOriginLoadingScreen.vue'
import AppHubDesktopDevOriginBar from './AppHubDesktopDevOriginBar.vue'
import { createDesktopDropInstall } from '../composables/useDesktopDropInstall.js'
import { clampPerPage } from '../../../utils/catalogPagination.js'
import {
  isRejectedDraftSubmission,
  isRunningRejectedVersion,
  isTestingPendingVersion,
  resolvePublisherTestVersion,
} from '../../../utils/publisherTestVersion.js'
import { evaluateOriginSafety } from '../../../utils/originSafety.js'
import { bridgeScopeLabel, isParentBridgeScope } from '../../../utils/appBridgeScopes.js'
import { parentBridgeScopeLabel, loadParentBridgeScopePrompts } from '../../../utils/parentBridgeScopePrompts.js'
import { resolveAppPermissions } from '../../../utils/resolveAppPermissions.js'
import { resolveAppApiUrls } from '../../../utils/resolveAppApiUrls.js'
import {
  clearInstalledPermissions,
  saveInstalledPermissions,
} from '../../../utils/installedAppPermissions.js'
import { createDesktopShell } from '../composables/useDesktopShell.js'
import { useDesktopIconDrag } from '../composables/useDesktopIconDrag.js'
import { buildDesktopItems, getGroupDisplayName, migrateGroupDisplayName, setGroupDisplayName } from '../utils/desktopIconGroups.js'
import {
  buildDesktopSession,
  loadDesktopSession,
  saveDesktopSession,
} from '../utils/desktopSession.js'
import { clampPointToLayer, nextIconGridSlot, snapPoint } from '../utils/desktopGrid.js'
import { DESKTOP_HUB_SETTINGS_KEY } from '../composables/useDesktopHubSettings.js'
import { applyDesktopSettings, loadDesktopSettings, saveDesktopSettings } from '../utils/desktopSettings.js'
import { loadHubKeyboardSettings, matchSnapShortcut, saveHubKeyboardSettings } from '../utils/hubKeyboardSettings.js'
import {
  isAppPinned,
  isAppVisibleInStart,
  loadStartMenuPins,
  pinApp,
  saveStartMenuPins,
  setPinVisible,
  unpinApp,
} from '../utils/startMenuPins.js'
import {
  favoriteApp,
  isAppFavorite,
  resolveStartMenuFavoriteApps,
  resolveStartMenuRecentApps,
  loadStartMenuFavorites,
  saveStartMenuFavorites,
  setFavoriteVisible,
  unfavoriteApp,
} from '../utils/startMenuFavorites.js'
import {
  loadRecentOpenLog,
  recordRecentApp,
  resolveRecentApps,
  resolveSuggestedApps,
} from '../utils/recentApps.js'
import { nextDuplicateName } from '../utils/duplicateAppUtils.js'
import AppHubCatalogIcon from '../../../components/AppHubCatalogIcon.vue'

const DESKTOP_HOST_KEY = 'apphubDesktopHost'

const props = defineProps({
  language: { type: String, default: 'vi' },
  /** Open installed (or catalog) app by slug on mount — e.g. /apphub?open=pilot-active */
  initialOpenSlug: { type: String, default: '' },
  openAppStoreOnMount: { type: Boolean, default: true },
  /** 'dark' | 'light' | 'auto' — auto uses saved user preference */
  theme: { type: String, default: 'auto' },
  /** Show Light mode in Start menu. Default: hidden when theme prop locks appearance */
  themeToggle: { type: Boolean, default: undefined },
})

const moduleOptions = inject('apphubOptions', {})
const rootApp = getCurrentInstance()?.appContext?.app

const lang = computed(() => resolveLang(moduleOptions?.language, props.language))

const originBootstrapLoading = computed(() => moduleOptions?.originBootstrapLoading === true)

const originSafety = computed(() => {
  if (moduleOptions && Object.prototype.hasOwnProperty.call(moduleOptions, 'originBlocked')) {
    return {
      safe: !moduleOptions.originBlocked,
      pending: moduleOptions.originCheckPending === true,
      loading: moduleOptions.originBootstrapLoading === true,
      reason: moduleOptions.originBlockReason ?? null,
      parentOrigin: moduleOptions.originBlockParentOrigin ?? null,
      expectedHubOrigin: moduleOptions.originBlockExpectedHubOrigin ?? null,
      expectedRuntimeOrigin: moduleOptions.originBlockExpectedRuntimeOrigin ?? null,
    }
  }
  return evaluateOriginSafety(moduleOptions)
})

const desktopReady = computed(() => !originBootstrapLoading.value && originSafety.value.safe)

const originBlockLabels = computed(() => ({
  title: t('origin_block_title', lang.value),
  same_origin_embed: t('origin_block_same_origin_embed', lang.value),
  not_configured: t('origin_block_not_configured', lang.value),
  wrong_origin: t('origin_block_wrong_origin', lang.value),
  runtime_not_configured: t('origin_block_runtime_not_configured', lang.value),
  runtime_same_origin: t('origin_block_runtime_same_origin', lang.value),
  generic: t('origin_block_generic', lang.value),
  hint: t('origin_block_hint', lang.value),
  current_origin: t('origin_block_current_origin', lang.value),
  expected_hub_origin: t('origin_block_expected_hub_origin', lang.value),
  expected_runtime_origin: t('origin_block_expected_runtime_origin', lang.value),
  parent_origin: t('origin_block_parent_origin', lang.value),
}))

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
const keyboardSettings = reactive(loadHubKeyboardSettings())
const startMenuPins = reactive(loadStartMenuPins())
const startMenuFavorites = reactive(loadStartMenuFavorites())
const recentOpenLog = ref(loadRecentOpenLog())

const builtinPlacements = reactive({})

const duplicateDialog = reactive({
  open: false,
  app: null,
  existing: null,
})
let duplicateResolve = null

const installPermDialog = reactive({
  open: false,
  app: null,
  action: 'install',
  permissions: [],
  apiUrls: [],
  intentToken: null,
  saving: false,
})
let installPermResolve = null

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
  hub_settings_app_name: t('hub_settings_app_name', lang.value),
  hub_settings_app_hint: t('hub_settings_app_hint', lang.value),
  hub_settings_app_title: t('hub_settings_app_title', lang.value),
  app_store_title: t('app_store_title', lang.value),
  app_store_status_draft: t('app_store_status_draft', lang.value),
  desktop_icon_draft_hint: t('desktop_icon_draft_hint', lang.value),
  publisher_pending_icon_badge: t('publisher_pending_icon_badge', lang.value),
  desktop_icon_pending_hint: t('desktop_icon_pending_hint', lang.value),
  publisher_rejected_icon_badge: t('publisher_rejected_icon_badge', lang.value),
  desktop_icon_rejected_hint: t('desktop_icon_rejected_hint', lang.value),
  desktop_icon_rejected_draft_hint: t('desktop_icon_rejected_draft_hint', lang.value),
  drop_hint: t('drop_hint', lang.value),
  drop_installing: t('drop_installing', lang.value),
  drop_error: t('drop_error', lang.value),
  drop_done_publish: t('drop_done_publish', lang.value),
  notif_error_title: t('notif_error_title', lang.value),
  notif_publish_success: t('notif_publish_success', lang.value),
  notif_publish_upgrade_success: t('notif_publish_upgrade_success', lang.value),
  notif_install_cancelled: t('notif_install_cancelled', lang.value),
  drop_method_publish: t('drop_method_publish', lang.value),
  drop_method_appstore: t('drop_method_appstore', lang.value),
  drop_method_local: t('drop_method_local', lang.value),
  settings_snap_grid: t('settings_snap_grid', lang.value),
  settings_light_mode: t('settings_light_mode', lang.value),
  duplicate_app_title: t('duplicate_app_title', lang.value),
  duplicate_app_replace: t('duplicate_app_replace', lang.value),
  duplicate_app_keep: t('duplicate_app_keep', lang.value),
  duplicate_app_cancel: t('duplicate_app_cancel', lang.value),
  install_perm_hint: t('install_perm_hint', lang.value),
  install_perm_parent_hint: t('install_perm_parent_hint', lang.value),
  bridge_perm_pending_dev_review: t('bridge_perm_pending_dev_review', lang.value),
  install_perm_permissions_title: t('install_perm_permissions_title', lang.value),
  install_perm_api_urls_title: t('install_perm_api_urls_title', lang.value),
  install_perm_api_urls_hint: t('install_perm_api_urls_hint', lang.value),
  install_perm_accept: t('install_perm_accept', lang.value),
  install_perm_refuse: t('install_perm_refuse', lang.value),
  install_perm_refused_install: t('install_perm_refused_install', lang.value),
  install_perm_refused_update: t('install_perm_refused_update', lang.value),
  desktop_icon_move_hint: t('desktop_icon_move_hint', lang.value),
  desktop_icon_hold_hint: t('desktop_icon_hold_hint', lang.value),
  draft_store_app_name: t('draft_store_app_name', lang.value),
  draft_store_app_hint: t('draft_store_app_hint', lang.value),
  draft_store_title: t('draft_store_title', lang.value),
  dev_tools_app_name: t('dev_tools_app_name', lang.value),
  dev_tools_app_hint: t('dev_tools_app_hint', lang.value),
  dev_tools_title: t('dev_tools_title', lang.value),
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
  start_menu_recent: t('start_menu_recent', lang.value),
  start_menu_search_results: t('start_menu_search_results', lang.value),
  start_menu_suggested: t('start_menu_suggested', lang.value),
  start_menu_empty: t('start_menu_empty', lang.value),
  taskbar_pins: t('taskbar_pins', lang.value),
  icon_context_open: t('icon_context_open', lang.value),
  icon_context_rename: t('icon_context_rename', lang.value),
  icon_context_properties: t('icon_context_properties', lang.value),
  icon_context_pin: t('icon_context_pin', lang.value),
  icon_context_unpin: t('icon_context_unpin', lang.value),
  icon_context_favorite: t('icon_context_favorite', lang.value),
  icon_context_unfavorite: t('icon_context_unfavorite', lang.value),
  icon_context_uninstall: t('icon_context_uninstall', lang.value),
  start_menu_favorites: t('start_menu_favorites', lang.value),
  icon_info_title: t('icon_info_title', lang.value),
  icon_info_name: t('icon_info_name', lang.value),
  icon_info_slug: t('icon_info_slug', lang.value),
  icon_info_version: t('icon_info_version', lang.value),
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

const contextMenuShowPin = computed(() => !iconContextMenu.group && !!iconContextMenu.app?.id)

const contextMenuPinLabel = computed(() => {
  const app = iconContextMenu.app
  if (!app?.id) return labels.value.icon_context_pin
  return isAppPinned(startMenuPins, app.id)
    ? labels.value.icon_context_unpin
    : labels.value.icon_context_pin
})

const contextMenuShowFavorite = computed(() => !iconContextMenu.group && !!iconContextMenu.app?.id)

const contextMenuShowUninstall = computed(() => {
  const app = iconContextMenu.app
  return !iconContextMenu.group && !!app?.id && !app.builtin && !app.module
})

const contextMenuFavoriteLabel = computed(() => {
  const app = iconContextMenu.app
  if (!app?.id) return labels.value.icon_context_favorite
  return isAppFavorite(startMenuFavorites, app.id)
    ? labels.value.icon_context_unfavorite
    : labels.value.icon_context_favorite
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
  const pinnedVersion = app.installedVersion ?? app.version
  if (pinnedVersion) rows.push({ label: L.icon_info_version, value: `v${pinnedVersion}` })
  rows.push({ label: L.icon_info_created, value: formatAppCreatedAt(app.createdAt) })
  rows.push({ label: L.icon_info_source, value: resolveAppInstallSource(app) })
  if (app.hint) rows.push({ label: L.icon_info_description, value: app.hint })
  if (app.desktopX != null && app.desktopY != null) {
    rows.push({ label: L.icon_info_position, value: `${app.desktopX}, ${app.desktopY}` })
  }
  return rows
})

const installPermTitle = computed(() => {
  const key = installPermDialog.action === 'update'
    ? 'install_perm_title_update'
    : 'install_perm_title_install'
  return t(key, lang.value)
})

const installPermMessage = computed(() => {
  const name = installPermDialog.app?.name || installPermDialog.app?.slug || ''
  const key = installPermDialog.action === 'update'
    ? 'install_perm_message_update'
    : 'install_perm_message_install'
  return formatLabel(key, { name })
})

const installPermLabels = computed(() => {
  const appLabel = installPermDialog.app?.name || installPermDialog.app?.slug || ''
  const translate = (key) => t(key, lang.value)
  return installPermDialog.permissions.map((scope) =>
    bridgeScopeLabel(scope, appLabel, translate, {
      parentScopeLabel: (parentScope, app) => parentBridgeScopeLabel(parentScope, app, translate),
    }),
  )
})

const installPermPending = computed(() =>
  installPermDialog.permissions.map((scope) => isParentBridgeScope(scope)),
)

const installPermHint = computed(() => {
  if (installPermPending.value.some(Boolean)) {
    return `${labels.value.install_perm_hint} ${labels.value.install_perm_parent_hint}`
  }
  return labels.value.install_perm_hint
})

function resolveInstallVersion(app) {
  return app?.installedVersion ?? app?.pending_version ?? app?.version ?? null
}

function needsServerInstallConsent(app, permissions, apiUrls) {
  if (!app?.slug) return false
  if (!isBackendReadyForApp(rootApp)) return false
  const api = getHostApiForApp(rootApp)
  if (!api?.recordBridgeConsent || !api?.createInstallIntent) return false
  return permissions.length > 0 || apiUrls.length > 0
}

async function askInstallPermissions(app, action = 'install') {
  let permissions = resolveAppPermissions(app)
  let apiUrls = resolveAppApiUrls(app)
  if (app?.slug) {
    const catalog = appStore.findCatalogItem(app.slug)
    if (!permissions.length) permissions = resolveAppPermissions(catalog)
    if (!apiUrls.length) apiUrls = resolveAppApiUrls(catalog)
  }
  if (!permissions.length && !apiUrls.length) return true

  let intentToken = null
  if (needsServerInstallConsent(app, permissions, apiUrls)) {
    const api = getHostApiForApp(rootApp)
    const version = resolveInstallVersion(app)
    try {
      const res = await api.createInstallIntent(app.slug, version ? { version } : {})
      intentToken = res?.data?.data?.intent_token ?? res?.data?.intent_token ?? null
      if (!intentToken) {
        throw new Error('Missing install intent token')
      }
    } catch (err) {
      desktopNotifications.push({
        type: 'error',
        title: app?.name || app?.slug || labels.value.notif_error_title,
        message: labels.value.install_perm_consent_failed,
      })
      return false
    }
  }

  if (permissions.some(isParentBridgeScope)) {
    const api = getHostApiForApp(rootApp)
    if (api?.parentBridgeScopePrompts) {
      await loadParentBridgeScopePrompts(() => api.parentBridgeScopePrompts())
    }
  }

  installPermDialog.app = app
  installPermDialog.action = action
  installPermDialog.permissions = permissions
  installPermDialog.apiUrls = apiUrls
  installPermDialog.intentToken = intentToken
  installPermDialog.saving = false

  return new Promise((resolve) => {
    installPermResolve = resolve
    nextTick(() => {
      installPermDialog.open = true
    })
  })
}

function finishInstallPerm(accepted) {
  installPermDialog.open = false
  installPermDialog.saving = false
  installPermResolve?.(accepted)
  installPermResolve = null
  installPermDialog.app = null
  installPermDialog.permissions = []
  installPermDialog.apiUrls = []
  installPermDialog.intentToken = null
  installPermDialog.action = 'install'
}

function closeInstallPerm(accepted, app, action) {
  if (!accepted && app) {
    desktopNotifications.push({
      type: 'warning',
      title: app.name || app.slug || labels.value.notif_error_title,
      message: action === 'update'
        ? labels.value.install_perm_refused_update
        : labels.value.install_perm_refused_install,
    })
  }
  finishInstallPerm(accepted)
}

async function onInstallPermAccept() {
  const app = installPermDialog.app
  const action = installPermDialog.action
  const permissions = [...installPermDialog.permissions]
  const intentToken = installPermDialog.intentToken
  if (!app?.slug) {
    closeInstallPerm(false, app, action)
    return
  }

  installPermDialog.saving = true
  const saved = await persistBridgeConsent(app, intentToken, permissions, installPermDialog.apiUrls)
  installPermDialog.saving = false
  if (!saved) {
    desktopNotifications.push({
      type: 'error',
      title: app.name || app.slug || labels.value.notif_error_title,
      message: labels.value.install_perm_consent_failed,
    })
    return
  }

  if (permissions.length) {
    saveInstalledPermissions(app.slug, permissions)
  } else {
    clearInstalledPermissions(app.slug)
  }
  closeInstallPerm(true, app, action)
}

function onInstallPermRefuse() {
  const app = installPermDialog.app
  const action = installPermDialog.action
  closeInstallPerm(false, app, action)
}

async function persistBridgeConsent(app, intentToken, permissions = [], apiUrls = []) {
  if (!app?.slug) return true
  if (!needsServerInstallConsent(app, permissions, apiUrls)) return true

  const api = getHostApiForApp(rootApp)
  const version = resolveInstallVersion(app)
  const payload = {
    ...(version ? { version } : {}),
    intent_token: intentToken,
  }
  if (!payload.intent_token) return false

  try {
    const res = await api.recordBridgeConsent(app.slug, payload)
    return res?.data?.success === true
  } catch {
    return false
  }
}

async function handleInstallUserApp(app, position, method = 'local') {
  // Always require explicit Accept dialog — never auto-record publish drop consents.
  const permitted = await askInstallPermissions(
    app,
    method === 'publish' ? 'update' : 'install',
  )
  if (!permitted) return 'cancelled'

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

function handleUninstallUserApp(appOrSlug) {
  const slug = typeof appOrSlug === 'string' ? appOrSlug : appOrSlug?.slug
  const app = (typeof appOrSlug === 'object' && appOrSlug?.id && !appOrSlug?.builtin ? appOrSlug : null)
    ?? (slug ? shell.findUserAppBySlug(slug) : null)
  if (!app || app.builtin) return false

  if (slug) appStore.uninstallApp(slug)

  if (slug) clearInstalledPermissions(slug)

  const winId = `win-${app.id}`
  if (wm.state.windows?.some((w) => w.id === winId)) {
    wm.closeWindow(winId)
  }
  if (isAppPinned(startMenuPins, app.id)) unpinApp(startMenuPins, app.id)
  if (isAppFavorite(startMenuFavorites, app.id)) unfavoriteApp(startMenuFavorites, app.id)

  shell.removeUserApp(app.id)
  assignDefaultIconPositions()
  schedulePersist()

  if (slug && isBackendReadyForApp(rootApp)) {
    const api = getHostApiForApp(rootApp)
    void (async () => {
      try {
        if (api?.revokeBridgeConsents) await api.revokeBridgeConsents(slug)
      } catch {
        /* ignore */
      }
      await userNotificationCenter.loadInbox({ reset: true, announce: false })
    })()
  }

  return true
}

function resolveCatalogMergedApp(app) {
  if (!app?.slug || app.builtin) return app
  const catalog = appStore.findCatalogItem(app.slug)
  return catalog ? { ...catalog, ...app } : app
}

function iconShowsDraftStatus(app) {
  if (!app || app.builtin) return false
  const merged = resolveCatalogMergedApp(app)
  if (isRejectedDraftSubmission(merged)) return false
  if (merged.status === 'draft') return true
  return false
}

function iconRejectedHint(app) {
  const merged = resolveCatalogMergedApp(app)
  if (isRejectedDraftSubmission(merged)) {
    return labels.value.desktop_icon_rejected_draft_hint
  }
  return labels.value.desktop_icon_rejected_hint
}

function syncUserAppPublisherFields(catalogApp) {
  const slug = catalogApp?.slug
  if (!slug || !shell.findUserAppBySlug(slug)) return false

  const rejectedVersion = catalogApp.rejected_version
    ?? (catalogApp.current_version_review_status === 'rejected' ? catalogApp.version : null)

  shell.patchUserAppBySlug(slug, {
    status: catalogApp.status,
    runtime_type: catalogApp.runtime_type,
    entry_url: catalogApp.entry_url,
    healthcheck_url: catalogApp.healthcheck_url,
    name: catalogApp.name,
    description: catalogApp.description,
    permissions: Array.isArray(catalogApp.permissions) ? catalogApp.permissions : [],
    pending_version: catalogApp.pending_version ?? null,
    catalog_version: catalogApp.version ?? null,
    rejected_version: rejectedVersion ?? null,
  })

  const pinVersion = resolvePublisherTestVersion(catalogApp)
  if (pinVersion) {
    shell.updateInstalledVersion(slug, pinVersion)
  }

  return true
}

function iconShowsPendingTest(app) {
  if (!app || app.builtin || iconShowsDraftStatus(app)) return false
  const merged = resolveCatalogMergedApp(app)
  if (isTestingPendingVersion(merged)) return true

  const pending = merged.pending_version
  if (!pending) return false

  const installed = merged.installedVersion ?? merged.version
  return Boolean(installed && installed === pending)
}

function iconShowsRejectedTest(app) {
  if (!app || app.builtin || iconShowsDraftStatus(app)) return false
  if (iconShowsPendingTest(app)) return false
  const merged = resolveCatalogMergedApp(app)
  if (isRejectedDraftSubmission(merged)) return true
  if (isRunningRejectedVersion(merged)) return true

  const rejected = merged.rejected_version
  if (!rejected) return false

  const installed = merged.installedVersion ?? merged.version
  return Boolean(installed && installed === rejected)
}

function syncPublisherVersionBadgesFromCatalog() {
  for (const userApp of shell.state.userApps) {
    if (!userApp?.slug || userApp.builtin) continue
    const catalog = appStore.findCatalogItem(userApp.slug)
    if (!catalog) continue
    syncUserAppPublisherFields(catalog)
  }
}

async function handleUpdateUserApp(app) {
  const permitted = await askInstallPermissions(app, 'update')
  if (!permitted) return false

  const version = resolvePublisherTestVersion(app)
  if (!app?.slug || !version) return false
  const ok = shell.updateInstalledVersion(app.slug, version)
  if (ok) {
    shell.patchUserAppBySlug(app.slug, {
      pending_version: app.pending_version ?? null,
      catalog_version: app.version ?? null,
      rejected_version: app.rejected_version ?? null,
    })
    schedulePersist()
  }
  return ok
}

async function refreshPublisherCatalog() {
  const api = getHostApiForApp(rootApp)
  if (!api?.apps || !isBackendReadyForApp(rootApp)) return

  await appStore.loadCatalog(api, {
    backendReady: true,
    mode: CATALOG_MODE_PUBLISHER,
    perPage: clampPerPage(48),
  })
  syncPublisherVersionBadgesFromCatalog()
  schedulePersist()
}

async function refreshPublisherCatalogIfReady() {
  if (!isBackendReadyForApp(rootApp)) return
  await refreshPublisherCatalog()
}

async function handleCatalogAppChanged(catalogApp) {
  if (!catalogApp?.slug) return

  const api = getHostApiForApp(rootApp)
  if (api?.apps && isBackendReadyForApp(rootApp)) {
    await appStore.loadCatalog(api, { backendReady: true, mode: CATALOG_MODE_STORE, perPage: clampPerPage(48) })
    await refreshPublisherCatalog()
  }

  const ownedInPublisher = appStore.catalogs.draft.items.some((a) => a.slug === catalogApp.slug)
  if (ownedInPublisher && typeof appStore.upsertCatalogItem === 'function') {
    appStore.upsertCatalogItem(CATALOG_MODE_PUBLISHER, catalogApp)
    if (shell.findUserAppBySlug(catalogApp.slug)) {
      syncUserAppPublisherFields(catalogApp)
      schedulePersist()
    }
  }
}

const shell = createDesktopShell({
  language: lang,
  getLabels: () => labels.value,
  handleInstall: handleInstallUserApp,
  handleUninstall: handleUninstallUserApp,
  onUpdateApp: handleUpdateUserApp,
  onCatalogChanged: handleCatalogAppChanged,
  onAppOpened: (appId) => touchRecentApp(appId),
})

const iconList = shell.iconList

const builtinIcons = computed(() => iconList.filter((a) => a?.builtin))
const startMenuCatalogApps = computed(() => iconList.filter((a) => a?.id))

const startMenuFavoriteApps = computed(() =>
  resolveStartMenuFavoriteApps(startMenuCatalogApps.value, startMenuFavorites),
)

const startMenuRecentApps = computed(() =>
  resolveStartMenuRecentApps(
    startMenuCatalogApps.value,
    startMenuFavorites,
    recentOpenLog.value,
  ),
)

const startMenuSuggestedApps = computed(() =>
  resolveSuggestedApps(startMenuCatalogApps.value, recentOpenLog.value),
)

const taskbarPinnedApps = computed(() =>
  startMenuCatalogApps.value.filter((a) => isAppVisibleInStart(startMenuPins, a.id)),
)

const taskbarExtraApps = computed(() => {
  const apps = shell.taskbarBuiltinApps
  const list = apps?.value ?? apps ?? []
  const isDev = moduleOptions?.isDevUser === true
  return list.filter((a) => !a.devOnly || isDev)
})

const draftStoreApp = computed(() =>
  taskbarExtraApps.value.find((a) => a.module === 'draft-store') ?? null,
)

const devToolsApp = computed(() =>
  taskbarExtraApps.value.find((a) => a.module === 'dev-tools') ?? null,
)

const visibleInStartIds = computed(() =>
  startMenuCatalogApps.value
    .filter((a) => isAppVisibleInStart(startMenuPins, a.id))
    .map((a) => a.id),
)

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

function persistStartMenuPins() {
  saveStartMenuPins(startMenuPins)
}

function persistStartMenuFavorites() {
  saveStartMenuFavorites(startMenuFavorites)
}

function toggleAppPin(appId) {
  if (!appId) return
  if (isAppPinned(startMenuPins, appId)) {
    unpinApp(startMenuPins, appId)
  } else {
    pinApp(startMenuPins, appId)
  }
  persistStartMenuPins()
}

function toggleAppFavorite(appId) {
  if (!appId) return
  if (isAppFavorite(startMenuFavorites, appId)) {
    unfavoriteApp(startMenuFavorites, appId)
  } else {
    favoriteApp(startMenuFavorites, appId)
  }
  persistStartMenuFavorites()
}

function setAppPinVisible(appId, visible) {
  setPinVisible(startMenuPins, appId, visible)
  persistStartMenuPins()
}

function setAppFavoriteVisible(appId, visible) {
  setFavoriteVisible(startMenuFavorites, appId, visible)
  persistStartMenuFavorites()
}

const hubSettings = reactive({
  desktopSettings,
  keyboardSettings,
  startMenuPins,
  startMenuFavorites,
  recentOpenLog,
  desktopApps: startMenuCatalogApps,
  activeTheme,
  showThemeToggle,
  setSnapToGrid: onSnapGridChange,
  setTheme: onThemeChange,
  saveKeyboardSettings: () => saveHubKeyboardSettings(keyboardSettings),
  getDesktopApps: () => startMenuCatalogApps.value,
  getRecentApps: () => resolveRecentApps(startMenuCatalogApps.value, recentOpenLog.value),
  isAppPinned: (appId) => isAppPinned(startMenuPins, appId),
  isAppFavorite: (appId) => isAppFavorite(startMenuFavorites, appId),
  setPinVisible: setAppPinVisible,
  setFavoriteVisible: setAppFavoriteVisible,
  toggleAppPin,
  toggleAppFavorite,
})

provide(DESKTOP_HUB_SETTINGS_KEY, hubSettings)

function resolveDropPosition(x, y) {
  const layer = iconsLayerRef.value
  if (!layer) return snapPoint(x, y, desktopSettings.snapToGrid)
  const clamped = clampPointToLayer(x, y, layer.clientWidth, layer.clientHeight)
  return snapPoint(clamped.x, clamped.y, desktopSettings.snapToGrid)
}

const desktopNotifications = createDesktopNotificationsState()
provide(DESKTOP_NOTIFICATIONS_KEY, desktopNotifications)

const userNotificationCenter = createUserNotificationCenter({
  getApi: () => getHostApiForApp(rootApp),
  getCacheKey: () => {
    const store = getAppHubStore(rootApp)
    const backend = String(store?.credentials?.backendUrl ?? '').trim()
    if (!backend) return ''
    const userId = store?.zoneContext?.state?.user?.id
    return userId != null && userId !== '' ? `${backend}#${userId}` : backend
  },
  desktopNotifications,
})
provide(USER_NOTIFICATION_CENTER_KEY, userNotificationCenter)

const dropInstall = createDesktopDropInstall({
  getAppStore: () => appStore,
  getHostApi: () => getHostApiForApp(rootApp),
  onNotify: (payload) => desktopNotifications.push(payload),
  getLabels: () => ({
    errorGeneric: labels.value.drop_error,
    errorTitle: labels.value.notif_error_title,
    publishSuccess: labels.value.notif_publish_success,
    publishUpgradeSuccess: labels.value.notif_publish_upgrade_success,
    installCancelled: labels.value.notif_install_cancelled,
  }),
  async onInstalled(app, { x, y, method }) {
    const position = resolveDropPosition(x, y)
    return handleInstallUserApp(app, position, method)
  },
  onPublishRegistered(catalogApp) {
    syncUserAppPublisherFields(catalogApp)
    if (catalogApp?.slug && typeof appStore.upsertCatalogItem === 'function') {
      appStore.upsertCatalogItem(CATALOG_MODE_PUBLISHER, catalogApp)
    }
    schedulePersist()
  },
  async onAfterPublish(catalogApp) {
    await refreshPublisherCatalog()
    if (catalogApp?.slug && typeof appStore.upsertCatalogItem === 'function') {
      appStore.upsertCatalogItem(CATALOG_MODE_PUBLISHER, catalogApp)
    }
    syncUserAppPublisherFields(catalogApp)
    schedulePersist()
  },
  onPersist: schedulePersist,
})

function measureWorkArea() {
  const work = workAreaRef.value
  if (!work) return
  wm.setWorkArea?.({ width: work.clientWidth, height: work.clientHeight })
  wm.relayoutWindows?.()
}

function isTypingTarget(target) {
  if (!target || !(target instanceof Element)) return false
  return !!target.closest('input, textarea, select, [contenteditable="true"]')
}

function onDocumentKeyDown(event) {
  const direction = matchSnapShortcut(event, keyboardSettings)
  if (!direction) return
  if (isTypingTarget(event.target)) return
  if (!wm.state.activeId) return

  event.preventDefault()
  wm.snapActiveWindow?.(wm.state.activeId, direction)
  schedulePersist()
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
  if (job.method === 'publish') return labels.value.drop_method_publish
  if (job.method === 'appstore') return labels.value.drop_method_appstore
  return labels.value.drop_method_local
}

function touchRecentApp(appId) {
  if (!appId) return
  recentOpenLog.value = recordRecentApp(appId, recentOpenLog.value)
}

function resolveAppIdFromWindow(win) {
  if (!win?.id || typeof win.id !== 'string') return null
  return win.id.startsWith('win-') ? win.id.slice(4) : null
}

function onOpenIcon(app) {
  measureWorkArea()
  shell.openApp(app, wm)
  schedulePersist()
}

const initialSlugOpened = ref(false)

async function ensureCatalogItemForSlug(slug) {
  let item = appStore.findCatalogItem(slug)
  if (item) return item

  const api = getHostApiForApp(rootApp)
  if (!api?.apps || !isBackendReadyForApp(rootApp)) return null

  const loadOpts = { backendReady: true, perPage: clampPerPage(48) }
  await appStore.loadCatalog(api, { ...loadOpts, mode: CATALOG_MODE_STORE })
  item = appStore.findCatalogItem(slug)
  if (item) return item

  await appStore.loadCatalog(api, { ...loadOpts, mode: CATALOG_MODE_PUBLISHER })
  return appStore.findCatalogItem(slug)
}

async function tryOpenAppBySlug(slug) {
  const normalized = String(slug ?? '').trim()
  if (!normalized) return false

  let app = shell.findUserAppBySlug(normalized)
  if (!app) {
    const item = await ensureCatalogItemForSlug(normalized)
    if (item) {
      appStore.installApp(normalized)
      shell.onUserAppInstalled(item)
      app = shell.findUserAppBySlug(normalized)
    }
  }

  if (app) {
    onOpenIcon(app)
    return true
  }
  return false
}

async function tryInitialOpenSlug() {
  if (initialSlugOpened.value || !props.initialOpenSlug || !moduleOptions?.hasToken) return
  const ok = await tryOpenAppBySlug(props.initialOpenSlug)
  if (ok) initialSlugOpened.value = true
}

watch(
  () => [moduleOptions?.hasToken, moduleOptions?.originBootstrapLoading],
  ([hasToken, bootstrapLoading]) => {
    if (!hasToken || bootstrapLoading === true) return
    refreshPublisherCatalogIfReady()
    tryInitialOpenSlug()
  },
)

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
  const menuH = app.builtin ? 176 : 220
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

function onContextMenuPin() {
  const app = iconContextMenu.app
  closeIconContextMenu()
  if (!app?.id) return
  toggleAppPin(app.id)
}

function onContextMenuUninstall() {
  const app = iconContextMenu.app
  closeIconContextMenu()
  if (!app) return
  handleUninstallUserApp(app)
}

function onContextMenuFavorite() {
  const app = iconContextMenu.app
  closeIconContextMenu()
  if (!app?.id) return
  toggleAppFavorite(app.id)
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
  const app = iconList.find((a) => a.builtin && a.module === 'app-store')
  if (app) onOpenIcon(app)
  else {
    measureWorkArea()
    shell.openBuiltinAppStore(wm)
    schedulePersist()
  }
}

function onTaskClick(win) {
  if (win.minimized) {
    touchRecentApp(resolveAppIdFromWindow(win))
    wm.focusWindow(win.id)
  } else if (wm.state.activeId === win.id) {
    wm.minimizeWindow(win.id)
  } else {
    touchRecentApp(resolveAppIdFromWindow(win))
    wm.focusWindow(win.id)
  }
  schedulePersist()
}

function restoreInstalledApps(slugs) {
  if (!Array.isArray(slugs)) return
  slugs.forEach((slug) => appStore.installApp(slug))
}

watch(() => wm.state.windows, () => schedulePersist(), { deep: true })
watch(() => shell.state.userApps, () => schedulePersist(), { deep: true })
watch(() => appStore.state.installedSlugs, () => schedulePersist(), { deep: true })
watch(
  () => appStore.catalogs.draft.items,
  () => syncPublisherVersionBadgesFromCatalog(),
  { deep: true },
)

function onDocumentDragOver(event) {
  if (!isMainScreen.value) return
  dropInstall.onDragOver(event, false)
}

function onDocumentPointerDownCapture(event) {
  if (!desktopReady.value) return
  const target = event.target
  if (!(target instanceof Element)) return
  const frame = target.closest('[data-window-id]')
  if (!frame) return
  const id = frame.getAttribute('data-window-id')
  if (id) wm.focusWindow(id)
}

function onDocumentFocusIn(event) {
  if (!desktopReady.value) return
  const target = event.target
  if (!(target instanceof Element)) return
  const frame = target.closest('[data-window-id]')
  if (!frame) return
  const id = frame.getAttribute('data-window-id')
  if (id) wm.focusWindow(id)
}

onMounted(async () => {
  if (!desktopReady.value) return
  await initDesktopShell()
})

watch(desktopReady, async (ready) => {
  if (ready) await initDesktopShell()
})

watch(
  () => isBackendReadyForApp(rootApp) && !!getHostApiForApp(rootApp)?.notifications,
  (ready) => {
    if (ready) void startUserNotificationInbox()
  },
)

async function initDesktopShell() {
  if (initDesktopShell.done) return
  initDesktopShell.done = true

  shell.tickClock()
  clockTimer = setInterval(shell.tickClock, 30_000)
  window.addEventListener('beforeunload', persistSession)
  window.addEventListener('dragend', onWindowDragEnd)
  document.addEventListener('dragover', onDocumentDragOver)
  document.addEventListener('pointerdown', onDocumentPointerDownCapture, true)
  document.addEventListener('focusin', onDocumentFocusIn, true)
  document.addEventListener('mousedown', onDocumentPointerDown)
  document.addEventListener('keydown', onDocumentKeyDown)

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
    await tryInitialOpenSlug()
    await refreshPublisherCatalogIfReady()
    await startUserNotificationInbox()
    return
  }

  ensureBuiltinPositions()
  assignDefaultIconPositions()

  if (props.initialOpenSlug) {
    await tryInitialOpenSlug()
    await refreshPublisherCatalogIfReady()
    await startUserNotificationInbox()
    return
  }

  if (props.openAppStoreOnMount) {
    const app = iconList.find((a) => a.builtin && a.module === 'app-store')
    if (app) onOpenIcon(app)
    else {
      shell.openBuiltinAppStore(wm)
      schedulePersist()
    }
  }

  await refreshPublisherCatalogIfReady()
  await startUserNotificationInbox()
}

let userNotificationInboxStarted = false

async function startUserNotificationInbox() {
  if (userNotificationInboxStarted) return
  if (!isBackendReadyForApp(rootApp)) return
  const api = getHostApiForApp(rootApp)
  if (!api?.notifications) return

  userNotificationInboxStarted = true
  await userNotificationCenter.bootstrapInbox()
  userNotificationCenter.startPolling()
}

onUnmounted(() => {
  if (!initDesktopShell.done) return

  if (clockTimer) clearInterval(clockTimer)
  if (persistTimer) clearTimeout(persistTimer)
  iconDrag.cleanup()
  resizeObserver?.disconnect()
  window.removeEventListener('resize', measureWorkArea)
  window.removeEventListener('beforeunload', persistSession)
  window.removeEventListener('dragend', onWindowDragEnd)
  document.removeEventListener('dragover', onDocumentDragOver)
  document.removeEventListener('pointerdown', onDocumentPointerDownCapture, true)
  document.removeEventListener('focusin', onDocumentFocusIn, true)
  document.removeEventListener('mousedown', onDocumentPointerDown)
  document.removeEventListener('keydown', onDocumentKeyDown)
  userNotificationCenter.dispose()
  persistSession()
})
</script>
