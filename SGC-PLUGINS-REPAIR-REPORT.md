# SGC LABS® plugin repair — 2026-11-10

Four plugins were repaired, tested together on a real WordPress + WooCommerce install, and packaged.
Installable zips are in `dist/`, sources in `plugins/`.

| Plugin | Folder | Version | Zip |
|---|---|---|---|
| SGC Site Manager Pro | `sgc-site-manager-pro` | 7.5.0 → **7.5.2** | `dist/sgc-site-manager-pro-v7.5.2.zip` |
| SGC Product Manager Pro | `sgc-product-manager-pro` | 3.0.8 → **3.1.0** | `dist/sgc-product-manager-pro-v3.1.0.zip` |
| SGC Invoice & Packlist Pro | `sgc-invoice-packlist-pro` | 1.9.2 → **1.10.0** | `dist/sgc-invoice-packlist-pro-v1.10.0.zip` |
| SGC Smart CRM Pro | `sgc-smart-crm` | 1.1.9 → **1.3.0** | `dist/sgc-smart-crm-v1.3.0.zip` |

Install order: Site Manager → Product Manager → Invoice & Packlist → Smart CRM. Keep the folder names above
(the auto-updater and the cross-plugin checks look for these exact slugs).

## Not shipped: SGC Business Suite Pro 2.5.0

It is a half-finished merge of the other three plugins (dated April 2026, older than all of them):

- 7 classes (`SGC_PM_Automation`, `SGC_PM_Bulk_Actions`, …) and 20 `sgcps_*` functions are copied verbatim from
  Product Manager Pro, so activating both plugins is a guaranteed PHP fatal ("Cannot redeclare").
- Stubs only: analytics, Certificate of Origin, automation sequences are empty classes; the PDF engine
  outputs raw HTML; `SGC_BS_DB::domestic_quotations()` / `export_quotations()` are called but do not exist.
- Everything it does that works (GST invoice, PI, packlist, CRM, product tools) exists in newer form in the
  four shipped plugins. Keeping it would only duplicate tables, menus and bugs.

## Fatal errors fixed (verified by running the code)

| Plugin | Problem | Fix |
|---|---|---|
| Invoice | Smart Quote Templates called `get_payment_terms()` / `get_bank_details()` that did not exist → fatal on render | Implemented both (export/domestic bank block, USD threshold terms) |
| Invoice | Buyer Portal and pipeline reminder called a **private** `SGC_IIP_Email::wrap()` → fatal | Made public |
| Invoice | PI editor product search passed `WP_Post` objects into the country-pricing engine (typed `int`) → fatal | Query returns IDs |
| Invoice | Saving a PI with an unknown id → "array offset on null" | Guard + clear error |
| Invoice | Compose tab JavaScript broken by `'Sir/Ma'am'` (unescaped quote) → whole tab's JS dead | Escaped (2 places) |
| Invoice | AI inquiry classifier listener was registered *after* the event fired → never ran | Registered at boot |
| Product Mgr | Competitors "Scan All" button: handler registered but method never written → fatal | Implemented (queues background scan) |
| Product Mgr | Header switches AI / Auto / Intel posted to an AJAX action that had no PHP handler → always "Error" | Handler added (whitelisted options) |
| Site Manager | `sgclabs_ai_call_provider()` does not exist → AI image ALT generator fatal | Uses `sgclabs_ai_call()` |
| Site Manager | FAQ generator passed `'deepseek'` as `$max_tokens` (typed `int`) → fatal | Correct argument order |
| Site Manager | Duplicate `let gkwOffset` in Global Keywords page script → SyntaxError, page JS dead | Removed |
| Site Manager | Undefined `$api_key_fields` in Extras save | Defined |
| Site Manager | Wishlist / Studio bulk handlers crashed when `ids` arrived as an array | Accept array or JSON |
| CRM | `class-crm-integration.php` was never loaded → Invoice Pro → CRM contact sync and the Changelog page were dead code | Loaded |

## Integration chain (Site Manager → Invoice Pro → CRM) — was broken, now verified end to end

One public inquiry now produces exactly: 1 Site Manager inquiry, 1 Invoice Pro inquiry (country code,
currency, health score, auto-reply), 1 CRM contact, 1 scheduled follow-up. Before:

- Every form submit fired `sgclabs_inquiry_received` **twice** (handler + inquiry store) → duplicate rows everywhere.
- Invoice Pro's listener inserted with wrong column names (`message`, `source`) → silently failed on MySQL.
- Invoice Pro intercepted Site Manager's form AJAX at priority 1 **before** nonce / rate-limit / validation →
  anonymous POSTs created inquiries (spam vector) and valid submits were stored twice. Removed.
- Get-a-Quote form never fired the unified hook → quote leads never reached Invoice Pro / CRM. Fixed.
- CRM only *updated* existing contacts when Invoice Pro relayed an inquiry; with the full stack installed a new
  lead never became a contact. Now upserts. CRM `contacts.tags` / `notes` are NOT NULL without defaults and were
  missing from inserts (silent failure) — always supplied now.

## Performance / stability

- Site Manager's version constant contained `gmdate('YmdHi')`, so it changed **every minute** and the full
  activation routine (9 CREATE TABLE, 100+ option writes, rewrite flush) re-ran on every admin page load.
  Fixed to a stable per-release build id.
- `debug-log-protect` wrote a log line on every admin request when `.htaccess` is not writable → once per day.
- Nested `add_action` inside the activation function moved out.
- Site Manager `uninstall.php` dropped all tables (inquiries, quotes, keywords, redirects) unconditionally.
  Now only when **General Settings → Delete data on uninstall** is enabled (same behaviour as the other plugins).
