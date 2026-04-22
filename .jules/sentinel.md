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

## 2025-04-19 - [Missing CSRF Protection in Admin Forms]
**Vulnerability:** The admin configuration page (`admin.php`) lacked Cross-Site Request Forgery (CSRF) protection on multiple state-changing actions, including login, configuration updates, and deleting entries. Although a `generate_csrf.php` file existed to manage tokens, the tokens were neither embedded in the forms nor verified on POST requests.
**Learning:** Having CSRF token generation logic is meaningless if the application does not enforce token verification on state-changing requests. Attackers could theoretically craft malicious pages to trick authenticated administrators into performing unintended actions.
**Prevention:** Always verify CSRF tokens on all POST requests that change state, and ensure the token is included as a hidden field in every corresponding form. Using `hash_equals` is important to prevent timing attacks during token comparison.

## 2026-04-20 - [XSS in Meeting Event and Chore Rendering]
**Vulnerability:** User-controlled data from external calendar feeds (ICS) and `config.json` (announcements, chores) was rendered directly into the DOM using `.innerHTML` without HTML escaping in `index.php`.
**Learning:** Any data originating from external sources or configuration files that can be modified by users must be treated as untrusted and sanitized before being injected into the DOM to prevent Cross-Site Scripting (XSS) attacks.
**Prevention:** Consistently applied the `escapeHtml()` utility function to all variables injected into HTML templates via `.innerHTML` within the `createMeetingEventHtml`, `renderChoresPage`, `renderNumberedChores`, `renderSpecialChores`, and `renderEverydayChores` functions.

- Learned that if a grid container overflows, you can dynamically hide past items (like calendar weeks) by setting `display: none` on the children and decreasing the container's `grid-template-rows` count iteratively while checking `scrollHeight > clientHeight` to fit the content cleanly into the available space without scrollbars.
- Integrated Gemini API into `api/process_email.php` as a fallback parser for unstructured emails when strict regular expressions fail, increasing resilience to format variations.
