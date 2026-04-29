This pull request addresses the issue where the fire danger image was not displaying on the dashboard.

1. **Fix Image Source Comparison Logic:**
    - The `imgActive.src` property returns a full URL (e.g., `http://.../assets/images/veryhigh.png?t=1234`), which was failing the strict comparison against the new relative URL (`assets/images/veryhigh.png?t=5678`).
    - The updated logic extracts the base path (e.g., `assets/images/veryhigh.png`) and uses `includes()` to safely determine if the image actually needs to be transitioned, ignoring cache-busting timestamp differences.
    - Updated the `else` blocks so that if the base image is already correct, its `src` is simply updated with the newest timestamp string without triggering a broken cross-fade.

2. **Fix Container Styling:**
    - The `#danger-image-container` was missing flexible sizing rules (`flex: 1; min-height: 0;`), causing layout collapsing issues in vertically constrained environments.

These fixes have been applied to both `index.php` and `current_index.php`.
