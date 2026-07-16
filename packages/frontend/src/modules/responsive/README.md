# Responsive / device mode

Branch scaffold for Hub chrome that adapts to **PC** vs **mobile** (viewport width/height), with per-phone folders for styles and custom actions.

## Layout

```
modules/responsive/
  detectDevice.js          # mode + phone snapshot
  registry.js              # merge mode + phone profiles
  composables/
    createDeviceModeState.js
    useDeviceMode.js
  profiles/
    pc/                    # desktop mode
      actions.js
      styles/pc.css
    mobile/                # mobile mode (all phones)
      actions.js
      styles/mobile.css
      phones/
        _default/          # unknown mobile UA
        iphone/            # UA match
        android/           # UA match
```

## Detection (current)

| Mode | Rule |
|------|------|
| `mobile` | `width ≤ 768` **or** `min(width,height) ≤ 500` |
| `pc` | otherwise |

Phone id (mobile only): light UA → `iphone` / `android` / `_default`.

## Usage

```js
import { useDeviceMode } from '@kennofizet/apphub-frontend'

const device = useDeviceMode()
device.state.mode      // 'pc' | 'mobile'
device.state.phone     // '' | '_default' | 'iphone' | 'android'
device.isMobile.value
device.runAction('myAction', payload)
```

Desktop root gets:

- `data-apphub-device="pc|mobile"`
- `data-apphub-phone="iphone|…"` (mobile only)

Style with those attributes (see profile CSS files).

## Scale: add a phone type

1. Create `profiles/mobile/phones/{id}/` with `index.js`, `actions.js`, `styles.css`.
2. Register in `registry.js` (`PHONE_PROFILES`) **or** call `registerPhoneProfile(id, profile)` at runtime.
3. Map detection in `detectPhoneProfile()` (UA / size / host override).

## Scale: custom actions

Export named functions from `actions.js`. Phone actions override mobile keys with the same name. Call via `runAction('name', …)`.

## Next (this branch)

- [x] Mobile layout for taskbar / start / windows
- Host override: `installAppHubModule({ deviceMode: 'mobile' })` force
- Finer breakpoints (tablet)

## Mobile chrome behavior

Phone home layout (not a shrunk Windows desktop):

- **Status bar** at top (clock + signal/battery; Island on iPhone)
- **App icon grid** (4 columns; ignores PC absolute icon positions)
- **Floating dock** (`AppHubMobileDock`) — open apps + notifications (+ shutdown if enabled)
  - No Start button, no pins, no Draft Store / Dev Tools defaults
  - PC keeps `.apphub-desktop__taskbar` unchanged
- **Home indicator** under the dock
- Windows: fullscreen; no drag/resize; close only
- Dev origin bar + Start menu: PC only
- Resize to mobile: `applyMobileFullscreenToAll`
