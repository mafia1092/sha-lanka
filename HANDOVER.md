# Sha Lanka Travels — Project Handover

Last updated: 2026-07-03. This is the single source of truth for picking the project up on another laptop (or a fresh Claude Code session). Everything needed to continue is here.

---

## 1. Quick start on the new laptop

```bash
# 1. Clone the repo (this is where ALL the code + optimized images live)
git clone https://github.com/mafia1092/sha-lanka.git
cd sha-lanka

# 2. (Only if you'll re-process images) install sharp locally — it is gitignored
npm install --no-save sharp

# 3. Preview: it's a static site. Any static server works, e.g.
npx serve .        # or: python -m http.server 5050
# then open http://localhost:5050
```

- **Live site:** https://mafia1092.github.io/sha-lanka/
- **GitHub repo:** https://github.com/mafia1092/sha-lanka (owner GitHub account: `mafia1092`)
- **In Claude Code:** open the cloned folder, then tell Claude to read `HANDOVER.md` first. The Claude Preview server is defined in `.claude/launch.json` (name: `sha-lanka`, port 5050) — but `.claude/` is gitignored, so it will be recreated on first `preview_start`.

You need Git auth to the `mafia1092/sha-lanka` repo on the new machine (GitHub sign-in via the Git credential manager, `gh auth login`, or a Personal Access Token).

---

## 2. What this project is

A modern, responsive **one-page marketing site for Sha Lanka Travels** (adventure-vehicle business in Sri Lanka: motorcycle/jeep/motorhome rentals, guided tours, and a car-carrier service).

It now works as a **hub/portal**: each service links OUT to its own dedicated website (see the map in §8). The contact form opens a **prefilled WhatsApp chat** (no backend). FAQ lives on its own page.

**Stack:** plain **HTML + Tailwind (Play CDN, inline config) + vanilla JS**. **No build step** — the files run as-is in any browser and upload as-is to any host. Libraries via CDN: AOS (scroll reveals), GLightbox (image lightbox), Google Fonts (Oswald + Inter).

---

## 3. Deployment (how "saving" and "publishing" work)

- **Hosting = GitHub Pages** (free, public repo, deploy from `main` branch / root).
- **To publish:** commit and `git push origin main`. Pages auto-rebuilds in ~1–2 min, then a CDN cache refresh (can lag a few minutes).
- **Verifying a deploy:** the Pages `builds/latest` API lags and is unreliable — instead **poll the live URL** for the new content (e.g. `curl -s "https://mafia1092.github.io/sha-lanka/?cb=$(date +%s)" | grep something-new`).
- **Git identity used so far** (kept out of global config on purpose): commits were made with
  `git -c user.name="mafia1092" -c user.email="shacabsl@gmail.com" commit -m "..."`.
  On the new laptop either do the same, or set your own `git config user.*`.
- **Hostinger (later):** the domain isn't live yet. When it is, upload the **same static files** to `public_html` via File Manager — no changes needed because there's no build step.

---

## 4. Project structure

```
index.html            Main one-page site (all sections)
faq.html              Standalone FAQ page (linked from the footer)
favicon.svg           Brand-blue favicon
assets/
  css/styles.css      All custom CSS (Tailwind utilities are inline in HTML)
  js/main.js          All JS: header scroll, mobile menu, AOS/GLightbox init,
                      card slideshows, gallery mosaic + animation, FAQ accordion,
                      contact-form → WhatsApp
  img/
    logo-on-dark.png   Header logo, white text (shown over the dark hero)
    logo-on-light.png  Header logo, gray text, TRANSPARENT (shown when header turns cream on scroll)
    badge-*.png        Small round service badges pinned on fleet/tour cards
    slides/<slug>/{1,2,3}.jpg   Per-card slideshow photos (7 slugs, see §7)
    gallery/gNN.jpg + gNN-lg.jpg   Gallery photos: thumb (≤700w) + lightbox (≤1600w), g01–g45
    partner/… (see partners/) 
    partners/site-*.png  Footer "our other websites" badges
.gitignore
HANDOVER.md           (this file)
```

`main.js` runs inside one `DOMContentLoaded` handler; each feature block is guarded by an element-existence check, so it's safe on both `index.html` and `faq.html`.

---

## 5. Key runtime behaviors (in `assets/js/main.js`)

- **Header:** gains `.scrolled` after 40px; swaps `logo-on-dark` → `logo-on-light` and shrinks the bar. Both logos are transparent PNGs.
- **Card slideshows** (`[data-slideshow]`, fleet + tours + carrier): horizontal **sliding carousel** — slides wrapped in a `.slides-track` with a cloned first frame for a seamless loop; advances every 5s.
- **Gallery** (`#gallery-mosaic`): **orientation-matched mosaic**. 16 frames (8 landscape `.gframe.landscape` 3:2 + 8 portrait `.gframe.portrait` 3:4). JS lays them into **balanced equal-height columns** (4 wide ≥900px, else 2) — each column gets the same 2 landscape + 2 portrait, so all columns end at the same height (flat bottom, no gaps). A running animation swaps a random frame to a fresh photo **of its own orientation** every ~2.2s (portrait photos only ever go in portrait frames). Click a frame → GLightbox opens the full `-lg` image. Orientation buckets are the `LAND` / `PORT` arrays in `main.js` (update these if gallery photos change).
  - Gotcha already fixed: swapping sets `img.src` directly. Do **not** re-introduce `new Image().onload` preloading — `onload` doesn't fire for cached images and the animation freezes once thumbnails are cached.
