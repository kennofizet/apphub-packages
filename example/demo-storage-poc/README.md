# localStorage isolation demo (3 hosted apps)



Proves **hosted** apps (`runtime_type: hosted`, **no** `allow-same-origin`) do **not** share `localStorage` across apps — but each app **can** persist data via the **Hub storage shim** (injected at serve time).



| App | Slug | Role |

|-----|------|------|

| Writer | `demo-storage-writer` | `setItem('apphub_cross_app_test', …)` |

| Reader 1 | `demo-storage-reader` | `getItem` same key |

| Reader 2 | `demo-storage-reader-b` | Same read test (second zip) |



## Pack



```bash

cd example

npm run pack:storage

```



Drop all three zips from `example/release/` on Hub → DEV approve → open in this order:



1. **Writer** — should show **WRITE OK** (shim proxies `localStorage` to Hub)

2. **Reader 1** — should **not** see Writer’s data (scoped by app slug)

3. **Reader 2** — same; proves another zip also cannot read Writer storage



## Expected (hosted + Hub storage shim)



| App | Expected UI |

|-----|-------------|

| Writer | `WRITE OK` — data stored on Hub origin keyed by `user + slug` |

| Readers | Key **not found** — different slug → isolated bucket |



Without the shim (raw zip outside Hub), Writer would fail with `SecurityError` (opaque sandbox).



## How Hub storage works



1. **Serve time:** backend injects `packages/backend/.../Catalog/Resources/hosted-storage-shim.js` into hosted HTML.

2. **Iframe:** Shim replaces `window.localStorage` with an in-memory map; `setItem` / `getItem` stay **synchronous** in the app.

3. **Parent:** Hub runner listens on `apphub:storage`, persists JSON in **Hub `localStorage`** at `apphub_hosted_store:{userId}:{slug}`.

4. **Hydrate:** On load, shim requests a snapshot; readers should await `window.__APPHUB_STORAGE__.ready` before reading (see `reader/app.js`).



Publishers do **not** need a build-time `localStorage` replace if they ship normal `localStorage` calls — Hub injects the shim automatically.



For heavy SPAs or cross-device sync, prefer **`runtime_type: iframe`** + publisher origin storage or your own API.



## Iframe contrast



Iframe apps on **different** publisher origins never share storage even **with** `allow-same-origin` (each has its own domain). This demo is only for **hosted zip** on Hub.

