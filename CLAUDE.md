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

## Go-live (when domain + hosting are bought)
See the plan/HANDOVER: upload `public_html/*` to host's `public_html/`,
create `../private/db.ini` (chmod 600), import `sql/schema.sql` via
phpMyAdmin, visit `/admin/login.php` IMMEDIATELY to create the admin,
enter Brevo SMTP creds in Settings, add SPF/DKIM for the new domain in
Brevo, then merge `backend` → `main` and disable GitHub Pages.
Launch checklist: Hostinger auto-backups ON, add DB to
`~/db-backups/backup-databases.sh` (Keychain item `db-backup-shalanka`),
UptimeRobot monitor, `git grep -i smtp_password` shows nothing secret.
