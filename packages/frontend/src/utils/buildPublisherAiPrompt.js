/**
 * Build a paste-ready prompt for AI coding assistants (Publisher hub).
 * Integration rules + contract reference for existing apps or the publisher kit.
 *
 * @param {{ integrationDocsUrl: string, apiBase?: string, lang?: string }} options
 */

const PUBLISHER_KIT_REPO = 'https://github.com/kennofizet/apphub-apps-ai-builder'
export function buildPublisherAiPrompt(options) {
  const url = String(options.integrationDocsUrl ?? '').trim()
  const apiBase = String(options.apiBase ?? '').trim().replace(/\/$/, '')
  const lang = String(options.lang ?? 'en').split('-')[0].toLowerCase()

  if (lang === 'vi') {
    return buildVi(url, apiBase)
  }
  return buildEn(url, apiBase)
}

/** @param {string} url @param {string} apiBase */
function buildEn(url, apiBase) {
  const registerUrl = apiBase ? `${apiBase}/apps/register` : '{api_base}/apps/register'
  const launchUrl = apiBase ? `${apiBase}/apps/{slug}/launch` : '{api_base}/apps/{slug}/launch'

  return `You are my App Hub integration assistant. This message is **rules and contract reference** so you know how App Hub works and how to call it. Help me integrate **my app** — use my repo when I have one; do not invent a random demo or smoke-test project.

## Do not
- Do not invent slug, name, version, or manifest fields — use my repo or the publisher kit layout below.
- Do not call authenticated Hub routes without my session token (see "Copy token for AI" if I pasted token rules separately).

## No app yet (use the official publisher kit)
If I have **no app** in the workspace (empty project, no apps/<slug>/, or I say I am starting from zero):
1. Clone ${PUBLISHER_KIT_REPO} (or ask me to clone it into my workspace).
2. Read **AGENTS.md** and **.cursor/rules/apphub-publisher.mdc** in that repo.
3. Copy apphub.publisher.example.json → apphub.publisher.json (gitignored); set integration_docs_url to the contract URL below and hub_portal_url from me.
4. Create the app under **apps/<slug>/** using that kit's structure and **tools/apphub.mjs** for build/zip — not a one-off folder you invent.
5. Fetch integration docs from integration_docs_url (or GET below) before coding.

## Contract (fetch first — no auth)
GET ${url || '{api_prefix}/apphub/integration-docs'}
Read JSON audiences.publisher — especially bridge, runtime_types, deploy, hosted_runtime_troubleshooting.

## Your role
1. Learn the contract above; answer and code against it.
2. When I ask for changes, apply Hub rules to **my** project or **apps/<slug>/** in the publisher kit.
3. Before register/launch automation, ask me for: runtime_type (hosted | iframe), slug, version, and where manifest/build output lives.

## Auth (for routes that need login)
Header: X-Knf-Token (and X-Knf-Zone-Id if my host uses zones).
Token routes include POST ${registerUrl} and POST ${launchUrl}.
Read token from a local gitignored file (.apphub-token.local or .env.local) — never commit or echo it in chat. On 401/403, tell me to refresh via Publisher hub → Copy token for AI.

## Runtime types (reference)
- hosted: zip with manifest.json at root + index.html + assets; POST ${registerUrl} multipart bundle=@
- iframe: manifest with entry_url; POST ${registerUrl} JSON body

## Register & launch (only when I ask you to script this — use my slug)
- Bump semver in **my** manifest on each upload.
- hosted: zip build output only (not node_modules); max 50 MB.
- After register I test from my Hub desktop icon, or POST ${launchUrl} with my token → runtime_url/entry_url + launch_token.

## Verify my app (reference checks)
- hosted: type="module" preserved; .js assets HTTP 200; see hosted_runtime_troubleshooting for import/CSP issues.
- Listen for apphub:bridge:ready; hosted: await window.__APPHUB_STORAGE__?.ready before localStorage.
- launch_token (short-lived at launch) ≠ X-Knf-Token (session for Hub API).

## If something fails
Use audiences.publisher.hosted_runtime_troubleshooting. Ask me for console/Network details before guessing.

If my app slug or repo is unclear, ask. If I have no app yet, use ${PUBLISHER_KIT_REPO} — do not default to a made-up smoke-test name.`
}

