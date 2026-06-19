/**
 * Static server for demo-iframe/html — must match manifest entry_url (default :15180).
 *
 *   node serve.mjs
 *   PORT=15180 node serve.mjs
 */
import http from 'node:http'
import { readFile } from 'node:fs/promises'
import { join, extname, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const PORT = Number(process.env.PORT || 15180)
const root = join(dirname(fileURLToPath(import.meta.url)), 'html')

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url || '/', `http://127.0.0.1:${PORT}`)
  let pathname = decodeURIComponent(url.pathname)

  if (pathname === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' })
    res.end(JSON.stringify({ ok: true, slug: 'demo-iframe-html' }))
    return
  }

  if (pathname === '/') pathname = '/index.html'

  const filePath = join(root, pathname.replace(/^\/+/, ''))
  if (!filePath.startsWith(root)) {
    res.writeHead(403)
    res.end('Forbidden')
    return
  }

  try {
    const body = await readFile(filePath)
    res.writeHead(200, { 'Content-Type': MIME[extname(filePath)] || 'application/octet-stream' })
    res.end(body)
  } catch {
    res.writeHead(404)
    res.end('Not found')
  }
})

server.on('error', (err) => {
  if (err && err.code === 'EADDRINUSE') {
    console.error(`Port ${PORT} is already in use.`)
    console.error('Another demo-iframe server may still be running, or pick another port:')
    console.error(`  $env:PORT=5181; npm run serve:iframe`)
    console.error('On Windows, find the process: Get-NetTCPConnection -LocalPort 15180')
    process.exit(1)
  }
  throw err
})

server.listen(PORT, () => {
  console.log(`demo-iframe serving ${root}`)
  console.log(`  entry_url: http://localhost:${PORT}/`)
  console.log(`  health:    http://localhost:${PORT}/health`)
})
