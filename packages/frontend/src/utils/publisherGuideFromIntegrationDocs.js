/**
 * Turn publisher integration-docs JSON into human-readable Guide sections.
 * Raw JSON stays on GET /integration-docs for AI agents — not shown in the UI.
 *
 * Publisher strings may include audiences.publisher.locales.{lang} overlays (e.g. vi).
 */

function deepMergeLocale(base, overlay) {
  if (!overlay || typeof overlay !== 'object') return base
  if (Array.isArray(overlay)) return overlay.slice()
  const out = { ...base }
  for (const [key, value] of Object.entries(overlay)) {
    if (
      value
      && typeof value === 'object'
      && !Array.isArray(value)
      && base[key]
      && typeof base[key] === 'object'
      && !Array.isArray(base[key])
    ) {
      out[key] = deepMergeLocale(base[key], value)
    } else {
      out[key] = value
    }
  }
  return out
}

/** @param {Record<string, unknown>} doc */
export function resolvePublisherAudience(doc, lang = 'en') {
  const raw = doc?.audiences?.publisher ?? doc?.publisher ?? doc
  if (!raw || typeof raw !== 'object') return null

  const { locales, ...base } = raw
  const code = String(lang || 'en').split('-')[0].toLowerCase()
  if (code === 'en' || !locales?.[code]) return base
  return deepMergeLocale(base, locales[code])
}

/**
 * @param {Record<string, unknown>} doc — API response (publisher subset or full with audiences.publisher)
 * @param {{
 *   overviewTitle: string,
 *   runtimeTitle: string,
 *   hostedTitle: string,
 *   iframeTitle: string,
 *   storageTitle: string,
 *   launchTitle: string,
 *   permissionsTitle: string,
 *   apiTitle: string,
 *   manifestTitle: string,
 *   deployTitle: string,
 *   stepsTitle: string,
 *   userInfoTitle: string,
 *   exampleTitle?: string,
 *   whenUse?: string,
 *   manifestLine?: string,
 *   launchLine?: string,
 *   storageLine?: string,
 *   apiNeeds?: string,
 *   uiOnly?: string,
 *   uiDoNot?: string,
 *   trustedBackend?: string,
 *   hostedTroubleshootingTitle?: string,
 * }} labels — localized section headings and inline prefixes
 * @param {string} [lang='en']
 */
