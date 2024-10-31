<?php

/**
 * OS DataHub Maps
 *
 * Contains all the backend interface for the plugin.
 * The mostly consists of the settings page but also includes
 * functions to check the validity of the API key.
 */

class OS_DataHub_Maps_Admin {

    const documentation_url   = 'https://skirridsystems.co.uk/os-datahub-maps/';
    const registration_url    = 'https://osdatahub.os.uk/';
    const os_support_url      = 'https://osdatahub.os.uk/support';
    const admin_page_url      = 'options-general.php?page=os-datahub-maps';

    private $no_api_key_html  =
            '<p><strong>You have not yet entered an API key.</strong> OS maps will not be shown until one is entered.
            To get an API key click <a href="' . self::registration_url . '" title="API key registration">here</a>.</p>';

    private $goto_settings_html =
            '<p>Go to the OS Maps <a href="' . self::admin_page_url . '">Settings page</a> to enter the API key.</p>';

    private $bad_api_key_html =
            '<p><strong>There appears to be an error with your API key.</strong> OS maps will not be shown until it is corrected.
            To get an API key click <a href="' . self::registration_url . '" title="API key registration">here</a>.</p>
            <p>Further information is available in the <a href="' . self::os_support_url . '">Data Hub Maps FAQ</a>.</p>
            <p>Refer to the <a href="' . self::documentation_url . '">plugin documentation</a> for more information.</p>';

    private $additional_mime_types = array(
                'gpx' => 'text/xml',
                'kml' => 'text/xml',
                'txt' => 'text/plain',
                'json' => 'application/json'
            );
    
    private $options;
    private $default_options;
    
    /**
     * Class constructor.
     * Read the plugin options from the database and add actions.
     */
    public function __construct( $default_options ) {
        $this->default_options = $default_options;
        add_action( 'admin_notices', array( $this, 'admin_notice' ) );
        add_action( 'admin_init', array( $this, 'settings_page' ) );
        add_action( 'admin_menu', array( $this, 'add_to_admin_menu' ) );
        add_filter( 'upload_mimes', array( $this, 'add_mime_types' ), 1, 1 );
        add_filter( 'wp_check_filetype_and_ext', array( $this, 'add_file_and_ext' ), 10, 4 );
    }
    
    /**
     * Set up the MIME types for GPX and KML files to permit upload.
     * Also text files for markers.
     */
    public function add_mime_types( $mime_types ){
        return array_merge( $mime_types, $this->additional_mime_types);
    }

    /**
     * Also add file extension checks for these file types.
     */
    public function add_file_and_ext( $types, $file, $filename, $mimes ) {
        foreach ( $this->additional_mime_types as $ext => $mime ) {
            if ( false !== stripos( $filename, '.' . $ext ) ) {
                $types['ext'] = $ext;
                $types['type'] = $mime;
                $types['proper_filename'] = $filename;
            }
        }

        return $types;
    }

    /**
     * Check for a valid API key, returns none, error, good or bad
     */
    private function api_key_check( $key ) {
        // if there is no API key
        if ( ! $key ) {
            return 'none';
        }
        // There is a key we now need to check it
        $args = array();
        $args['timeout'] = 15;
        $result = wp_remote_get( 'https://api.os.uk/maps/raster/v1/zxy/Road_27700/0/0/0.png?key=' . esc_attr($key), $args );
        if( is_wp_error( $result ) )
            return 'error';
        if ( $result['response']['code'] != 200 )
            return 'bad';
        if ( stripos( wp_remote_retrieve_body( $result ), 'Invalid ApiKey' ) !== false )
            return 'bad';
        return 'good';
    }

    /**
     * Plugin activation sets the transient 'osmap-activation-check'
     * If that transient is discovered during admin_notices, run the API key check and show a warning if not valid.
     */
    public function admin_notice() {
        if ( get_transient( 'osmap-activation-check' ) ) {
            $key = $this->api_key_check( $this->options['apikey'] );
            if ( $key == 'none' ) {
                echo '<div class="error notice is-dismissible">' . $this->no_api_key_html . $this->goto_settings_html . '</div>';
            } elseif ( $key != 'good' ) {
                echo '<div class="error notice is-dismissible">' . $this->bad_api_key_html . '</div>';
            }

            delete_transient( 'osmap-activation-check' );
        }
    }

