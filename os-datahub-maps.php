<?php  

/**
 * Plugin Name:       OS DataHub Maps
 * Plugin URI:        https://skirridsystems.co.uk/os-datahub-maps/
 * Description:       Plugin for displaying OS Maps using the Data Hub Maps API.
 * Version:           1.8.0
 * Author:            Simon Large
 * Author             URI: https://skirridsystems.co.uk/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Plugin version used for cache busting
 */
define( 'OS_DATAHUB_MAPS_VERSION', '1.8.0' );

/**
 * This code runs when the plugin is activated.
 */
function os_datahub_maps_on_activate() {
    set_transient( 'osmap-activation-check', true, 5 );
}

/**
 * This code runs when the plugin is deleted.
 */
function os_datahub_maps_on_uninstall() {
    // Delete our options from the database.
    delete_option( 'os_datahub_map_options' );
    delete_site_option( 'os_datahub_map_options' );
}

register_activation_hook( __FILE__, 'os_datahub_maps_on_activate' );
register_uninstall_hook( __FILE__, 'os_datahub_maps_on_uninstall' );


/**
 * This is the main plugin class.
 * Most of the work is done in worker classes.
 */
class OS_DataHub_Maps {

    /**
     * Default values for plugin settings.
     */
    private $default_options = [
        'apikey'            => '',
        'default_zoom'      => 7,
        'default_height'    => 400,
        'default_width'     => '',
        'default_profile'   => '',
        'default_color'     => '#3366cc',
        'default_track'     => 4,
        'default_hover'     => false,
        'default_waypoint'  => true,
        'default_gestures'  => true,
        'default_location'  => 'none',
        'premium_data'      => 'all',
        'open_data_style'   => 'Outdoor',
        'min_zoom'          => 2,
        'max_zoom'          => 11,
        'zoom_step'         => 1,
        'fullscreen'        => 'all',
        'profile_fill'      => '#3366cc',
        'pan_anywhere'      => 'all',
        'can_print'         => 'none',
        'show_pane'         => false,
        'imperial'          => false,
        'show_scale'        => false,
        'add_link'          => false,
        'version'           => OS_DATAHUB_MAPS_VERSION
    ];

    /**
     * Class constructor.
     * Read the plugin options, set on the admin page.
     */
    public function __construct() {

        if ( is_admin() ) {
            // Called in the backend, providing the settings page.
            require_once( plugin_dir_path( __FILE__ ) . 'include/osmap-admin.php' );
            new OS_DataHub_Maps_Admin( $this->default_options );
        } else {
            // Called in the frontend when displaying pages.
            require_once( plugin_dir_path( __FILE__ ) . 'include/osmap-shortcode.php' );
            require_once( plugin_dir_path( __FILE__ ) . 'include/osmap-script.php' );
            new OS_DataHub_Maps_Shortcode( $this->default_options );
        }
    }

}

/**
 * Begins execution of the plugin.
 */
function os_datahub_maps_start() {
    new OS_DataHub_Maps();
}
os_datahub_maps_start();
