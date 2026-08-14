import {
  normalizeDesktopThemeRules,
  renderDesktopThemeCustomCss,
} from './desktopThemeCustomCss.js'

/** Structural wallpaper / icon chrome families for custom themes. */
export const DESKTOP_THEME_SKINS = Object.freeze([
  'classic',
  'light',
  'ocean',
  'forest',
  'dusk',
  'aurora',
  'solar',
  'cyber',
  'ink',
  'ember',
])

const SKIN_SET = new Set(DESKTOP_THEME_SKINS)

/** Decorative mix glyphs rendered on the wallpaper (not app icons). */
export const DESKTOP_THEME_SKIN_MIX = Object.freeze({
  classic: ['🛒', '📖', '⚙️'],
  light: ['📁', '📝', '🔍'],
  ocean: ['🪸', '🧭', '🐚', '⛵'],
  forest: ['🍃', '🍄', '🦌', '🏕️'],
  dusk: ['🌹', '🕯️', '🫖', '📜'],
  aurora: ['💜', '🪐', '✨', '🔮'],
  solar: ['🔆', '📐', '🛰️', '⏳'],
  cyber: ['⚡', '🖥️', '🛰️', '👾'],
  ink: ['✒️', '📎', '📰', '🗂️'],
  ember: ['🔥', '🏜️', '🌋', '🪓'],
})

export function normalizeDesktopThemeSkin(raw) {
  const skin = typeof raw === 'string' ? raw.trim().toLowerCase() : ''
  if (!skin) return 'classic'
  if (!SKIN_SET.has(skin)) {
    throw new Error(`Unknown desktop theme skin: ${raw}`)
  }
  return skin
}

export function mixIconsForSkin(skin) {
  const key = SKIN_SET.has(skin) ? skin : 'classic'
  return DESKTOP_THEME_SKIN_MIX[key] || DESKTOP_THEME_SKIN_MIX.classic
}

export const DESKTOP_THEME_TOKENS = Object.freeze([
  '--ah-text',
  '--ah-text-secondary',
  '--ah-text-muted',
  '--ah-border',
  '--ah-border-subtle',
  '--ah-hover',
  '--ah-hover-strong',
  '--ah-surface',
  '--ah-surface-elevated',
  '--ah-surface-panel',
  '--ah-surface-input',
  '--ah-taskbar',
  '--ah-taskbar-shadow',
  '--ah-shadow',
  '--ah-icon-label-shadow',
  '--ah-icon-drag-shadow',
  '--ah-accent',
  '--ah-accent-soft',
  '--ah-accent-muted',
  '--ah-success',
  '--ah-danger',
  '--ah-titlebar',
  '--ah-titlebar-text',
  '--ah-overlay',
  '--ah-link',
  '--ah-draft-accent',
  '--ah-draft-accent-strong',
  '--ah-draft-surface',
  '--ah-draft-hero-bg',
  '--ah-draft-card-bg',
  '--ah-draft-card-border',
  '--ah-draft-icon-bg',
  '--ah-scrollbar-size',
  '--ah-scrollbar-track',
  '--ah-scrollbar-thumb',
  '--ah-scrollbar-thumb-hover',
  '--ah-scrollbar-radius',
  '--ah-wallpaper',
  '--ah-wallpaper-filter',
  '--ah-wallpaper-drop-filter',
  '--ah-blur',
  '--ah-panel-blur',
  '--ah-radius',
  '--ah-radius-sm',
  '--ah-elevation-shadow',
  '--ah-elevation-shadow-active',
  '--ah-panel-shadow',
  '--ah-focus-ring',
  '--ah-window-border',
  '--ah-window-border-active',
  '--ah-window-shadow',
  '--ah-window-shadow-active',
  '--ah-window-radius',
  '--ah-start-hero-bg',
  '--ah-start-hero-border',
  '--ah-font',
  '--ah-font-size',
  '--ah-font-size-sm',
  '--ah-font-weight',
  '--ah-letter-spacing',
  '--ah-icon-size',
  '--ah-icon-tile-width',
  '--ah-taskbar-height',
  '--ah-titlebar-height',
  '--ah-space-xs',
  '--ah-space-sm',
  '--ah-space-md',
  '--ah-space-lg',
  '--ah-dock-bg',
  '--ah-dock-border',
  '--ah-dock-shadow',
  '--ah-dock-radius',
  '--ah-drop-glow',
  '--ah-drop-hint-bg',
  '--ah-motion-fast',
  '--ah-motion-normal',
  '--ah-motion-slow',
  '--ah-ease-out',
  '--ah-ease-standard',
  '--ah-ease-spring',
  '--ah-motion-pop-duration',
  '--ah-motion-panel-duration',
  '--ah-motion-hover-duration',
  '--ah-fx-pop-scale-from',
  '--ah-fx-pop-scale-to',
  '--ah-fx-panel-rise',
  '--ah-fx-panel-scale-from',
  '--ah-fx-icon-lift',
])

