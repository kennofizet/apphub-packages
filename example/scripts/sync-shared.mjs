import { copyFileSync, existsSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const exampleRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const source = join(exampleRoot, 'shared', 'publisher-bridge.js')
const targets = [
  join(exampleRoot, 'demo-simple', 'html', 'publisher-bridge.js'),
  join(exampleRoot, 'demo-iframe', 'html', 'publisher-bridge.js'),
]

if (!existsSync(source)) {
  throw new Error(`Missing shared module: ${source}`)
}

for (const dest of targets) {
  copyFileSync(source, dest)
  console.log(`Synced → ${dest.replace(exampleRoot + '/', '')}`)
}
