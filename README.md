# Saidi Papetrie 🖊️

E-commerce store for school, office, IT supplies & stationery — built for the Algerian
market (COD / paiement à la livraison, 58 wilayas, French + Arabic RTL).

Inspired structurally by taziri.net, rebuilt as an original, modern, fully-featured shop
with a complete admin back-office and pluggable delivery integrations (Noest, Yalidine).

## Stack
- **Laravel 11** (PHP 8.2+) · **MySQL 8** · **Blade + Tailwind CSS** (compiled with Vite)
- Bilingual **FR / AR** with automatic **RTL**
- **Docker** for local development · deploys to **cPanel shared hosting**

## Features
**Storefront**
- Modern themed home with category "industry" tiles, featured / new / on-sale rows
- Catalog with search, category filter, price filter, sorting, pagination
- Product page with **image gallery + size/variant picker** (selecting a size can swap the photo and update the price)
- Session cart, **COD checkout** with wilaya + home/stop-desk delivery and **live delivery-fee calculation**
- Order confirmation with marketing **pixel** events (Purchase)
- **Tracking pixels** (Facebook, TikTok, Google, Snapchat) — global or **per-product**

**Admin** (`/admin`)
- Dashboard with KPIs, 14-day sales chart, recent orders, top products
- Products CRUD with **multiple images** + **sizes/variants** + per-product pixels
- Categories CRUD (icon + color for the themed look)
- Orders: filter/search, status workflow, **dispatch to a delivery provider**
- Delivery: editable **per-wilaya** home & stop-desk fees (58 wilayas seeded)
- Pixels manager · Store settings (branding, contact, social, SEO, announcement bar)

**Delivery (pluggable driver layer)** — `app/Services/Delivery`
- **Noest Express** — API driver, ready for your official docs (token + GUID in `.env`)
- **Yalidine** — manual entry now (paste tracking #), API path scaffolded for later
- **Manual / own delivery** — record your own tracking
- Add a new carrier = one class implementing `ShippingDriver`

---

## Local development (Docker)

```bash
docker compose up -d --build      # app + mysql + phpMyAdmin
```

First boot auto-runs `composer install`, `key:generate`, `migrate --seed`.

| Service       | URL                          |
|---------------|------------------------------|
| Storefront    | http://localhost:8088        |
| Admin         | http://localhost:8088/admin  |
| phpMyAdmin    | http://localhost:8089        |

**Admin login:** `admin@saidi-papetrie.dz` / `password`

Rebuild front-end assets after editing CSS/JS:
```bash
docker compose run --rm node sh -c "npm install && npm run build"
```

Handy:
```bash
docker compose exec app php artisan migrate:fresh --seed   # reset demo data
docker compose exec app php artisan tinker
```

---

## Deploy to cPanel
See **[DEPLOYMENT-CPANEL.md](DEPLOYMENT-CPANEL.md)** for the full step-by-step guide.

## Connect delivery services
See **[docs/delivery.md](docs/delivery.md)** — where to paste the Noest documentation/credentials
and how Yalidine manual mode works.