const TOKEN_SET = new Set(DESKTOP_THEME_TOKENS)
const MAX_VALUE_LENGTH = 1536
const UNSAFE_VALUE_RE = /[;{}\\]|url\s*\(|@import|expression\s*\(|javascript:|https?:|data:/i
const SAFE_CSS_FUNCTIONS = new Set([
  'rgb',
  'rgba',
  'hsl',
  'hsla',
  'hwb',
  'lab',
  'lch',
  'oklab',
  'oklch',
  'color',
  'color-mix',
  'linear-gradient',
  'radial-gradient',
  'conic-gradient',
  'repeating-linear-gradient',
  'repeating-radial-gradient',
  'repeating-conic-gradient',
  'var',
  'calc',
  'min',
  'max',
  'clamp',
  'cubic-bezier',
  'steps',
  'brightness',
  'saturate',
  'contrast',
  'blur',
  'grayscale',
  'sepia',
  'hue-rotate',
  'invert',
  'opacity',
  'drop-shadow',
  'translate',
  'translatex',
  'translatey',
  'translatez',
  'translate3d',
  'scale',
  'scalex',
  'scaley',
  'rotate',
  'rotatex',
  'rotatey',
  'rotatez',
  'skew',
  'skewx',
  'skewy',
  'matrix',
  'perspective',
])

const LENGTH_TOKENS = new Set([
  '--ah-scrollbar-size',
  '--ah-scrollbar-radius',
  '--ah-radius',
  '--ah-radius-sm',
  '--ah-window-radius',
  '--ah-font-size',
  '--ah-font-size-sm',
  '--ah-letter-spacing',
  '--ah-icon-size',
  '--ah-icon-tile-width',
  '--ah-taskbar-height',
  '--ah-titlebar-height',
  '--ah-space-xs',
  '--ah-space-sm',
  '--ah-space-md',
  '--ah-space-lg',
  '--ah-dock-radius',
  '--ah-fx-panel-rise',
])
const SHADOW_TOKENS = new Set([
  '--ah-icon-label-shadow',
  '--ah-icon-drag-shadow',
  '--ah-elevation-shadow',
  '--ah-elevation-shadow-active',
  '--ah-panel-shadow',
  '--ah-taskbar-shadow',
  '--ah-focus-ring',
  '--ah-window-shadow',
  '--ah-window-shadow-active',
  '--ah-dock-shadow',
  '--ah-drop-glow',
])
const BACKGROUND_TOKENS = new Set([
  '--ah-titlebar',
  '--ah-draft-hero-bg',
  '--ah-draft-card-bg',
  '--ah-draft-icon-bg',
  '--ah-wallpaper',
  '--ah-start-hero-bg',
  '--ah-dock-bg',
  '--ah-drop-hint-bg',
])
const FILTER_TOKENS = new Set([
  '--ah-wallpaper-filter',
  '--ah-wallpaper-drop-filter',
  '--ah-blur',
  '--ah-panel-blur',
])
const TIME_TOKENS = new Set([
  '--ah-motion-fast',
  '--ah-motion-normal',
  '--ah-motion-slow',
  '--ah-motion-pop-duration',
  '--ah-motion-panel-duration',
  '--ah-motion-hover-duration',
])
const EASING_TOKENS = new Set([
  '--ah-ease-out',
  '--ah-ease-standard',
  '--ah-ease-spring',
])
const FONT_TOKENS = new Set(['--ah-font'])
const NUMBER_TOKENS = new Set([
  '--ah-font-weight',
  '--ah-fx-pop-scale-from',
  '--ah-fx-pop-scale-to',
  '--ah-fx-panel-scale-from',
  '--ah-fx-icon-lift',
])

const PROPERTY_SUPPORT = {
  color: 'color',
  background: 'background',
  'background-color': 'background-color',
  'background-image': 'background-image',
  border: 'border',
  'border-color': 'border-color',
  'border-top-color': 'border-top-color',
  'border-right-color': 'border-right-color',
  'border-bottom-color': 'border-bottom-color',
  'border-left-color': 'border-left-color',
  'border-width': 'border-width',
  'border-style': 'border-style',
  'border-radius': 'border-radius',
  outline: 'outline',
  'outline-color': 'outline-color',
  'outline-offset': 'outline-offset',
  'box-shadow': 'box-shadow',
  'text-shadow': 'text-shadow',
  'backdrop-filter': 'backdrop-filter',
  '-webkit-backdrop-filter': 'backdrop-filter',
  filter: 'filter',
  opacity: 'opacity',
  transform: 'transform',
  'transform-origin': 'transform-origin',
  transition: 'transition',
  'transition-duration': 'transition-duration',
  'transition-timing-function': 'transition-timing-function',
  'transition-property': 'transition-property',
  'animation-duration': 'animation-duration',
  'animation-timing-function': 'animation-timing-function',
  'animation-delay': 'animation-delay',
  'font-family': 'font-family',
  'font-size': 'font-size',
  'font-weight': 'font-weight',
  'font-style': 'font-style',
  'letter-spacing': 'letter-spacing',
  'line-height': 'line-height',
  'text-transform': 'text-transform',
  padding: 'padding',
  'padding-top': 'padding-top',
  'padding-right': 'padding-right',
  'padding-bottom': 'padding-bottom',
  'padding-left': 'padding-left',
  margin: 'margin',
  'margin-top': 'margin-top',
  'margin-right': 'margin-right',
  'margin-bottom': 'margin-bottom',
  'margin-left': 'margin-left',
  gap: 'gap',
  'row-gap': 'row-gap',
  'column-gap': 'column-gap',
  width: 'width',
  'min-width': 'min-width',
  'max-width': 'max-width',
  height: 'height',
  'min-height': 'min-height',
  'max-height': 'max-height',
  overflow: 'overflow',
  'overflow-x': 'overflow-x',
  'overflow-y': 'overflow-y',
  visibility: 'visibility',
}

export function normalizeDesktopThemeTokenKey(rawKey) {
  const key = typeof rawKey === 'string' ? rawKey.trim() : ''
  if (!key) return ''
  return key.startsWith('--') ? key : `--${key}`
}

function cssPropertyForToken(token) {
  if (LENGTH_TOKENS.has(token)) {
    if (token.includes('radius')) return 'border-radius'
    if (token.includes('letter-spacing')) return 'letter-spacing'
    if (token.includes('font-size') || token === '--ah-icon-size') return 'font-size'
    return 'width'
  }
  if (SHADOW_TOKENS.has(token)) {
    return token === '--ah-icon-label-shadow' ? 'text-shadow' : 'box-shadow'
  }
  if (BACKGROUND_TOKENS.has(token)) return 'background'
  if (FILTER_TOKENS.has(token)) {
    return token === '--ah-blur' || token === '--ah-panel-blur' ? 'backdrop-filter' : 'filter'
  }
  if (TIME_TOKENS.has(token)) return 'transition-duration'
  if (EASING_TOKENS.has(token)) return 'transition-timing-function'
  if (FONT_TOKENS.has(token)) return 'font-family'
  if (NUMBER_TOKENS.has(token)) {
    return token === '--ah-font-weight' ? 'font-weight' : 'opacity'
  }
  return 'color'
}

function hasOnlyKnownVariableReferences(value) {
  const refs = [...value.matchAll(/var\(\s*(--[a-z0-9-]+)/gi)]
  return refs.every((match) => TOKEN_SET.has(match[1]))
}

function hasOnlySafeCssFunctions(value) {
  const functions = [...value.matchAll(/([a-z][a-z0-9-]*)\s*\(/gi)]
  return functions.every((match) => SAFE_CSS_FUNCTIONS.has(match[1].toLowerCase()))
}

function resolveSupportsCss(options = {}) {
  return options.supportsCss ??
    (typeof CSS !== 'undefined' && typeof CSS.supports === 'function'
      ? CSS.supports.bind(CSS)
      : null)
}

function assertSafeCssValue(label, value, cssProperty, options = {}) {
  if (typeof value !== 'string') {
    throw new Error(`${label} must be a string`)
  }
  // Theme authors often paste `!important`; Hub scopes selectors instead — strip so CSS.supports passes.
  const trimmed = value.trim().replace(/\s*!important\s*$/i, '').trim()
  if (!trimmed) throw new Error(`${label} cannot be empty`)
  if (trimmed.length > MAX_VALUE_LENGTH) {
    throw new Error(`${label} exceeds ${MAX_VALUE_LENGTH} characters`)
  }
  if (UNSAFE_VALUE_RE.test(trimmed)) {
    throw new Error(`Unsafe CSS value for ${label}`)
  }
  if (!hasOnlyKnownVariableReferences(trimmed)) {
    throw new Error(`Unknown CSS variable reference in ${label}`)
  }
  if (!hasOnlySafeCssFunctions(trimmed)) {
    throw new Error(`Unsafe CSS function in ${label}`)
  }

  const supportsCss = resolveSupportsCss(options)
  if (supportsCss && !supportsCss(cssProperty, trimmed)) {
    if (
      (cssProperty === 'backdrop-filter' || cssProperty === '-webkit-backdrop-filter')
      && supportsCss('filter', trimmed)
    ) {
      return trimmed
    }
    // Unitless scales (0.98) validate poorly as opacity in some engines — allow simple numbers.
    if (cssProperty === 'opacity' && /^-?\d+(\.\d+)?$/.test(trimmed)) {
      return trimmed
    }
    throw new Error(`Invalid CSS value for ${label}`)
  }
  return trimmed
}

export function validateDesktopThemeTokenValue(token, rawValue, options = {}) {
  if (!TOKEN_SET.has(token)) {
    throw new Error(`Unknown desktop theme token: ${token}`)
  }
  return assertSafeCssValue(
    `Desktop theme token ${token}`,
    rawValue,
    cssPropertyForToken(token),
    options,
  )
}

export function validateDesktopThemeRuleValue(property, rawValue, options = {}) {
  const cssProperty = PROPERTY_SUPPORT[property] ?? property
  return assertSafeCssValue(
    `Desktop theme property ${property}`,
    rawValue,
    cssProperty,
    options,
  )
}

export function normalizeDesktopThemeRequest(payload, options = {}) {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw new Error('Desktop theme payload must be an object')
  }

  const mode = String(payload.mode ?? '').trim().toLowerCase()
  if (mode === 'dark' || mode === 'light' || mode === 'auto') {
    return { mode, tokens: {}, rules: [], skin: null }
  }
  if (mode !== 'custom') {
    throw new Error('Desktop theme mode must be custom, dark, light, or auto')
  }

  const rawTokens = payload.tokens
  const hasTokens = rawTokens != null
  if (hasTokens && (typeof rawTokens !== 'object' || Array.isArray(rawTokens))) {
    throw new Error('Custom desktop theme tokens must be an object')
  }

  const tokens = {}
  if (hasTokens) {
    const entries = Object.entries(rawTokens)
    if (entries.length > DESKTOP_THEME_TOKENS.length) {
      throw new Error(`Custom desktop theme supports at most ${DESKTOP_THEME_TOKENS.length} tokens`)
    }
    for (const [rawKey, rawValue] of entries) {
      const token = normalizeDesktopThemeTokenKey(rawKey)
      if (!TOKEN_SET.has(token)) {
        throw new Error(`Unknown desktop theme token: ${rawKey}`)
      }
      if (Object.prototype.hasOwnProperty.call(tokens, token)) {
        throw new Error(`Duplicate desktop theme token: ${rawKey}`)
      }
      tokens[token] = validateDesktopThemeTokenValue(token, rawValue, options)
    }
  }

  const rules = normalizeDesktopThemeRules(payload.rules, {
    validateValue: (property, value) => validateDesktopThemeRuleValue(property, value, options),
  })

  if (Object.keys(tokens).length === 0 && rules.length === 0) {
    throw new Error('Custom desktop theme requires tokens and/or rules')
  }

  const skin = payload.skin == null || payload.skin === ''
    ? 'classic'
    : normalizeDesktopThemeSkin(payload.skin)

  return { mode: 'custom', tokens, rules, skin }
}

export function applyDesktopThemeTokens(style, tokens = {}) {
  if (!style) return
  for (const token of DESKTOP_THEME_TOKENS) style.removeProperty(token)
  for (const [token, value] of Object.entries(tokens)) style.setProperty(token, value)
}

export function desktopThemeStyleObject(tokens = {}) {
  const style = {}
  for (const [token, value] of Object.entries(tokens)) {
    if (TOKEN_SET.has(token)) style[token] = value
  }
  return style
}

export function desktopThemeCustomCssText(rules = []) {
  return renderDesktopThemeCustomCss(rules)
}