    /**
     * Generate the plugin settings page using the WordPress Settings API.
     */
    public function settings_page() {
        $this->options = wp_parse_args( get_option( 'os_datahub_map_options' ), $this->default_options );
        // Include WordPress Color Picker dependencies
        wp_enqueue_style( 'wp-color-picker' ); 
        wp_enqueue_script( 'os-datahub-admin', plugins_url( 'js/osmap-admin.js', dirname(__FILE__) ), array( 'wp-color-picker' ), false, true );
        // Register the name used for our options in the database.
        register_setting('os-datahub-maps', 'os_datahub_map_options', array( $this, 'sanitize_cb' ) );
        // Add the sections to be used on the page
        add_settings_section('sect_api_key',    'API Keys',                     '',                                     'os-datahub-maps');
        add_settings_section('sect_defaults',   'Map Defaults',                 array( $this, 'default_section_cb' ),   'os-datahub-maps');
        add_settings_section('sect_global',     'Global Settings',              array( $this, 'global_sect_cb' ),       'os-datahub-maps');
        // Add fields to each section
        add_settings_field('apikey',            'Maps API Key',                 array( $this, 'apikey_cb' ),            'os-datahub-maps', 'sect_api_key');
        add_settings_field('default_zoom',      'Map Zoom',                     array( $this, 'default_zoom_cb' ),      'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_height',    'Map Height (pixels)',          array( $this, 'default_height_cb' ),    'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_width',     'Map Width (pixels)',           array( $this, 'default_width_cb' ),     'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_profile',   'Profile Height (pixels)',      array( $this, 'default_profile_cb' ),   'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_color',     'GPX Track Colour',             array( $this, 'default_color_cb' ),     'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_track',     'GPX Track Width (pixels)',     array( $this, 'default_track_cb' ),     'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_hover',     'Open Popups on Hover',         array( $this, 'default_hover_cb' ),     'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_waypoint',  'Enable Waypoints',             array( $this, 'default_waypoint_cb' ),  'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_gestures',  'Enable Gesture Handling',      array( $this, 'default_gestures_cb' ),  'os-datahub-maps', 'sect_defaults');
        add_settings_field('premium_data',      'Premium Data Use',             array( $this, 'premium_data_cb' ),      'os-datahub-maps', 'sect_defaults');
        add_settings_field('open_data_style',   'OpenData Style',               array( $this, 'open_data_style_cb' ),   'os-datahub-maps', 'sect_defaults');
        add_settings_field('default_location',  'Enable Location on Map',       array( $this, 'default_location_cb' ),  'os-datahub-maps', 'sect_defaults');
        add_settings_field('fullscreen',        'Fullscreen',                   array( $this, 'fullscreen_cb' ),        'os-datahub-maps', 'sect_global');
        add_settings_field('pan_anywhere',      'Unrestricted Pan',             array( $this, 'pan_anywhere_cb' ),      'os-datahub-maps', 'sect_global');
        add_settings_field('can_print',         'Print Maps',                   array( $this, 'can_print_cb' ),         'os-datahub-maps', 'sect_global');
        add_settings_field('min_zoom',          'Minimum Zoom to Use',          array( $this, 'min_zoom_cb' ),          'os-datahub-maps', 'sect_global');
        add_settings_field('man_zoom',          'Maximum Zoom to Use',          array( $this, 'max_zoom_cb' ),          'os-datahub-maps', 'sect_global');
        add_settings_field('zoom_step',         'Zoom Step Size',               array( $this, 'zoom_step_cb' ),         'os-datahub-maps', 'sect_global');
        add_settings_field('profile_fill',      'Profile Fill Colour',          array( $this, 'profile_fill_cb' ),      'os-datahub-maps', 'sect_global');
        add_settings_field('show_pane',         'Info Pane',                    array( $this, 'show_pane_cb' ),         'os-datahub-maps', 'sect_global');
        add_settings_field('imperial',          'Use Imperial Units',           array( $this, 'imperial_cb' ),          'os-datahub-maps', 'sect_global');
        add_settings_field('show_scale',        'Show Scale',                   array( $this, 'show_scale_cb' ),        'os-datahub-maps', 'sect_global');
        add_settings_field('add_link',          'Add Link to GPX/KML Files',    array( $this, 'add_link_cb' ),          'os-datahub-maps', 'sect_global');
    }

