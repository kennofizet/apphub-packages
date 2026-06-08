export function normalizeAppName(name) {
  return String(name ?? '').trim()
}

export function findAppByName(name, apps) {
  const target = normalizeAppName(name).toLowerCase()
  if (!target) return null
  return (apps ?? []).find((a) => normalizeAppName(a.name).toLowerCase() === target) ?? null
}

/** Next name: "App" → "App 2", existing "App 2" → "App 3" */
export function nextDuplicateName(baseName, apps) {
  const base = normalizeAppName(baseName)
  const names = new Set((apps ?? []).map((a) => normalizeAppName(a.name)))
  if (!names.has(base)) return base
  let n = 2
  while (names.has(`${base} ${n}`)) n += 1
  return `${base} ${n}`
}

export function nextDuplicateSlug(baseSlug, apps) {
  const base = String(baseSlug ?? '').trim() || 'app'
  const slugs = new Set((apps ?? []).map((a) => a.slug))
  if (!slugs.has(base)) return base
  let n = 2
  while (slugs.has(`${base}-${n}`)) n += 1
  return `${base}-${n}`
}
