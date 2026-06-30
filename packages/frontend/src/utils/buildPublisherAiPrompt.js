/**
 * Build a paste-ready prompt for AI coding assistants (Publisher hub).
 * Includes contract URL, scripted register/test flow, and troubleshooting pointers.
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

  return `You are helping me build, register, and smoke-test an App Hub publisher app. Prefer API/scripts over asking me to drag a zip onto the Hub desktop — but you cannot call Hub APIs without my credentials.

## Contract (fetch first — no auth)
GET ${url || '{api_prefix}/apphub/integration-docs'}
Read JSON audiences.publisher — bridge, runtime_types, deploy, hosted_runtime_troubleshooting.

## Auth (required — ask me first)
These routes need the **host user session token** (same as the logged-in product app), not a publisher API key:
- POST ${registerUrl}
- POST ${launchUrl}

Send header: X-Knf-Token: <token> (and X-Knf-Zone-Id if my host uses zones).

Before writing curl/scripts, use my session token from a **local file** (see separate "Copy token for AI" instructions if I pasted them), or ask me to run **Copy token for AI** in Publisher hub.

Send header: X-Knf-Token: <read from local .apphub-token.local or .env.local — never commit that file>

## Choose runtime
- hosted: zip bundle (dist/ + manifest.json at zip root, runtime_type: hosted)
- iframe: self-hosted SPA at entry_url (POST JSON register, no zip)

## Publish via API (instead of manual desktop drop)
1. Build production output (hosted: relative asset paths, e.g. Vite base: './').
2. hosted: zip manifest.json + index.html + assets/ at root (never node_modules). Max 50 MB.
3. With my X-Knf-Token, POST ${registerUrl}:
   - hosted: multipart/form-data bundle=@app.zip
   - iframe: JSON { runtime_type, entry_url, version, slug, permissions, … }
4. After register I can open the app from my Hub desktop icon (owner install).
5. Bump manifest version on every re-upload.

Alternative if I refuse to share a token: I drop the zip/manifest on the Hub desktop while logged in (browser uses my session — no token in the prompt).

## Verify it works
1. With X-Knf-Token: POST ${launchUrl} → runtime_url or entry_url + launch_token.
2. hosted checks: type="module" preserved; all .js return 200; no import/CSP frame-ancestors errors.
3. apphub:bridge:ready; hosted: await window.__APPHUB_STORAGE__?.ready before localStorage.
4. launch_token is minted at launch (short-lived) — separate from X-Knf-Token used to call Hub APIs.
5. Compare your hosted zip to example_tree in hosted_runtime_troubleshooting (manifest.json, index.html, assets/ at zip root).

## If something fails
Use audiences.publisher.hosted_runtime_troubleshooting — zip/build vs platform CSP/origin.

Deliver: build script, register curl/script that reads token from local file, and a short test report. On 401/403 tell me to copy a fresh token from Publisher hub.`
}

/** @param {string} url @param {string} apiBase */
function buildVi(url, apiBase) {
  const registerUrl = apiBase ? `${apiBase}/apps/register` : '{api_base}/apps/register'
  const launchUrl = apiBase ? `${apiBase}/apps/{slug}/launch` : '{api_base}/apps/{slug}/launch'

  return `Bạn giúp tôi build, đăng và smoke-test app publisher trên App Hub. Ưu tiên API/script thay vì kéo zip lên desktop — nhưng không gọi được Hub API nếu thiếu token đăng nhập của tôi.

## Hợp đồng (tải trước — không cần auth)
GET ${url || '{api_prefix}/apphub/integration-docs'}
Đọc audiences.publisher — bridge, runtime_types, deploy, hosted_runtime_troubleshooting.

## Auth (bắt buộc — hỏi tôi trước)
Các route sau cần **session token user host** (đã đăng nhập sản phẩm), không phải API key publisher:
- POST ${registerUrl}
- POST ${launchUrl}

Header: X-Knf-Token: <token> (và X-Knf-Zone-Id nếu host dùng zone).

Trước khi viết curl/script, dùng token từ **file local** (xem hướng dẫn "Sao chép token cho AI" nếu tôi đã dán), hoặc bảo tôi bấm **Sao chép token cho AI** trong Publisher hub.

Header: X-Knf-Token: <đọc từ .apphub-token.local hoặc .env.local — không commit file đó>

## Chọn runtime
- hosted: zip (dist/ + manifest.json ở root, runtime_type: hosted)
- iframe: SPA tại entry_url (POST JSON, không zip)

## Đăng qua API (thay vì thả tay desktop)
1. Build production (đường dẫn tương đối, Vite base: './').
2. hosted: zip manifest + index.html + assets/ ở root. Tối đa 50 MB.
3. Có X-Knf-Token của tôi, POST ${registerUrl}:
   - hosted: multipart bundle=@app.zip
   - iframe: JSON runtime_type, entry_url, version, slug, permissions, …
4. Sau register mở app từ icon desktop Hub.
5. Mỗi lần đăng lại tăng version semver.

Nếu tôi không gửi token: tôi thả zip/manifest lên desktop Hub khi đã đăng nhập (trình duyệt dùng session — không cần token trong prompt).

## Kiểm tra
1. Có X-Knf-Token: POST ${launchUrl} → runtime_url/entry_url + launch_token.
2. hosted: type="module", mọi .js 200, không lỗi import/CSP.
3. apphub:bridge:ready; hosted: await window.__APPHUB_STORAGE__?.ready.
4. launch_token cấp lúc launch (ngắn hạn) — khác X-Knf-Token gọi API Hub.
5. So sánh zip với example_tree trong hosted_runtime_troubleshooting (manifest.json, index.html, assets/ ở root zip).

## Lỗi
hosted_runtime_troubleshooting — zip/build vs CSP/origin host.

Giao: script build, curl/script đọc token từ file local, báo cáo test ngắn. Lỗi 401/403 thì bảo tôi sao chép token mới từ Publisher hub.`
}
