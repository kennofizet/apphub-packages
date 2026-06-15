<template>
  <div class="hub-host">
    <div v-if="!staticConfig.backendUrl" class="hub-host__setup">
      <h1>App Hub host — configuration required</h1>
      <p>Set <code>VITE_APPHUB_BACKEND_URL</code> in <code>.env</code> (or CI/CD env) before build.</p>
      <p class="hub-host__setup-warn">
        Deploy on a <strong>dedicated subdomain</strong> and embed in your product iframe.
        Token, language, and theme are sent from the <strong>parent</strong> via
        <code>postMessage</code> (see README).
      </p>
    </div>

    <p v-else-if="!ready && waitingMode === 'standalone-loading'" class="hub-host__waiting">
      Fetching dev session…
    </p>

    <div v-else-if="!ready && waitingMode === 'standalone-failed'" class="hub-host__waiting hub-host__waiting--help">
      <p>Could not load a dev session for standalone Hub.</p>
      <p>
        Set <code>VITE_APPHUB_DEV_LOGIN_URL</code> in <code>.env</code>
        (e.g. <code>http://127.0.0.1:8000/api/user/login?user_id=1</code>),
        or open with <code>?token=…</code>.
      </p>
    </div>

    <p v-else-if="!ready" class="hub-host__waiting">Waiting for session from parent app…</p>

    <AppHubDesktop
      v-else
      :language="config.language"
      :theme="config.theme"
      :theme-toggle="config.themeToggle"
      :initial-open-slug="initialOpenSlug"
    />
  </div>
</template>

<script setup>
import { getCurrentInstance } from 'vue'
import { AppHubDesktop } from '@kennofizet/apphub-frontend'
import { resolveInitialOpenSlug } from './apphub-config.js'
import { useParentHostConfig } from './useParentHostConfig.js'

const vueApp = getCurrentInstance()?.appContext?.app
const initialOpenSlug = resolveInitialOpenSlug()
const { config, ready, staticConfig, waitingMode } = useParentHostConfig(vueApp)
</script>

<style>
html,
body,
#app {
  margin: 0;
  padding: 0;
  height: 100%;
  overflow: hidden;
}

.hub-host {
  width: 100%;
  height: 100%;
  min-height: 100%;
}

.hub-host__waiting {
  margin: 1rem;
  font-family: system-ui, sans-serif;
  color: #64748b;
}

.hub-host__waiting--help p {
  margin: 0.35rem 0;
}

.hub-host__setup {
  max-width: 640px;
  margin: 2rem auto;
  padding: 0 1rem;
  font-family: system-ui, sans-serif;
  line-height: 1.5;
}

.hub-host__setup h1 {
  font-size: 1.35rem;
}

.hub-host__setup-warn {
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid #f59e0b;
  background: #fffbeb;
  color: #92400e;
}
</style>
