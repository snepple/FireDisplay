git add index.php current_index.php
git commit -m "feat: add town boundary and parcel search layers to permit map

- Added an Oakland town boundary layer to the permit map using Esri FeatureService via GeoJSON
- Implemented a zoom-dependent parcel layer that automatically loads Oakland parcels when zoomed in (>= 15) to maintain map performance and avoid clutter
- Parcels are interactive and display owner, address, and map/lot info on click
- Modified both index.php and current_index.php for feature parity"
