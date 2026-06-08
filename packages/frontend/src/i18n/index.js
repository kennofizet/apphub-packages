import en from './translations/en.js'
import vi from './translations/vi.js'
import { resolveLang } from './resolveLang.js'

const catalogs = { en, vi }

export function t(key, lang = 'vi', params = {}) {
  const code = resolveLang(lang)
  let text = catalogs[code]?.[key] ?? catalogs.en?.[key] ?? String(key)
  if (params && typeof params === 'object') {
    Object.entries(params).forEach(([k, v]) => {
      text = text.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v ?? ''))
    })
  }
  return text
}
