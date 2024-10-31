<?php

/**
 * OS DataHub Maps
 *
 * Class to process the shortcode attributes and generate an OS map.
 * Uses the OS_DataHub_Maps_Javascript class in osmap-script.php to generate output.
 */

class OS_DataHub_Maps_Shortcode {

    /**
     * The script generator class, instantiated here.
     */
    private $script_class;

    /**
     * All maps on a page have a unique ID starting with 0.
     * Keep track of these in $mapid_next.
     */
    private $mapid_next;

    /**
     * Cached plugin options and defaults.
     */
    private $options;
    private $default_options;
    private $default_link;

    /**
     * Error messages.
     */
    private $error_text;

    /**
     * Class constructor.
     * Save default options and set actions.
     */
    public function __construct( $default_options ) {
        $this->default_options = $default_options;

        add_action( 'init', array( $this, 'init_maps' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'add_stylesheets' ) );
        add_action( 'wp_footer', array( $this, 'print_scripts' ), 1000 );
        add_action( 'plugins_loaded', array( $this, 'add_shortcode' ) );
    }

    public function init_maps() {
        // Read plugin options.
        $this->options = wp_parse_args( get_option( 'os_datahub_map_options' ), $this->default_options );
        // Create script generator class.
        $this->script_class = new OS_DataHub_Maps_Javascript( $this->options );
        // Register scripts for possible use by map generator.
        $this->register_scripts();
    }

    /**
     * Load the shortcode, but only if OS OpenSpace Maps is not loaded too.
     */
    public function add_shortcode() {
        if ( function_exists( 'osmap_generator' ) ) {
            // Add a test shortcode while the old OpenSpace Maps plugin is active.
            add_shortcode( 'osmap_test', array( $this, 'shortcode_callback' ) );
        } else {
            add_shortcode( 'osmap', array( $this, 'shortcode_callback' ) );
        }
        add_shortcode( 'osmap_marker', array( $this, 'marker_shortcode' ) );
        add_shortcode( 'osmap_link', array( $this, 'marker_link_shortcode' ) );
    }

    /**
     * Helper function to convert map scale values from legacy shortcode instances
     * of OS OpenSpace maps to the nearest equivalent zoom value in OS Data Hub maps.
     */
    private function scale_to_zoom( $scale, $default ) {
        $scale_list = array(
            2500 => 0,
            1000 => 0,
            500  => 1,
            200  => 2,
            100  => 3,
            50   => 4,
            25   => 5,
            10   => 6,
            5    => 7,
            4    => 8,
            3    => 9,
            2.5  => 9,
            2    => 10,
            1    => 11
        );
        foreach ( $scale_list as $key => $value ) {
            if ($key == $scale) return $value;
        }
        return $default;
    }
    
    /**
     * Helper function to get the next available mapid value
     * and add it to the internal list of maps on the page.
     */
    private function get_new_mapid() {
        if (isset( $this->mapid_next ) ) {
            $mapid = $this->mapid_next + 1;
        } else {
            $mapid = 1;
        }
        $this->mapid_next = $mapid;
        return $mapid;
    }

    /**
     * Add error message to error text list.
     */
    private function add_error_text( $errstr ) {
        if ( $this->error_text != '' ) {
            $this->error_text .= '<br />';
        }
        $this->error_text .= $errstr;
    }

    /**
     * Helper function to ensure a URL is absolute.
     * Ensure resource scheme matches site scheme too.
     */
    private function get_absolute_url( $url ) {
        if ( strpos( $url, "://" ) === false ) {
            $url = get_site_url( null, $url );
        } else {
            $url = set_url_scheme( $url );
        }
        return $url;
    }

    /**
     * Helper function to get local path for URL.
     */
    private function get_local_path( $url ) {
        $wp_dir = wp_upload_dir();
        if ( strpos( $url, $wp_dir['baseurl'] ) === false ) {
            return false;
        } else {
            return str_replace( $wp_dir['baseurl'], $wp_dir['basedir'], $url );
        }
    }

