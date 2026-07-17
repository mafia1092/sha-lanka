-- ============================================================
-- Sha Lanka Travels — database schema + seed data
-- Safe to re-run: CREATE TABLE IF NOT EXISTS + guarded seeds.
-- Import locally:   mariadb -u shalanka -p shalanka < sql/schema.sql
-- Import Hostinger: phpMyAdmin -> Import -> this file
-- ============================================================

-- This file is UTF-8; force the client connection to match so seeded text
-- (em-dashes, accents) is stored correctly regardless of client defaults.
SET NAMES utf8mb4;

-- 1. Inquiries — every contact-form submission lands here (the "inbox")
CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL,
  phone          VARCHAR(40)  NOT NULL DEFAULT '',
  service_choice VARCHAR(60)  NOT NULL DEFAULT '',
  message        TEXT NOT NULL,
  status         ENUM('new','replied','closed') NOT NULL DEFAULT 'new',
  admin_note     TEXT NULL,
  ip_hash        VARCHAR(64) NOT NULL DEFAULT '',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status, created_at),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Admin users — no seed row on purpose (public repo = no passwords in git).
--    admin/login.php shows a one-time "create first admin" form while this
--    table is empty. Do that IMMEDIATELY after importing this schema.
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(150) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'superadmin',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Settings — key/value store (SMTP creds are entered via the admin UI,
--    never committed to git; they live only in this table).
CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Site content — curated editable text blocks shown on the public pages
CREATE TABLE IF NOT EXISTS site_content (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  content_key   VARCHAR(100) NOT NULL UNIQUE,
  content_value TEXT NULL,
  label         VARCHAR(150) NOT NULL DEFAULT '',
  section       VARCHAR(30)  NOT NULL DEFAULT '',
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Gallery — one row per photo. file_base 'g01' means the files
--    assets/img/gallery/g01.jpg (thumb) + g01-lg.jpg (lightbox) exist.
CREATE TABLE IF NOT EXISTS gallery_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  file_base   VARCHAR(80) NOT NULL UNIQUE,
  orientation ENUM('land','port') NOT NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active, orientation, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. FAQ items (faq.php renders these in sort_order)
CREATE TABLE IF NOT EXISTS faq_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question   VARCHAR(500) NOT NULL,
  answer     TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Service cards — the 7 fleet/tour/carrier cards. Slide photos stay as
--    files (assets/img/slides/<slug>/{1,2,3}.jpg, replaced in place).
CREATE TABLE IF NOT EXISTS service_cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(40) NOT NULL UNIQUE,
  section     ENUM('fleet','tours','carrier') NOT NULL,
  title       VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  link_url    VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Notifications — the admin bell
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         VARCHAR(30) NOT NULL,
  title        VARCHAR(255) NOT NULL,
  message      TEXT NOT NULL,
  link         VARCHAR(255) NULL,
  inquiry_id   INT UNSIGNED NULL,
  is_read      TINYINT(1) NOT NULL DEFAULT 0,
  is_dismissed TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_unread (is_read, is_dismissed, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Page views — lightweight analytics (rows older than 180 days are pruned
--    automatically by sys/track.php)
CREATE TABLE IF NOT EXISTS page_views (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  VARCHAR(64) NOT NULL,
  page_url    VARCHAR(255) NOT NULL,
  referer     VARCHAR(255) NULL,
  ip_hash     VARCHAR(64) NOT NULL,
  device_type ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_page (page_url, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Rate limiting for the public contact endpoint
CREATE TABLE IF NOT EXISTS rate_limit (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind       VARCHAR(20) NOT NULL DEFAULT 'contact',
  ip_hash    VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rl (kind, ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA — re-run safe:
--  * settings / site_content / service_cards use INSERT IGNORE (rows the
--    admin only EDITS — existing values are never overwritten, and new keys
--    added in future versions get created)
--  * gallery_images / faq_items seed ONLY into an empty table (rows the
--    admin can DELETE — a re-import must not resurrect them)
-- ============================================================

-- Settings (SMTP username/password/from left EMPTY on purpose — enter them
-- in Admin -> Settings. ip_salt is auto-generated on first use.)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('smtp_host',              'smtp-relay.brevo.com'),
  ('smtp_port',              '587'),
  ('smtp_username',          ''),
  ('smtp_password',          ''),
  ('smtp_from_email',        ''),
  ('smtp_from_name',         'Sha Lanka Travels'),
  ('business_email',         'suranga007@yahoo.com'),
  ('business_phone',         '+94 77 748 8746'),
  ('business_whatsapp',      '94777488746'),
  ('business_address',       '137/4, St. Francis Road, Welihena, Kochchikade'),
  ('notify_email_on_inquiry','1'),
  -- Homepage hero background: filename inside assets/img/hero/ (uploaded via
  -- Admin -> Site Text). Empty = fall back to the default in styles.css.
  ('hero_image',             ''),
  ('ip_salt',                '');

-- Site content (current live wording, lifted from index.html / faq.html)
INSERT IGNORE INTO site_content (content_key, content_value, label, section) VALUES
  ('hero_eyebrow',   'Your Comprehensive Travel Partner', 'Hero — small line above the headline', 'home'),
  ('hero_title_1',   'Explore Sri Lanka,',                'Hero — headline line 1', 'home'),
  ('hero_title_2',   'Your Way',                          'Hero — headline line 2 (blue)', 'home'),
  ('hero_sub',       'Adventure-ready motorcycles, rugged 4x4 jeeps and overland motorhomes — plus expertly guided tours and nationwide vehicle transport.', 'Hero — subtitle paragraph', 'home'),
  ('about_story_1',  'Born from a love of the open road and Sri Lanka''s wild, beautiful terrain, Sha Lanka Travels is built on an adventure-ready heritage. From misty hill-country passes to coastal highways and rugged backcountry trails, we equip travellers with vehicles and journeys made to go further.', 'About — story paragraph 1', 'about'),
  ('about_story_2',  'Whether you want the freedom to roam on your own or a curated expedition led by people who know every bend in the road, we''ve got you covered — three ways:', 'About — story paragraph 2', 'about'),
  ('fleet_sub',      'Three ways to roam the island — each maintained, road-ready, and built for the journey ahead.', 'Fleet — section subtitle', 'fleet'),
  ('tours_sub',      'Hand-crafted journeys led by local guides who know the island inside out.', 'Tours — section subtitle', 'tours'),
  ('carrier_sub',    'Specialised vehicle transport and towing — safe, insured and nationwide.', 'Car Carrier — section subtitle', 'carrier'),
  ('gallery_sub',    'Vehicles, tours and scenic routes from across the island.', 'Gallery — section subtitle', 'gallery'),
  ('contact_sub',    'Tell us about your trip and we''ll get back to you with a tailored quote.', 'Contact — section subtitle', 'contact'),
  ('footer_tagline', 'Your comprehensive travel partner — adventure-ready rentals, guided tours and nationwide vehicle transport across Sri Lanka.', 'Footer — tagline under the logo', 'footer'),
  ('faq_banner_sub', 'Everything you need to know about our rentals, guided tours and vehicle transport.', 'FAQ page — banner subtitle', 'faq');

-- Service cards (titles/descriptions/links exactly as on the live site)
INSERT IGNORE INTO service_cards (slug, section, title, description, link_url, sort_order) VALUES
  ('moto-rental',      'fleet',   'Motorcycles',        'Adventure-ready riding for the open road and the back trails. Light, nimble and endlessly fun.', 'https://negombo-motorcycle-tours.com/', 1),
  ('jeep-rental',      'fleet',   'Jeeps',              'Rugged 4x4 capability for national parks, mountain trails and everything in between.', 'https://srilankajeeprent.com/', 2),
  ('motorhome-rental', 'fleet',   'Motorhomes',         'Comfortable overland travel with your bed, kitchen and view all on board.', 'https://camperexplore.com/index.html', 3),
  ('moto-tours',       'tours',   'Motorcycle Tours',   'Throttle through hill country, tea estates and coastal roads on a guided ride.', 'https://www.srilankamotorcycletours.com/', 1),
  ('jeep-tours',       'tours',   'Jeep Expeditions',   'Off the beaten track to national parks, waterfalls and viewpoints few ever reach.', 'https://srilankajeeptours.com/', 2),
  ('motorhome-tours',  'tours',   'Motorhome Journeys', 'Slow travel done right — a rolling basecamp following the island''s best routes.', 'https://camperexplore.com/tours.html', 3),
  ('car-carrier',      'carrier', 'Your Vehicle, Delivered Safely', 'When your vehicle needs to get somewhere it can''t drive itself, our car carrier service handles it. From breakdowns and recovery to scheduled transport between cities, we move cars, bikes and equipment with care.', 'https://carcarriernegombo.com/', 1);

-- Gallery: the 45 original photos. Seeded ONLY when the table is empty —
-- the admin deletes gallery rows (and their files), and INSERT IGNORE would
-- resurrect them as broken tiles on a re-import.
INSERT INTO gallery_images (file_base, orientation, sort_order)
SELECT b, o, s FROM (
  SELECT 'g01' AS b, 'land' AS o, 1 AS s
  UNION ALL SELECT 'g02','land',2  UNION ALL SELECT 'g03','land',3  UNION ALL SELECT 'g04','land',4
  UNION ALL SELECT 'g05','land',5  UNION ALL SELECT 'g06','port',6  UNION ALL SELECT 'g07','port',7
  UNION ALL SELECT 'g08','land',8  UNION ALL SELECT 'g09','land',9  UNION ALL SELECT 'g10','land',10
  UNION ALL SELECT 'g11','land',11 UNION ALL SELECT 'g12','land',12 UNION ALL SELECT 'g13','land',13
  UNION ALL SELECT 'g14','land',14 UNION ALL SELECT 'g15','land',15 UNION ALL SELECT 'g16','land',16
  UNION ALL SELECT 'g17','port',17 UNION ALL SELECT 'g18','land',18 UNION ALL SELECT 'g19','port',19
  UNION ALL SELECT 'g20','port',20 UNION ALL SELECT 'g21','land',21 UNION ALL SELECT 'g22','land',22
  UNION ALL SELECT 'g23','land',23 UNION ALL SELECT 'g24','port',24 UNION ALL SELECT 'g25','port',25
  UNION ALL SELECT 'g26','port',26 UNION ALL SELECT 'g27','port',27 UNION ALL SELECT 'g28','port',28
  UNION ALL SELECT 'g29','port',29 UNION ALL SELECT 'g30','port',30 UNION ALL SELECT 'g31','port',31
  UNION ALL SELECT 'g32','port',32 UNION ALL SELECT 'g33','land',33 UNION ALL SELECT 'g34','port',34
  UNION ALL SELECT 'g35','land',35 UNION ALL SELECT 'g36','land',36 UNION ALL SELECT 'g37','land',37
  UNION ALL SELECT 'g38','land',38 UNION ALL SELECT 'g39','port',39 UNION ALL SELECT 'g40','port',40
  UNION ALL SELECT 'g41','port',41 UNION ALL SELECT 'g42','land',42 UNION ALL SELECT 'g43','port',43
  UNION ALL SELECT 'g44','port',44 UNION ALL SELECT 'g45','port',45
) seed
WHERE NOT EXISTS (SELECT 1 FROM gallery_images);

-- FAQ (seeded only when the table is completely empty)
INSERT INTO faq_items (question, answer, sort_order)
SELECT q, a, s FROM (
  SELECT 'What do I need to rent a vehicle?' AS q,
         'A valid driving licence (and an International Driving Permit where required), a form of ID, and a refundable deposit. Our team will confirm the exact requirements for your chosen vehicle.' AS a, 1 AS s
  UNION ALL SELECT 'Do you deliver vehicles to my location?',
         'Yes — we offer island-wide pickup and delivery options for rentals. Share your location when you enquire and we''ll arrange the most convenient handover.', 2
  UNION ALL SELECT 'Are your guided tours customisable?',
         'Absolutely. Every itinerary can be tailored to your dates, pace and interests — from short weekend rides to multi-week island expeditions.', 3
  UNION ALL SELECT 'What areas does the car carrier service cover?',
         'Our vehicle transport and towing covers the whole of Sri Lanka. Whether it''s a breakdown recovery or a scheduled inter-city move, we''ll get your vehicle there safely.', 4
  UNION ALL SELECT 'Is insurance included?',
         'All rentals and transport are covered by insurance. We''ll walk you through exactly what''s included and any optional extras before you book.', 5
) seed
WHERE NOT EXISTS (SELECT 1 FROM faq_items);
