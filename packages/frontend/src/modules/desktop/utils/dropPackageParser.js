import { clampString, isValidSlug } from '../../../utils/safeStorage.js'
import { resolveAppPermissions } from '../../../utils/resolveAppPermissions.js'

const MAX_MANIFEST_BYTES = 64 * 1024

/**
 * Parse dropped files into an install intent.
 * Hosted publish: drop .zip (manifest.json inside zip) → draft submit, await DEV approval.
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

  const zipFile = files.find((f) => /\.zip$/i.test(f.name))
  const jsonFile = files.find((f) =>
    f.name.endsWith('.apphub.json') || f.name === 'manifest.json' || f.type === 'application/json',
  )

  if (zipFile) {
    let manifest = null
    if (jsonFile) {
      manifest = await readManifestFile(jsonFile)
    }

    if (!manifest || isHostedPublishManifest(manifest)) {
      return buildPublishIntent(zipFile, manifest)
    }
  }

  if (jsonFile) {
    const manifest = await readManifestFile(jsonFile)
    if (!manifest) return null

    if (manifest.source === 'appstore' && manifest.slug) {
      const slug = normalizeSlug(manifest.slug)
      if (!slug) return null
      return {
        method: 'appstore',
        slug,
        name: clampString(manifest.name) || slug,
        icon: clampString(manifest.icon, 16) || '🛒',
        description: clampString(manifest.description, 500),
        permissions: resolveAppPermissions(manifest),
      }
    }

    if (manifest.source === 'local' || (manifest.name && !isHostedPublishManifest(manifest))) {
      const slug = normalizeSlug(manifest.slug ?? manifest.name ?? stripExt(jsonFile.name))
      if (!slug) return null
      return {
        method: 'local',
        slug,
        name: clampString(manifest.name) || stripExt(jsonFile.name),
        icon: clampString(manifest.icon, 16) || pickIcon(files),
        description: clampString(manifest.description, 500),
        permissions: resolveAppPermissions(manifest),
      }
    }
  }

  const primary = files[0]
  if (primary.size > 50 * 1024 * 1024) return null

  return {
    method: 'local',
    slug: slugify(stripExt(primary.name)),
    name: stripExt(primary.name),
    icon: pickIcon(files),
    description: '',
    fileName: primary.name,
  }
}

function isHostedPublishManifest(manifest) {
  if (!manifest || typeof manifest !== 'object') return false
  const runtime = typeof manifest.runtime_type === 'string' ? manifest.runtime_type.toLowerCase() : ''
  if (runtime === 'hosted') return true
  if (manifest.source === 'publish' || manifest.source === 'hosted') return true
  return Boolean(manifest.slug && manifest.name && !manifest.entry_url)
}

function buildPublishIntent(zipFile, manifest) {
  const slug = manifest?.slug ? normalizeSlug(manifest.slug) : null
  const name = manifest?.name
    ? clampString(manifest.name)
    : stripExt(zipFile.name)

  return {
    method: 'publish',
    zipFile,
    manifest,
    slug,
    name: name || stripExt(zipFile.name),
    icon: clampString(manifest?.icon, 16) || '📦',
    description: clampString(manifest?.description ?? manifest?.short_description, 500),
    permissions: resolveAppPermissions(manifest ?? {}),
  }
}

async function readManifestFile(jsonFile) {
  if (!jsonFile || jsonFile.size > MAX_MANIFEST_BYTES) return null
  try {
    const text = await jsonFile.text()
    if (text.length > MAX_MANIFEST_BYTES) return null
    const manifest = JSON.parse(text)
    if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) return null
    return manifest
  } catch {
    return null
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
