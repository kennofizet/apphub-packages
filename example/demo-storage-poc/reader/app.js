const originEl = document.getElementById('origin')
const resultEl = document.getElementById('result')

originEl.textContent = `window.origin = ${JSON.stringify(window.origin)}`

function collectAllLocalStorage() {
  const entries = []
  for (let i = 0; i < localStorage.length; i += 1) {
    const key = localStorage.key(i)
    if (key != null) {
      entries.push([key, localStorage.getItem(key)])
    }
  }
  entries.sort((a, b) => a[0].localeCompare(b[0]))
  return entries
}

function renderAllLocalStorage(container, entries) {
  container.replaceChildren()

  const summary = document.createElement('p')
  summary.className = 'app__result-summary'

  if (entries.length === 0) {
    summary.textContent = 'No keys in localStorage for this app slug.'
    container.className = 'app__result app__result--ok'
    container.append(summary)
    return
  }

  summary.textContent = `${entries.length} key(s) in localStorage`
  const list = document.createElement('dl')
  list.className = 'app__storage-list'

  for (const [key, value] of entries) {
    const dt = document.createElement('dt')
    dt.textContent = key
    const dd = document.createElement('dd')
    dd.textContent = value ?? ''
    list.append(dt, dd)
  }

  container.className = 'app__result app__result--ok'
  container.append(summary, list)
}

async function run() {
  if (window.__APPHUB_STORAGE__?.ready) {
    await window.__APPHUB_STORAGE__.ready
  }

  try {
    renderAllLocalStorage(resultEl, collectAllLocalStorage())
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err)
    resultEl.textContent = `READ FAILED — ${msg}`
    resultEl.className = 'app__result app__result--bad'
  }
}

void run()