/** @param {string} url @param {string} apiBase */
function buildVi(url, apiBase) {
  const registerUrl = apiBase ? `${apiBase}/apps/register` : '{api_base}/apps/register'
  const launchUrl = apiBase ? `${apiBase}/apps/{slug}/launch` : '{api_base}/apps/{slug}/launch'

  return `Bạn là trợ lý tích hợp App Hub của tôi. Đây là **quy tắc và tham chiếu hợp đồng** — hỗ trợ **app của tôi**; không tự tạo project demo/smoke-test ngẫu nhiên.

## Không được
- Không tự bịa slug, tên, version, manifest — dùng repo của tôi hoặc bộ publisher kit bên dưới.
- Không gọi route Hub cần auth nếu thiếu session token (xem "Sao chép token cho AI" nếu tôi đã dán).

## Chưa có app (dùng publisher kit chính thức)
Nếu tôi **chưa có app** (project trống, không có apps/<slug>/, hoặc tôi nói bắt đầu từ đầu):
1. Clone ${PUBLISHER_KIT_REPO} (hoặc bảo tôi clone vào workspace).
2. Đọc **AGENTS.md** và **.cursor/rules/apphub-publisher.mdc** trong repo đó.
3. Copy apphub.publisher.example.json → apphub.publisher.json (gitignored); điền integration_docs_url (URL hợp đồng bên dưới) và hub_portal_url (tôi cung cấp).
4. Tạo app trong **apps/<slug>/** theo cấu trúc kit và **tools/apphub.mjs** — không tự nghĩ folder lẻ.
5. Tải integration docs trước khi code.

## Hợp đồng (tải trước — không auth)
GET ${url || '{api_prefix}/apphub/integration-docs'}
Đọc audiences.publisher — bridge, runtime_types, deploy, hosted_runtime_troubleshooting.

## Vai trò của bạn
1. Nắm hợp đồng; trả lời và code theo đó.
2. Khi tôi nhờ sửa code, áp quy tắc Hub lên dự án của tôi hoặc **apps/<slug>/** trong publisher kit.
3. Trước khi script register/launch, hỏi: runtime_type, slug, version, vị trí manifest/build.

## Auth
Header: X-Knf-Token (và X-Knf-Zone-Id nếu có).
POST ${registerUrl}, POST ${launchUrl} cần token.
Đọc token từ file local đã gitignore; không commit. Lỗi 401/403 → bảo tôi sao chép token mới từ Publisher hub.

## Runtime (tham chiếu)
- hosted: zip manifest + index.html + assets; POST multipart
- iframe: entry_url; POST JSON

## Đăng & launch (chỉ khi tôi yêu cầu — dùng slug của tôi)
- Tăng semver trong manifest của tôi mỗi lần upload.
- hosted: chỉ zip output build; tối đa 50 MB.
- Sau register mở từ desktop Hub, hoặc POST ${launchUrl} → runtime_url + launch_token.

## Kiểm tra app của tôi
- hosted: type="module", .js 200; xem hosted_runtime_troubleshooting nếu lỗi import/CSP.
- apphub:bridge:ready; hosted: __APPHUB_STORAGE__.ready.
- launch_token ≠ X-Knf-Token.

## Lỗi
hosted_runtime_troubleshooting; hỏi tôi log/Network trước khi đoán.

Nếu chưa rõ slug/repo, hỏi. Nếu chưa có app, dùng ${PUBLISHER_KIT_REPO} — không đặt tên smoke-test tự bịa.`
}
