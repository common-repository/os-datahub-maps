=== CSS Files for Leaflet Maps ===

Most of these files are imported from other repositories to provide handling for Leaflet maps.
WordPress no longer allows plugins to pull in 3rd party files from other servers,
so these are local copies of publicly available CSS files.

== Source URLs ==

* https://unpkg.com/leaflet@1.9.4/dist/leaflet.css
* https://unpkg.com/@raruto/leaflet-gesture-handling@1.3.5/dist/leaflet-gesture-handling.min.css
* https://github.com/brunob/leaflet.fullscreen/releases/tag/v3.0.1
* https://unpkg.com/@raruto/leaflet-elevation@1.7.0/dist/leaflet-elevation.css
* https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.76.1/dist/L.Control.Locate.min.css

== Changes After Import ==

In leaflet-elevation.css the paths for images have been changed to lie within this folder
rather than the parent level, for consistency with everything else. For example:
`url(../images/elevation-position.png)` => `url(images/elevation-position.png)`

In L.Control.Locate.min.css the paths for SVG files have been relocated to lie within the
images folder rather than the parent folder, for consistency. For example:
`url(../location-arrow-solid.svg)` => `url(images/location-arrow-solid.svg)`

== Additional Marker Images ==

* https://github.com/pointhi/leaflet-color-markers/
* https://mapicons.mapsmarker.com/numbers-letters/numbers/
* https://mapicons.mapsmarker.com/numbers-letters/letters/
