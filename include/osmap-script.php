<?php

/**
 * OS DataHub Maps
 *
 * Maps are generated by outputting an empty <div> and using Leaflet maps to pull in the map tiles
 * using Javascript. This class handles the Javascript and HTML code generation.
 */

class OS_DataHub_Maps_Javascript {

    /**
     * Cached plugin options, read at construction time.
     */
    private $options;

    /**
     * List of supported marker colours.
     */
    private $marker_list = [ 'red', 'orange', 'yellow', 'green', 'blue', 'violet', 'gold', 'grey', 'black' ];

    /**
     * Number of view layers.
     */
    private $layer_count;

    /**
     * List of optional plugins.
     */
    private $used_scripts;

    /**
     * Class constructor.
     * Store the plugin options
     */
    public function __construct( $options ) {
        $this->options = $options;
    }

    /**
     * Return true if the passed tag indicates the script is required.
     */
    public function is_used_script( $tag ) {
        if ( $tag === true ) {
            return true;
        } else {
            return isset( $this->used_scripts[$tag] );
        }
    }

    /**
     * List of scripts required to produce the map.
     */
    public function get_script_list() {
        $script_list = [
            [ 'tag' => 'osmap-leaflet',     'file' => 'leaflet.js',                     'used' => true ],
            [ 'tag' => 'osmap-gesture',     'file' => 'leaflet-gesture-handling.min.js','used' => true ],
            [ 'tag' => 'osmap-fullscreen',  'file' => 'Control.FullScreen.js',          'used' => 'fullscreen' ],
            [ 'tag' => 'osmap-proj4',       'file' => 'proj4.js',                       'used' => true ],
            [ 'tag' => 'osmap-proj4-leaf',  'file' => 'proj4leaflet.min.js',            'used' => true ],
            [ 'tag' => 'osmap-omnivore',    'file' => 'leaflet-omnivore.min.js',        'used' => 'kml' ],
            [ 'tag' => 'osmap-gpx',         'file' => 'gpx.min.js',                     'used' => 'gpx' ],
            [ 'tag' => 'osmap-elevation',   'file' => 'leaflet-elevation.min.js',       'used' => 'elevation' ],
            [ 'tag' => 'osmap-print',       'file' => 'leaflet.browser.print.min.js',   'used' => 'print' ],
            [ 'tag' => 'osmap-locate',      'file' => 'L.Control.Locate.min.js',        'used' => 'locate' ],
            [ 'tag' => 'osmap-script',      'file' => 'os-datahub-maps.js',             'used' => true ]
        ];
        return $script_list;
    }

    /**
     * List of stylesheets required to produce the map.
     */
    public function get_style_list() {
        $style_list = [
            [ 'tag' => 'osmap-leaflet',     'file' => 'leaflet.css' ],
            [ 'tag' => 'osmap-gesture',     'file' => 'leaflet-gesture-handling.min.css' ],
            [ 'tag' => 'osmap-fullscreen',  'file' => 'Control.FullScreen.css' ],
            [ 'tag' => 'osmap-elevation',   'file' => 'leaflet-elevation.css' ],
            [ 'tag' => 'osmap-locate',      'file' => 'L.Control.Locate.min.css' ],
            [ 'tag' => 'osmap-stylesheet',  'file' => 'osmap-style.css' ]
        ];
        return $style_list;
    }

    /**
     * Check the passed option value and return true if it grants permission.
     */
    private function is_permitted( $option, $default = true ) {
        if ( $option == 'all' ) {
            return true;
        } else if ( $option == 'none' ) {
            return false;
        } else if ( $option == 'logged_in' ) {
            return is_user_logged_in();
        }
        return $default;
    }