export function publisherGuideSectionsFromIntegrationDocs(doc, labels, lang = 'en') {
  const publisher = resolvePublisherAudience(doc, lang)
  if (!publisher) return []

  const sections = []
  const version = doc?.schema_version ? ` (${doc.schema_version})` : ''
  const summary = publisher.summary
  if (typeof summary === 'string' && summary.trim()) {
    sections.push({
      title: labels.overviewTitle ?? 'Overview',
      lines: [summary.trim()],
    })
  }

  const runtime = publisher.runtime_types
  if (runtime && typeof runtime === 'object') {
    const lines = []
    for (const key of ['hosted', 'iframe']) {
      const rt = runtime[key]
      if (!rt || typeof rt !== 'object') continue
      const title = key === 'hosted' ? labels.hostedTitle : labels.iframeTitle
      const parts = [
        rt.summary,
        rt.choose_when && labels.whenUse ? `${labels.whenUse} ${rt.choose_when}` : rt.choose_when,
        rt.manifest && labels.manifestLine ? `${labels.manifestLine} ${rt.manifest}` : rt.manifest,
        rt.launch && labels.launchLine ? `${labels.launchLine} ${rt.launch}` : rt.launch,
        rt.storage && labels.storageLine ? `${labels.storageLine} ${rt.storage}` : rt.storage,
      ].filter(Boolean)
      if (parts.length) {
        lines.push(`${title}: ${parts[0]}`)
        parts.slice(1).forEach((p) => lines.push(`  · ${p}`))
      }
    }
    if (lines.length) {
      sections.push({ title: `${labels.runtimeTitle}${version}`, lines })
    }
  }

  const storage = publisher.hosted_storage
  if (storage && typeof storage === 'object') {
    const lines = [
      storage.automatic,
      storage.ready,
      storage.scope,
      storage.iframe_note,
    ].filter((s) => typeof s === 'string' && s.trim())
    if (lines.length) {
      sections.push({ title: labels.storageTitle, lines })
    }
  }

  const hostedTs = publisher.hosted_runtime_troubleshooting
  if (hostedTs && typeof hostedTs === 'object') {
    const title =
      (typeof hostedTs.title === 'string' && hostedTs.title.trim())
      || labels.hostedTroubleshootingTitle
      || 'Hosted runtime — troubleshooting'
    const lines = []
    if (typeof hostedTs.summary === 'string' && hostedTs.summary.trim()) {
      lines.push(hostedTs.summary.trim())
    }

    const zip = hostedTs.zip_contract
    if (zip && typeof zip === 'object') {
      if (zip.summary) lines.push(zip.summary)
      if (Array.isArray(zip.layout)) lines.push(...zip.layout.map((l) => `· ${l}`))
      if (Array.isArray(zip.example_tree)) {
        lines.push(`Example zip root: ${zip.example_tree.join(', ')}`)
      }
      if (zip.reference) lines.push(zip.reference)
    }

    const js = hostedTs.javascript_modules
    if (js && typeof js === 'object') {
      if (js.summary) lines.push(js.summary)
      if (Array.isArray(js.hub_html_changes)) {
        lines.push('Hub HTML changes:')
        js.hub_html_changes.forEach((l) => lines.push(`  · ${l}`))
      }
      if (js.asset_auth) lines.push(js.asset_auth)
      if (js.mime_types) lines.push(js.mime_types)
      const imp = js.import_outside_module
      if (imp && typeof imp === 'object') {
        if (imp.symptom) lines.push(imp.symptom)
        if (Array.isArray(imp.likely_causes)) {
          imp.likely_causes.forEach((l) => lines.push(`  · ${l}`))
        }
        if (Array.isArray(imp.publisher_checks)) {
          lines.push('Publisher checks:')
          imp.publisher_checks.forEach((l) => lines.push(`  · ${l}`))
        }
      }
    }

    const csp = hostedTs.csp_framing
    if (csp && typeof csp === 'object') {
      if (csp.summary) lines.push(csp.summary)
      if (csp.nested_chain) lines.push(csp.nested_chain)
      if (csp.self_only_error) lines.push(csp.self_only_error)
      if (csp.publisher_action) lines.push(csp.publisher_action)
      if (csp.hub_launch_url) lines.push(csp.hub_launch_url)
      const env = csp.platform_env
      if (env && typeof env === 'object') {
        Object.entries(env).forEach(([k, v]) => {
          if (typeof v === 'string' && v) lines.push(`${k}: ${v}`)
        })
      }
    }

    const serve = hostedTs.runtime_serving
    if (serve && typeof serve === 'object') {
      if (serve.summary) lines.push(serve.summary)
      if (Array.isArray(serve.behaviors)) {
        serve.behaviors.forEach((l) => lines.push(`· ${l}`))
      }
      if (serve.devtools_expectation) lines.push(serve.devtools_expectation)
    }

    const checklist = hostedTs.troubleshooting_checklist
    if (Array.isArray(checklist) && checklist.length) {
      lines.push('Troubleshooting:')
      checklist.forEach((row) => {
        if (!row || typeof row !== 'object') return
        const parts = [
          row.symptom,
          row.likely_cause && `Cause: ${row.likely_cause}`,
          row.publisher_action && `You: ${row.publisher_action}`,
          row.platform_action && row.platform_action !== '—' && `Platform: ${row.platform_action}`,
        ].filter(Boolean)
        if (parts.length) lines.push(`  · ${parts.join(' — ')}`)
      })
    }

    const build = hostedTs.build_tools
    if (build && typeof build === 'object') {
      lines.push('Build tools:')
      Object.entries(build).forEach(([k, v]) => {
        if (typeof v === 'string' && v) lines.push(`  · ${k}: ${v}`)
      })
    }

    if (lines.length) {
      sections.push({ title, lines })
    }
  }

  const bridge = publisher.bridge
  if (bridge && typeof bridge === 'object') {
    if (bridge.overview) {
      sections.push({
        title: labels.launchTitle,
        lines: [
          bridge.overview,
          ...(Array.isArray(bridge.connection_flow) ? bridge.connection_flow : []),
        ],
      })
    }

    const perms = bridge.permissions
    if (Array.isArray(perms) && perms.length) {
      sections.push({
        title: labels.permissionsTitle,
        lines: perms.map((p) => {
          const scope = p?.scope ?? ''
          const desc = p?.description ?? ''
          return scope ? `${scope} — ${desc}` : desc
        }).filter(Boolean),
      })
    }

    const jsApi = bridge.javascript_api
    if (jsApi && typeof jsApi === 'object') {
      const needsLabel = labels.apiNeeds ?? 'needs'
      const lines = Object.entries(jsApi).map(([name, meta]) => {
        if (!meta || typeof meta !== 'object') return ''
        const desc = meta.description ?? ''
        const req = Array.isArray(meta.requires) && meta.requires.length
          ? ` (${needsLabel} ${meta.requires.join(', ')})`
          : ''
        return `${name}${req} — ${desc}`
      }).filter(Boolean)
      if (lines.length) {
        sections.push({ title: labels.apiTitle, lines })
      }
    }

    if (bridge.example && typeof bridge.example === 'string') {
      sections.push({
        title: labels.exampleTitle ?? 'Example',
        code: bridge.example.trim(),
      })
    }

    const tiers = bridge.data_tiers
    if (tiers && typeof tiers === 'object') {
      const lines = []
      const display = tiers.display_only
      const auth = tiers.authoritative
      if (display?.use_for && labels.uiOnly) lines.push(`${labels.uiOnly} ${display.use_for}`)
      if (display?.do_not_use_for && labels.uiDoNot) lines.push(`${labels.uiDoNot} ${display.do_not_use_for}`)
      if (auth?.use_for && labels.trustedBackend) lines.push(`${labels.trustedBackend} ${auth.use_for}`)
      if (lines.length) {
        sections.push({ title: labels.userInfoTitle, lines })
      }
    }

    const manifest = bridge.manifest
    if (manifest && typeof manifest === 'object') {
      const lines = Object.entries(manifest)
        .map(([k, v]) => (typeof v === 'string' && v ? `${k}: ${v}` : ''))
        .filter(Boolean)
      if (lines.length) {
        sections.push({ title: labels.manifestTitle, lines })
      }
    }
  }

  const deploy = publisher.deploy
  if (deploy && typeof deploy === 'object') {
    const lines = ['hosted', 'iframe', 'local']
      .map((k) => (typeof deploy[k] === 'string' ? deploy[k] : ''))
      .filter(Boolean)
    if (lines.length) {
      sections.push({ title: labels.deployTitle, lines })
    }
  }

  const steps = publisher.steps
  if (Array.isArray(steps) && steps.length) {
    sections.push({
      title: labels.stepsTitle,
      lines: steps.map((s, i) => `${i + 1}. ${s}`),
    })
  }

  return sections
}
