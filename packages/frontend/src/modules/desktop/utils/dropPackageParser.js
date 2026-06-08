import { clampString, isValidSlug } from '../../../utils/safeStorage.js'

const MAX_MANIFEST_BYTES = 64 * 1024

/**
 * Parse dropped files into an install intent (app store slug or local package).
 */
export async function parseDropFiles(dataTransfer) {
  let files = [...(dataTransfer?.files ?? [])]

  if (!files.length && dataTransfer?.items?.length) {
    for (const item of dataTransfer.items) {
      if (item.kind === 'file') {
        const file = item.getAsFile()
        if (file) files.push(file)
      }
    }
  }

  if (!files.length) return null

  const jsonFile = files.find((f) =>
    f.name.endsWith('.apphub.json') || f.name === 'manifest.json' || f.type === 'application/json',
  )

  if (jsonFile) {
    if (jsonFile.size > MAX_MANIFEST_BYTES) return null
    try {
      const text = await jsonFile.text()
      if (text.length > MAX_MANIFEST_BYTES) return null
      const manifest = JSON.parse(text)
      if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) return null

      if (manifest.source === 'appstore' && manifest.slug) {
        const slug = normalizeSlug(manifest.slug)
        if (!slug) return null
        return {
          method: 'appstore',
          slug,
          name: clampString(manifest.name) || slug,
          icon: clampString(manifest.icon, 16) || '🛒',
          description: clampString(manifest.description, 500),
        }
      }
      if (manifest.source === 'local' || manifest.name) {
        const slug = normalizeSlug(manifest.slug ?? manifest.name ?? stripExt(jsonFile.name))
        if (!slug) return null
        return {
          method: 'local',
          slug,
          name: clampString(manifest.name) || stripExt(jsonFile.name),
          icon: clampString(manifest.icon, 16) || pickIcon(files),
          description: clampString(manifest.description, 500),
        }
      }
    } catch {
      /* fall through */
    }
  }

  const primary = files[0]
  if (primary.size > 50 * 1024 * 1024) return null
  const isArchive = /\.(zip|apphub|tar|gz)$/i.test(primary.name)

  return {
    method: 'local',
    slug: slugify(stripExt(primary.name)),
    name: stripExt(primary.name),
    icon: isArchive ? '📦' : pickIcon(files),
    description: '',
    fileName: primary.name,
  }
}

function stripExt(name) {
  return name.replace(/\.[^.]+$/, '')
}

function normalizeSlug(value) {
  const slug = slugify(value)
  return isValidSlug(slug) ? slug : null
}

function slugify(value) {
  return String(value)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '') || 'dropped-app'
}

function pickIcon(files) {
  const img = files.find((f) => f.type.startsWith('image/'))
  if (img) return '🖼️'
  if (files.some((f) => /\.zip$/i.test(f.name))) return '📦'
  return '📄'
}
