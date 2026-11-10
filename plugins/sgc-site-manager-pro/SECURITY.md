# Security Policy

## Supported Versions

| Version  | Supported          |
| -------- | ------------------ |
| 6.9.x    | ✅ Active support  |
| 6.8.x    | ✅ Security only   |
| 6.6.x    | ⚠️ Critical only   |
| < 6.6    | ❌ Unsupported     |

## Reporting a Vulnerability

If you believe you have discovered a security vulnerability in **SGC LABS® Site Manager Pro**, please report it responsibly.

### How to report

- **Email:** security@labsglass.com
- **Subject line:** `[SECURITY] SGC LABS Manager Pro — <short summary>`
- Include:
  - Affected version (run **WP Admin → SGC LABS → Security Audit** to see the signature)
  - Steps to reproduce
  - Proof-of-concept (if available)
  - Your assessment of severity (CVSS optional but appreciated)

### What you can expect

| Stage              | Target time   |
| ------------------ | ------------- |
| Acknowledgement    | Within 48 hrs |
| Initial assessment | Within 7 days |
| Fix or mitigation  | 30–90 days, depending on severity |
| Public disclosure  | After patch release + coordinated window |

We will credit you in the `CHANGELOG.md` and the release notes unless you prefer to remain anonymous.

## Built-in Security Tooling

The plugin ships with a **built-in Security Audit** at **WP Admin → SGC LABS → Security Audit** that scans the codebase for:

- Unsanitized superglobals (`$_GET` / `$_POST` / `$_REQUEST` / `$_COOKIE`)
- Dangerous calls (`eval`, `system`, `passthru`, `shell_exec`, `exec`, `proc_open`)
- Unprepared `$wpdb` queries
- AJAX handlers without nonce verification
- Dynamic `base64_decode` patterns

You can also verify integrity of the installed files against the shipped manifest via `SGCLabs_Updater::verify_integrity()`.

## Hardening Defaults Shipped in 6.9.0

- All AJAX handlers gated by `check_ajax_referer()` + `current_user_can()`.
- All REST endpoints sanitize inputs and apply rate limiting (30/min/IP).
- Cookies set with `SameSite=Lax` + `Secure` (when SSL active).
- Login throttle: 5 fails / 15 min → 30 min lockout.
- XML-RPC disabled by default.
- Bot user-agents blocked at login screen.

## Out of Scope

- Vulnerabilities requiring physical access to the server.
- Vulnerabilities in third-party hosting (e.g. Bluehost shared hosting limitations).
- Theoretical attacks without a proof-of-concept.
- Reports based on outdated automated scanners that flag false positives without manual verification.

Thank you for helping keep the SGC LABS® ecosystem secure.
