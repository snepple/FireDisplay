# Incident/Learning Journal

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
