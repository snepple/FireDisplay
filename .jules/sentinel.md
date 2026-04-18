## 2025-04-15 - [Hardcoded Secret in TTS Endpoint]
**Vulnerability:** A hardcoded Google TTS API key (`GOOGLE_API_KEY`) was present directly in the `api/speak.php` file.
**Learning:** Hardcoding sensitive API keys in the source code leaks the credential to anyone who views the codebase and requires manual code changes to cycle keys.
**Prevention:** Always externalize secrets to a configuration file (`config.json` or environment variables), and explicitly exclude the secrets when rendering or exporting that configuration through API endpoints.

## 2026-04-16 - [XSS in Burn Permit Display via Unsanitized Email Content]
**Vulnerability:** Burn permit details derived from unauthenticated, parsed emails were rendered directly into the DOM using `.innerHTML` without HTML escaping in `index.php` and `current_index.php`.
**Learning:** Input from external sources (like piped emails from cPanel) cannot be implicitly trusted and must always be sanitized before display, especially when inserted into the DOM via `.innerHTML`.
**Prevention:** Added a JavaScript `escapeHtml()` utility function to explicitly sanitize variables such as `address`, `type`, and `details` before injecting them into HTML templates.

## 2026-04-17 - [DOM Modification for Embedded Contexts]
**Observation:** Added logic in `index.php` and `current_index.php` to actively traverse and modify the parent document (`window.parent.document`) to alter the appearance of a third-party embedding framework (IamResponding).
**Learning:** Cross-origin constraints generally prevent scripts inside an iframe from modifying their parent document. However, if both the parent and iframe share the exact same origin, or if they explicitly configure `document.domain` identically, this is permitted. It's crucial to wrap such logic in `try/catch` blocks to gracefully fail and prevent breaking the application when accessed directly or hosted on a different origin than the parent frame.
**Prevention:** Ensured the DOM traversal and modification code (`hideIamRespondingTitleBar`) is wrapped in a `try/catch` and gracefully handles errors without breaking the rest of the JavaScript execution on the page.

## $(date +%Y-%m-%d) - [Admin CSRF Vulnerability]
**Vulnerability:** The admin dashboard (`admin.php`) was entirely missing Cross-Site Request Forgery (CSRF) protection on all POST forms, including critical state-changing actions like password resets and configuration changes.
**Learning:** Legacy PHP systems sometimes forget to implement CSRF validation across all endpoints or forms, leaving administrative functions vulnerable to forced execution if the user clicks a malicious link.
**Prevention:** Implement a global CSRF token generation during session creation and uniformly apply validation at the ingress point for all POST requests. Ensure every HTML form includes the hidden token.
