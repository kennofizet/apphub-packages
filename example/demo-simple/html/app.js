const btn = document.getElementById('btn')
const hello = document.getElementById('hello')
const status = document.getElementById('status')
const versionEl = document.getElementById('version')

const params = new URLSearchParams(window.location.search)
const launchToken = params.get('launch_token')

if (launchToken) {
  status.textContent = 'Launch token present in URL.'
}

/** Hub runtime requires launch_token on every bundle file request. */
function runtimeUrl(path) {
  if (!launchToken) return path
  const sep = path.includes('?') ? '&' : '?'
  return `${path}${sep}launch_token=${encodeURIComponent(launchToken)}`
}

fetch(runtimeUrl('./manifest.json'))
  .then((res) => (res.ok ? res.json() : null))
  .then((manifest) => {
    if (manifest?.version && versionEl) {
      versionEl.textContent = `Version ${manifest.version}`
    }
  })
  .catch(() => {
    if (versionEl) versionEl.hidden = true
  })

btn?.addEventListener('click', () => {
  hello.hidden = false
  hello.textContent = 'Hello from demo-simple-html!'
})
