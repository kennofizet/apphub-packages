import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import {
  DESKTOP_THEME_RULE_SELECTORS,
  normalizeDesktopThemeRules,
  renderDesktopThemeCustomCss,
  scopeDesktopThemeSelector,
} from '../src/modules/emulator/shared/desktopThemeCustomCss.js'
import {
  applyDesktopThemeTokens,
  DESKTOP_THEME_TOKENS,
  normalizeDesktopThemeRequest,
} from '../src/modules/emulator/shared/desktopThemePack.js'
import {
  clearDesktopThemePack,
  desktopThemeStorageKey,
  loadDesktopThemePack,
  saveDesktopThemePack,
} from '../src/modules/emulator/shared/desktopThemeStorage.js'

function test(name, callback) {
  try {
    callback()
    console.log(`ok - ${name}`)
  } catch (error) {
    console.error(`not ok - ${name}`)
    throw error
  }
}

const themeCss = await readFile(
  new URL('../src/modules/emulator/pc/styles/theme.css', import.meta.url),
  'utf8',
)

test('allowlist exactly matches canonical theme.css definitions', () => {
  const fromCss = [...themeCss.matchAll(/^\s*(--ah-[a-z0-9-]+)\s*:/gim)]
    .map((match) => match[1])
  assert.deepEqual([...new Set(fromCss)].sort(), [...DESKTOP_THEME_TOKENS].sort())
})

test('normalizes custom token keys and values', () => {
  const theme = normalizeDesktopThemeRequest({
    mode: 'custom',
    tokens: {
      'ah-accent': '#2dd4bf',
      '--ah-surface': 'rgb(15 39 68)',
      'ah-draft-card-bg': 'var(--ah-hover)',
      'ah-wallpaper': 'linear-gradient(160deg, #04121f 0%, #0f2744 100%)',
      'ah-panel-blur': 'blur(28px) saturate(1.5)',
      'ah-motion-pop-duration': '0.7s',
      'ah-ease-spring': 'cubic-bezier(0.34, 1.6, 0.64, 1)',
      'ah-window-shadow-active': '0 18px 50px rgba(45, 212, 191, 0.35)',
      'ah-font': '"Segoe UI", system-ui, sans-serif',
      'ah-fx-pop-scale-from': '0.7',
      'ah-fx-panel-rise': '28px',
    },
  }, { supportsCss: () => true })

  assert.equal(theme.mode, 'custom')
  assert.equal(theme.tokens['--ah-accent'], '#2dd4bf')
  assert.equal(theme.tokens['--ah-surface'], 'rgb(15 39 68)')
  assert.equal(theme.tokens['--ah-panel-blur'], 'blur(28px) saturate(1.5)')
  assert.equal(theme.tokens['--ah-ease-spring'], 'cubic-bezier(0.34, 1.6, 0.64, 1)')
  assert.equal(theme.tokens['--ah-fx-panel-rise'], '28px')
  assert.deepEqual(theme.rules, [])
})

