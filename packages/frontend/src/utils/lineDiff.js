/**
 * Line-oriented diff for dev code review (VS Code–style rows).
 *
 * @typedef {{ type: 'equal'|'insert'|'delete', text: string, oldLine?: number|null, newLine?: number|null }} DiffLine
 */

/**
 * @param {string|null|undefined} oldText
 * @param {string|null|undefined} newText
 * @returns {DiffLine[]}
 */
export function diffLines(oldText, newText) {
  const oldLines = splitLines(oldText ?? '')
  const newLines = splitLines(newText ?? '')
  if (oldLines.length === 0 && newLines.length === 0) return []

  const oldCmp = oldLines.map(normalizeLine)
  const newCmp = newLines.map(normalizeLine)
  const lcs = buildLcs(oldCmp, newCmp)

  /** @type {DiffLine[]} */
  const out = []
  let oi = oldLines.length
  let ni = newLines.length
  let oldNum = oldLines.length
  let newNum = newLines.length

  while (oi > 0 || ni > 0) {
    if (oi > 0 && ni > 0 && oldCmp[oi - 1] === newCmp[ni - 1]) {
      out.push({
        type: 'equal',
        text: newLines[ni - 1],
        oldLine: oldNum,
        newLine: newNum,
      })
      oi -= 1
      ni -= 1
      oldNum -= 1
      newNum -= 1
      continue
    }

    if (oi > 0 && (ni === 0 || lcs[oi - 1][ni] >= lcs[oi][ni - 1])) {
      out.push({
        type: 'delete',
        text: oldLines[oi - 1],
        oldLine: oldNum,
        newLine: null,
      })
      oi -= 1
      oldNum -= 1
      continue
    }

    if (ni > 0) {
      out.push({
        type: 'insert',
        text: newLines[ni - 1],
        oldLine: null,
        newLine: newNum,
      })
      ni -= 1
      newNum -= 1
    }
  }

  return out.reverse()
}

function normalizeText(text) {
  return String(text).replace(/^\uFEFF/, '').replace(/\r\n/g, '\n').replace(/\r/g, '\n')
}

function normalizeLine(line) {
  return String(line).replace(/\r$/, '')
}

function splitLines(text) {
  const normalized = normalizeText(text)
  if (normalized === '') return []

  const parts = normalized.split('\n')
  if (parts.length > 1 && parts[parts.length - 1] === '') {
    parts.pop()
  }

  return parts
}

/** @param {string[]} a @param {string[]} b */
function buildLcs(a, b) {
  const rows = a.length + 1
  const cols = b.length + 1
  /** @type {number[][]} */
  const table = Array.from({ length: rows }, () => Array(cols).fill(0))

  for (let i = 1; i < rows; i += 1) {
    for (let j = 1; j < cols; j += 1) {
      if (a[i - 1] === b[j - 1]) {
        table[i][j] = table[i - 1][j - 1] + 1
      } else {
        table[i][j] = Math.max(table[i - 1][j], table[i][j - 1])
      }
    }
  }

  return table
}
