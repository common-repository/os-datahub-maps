=== OS DataHub Maps ===
Contributors: skirridsystems
Tags: Ordnance Survey, Map, Walking, Cycling, Riding
Requires PHP: 5.6.0
Requires at least: 4.5
Tested up to: 6.6
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to display UK Ordnance Survey maps with markers and tracks.

== Description ==
This plugin allows maps to be inserted into a page or post using the new Ordnance Survey Data Hub platform which launched in 2020. It displays UK [Ordnance Survey maps](https://osdatahub.os.uk/) with their legendary level of topographical detail, making them ideal for walking, cycling, riding, and just about anything outdoors-related. You can add routes from GPX files, elevation profiles, and markers for points of interest.

The plugin takes over where the previous plugin, OS OpenSpace Maps, left off. Using the new OS Data Hub and its suite of APIs, you now have access to the full 1:25,000 Explorer mapping as well as the 1:50,000 Landranger series. And the free allowances are a lot more generous, offering typically 2,000,000 map views per month.

This plugin uses Leaflet.js to display the maps, giving a clean interface and a responsive feel with easy zoom control, full screen view and even printing. Insert the `[osmap]` shortcode anywhere in your post, page or custom post type content to display a map.

The map is set up using shortcode attributes, and there are now more of these than can be described in this Readme file. For full details, please check the [plugin homepage](https://skirridsystems.co.uk/os-datahub-maps/).

= Additional Shortcodes =
Display one of the marker icons inline with your text using `[osmap_marker color=red]`. This may be used to add annotation to your maps.

Use `[osmap_link marker=1 zoom=8]` in conjunction with `marker_link=link` attribute to embedded a link within text on the page. When clicked, the map will be scrolled into view, zoomed onto the marker position and the marker opened.

= Examples =
* `[osmap]` displays a map at the default height and zoom level, centred on OS headquarters in Southampton. This is the simplest way to test that the plugin is working.

* `[osmap height="300" width="300" color="blue" gpx="http://www.example.co.uk/myfile.gpx"]` displays a 300px by 300px window containing a blue track from the file specified.

* `[osmap markers="NY2000008000;Wasdale"]`shows a default size and zoom window with a marker placed and the popup text "Wasdale"

= Migration from OS OpenSpace Maps =
Ordnance Survey has now shut down the OpenSpace Maps service. This plugin aims to give a seamless upgrade to use the new Data Hub Maps service instead. It uses the same shortcode and all the same attributes are supported.

The Data Hub service is a significant upgrade from OpenSpace, allowing use of the excellent 1:25,000 Explorer mapping. It also has a much more generous free data allowance, typically 2 million map views per month.

The plugin itself is also a major upgrade, with many more features than the original OpenSpace plugin.

== Installation ==

1. Go to the Plugins page in the admin area, search for `datahub` navigate to the plugin page and click `install`.
1. Or download and extract `os-datahub-maps.zip` into the `/wp-content/plugins/` directory. This will create a subfolder called `os-datahub-maps` containing the plugin files.
1. Activate `OS Maps` through the 'Plugins' menu in WordPress
1. Before the plugin will work you need to add your API key to the settings page on the dashboard.
1. If you are running WordPress Multisite you (or your network administrator) will have to add KML and GPX files to the allowed file types under 'Network Settings' if you wish to upload these files.

== Frequently Asked Questions ==

= Is this compatible with OS OpenSpace Maps? =

As far as possible, yes. The new Data Hub mapping service is very different from the OpenSpace service, but we have tried to maintain compatibility as far as possible. All being well you should be able to install the new plugin, set the API key and a few defaults. When you disable the OpenSpace plugin, this one should just populate the existing shortcodes in the same way.

= Where can I get support? =

You can find examples and much more information on the [plugin homepage](https://skirridsystems.co.uk/os-datahub-maps/)

Ask questions in the support forum on the [WordPress plugin page](https://wordpress.org/plugins/os-datahub-maps/)

== Screenshots ==

1. A 1:50,000 Landranger map embedded in a WordPress post
2. A map showing a GPX track and elevation profile
3. A 1:25,000 Explorer map
4. Highly detailed street-level views are also available
5. Zoomed out to 1:1M scale
6. The defaults settings page
7. The global settings page

== Changelog ==

= 1.8.0 =
New: Add start_locate option to allow a map to open at the current location
Change: Update to Leaflet 1.9.4
Change: Use Control.FullScreen plugin in place of Leaflet.Fullscreen which is no longer maintained.

For previous versions please refer to Changelog.txt

== Upgrade Notice ==

= 1.8.0 =
Add new location option.
Dependency updates.
