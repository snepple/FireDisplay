## 2024-05-20 - [Geocoding Bottleneck Fixed]
**Learning:** In dashboards that frequently refresh data (like burn permits), un-memoized mapping logic (`permits.map(p => fetch(geocodeURL))`) creates a critical network bottleneck and risks hitting API rate limits (like Nominatim's) by redundantly querying identical addresses across refresh intervals.
**Action:** When working with map integrations or geocoding in a polling environment, always implement a client-side cache (e.g., an in-memory `Map()`) to memoize the coordinates for static addresses.

## 2024-05-24 - [DOM Optimization] DocumentFragment for bulk inserts
**Learning:** Appending children directly to a live DOM element inside a loop (e.g. `container.appendChild(el)`) causes repeated browser reflows and repaints.
**Action:** Always batch DOM insertions by appending elements to a `document.createDocumentFragment()` first, and then append the entire fragment to the container outside the loop to trigger only a single reflow.

## 2024-05-25 - [Layout Thrashing Fixed] Batching Read/Write for Pruning
**Learning:** Functions like `pruneDashboard`, `pruneCalendar`, and `pruneChores` read layout properties (`clientHeight`, `scrollHeight`, `offsetHeight`) and immediately modify the DOM (`removeChild`, `style.display='none'`) repeatedly inside `while` loops. This interleaving of layout reads and DOM writes forces the browser to synchronously recalculate layout (reflow) on every iteration, causing significant "layout thrashing" and tanking UI thread performance during pruning operations.
**Action:** To optimize pruning and DOM cleanup loops, always implement a strict "Batch Read -> Compute -> Batch Write" pattern. Read all necessary layout properties and cache them in arrays, compute the exact number of nodes to remove/hide, and then perform all DOM writes in a separate loop without any further layout reads.

## 2024-05-26 - [Backend Optimization] Concurrent External API Requests
**Learning:** Sequential calls to `file_get_contents()` for independent external APIs create cumulative network latency, slowing down backend endpoints (e.g., from ~0.45s to ~0.19s wait time reduction).
**Action:** Always batch independent backend network requests using concurrent handlers like `curl_multi_exec` instead of making sequential blocking calls to eliminate cumulative latency.

## 2024-05-27 - [Frontend Optimization] Concurrent Fetch Resolution
**Learning:** Sequential `await` statements for independent network calls (e.g., `await loadFireDanger(); await loadBurnPermits();`) in the frontend create a rendering waterfall, unnecessarily delaying the dashboard's initial display while waiting for each request to finish before starting the next.
**Action:** Always initialize independent asynchronous network requests concurrently and await them together using `Promise.all()` to minimize total latency and eliminate waterfall delays.