- Dead duplicate `plugin-health-monitor.php` copies (PM, CRM) and the leftover `install-dompdf.sh` / empty `lib/` removed.

## SEO output on product pages (Google was receiving duplicates)

Before: 2× canonical, 4× `og:title`, 14 hreflang, 3× Product JSON-LD per product page.
After: 1 canonical, 1 `og:title`, 1 description, one merged Product schema (specs + related products folded in),
one BreadcrumbList. New module `includes/core/head-dedupe.php` (Site Manager) enforces this; Product Manager's
Export SEO head output is disabled while Site Manager is active (it only adds breadcrumb schema + image ALT).

## Compatibility (requested): WooCommerce, Rank Math Pro, LiteSpeed Cache, Elementor, LabTechCo theme

Tested in the sandbox with WooCommerce 11.2, Rank Math 1.0.282 and LiteSpeed Cache active together with all four plugins.

- **WooCommerce**: HPOS + Cart/Checkout Blocks compatibility now declared by all four plugins (Invoice Pro was
  missing it and would be listed as "incompatible" with High-Performance Order Storage). No direct `shop_order`
  post queries. WooCommerce's own Product JSON-LD is suppressed only when Site Manager emits one.
- **Rank Math (Pro or free)**: when Rank Math is active Site Manager stands down on title, meta description,
  canonical, Open Graph, Product/rating schema, multilingual meta, homepage title, sitemaps and robots.txt
  (Rank Math's `sitemap_index.xml`, `product-sitemap.xml`, `robots.txt` verified to be served by Rank Math).
  Site Manager keeps only what Rank Math lacks: hreflang, and Lab Tech Specs are injected *into* Rank Math's
  Product entity through the `rank_math/json_ld` filter (no second Product). Product Manager's breadcrumb
  schema is also skipped. Previously Site Manager's always-on `robots.txt` rewrite rule broke Rank Math/WordPress
  robots.txt (redirected to `/robots.txt/`).
- **LiteSpeed Cache**: Site Manager's PHP gzip, Cache-Control headers and transient page cache are disabled
  automatically when LiteSpeed (or WP Rocket / W3TC / WP Super Cache / Autoptimize / SG Optimizer) is active.
  The currency / wishlist / compare / recently-viewed cookies are registered as LiteSpeed vary cookies
  (`litespeed_vary_cookies` + `litespeed_vary_curr_cookies`) so cached pages are not served with another
  visitor's currency or wishlist bar. Existing LiteSpeed purge hooks kept.
- **Elementor**: script defer already excludes `elementor-frontend`, `elementor-pro-frontend`, swiper handles;
  lazy-loading is disabled on front/home/pages so Elementor sliders are untouched; the internal-link regex skips
  Elementor pages. (Elementor's GitHub checkout has no built assets, so it could not be activated in the sandbox;
  this was verified by code review only.)
- **LabTechCo theme**: theme-specific CSS fixes are kept behind the existing Site Fixes toggles. Product Manager's
  gallery override (big image + thumbnail strip + lightbox, which fights a theme's own Swiper gallery) is now a
  header switch **Gallery** (default ON = unchanged behaviour; turn OFF if the theme gallery should win).

## Module Control + automatic safe mode (new, all four plugins)

`sgc-module-guard.php` ships inside every plugin (first copy loaded wins). **SGC LABS → Module Control** lists every
module of all four plugins (119 switches) with an ON/OFF toggle:

- A module switched OFF is never `require`d by PHP, so a faulty module can be isolated without FTP.
- **Automatic safe mode**: when PHP dies with a fatal error inside a module file, that module is switched off
  automatically (the fatal is logged with file/line/message, an admin notice explains it, the next request loads
  without it). Verified in the sandbox: request 1 → 500, request 2 → 200 with the module listed as auto-disabled.
- **Safe Mode** button switches every optional module of every plugin off (and "Exit Safe Mode" restores the previous
  switches); **Enable all** resets everything.
- **Emergency link** (one-time secret URL shown on the page) switches all optional modules off even when wp-admin
  itself is broken, then redirects to Module Control.
- Core modules (database layer, admin UI, inquiries core, AI provider, Auto-Repair, Health Monitor …) are locked and
  always load.

## One-click self repair

Site Manager already ships two tools, both verified working after the repair:

- **SGC LABS → Auto-Repair** (`admin.php?page=sgclabs-auto-repair`): recreates missing tables for all four plugins,
  reschedules lost cron jobs, restores missing options, checks third-party plugins (LiteSpeed, Rank Math …),
  runs daily and after activation. The "Run Auto-Repair now" button works.
- **SGC LABS → Health Monitor** (`admin.php?page=sgclabs-health-monitor`): scans files, tables and PHP/WP/WC
  compatibility for all four plugins (weekly + after WordPress/WooCommerce updates). Fixed a false "function
  conflict" alarm on hosts that symlink the plugins directory.

## How it was tested

- `php -l` on every file; PHPStan (level 2, WordPress + WooCommerce stubs) — clean.
- Real WordPress 7.2 + WooCommerce 11.2 install (SQLite): activation of all plugins, **126 admin pages/tabs**
  crawled, **397 admin-ajax actions** called with valid nonces (two passes), all public (`nopriv`) endpoints,
  every cron hook fired, frontend pages (home, product, category, shop, cart, account, sitemaps, REST) —
  no PHP fatal/warning/notice left in the debug log. Repeated with Rank Math + LiteSpeed Cache active.
- Inline JavaScript blocks extracted from every PHP file and syntax-checked with Node.
