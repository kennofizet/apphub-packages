/**
 * Paste-ready prompt for AI coding assistants (Publisher hub).
 * Integration rules + contract + optional session token (save locally first).
 *
 * @param {{
 *   integrationDocsUrl: string,
 *   apiBase?: string,
 *   lang?: string,
 *   token?: string,
 * }} options
 */

const PUBLISHER_KIT_REPO = 'https://github.com/kennofizet/apphub-apps-ai-builder'
const PACKAGES_REPO = 'https://github.com/kennofizet/apphub-packages'
const COPY_TOKEN_BTN = { en: 'Copy token for AI', vi: 'Sao chép token cho AI' }

export { COPY_TOKEN_BTN }

export function buildPublisherAiPrompt(options) {
  const url = String(options.integrationDocsUrl ?? '').trim()
  const apiBase = String(options.apiBase ?? '').trim().replace(/\/$/, '')
  const token = String(options.token ?? '').trim()
  const lang = String(options.lang ?? 'en').split('-')[0].toLowerCase()

  if (lang === 'vi') {
    return buildVi(url, apiBase, token)
  }
  return buildEn(url, apiBase, token)
}

/** @param {string} token @param {string} apiBase @param {string} url @param {string} lang */
export function buildPublisherTokenBlock(token, apiBase, url, lang = 'en') {
  if (!token) {
    return lang === 'vi'
      ? '(Chưa có token — đăng nhập host app, mở Publisher hub, bấm Sao chép quy tắc AI lại.)'
      : '(No token in this paste — sign in to the host app, open Publisher hub, copy AI rules again.)'
  }

  const apiLine = apiBase
    ? (lang === 'vi' ? `API App Hub: ${apiBase}` : `App Hub API base: ${apiBase}`)
    : (lang === 'vi' ? 'API App Hub: đặt APPHUB_API_BASE trong file local' : 'App Hub API base: set APPHUB_API_BASE in local env file')

  if (lang === 'vi') {
    return `## Token session (lưu file local TRƯỚC — bí mật)
Header: X-Knf-Token
${apiLine}

Token (ghi vào file local ngay, không commit):
${token}

1. Tạo .apphub-token.local (một dòng) hoặc .env.local với APPHUB_TOKEN=…
2. Thêm file đó vào .gitignore ngay.
3. Không commit, push, hoặc đăng token lên git/PR/CI.
4. Gọi API Hub (register, launch, …) đọc token từ file local.
5. Lỗi 401/403 (auth) → bảo tôi mở Publisher hub → bấm **${COPY_TOKEN_BTN.vi}**, dán token mới vào file local, thử lại.

Hợp đồng (công khai): ${url || 'GET …/integration-docs'}`
  }

  return `## Session token (save to local file FIRST — secret)
Header: X-Knf-Token
${apiLine}

Token (write to a local file now — do not commit):
${token}

1. Create .apphub-token.local (one line) or .env.local with APPHUB_TOKEN=…
2. Add that file to .gitignore immediately.
3. Never commit, push, or publish the token.
4. Hub API calls (register, launch, …) read the token from that local file only.
5. On 401/403 (auth fail) → tell me to open Publisher hub → click **${COPY_TOKEN_BTN.en}**, paste the new token into the local file, retry.

Contract (public): ${url || 'GET …/integration-docs'}`
}

