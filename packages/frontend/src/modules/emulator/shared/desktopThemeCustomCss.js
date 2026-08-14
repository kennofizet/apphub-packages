export const DESKTOP_THEME_STYLE_ID = 'apphub-desktop-theme-custom'

/** Hub chrome selectors packs may target (single class chain + optional pseudo). */
export const DESKTOP_THEME_RULE_SELECTORS = Object.freeze([
  '.apphub-desktop',
  '.apphub-desktop__wallpaper',
  '.apphub-desktop__icon',
  '.apphub-desktop__icon:hover',
  '.apphub-desktop__icon-label',
  '.apphub-desktop__icon-img',
  '.apphub-desktop__taskbar',
  '.apphub-desktop__task',
  '.apphub-desktop__task:hover',
  '.apphub-desktop__task.active',
  '.apphub-desktop__task.active::after',
  '.apphub-desktop__clock',
  '.apphub-start-btn',
  '.apphub-start-btn:hover',
  '.apphub-start__panel',
  '.apphub-start__hero',
  '.apphub-start__app-tile',
  '.apphub-start__app-tile:hover',
  '.apphub-start__search',
  '.apphub-start__search:focus',
  '.apphub-win',
  '.apphub-win--active',
  '.apphub-win__titlebar',
  '.apphub-win__body',
  '.apphub-win__btn',
  '.apphub-win__btn:hover',
  '.apphub-store',
  '.apphub-store__card',
  '.apphub-store__search',
  '.apphub-draft-store',
  '.apphub-draft-card',
  '.apphub-publish',
  '.apphub-icon-menu',
  '.apphub-icon-menu__item',
  '.apphub-icon-menu__item:hover',
  '.apphub-icon-folder__panel',
  '.apphub-mobile-dock',
  '.apphub-mobile-status',
  '.apphub-mobile-home-indicator',
  '.apphub-mobile-control-center__panel',
  '.apphub-drop-layer__hint',
  '.apphub-drop-layer__glow',
  '.apphub-drop-badge__label',
  '.apphub-install-perm-dialog__panel',
])

export const DESKTOP_THEME_RULE_PROPERTIES = Object.freeze([
  'color',
  'background',
  'background-color',
  'background-image',
  'border',
  'border-color',
  'border-top-color',
  'border-right-color',
  'border-bottom-color',
  'border-left-color',
  'border-width',
  'border-style',
  'border-radius',
  'outline',
  'outline-color',
  'outline-offset',
  'box-shadow',
  'text-shadow',
  'backdrop-filter',
  '-webkit-backdrop-filter',
  'filter',
  'opacity',
  'transform',
  'transform-origin',
  'transition',
  'transition-duration',
  'transition-timing-function',
  'transition-property',
  'animation-duration',
  'animation-timing-function',
  'animation-delay',
  'font-family',
  'font-size',
  'font-weight',
  'font-style',
  'letter-spacing',
  'line-height',
  'text-transform',
  'padding',
  'padding-top',
  'padding-right',
  'padding-bottom',
  'padding-left',
  'margin',
  'margin-top',
  'margin-right',
  'margin-bottom',
  'margin-left',
  'gap',
  'row-gap',
  'column-gap',
  'width',
  'min-width',
  'max-width',
  'height',
  'min-height',
  'max-height',
  'overflow',
  'overflow-x',
  'overflow-y',
  // Safer hide than display:none — keeps layout/bridge chrome reachable
  'visibility',
])

const SELECTOR_SET = new Set(DESKTOP_THEME_RULE_SELECTORS)
const PROPERTY_SET = new Set(DESKTOP_THEME_RULE_PROPERTIES)
const MAX_RULES = 48
const MAX_PROPS_PER_RULE = 28

/**
 * @param {string} selector
 * @returns {string}
 */
