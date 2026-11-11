# SGC LABS® Manager Pro — Changelog

## v7.4.0 — 2026-05-09 | Build: 20260509-1000 | Export SEO Engine

### GPL 2.0 + WordPress International Compliance
- Plugin Name shortened to "SGC Site Manager Pro" (WP.org max 80 chars)
- `Requires at least: 6.0` (was 5.8)
- `Requires PHP: 8.0` (was 7.4 — EOL)
- `Tested up to: 6.8` added
- `WC requires at least: 6.0` / `WC tested up to: 9.8` updated
- `Plugin URI` updated to proper plugin page URL
- `@package SGCLabs_Manager` + `@copyright` added
- PHP 8.0 guard — admin notice (no fatal) if PHP < 8.0
- Build constants: `SGCLABS_BUILD_DATE`, `SGCLABS_BUILD_TIME`, `SGCLABS_BUILD_ID` added
- GPL license preamble added to main file header

### New: Export SEO Engine (`includes/export-seo-engine.php` — 550+ lines)

**1. AggregateRating Schema** — WC product reviews → JSON-LD AggregateRating
- Auto-injects on every product page when reviews exist
- Includes brand, manufacturer, countryOfOrigin (India), HS code, tech specs
- Google SERP: ⭐⭐⭐⭐⭐ star rating visible in search results → +15-30% CTR
- VideoObject schema if `_sgclabs_product_video` meta is set
- FAQPage schema auto-injects if AI FAQs exist for that product

**2. AI FAQ Bulk Generator**
- AJAX: `sgclabs_generate_product_faq` — single product, uses DeepSeek AI
- AJAX: `sgclabs_bulk_faq_generate` — batch mode, 3-5 per API call, 0.5s rate limit
- Saves to `_sgclabs_ai_faq` post meta (JSON)
- FAQPage schema auto-injects from saved FAQs → Google "People Also Ask" box
- WooCommerce product editor: "AI FAQ + SEO Score" meta box with one-click generate

**3. Country Landing Page Shortcode**
- `[sgclabs_export_page country="Germany"]`
- 10 countries pre-configured: Germany, USA, UAE, UK, Nigeria, Kenya, Saudi Arabia, Australia, Canada, Brazil
- Per-country: incoterm, port, import duty note, buyer types, currency, flag
- Auto-injects WebPage schema.org for that country
- Hero section, info cards, "Why SGC LABS" grid, CTA with WhatsApp/email
- Add to any WordPress page — instant SEO-optimized export landing page

**4. Webmaster Verification Meta Tags**
- Admin page: SGC SEO Hub → Webmaster Verify
- Baidu (ziyuan.baidu.com) — Chinese market
- Bing Webmaster Tools (msvalidate.01)
- Yandex Webmaster (Russian/CIS buyers)
- Pinterest (p:domain_verify)
- Norton Safe Web (enterprise trust badge)
- Alexa / Amazon

**5. Bulk SEO Health Dashboard** (`includes/seo-health-dashboard.php`)
- Admin page: SGC SEO Hub → SEO Health
- Scans all products in batches of 50 — score /100 per product
- 10 checks: SEO title, meta desc, focus keyword, image alt, FAQ schema, reviews, content length, HS code, tech specs, schema description
- Priority queue: Critical (<40), Needs Work (40-69), Good (70+)
- Filter by priority — "Fix All Critical" button auto-generates FAQs for lowest-scoring

**6. Export-Optimized Open Graph** (fallback when Yoast/RankMath not active)
- HS code + incoterm added to og:description
- `product:availability`, `product:price:amount`, `product:price:currency`
- Twitter card summary_large_image

**7. Google Merchant Center Feed**
- URL: `/sgclabs-gmc-feed.xml`
- RSS format with Google Base namespace
- Fields: id, title, description, link, image, price, brand, mpn, condition, target_country, HS code, MOQ
- Limit configurable via `sgclabs_gmc_feed_limit` option (default 500)

---

- **CRITICAL FIX:** `sgclabs_inquiries` table now created in `sgclabs_activate()` — was ONLY created on form submit before. "Fix Missing Items" button now actually works
- **CRITICAL FIX:** `sgclabs_kw_rankings` table now created in `sgclabs_activate()` — was listed in health check but never existed anywhere in the plugin
- **FIX:** `sgclabs_inquiry_received` action hook was fired by `SGCLabs_Inquiry_Store::add()` but **nobody listened** — now active with 3 listeners (CRM sync, follow-up scheduler, automation log)
- **FIX:** B2B Quote Cart (`sgclabs_b2b_quotes`) was completely isolated — never linked to main `sgclabs_inquiries` table. Now mirrors every B2B quote into unified pipeline

