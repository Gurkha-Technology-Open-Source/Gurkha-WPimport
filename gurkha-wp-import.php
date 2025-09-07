<?php
/**
 * Plugin Name: Gurkha WP Import
 * Plugin URI: https://github.com/arjan-gurkha/Gurkha-WPimport
 * Description: A plugin to import blog posts from a zip bundle.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include the main plugin class.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-gurkha-wp-import.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_gurkha_wp_import() {

    $plugin = new Gurkha_WP_Import();
    $plugin->run();

}
run_gurkha_wp_import();
