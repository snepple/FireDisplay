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

## 2024-05-24 - Unauthenticated Access to API Endpoint via speak.php Bypass
**Vulnerability:** The `api/speak.php` endpoint lacked the `verify_dashboard_token()` check. This allowed any unauthenticated user to make POST requests with text and have the backend synthesize speech via the Google Cloud TTS API using the department's API key. This represented an Unauthenticated API Quota Exhaustion vulnerability and potential SSRF proxy issue.
**Learning:** All endpoints that interact with third-party APIs or perform sensitive operations must enforce authorization. Even if an endpoint is only meant to be called from the frontend `index.php` (which itself is protected), the backend PHP script must independently verify the `dashboard_token`.
**Prevention:** Include `security_check.php` and invoke `verify_dashboard_token()` at the very top of all API endpoints, including utility scripts like `speak.php`. Ensure frontend `fetch` calls append the token to the URL so they can successfully authenticate.
