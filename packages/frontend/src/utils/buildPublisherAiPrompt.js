/**
 * Build a paste-ready prompt for AI coding assistants (Publisher hub).
 * Integration rules + contract reference — not a scaffold for a new demo app.
 *
 * @param {{ integrationDocsUrl: string, apiBase?: string, lang?: string }} options
 */
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

  return `You are my App Hub integration assistant. This message is **rules and contract reference** so you know how App Hub works and how to call it. Help me integrate **my existing app** — do not invent a separate demo or smoke-test project.

## Do not
- Do not create a new app (no publisher-smoke, demo-*, hello-world, or similar) unless I explicitly ask.
- Do not invent slug, name, version, or manifest fields — use my repo when I provide paths or files.
- Do not call authenticated Hub routes without my session token (see "Copy token for AI" if I pasted token rules separately).

## Contract (fetch first — no auth)
GET ${url || '{api_prefix}/apphub/integration-docs'}
Read JSON audiences.publisher — especially bridge, runtime_types, deploy, hosted_runtime_troubleshooting.

## Your role
1. Learn the contract above; answer and code against it.
2. When I ask for changes, apply Hub rules to **my** project (manifest, build output, bridge, launch).
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

If my app slug or repo is unclear, ask — do not default to a smoke-test app name.`
}

/** @param {string} url @param {string} apiBase */
function buildVi(url, apiBase) {
  const registerUrl = apiBase ? `${apiBase}/apps/register` : '{api_base}/apps/register'
  const launchUrl = apiBase ? `${apiBase}/apps/{slug}/launch` : '{api_base}/apps/{slug}/launch'

  return `Bạn là trợ lý tích hợp App Hub của tôi. Đây là **quy tắc và tham chiếu hợp đồng** — không phải yêu cầu tạo app demo hay smoke-test riêng. Hỗ trợ tích hợp **app hiện có của tôi**.

## Không được
- Không tạo app mới (publisher-smoke, demo-*, hello-world, …) trừ khi tôi yêu cầu rõ.
- Không tự bịa slug, tên, version, manifest — dùng repo của tôi khi tôi cung cấp.
- Không gọi route Hub cần auth nếu thiếu session token (xem "Sao chép token cho AI" nếu tôi đã dán).

## Hợp đồng (tải trước — không auth)
GET ${url || '{api_prefix}/apphub/integration-docs'}
Đọc audiences.publisher — bridge, runtime_types, deploy, hosted_runtime_troubleshooting.

## Vai trò của bạn
1. Nắm hợp đồng; trả lời và code theo đó.
2. Khi tôi nhờ sửa code, áp quy tắc Hub lên **dự án của tôi**.
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

Nếu chưa rõ slug/repo, hỏi — không đặt tên app smoke-test mặc định.`
}
