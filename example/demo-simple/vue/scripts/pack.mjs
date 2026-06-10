import { cpSync, createWriteStream, existsSync, mkdirSync, readdirSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import { execSync } from 'node:child_process'
import archiver from 'archiver'

const root = join(fileURLToPath(import.meta.url), '..', '..')
const dist = join(root, 'dist')
const outDir = join(root, 'release')
const zipPath = join(outDir, 'demo-simple-vue.zip')

if (!existsSync(join(root, 'node_modules'))) {
  execSync('npm install', { cwd: root, stdio: 'inherit' })
}

execSync('npm run build', { cwd: root, stdio: 'inherit' })

cpSync(join(root, 'manifest.json'), join(dist, 'manifest.json'))

mkdirSync(outDir, { recursive: true })

const output = createWriteStream(zipPath)
const archive = archiver('zip', { zlib: { level: 9 } })

archive.pipe(output)
addDir(dist, dist)
await archive.finalize()

await new Promise((resolve, reject) => {
  output.on('close', resolve)
  output.on('error', reject)
})

console.log(`Created ${zipPath}`)

function addDir(base, dir) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name)
    const entry = relative(base, full).replace(/\\/g, '/')
    if (statSync(full).isDirectory()) {
      addDir(base, full)
    } else {
      archive.file(full, { name: entry })
    }
  }
}
