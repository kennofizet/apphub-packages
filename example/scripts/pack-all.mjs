import { cpSync, existsSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'
import { updateManifestVersion } from './lib/manifest.mjs'
import { removeZipFilesIn, zipDirectory, zipFiles } from './lib/zip.mjs'

const exampleRoot = join(fileURLToPath(import.meta.url), '..', '..')
const htmlRoot = join(exampleRoot, 'demo-simple', 'html')
const vueRoot = join(exampleRoot, 'demo-simple', 'vue')
const iframeRoot = join(exampleRoot, 'demo-iframe', 'html')
const releaseDir = join(exampleRoot, 'release')

const htmlManifestPath = join(htmlRoot, 'manifest.json')
const vueManifestPath = join(vueRoot, 'manifest.json')
const iframeManifestPath = join(iframeRoot, 'manifest.json')
const htmlZipPath = join(releaseDir, 'demo-simple-html.zip')
const vueZipPath = join(releaseDir, 'demo-simple-vue.zip')
const iframeManifestReleasePath = join(releaseDir, 'demo-iframe-manifest.json')

const HTML_FILES = ['manifest.json', 'index.html', 'styles.css', 'app.js']

console.log('App Hub examples — bump manifests + pack zips\n')

const removed = [
  removeZipFilesIn(releaseDir),
  removeZipFilesIn(join(htmlRoot, 'release')),
  removeZipFilesIn(htmlRoot),
  removeZipFilesIn(join(vueRoot, 'release')),
].reduce((sum, n) => sum + n, 0)

if (removed > 0) {
  console.log(`Removed ${removed} old zip file(s)\n`)
}

const htmlMeta = updateManifestVersion(htmlManifestPath)
console.log(`HTML  ${htmlMeta.previousVersion} → ${htmlMeta.version}  (${htmlMeta.manifest.slug})`)

const vueMeta = updateManifestVersion(vueManifestPath)
console.log(`Vue   ${vueMeta.previousVersion} → ${vueMeta.version}  (${vueMeta.manifest.slug})`)

const iframeMeta = updateManifestVersion(iframeManifestPath)
console.log(`Iframe ${iframeMeta.previousVersion} → ${iframeMeta.version}  (${iframeMeta.manifest.slug})\n`)

await packHtml()
await packVue()
packIframeManifest()

console.log('\nDone — drop these on App Hub desktop:')
console.log(`  ${htmlZipPath}`)
console.log(`  ${vueZipPath}`)
console.log(`  ${iframeManifestReleasePath}  (serve demo-iframe first — see demo-iframe/README.md)`)

async function packHtml() {
  const files = HTML_FILES.map((name) => join(htmlRoot, name))
  const missing = files.filter((path) => !existsSync(path))
  if (missing.length) {
    throw new Error(`HTML demo missing: ${missing.join(', ')}`)
  }
  await zipFiles(files, htmlZipPath)
  console.log(`Packed HTML → release/demo-simple-html.zip (v${htmlMeta.version})`)
}

async function packVue() {
  if (!existsSync(join(vueRoot, 'node_modules'))) {
    console.log('Vue: npm install…')
    execSync('npm install', { cwd: vueRoot, stdio: 'inherit' })
  }

  console.log('Vue: npm run build…')
  execSync('npm run build', { cwd: vueRoot, stdio: 'inherit' })

  const dist = join(vueRoot, 'dist')
  cpSync(vueManifestPath, join(dist, 'manifest.json'))

  await zipDirectory(dist, vueZipPath)
  console.log(`Packed Vue  → release/demo-simple-vue.zip (v${vueMeta.version})`)
}

function packIframeManifest() {
  if (!existsSync(iframeManifestPath)) {
    throw new Error(`Iframe demo missing: ${iframeManifestPath}`)
  }
  const text = readFileSync(iframeManifestPath, 'utf8')
  writeFileSync(iframeManifestReleasePath, text)
  console.log(`Copied iframe manifest → release/demo-iframe-manifest.json (v${iframeMeta.version})`)
}
