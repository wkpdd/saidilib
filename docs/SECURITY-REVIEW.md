# Saidi Papetrie — Security & Penetration Review

**Date:** 2026-07-03
**Scope:** Laravel 11 storefront + admin (COD e-commerce). Reviewed: authentication,
authorization/IDOR, CSRF, XSS, SQL injection, mass assignment, file uploads, the new
customer-account / client-debt / refund / incident / reset-data features, and the
delivery bordereau.
**Method:** White-box source review + live probing of the running app (auth flows,
route guards, ownership checks, upload handling).

---

## Summary

| Severity | Finding | Status |
|---|---|---|
| 🔴 High | Unrestricted file upload (product images & logo) — no type/size validation | ✅ **Fixed** |
| 🟠 Medium | No brute-force throttling on login | ✅ **Fixed** |
| 🟠 Medium | Mass assignment (`$guarded = []`) on several models | ⚠️ Mitigated / recommend |
| 🟡 Low | No rate-limit on public checkout (COD spam) | ⚠️ Recommended |
| 🟡 Low | Production hardening flags (debug, secure cookies, HTTPS) | ⚠️ Checklist |
| 🟡 Low | Admin-supplied tracking-pixel `<script>` injection trust boundary | ℹ️ By design |
| ⚪ Info | Default seeded admin password | ⚠️ Change before go-live |

No **critical** remotely-exploitable vulnerability (unauthenticated RCE / SQLi / auth
bypass) was found. Overall posture is solid for a COD storefront.

---

## Fixed during this engagement

### 1. Unrestricted file upload → potential RCE  🔴 High (fixed)
`ProductController::syncImages()` and `SettingController::update()` stored uploaded
files (`$request->file('images')`, `logo`) **without validating MIME type or size**.
An authenticated staff/manager could upload a `.php` (or `.phtml`) file into the
public `storage/` path; on hosts that execute PHP from that path (typical cPanel +
Apache) this is remote code execution.

**Fix applied:**
- Gallery images: `image|mimes:jpeg,jpg,png,webp,gif|max:5120`, capped at 12 files.
- Logo: `image|mimes:jpeg,jpg,png,webp|max:2048` — **SVG intentionally disallowed**
  (inline SVG can carry stored XSS).

### 2. Login brute-force  🟠 Medium (fixed)
Added `throttle:10,1` (10 attempts/min/IP) to admin login, customer login, and
customer registration routes.

### 3. Destructive "reset data" endpoint — hardened by design
The new go-live reset (`SettingController::resetData`) is guarded by **four** layers:
`fulladmin` middleware + **current-password re-entry** + typed confirmation word
(`REINITIALISER`) + CSRF, and writes an **audit log** line (`Log::warning`) with the
actor's id/email/IP. It only truncates test/demo tables and never touches admin
accounts, wilayas, settings or pixels.

### 4. Customer order IDOR — verified safe
`AccountController::order()` enforces `abort_unless($order->client_id === $client->id)`,
so a logged-in customer cannot read another customer's order by guessing IDs.

---

## Existing controls confirmed good

- **CSRF:** all state-changing forms carry `@csrf`; Laravel's `web` group verifies tokens.
- **Password storage:** bcrypt via `'password' => 'hashed'` cast (users *and* clients).
- **SQL injection:** exclusively Eloquent / query-builder with bound parameters; search
  filters use parameterized `LIKE`. No raw interpolated SQL.
- **XSS:** Blade auto-escapes. The only raw output (`{!! Barcode::svg() !!}`) renders an
  SVG we generate ourselves from a system-issued reference that is sanitized to
  `[\x20-\x7E]` inside `Barcode`.
- **Auth separation:** staff (`web` guard) and customers (`client` guard) are fully
  separate providers/tables — a customer session can never reach `/admin`.
- **RBAC:** `admin` / `manager` / `staff` roles; team, settings and reset require `fulladmin`.
- **Secrets:** `.env` is excluded from the deployment artifact; app key is per-install.
- **Static caching** headers scoped to asset extensions only (no HTML caching leak).

---

## Recommendations (not blocking, ordered by value)

1. **Mass assignment hardening (Medium).** `Order`, `Product`, `Category`, `Pixel`,
   `Setting`, `Wilaya`, `ProductImage` use `$guarded = []`. This is currently safe
   because every controller assigns **validated** arrays explicitly (never
   `update($request->all())`), but converting to explicit `$fillable` is cheap
   defense-in-depth against a future careless edit.
2. **Checkout rate-limiting (Low).** `POST /commande` is unauthenticated and
   un-throttled; a bot could spam COD orders / grief stock. Add `throttle` + a honeypot
   field.
3. **Production flags (Low).** Before go-live set `APP_DEBUG=false`, `APP_ENV=production`,
   `SESSION_SECURE_COOKIE=true`, force HTTPS, and add security headers
   (`X-Content-Type-Options: nosniff`, `Referrer-Policy`, a basic CSP) in `.htaccess`.
4. **Change the seeded admin password (Info).** The demo seeds `admin@saidi-papetrie.dz`
   / `password`. Rotate it (and remove the hint on the login screen) before launch.
5. **Restrict admin "image URL" field (Low).** External image URLs are stored and
   emitted in `<img src>`; constrain to `http(s)://` to avoid odd schemes.
6. **Pixel scripts (Info/by-design).** The tracking partial injects third-party
   `<script>` using admin-entered IDs — expected for a pixel manager, but treat the
   admin panel as a high-trust boundary and consider validating ID formats.

---

## Dependency advisories (2026-07)

`composer audit` reports **3 advisories against `laravel/framework`** (a signed-URL
path-confusion and a CRLF-injection in the default `email` validation rule). The fixes
ship only in the **Laravel 12** line; the framework was patch-updated to the latest
**11.54** but 11.x does not receive these fixes. Practical exposure here is low: the
mailer is `log` and no email header is built from user input, and signed URLs are not
used. **Recommendation:** plan a Laravel 12 upgrade after launch. `dompdf` (added for the
B2B price list) is on the current 3.x line with no open advisories; it renders trusted,
server-side templates only (no user-supplied HTML), so its historic SSRF/RCE classes do
not apply.

## RBAC & stored secrets (2026-07, update)

- **Granular RBAC** now gates every admin section via `perm:{section}` middleware
  (`EnsurePermission`), backed by a per-user `permissions` JSON column. `admin` role
  keeps implicit full access; the destructive data-reset stays restricted to full
  admins even if the `settings` permission is delegated. Nav links and routes are both
  gated (verified: a staff user gets 403 on non-granted sections).
- **Third-party secrets in settings.** Noest API token/GUID, Telegram bot token, and
  the Facebook/Instagram Page tokens (social publishing) are stored in the `settings`
  table in plaintext (like most Laravel app settings).
  They are only readable by users with the `settings` permission. If DB-at-rest
  encryption is a requirement, wrap these values with Laravel's `encrypted` cast /
  `Crypt`. Telegram/Noest calls use HTTPS and short timeouts, and Telegram fan-out runs
  `afterResponse()` so checkout is never blocked.

## Go-live security checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, fresh `APP_KEY`
- [ ] HTTPS enforced + `SESSION_SECURE_COOKIE=true`
- [ ] Admin password rotated; login hint removed
- [ ] Run the **Réinitialiser les données** button to clear demo data
- [ ] Set real Noest `NOEST_API_TOKEN` / `NOEST_GUID`
- [ ] `php artisan config:cache route:cache view:cache` on the server
- [ ] Confirm `storage/` and `.env` are **not** web-accessible (docroot = `public/`)