/** @param {string} url @param {string} apiBase @param {string} token */
function buildEn(url, apiBase, token) {
  const registerUrl = apiBase ? `${apiBase}/apps/register` : '{api_base}/apps/register'
  const launchUrl = apiBase ? `${apiBase}/apps/{slug}/launch` : '{api_base}/apps/{slug}/launch'
  const tokenBlock = buildPublisherTokenBlock(token, apiBase, url, 'en')

  return `You are my App Hub integration assistant. This is **rules + contract reference** for how App Hub works. I may be non-technical — proceed step by step with minimal jargon.

${tokenBlock}

## Do not (git & repo)
- **Never** delete, replace, or re-init my **.git** folder.
- **Never** run \`git clone ${PUBLISHER_KIT_REPO} .\` inside my existing project (that steals the kit's git history and breaks my repo).
- **Never** invent slug/name/version or a random demo app (publisher-smoke, hello-world, etc.).

## Empty or no app yet — bootstrap publisher kit (auto, no confirmation)
If the workspace has **no apps/<slug>/** (empty folder, new vibe-code project, or I say "start from zero"):
**Proceed automatically** — do not ask "should I clone?" I want plug-and-play.

1. **Keep my git:** If \`.git\` exists, keep it and my remote unchanged. Copy **the entire publisher kit** from ${PUBLISHER_KIT_REPO} into this workspace — **all** kit files and folders **except** the kit's \`.git\` (no kennofizet remote/history).
   - Use degit, GitHub archive/tarball, or fork + copy the full tree — **not** \`git clone … .\` inside my project.
2. **Truly empty (no .git):** Fork ${PUBLISHER_KIT_REPO} to **my** GitHub account (or init **my** git here), copy the **full kit** into the repo — **my** remote, not kennofizet's.
3. \`cp apphub.publisher.example.json apphub.publisher.json\` — set integration_docs_url to the contract URL below; set hub_portal_url if you know it.
4. \`npm install\`, read **AGENTS.md**, create the app under **apps/<slug>/**, use **tools/apphub.mjs** for build/zip.
5. Pick a friendly slug from my app idea; only ask me if the name is unclear.

## Contract (fetch — no auth)
GET ${url || '{api_prefix}/apphub/integration-docs'}
Read audiences.publisher — bridge, runtime_types, deploy, hosted_runtime_troubleshooting.

## Your role
1. Follow the contract; help **my** app in **my** repo (or apps/<slug>/ after kit bootstrap).
2. Auth: read X-Knf-Token from .apphub-token.local / .env.local (see above). On auth errors, ask me to use Publisher hub → **${COPY_TOKEN_BTN.en}**.
3. Register/launch (when I ask): POST ${registerUrl}, POST ${launchUrl} — use **my** slug.

## Runtime (reference)
- hosted: zip manifest.json + index.html + assets → POST ${registerUrl} multipart
- iframe: entry_url → POST ${registerUrl} JSON

## Verify (reference)
- hosted: type="module", .js 200; hosted_runtime_troubleshooting for import/CSP
- apphub:bridge:ready; hosted: await window.__APPHUB_STORAGE__?.ready
- launch_token ≠ X-Knf-Token

## Upstream (only for Hub/kit bugs — not my app code)
If you find a **core** bug or missing feature in App Hub packages or publisher kit, open an Issue or PR:
- Packages (backend/frontend): ${PACKAGES_REPO}
- Publisher kit: ${PUBLISHER_KIT_REPO}`
}

/** @param {string} url @param {string} apiBase @param {string} token */
function buildVi(url, apiBase, token) {
  const registerUrl = apiBase ? `${apiBase}/apps/register` : '{api_base}/apps/register'
  const launchUrl = apiBase ? `${apiBase}/apps/{slug}/launch` : '{api_base}/apps/{slug}/launch'
  const tokenBlock = buildPublisherTokenBlock(token, apiBase, url, 'vi')

  return `Bạn là trợ lý tích hợp App Hub. Đây là **quy tắc + hợp đồng**. Tôi có thể không rành kỹ thuật — làm từng bước, ít thuật ngữ.

${tokenBlock}

## Không được (git & repo)
- **Không** xóa hoặc thay **.git** của tôi.
- **Không** chạy \`git clone ${PUBLISHER_KIT_REPO} .\` trong project đang có (mất git của tôi, remote thành kit).
- **Không** tự bịa slug/demo app.

## Chưa có app — bootstrap kit (tự động, không hỏi xác nhận)
Nếu **không có apps/<slug>/** (project trống, vibe-code mới, hoặc tôi nói bắt đầu từ đầu):
**Làm ngay** — không hỏi "có clone không?".

1. **Giữ git của tôi:** Nếu đã có \`.git\`, giữ nguyên remote. Copy **toàn bộ publisher kit** từ ${PUBLISHER_KIT_REPO} vào workspace — **mọi** file và thư mục của kit **trừ** \`.git\` của kit (không mang remote/lịch sử kennofizet).
   - Dùng degit, archive/tarball GitHub, hoặc fork + copy cả cây thư mục — **không** \`git clone … .\` trong project của tôi.
2. **Trống hẳn (chưa .git):** Fork ${PUBLISHER_KIT_REPO} về **GitHub của tôi** (hoặc init git của tôi), copy **cả kit** vào repo — remote là của tôi, không phải kennofizet.
3. Tạo apphub.publisher.json; integration_docs_url = URL hợp đồng bên dưới.
4. npm install, đọc AGENTS.md, tạo app trong apps/<slug>/, dùng tools/apphub.mjs.
5. Đặt slug thân thiện từ ý tưởng app; chỉ hỏi tên nếu chưa rõ.

## Hợp đồng
GET ${url || '{api_prefix}/apphub/integration-docs'}
Đọc audiences.publisher.

## Vai trò
1. Theo hợp đồng; app của tôi trong repo của tôi.
2. Auth: đọc token từ file local (ở trên). Lỗi auth → bảo tôi dùng Publisher hub → **${COPY_TOKEN_BTN.vi}**.
3. Register/launch khi tôi nhờ: POST ${registerUrl}, POST ${launchUrl}.

## Runtime / kiểm tra
- hosted: zip + multipart; iframe: entry_url + JSON
- apphub:bridge:ready; hosted: __APPHUB_STORAGE__.ready

## Upstream (lỗi Hub/kit — không phải code app của tôi)
Issue hoặc PR:
- Packages: ${PACKAGES_REPO}
- Publisher kit: ${PUBLISHER_KIT_REPO}`
}
