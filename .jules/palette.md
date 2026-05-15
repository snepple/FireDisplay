## 2024-05-09 - Accessible Focus States in Embedded PHP
**Learning:** When adding focus styles to custom elements via attributes like `[role="button"]:focus-visible` inside PHP `echo` blocks (e.g., in `admin.php`), it is critical to properly escape double quotes within the selector string (`[role=\"button\"]:focus-visible`) to avoid fatal PHP syntax parse errors that crash the application.
**Action:** When working on CSS rules embedded within server-side template literals or string echoes, verify syntax using `php -l <filename>` before deploying to catch escaping issues early.
## 2024-05-24 - Admin change password form real-time validation
**Learning:** Adding real-time client side validation for password and confirm password with native HTML5 `minlength` attributes gives immediate feedback, preventing form submission errors and improving UX.
**Action:** Always provide inline, real-time validation for password change/reset forms using JavaScript and native HTML5 form constraints where possible, combined with an `aria-live="polite"` region for accessibility.

## 2024-05-18 - Form Submission Prevention on Enter
**Learning:** In hybrid PHP/JS dashboard applications like `admin.php`, text inputs embedded within large multi-section forms natively trigger full form submission when the 'Enter' key is pressed. This frequently causes data loss if the user intended to add the text to a dynamic list (like adding a room to a station) rather than saving the entire page settings.
**Action:** When implementing text input fields for dynamic lists within larger forms, always intercept the 'Enter' key (e.g., `onkeydown="if(event.key === 'Enter') { event.preventDefault(); action(); }"`) to prevent the default behavior of prematurely submitting the entire parent form and discarding user input.