export function scopeDesktopThemeSelector(selector) {
  const trimmed = String(selector ?? '').trim()
  if (trimmed === '.apphub-desktop') {
    return '.apphub-desktop.apphub-desktop--custom-theme'
  }
  const root = '.apphub-desktop.apphub-desktop--custom-theme'
  const scoped = `${root} ${trimmed}`
  // Skin themes draw shape on chrome plates / rings / faces — mirror icon rules onto
  // those nodes (and raise specificity vs data-ah-skin plate CSS) so Theme Studio
  // border-radius / border / shadow edits show on the real desktop, not only preview.
  if (trimmed === '.apphub-desktop__icon' || trimmed === '.apphub-desktop__icon:hover') {
    const iconSel = trimmed
    const under = (extra) => `${root} ${iconSel} ${extra}`
    const underSkin = (extra) => `${root}[data-ah-skin] ${iconSel} ${extra}`
    return [
      scoped,
      `${root}[data-ah-skin] ${iconSel}`,
      under('.apphub-skin-chrome'),
      under('.apphub-skin-chrome__plate'),
      under('.apphub-skin-chrome__plate[class*="plate--"]'),
      under('.apphub-skin-chrome__ring'),
      under('.apphub-skin-chrome__shell'),
      under('.apphub-skin-chrome__face'),
      under('.apphub-skin-chrome__face--round'),
      under('.apphub-desktop__icon-img-wrap'),
      under('.apphub-desktop__icon-img-wrap--skin'),
      underSkin('.apphub-skin-chrome__plate'),
      underSkin('.apphub-skin-chrome__plate[class*="plate--"]'),
      underSkin('.apphub-skin-chrome__ring'),
      underSkin('.apphub-skin-chrome__face'),
      underSkin('.apphub-skin-chrome__face--round'),
    ].join(',')
  }
  if (trimmed === '.apphub-desktop__icon-img') {
    return [
      scoped,
      `${scoped}.apphub-desktop__icon-skin-glyph`,
      `${root} .apphub-skin-chrome__face ${trimmed}`,
      `${root} .apphub-skin-chrome__face .apphub-desktop__icon-skin-glyph`,
      `${root}[data-ah-skin] .apphub-skin-chrome__face ${trimmed}`,
      `${root}[data-ah-skin] .apphub-skin-chrome__face .apphub-desktop__icon-skin-glyph`,
    ].join(',')
  }
  return scoped
}

/**
 * @param {Array<{ selector: string, props: Record<string, string> }>} rules
 * @returns {string}
 */
export function renderDesktopThemeCustomCss(rules = []) {
  if (!Array.isArray(rules) || rules.length === 0) return ''
  const blocks = []
  for (const rule of rules) {
    const selector = scopeDesktopThemeSelector(rule.selector)
    const decls = Object.entries(rule.props ?? {})
      .map(([prop, value]) => `${prop}:${value}`)
      .join(';')
    if (!decls) continue
    blocks.push(`${selector}{${decls}}`)
  }
  return blocks.join('')
}

/**
 * @param {unknown} raw
 * @param {{ validateValue?: (property: string, value: string) => string }} [options]
 * @returns {Array<{ selector: string, props: Record<string, string> }>}
 */
export function normalizeDesktopThemeRules(raw, options = {}) {
  if (raw == null) return []
  if (!Array.isArray(raw)) {
    throw new Error('Custom desktop theme rules must be an array')
  }
  if (raw.length > MAX_RULES) {
    throw new Error(`Custom desktop theme supports at most ${MAX_RULES} rules`)
  }

  const rules = []
  const seen = new Set()
  for (const item of raw) {
    if (!item || typeof item !== 'object' || Array.isArray(item)) {
      throw new Error('Each desktop theme rule must be an object')
    }
    const selector = String(item.selector ?? '').trim()
    if (!SELECTOR_SET.has(selector)) {
      throw new Error(`Unsupported desktop theme selector: ${selector || '(empty)'}`)
    }
    if (seen.has(selector)) {
      throw new Error(`Duplicate desktop theme selector: ${selector}`)
    }
    seen.add(selector)

    const rawProps = item.props ?? item.declarations
    if (!rawProps || typeof rawProps !== 'object' || Array.isArray(rawProps)) {
      throw new Error(`Desktop theme rule ${selector} requires a props object`)
    }
    const entries = Object.entries(rawProps)
    if (entries.length === 0) {
      throw new Error(`Desktop theme rule ${selector} requires at least one property`)
    }
    if (entries.length > MAX_PROPS_PER_RULE) {
      throw new Error(`Desktop theme rule ${selector} supports at most ${MAX_PROPS_PER_RULE} properties`)
    }

    const props = {}
    for (const [rawProp, rawValue] of entries) {
      const property = String(rawProp ?? '').trim().toLowerCase()
      if (!PROPERTY_SET.has(property)) {
        throw new Error(`Unsupported desktop theme property: ${rawProp}`)
      }
      if (Object.prototype.hasOwnProperty.call(props, property)) {
        throw new Error(`Duplicate desktop theme property in ${selector}: ${property}`)
      }
      const value = typeof options.validateValue === 'function'
        ? options.validateValue(property, rawValue)
        : String(rawValue ?? '').trim()
      props[property] = value
    }
    rules.push({ selector, props })
  }
  return rules
}

/**
 * @param {string} cssText
 * @param {{ doc?: Document, styleId?: string }} [options]
 */
export function mountDesktopThemeCustomCss(cssText, options = {}) {
  const doc = options.doc ?? (typeof document !== 'undefined' ? document : null)
  const styleId = options.styleId ?? DESKTOP_THEME_STYLE_ID
  if (!doc) return null

  let el = doc.getElementById(styleId)
  if (!cssText) {
    el?.remove()
    return null
  }
  if (!el) {
    el = doc.createElement('style')
    el.id = styleId
    el.setAttribute('data-apphub-desktop-theme', 'custom')
    doc.head.appendChild(el)
  }
  el.textContent = cssText
  return el
}

export function unmountDesktopThemeCustomCss(options = {}) {
  mountDesktopThemeCustomCss('', options)
}
