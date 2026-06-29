import { unref } from 'vue'

/** Vue 3.2-compatible substitute for `toValue` (added in Vue 3.3). */
export function toValue(source) {
  return typeof source === 'function' ? source() : unref(source)
}
