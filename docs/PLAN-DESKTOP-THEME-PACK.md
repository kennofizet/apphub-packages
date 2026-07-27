# Plan — Desktop theme pack apply (publisher bridge)

**Status:** proposal  
**Related:** https://github.com/kennofizet/apphub-packages/issues/8  
**Requester:** @phuongdpreg (publisher Theme Studio)

## Goal

Let a child app apply a **validated CSS token pack** (`--ah-*`) to `.apphub-desktop`, persist per Hub user, and reset to built-in dark/light/auto.

## Why

Publisher app **Theme Studio** can design and save packs locally, but Hub today only supports **dark | light | auto**. There is no bridge API to apply custom tokens to the live Hub UI.

## Proposed child API

```js
await bridge.applyDesktopTheme({
  tokens: {
    'ah-accent': '#2dd4bf',
    'ah-surface': '#0f2744',
  },
  mode: 'custom', // or 'dark' | 'light' | 'auto' to clear custom pack
});
```

Fallback:

```js
await bridge.sendDesktopMessage({
  type: 'theme.apply',
  tokens: { /* … */ },
});
```

## Frontend work

1. Allowlist keys from Hub `theme.css` (`--ah-*` only).
2. Apply as CSS variables on `.apphub-desktop`.
3. Persist with desktop settings; support reset.
4. Reject unknown keys with a clear bridge error.

## Docs

- Add to `audiences.publisher.bridge.javascript_api` in integration-docs.json (implementation PR).
- Production suite themes: out of scope here (parent-bridge later).

## Acceptance

- [ ] Child apply updates Hub UI immediately
- [ ] Reset to dark/light/auto works
- [ ] Invalid keys rejected
- [ ] Documented in integration-docs
