import { createWriteStream, existsSync, mkdirSync, readdirSync, statSync, unlinkSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import archiver from 'archiver'

/** @param {string} dir */
export function removeZipFilesIn(dir) {
  if (!existsSync(dir)) return 0
  let removed = 0
  for (const name of readdirSync(dir)) {
    if (!name.toLowerCase().endsWith('.zip')) continue
    unlinkSync(join(dir, name))
    removed += 1
  }
  return removed
}

/**
 * @param {string} sourceDir
 * @param {string} zipPath
 * @param {{ include?: (relativePath: string) => boolean }} [options]
 */
export async function zipDirectory(sourceDir, zipPath, options = {}) {
  mkdirSync(dirname(zipPath), { recursive: true })

  const output = createWriteStream(zipPath)
  const archive = archiver('zip', { zlib: { level: 9 } })

  archive.pipe(output)
  addDir(sourceDir, sourceDir, archive, options.include)
  await archive.finalize()

  await new Promise((resolve, reject) => {
    output.on('close', resolve)
    output.on('error', reject)
    archive.on('error', reject)
  })
}

/**
 * @param {string[]} files — absolute paths
 * @param {string} zipPath
 */
export async function zipFiles(files, zipPath) {
  mkdirSync(dirname(zipPath), { recursive: true })

  const output = createWriteStream(zipPath)
  const archive = archiver('zip', { zlib: { level: 9 } })

  archive.pipe(output)
  for (const file of files) {
    archive.file(file, { name: file.split(/[/\\]/).pop() })
  }
  await archive.finalize()

  await new Promise((resolve, reject) => {
    output.on('close', resolve)
    output.on('error', reject)
    archive.on('error', reject)
  })
}

function addDir(base, dir, archive, include) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name)
    const entry = relative(base, full).replace(/\\/g, '/')
    if (statSync(full).isDirectory()) {
      addDir(base, full, archive, include)
    } else if (!include || include(entry)) {
      archive.file(full, { name: entry })
    }
  }
}
