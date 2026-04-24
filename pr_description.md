This pull request addresses two issues on the dashboard:

1. **Fix Burn Permits Text/Image Overlap on Dashboard:**
    - Modified the `.no-burn-permits` CSS in `index.php` and `current_index.php` to use `flex-shrink: 1` and `min-height: 0` constraints, along with dynamic `max-height: 50vh` on the image and `min(vw, vh)` on text clamps. This ensures the "No active online burn permits at this time." text and image gracefully shrink instead of overflowing and disappearing on smaller vertically-constrained screens like IAMResponding.

2. **Add Toast Notification for Manual Fire Danger Update:**
    - Updated the `loadFireDanger` JavaScript function in `index.php` and `current_index.php` to implement a self-dismissing toast notification.
    - When the force-refresh button is clicked, a "Updating..." toast appears absolute positioned in the bottom-right of the fire danger container.
    - Upon completion, the toast updates to show the fetched risk level and the date (if available) before fading out after 4 seconds.
