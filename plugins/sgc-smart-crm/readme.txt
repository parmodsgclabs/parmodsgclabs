=== SGC Smart CRM Pro ===
Contributors:       sgclabs
Tags:               crm, email marketing, woocommerce, automation, ai
Requires at least:  5.8
Tested up to:       6.7
Requires PHP:       7.4
Stable tag:         1.3.0
License:            GPLv2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

AI-Powered B2B CRM — Contact management, email campaigns, WooCommerce automation, and weekly product promos for SGC LABS®.

== Description ==

SGC Smart CRM Pro is a complete AI-powered CRM and email automation plugin built for B2B scientific glassware businesses. It integrates deeply with WooCommerce and SGC LABS® Manager Pro.

= Key Features =

* **Contact Management** — CSV/Excel import, manual add, AI auto-segmentation
* **AI Campaigns** — Newsletter, product promo, announcement generation via Claude / OpenAI / Gemini / DeepSeek
* **Email Automation** — WooCommerce order follow-ups (Day 3, 14, 30), inquiry follow-ups (Day 2, 7), welcome sequences
* **Weekly Promo** — AI-generated product promo broadcasts every Monday & Thursday
* **Open & Click Tracking** — Track email engagement with pixel-based tracking
* **Unsubscribe Management** — One-click unsubscribe compliance
* **Product Promo Builder** — Select WooCommerce products → AI builds campaign

= AI Providers Supported =

* Claude (claude-haiku-4-5-20251001)
* OpenAI (gpt-4o)
* Google Gemini (gemini-2.5-flash)
* DeepSeek (deepseek-chat, deepseek-v3)

= Requirements =

* WordPress 5.8+
* PHP 7.4+
* WooCommerce 5.0+ (recommended)
* SGC LABS® Manager Pro (optional — used as AI fallback)

== Installation ==

1. Upload the `sgc-smart-crm` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **SGC LABS® → 📧 Smart CRM**
4. Go to **Settings → AI Provider** and add your API key
5. Import contacts or sync WooCommerce customers

== Frequently Asked Questions ==

= Do I need SGC LABS® Manager Pro? =
No. The CRM has its own AI provider settings. SGC Manager Pro is used as a fallback only if no CRM API key is configured.

= Which email provider is used? =
The plugin uses WordPress `wp_mail()` which works with any SMTP plugin (WP Mail SMTP, FluentSMTP, Postmark, etc.).

= Is contact data stored locally? =
Yes. All contact and campaign data is stored in your WordPress database. No external data storage.

= How do I comply with GDPR? =
The plugin includes one-click unsubscribe links in all emails and marks contacts as unsubscribed in the database. Use the export feature to fulfill data access requests.

== Changelog ==

= 1.0.5 =
* NEW: Data safety — plugin uninstall no longer deletes contacts/campaigns by default
* NEW: Settings → Danger Zone — explicit toggle to enable data wipe on uninstall
* NEW: Auto-backup before any data wipe — CSV saved to /wp-content/uploads/sgc-crm-backups/
* NEW: Weekly auto-backup cron — runs every Monday 2 AM, keeps last 8 backups (auto-rotate)
* NEW: Settings → Backup Status panel — shows last backup time, contact count, next run
* NEW: "Run Backup Now" button in Settings for instant manual backup
* NEW: sgc_crm_delete_data_on_uninstall option (default OFF) added to options whitelist
* FIX: Weekly cron schedule now uses proper 'weekly' interval (604800s)

= 1.0.4 =
* NEW: Bulk selection checkboxes in Contacts table (select individual or all on page)
* NEW: Bulk Delete — delete multiple contacts with one click + confirmation
* NEW: Bulk Export — export only selected contacts as CSV (with AI Score column)
* NEW: Bulk Assign Segment — reassign segment/audience for multiple contacts at once
* NEW: "Add Contact" button added to Contacts toolbar
* NEW: AI Score column now visible in Contacts table (color-coded: green ≥70, orange ≥40, red <40)
* NEW: Assign Segment modal with full SGC audience list including Warm Lead & New Lead
* FIX: Edit modal now correctly sets email field as readonly when editing existing contact
* IMPROVED: Selected rows highlighted with blue tint for clear visual feedback
* IMPROVED: Bulk action bar auto-shows/hides based on selection count

= 1.0.3 =
* Google Contacts CSV import (auto-detect Google CSV column format)
* Background import with live progress polling
* Import cancel support
* Improved column auto-detection for email, name, phone fields

= 1.0.0 =
* Initial release
* AI campaign generation (Claude, OpenAI, Gemini, DeepSeek)
* CSV and Excel contact import
* WooCommerce customer sync
* SGC LABS® inquiry form integration
* Order and inquiry follow-up automation
* Weekly AI product promo broadcasts
* Open and click tracking
* Unsubscribe management

== Upgrade Notice ==

= 1.0.5 =
* NEW: Data safety — plugin uninstall no longer deletes contacts/campaigns by default
* NEW: Settings → Danger Zone — explicit toggle to enable data wipe on uninstall
* NEW: Auto-backup before any data wipe — CSV saved to /wp-content/uploads/sgc-crm-backups/
* NEW: Weekly auto-backup cron — runs every Monday 2 AM, keeps last 8 backups (auto-rotate)
* NEW: Settings → Backup Status panel — shows last backup time, contact count, next run
* NEW: "Run Backup Now" button in Settings for instant manual backup
* NEW: sgc_crm_delete_data_on_uninstall option (default OFF) added to options whitelist
* FIX: Weekly cron schedule now uses proper 'weekly' interval (604800s)

= 1.0.4 =
Drop-in upgrade. No database changes. Simply deactivate v1.0.3, upload and activate v1.0.4.

