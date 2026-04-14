## 2024-05-20 - [Geocoding Bottleneck Fixed]
**Learning:** In dashboards that frequently refresh data (like burn permits), un-memoized mapping logic (`permits.map(p => fetch(geocodeURL))`) creates a critical network bottleneck and risks hitting API rate limits (like Nominatim's) by redundantly querying identical addresses across refresh intervals.
**Action:** When working with map integrations or geocoding in a polling environment, always implement a client-side cache (e.g., an in-memory `Map()`) to memoize the coordinates for static addresses.
