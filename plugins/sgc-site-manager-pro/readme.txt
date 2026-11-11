=== SGC LABS® Site Manager Pro ===
Contributors: sgclabs
Donate link: https://labsglass.com
Tags: seo, woocommerce, schema, ai-seo, shipping, focus-keywords, rankmath, claude-ai, performance
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 7.5.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete SEO & Site Manager — RankMath Pro Replacement with Multi-AI (Claude/OpenAI/Gemini/DeepSeek), 50+ modules, WooCommerce SEO, and full site management.

== Description ==

SGC LABS® Site Manager Pro is an all-in-one WordPress plugin for SEO management,
WooCommerce optimization, AI content generation, and site administration.

**Key Features:**

* Focus Keyword + SEO Score + SERP Preview
* 15+ Schema Types (Product, FAQ, Breadcrumb, Article, etc.)
* Instant Indexing (IndexNow API)
* Multi-AI Auto SEO (Claude, DeepSeek, Gemini, OpenAI)
* Multilingual SEO (11 languages)
* Keyword Manager + Google Compliance
* Core Web Vitals Optimization
* Bulk SEO Editor + Role Manager
* WooCommerce SEO (Brand/GTIN/MPN)
* Redirections + Regex Redirects + Auto Slug Redirect
* Social Preview (Facebook/Twitter)
* Readability Analysis + Breadcrumbs
* Automatic Shipping Rules (OOP)
* AI Focus Keywords from Slug + Canonical URL
* Site Crawler + Broken Link Scanner
* B2B Quote Engine + AI Auto-Reply
* Get-a-Quote + Inquiry Form
* Header & Footer Editor + WhatsApp Button
* Announcement Bar
* Popup + Currency Switcher + AI Chatbot
* Image SEO + Alt Text Optimizer
* Internal Links + Redirections Manager
* Google Search Console Connect
* Rank Tracker
* And 30+ more modules...

== Installation ==

1. Upload `sgclabs-manager-pro` folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu in WordPress
3. Go to **SGC LABS® Pro** in admin sidebar to configure
4. Add your AI API key in **AI Control Center** for AI features

== Frequently Asked Questions ==

= Does this replace RankMath? =
Yes. It provides full RankMath parity plus additional AI-powered features including multi-provider AI SEO generation.

= Which AI providers are supported? =
Claude (Anthropic), DeepSeek, Gemini (Google), and OpenAI. All configurable in AI Control Center.

= Does it work with WooCommerce? =
Yes. Full WooCommerce support including product schema, image SEO, shipping rules, B2B quotes, and local SEO.

= Is it compatible with other SEO plugins? =
Yes. Compatible mode automatically activates when RankMath, Yoast SEO, AIOSEO, or SEOPress are detected. No duplicate meta tags.

= What PHP version is required? =
PHP 7.4 or higher. PHP 8.0+ recommended for best performance.

== Changelog ==

= 6.6.2 =
* FIXED: Fatal PHP parse error in related-product-links.php
* FIXED: AES-256-CBC now uses random IV per encryption (security hardening)
* FIXED: 33 unescaped nonce outputs — all wrapped with esc_js()
* FIXED: ABSPATH guard added to 3 class files
* FIXED: Tech Specs menu callback mismatch (white screen fix)
* FIXED: Missing menu entries for Auto SEO and Claude SEO pages
* FIXED: Duplicate AJAX handler registrations (ai-seo-features vs class version)
* FIXED: Missing wp_ajax_ handler for sgclabs_svl_save and sgclabs_save_extras
* FIXED: JS $.ajax global override replaced with safe ajaxPrefilter/ajaxComplete
* ADDED: Toggle state DB persistence in SEO Hub and Content Rewriter
* ADDED: load_plugin_textdomain for i18n support
* ADDED: Responsive CSS breakpoints (1024px / 768px / 480px) in admin and control CSS
* ADDED: License, Domain Path fields to plugin header
* IMPROVED: Deactivation hook converted from anonymous to named function
* IMPROVED: Version strings unified to 6.6.2 across all files
* APPLIED: WordPress Professional Standards (PHPDoc, Security, Input Validation, Escaping)

= 6.6.0 =
* NEW: Automatic Shipping Rules module (OOP)
* NEW: AI Focus Keywords from Slug + Canonical URL (OOP)
* NEW: Edit & Preview for generated keywords with SERP preview
* NEW: Professional folder structure (public/, templates/, languages/)
* NEW: Base module class for OOP architecture
* NEW: uninstall.php for proper cleanup
* IMPROVED: Existing modules gradually converting to OOP

= 6.5.4 =
* Performance module improvements
* SEO Hub enhancements
* Bug fixes

= 6.5.2 =
* JS-driven Control System introduced
* Security hardening: nonce, capability checks
* Activity monitor for AJAX operations

== Upgrade Notice ==

= 6.6.2 =
Security release. Fixes encryption vulnerability (fixed IV), 33 XSS issues, and multiple fatal errors. Upgrade immediately.

= 6.6.0 =
Major architecture upgrade. All new features built with OOP. Backward compatible.
