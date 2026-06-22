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
