import assert from 'node:assert/strict'
import { mergeAppPermissions, resolveAppPermissions } from '../src/utils/resolveAppPermissions.js'

const merged = mergeAppPermissions(
  { permissions: ['desktop.message', 'user.read'] },
  { permissions: ['desktop.theme', { scope: 'desktop.message' }] },
)

assert.deepEqual(
  merged,
  ['desktop.message', 'user.read', 'desktop.theme'],
  'runner permissions must union stale window props with live catalog permissions',
)

assert.deepEqual(
  resolveAppPermissions({ permissions: ['desktop.theme', 'desktop.future_scope', 'nope', 'parent.project.list'] }),
  ['desktop.theme', 'desktop.future_scope', 'parent.project.list'],
  'declared catalog scopes must keep known + forward-compatible desktop.* / parent.* values',
)

assert.deepEqual(
  mergeAppPermissions(
    { permissions: ['user.read', 'desktop.download'] },
    { permissions: ['desktop.theme'] },
    { permissions: ['desktop.theme', 'user.profile'] },
  ),
  ['user.read', 'desktop.download', 'desktop.theme', 'user.profile'],
  'launch scopes_granted must be mergeable into declared runner permissions',
)

console.log('App permission union tests passed.')