    /**
     * Add the plugin settings page to the general settings menu.
     */
    public function add_to_admin_menu() {
        // Add our settings page to the options menu
        add_options_page( 'OS Maps', 'OS Maps', 'manage_options', 'os-datahub-maps', array( $this, 'options_display' ) ); 
    }

    /**
     * Function to populate a 'select' combo box with supported zoom values.
     */
    private function show_zoom_selector( $ident, $default ) {
        // These are the OS zoom values from the technical spec.
        $zoom_list = array(
            0  => 'Whole UK',
            1  => 'Half UK',
            2  => '1:1M',
            3  => '1:1M, zoom',
            4  => '1:250,000',
            5  => '1:250,000, zoom',
            6  => '1:50,000 Landranger',
            7  => '1:50,000 Landranger, zoom',
            8  => '1:25,000 Leisure',
            9  => '1:25,000 Leisure, zoom',
            10 => '1:3,307 Detail',
            11 => '1:1,654 Detail',
            12 => '1:827 Detail',
            13 => '1:413 Detail'
        );
        $current_zoom = $this->options[ $ident ];
        if ( ! $current_zoom ) $current_zoom = $default;
        echo '<select id="' . $ident . '" name="os_datahub_map_options[' . $ident . ']">';
        foreach ( $zoom_list as $key => $value ) {
            $selected = ( $current_zoom == $key ) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_attr($value) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Function to populate a 'select' combo box with supported OpenData styles.
     */
    private function show_open_data_style_selector( $default ) {
        // These are the OS style names from the technical spec.
        $style_list = [ 'Road', 'Outdoor', 'Light' ];
        $current_style = $this->options['open_data_style'];
        if ( ! $current_style ) $current_style = $default;
        echo '<select id="open_data_style" name="os_datahub_map_options[open_data_style]">';
        foreach ( $style_list as $style ) {
            $selected = ( $current_style == $style ) ? 'selected="selected"' : '';
            echo '<option value="' . $style . '" ' . $selected . '>' . $style . '</option>';
        }
        echo '</select>';
    }

    /**
     * Function to populate a 'select' combo box with premium data options.
     */
    private function show_premium_data_selector( $ident, $default ) {
        $users_list = array(
            'all'       => 'Everyone',
            'logged_in' => 'Logged-in users',
            'none'      => 'No one'
        );
        $current_users = $this->options[ $ident ];
        if ( ! $current_users ) $current_users = $default;
        echo '<select id="' . $ident .'" name="os_datahub_map_options[' . $ident . ']">';
        foreach ( $users_list as $key => $value ) {
            $selected = ( $current_users == $key ) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_attr($value) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Function to populate a 'select' combo box with gesture type options.
     */
    private function show_gestures_selector() {
        $gestures_list = array(
            'true'      => 'Always',
            'mobile'    => 'Mobile-only',
            'false'     => 'Never'
        );
        $current_gestures = $this->options['default_gestures'];
        if ( is_bool( $current_gestures ) || !array_key_exists( $current_gestures, $gestures_list ) ) {
            $current_gestures = $current_gestures ? 'true' : 'false';
        }
        echo '<select id="default_gestures" name="os_datahub_map_options[default_gestures]">';
        foreach ( $gestures_list as $key => $value ) {
            $selected = ( $current_gestures == $key ) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_attr($value) . '</option>';
        }
        echo '</select>';
    }

    /**
     * Function to populate a 'select' combo box with supported zoom step sizes.
     */
    private function show_zoom_step_selector( $default ) {
        // These are the step sizes we have chosen to offer.
        $step_list = [ '1', '0.5', '0.25' ];
        $current_step = $this->options['zoom_step'];
        if ( ! $current_step ) $current_step = $default;
        echo '<select id="zoom_step" name="os_datahub_map_options[zoom_step]">';
        foreach ( $step_list as $step ) {
            $selected = ( $current_step == $step ) ? 'selected="selected"' : '';
            echo '<option value="' . $step . '" ' . $selected . '>' . $step . '</option>';
        }
        echo '</select>';
    }

    /**
     * Callback functions used by the Settings API to generate section headings.
     */
    public function default_section_cb() {
        echo '<p>These options apply to all maps but can be overridden using shortcode attributes.</p>';
    }

    public function global_sect_cb() {
        echo '<p>These options apply to all maps and cannot be overridden using shortcode attributes.</p>';
    }

    /**
     * Callback functions used by the Settings API to display each of the fields.
     * These are the callbacks for the defaults section.
     */
    public function apikey_cb() {
        echo '<input id="apikey" name="os_datahub_map_options[apikey]" size="40" type="text" value="' . esc_attr($this->options['apikey']) . '" />';
        echo '<p class="description">You must register on the Ordnance Survey Data Hub for a Maps API key
              <a href="' . self::registration_url . '" target="_blank">here</a> before using this plugin.</p>';
    }

    public function default_zoom_cb() {
        $this->show_zoom_selector( 'default_zoom', 7 );
    }

    public function default_height_cb() {
        echo '<input id="default_height" name="os_datahub_map_options[default_height]" size="4" type="text" value="' . esc_attr($this->options['default_height']) . '" />';
        echo '<p class="description">Height of map on the page. You should always specify this value unless you set it in your own CSS.';
    }

    public function default_width_cb() {
        echo '<input id="default_width" name="os_datahub_map_options[default_width]" size="4" type="text" value="' . esc_attr($this->options['default_width']) . '" />';
        echo '<p class="description">Width of map on the page. Leave blank to use all available width or your own CSS.';
    }

    public function default_profile_cb() {
        echo '<input id="default_profile" name="os_datahub_map_options[default_profile]" size="4" type="text" value="' . esc_attr($this->options['default_profile']) . '" />';
        echo '<p class="description">Height of elevation profile below the map when showing GPX files. Leave blank to disable profiles.';
    }

    public function default_color_cb() {
        echo '<input id="default_color" name="os_datahub_map_options[default_color]" size="12" type="text" value="' . esc_attr($this->options['default_color']) . '" class="osmap-color-picker" />';
        echo '<p class="description">Colour used for drawing GPX routes on the map.';
    }

    public function default_track_cb() {
        echo '<input id="default_track" name="os_datahub_map_options[default_track]" size="4" type="text" value="' . esc_attr($this->options['default_track']) . '" />';
        echo '<p class="description">Track width (pixels) used for drawing GPX routes on the map.';
    }

    public function default_hover_cb() {
        echo '<input id="default_hover" name="os_datahub_map_options[default_hover]" type="checkbox"' . ($this->options['default_hover'] ? ' checked="true"' : '') . ' />';
        echo '<p class="description">Open marker and track popups on hover instead of requiring a click.</p>';
    }

    public function default_waypoint_cb() {
        echo '<input id="default_waypoint" name="os_datahub_map_options[default_waypoint]" type="checkbox"' . ($this->options['default_waypoint'] ? ' checked="true"' : '') . ' />';
        echo '<p class="description">Show waypoints on GPX tracks.</p>';
    }

    public function default_gestures_cb() {
        $this->show_gestures_selector();
        echo '<p class="description">Two-finger drag on mobile devices and Ctrl-scroll to zoom on desktop.<br />Recommended for full width maps to avoid navigation getting trapped.<br />This setting will be disabled automatically when viewing full screen.</p>';
    }

    public function premium_data_cb() {
        $this->show_premium_data_selector( 'premium_data', 'all' );
        echo '<p class="description">Select which users are permitted to view premium data (Landranger, Explorer and detail views beyond 1:25,000).</p>';
    }

    public function open_data_style_cb() {
        $this->show_open_data_style_selector( 'Outdoor' );
        echo '<p class="description">Style used for detail views beyond 1:25,000 or when Leisure maps are not in use.</p>';
    }

    public function default_location_cb() {
        $this->show_premium_data_selector( 'default_location', 'none' );
        echo '<p class="description">Select which users are permitted to show their location on the map.</p>';
    }

    /**
     * Callback functions used by the Settings API to display each of the fields.
     * These are the callbacks for the global section.
     */
    public function fullscreen_cb() {
        $this->show_premium_data_selector( 'fullscreen', 'all' );
        echo '<p class="description">Select which users get a control to show the map on full screen.</p>';
    }

    public function pan_anywhere_cb() {
        $this->show_premium_data_selector( 'pan_anywhere', 'all' );
        echo '<p class="description">Select which users get unrestricted panning of maps.</p>';
    }

    public function can_print_cb() {
        $this->show_premium_data_selector( 'can_print', 'none' );
        echo '<p class="description">Select which users are allowed to print maps.</p>';
    }

    public function min_zoom_cb() {
        $this->show_zoom_selector( 'min_zoom', 2 );
    }

    public function max_zoom_cb() {
        $this->show_zoom_selector( 'max_zoom', 9 );
    }

    public function zoom_step_cb() {
        $this->show_zoom_step_selector( '1' );
        echo '<p class="description">A step size of 1 doubles the scale each time. A step size of 0.5 takes two steps to double the scale.</p>';
    }

    public function profile_fill_cb() {
        echo '<input id="profile_fill" name="os_datahub_map_options[profile_fill]" size="12" type="text" value="' . esc_attr($this->options['profile_fill']) . '" class="osmap-color-picker" />';
        echo '<p class="description">Colour used for elevation profiles.';
    }

    public function show_pane_cb() {
        echo '<input id="show_pane" name="os_datahub_map_options[show_pane]" type="checkbox"' . ($this->options['show_pane'] ? ' checked="true"' : '') . ' />';
        echo '<p class="description">Show height and route distance at current position on the elevation profile.</p>';
    }

    public function imperial_cb() {
        echo '<input id="imperial" name="os_datahub_map_options[imperial]" type="checkbox"' . ($this->options['imperial'] ? ' checked="true"' : '') . ' />';
        echo '<p class="description">Display elevation profile data in imperial units (miles / feet).</p>';
    }

    public function show_scale_cb() {
        echo '<input id="show_scale" name="os_datahub_map_options[show_scale]" type="checkbox"' . ($this->options['show_scale'] ? ' checked="true"' : '') . ' />';
        echo '<p class="description">Display a map scale indicator at the bottom of the map.</p>';
    }

    public function add_link_cb() {
        echo '<input id="add_link" name="os_datahub_map_options[add_link]" type="checkbox"' . ($this->options['add_link'] ? ' checked="true"' : '') . ' />';
        echo '<p class="description">Adds a link to the GPX/KML file below the map if one file has been used. If multiple files are used you will need to add links manually.</p>';
    }

    /**
     * Helper function to validate and range-check an integer, and display an error message.
     */
    private function validate_as_number( $label, $text, $minval, $maxval, $blank_ok ) {
        if ( ( $text === '' ) && $blank_ok ) return true;

        $value = filter_var( $text, FILTER_VALIDATE_FLOAT );

        if ( $value !== false ) {
            if ( ( $value >= $minval ) && ( $value <= $maxval ) ) return true;
        }

        // Display error message
        $range = "{$minval} - {$maxval}";
        if ( $blank_ok ) $range .= ' or be left blank';
        add_settings_error( 'osmap_messages', 'osmap_msg', "<p><strong>{$label} '{$text}' is not a valid number</strong> Must lie in the range {$range}.</p>", 'error' );

        return false;
    }

    /**
     * Callback function used by the Settings API to validate all settings entered.
     */
    public function sanitize_cb($options) {
        $old_options = wp_parse_args( get_option( 'os_datahub_map_options' ), $this->default_options );
        $options['apikey'] = sanitize_text_field( trim( $options['apikey'] ) );
        $options['default_zoom'] = sanitize_text_field( trim( $options['default_zoom'] ) );
        $options['default_height'] = sanitize_text_field( trim( $options['default_height'] ) );
        $options['default_width'] = sanitize_text_field( trim( $options['default_width'] ) );
        $options['default_profile'] = sanitize_text_field( trim( $options['default_profile'] ) );
        $options['default_color'] = sanitize_text_field( trim( $options['default_color'] ) );
        $options['default_track'] = sanitize_text_field( trim( $options['default_track'] ) );
        $options['default_hover'] = isset( $options['default_hover'] );
        $options['default_waypoint'] = isset( $options['default_waypoint'] );
        $options['default_gestures'] = sanitize_text_field( trim( $options['default_gestures'] ) );
        $options['min_zoom'] = sanitize_text_field( trim( $options['min_zoom'] ) );
        $options['max_zoom'] = sanitize_text_field( trim( $options['max_zoom'] ) );
        $options['profile_fill'] = sanitize_text_field( trim( $options['profile_fill'] ) );
        $options['show_pane'] = isset( $options['show_pane'] );
        $options['imperial'] = isset( $options['imperial'] );
        $options['show_scale'] = isset( $options['show_scale'] );
        $options['add_link'] = isset( $options['add_link'] );
        // Sanitize zoom levels.
        if ( $options['min_zoom'] > $options['default_zoom'] ) {
            $options['min_zoom'] = $options['default_zoom'];
        }
        if ( $options['max_zoom'] < $options['default_zoom'] ) {
            $options['max_zoom'] = $options['default_zoom'];
        }
        // Store version number
        $options['version'] = OS_DATAHUB_MAPS_VERSION;

        // Now check for errors.
        // Check the API key.
        $options['apikey_state'] = $this->api_key_check( $options['apikey'] );
        if ( $options['apikey_state'] == 'bad') {
            add_settings_error( 'osmap_messages', 'osmap_msg', $this->bad_api_key_html, 'error' );
        } else if ( $options['apikey_state'] == 'none' ) {
            add_settings_error( 'osmap_messages', 'osmap_msg', $this->no_api_key_html, 'error' );
        }
        if ( ! $this->validate_as_number( 'Default height', $options['default_height'], 100, 5000, true ) ) {
            $options['default_height'] = $old_options['default_height'];
        }
        if ( ! $this->validate_as_number( 'Default width', $options['default_width'], 100, 5000, true ) ) {
            $options['default_width'] = $old_options['default_width'];
        }
        if ( ! $this->validate_as_number( 'Default profile height', $options['default_profile'], 100, 1000, true ) ) {
            $options['default_profile'] = $old_options['default_profile'];
        }
        if ( ! $this->validate_as_number( 'Default GPX/KML track width', $options['default_track'], 1, 15, false ) ) {
            $options['default_track'] = $old_options['default_track'];
        }
        if ( ! sanitize_hex_color( $options['default_color'] ) ) {
            add_settings_error( 'osmap_messages', 'osmap_msg', '<p><strong>Default track colour is invalid</strong> Must be a #hex-color-value eg. #884488</p>', 'error' );
            $options['default_color'] = $old_options['default_color'];
        }

        return $options;
    }

    /**
     * Code to display the settings page.
     */
    public function options_display() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
     
        // Show the settings page form
        $title = "OS Maps Settings";
    ?>
        <div class="wrap">
        <h2><?php echo esc_html($title);?></h2>
        <p>For further information please refer to the <a href="<?php echo self::documentation_url; ?>" target="_blank">plugin documentation</a></p>
        <form action="options.php" method="post">
    <?php
            // Output security fields for the registered setting "os_datahub_map_options"
            settings_fields( 'os-datahub-maps' );
            // Output setting sections and their fields
            do_settings_sections('os-datahub-maps');
            // Output save settings button
            submit_button( 'Save Settings' );
    ?>
        </form>
        </div>
    <?php
    }
}
