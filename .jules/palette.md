## 2024-05-09 - Accessible Focus States in Embedded PHP
**Learning:** When adding focus styles to custom elements via attributes like `[role="button"]:focus-visible` inside PHP `echo` blocks (e.g., in `admin.php`), it is critical to properly escape double quotes within the selector string (`[role=\"button\"]:focus-visible`) to avoid fatal PHP syntax parse errors that crash the application.
**Action:** When working on CSS rules embedded within server-side template literals or string echoes, verify syntax using `php -l <filename>` before deploying to catch escaping issues early.
## 2024-05-24 - Admin change password form real-time validation
**Learning:** Adding real-time client side validation for password and confirm password with native HTML5 `minlength` attributes gives immediate feedback, preventing form submission errors and improving UX.
**Action:** Always provide inline, real-time validation for password change/reset forms using JavaScript and native HTML5 form constraints where possible, combined with an `aria-live="polite"` region for accessibility.
## 2026-05-16 - Add aria-pressed to calendar view toggles
**Learning:** When implementing custom stateful toggle buttons or tabs (e.g., calendar view modes), use the `aria-pressed` attribute and dynamically update it to `true` or `false` via JavaScript to ensure screen readers announce the currently active state.
**Action:** Check for stateful toggles in UI components and ensure they reflect their state using `aria-pressed` or `aria-selected`.
