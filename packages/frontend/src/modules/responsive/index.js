export {
  APPHUB_DEVICE_MODE_KEY,
  DATA_DEVICE,
  DATA_PHONE,
  DEVICE_MOBILE,
  DEVICE_PC,
  MOBILE_MAX_SHORT_EDGE,
  MOBILE_MAX_WIDTH,
  PHONE_ANDROID,
  PHONE_DEFAULT,
  PHONE_IPHONE,
} from './constants.js'

export {
  detectDeviceMode,
  detectDeviceSnapshot,
  detectPhoneProfile,
} from './detectDevice.js'

export {
  listPhoneProfileIds,
  registerPhoneProfile,
  resolveDeviceProfile,
} from './registry.js'

export { createDeviceModeState } from './composables/createDeviceModeState.js'
export { provideDeviceMode, useDeviceMode } from './composables/useDeviceMode.js'
