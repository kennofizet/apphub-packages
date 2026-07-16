/** @type {Record<string, (...args: unknown[]) => unknown>} */
export const actions = {
  // Example: custom PC chrome hooks — override/extend per host later
  // focusTaskbar() {},
}

export default {
  id: 'pc',
  actions,
}
