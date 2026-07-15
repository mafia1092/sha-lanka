# Sha Lanka Travels — sha-lanka repo

One-page marketing/hub site + lean PHP backend (inquiry inbox, small CMS,
analytics). Read `HANDOVER.md` for full history and deployment detail.

## Stack
- **Public site:** PHP-rendered HTML (`public_html/index.php`, `faq.php`) +
  Tailwind Play CDN + vanilla JS (`assets/js/main.js`). No build step.
- **Backend:** plain PHP 8.x + MySQL (mysqli, prepared statements, exceptions
  enabled). No framework, no composer. PHPMailer (vendored,
  `public_html/mail/PHPMailer/`) sends via Brevo SMTP.
- **Admin panel:** `public_html/admin/` (login at `/admin/login.php`).
  Shared helpers in `public_html/sys/` (db_connect, helpers, auth, mailer,
  notifications, track, images).
- **Database:** local dev = MariaDB (Homebrew), db `shalanka`. Production =
  MySQL on the shared host. Schema + seeds: `sql/schema.sql` (idempotent —
  safe to re-import).

## Key commands
```bash
# Local dev server (or use the Claude preview "sha-lanka")
php -S localhost:5050 -t public_html

# Local DB (MariaDB via Homebrew)
brew services start mariadb
mariadb -u shalanka -p shalanka                      # creds in private/db.ini
mariadb -u shalanka -p shalanka < sql/schema.sql     # (re)apply schema/seeds

# Lint all PHP
cd public_html && for f in $(find . -name '*.php' -not -path './mail/*'); do php -l "$f"; done
```

## Rules (important)
- **Branches:** `main` currently feeds GitHub Pages (static legacy site) —
  do NOT merge the PHP backend to `main` until the Hostinger go-live.
  Backend work lives on the `backend` branch.
- **Secrets:** DB creds live in `private/db.ini` (gitignored; template =
  `private/db.ini.example`). SMTP credentials live ONLY in the `settings`
  DB table (entered via Admin → Settings). Never commit either.
- **No seeded admin:** `admin_users` starts empty; `/admin/login.php` shows a
  one-time "create first admin" form while the table is empty. Do this
  immediately after importing the schema on a new environment.
- **Content lives in the DB + uploaded files, not git:** after go-live,
  gallery uploads land in `assets/img/gallery/` on the SERVER. When
  redeploying code, never sync-delete that folder, and remember backups
  must cover the database.
- **Escaping:** every dynamic value echoed into HTML goes through `h()`;
  every query with user input uses prepared statements.
- **Images:** GD pipeline in `sys/images.php` (thumb ≤700w q72 +
  `-lg` ≤1600w q80). GD cannot decode iPhone HEIC — the admin rejects it
  with a message; export as JPEG first.
- **Gallery mosaic needs ≥8 active photos per orientation** (land/port);
  the admin blocks deletes/deactivations that would break that.
- Do not re-introduce `new Image().onload` preloading in the gallery
  animation (cached images never fire onload → animation freezes).

## Production (LIVE since 2026-07-15)
- **URL:** https://shalankatravels.com — Hostinger shared hosting, SAME
  account as the Ngo site (`u919711926`, SSH port 65002, IP 46.202.138.230).
  Be careful: `~/domains/` also holds the LIVE Negombo sites — only ever
  touch `~/domains/shalankatravels.com/`.
- **Docroot:** `~/domains/shalankatravels.com/public_html` (PHP 8.2).
- **DB:** `u919711926_gaQyX` (reused from the old WP site) — creds in
  `~/domains/shalankatravels.com/private/db.ini` (chmod 600, outside webroot).
- **Deploy** (from repo root; never use --delete — the server holds
  admin-uploaded gallery images that are NOT in git):
  ```bash
  rsync -az --chmod=D755,F644 -e "ssh -p 65002" public_html/ \
    u919711926@46.202.138.230:domains/shalankatravels.com/public_html/
  ```
  The `--chmod` matters: Hostinger 404s static files without world-read
  (this bit us at go-live).
- **Old WordPress site backup** (was on the domain before): server
  `~/backups/shalankatravels-wp-2026-07-15/` + local
  `../shalankatravels-wp-backup-2026-07-15/` (232 MB files + DB dump).
- Git flow: `main` = what's in production; work on branches, merge, deploy.