### New Features
- **NEW:** `includes/inquiry-automation.php` — Full Inquiry Automation Module
  - Listens to `sgclabs_inquiry_received` from all 3 sources (Get-a-Quote, Inquiry shortcode, B2B Quote)
  - **CRM auto-sync**: When SGC Smart CRM Pro is active, new inquiry email → contact auto-created (deduped by email)
  - **3-day follow-up email**: Auto-scheduled via `wp_schedule_single_event` per inquiry — fires if status not won/lost/pi_sent
  - **Follow-up tracking**: `follow_up_sent` + `follow_up_at` columns added to `sgclabs_inquiries` (via safe ALTER)
  - **Manual trigger**: Admin AJAX `sgclabs_manual_followup` for instant send
  - **Custom template**: Settings page for subject, body, from name/email, delay days
  - **Follow-up log**: Last 200 sends visible in admin (SGC Manager Pro → Inq. Automation)
- **NEW:** Inquiry pipeline status upgrade — `new/read` → `new/read/quoted/pi_sent/won/lost`
  - Color-coded pill tabs with per-status counts
  - Inline dropdown status changer — no page reload needed
  - Row color coding: new=yellow, won=green
  - Follow-up sent indicator column (✉️)
  - Source type badge (quote/b2b/shortcode)
  - INQ number column added

### Internal
- `sgclabs_inquiries` schema: added `follow_up_sent`, `follow_up_at`, `source_type` columns (safe `ALTER` on `init`)
- `sgclabs_kw_rankings` schema: keyword, position, previous, url, impressions, clicks, ctr, recorded_date, source
- All version strings updated: plugin header, constant, CHANGELOG
- GitHub Actions: `git tag v7.3.1 && git push origin v7.3.1`

---

## v7.2.1 — May 4, 2026 · API Fix + Data Health

### Bug Fixes
- **CRITICAL FIX:** `sgclabs_decrypt()` → `sgclabs_get_api_key()` in `multisite-seo-differentiator.php` (6 locations)
  - Root cause: `sgclabs_decrypt()` never existed — raw `sgce2:...` encrypted string was sent to API → 401 error
  - AI Control Center worked, 5-Domain SEO failed — same key, different decrypt function
  - Now uses same `sgclabs_get_api_key()` as rest of plugin → consistent everywhere
- **FIX:** `gemini-2.0-flash` → `gemini-2.5-flash` in multisite-seo-differentiator.php (2 locations)
  - Now consistent with `ai-provider.php` — single Gemini model across entire plugin
- **FIX:** Removed `response_format: json_object` from DeepSeek calls — caused empty responses
- **FIX:** `sgclabs_msd_extract_json()` — 4-layer JSON fallback (direct parse → fence strip → regex → substr)
- **FIX:** Better AI error messages — HTTP code, API error body, actionable suggestion shown in log

### New Features
- **NEW:** `includes/data-migrator.php` — Data Health Check module
  - Auto-scans on every plugin upgrade via `sgclabs_after_upgrade` hook
  - Shows API keys status (in DB, decryptable, prefix)
  - Shows DB tables (exists, row count)
  - Shows 15 core settings inherited status
  - One-click JSON export of all `sgclabs_*` settings
  - One-click JSON import with overwrite option
  - "Fix Missing Items" button — re-runs activation to restore missing tables/defaults
  - WP Admin banner if issues found
  - Menu: SGC Manager Pro → Data Health
- **NEW:** "Test AI connection" debug button in 5-Domain SEO page
  - Shows: key in DB, decrypt result, HTTP code, raw API response, actionable fix

### Internal
- All version strings updated: plugin header, constant, module comments, CHANGELOG, GitHub workflow
- GitHub Actions: `git tag v7.2.1 && git push origin v7.2.1`

---

## v7.2.0 — May 4, 2026 (superseded by v7.2.1)
- data-migrator.php added (had decrypt bug)

## v7.1.0 — May 4, 2026 · Multi-Site SEO Pack
- 5-Domain SEO Differentiator — live fetch, AI differentiate, REST cross-site push
- Token-based auth, DeepSeek Chat+Reasoner, Gemini Flash, Claude Haiku
- RankMath bridge — zero meta conflict

## v6.9.9 — May 2, 2026
- Core Bridge, atomic doc sequences, duplicate table fixes

## v6.9.0 — April 2026
- Module Registry, Security Audit, GitHub auto-updater

---
*SGC LABS® Manager Pro — Ambala India · Powered by Claude · Anthropic*
