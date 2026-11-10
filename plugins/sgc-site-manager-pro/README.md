# SGC LABS® Site Manager Pro

> **Version 6.9.0 — International Edition**
> Complete SEO + AI + WooCommerce manager. RankMath Pro replacement for B2B export stores.

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-96588a)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/License-GPLv2%2B-green)](LICENSE.txt)

---

## What's in 6.9.0

The **International Edition** is a fully **additive** release — no existing files were modified, only new infrastructure was layered on top.

### New core infrastructure

| Module | Class | Purpose |
| ------ | ----- | ------- |
| Module Registry | `SGCLabs_Registry` | Categorised, alphabetical, per-module toggle, health-check |
| Built-in Security Audit | `SGCLabs_Security` | In-house code scanner (no third-party dependency) |
| Auto Sign-Stamp Updater | `SGCLabs_Updater` | SHA-256 manifest, version stamp, integrity verifier |

### New international modules

| Slug | What it does |
| ---- | ------------ |
| `gdpr_consent` | Granular cookie banner + 180-day consent log |
| `multi_currency` | 8-currency display switcher (INR/USD/EUR/GBP/AED/SAR/JPY/AUD) |
| `wishlist` | Guest-cookie + user-meta wishlist, cap 100 items |
| `recently_viewed` | Cookie-based, 12 items, 14 days, auto-rendered |
| `product_compare` | Up to 4 products, side-by-side spec table |
| `quick_view` | AJAX product modal from shop loop |
| `rest_api` | `sgclabs/v1` namespace — products, search, quote, inquiry, health |
| `login_hardening` | 5/15-min throttle, 30-min lockout, XML-RPC off, bot blocker |

### New admin pages

- **📚 Module Browser** — search, sort, accordion-by-category, function index, bulk enable/disable.
- **🌍 International** — overview / currency rates / GDPR consent log / REST API reference (tabbed).
- **🛡️ Security Audit** — built-in scanner with severity cards, environment table, findings list, integrity check.

---

## Folder layout

```
sgclabs-manager-pro/
├── sgclabs-manager.php          # Main loader (plugin entry)
├── VERSION                      # Single source of truth for version
├── release.manifest.json        # Auto-generated SHA-256 manifest (after activation)
├── composer.json                # PSR-4 autoload + dev tooling
├── phpcs.xml.dist               # WordPress Coding Standards
├── CHANGELOG.md                 # Keep-a-Changelog format
├── SECURITY.md                  # Responsible disclosure policy
├── CONTRIBUTING.md              # Workflow + architecture rules
├── CODE_OF_CONDUCT.md           # Contributor Covenant 2.1
├── .editorconfig                # Cross-editor style consistency
├── .gitignore
├── .github/
│   └── workflows/
│       ├── ci.yml               # PHP lint + WPCS + security on every push
│       └── release.yml          # Auto-build ZIP on tag push
├── bin/
│   ├── build-zip.sh             # Reproducible release ZIP builder
│   └── version-stamp.php        # Auto-increment version + manifest
├── admin/                       # (existing) admin menu router & pages
├── includes/
│   ├── core/                    # NEW v6.9.0 — registry, security, updater, browser
│   ├── international/           # NEW v6.9.0 — 8 international modules
│   ├── feature-control.php      # (existing)
│   └── ...                      # (existing 70+ modules)
├── assets/                      # CSS, JS, images
├── public/                      # Front-end facing assets
├── templates/                   # Shortcode + page templates
├── languages/                   # i18n
└── docs/                        # Internal documentation
```

---

## Installation

1. Download the latest ZIP: `sgclabs-manager-pro-v6.9.0-international.zip`.
2. WP Admin → **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Activate**.
4. Visit **SGC LABS → Module Browser** to enable / disable individual modules.

The plugin folder must be installed as `wp-content/plugins/sgclabs-manager-pro/` (no nested duplicates).

---

## Compatibility

| Stack | Tested |
| ----- | ------ |
| PHP | 8.0, 8.1, 8.2, 8.3 |
| WordPress | 6.0 – 6.7 |
| WooCommerce | 7.x – 9.x |
| MySQL | 5.7 (Bluehost shared) and 8.0 |

---

## Development

```bash
composer install
composer phpcs        # Lint
composer phpcbf       # Auto-fix
composer build        # Build release ZIP
composer stamp        # Regenerate version + manifest
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## License

GPL-2.0-or-later. See [LICENSE.txt](LICENSE.txt).

© SGC LABS®, Ambala, India.
