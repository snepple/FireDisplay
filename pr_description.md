🛡️ Sentinel: [HIGH] Fix Unauthenticated API Quota Exhaustion / SSRF via speak.php Bypass

🚨 Severity: HIGH
💡 Vulnerability: The `api/speak.php` endpoint completely lacked the `verify_dashboard_token()` check. This allowed any unauthenticated user to directly hit the endpoint, make POST requests with text, and have the backend synthesize speech via the Google Cloud TTS API using the department's API key. This represented an Unauthenticated API Quota Exhaustion vulnerability and a potential SSRF proxy issue.
🎯 Impact: An attacker could bypass the frontend authentication and exhaust the Google Cloud TTS API quota or run up billing charges for the department.
🔧 Fix:
1. Included `security_check.php` and invoked `verify_dashboard_token()` at the top of `api/speak.php`.
2. Updated the frontend JavaScript in `index.php` and `current_index.php` to append the `token` parameter to the URL when making fetch requests to `api/speak.php` so legitimate uses still work.
3. Added a new unit test in `tests/speak.test.js` to explicitly assert that a 403 Forbidden is returned for requests with an invalid/missing dashboard token.
4. Logged this critical finding in `.jules/sentinel.md`.
✅ Verification: Ran `npx jest` to ensure tests, including the new `speak.php` 403 check, pass successfully.
