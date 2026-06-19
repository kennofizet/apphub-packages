const KEY = 'apphub_cross_app_test'
const originEl = document.getElementById('origin')
const resultEl = document.getElementById('result')

originEl.textContent = `window.origin = ${JSON.stringify(window.origin)}`

const payload = JSON.stringify({
  app: 'demo-storage-writer',
  message: 'hello from writer zip',
  at: new Date().toISOString(),
})

async function run() {
  if (window.__APPHUB_STORAGE__?.ready) {
    await window.__APPHUB_STORAGE__.ready
  }

  try {
    localStorage.setItem(KEY, payload)
    const readBack = localStorage.getItem(KEY)
    resultEl.textContent = `WRITE OK — read back: ${readBack}`
    resultEl.className = 'app__result app__result--ok'
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err)
    resultEl.textContent = `WRITE FAILED — ${msg}`
    resultEl.className = 'app__result app__result--bad'
  }
}

void run()
