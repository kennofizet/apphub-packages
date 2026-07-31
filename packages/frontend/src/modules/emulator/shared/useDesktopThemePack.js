import { computed, onUnmounted, reactive, watch } from 'vue'
import {
  mountDesktopThemeCustomCss,
  unmountDesktopThemeCustomCss,
} from './desktopThemeCustomCss.js'
import {
  desktopThemeCustomCssText,
  desktopThemeStyleObject,
  normalizeDesktopThemeRequest,
} from './desktopThemePack.js'
import {
  clearDesktopThemePack,
  loadDesktopThemePack,
  saveDesktopThemePack,
} from './desktopThemeStorage.js'

export function useDesktopThemePack(options) {
  const state = reactive({
    mode: null,
    tokens: {},
    rules: [],
  })

  function storage() {
    return options.storage?.() ?? (typeof localStorage !== 'undefined' ? localStorage : null)
  }

  function hydrate(key) {
    const saved = loadDesktopThemePack(storage(), key)
    state.mode = saved?.mode ?? null
    state.tokens = saved?.tokens ?? {}
    state.rules = saved?.rules ?? []
  }

  function syncCustomCss() {
    const active = state.mode === 'custom' && !options.isThemeLocked?.()
    mountDesktopThemeCustomCss(active ? desktopThemeCustomCssText(state.rules) : '')
  }

  watch(
    () => options.getStorageKey?.() ?? null,
    (key) => hydrate(key),
    { immediate: true },
  )

  watch(
    () => [state.mode, state.rules, options.isThemeLocked?.() === true],
    () => syncCustomCss(),
    { deep: true, immediate: true },
  )

  onUnmounted(() => {
    unmountDesktopThemeCustomCss()
  })

  function applyTheme(payload) {
    if (options.isThemeLocked?.()) {
      throw new Error('Desktop theme is locked by the Hub host')
    }

    const theme = normalizeDesktopThemeRequest(payload)
    const key = options.getStorageKey?.() ?? null

    if (theme.mode === 'custom') {
      if (!key) throw new Error('Hub user is not available for desktop theme persistence')
      if (!saveDesktopThemePack(storage(), key, theme)) {
        throw new Error('Unable to persist desktop theme')
      }
      state.mode = 'custom'
      state.tokens = theme.tokens
      state.rules = theme.rules
      return {
        mode: 'custom',
        tokens: { ...theme.tokens },
        rules: theme.rules.map((rule) => ({
          selector: rule.selector,
          props: { ...rule.props },
        })),
      }
    }

    clearDesktopThemePack(storage(), key)
    state.mode = null
    state.tokens = {}
    state.rules = []
    options.setBuiltInMode?.(theme.mode)
    return { mode: theme.mode, tokens: {}, rules: [] }
  }

  return {
    state,
    hasCustomTheme: computed(() => state.mode === 'custom' && !options.isThemeLocked?.()),
    customThemeStyle: computed(() =>
      state.mode === 'custom' && !options.isThemeLocked?.()
        ? desktopThemeStyleObject(state.tokens)
        : {},
    ),
    colorScheme: computed(() =>
      state.mode === 'custom' && !options.isThemeLocked?.()
        ? 'custom'
        : (options.getBuiltInMode?.() ?? 'auto'),
    ),
    applyTheme,
  }
}
