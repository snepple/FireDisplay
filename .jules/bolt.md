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

## 2024-05-28 - [Frontend Optimization] Redundant ICS Component Instantiation
**Learning:** Instantiating wrapper classes (like `new ICAL.Event(e)`) repeatedly inside frontend iterative arrays (`.forEach`, `.filter`) during calendar parsing unnecessarily bloats CPU usage and delays rendering, especially when defensive instantiations occur in downstream logic.
**Action:** Always map raw components to their wrapper objects immediately upon fetching the data (e.g., `comp.getAllSubcomponents('vevent').map(c => new ICAL.Event(c))`). This normalizes the data array structure early and prevents repetitive instantiations downstream.

## 2024-05-29 - [Frontend Optimization] Redundant Config Parsing in Render Loops
**Learning:** Performing array operations (like `.map()`, `Set()` creation, `.sort()`) and string manipulation (`.split('-')`, `new Date()`) on static configuration data directly inside functions that are called frequently during rendering loops (like calendar day iterations) creates severe CPU bottlenecks and blocks the main thread.
**Action:** Always extract invariant parsing operations out of render loops, or use an internal module-level `cache` object to compute static configuration constants exactly once.
## 2025-05-30 - [Frontend Optimization] Regex Instantiation in Render Loops
**Learning:** Instantiating `RegExp` objects dynamically inside nested loops during frequent rendering operations (like `getCleanNameFromSummary` called repeatedly in array reduction loops) causes severe CPU overhead and garbage collection pauses.
**Action:** Always extract invariant regular expressions (especially those constructed from static arrays like `["Career", "Chief", "Per-Diem", "Night Duty"]`) into module-level or global constants so they are compiled exactly once.

## $(date +%Y-%m-%d) - Extract invariant RegEx from loops
**Learning:** Dynamically instantiating `new RegExp()` inside high-frequency rendering loops (`renderEvent`) and string replacements within the application causes measurable CPU overhead and creates unnecessary garbage collection pressure, negatively impacting performance.
**Action:** Always extract invariant Regular Expressions to module-level or global constants so they are compiled exactly once when the script loads, rather than being re-created dynamically inside loops or function calls.

## 2025-01-24 - [Backend Optimization] Efficient Directory Cleanup
**Learning:** Using `glob()` to list files for background cleanup tasks pre-loads the entire set of filenames into memory, which scales poorly as the cache directory grows. Additionally, performing separate `is_file()` and `filemtime()` calls inside the loop incurs redundant filesystem I/O overhead.
**Action:** Use `DirectoryIterator` for large-scale file system cleanup tasks. It provides a memory-efficient streaming approach and allows retrieving file properties like type and modification time in a more optimized manner within a single pass.