test('accepts sandboxed custom rules for Hub chrome selectors', () => {
  const theme = normalizeDesktopThemeRequest({
    mode: 'custom',
    tokens: { 'ah-accent': '#2dd4bf' },
    rules: [
      {
        selector: '.apphub-win',
        props: {
          'border-radius': '18px',
          transform: 'perspective(900px) rotateX(1deg)',
          'box-shadow': '0 24px 60px rgba(45, 212, 191, 0.28)',
        },
      },
      {
        selector: '.apphub-desktop__wallpaper',
        props: {
          filter: 'saturate(1.25) contrast(1.05)',
        },
      },
    ],
  }, { supportsCss: () => true })

  assert.equal(theme.rules.length, 2)
  assert.equal(theme.rules[0].selector, '.apphub-win')
  assert.equal(theme.rules[0].props['border-radius'], '18px')
  assert.match(
    renderDesktopThemeCustomCss(theme.rules),
    /\.apphub-desktop\.apphub-desktop--custom-theme \.apphub-win\{/,
  )
  assert.equal(
    scopeDesktopThemeSelector('.apphub-desktop'),
    '.apphub-desktop.apphub-desktop--custom-theme',
  )
  assert.ok(DESKTOP_THEME_RULE_SELECTORS.includes('.apphub-mobile-dock'))
})

test('rules-only packs are valid', () => {
  const theme = normalizeDesktopThemeRequest({
    mode: 'custom',
    rules: [{
      selector: '.apphub-start__panel',
      props: { 'backdrop-filter': 'blur(40px) saturate(1.8)' },
    }],
  }, { supportsCss: () => true })
  assert.deepEqual(theme.tokens, {})
  assert.equal(theme.rules.length, 1)
})

test('rejects unknown and unsafe tokens', () => {
  assert.throws(
    () => normalizeDesktopThemeRequest({
      mode: 'custom',
      tokens: { 'ah-not-real': '#fff' },
    }),
    /Unknown desktop theme token/,
  )
  assert.throws(
    () => normalizeDesktopThemeRequest({
      mode: 'custom',
      tokens: { 'ah-titlebar': 'url(https://example.test/theme.png)' },
    }),
    /Unsafe CSS value/,
  )
  assert.throws(
    () => normalizeDesktopThemeRequest({
      mode: 'custom',
      tokens: { 'ah-surface': 'var(--unknown-token)' },
    }),
    /Unknown CSS variable reference/,
  )
  assert.throws(
    () => normalizeDesktopThemeRequest({
      mode: 'custom',
      tokens: { 'ah-titlebar': 'image-set("https://example.test/theme.png" 1x)' },
    }),
    /Unsafe CSS value|Unsafe CSS function/,
  )
})

test('rejects unsupported selectors and properties', () => {
  assert.throws(
    () => normalizeDesktopThemeRules([{ selector: 'body', props: { color: '#fff' } }]),
    /Unsupported desktop theme selector/,
  )
  assert.throws(
    () => normalizeDesktopThemeRules([{
      selector: '.apphub-win',
      props: { 'pointer-events': 'none' },
    }]),
    /Unsupported desktop theme property/,
  )
})

test('rejects values unsupported for their token category', () => {
  assert.throws(
    () => normalizeDesktopThemeRequest({
      mode: 'custom',
      tokens: { 'ah-accent': 'not-a-color' },
    }, { supportsCss: () => false }),
    /Invalid CSS value/,
  )
})

test('applies custom properties and removes stale properties on reset', () => {
  const values = new Map()
  const style = {
    setProperty: (key, value) => values.set(key, value),
    removeProperty: (key) => values.delete(key),
  }
  values.set('--ah-text', '#fff')
  applyDesktopThemeTokens(style, { '--ah-accent': '#2dd4bf' })
  assert.equal(values.has('--ah-text'), false)
  assert.equal(values.get('--ah-accent'), '#2dd4bf')

  applyDesktopThemeTokens(style, {})
  assert.equal(values.size, 0)
})

test('persists packs under isolated backend and user keys', () => {
  const values = new Map()
  const storage = {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, value),
    removeItem: (key) => values.delete(key),
  }
  const userOne = desktopThemeStorageKey('https://hub.example/api/', 1)
  const userTwo = desktopThemeStorageKey('https://hub.example/api/', 2)
  const theme = normalizeDesktopThemeRequest({
    mode: 'custom',
    tokens: { 'ah-accent': '#2dd4bf' },
    rules: [{ selector: '.apphub-win', props: { 'border-radius': '20px' } }],
  }, { supportsCss: () => true })

  assert.notEqual(userOne, userTwo)
  assert.equal(saveDesktopThemePack(storage, userOne, theme), true)
  assert.deepEqual(loadDesktopThemePack(storage, userOne), theme)
  assert.equal(loadDesktopThemePack(storage, userTwo), null)
  clearDesktopThemePack(storage, userOne)
  assert.equal(loadDesktopThemePack(storage, userOne), null)
})

test('normalizes dark, light, and auto as clear requests', () => {
  for (const mode of ['dark', 'light', 'auto']) {
    assert.deepEqual(normalizeDesktopThemeRequest({ mode }), { mode, tokens: {}, rules: [] })
  }
})

console.log('Desktop theme pack tests passed.')
