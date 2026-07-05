# Saidi Papetrie — Android app (PWA)

The store is a **Progressive Web App (PWA)**: it installs on Android like a native
app, runs full-screen (no browser bar), keeps working when the connection drops, and
loads instantly on repeat visits. This is the right fit for a low-bandwidth COD store —
no Play Store download, no 30 MB APK, updates ship the moment you deploy.

## What was added
- `public/manifest.webmanifest` — app name, orange theme, icons, shortcuts, standalone display.
- `public/sw.js` — service worker tuned for weak connections (see below).
- `public/offline.html` — branded offline fallback page.
- `public/img/` — app icons (192, 512, maskable) generated from the logo.
- PWA `<meta>` + `apple-touch-icon` + service-worker registration in the storefront layout.
- App-style **sticky "add to cart" bar** on product pages (phones only).

## Connection resilience (Wi-Fi / 4G / LTE / weak)
The service worker (`public/sw.js`) uses:
- **Page loads**: network-first with a **4-second timeout** → falls back to the cached
  page → then to the offline page. A spotty 4G connection never leaves the user on a
  blank hanging screen.
- **CSS/JS/fonts/icons**: cache-first (instant on repeat visits, near-zero data).
- **Product images**: stale-while-revalidate, capped at 120 images so storage stays small.
- **Admin, cart & checkout writes**: never cached (always live).

> ⚠️ Service workers require **HTTPS** in production (works on `localhost` for testing).
> `saidi.h47.io` already serves HTTPS, so the PWA activates automatically there.

## How a customer installs it (Android)
1. Open **https://saidi.h47.io** in **Chrome**.
2. Chrome shows an **"Install app" / "Add to Home screen"** prompt (or use the ⋮ menu →
   *Installer l'application*).
3. The Saidi icon appears on the home screen; tapping it opens the store full-screen.

On iPhone: Safari → Share → *Sur l'écran d'accueil*.

## Optional: publish a real APK on Google Play
The PWA can be wrapped into an installable APK/AAB with a **TWA (Trusted Web Activity)** —
no code changes needed:

**Easiest — PWABuilder (web UI):**
1. Go to https://www.pwabuilder.com and enter `https://saidi.h47.io`.
2. It validates the manifest/SW, then **Package for Android** → download the `.aab`.
3. Upload the `.aab` to Google Play Console.

**CLI — Bubblewrap:**
```bash
npm i -g @bubblewrap/cli
bubblewrap init --manifest https://saidi.h47.io/manifest.webmanifest
bubblewrap build      # produces app-release-signed.apk / .aab
```
To remove the browser address bar in the TWA you add a **Digital Asset Links** file at
`https://saidi.h47.io/.well-known/assetlinks.json` (PWABuilder/Bubblewrap generate its
content from your signing key).

## Testing the PWA
- Chrome DevTools → **Application** tab → *Manifest* (should list the icons) and
  *Service Workers* (should be "activated and running").
- Lighthouse → PWA audit.
- Toggle **Offline** in DevTools → Network, reload → the offline page (or cached pages) appears.
