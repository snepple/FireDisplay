## 2024-05-09 - Accessible Focus States in Embedded PHP
**Learning:** When adding focus styles to custom elements via attributes like `[role="button"]:focus-visible` inside PHP `echo` blocks (e.g., in `admin.php`), it is critical to properly escape double quotes within the selector string (`[role=\"button\"]:focus-visible`) to avoid fatal PHP syntax parse errors that crash the application.
**Action:** When working on CSS rules embedded within server-side template literals or string echoes, verify syntax using `php -l <filename>` before deploying to catch escaping issues early.
