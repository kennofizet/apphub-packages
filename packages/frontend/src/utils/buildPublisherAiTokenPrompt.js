/**
 * Paste-ready AI instructions for using the host session token locally (never commit).
 *
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

  if (lang === 'vi') {
    return buildVi(token, apiBase, docsUrl)
  }
  return buildEn(token, apiBase, docsUrl)
}

/** @param {string} token @param {string} apiBase @param {string} docsUrl */
function buildEn(token, apiBase, docsUrl) {
  const apiLine = apiBase ? `App Hub API base: ${apiBase}` : 'App Hub API base: (set APPHUB_API_BASE in your local env file)'

  return `## App Hub session token (secret — local device only)

Header for authenticated Hub routes: X-Knf-Token
${apiLine}

Token value (copy into local file only):
${token}

### Rules you MUST follow
1. Save this token to a **local-only file** on my machine, for example:
   - .apphub-token.local (plain text, one line)
   - or .env.local with APPHUB_TOKEN=${token}
2. Add that file to **.gitignore** immediately (and never remove it from .gitignore).
3. **Never** commit, push, publish, screenshot, or paste this token into PRs, CI logs, chat archives, or public repos.
4. For routes that need auth (POST /apps/register, POST /apps/{slug}/launch, GET /apps, …), read the token from that local file and send header X-Knf-Token (plus X-Knf-Zone-Id if my host uses zones).
5. If Hub API returns **401 or 403**, stop retrying. Tell me: "Session expired — open App Hub → Publisher hub → **Copy token for AI** again, update your local token file, then retry."

Contract (public, no token): ${docsUrl || 'GET …/integration-docs'}

Remember: launch_token from POST …/launch is a different short-lived token for runtime/bridge — not the same as X-Knf-Token above.`
}

/** @param {string} token @param {string} apiBase @param {string} docsUrl */
function buildVi(token, apiBase, docsUrl) {
  const apiLine = apiBase ? `API App Hub: ${apiBase}` : 'API App Hub: (đặt APPHUB_API_BASE trong file env local)'

  return `## Token session App Hub (bí mật — chỉ trên máy local)

Header cho route Hub cần auth: X-Knf-Token
${apiLine}

Giá trị token (chỉ ghi vào file local):
${token}

### Quy tắc BẮT BUỘC
1. Lưu token vào **file chỉ trên máy tôi**, ví dụ:
   - .apphub-token.local (một dòng)
   - hoặc .env.local với APPHUB_TOKEN=${token}
2. Thêm file đó vào **.gitignore** ngay (không bao giờ commit).
3. **Không** commit, push, chụp màn hình, hoặc dán token vào PR, CI, chat công khai.
4. Route cần auth (POST /apps/register, POST /apps/{slug}/launch, GET /apps, …): đọc token từ file local, gửi header X-Knf-Token (và X-Knf-Zone-Id nếu host dùng zone).
5. Nếu API trả **401 hoặc 403**, dừng retry. Báo tôi: "Session hết hạn — mở App Hub → Publisher hub → **Sao chép token cho AI** lại, cập nhật file token local, rồi thử lại."

Hợp đồng (công khai): ${docsUrl || 'GET …/integration-docs'}

launch_token từ POST …/launch là token ngắn hạn cho runtime — khác X-Knf-Token ở trên.`
}
