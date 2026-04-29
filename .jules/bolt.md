## 2024-05-20 - [Geocoding Bottleneck Fixed]
**Learning:** In dashboards that frequently refresh data (like burn permits), un-memoized mapping logic (`permits.map(p => fetch(geocodeURL))`) creates a critical network bottleneck and risks hitting API rate limits (like Nominatim's) by redundantly querying identical addresses across refresh intervals.
**Action:** When working with map integrations or geocoding in a polling environment, always implement a client-side cache (e.g., an in-memory `Map()`) to memoize the coordinates for static addresses.
## 2024-05-24 - [DOM Optimization] DocumentFragment for bulk inserts
**Learning:** Appending children directly to a live DOM element inside a loop (e.g. `container.appendChild(el)`) causes repeated browser reflows and repaints.
**Action:** Always batch DOM insertions by appending elements to a `document.createDocumentFragment()` first, and then append the entire fragment to the container outside the loop to trigger only a single reflow.
