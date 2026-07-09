/** @type {Record<string, string>} */
let cachedPrompts = Object.freeze({})

/**
 * @param {Record<string, string>|null|undefined} prompts
 */
export function setParentBridgeScopePrompts(prompts) {
  if (!prompts || typeof prompts !== 'object' || Array.isArray(prompts)) {
    cachedPrompts = Object.freeze({})
    return
  }
  const next = {}
  for (const [scope, template] of Object.entries(prompts)) {
    if (typeof scope === 'string' && scope !== '' && typeof template === 'string' && template.trim() !== '') {
      next[scope] = template.trim()
    }
  }
  cachedPrompts = Object.freeze(next)
}

/** @returns {Readonly<Record<string, string>>} */
export function getParentBridgeScopePrompts() {
  return cachedPrompts
}

/**
 * @param {string} scope
 * @param {string} appLabel
 * @param {(key: string) => string} translate
 */
export function parentBridgeScopeLabel(scope, appLabel, translate) {
  const template = cachedPrompts[scope]
  if (template) {
    return template.replace(/\{app\}/g, appLabel).replace(/\{scope\}/g, scope)
  }
  const fallback = translate('bridge_perm_parent_default')
  return fallback.replace(/\{app\}/g, appLabel).replace(/\{scope\}/g, scope)
}

/**
 * @param {() => Promise<{ data?: { data?: { prompts?: Record<string, string> } } }>|null>} fetchPrompts
 */
export async function loadParentBridgeScopePrompts(fetchPrompts) {
  if (typeof fetchPrompts !== 'function') return
  try {
    const res = await fetchPrompts()
    const prompts = res?.data?.data?.prompts ?? res?.data?.prompts ?? null
    setParentBridgeScopePrompts(prompts)
  } catch {
    setParentBridgeScopePrompts(null)
  }
}
