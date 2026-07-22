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
  admin uploads land on the SERVER — gallery in `assets/img/gallery/`, the
  homepage hero in `assets/img/hero/` (gitignored), service slides in
  `assets/img/slides/<slug>/`. When redeploying code, never sync-delete
  those folders, and remember backups must cover the database.
- **Escaping:** every dynamic value echoed into HTML goes through `h()`;
  every query with user input uses prepared statements.
- **Images:** GD pipeline in `sys/images.php` — gallery (thumb ≤700w q72 +
  `-lg` ≤1600w q80), service slides (≤1600w q80), homepage hero (≤1920w q82,
  filename in `settings.hero_image`, uploaded via Admin → Content). GD cannot
  decode iPhone HEIC — the admin rejects it with a message; export as JPEG first.
- **Gallery = TWO full-bleed rows sliding sideways forever** (sits under
  About). `index.php` renders every active photo flat; `main.js` zips
  land/port and deals them into 2 rows in pairs (mixed shapes per row),
  repeats each row until wider than the screen, then duplicates it — the CSS
  slides each row by exactly `-50%` (= one copy), so the loop is seamless BY
  CONSTRUCTION (never reintroduce JS-measured pixel distances). Row height
  is fixed (260px / 160px mobile); frame widths are explicit per orientation
  (390/195, 240/120) with `min-width:0` + `max-width` — WITHOUT that cap,
  WebKit inflates frames to the photos' intrinsic 700px width once loaded
  and the loop runs off the end (the great iPhone bug of 2026-07).
  ALL active photos are shown; speed is `SPEED` px/sec in `main.js`.
  `.gallery-mosaic` needs `contain: paint`. The admin's ≥8-per-orientation
  guard is now just a sensible minimum, not a layout requirement.
- Do not re-introduce `new Image().onload` preloading in the gallery
  (cached images never fire onload → animation freezes). The old per-photo
  swap animation was removed — the slide replaces it.
- **`.gcol` MUST keep `min-width: 0` (+ `max-width` = its flex-basis).**
  Without it, WebKit floors each column at the photos' intrinsic width
  (700px thumbs) once images load — columns balloon 165→700px, the wall
  outgrows the slide distance and the loop runs off the end (iPhone bug,
  invisible in Chrome). `gtest.php` (in repo) is the on-device harness that
  proves the loop: open `/gtest.php?fast=1` — HUD must say SEAMLESS.
- **CSS/JS are cache-busted** via `asset()` (`?v=filemtime`) in `index.php` /
  `faq.php`. Without it, returning visitors mix new HTML with stale cached
  CSS/JS and the layout breaks. Keep new asset links going through `asset()`.

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
  rsync -az -e "ssh -p 65002" public_html/ \
    u919711926@46.202.138.230:domains/shalankatravels.com/public_html/
  # macOS's rsync has no --chmod, so fix perms afterwards — Hostinger 404s
  # any file that isn't world-readable (this bit us at go-live AND on the
  # favicon update):
  ssh -p 65002 u919711926@46.202.138.230 \
    'cd domains/shalankatravels.com/public_html && \
     find . -type d -exec chmod 755 {} + && find . -type f -exec chmod 644 {} +'
  ```
- **Old WordPress site backup** (was on the domain before): server
  `~/backups/shalankatravels-wp-2026-07-15/` + local
  `../shalankatravels-wp-backup-2026-07-15/` (232 MB files + DB dump).
- Git flow: `main` = what's in production; work on branches, merge, deploy.
