/**
 * Token-only refresh paste (rules are in buildPublisherAiPrompt when copying AI rules).
 */
import { buildPublisherTokenBlock, COPY_TOKEN_BTN } from './buildPublisherAiPrompt.js'

/**
 * @param {{
 *   token: string,
 *   apiBase?: string,
 *   integrationDocsUrl?: string,
 *   lang?: string,
 * }} options
 */
export function buildPublisherAiTokenPrompt(options) {
  const token = String(options.token ?? '').trim()
  const apiBase = String(options.apiBase ?? '').trim().replace(/\/$/, '')
  const docsUrl = String(options.integrationDocsUrl ?? '').trim()
  const lang = String(options.lang ?? 'en').split('-')[0].toLowerCase()

  if (!token) {
    return lang === 'vi'
      ? 'Không có session token. Đăng nhập host app và mở Publisher hub trước.'
      : 'No session token available. Sign in to the host app and open Publisher hub first.'
  }

  const block = buildPublisherTokenBlock(token, apiBase, docsUrl, lang)
  const footer = lang === 'vi'
    ? `(Từ nút **${COPY_TOKEN_BTN.vi}** trong Publisher hub.) launch_token từ POST …/launch khác X-Knf-Token ở trên.`
    : `(From **${COPY_TOKEN_BTN.en}** in Publisher hub.) launch_token from POST …/launch is not the same as X-Knf-Token above.`

  return `${block}\n\n${footer}`
}
