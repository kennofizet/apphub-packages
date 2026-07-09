import { readFileSync, writeFileSync } from 'node:fs'

/** @param {string} version */
export function bumpPatchVersion(version) {
  const match = /^(\d+)\.(\d+)\.(\d+)(?:-.+)?$/.exec(String(version).trim())
  if (!match) {
    throw new Error(`Invalid semver in manifest: ${version}`)
  }
  return `${match[1]}.${match[2]}.${Number(match[3]) + 1}`
}

/** @param {string} manifestPath */
export function readManifest(manifestPath) {
  const raw = readFileSync(manifestPath, 'utf8').replace(/^\uFEFF/, '')
  return JSON.parse(raw)
}

/**
 * @param {string} manifestPath
 * @param {{ bump?: boolean }} [options]
 * @returns {{ manifest: Record<string, unknown>, version: string, previousVersion: string }}
 */
export function updateManifestVersion(manifestPath, { bump = true } = {}) {
  const manifest = readManifest(manifestPath)
  const previousVersion = String(manifest.version ?? '')
  const version = bump ? bumpPatchVersion(previousVersion) : previousVersion
  manifest.version = version
  writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8')
  return { manifest, version, previousVersion }
}
