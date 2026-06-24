/**
 * Local publisher tool backend proxy for App Hub bridge APIs.
 *
 * Browser demo apps call this server (manifest api_urls). This process forwards
 * to App Hub; App Hub checks the proxy's IP against api_urls.
 *
 * Usage (PowerShell):
 *   $env:APPHUB_BACKEND_URL = "http://localhost/jmm/zz/api/knf/apphub"
 *   node server.mjs
 *
 * Default listen: http://localhost:51732 (match demo manifest api_urls).
 */
import http from 'node:http'
import { URL } from 'node:url'

const PORT = Number(process.env.PORT || 51732)
const APPHUB_BASE = String(process.env.APPHUB_BACKEND_URL || '').replace(/\/$/, '')
const BRIDGE_PROXY_SECRET = String(process.env.APPHUB_BRIDGE_PROXY_SECRET || '').trim()

if (!APPHUB_BASE) {
  console.error('Set APPHUB_BACKEND_URL (e.g. http://localhost/jmm/zz/api/knf/apphub)')
  process.exit(1)
}

const FORWARD_HEADERS = [
  'accept',
  'content-type',
  'x-apphub-launch-token',
  'x-apphub-app-slug',
  'x-apphub-session-id',
]

function applyCors(res, origin, req) {
  const allowOrigin = origin === 'null' || origin ? (origin || '*') : '*'
  res.setHeader('Access-Control-Allow-Origin', allowOrigin)
  res.setHeader('Vary', 'Origin')
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
  res.setHeader(
    'Access-Control-Allow-Headers',
    'Accept, Content-Type, X-AppHub-Launch-Token, X-AppHub-App-Slug, X-AppHub-Session-Id',
  )
  if (req?.headers['access-control-request-private-network'] === 'true') {
    res.setHeader('Access-Control-Allow-Private-Network', 'true')
  }
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = []
    req.on('data', (chunk) => chunks.push(chunk))
    req.on('end', () => resolve(Buffer.concat(chunks)))
    req.on('error', reject)
  })
}

function isAllowedPath(pathname) {
  return pathname === '/verify-launch-token' || pathname.startsWith('/bridge/')
}

const server = http.createServer(async (req, res) => {
  const origin = typeof req.headers.origin === 'string' ? req.headers.origin : ''
  applyCors(res, origin, req)

  if (req.method === 'OPTIONS') {
    res.writeHead(204)
    res.end()
    return
  }

  const incoming = new URL(req.url || '/', `http://127.0.0.1:${PORT}`)
  if (!isAllowedPath(incoming.pathname)) {
    res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' })
    res.end('Use /bridge/* or /verify-launch-token')
    return
  }

  const target = `${APPHUB_BASE}${incoming.pathname}${incoming.search}`
  const headers = {}
  if (BRIDGE_PROXY_SECRET !== '') {
    headers['x-apphub-bridge-proxy-secret'] = BRIDGE_PROXY_SECRET
    headers['x-apphub-publisher-origin'] = `http://localhost:${PORT}`
  }
  for (const name of FORWARD_HEADERS) {
    const value = req.headers[name]
    if (typeof value === 'string' && value !== '') {
      headers[name] = value
    }
  }

  let body
  if (req.method === 'POST' || req.method === 'PUT' || req.method === 'PATCH') {
    body = await readBody(req)
  }

  try {
    const upstream = await fetch(target, {
      method: req.method,
      headers,
      body: body?.length ? body : undefined,
    })
    const text = await upstream.text()
    const contentType = upstream.headers.get('content-type') || 'application/json'
    applyCors(res, origin, req)

    if (upstream.status === 404 && text.includes('<title>404 Not Found</title>')) {
      res.writeHead(502, { 'Content-Type': 'application/json' })
      res.end(JSON.stringify({
        success: false,
        error: `App Hub API not found at ${target}. Set APPHUB_BACKEND_URL to VITE_APPHUB_BACKEND_URL from hub-host .env (this test host uses http://localhost:8000/api/jmm/zz/apphub).`,
      }))
      return
    }

    res.writeHead(upstream.status, { 'Content-Type': contentType })
    res.end(text)
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err)
    res.writeHead(502, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ success: false, error: message }))
  }
})

server.listen(PORT, () => {
  console.log(`Publisher bridge proxy: http://localhost:${PORT}`)
  console.log(`Forwarding to: ${APPHUB_BASE}`)
})
