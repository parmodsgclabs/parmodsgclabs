# SGC Invoice & Packlist Pro — Changelog

## v1.8.0 — 2026-05-21 | Build: 20260521-1800

### Bug Fix
- **CRITICAL: Smart Email Compose fixed** — `$bcc_email` undefined variable in `ajax_send_compose()` replaced with correct `$bcc_auto`. Was corrupting JSON response when WP_DEBUG on, causing "Network error" on send.

### New Features

**Country Autocomplete — Manual Inquiry Entry**
- `admin/views/tab-inquiries.php` — Country field now uses native HTML5 `<datalist>` with 195 countries
- Type "ger" → Germany, "ind" → India / Indonesia — zero extra JS/libraries

**3-Step Conveyor Flow — Proforma Invoice**
- **Step 1 — Save:** `💾 1. Save` — unchanged behaviour
- **Step 2 — Preview & Review:** `👁 2. Preview & Review` — auto-saves PI, generates PDF, opens iframe modal; "Mark as Reviewed" button inside modal footer
- **Step 3 — Send:** `✉ 3. Send to Buyer` — disabled/greyed until Step 2 reviewed; on click redirects to Compose tab with buyer email, name, subject pre-filled
- `piReviewed` state resets on every new PI load — cannot send without reviewing
- Revised PIs (Rev.2+) auto-set subject prefix "REVISED PI/..." and formal tone in compose

**Compose Tab — PI Pre-Fill (Option B)**
- `admin/views/tab-compose.php` — reads URL params `pre_pi_id`, `pre_email`, `pre_name`, `pre_subject`, `pre_revised`, `pre_pi_num`, `pre_revision`
- Shows blue pre-fill banner with PI number and "← Back to PI" link
- Revised PIs: tone auto-set to Formal, raw field pre-seeded with supersede note
- All PHP values sanitized/escaped before JS injection via `wp_json_encode()`

**PI Cancellation Email to Buyer**
- `includes/class-email.php` — new `SGC_IIP_Email::send_pi_cancellation(array $pi, array $inq, string $reason)` static method
- Sent only when PI status was `sent`, `accepted`, or `advance` (not for drafts)
- Branded SGC LABS® template, domestic/export detection, reason block shown in amber highlight
- Always BCC to company email; logs to error_log on success/failure
- `includes/class-hooks.php` — `ajax_delete_pi()` fully rewritten: accepts `reason` POST param, sends cancel email, returns `{message, email_sent}` in response
- New `ajax_pi_get()` → `sgc_iip_pi_get` AJAX — returns PI status/fields for JS status check

**Cancel PI Modal**
- `admin/views/tab-proforma.php` — new `#modal-cancel-pi` modal with branded red header, required reason textarea, validation before submit
- `deletePI()` JS function rewritten: fetches PI status first; shows cancel modal for sent/accepted PIs; simple confirm for draft/expired PIs
- Cancel confirm button runs reason validation before firing AJAX

### Technical
- Version: `1.8.0` | Build ID: `20260521-1800`
- No DB schema changes
- No new WP options

## v1.3.0 — 2026-05-08 | Build: 20260508-1200

### New Features

**Buyer Self-Service Portal** (`includes/class-buyer-portal.php` — new file, 422 lines)
- Token URL: `yoursite.com/sgc-pi-portal/?sgc_token=XXXXXX` — no login, any device
- Buyer can view PI summary (products, incoterm, payment terms, grand total)
- Accept PI — one click, admin notified, pipeline → accepted
- Reject / Request changes — with reason text, admin notified, pipeline → lost
- Upload payment proof — JPG/PNG/PDF up to 10MB, stored securely in uploads/sgc-iip-proofs/
- Payment progress bar + full payment history visible to buyer
- Dispatch tracking — AWB, courier, tracking URL, EDD shown when set
- Message to seller — buyer sends message, admin gets email
- Portal link email — `sgc_iip_send_portal_link` AJAX sends branded email to buyer
- Security: nonce every POST, token validated against DB, noindex/nofollow, .htaccess on uploads

