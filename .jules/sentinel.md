# Sentinel

## Security & Reliability Learnings
- **XSS in HTML attributes via json_encode:** When rendering user data (like logs) into HTML attributes such as `title`, use `htmlspecialchars` with `ENT_QUOTES`. Without it, single quotes inside JSON can escape the attribute in older PHP versions, leading to XSS vulnerabilities.
- **Log generation safety:** Before instrumenting logging with context variables (like sender or subject in email processing), ensure the variables are initialized and extracted. Trying to log undefined variables leads to `E_WARNING` notices and empty data in logs.

## Date: [Current Date]

### Issue: Burn Permit Location Not Falling Back to Permittee's Address when Empty or "--"

**Context:** The `api/process_email.php` script extracts burn permit details from incoming emails. If the "Address of Burn Location" is missing, the email template sometimes provides `--`.
**Problem:** The string `--` evaluated as truthy in PHP, which caused the script to use `--` as the primary address instead of correctly falling back to the permittee's home address (`$person_address`). This caused mapping and display issues on the dashboard.
**Resolution:** Updated the condition to `!empty($burn_location_address) && trim($burn_location_address) !== '--'`. The fix was applied to both the initial regex extraction assignment and the Gemini API fallback assignment. A unit test was also added to ensure this edge case doesn't regress.
**Learning:** When parsing text inputs or human-formatted emails, do not assume that empty fields will be strictly empty strings (`""`) or `null`. Fields might contain visual placeholders like `--` or `N/A`, which need to be explicitly evaluated.

## 2024-05-24 - Unauthenticated Access to Configuration Data in API Endpoint
**Vulnerability:** The configuration export endpoint in `admin.php` (`?api=1`) was situated at the top of the script, before the `$_SESSION['admin_logged_in']` authentication check. This allowed any unauthenticated user to access the endpoint and retrieve a large portion of the application's configuration, including `department_info`, `dashboard_settings`, schedules, and active chores, representing an Information Disclosure vulnerability.
**Learning:** Security checks and access controls must be placed explicitly before *any* sensitive routing logic is executed. In flat PHP files like `admin.php` that mix HTML rendering with API responses, routing logic must be strictly ordered so that endpoints requiring authentication are nested safely beneath the global session validations.
**Prevention:** Always verify the flow of execution in single-file administrative scripts to ensure that unauthenticated users cannot trigger data exports or state changes before reaching the intended authorization checks.

## 2024-05-24 - Cross-Site Scripting (XSS) Vulnerability in admin.php
**Vulnerability:** A Reflected XSS vulnerability was found in `admin.php`. The `$page` parameter, derived directly from `$_GET['page']`, was embedded into the HTML template without being sanitized (specifically in the `<h1>` header tag and the `name` attribute of the save button). This could allow an attacker to execute arbitrary scripts if an authenticated admin visited a malicious link.
**Learning:** Always sanitize untrusted input, including query parameters intended for internal template logic or routing, before rendering it into HTML. Even seemingly internal variables like `page` routers can be manipulated by users.
**Prevention:** Apply `htmlspecialchars()` (or an equivalent contextual sanitization function) to all variables populated from user input (like `$_GET`, `$_POST`, etc.) before echoing them to the browser.

## 2024-05-24 - Unauthenticated Access to Configuration Data via get_config.php Bypass
**Vulnerability:** The API endpoint `api/get_config.php` returned sensitive configuration data (such as department infrastructure, apparatus, and scheduling) without checking the `dashboard_token` parameter, even though the main `index.php` file was protected by this token. This allowed an unauthenticated attacker to bypass the frontend token requirement and query the backend directly for information disclosure.
**Learning:** Security checks implemented at the frontend entry point (e.g., `index.php`) do not protect backend API endpoints from direct access. Every API endpoint that serves sensitive data must independently verify authorization requirements.
**Prevention:** Implement token or session validation directly in all data-returning endpoints, ensuring that auxiliary scripts enforce the same access controls as the main application interface.

