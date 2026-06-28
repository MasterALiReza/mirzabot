# OxBot — Complete Remediation Plan

> Generated from full codebase audit. All findings are real, severity-graded, and organized into actionable phases.

---

## Phase 1 — Critical (Immediate Security & Stability)

| # | Task | File(s) | Why |
|---|------|---------|-----|
| 1.1 | Add centralized auth middleware to all API endpoints | All `api/*.php` | Most endpoints have zero auth; anyone who finds the path can read/write data |
| 1.2 | Replace all SQL string interpolation with prepared statements | `api/users.php`, `api/invoice.php`, `api/product.php`, `api/discount.php`, `api/category.php`, `function.php:textbotlang`, `install.php` | Direct SQL injection in every user-supplied parameter |
| 1.3 | Fix undefined variable `$data` in log/stat endpoints | `api/log.php:54`, `api/statbot.php:29` | Causes runtime warnings and silent failures |
| 1.4 | Fix undefined variable `$new_marzban` in product & panel routes | `api/product.php`, `api/panels.php` | Breaks panel creation and product assignment |
| 1.5 | Secure `install.php` against re-execution and SQLi | `install.php` | Writes admin password hash via string interpolation; no installed-lock check |
| 1.6 | Remove hardcoded bot token from `botconfig.php` | `botconfig.php` | Token visible in plaintext; move to env var |
| 1.7 | Remove hardcoded `$APIKEY` dual-use (bot token + API key) | `botapi.php`, `config.php` | Same variable used for both Telegram bot auth and internal API auth |
| 1.8 | Restrict access to `banner_generator.php` | `api/banner_generator.php` | Unauthenticated; can be used to generate arbitrary images consuming server resources |
| 1.9 | Restrict access to `verify.php` and `keyboard.php` | `api/verify.php`, `api/keyboard.php` | Unauthenticated public endpoints; verify could leak transaction data |
| 1.10 | Fix variable typo `$update` vs `$updates` | `bot.php` | Silent logic failure where expected array is undefined |

---

## Phase 2 — High (API Hardening & Data Integrity)

| # | Task | File(s) | Why |
|---|------|---------|-----|
| 2.1 | Standardize DB driver — pick PDO or mysqli, drop the other | All files | Mixed usage is confusing and risks inconsistent escaping |
| 2.2 | Add input validation & type coercion on all `$_GET`/`$_POST` reads | All `api/*.php` | No sanitization anywhere; integers expected but strings accepted |
| 2.3 | Sanitize `textbotlang` parameter in `function.php` | `function.php` | Direct interpolation into SQL `LIKE` clause |
| 2.4 | Add CSRF protection or origin checks on mutation endpoints | `api/payment.php`, `api/invoice.php`, `api/discount.php` | No way to verify request origin |
| 2.5 | Replace `hash.txt` file-based token with DB or env-based auth | `manage/index.php`, `api/users.php` | File-based token is fragile and unencrypted |
| 2.6 | Audit `manage/index.php` for auth bypass and XSS | `manage/index.php` | 9,477 lines; likely contains additional vulnerabilities |
| 2.7 | Fix file path traversal risk in `miniapp.php` | `api/miniapp.php` | User-supplied path component without validation |

---

## Phase 3 — Medium (Code Quality & Maintainability)

| # | Task | File(s) | Why |
|---|------|---------|-----|
| 3.1 | Refactor `bot.php` into modular handlers | `bot.php` | Monolithic file is hard to debug, test, and extend |
| 3.2 | Extract DB credentials from `config.php` into environment variables | `config.php` | Hardcoded credentials in repo are a leak risk |
| 3.3 | Remove unused/dead code across all API files | All `api/*.php` | Dead branches confuse maintainers |
| 3.4 | Add proper error handling (try/catch or error_get_last) | All files | Silent failures on DB queries and API calls |
| 3.5 | Add logging consistency — use a single logger, not `error_log()` + echo | All files | Mixed logging makes debugging harder |
| 3.6 | Normalize response format across all API endpoints | All `api/*.php` | Some return JSON, some echo HTML, some return raw text |
| 3.7 | Audit and fix undefined index warnings | `function.php`, `bot.php`, `manage/index.php` | Multiple `$_POST`/`$_GET` reads without `??` or `isset()` |

---

## Phase 4 — Low (Best Practices & Future-Proofing)

| # | Task | File(s) | Why |
|---|------|---------|-----|
| 4.1 | Add a `robots.txt` and deny all non-public paths | Root | Prevent search indexing of admin/API paths |
| 4.2 | Add rate limiting on auth and payment endpoints | `api/payment.php`, `manage/index.php` | No brute-force protection |
| 4.3 | Add comprehensive input length/format validation | All `api/*.php` | No max-length or format checks on strings/emails/phones |
| 4.4 | Add unit/integration test scaffolding | Root (new `tests/`) | No tests exist; regression safety is zero |
| 4.5 | Add `.env.example` and migrate all secrets out of code | Root | Industry standard; enables per-environment config |
| 4.6 | Add CI pipeline (lint + SAST) | Root (new `.github/workflows/`) | Catch issues before they reach production |
| 4.7 | Review and tighten file permissions on all API files | All `api/*.php` | Ensure web server user has minimum required access |

---

## File Index

| File | Lines | Issues |
|------|-------|--------|
| `config.php` | ~30 | Hardcoded DB + API creds, `$APIKEY` dual-use |
| `botconfig.php` | ~50 | Hardcoded bot token |
| `botapi.php` | ~200 | `$APIKEY` as bot token, mixed PDO/mysqli |
| `function.php` | ~1,500 | SQLi in textbotlang, undefined indices |
| `bot.php` | ~3,000+ | Monolithic, `$update` typo, mixed DB |
| `install.php` | ~300 | SQLi, no installed-lock |
| `api/users.php` | ~200 | No auth, SQLi |
| `api/product.php` | ~200 | Undefined `$new_marzban`, no auth |
| `api/invoice.php` | ~200 | No auth, SQLi |
| `api/payment.php` | ~150 | No auth |
| `api/settings.php` | ~150 | No auth, undefined `$db` |
| `api/service.php` | ~150 | No auth |
| `api/category.php` | ~100 | No auth, undefined `$db` |
| `api/panels.php` | ~200 | Undefined `$new_marzban`, no auth |
| `api/discount.php` | ~200 | No auth, SQLi |
| `api/log.php` | ~100 | Undefined `$data` |
| `api/statbot.php` | ~100 | Undefined `$data` |
| `api/banner_generator.php` | ~100 | Unauthenticated |
| `api/miniapp.php` | ~100 | Path traversal |
| `api/verify.php` | ~100 | Unauthenticated |
| `api/keyboard.php` | ~100 | Unauthenticated |
| `manage/index.php` | 9,477 | Pending deep audit |

---

## Execution Order

1. **Phase 1** — Fix critical security holes and crashing bugs first
2. **Phase 2** — Harden APIs and ensure data integrity
3. **Phase 3** — Pay down technical debt for maintainability
4. **Phase 4** — Add infrastructure, testing, and CI

Each phase is self-contained. Deploy and verify after each phase before starting the next.
