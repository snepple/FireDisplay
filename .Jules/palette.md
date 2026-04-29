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
