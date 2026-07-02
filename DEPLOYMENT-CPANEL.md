# Deploying Saidi Papetrie to cPanel (shared hosting, PHP 8.x + MySQL)

cPanel cannot run Docker — Docker here is only for local development. For production you
upload the Laravel app and point the web root at its `public/` folder. Two routes below:
**A) the clean way (subdomain-style docroot)** and **B) the no-SSH way**.

---

## 0. Before you start
- PHP **8.2 or 8.3** selected in cPanel → *MultiPHP Manager* (8.4 also works).
- Required PHP extensions (enable in *Select PHP Extensions* / *PHP Selector*):
  `pdo_mysql, mbstring, openssl, tokenizer, ctype, json, bcmath, fileinfo, gd, xml, curl, zip`.
- Compile assets **before** uploading (cPanel usually has no Node):
  ```bash
  docker compose run --rm node sh -c "npm install && npm run build"
  ```
  This produces `public/build/` — make sure it is uploaded.

---

## 1. Create the database
cPanel → **MySQL® Databases**:
1. Create a database, e.g. `cpuser_saidi`.
2. Create a user + password, **add the user to the database** with **All Privileges**.
3. Note the full names (cPanel prefixes them, e.g. `cpuser_saidi`, `cpuser_admin`).

## 2. Upload the files
Zip the project **without** `node_modules`, `.git`, and (optionally) `vendor`
(if your host runs Composer; otherwise include `vendor`).

- Upload the zip via **File Manager** to a folder **above** `public_html`, e.g. `/home/cpuser/saidi`.
- Extract it there.

> Why above public_html? Only Laravel's `public/` should be web-accessible. Keeping the app
> code outside the web root is the secure setup.

## 3. Point the domain at `public/`
**Option A — set the Document Root (recommended):**
- For an addon/sub-domain, cPanel → *Domains* → set **Document Root** to `/home/cpuser/saidi/public`.

**Option B — using the existing `public_html`:**
- Move the **contents** of `saidi/public/` into `public_html/`.
- Edit `public_html/index.php` and fix the two require paths to point at the app folder:
  ```php
  require __DIR__.'/../saidi/vendor/autoload.php';
  $app = require_once __DIR__.'/../saidi/bootstrap/app.php';
  ```

## 4. Configure `.env`
Copy `.env.cpanel.example` to `.env` (in the app root) and fill in:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.dz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cpuser_saidi
DB_USERNAME=cpuser_admin
DB_PASSWORD=********
```

## 5. Install dependencies & initialize
**If you have SSH (Terminal in cPanel):**
```bash
cd ~/saidi
php composer.phar install --no-dev --optimize-autoloader   # or `composer install`
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**If you do NOT have SSH:**
- Upload the project **with** `vendor/` already included (don't exclude it in step 2).
- Generate an app key locally and paste it into `.env` (`APP_KEY=base64:...`), or use the
  *Setup* helper: temporarily set `APP_DEBUG=true`, visit the site once — or run the
  one-off web installer described in `docs/cpanel-no-ssh.md`.
- Import the database: locally run `php artisan migrate --seed` against a dump, then import
  the resulting `.sql` via **phpMyAdmin**. (A ready dump can be exported from the Docker DB:
  `docker compose exec db mysqldump -uroot -proot saidi > saidi.sql`.)
- Manually create the storage symlink via File Manager, or set `FILESYSTEM_DISK=public` and
  copy `storage/app/public` into `public/storage`.

## 6. Permissions
```bash
chmod -R 775 storage bootstrap/cache
```
(File Manager → select folders → *Permissions* → 775 if no shell.)

## 7. Cron (optional but recommended)
cPanel → **Cron Jobs**, every minute:
```
php /home/cpuser/saidi/artisan schedule:run >> /dev/null 2>&1
```

## 8. Go live checklist
- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS forced (cPanel → *Domains* → Force HTTPS Redirect)
- [ ] Change the admin password (login → it's a normal user record; update via Tinker or DB)
- [ ] Set real store info in **/admin/settings**
- [ ] Add your **pixels** in /admin/pixels
- [ ] Set **delivery fees** in /admin/wilayas and fill Noest/Yalidine credentials in `.env`

---

### Updating later
Re-upload changed files, then (SSH):
```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
Always re-run `npm run build` locally and upload `public/build/` when CSS/JS changes.