    /**
     * Open the script tag and create the control variable in the global namespace.
     */
    public function open_script( $mapid ) {
        $javascript = "\n<script>
        if (typeof osDataHubMap != 'object') { var osDataHubMap = { mapList: [] }; }
        osDataHubMap.mapList.push({
            map: null,";
        return $javascript;
    }

    /**
     * Create the map instance without any adornments.
     */
    public function create_map( $mapid, $args, $extent_list ) {
        $open_data_style = esc_js($args['open_data_style']);
        $min_zoom = esc_js($this->options['min_zoom']);
        $max_zoom = esc_js($this->options['max_zoom']);
        $fullscreen = ( $this->is_permitted( $this->options['fullscreen'] ) ? 'true' : 'false' );
        $restrict_pan = ( $this->is_permitted( $this->options['pan_anywhere'] ) ? 'false' : '0.45' );
        $can_print = ( $this->is_permitted( $this->options['can_print'], false ) ? 'true' : 'false' );
        $locate = ( $this->is_permitted( $args['location'], false ) ? 'true' : 'false' );
        $start_locate = ( $args['start_locate'] ? 'true' : 'false' );
        $show_scale = ( $this->options['show_scale'] ? 'true' : 'false' );
        $imperial = ( $this->options['imperial'] ? 'true' : 'false' );
        $hover = ( $args['hover'] ? 'true' : 'false' );
        $gpxstyle = ( $args['gpxstyle'] ? 'true' : 'false' );
        $waypoint = ( $args['waypoint'] ? 'true' : 'false' );
        $center = esc_js($args['center']);
        $default_center = esc_js($args['default_center']);
        $map_fit = esc_js($args['map_fit']);
        $zoom = esc_js($args['zoom']);
        $marker_link = esc_js($args['marker_link']);
        $marker_zoom = esc_js($args['marker_zoom']);
        if ( $args['gestures'] === 'mobile' ) {
            $gestures = '"mobile"';
        } else if ( $args['gestures'] === 'false' ) {
            $gestures = 'false';
        } else {
            $gestures = $args['gestures'] ? 'true' : 'false';
        }
        $autofit = ( $map_fit == 'auto' ) ? 'true' : 'false';
        $fit_margin = intval( $args['fit_margin'] );
        $popup = esc_js( $args['popup'] );

        $start_marker = esc_js($args['start_marker']);
        $end_marker = esc_js($args['end_marker']);

        // Validate zoom_step as a value between 0.1 and 1
        $zoom_step = floatval($this->options['zoom_step']);
        if ( ( $zoom_step < 0.1 ) || ( $zoom_step > 1 ) ) {
            $zoom_step = 1;
        }

        // Check whether we can use premium data.
        if ( ! $this->is_permitted( $args['premium_data'] ) ) {
            // Restrict max zoom if premium data is not allowed.
            if ( $max_zoom > 9.5 ) $max_zoom = 9.5;
            // Leisure style only available as open data up to zoom 5.
            $max_leisure = 5.5;
        } else {
            $max_leisure = 9.5;
        }

        if ( !$args['leisure'] ) {
            $max_leisure = 0;
        }

        if ( $can_print ) {
            $this->used_scripts['print'] = true;
        }
        if ( $fullscreen ) {
            $this->used_scripts['fullscreen'] = true;
        }
        if ( $locate ) {
            $this->used_scripts['locate'] = true;
        }

        $javascript = "
            options: {
                mapId: $mapid,
                openDataStyle: '$open_data_style',
                zoom: $zoom,
                minZoom: $min_zoom,
                maxZoom: $max_zoom,
                maxLeisure: $max_leisure,
                zoomStep: $zoom_step,
                center: '$center',
                defaultCenter: '$default_center',
                mapFit: '$map_fit',
                gestures: $gestures,
                fullscreen: $fullscreen,
                autoFit: $autofit,
                autoFitMargin: $fit_margin,
                popup: '$popup',
                restrictPan: $restrict_pan,
                canPrint: $can_print,
                locate: $locate,
                startLocate: $start_locate,
                showScale: $show_scale,
                gpxStyle: $gpxstyle,
                waypoint: $waypoint,
                imperial: $imperial,
                hover: $hover,
                markerLink: '$marker_link',
                markerZoom: '$marker_zoom',
                startIconColor: '$start_marker',
                endIconColor: '$end_marker',";
        if ( $map_fit == 'extent' ) {           
            $javascript .= "
                extentList: [ '$extent_list[0]', '$extent_list[1]' ],";
        }
        $this->layer_count = 0;

        return $javascript;
    }

    /**
     * Add a set of display layers to the map.
     * Layer names beginning with '-' are initially hidden.
     */
    public function add_layers( $mapid, $args, $layer_list ) {
        $this->layer_count = count( $layer_list );
        $javascript = "
                layerList: [ ";
        foreach ( $layer_list as $layer ) {
            $name = esc_js( $layer );
            $javascript .= "'$name', ";
        }
        $javascript .= "],";
        if ( $args['layers_open'] ) {
            $javascript .= "
                layerListOpen: true,";
        }
        return $javascript;
    }

    /**
     * Convert layer to integer and validate.
     */
    private function get_layer( $layer_id ) {
        $layer = intval( $layer_id );
        if ( ( $layer < 1 ) || ( $layer > $this->layer_count ) ) {
            $layer = 0;
        }
        return $layer;
    }

    /**
     * Sanitise text for use in popup.
     */
    private function get_popup_text( $text, $quote ) {
        // If the text appears to contain HTML, first sanitize as if it were post content.
        // Replace escaped double quotes.
        if ( $text != '' ) {
            if ( $quote != '' ) {
                $text = str_replace( $quote, '"', $text );
            }
            if ( strpbrk( $text, "<>" ) ) {
                $text = addslashes( wp_kses_post( $text ) );
            } else {
                $text = esc_js( $text );
            }
        }
        return $text;
    }

    /**
     * Add a GPX or KML route to the map.
     * Properties in $args provide the route colour, track width, etc.
     */
    public function add_route( $mapid, $args, $route_type, $route_list ) {
        $weight = esc_js( $args['track'] );
        $opacity = esc_js( $args['opacity'] );

        if ( $route_type == 'kml' ) {
            $javascript = "
                kmlList: [";
            // Use Omnivore for KML files only
            $this->used_scripts['kml'] = true;
        } else {
            $javascript = "
                gpxList: [";
            // Use leaflet-gpx for GPX files
            $this->used_scripts['gpx'] = true;
        }
        foreach ( $route_list as $route ) {
            // Sanitize the values to be output.
            $track = esc_js( $route['location'] );
            $color = esc_js( $route['color'] ?: $args['color'] );
            $text = $this->get_popup_text( $route['desc'], $args['quote'] );
            $group = $this->get_layer( $route['layer'] );
            $javascript .= "
                    { weight: $weight, opacity: $opacity, color: '$color', group: $group, text: '$text', url: '$track' },";
        }
        $javascript .= "
                ],";

        return $javascript;
    }

    /**
     * Add an elevation profile below the map.
     */
    public function add_elevation( $mapid, $args ) {
        $route = esc_js( $args['route'] );
        $this->used_scripts['elevation'] = true;
        $javascript = "
                elevationUrl: '$route',";
        return $javascript;
    }

    /**
     * Add feature markers to the map
     */
    public function add_markers( $mapid, $args, $marker_list ) {
        $javascript = "
                markerList: [";
        foreach ( $marker_list as $marker ) {
            // Sanitize the values to be output.
            $pos = esc_js( $marker['location'] );
            $color = esc_js( $marker['color'] );
            $group = $this->get_layer( $marker['layer'] );
            $text = $this->get_popup_text( $marker['desc'], $args['quote'] );
            $javascript .= "
                    { position: '$pos', color: '$color', group: $group, text: '$text' },";
        }
        $javascript .= "
                ],";
        return $javascript;
    }

    /**
     * Add the OS branding to the map, close the script and add the empty divs.
     */
    public function close_script( $mapid, $args, $error_text ) {
        $javascript = "
            }
        });\n</script>\n";
        
        // Generate empty divs to be populated by Javascript
        if ( $args['width'] ) {
            $width = " width: " . esc_attr( $args['width'] ) . "px;";
        } else {
            $width = '';
        }
        
        if ( $args['height'] ) {
            $height = " height: " . esc_attr( $args['height'] ) . "px;";
        } else {
            $height = '';
        }

        if ( $args['class'] ) {
            $custom_class = ' ' . esc_attr( $args['class'] );
        } else {
            $custom_class = '';
        }

        $javascript .="<div class='os-datahub-map-target' id='os-datahub-target-$mapid'></div>\n";
        $class = 'os-datahub-map' . $custom_class;
        $javascript .="<div class='$class' id='os-datahub-map-$mapid' style='max-width:100%;{$height}{$width}'></div>\n";

        if ( $args['have_elevation'] ) {
            $class = 'os-datahub-elev' . $custom_class;
            if ( !$this->options['show_pane'] ) $class .= ' os-datahub-hide-pane';
            $profile = esc_attr( $args['profile'] );
            $javascript .= "<div class='$class' id='os-datahub-elev-$mapid' style='height:{$profile}px; visibility:hidden; max-width:100%;$width'></div>\n";
        }

        if ( $this->options['add_link'] && ( $args['route'] != '' ) ) {
            $route = esc_url( $args['route'] );
            $class = 'osmap_download' . $custom_class;
            $javascript .= "<a class='$class' href='$route' download title='Right click and choose save-as if the direct click does not work.'>Download file for GPS</a>\n";
        }

        if ( ( $error_text != '' ) && current_user_can( 'edit_pages' ) ) {
            $javascript .= "<p>{$error_text}</p>\n";
        }

        return $javascript;
    }

    /**
     * Add a list of map markers below the map.
     */
    public function add_marker_links( $mapid, $args, $marker_list ) {
        $class = 'os-datahub-map-marker-list';
        if ( $args['class'] ) {
            $class .= ' ' . esc_attr( $args['class'] );
        }
        $html = "
        <ul id='os-datahub-map-$mapid-marker-list' class='$class'>";
        foreach ( $marker_list as $i => $marker ) {
            $label = strip_tags( $marker['desc'] );
            $id = $i + 1;
            $html .= "
            <li><a class='os-datahub-map-link' href='#os-datahub-target-$mapid' data-marker='$id'>$label</a></li>";
        }
        $html .= "
        </ul>";
        return $html;
    }

    /**
     * Script to be enqueued and output in the page footer.
     * This adds the initialiser for the global osDataHubMap variable.
     */
    public function footer_script() {
        $apikey = esc_js( $this->options['apikey'] );
        $marker_path = trailingslashit( plugins_url( 'css/images', dirname(__FILE__) ) );
        $javascript = "if (typeof osDataHubMap != 'object') { var osDataHubMap = { mapList: [] }; }";
        $javascript .= "osDataHubMap.apiKey = '$apikey'; osDataHubMap.markerPath = '$marker_path';";

        // Append this to our own script.
        wp_add_inline_script( 'osmap-script', $javascript, 'before' );
    }

    /**
     * Generate custom CSS to change the fill colour of the elevation profile.
     */
    public function custom_css() {
        $css = '';
        if ( $this->options['profile_fill'] ) {
            $css .= '
            .elevation-control.elevation .background .area path.altitude {
                fill: ' . esc_attr( $this->options['profile_fill'] ) . ';
            }';
        }
        if ( $css ) {
            wp_add_inline_style( 'osmap-stylesheet', $css );
        }
    }

    /**
     * Return a marker icon as an image.
     */
    public function marker_img( $color ) {
        $path = trailingslashit( plugins_url( 'css/images', dirname(__FILE__) ) );
        $png = "marker-icon-2x.png";
        if ( preg_match( '/^(\d+)$/', $color, $matches ) ) {
            $suffix = $matches[1];
            if ( $suffix == 0 ) {
                $png = "letter_s.png";
            } else if ( $suffix <= 30 ) {
                $png = "number_{$suffix}.png";
            }
        } else if ( preg_match( '/^([a-zA-Z])$/', $color, $matches ) ) {
            $suffix = strtolower( $matches[1] );
            $png = "letter_{$suffix}.png";
        } else {
            if ( $color && in_array( $color, $this->marker_list ) ) {
                $png = "marker-icon-2x-{$color}.png";
            }
        }
        $path .= $png;
        $html = "<img style='height:1.5em; width:auto; vertical-align:middle;' src='{$path}' />";
        return $html;
    }

    /**
     * Return a link to a map marker.
     */
    public function marker_link( $args ) {
        $mapid = intval( $args['map'] );
        $map_ident = "os-datahub-map-$mapid";
        $markerid = intval( $args['marker'] );
        $zoom = esc_js($args['zoom']);
        $label = esc_html($args['label']);
        $html = "<a class='$map_ident-marker-link os-datahub-map-link'";
        $html .= " href='#os-datahub-target-$mapid' data-marker='$markerid' data-zoom='$zoom'>$label</a>";
        return $html;
    }

}
