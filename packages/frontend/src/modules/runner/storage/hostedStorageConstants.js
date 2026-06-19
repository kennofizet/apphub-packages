/** postMessage channel for hosted zip localStorage proxy (Hub parent ↔ sandboxed iframe). */
export const HOSTED_STORAGE_CHANNEL = 'apphub:storage'

export const HOSTED_STORAGE_OP_HYDRATE = 'hydrate'
export const HOSTED_STORAGE_OP_SNAPSHOT = 'snapshot'
export const HOSTED_STORAGE_OP_SET = 'set'
export const HOSTED_STORAGE_OP_REMOVE = 'remove'
export const HOSTED_STORAGE_OP_CLEAR = 'clear'

export const HOSTED_STORAGE_LS_PREFIX = 'apphub_hosted_store:'
