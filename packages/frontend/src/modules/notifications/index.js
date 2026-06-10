import './styles/notifications.css'

export {
  createDesktopNotificationsState,
  useDesktopNotifications,
  DESKTOP_NOTIFICATIONS_KEY,
} from './composables/createDesktopNotifications.js'
export { default as AppHubDesktopNotifications } from './components/AppHubDesktopNotifications.vue'
export { parseApiError } from './utils/parseApiError.js'
