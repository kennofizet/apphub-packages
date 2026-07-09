/**
 * Draft-only parent-bridge fixtures for DEV UI demos.
 * Never used for active apps — real API / consent gates apply there.
 */

const FIXTURES = {
  'project.list': [
    {
      id: 9001,
      code: 'DEMO-A',
      name: 'Draft demo project A',
      status: 'active',
      updated_at: '2026-07-01T00:00:00Z',
      _draft_fixture: true,
    },
    {
      id: 9002,
      code: 'DEMO-B',
      name: 'Draft demo project B',
      status: 'planning',
      updated_at: '2026-07-02T00:00:00Z',
      _draft_fixture: true,
    },
  ],
  'project.members': [
    {
      userId: 1,
      name: 'Demo Member',
      role: 'lead',
      jobPosition: 'Developer',
      _draft_fixture: true,
    },
  ],
  'signature.user': {
    url: null,
    mime: 'image/png',
    data: null,
    _draft_fixture: true,
  },
}

/**
 * @param {string} action
 * @returns {unknown}
 */
export function draftParentBridgeFixture(action) {
  const key = String(action ?? '').trim().toLowerCase()
  if (Object.prototype.hasOwnProperty.call(FIXTURES, key)) {
    return structuredClone
      ? structuredClone(FIXTURES[key])
      : JSON.parse(JSON.stringify(FIXTURES[key]))
  }
  return { _draft_fixture: true, action: key, note: 'No fixture for this action yet' }
}
