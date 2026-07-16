/** Device mode kinds for App Hub responsive chrome. */
export const DEVICE_PC = 'pc'
export const DEVICE_MOBILE = 'mobile'

/** Phone profile ids under profiles/mobile/phones/ — add folders to scale. */
export const PHONE_DEFAULT = '_default'
export const PHONE_IPHONE = 'iphone'
export const PHONE_ANDROID = 'android'

/** Width below this → mobile mode (px). */
export const MOBILE_MAX_WIDTH = 768

/**
 * Short edge below this also treats as mobile (e.g. landscape phones with width ≥ 768).
 * Height alone never forces PC → mobile when width is large desktop.
 */
export const MOBILE_MAX_SHORT_EDGE = 500

export const APPHUB_DEVICE_MODE_KEY = 'apphubDeviceMode'

/** CSS data attributes applied on .apphub-desktop */
export const DATA_DEVICE = 'data-apphub-device'
export const DATA_PHONE = 'data-apphub-phone'