## 2024-05-24 - Server-Sent Events (SSE) on Shared Hosting Limitations
**Challenge:** Traditional WebSockets require a daemonized Node.js or long-running PHP process (like Swoole/Ratchet), which is typically not allowed or aggressively killed on shared web hosting environments like Bluehost. Standard SSE also suffers from this because keeping a PHP worker open indefinitely to stream events will quickly exhaust the hosting account's maximum allowed concurrent processes, leading to "Resource Limit Reached" (503) errors.
**Solution (Short-Lived SSE):** Implemented a hybrid approach. The PHP script (`api/stream_updates.php`) opens an SSE stream but uses a `while` loop that terminates automatically after a short timeout (e.g., 25 seconds) or immediately after detecting and streaming a file modification event. The frontend `EventSource` API natively auto-reconnects when the connection is closed. This provides near real-time push capabilities without permanently occupying server worker processes.
**Learning:** When architecting real-time features on restrictive shared hosting, use short-polling wrapped inside SSE or long-polling mechanisms to simulate persistent connections while strictly adhering to process execution time limits.

## 2024-05-24 - Double Buffering Image Transitions with CSS
**Challenge:** Directly changing an `<img>` tag's `src` attribute causes a momentary blank white flash while the new image downloads over the network, which is visually disruptive on digital signage displays.
**Solution:** Implemented double-buffering by stacking two absolutely positioned `<img>` tags (`active` and `standby`) inside a relative container. The new image URL is assigned to the `standby` image. A JavaScript `onload` listener waits until the `standby` image has completely downloaded before applying a CSS opacity transition (cross-fade) to hide the `active` image and reveal the `standby` one.
**Learning:** To create seamless visual updates without flashing, load new media assets asynchronously in the background (or in hidden elements) and swap them into view only *after* the browser confirms they have been fully parsed and loaded into memory.
## 2026-04-27 - File Upload Vulnerability in admin.php\n**Vulnerability:** The file upload logic in `admin.php` used a logical OR (`||`) to validate the MIME type and extension (`if (in_array($type, $allowedTypes) || in_array($ext, $allowedExts))`). This allowed an attacker to bypass validation by providing a disallowed file extension (e.g., `.php`) with a valid audio MIME type, leading to Arbitrary File Upload and potential Remote Code Execution (RCE).\n**Learning:** Always validate both the MIME type and the file extension concurrently using a logical AND (`&&`) to ensure strict validation of uploaded files.\n**Prevention:** Use `&&` when checking both MIME type and file extension against allowlists to prevent bypassing security checks in file upload endpoints.

## 2024-05-24 - Missing Rate Limiting on Admin Login Endpoint
**Vulnerability:** The `admin.php` login form did not have any rate limiting or brute-force protection mechanism in place. An attacker could attempt to guess the password repeatedly without any restriction, increasing the likelihood of a successful compromise, especially given that a default password (`ChangeMe123!`) is specified in the configuration.
**Learning:** Always implement robust rate limiting or account lockout mechanisms for authentication endpoints.
**Prevention:** IP-based tracking was introduced to limit the number of failed attempts (e.g., maximum 5 attempts within 15 minutes) before temporarily locking the IP out. This approach requires maintaining an attempt count with a timestamp (such as in `data/login_attempts.json`).

