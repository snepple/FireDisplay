## 2024-04-14 - Added ARIA labels to icon-only buttons
**Learning:** Found several icon-only buttons (like `&#10094;`, `&#10095;`, and `x`) and visually-hidden toggles that were lacking `aria-label` attributes, making them inaccessible to screen readers.
**Action:** Always verify that elements containing only visual icons (or Unicode characters acting as icons) have descriptive `aria-label`s.

## 2024-04-27 - Linking form labels and inputs
**Learning:** Many form configurations in `admin.php` were missing explicit `for` attributes on `<label>` elements linking them to corresponding input `id`s. This is a critical accessibility issue, as screen readers rely on these associations to correctly announce form fields, and they improve usability by making the labels clickable.
**Action:** Always ensure that `<label>` tags explicitly use the `for` attribute pointing to the `id` of their target input element, especially in setting/configuration forms.

## 2024-05-18 - Missing ID associations on labels
**Learning:** Found several input elements (especially dynamically generated or array-based ones) that were enclosed within `<label>` tags or sitting next to `<label>` tags but lacked proper `for` and `id` linking.
**Action:** When adding inputs, always use `id` on the `<input>` and a matching `for` attribute on the corresponding `<label>`, rather than just nesting the input inside the label or relying on visual proximity, to ensure robust screen reader support and better clickable area.
## 2024-05-24 - Missing ARIA label on icon-only Force Reload button
**Learning:** Found an icon-only button (a refresh button with an SVG) that was missing an `aria-label`, making it inaccessible to screen reader users, despite having a `title` attribute.
**Action:** Always ensure that icon-only buttons (containing only SVGs or Unicode characters) have descriptive `aria-label` attributes to ensure they are accessible to screen readers.

## 2024-05-24 - Missing ARIA label on multiple remove buttons
**Learning:** Found multiple icon-only "Remove" or "Remove Station/Event" buttons in the admin panel which were completely missing an `aria-label`, making it difficult for screen readers to explain what will be removed.
**Action:** Always ensure buttons whose labels aren't descriptive or clear on their own or use symbols have proper `aria-label` attributes.

## 2024-05-24 - Missing "for" attribute linking labels to inputs
**Learning:** There are quite a few instances across the `admin.php` page where labels for inputs are implicitly referencing the next input but aren't strictly linked using `for="id"` and matching ID on the input. This is important for screen reader accessibility to make sure the labels are attached properly to the fields.
**Action:** Always map `<label>` tags explicitly to their `<input>` using the `for` attribute referencing the `id` of the `<input>`.

## 2026-05-01 - Keyboard Accessibility Enhancements
**Learning:** Found that buttons and interactive elements in `admin.php`, `index.php`, and `current_index.php` were missing explicit `:focus-visible` styles, causing a poor experience for keyboard navigators, especially in dark mode where default browser outlines aren't clear.
**Action:** Always verify that interactive elements define a high-contrast `:focus-visible` outline to guarantee keyboard accessibility is preserved across light/dark themes.

## 2024-05-30 - Unique IDs for Dynamic Form Elements
**Learning:** Found that dynamically generated forms (like repeating "Add Station" sections or dynamically added events/chores in JavaScript) often duplicate `<label>` and `<input>` elements without providing unique `id`s, breaking the `for` association and causing accessibility and interaction issues (e.g. clicking a label focuses the first input on the page instead of the adjacent one).
**Action:** When creating or looping over dynamic form elements, always generate a unique suffix (using `uniqid()` in PHP or a random string / iterator in JavaScript) to append to the input `id` and the label `for` attribute, ensuring explicit and unique mapping is maintained.
