## 2025-04-15 - [Hardcoded Secret in TTS Endpoint]
**Vulnerability:** A hardcoded Google TTS API key (`GOOGLE_API_KEY`) was present directly in the `api/speak.php` file.
**Learning:** Hardcoding sensitive API keys in the source code leaks the credential to anyone who views the codebase and requires manual code changes to cycle keys.
**Prevention:** Always externalize secrets to a configuration file (`config.json` or environment variables), and explicitly exclude the secrets when rendering or exporting that configuration through API endpoints.

## 2026-04-16 - [XSS in Burn Permit Display via Unsanitized Email Content]
**Vulnerability:** Burn permit details derived from unauthenticated, parsed emails were rendered directly into the DOM using `.innerHTML` without HTML escaping in `index.php` and `current_index.php`.
**Learning:** Input from external sources (like piped emails from cPanel) cannot be implicitly trusted and must always be sanitized before display, especially when inserted into the DOM via `.innerHTML`.
**Prevention:** Added a JavaScript `escapeHtml()` utility function to explicitly sanitize variables such as `address`, `type`, and `details` before injecting them into HTML templates.