- **Contact form** (`#contact-form`): on submit it composes a WhatsApp message from the fields and opens `https://wa.me/94777488746?text=…` (WhatsApp Web on desktop / app on mobile). No backend, no Web3Forms key needed.

---

## 6. Image pipeline (Node + `sharp`)

Owner photos are huge (15–35 MB raw). They're optimized locally with **`sharp`** and only the **optimized copies** are committed. Throwaway scripts were kept in the session scratchpad (not in the repo); re-create as needed.

- `sharp` is installed with `npm install --no-save sharp` (goes into gitignored `node_modules/`).
- Run scripts with NODE_PATH pointed at the project's node_modules (needed if the script lives outside the project):
  `NODE_PATH="<abs path>/node_modules" node script.js`
- **`sharp` cannot decode HEIC** in this build — HEIC inputs throw and are skipped. Re-export HEIC → JPG first.
- Typical recipe: `sharp(src).rotate().resize({width, fit:'inside', withoutEnlargement:true}).jpeg({quality, mozjpeg:true})`. Gallery = two sizes (`gNN.jpg` ≤700w q72 thumb, `gNN-lg.jpg` ≤1600w q80). Badges/logos → PNG (keep transparency). Header transparent logo was made by knocking out the white background of the source logo.

---

## 7. What is NOT in Git (important)

`.gitignore` excludes the **original source files** because they're large and only the optimized versions are needed live. These exist **only on the original laptop**:

- `logo/` — brand logos + the round site badges (IMG_8566–8570, IMG_8644*, SHA LANKA LOGO, rent_69f87bb216e26)
- Source photo folders: `Gallery pic/`, `Motocycle rental/`, `Motocycle Tours/`, `Jeep rental/`, `Jeep tours/`, `Motorhome rental/`, `Motorhome  Tours/`, `car carrier/`
- `node_modules/`, `.claude/`, `*.tmp`

→ The cloned repo has everything the **live site** needs. But to **re-process/add images or logos** on the new laptop, copy those source folders over separately (USB/cloud) — they are not on GitHub.

**Slide slugs** (folder → `assets/img/slides/<slug>/`): `moto-rental`, `moto-tours`, `jeep-rental`, `jeep-tours`, `motorhome-rental`, `motorhome-tours`, `car-carrier`.

---

## 8. Content & external links reference

**Brand:** Sha Lanka Travels — tagline "Your Comprehensive Travel Partner".
**Address:** 137/4, St. Francis Road, Welihena, Kochchikade, Sri Lanka.
**Phone / WhatsApp:** +94 77 748 8746 (`wa.me/94777488746`). **Email:** suranga007@yahoo.com.
**Socials:** Facebook `profile.php?id=61575452196155`, Instagram `sha_lanka_travels`, TikTok `@shalankatravelsofficial`.
**Palette:** brand blue `#1577BE` / glow `#2BA8E0`; cream `#F5F0E6`, espresso `#1C1A17`, sand `#D9C3A3`, bark `#2B2620`. **Fonts:** Oswald (display), Inter (body).

**Section buttons → sub-brand sites (open in new tab):**
| Section | Links to |
|---|---|
| Fleet ▸ Motorcycles | negombo-motorcycle-tours.com |
| Fleet ▸ Jeeps | srilankajeeprent.com |
| Fleet ▸ Motorhomes | camperexplore.com/index.html |
| Tours ▸ Motorcycle | www.srilankamotorcycletours.com |
| Tours ▸ Jeep | srilankajeeptours.com |
| Tours ▸ Motorhome | camperexplore.com/tours.html |
| Car Carrier | carcarriernegombo.com |

**Footer "Explore Our Family of Websites" badges (5):** Negombo Motorcycle Tours, Sri Lanka Motorcycle Tours, Sri Lanka Jeep Rentals (→ **srilankajeeprent.com**; the badge's printed `srilankajeeprentals.com` is dead), Sri Lanka Jeep Tours, Camper Explore. (ceylonroyalenfieldtours.com badge exists in `logo/` but is intentionally excluded.)

---

## 9. Known quirks / gotchas (save yourself time)

- **`preview_screenshot` times out (~30s)** in this environment — verify with `preview_eval` (computed styles, dimensions, `data-*`) instead.
- **Long-running `preview_eval` loops** (>~7s of `await`) can hit the 30s tool budget — use short two-shot checks (snapshot, wait ~2.6s, snapshot).
- **GitHub Pages `builds/latest` API lags** — confirm deploys by polling the live URL, not the API.
- **`gh` CLI** on the original laptop is at `C:\Program Files\GitHub CLI\gh.exe` (not on the bash PATH). New laptop: install + `gh auth login`.
- Harmless `LF will be replaced by CRLF` warnings on Windows commits.

---

## 10. Possible next steps / open items

- **Footer logos are currently 100px** (owner asked +150%); may want to dial back to ~70–80px.
- **Hostinger go-live:** upload the static files to `public_html` when the domain is ready (no build needed).
- If `srilankajeeprentals.com` goes live, the Jeep Rentals footer badge could point there instead of `srilankajeeprent.com`.
- Optional performance polish before go-live: compile Tailwind to one static CSS file instead of the Play CDN.

---

## 11. Full history

The complete, dated decision log for every change (hub conversion, real photos, gallery iterations, footer/logo work) lives in the git commit history (`git log`) and — on the original laptop only — in the Claude plan file at `~/.claude/plans/` (not synced). The commit messages are descriptive; `git log --stat` is the fastest way to see what each change touched.
