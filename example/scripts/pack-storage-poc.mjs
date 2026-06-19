import { existsSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { updateManifestVersion } from './lib/manifest.mjs'
import { removeZipFilesIn, zipFiles } from './lib/zip.mjs'

const exampleRoot = join(fileURLToPath(import.meta.url), '..', '..')
const releaseDir = join(exampleRoot, 'release')

const APPS = [
  { dir: 'writer', zip: 'demo-storage-writer.zip', files: ['manifest.json', 'index.html', 'styles.css', 'app.js'] },
  { dir: 'reader', zip: 'demo-storage-reader.zip', files: ['manifest.json', 'index.html', 'styles.css', 'app.js'] },
  { dir: 'reader-b', zip: 'demo-storage-reader-b.zip', files: ['manifest.json', 'index.html', 'styles.css', 'app.js'] },
]

console.log('Storage POC — pack 3 hosted zips\n')

const removed = removeZipFilesIn(releaseDir)
if (removed > 0) console.log(`Removed ${removed} old zip(s) in release/\n`)

for (const app of APPS) {
  const root = join(exampleRoot, 'demo-storage-poc', app.dir)
  const manifestPath = join(root, 'manifest.json')
  const meta = updateManifestVersion(manifestPath)
  const files = app.files.map((name) => join(root, name))
  const missing = files.filter((p) => !existsSync(p))
  if (missing.length) {
    throw new Error(`Missing: ${missing.join(', ')}`)
  }
  const out = join(releaseDir, app.zip)
  await zipFiles(files, out)
  console.log(`Packed ${app.zip} (v${meta.version}, ${meta.manifest.slug})`)
}

console.log('\nDrop all three from example/release/ on Hub desktop.')
