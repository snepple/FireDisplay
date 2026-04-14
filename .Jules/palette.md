## 2024-04-14 - Added ARIA labels to icon-only buttons
**Learning:** Found several icon-only buttons (like `&#10094;`, `&#10095;`, and `x`) and visually-hidden toggles that were lacking `aria-label` attributes, making them inaccessible to screen readers.
**Action:** Always verify that elements containing only visual icons (or Unicode characters acting as icons) have descriptive `aria-label`s.