**Balance Payment Auto-Reminder** (`includes/class-followup.php` — fully rebuilt, 172 lines)
- WP-Cron `sgc_iip_balance_reminder_check` — daily at activation
- Smart trigger: `advance` stage + dispatch date set + X days before EDD
- Auto-calculates balance (grand total − recorded payments) — skips if zero
- Portal link in reminder email — buyer can upload proof directly
- Manual trigger from admin: `sgc_iip_manual_balance_remind` AJAX
- Tracks sent: `balance_reminder_sent` + `balance_reminder_at` columns (no duplicates)
- Settings: active toggle, delay days, custom subject & body with variables {name} {pi_number} {balance} {edd}

**WordPress / International Resale Standards**
- Plugin header: `Requires at least: 6.0`, `Requires PHP: 8.0`, `Tested up to: 6.8`
- `License: GPL v2 or later` + `License URI` properly declared
- `Text Domain: sgc-iip` + `Domain Path: /languages`
- `load_plugin_textdomain()` on init — i18n ready for 20+ languages
- PHP version guard: admin notice if PHP < 8.0 (no fatal error)
- `register_deactivation_hook` — clears balance reminder cron on deactivate
- `flush_rewrite_rules()` on activate/deactivate for portal URL
- All buyer-facing strings use `__()` / `esc_html_e()` with `sgc-iip` text domain
- Build constants: `SGC_IIP_BUILD_DATE`, `SGC_IIP_BUILD_TIME`, `SGC_IIP_BUILD_ID`, `SGC_IIP_BUILD`
- `SGC_IIP_MIN_WP`, `SGC_IIP_MIN_PHP`, `SGC_IIP_SLUG`, `SGC_IIP_TEXTDOMAIN`, `SGC_IIP_ASSETS`

### DB Changes
- `sgc_iip_inquiries` table: 3 new columns added via safe ALTER on `init` (once)
  - `payment_proof_url varchar(500)` — buyer uploaded proof URL
  - `balance_reminder_sent tinyint(1)` — 1 = reminder sent
  - `balance_reminder_at datetime` — reminder sent timestamp
- Existing rows: `pi_accept_token` auto-generated for all rows without one

### Technical
- Version: `1.3.0` | Build ID: `20260508-1200`
- `includes/class-buyer-portal.php` — new file
- `includes/class-followup.php` — stub replaced with full implementation
- `includes/class-hooks.php` — 3 new AJAX actions: `sgc_iip_send_portal_link`, `sgc_iip_manual_balance_remind`, `sgc_iip_save_followup_settings`
- `sgc-iip.php` — WordPress-standard header, PHP guard, i18n, activate/deactivate hooks

---

## v1.2.5 — 2026-05-07 | Build: 20260507-1000
- Compose email: CC + BCC fields added
- Bank details: Export (SWIFT) and Domestic (IFSC/UPI) separate sections in Settings
- QR auto-generate from UPI ID and wire reference URL
- PI PDF: auto-selects bank by buyer country (India → IFSC/UPI, International → SWIFT)

## v1.2.4 — 2026-05-06
- All Documents tab: One-Click Full Sync (Manager Pro + WooCommerce → IIP)
- Sync progress bar + live log

## v1.2.3 — 2026-05-06
- Fix: Array to string error in ajax_map loop (external class callbacks)

## v1.2.2 — 2026-05-05
- All Documents tab: global list of Inquiries, PIs, Packlists with CSV export

## v1.2.1 — 2026-05-05
- CRM Won-sync: sgc_iip_deal_won hook — contact upsert, LTV, segment, sequences

## v1.2.0 — 2026-05-04
- Payment register table, full Kanban drag-drop pipeline, payment modal

## v1.1.5 — 2026-04-23
- Initial: PI creation, Packing List, Pipeline, Email compose, WhatsApp, Settings