    /**
     * Helper function to get content of a file as array of lines or a string.
     */
    private function get_file_content( $filename, $as_string ) {
        // In case the file doesn't exist.
        set_error_handler( function( $errno, $errstr ) {}, E_WARNING );
        $file_url = $this->get_absolute_url( $filename );
        if ( $as_string ) {
            $content = file_get_contents( $file_url );
        } else {
            $content = file( $file_url, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        }
        if ( $content === false ) {
            // Try local path instead as some servers reject URL access.
            $file_path = $this->get_local_path( $file_url );
            if ( $file_path !== false ) {
                if ( $as_string ) {
                    $content = file_get_contents( $file_path );
                } else {
                    $content = file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
                }
            }
        }
        if ( $content === false ) {
            $this->add_error_text( "{$filename} not found" );
        }
        restore_error_handler();
        return $content;
    }

    /**
     * Helper function to split a marker, GPX or KML string at its delimiters.
     * Format is location&index!colour;description
     * Only location is mandatory
     */
    private function extract_specifier( &$string, $id ) {
        $desc_start = strpos( $string, $id );
        if ( $desc_start !== false ) {
            $result = substr( $string, $desc_start + 1 );
            $string = substr( $string, 0, $desc_start );
        } else {
            $result = '';
        }
        return $result;
    }

    /**
     * Helper function to split a marker, GPX or KML string at its delimiters.
     * Format is location$layer!colour;description
     * Only location is mandatory
     */
    private function split_specifier( $string, $is_url = false ) {
        $result['desc']  = $this->extract_specifier( $string, ';' );
        $result['color'] = $this->extract_specifier( $string, '!' );
        $result['layer'] = $this->extract_specifier( $string, '$' );
        $result['location'] = $is_url ? $this->get_absolute_url( $string ) : $string;
        return $result;
    }

    /**
     * Helper function to sanitise a JSON-derived item.
     * Only location is mandatory.
     */
    private function sanitise_specifier( $item, $is_url = false ) {
        $location = $item['location'];
        if ( !$location ) {
            return null;
        }
        $item['location'] = $is_url ? $this->get_absolute_url( $location ) : $location;
        if ( !isset( $item['desc'] ) )  $item['desc']  = '';
        if ( !isset( $item['color'] ) ) $item['color'] = '';
        if ( !isset( $item['layer'] ) ) $item['layer'] = '';
        return $item;
    }

    /**
     * Get markers, GPX, KML from a file or direct from the attribute.
     * Used to determine whether to output footer scripts.
     */
    private function get_list( $file_arg, $attribute, $is_url = false ) {
        $list = array();
        $items = false;
        if ( $file_arg != '' ) {
            if ( strcasecmp( pathinfo( $file_arg, PATHINFO_EXTENSION ), 'json' ) == 0 ) {
                // JSON file.
                $json = $this->get_file_content( $file_arg, true );
                if ( $json ) {
                    $decode = json_decode( $json, true, 4 );
                    if ( $decode !== null ) {
                        // Ensure we have the minimum required data.
                        foreach ( $decode as $item ) {
                            $clean = $this->sanitise_specifier( $item, $is_url );
                            if ( $clean !== null ) {
                                $list[] = $clean;
                            }
                        }
                    } else {
                        $this->add_error_text( "{$file_arg} invalid JSON" );
                    }
                }
            } else {
                // Old-style content in a file.
                $items = $this->get_file_content( $file_arg, false );
            }
        } else {
            // Old-style content in the attribute
            $items = explode ( '|', $attribute );
        }
        // Extract the old-style content.
        if ( $items && ( $items[0] != '' ) ) {
            // Split the 
            foreach ( $items as $item) {
                // Split the marker into its components and add to list.
                $list[] = $this->split_specifier( $item, $is_url );
            }
        }
        return $list;
    }

    /**
     * Return true if maps have been requested.
     * Used to determine whether to output footer scripts.
     */
    public function maps_on_page() {
        return isset( $this->mapid_next );
    }

    /**
     * Main function to process the shortcode.
     */
    public function shortcode_callback( $atts ) {
        // Do nothing if we are on a feed
        if ( is_feed() ) return;

        // Clear error text
        $this->error_text = '';

        // Return if the api key is not valid
        if ( ! ( 'good' == $this->options['apikey_state'] ) )
            return '<div id="os-map-error">OS Maps Error: Invalid Maps API Key</div>';

        $mapid = $this->get_new_mapid();

        // Get the shortcode info, set defaults
        $args = shortcode_atts( array(
                'zoom'              => '',
                'scale'             => '',
                'height'            => $this->options['default_height'],
                'width'             => $this->options['default_width'],
                'profile'           => $this->options['default_profile'],
                'center'            => '',
                'centre'            => '',
                'color'             => $this->options['default_color'],
                'colour'            => '',
                'track'             => $this->options['default_track'],
                'hover'             => $this->options['default_hover'],
                'opacity'           => '0.7',
                'gpx'               => '',
                'gpxfile'           => '',
                'gpxstyle'          => '',
                'waypoint'          => $this->options['default_waypoint'],
                'start_marker'      => '',
                'end_marker'        => '',
                'kml'               => '',
                'kmlfile'           => '',
                'markers'           => '',
                'markerfile'        => '',
                'marker_link'       => '',
                'marker_zoom'       => '',
                'layers'            => '',
                'extent'            => '',
                'quote'             => '',
                'fit_margin'        => '20',
                'popup'             => '',
                'gestures'          => $this->options['default_gestures'],
                'location'          => $this->options['default_location'],
                'premium_data'      => $this->options['premium_data'],
                'open_data_style'   => $this->options['open_data_style'],
                'leisure'           => true,
                'class'             => '',
                'custom'            => '',
                'start_locate'      => false,
            ),
            $atts );

        // Allow centre in place of center
        if ( $args['centre'] ) {
            $args['center'] = $args['centre'];
        }

        $args['have_elevation'] = false;

        // Use GPX if available, first from file, then from attributes
        $gpx_list = $this->get_list( $args['gpxfile'], $args['gpx'], true );
        $gpx_count = count( $gpx_list );

        // Use KML if available, first from file, then from attributes
        $kml_list = $this->get_list( $args['kmlfile'], $args['kml'], true );
        $kml_count = count( $kml_list );

        // Use markers if available, first from file, then from attributes
        $marker_list = $this->get_list( $args['markerfile'], $args['markers'] );
        $marker_count = count( $marker_list );

        // Use layer list if provided.
        if ( $args['layers'] != '' ) {
            $list = $args['layers'];
            // Check for leading +
            if ( strpos( $list, '+' ) === 0 ) {
                $args['layers_open'] = true;
                $list = substr( $list, 1 );
            } else {
                $args['layers_open'] = false;
            }
            $layer_list = explode ( '|', $list );
            $layer_count = count( $layer_list );
        } else {
            $layer_count = 0;
        }

        // Determine how map is fitted to page
        $map_fit = '';
        // Specific extent takes first priority
        if ( $args['extent'] != '' ) {
            $extent_list = explode ( '|', $args['extent'] );
            if ( count( $extent_list ) == 2 ) {
                $map_fit = 'extent';
            }
        } else {
            $extent_list = '';
        }

        // Use 'center' if both zoom and center have been specified.
        if ( ( $map_fit == '' ) && ( $args['center'] != '' ) && ( $args['zoom'] != '' ) ) {
            $map_fit = 'center';
        }

        // Auto fit if route or markers provided.
        if ( $map_fit == '' ) {
            if ( ( $gpx_count != 0 ) ||
                 ( $kml_count != 0 ) ||
                 ( $marker_count > 1 ) ) {
                $map_fit = 'auto';
            }
        }

        // Set OS headquarters as default center in case nothing else works.
        $args['default_center'] = '50.938064,-1.470971';

        // No fit method defined, find best default.
        if ( $map_fit == '' ) {
            if ( $args['center'] == '' ) {
                if ( $marker_count == 1 ) {
                    // Use single marker as center.
                    $args['center'] = $marker_list[0]['location'];
                } else {
                    // Use default center.
                    $args['center'] = $args['default_center'];
                }
            }
            $map_fit = 'center';
        }
        $args['map_fit'] = $map_fit;

        // Allow colour in place of color.
        if ( $args['colour'] ) {
            $args['color'] = $args['colour'];
        }

        // Use scale values inherited from OpenSpace maps.
        if ( $args['scale'] ) {
            $args['zoom'] = $this->scale_to_zoom( $args['scale'], $args['zoom'] );
        }

        // Use default zoom if not specified.
        if ( $args['zoom'] == '' ) {
            $args['zoom'] = $this->options['default_zoom'];
        }

        // Route used for link and profile.
        $args['route'] = '';

        // Generate the map.
        $javascript = $this->script_class->open_script( $mapid );

        $javascript .= $this->script_class->create_map( $mapid, $args, $extent_list );

        if ( $layer_count != 0 ) {
            $javascript .= $this->script_class->add_layers( $mapid, $args, $layer_list );
        }

        // Add markers
        if ( $marker_count != 0 ) {
            $javascript .= $this->script_class->add_markers( $mapid, $args, $marker_list );
        }

        // Save link marker defaults
        $this->default_link['map'] = $mapid;
        $this->default_link['marker'] = 1;
        $this->default_link['zoom'] = intval( $args['marker_zoom'] );

        // Add GPX routes
        if ( $gpx_count != 0 ) {
            $javascript .= $this->script_class->add_route( $mapid, $args, 'gpx', $gpx_list );
            if ( $gpx_count == 1 ) {
                $args['route'] = $gpx_list[0]['location'];
                if ( $args['profile'] ) {
                    // Fix profile height
                    if ( $args['profile'] < 30 ) {
                        $args['profile'] = 100;     // Prevent tiny profiles
                    }
                    $args['profile'] += 20;         // Account for forced margin
                    $javascript .= $this->script_class->add_elevation( $mapid, $args );
                    $args['have_elevation'] = true;
                }
            }
        }

        // Add KML overlays
        if ( $kml_count != 0 ) {
            $javascript .= $this->script_class->add_route( $mapid, $args, 'kml', $kml_list );
            if ( ( $args['route'] == '' ) && ( $kml_count == 1 ) ) {
                $args['route'] = $kml_list[0]['location'];
            }
        }

        // Add the closing parts of the script and the HTML.
        $javascript .= $this->script_class->close_script( $mapid, $args, $this->error_text );

        // Add marker links
        if ( ( $marker_count != 0 ) && ( stripos( $args['marker_link'], 'auto' ) !== false ) ) {
            $javascript .= $this->script_class->add_marker_links( $mapid, $args, $marker_list );
        }

        // Add a hook for custom output.
        return apply_filters( 'os_map_post', $javascript, $mapid, $args, $marker_list, $gpx_list, $kml_list );
    }

    /**
     * Function to process the osmap_marker shortcode.
     */
    public function marker_shortcode( $atts ) {
        // Do nothing if we are on a feed
        if ( is_feed() ) return;

        // Get the shortcode info, set defaults
        $args = shortcode_atts( array(
                  'color'       => '',
                ),
                $atts );

        return $this->script_class->marker_img( $args['color'] );
    }

    /**
     * Function to process the osmap_link shortcode.
     */
    public function marker_link_shortcode( $atts ) {
        // Do nothing if we are on a feed
        if ( is_feed() ) return;

        if ( !isset( $this->default_link ) ) {
            $this->default_link = array(
                'map'       => 1,
                'marker'    => 1,
                'zoom'      => 0,
            );
        }
        if ( !isset( $this->default_link['label'] ) ) {
            $this->default_link['label'] = 'view on map';
        }

        // Get the shortcode info, set defaults
        $args = shortcode_atts( $this->default_link, $atts );
        $this->default_link = $args;
        $this->default_link['marker'] = intval( $this->default_link['marker'] ) + 1;

        return $this->script_class->marker_link( $args );
    }

    /**
     * Function to register scripts for possible output later.
     */
    private function register_scripts() {
        $script_list = $this->script_class->get_script_list();
        $plugin_dir = dirname(__FILE__);
        foreach ( $script_list as $script ) {
            wp_register_script ( $script['tag'], plugins_url( 'js/' . $script['file'], $plugin_dir ), '', OS_DATAHUB_MAPS_VERSION );
        }
    }

    /**
     * Function to enqueue all required stylesheets.
     */
    public function add_stylesheets() {
        $style_list = $this->script_class->get_style_list();
        $plugin_dir = dirname(__FILE__);
        foreach ( $style_list as $style ) {
            wp_enqueue_style ( $style['tag'], plugins_url( 'css/' . $style['file'], $plugin_dir ), '', OS_DATAHUB_MAPS_VERSION );
        }
        // Append additional customisation CSS
        $this->script_class->custom_css();
    }

    /**
     * Function called by WordPress when footer scripts are output.
     * Print the previously registered scripts.
     */
    public function print_scripts() {
        // Only output scripts if there are maps present on the page.
        if ( isset( $this->mapid_next ) ) {
            // Append the script to initialise the maps
            $this->script_class->footer_script();
            // Output all scripts
            $script_list = $this->script_class->get_script_list();
            foreach ( $script_list as $script ) {
                if ( $this->script_class->is_used_script( $script['used'] ) ) {
                    wp_print_scripts ( $script['tag'] );
                }
            }
        }
    }
}