## $(date +%Y-%m-%d) - Missing CSRF Protection on Logout
**Vulnerability:** The `admin.php` logout endpoint `?logout=true` did not have CSRF protection. A malicious website or email could include an image or link like `<img src="https://example.com/admin.php?logout=true">` which would automatically log out any authenticated administrator viewing it.
**Learning:** Even though logging a user out is usually a low-impact action, all state-changing endpoints in an application, especially in administrative panels, should be protected against CSRF to ensure session stability and prevent annoyance or denial of service.
**Prevention:** Always require and validate a CSRF token for all state-changing actions, including logout endpoints, using a secure comparison function like `hash_equals()`. Ensure that `!empty()` checks are performed on both the session token and the request token before comparison to prevent type errors.
## $(date +%Y-%m-%d) - XSS in inline JavaScript via json_encode
**Vulnerability:** Found `json_encode()` calls used directly within `<script>` blocks or assigned to variables that render into HTML without hex-encoding flags in `admin.php`. An attacker who can control data encoded in these JSON objects (such as chore names or announcement content) could break out of the script context and execute arbitrary JavaScript.
**Learning:** `json_encode()` by default does not escape characters like `<`, `>`, `'`, `"` or `&`. If user-controllable data is included in the encoded JSON, an attacker could use `</script>` or malicious payloads to achieve XSS.
**Prevention:** Always use the `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` flags when echoing `json_encode()` directly into inline JavaScript or HTML.

## $(date +%Y-%m-%d) - Type-Juggling CSRF Bypass
**Vulnerability:** CSRF token validation in `api/save_locations.php` checked for token match using strict equality (`!==`). If JSON decoding produced a different type, or if a token was empty, this could cause validation issues or bypasses depending on PHP versions or edge cases.
**Learning:** Using `hash_equals()` with string-casting is the only robust way to compare security tokens to prevent timing attacks and type-juggling vulnerabilities.
**Prevention:** Always validate CSRF tokens using `hash_equals((string)$expected, (string)$provided)` and ensure both values are checked for emptiness prior to comparison.
## $(date +%Y-%m-%d) - CSRF Protection in API endpoints
**Vulnerability:** Although a token query parameter is expected in `api/add_permit.php` to authorize the action against the `dashboard_token`, adding manual records (like burn permits) via a POST request from a web interface normally requires a CSRF token as well, depending on whether the `dashboard_token` itself is treated as sufficient CSRF protection (since it acts as a bearer token).
**Learning:** For endpoints acting as APIs protected by bearer tokens, standard CSRF using session state might not apply if they are truly stateless, but if they rely on cookies or implicit session auth, explicit CSRF tokens are still needed. Since `api/add_permit.php` uses `verify_dashboard_token()` from `api/security_check.php` which checks `$_GET['token']`, it functions essentially as an API token, mitigating CSRF so long as the token isn't leaked or predictable.
**Prevention:** Ensured the backend requires the API token explicitly, and the frontend fetches this token and appends it to the URL, keeping the action authenticated without requiring full session-based CSRF checks which aren't currently implemented across all API endpoints in this system.

## 2026-05-04 - Session Fixation Vulnerability in admin.php
**Vulnerability:** The login endpoint in `admin.php` set `$_SESSION['admin_logged_in'] = true;` without regenerating the session ID upon successful authentication. If an attacker could force a known session ID onto an admin before they log in, the attacker could hijack the authenticated session (Session Fixation).
**Learning:** Establishing a new session lifecycle boundary is critical when a user's privilege level changes (e.g., transitioning from unauthenticated to authenticated).
**Prevention:** Always call `session_regenerate_id(true);` immediately prior to setting authentication flags in `$_SESSION`.

## 2026-05-05 - [Email Injection Bypass via HTTP]
**Vulnerability:** Unauthenticated external users could bypass the mail forwarder and inject fake burn permits or fire danger statuses by sending direct HTTP POST requests to `api/process_email.php`. The script read from `php://input` (via `php://stdin`) without verifying the dashboard token when not in testing mode.
**Learning:** Scripts designed to be executed via CLI (like email forwarders) but accessible over the web server are vulnerable to Server-Side Request Forgery/Injection if they do not explicitly restrict execution to the CLI SAPI or require HTTP authentication.
**Prevention:** Always verify the execution context using `php_sapi_name() !== 'cli'` and enforce strict authentication (e.g., `verify_dashboard_token()`) for any non-CLI access.
