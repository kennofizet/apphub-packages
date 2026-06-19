/**
 * Injected into hosted zip HTML by App Hub runtime (first script in <head>).
 * Replaces window.localStorage with an in-memory store synced to the Hub parent via postMessage.
 */
(function installAppHubHostedStorageShim() {
  if (typeof window === 'undefined' || window.parent === window) return

  var CHANNEL = 'apphub:storage'
  var mem = new Map()
  var hydrated = false
  var hydrateWaiters = []

  function notifyHydrated() {
    if (hydrated) return
    hydrated = true
    var waiters = hydrateWaiters
    hydrateWaiters = []
    waiters.forEach(function (fn) {
      try {
        fn()
      } catch (e) {}
    })
  }

  function post(op, key, value) {
    try {
      window.parent.postMessage({ channel: CHANNEL, op: op, key: key, value: value }, '*')
    } catch (e) {}
  }

  var proxy = {
    get length() {
      return mem.size
    },
    key: function (index) {
      var keys = Array.from(mem.keys())
      return keys[index] == null ? null : keys[index]
    },
    getItem: function (key) {
      if (key == null) return null
      var k = String(key)
      return mem.has(k) ? mem.get(k) : null
    },
    setItem: function (key, value) {
      if (key == null) return
      var k = String(key)
      var v = String(value)
      mem.set(k, v)
      post('set', k, v)
    },
    removeItem: function (key) {
      if (key == null) return
      var k = String(key)
      mem.delete(k)
      post('remove', k)
    },
    clear: function () {
      mem.clear()
      post('clear')
    },
  }

  window.addEventListener('message', function (event) {
    if (event.source !== window.parent) return
    var data = event.data
    if (!data || data.channel !== CHANNEL || data.op !== 'snapshot') return
    var snapshot = data.data
    if (!snapshot || typeof snapshot !== 'object') {
      notifyHydrated()
      return
    }
    Object.keys(snapshot).forEach(function (k) {
      mem.set(k, String(snapshot[k]))
    })
    notifyHydrated()
  })

  try {
    Object.defineProperty(window, 'localStorage', {
      value: proxy,
      configurable: true,
      writable: false,
    })
  } catch (e) {}

  window.__APPHUB_STORAGE__ = {
    ready: new Promise(function (resolve) {
      if (hydrated) {
        resolve()
        return
      }
      hydrateWaiters.push(resolve)
    }),
  }

  post('hydrate')
})()
