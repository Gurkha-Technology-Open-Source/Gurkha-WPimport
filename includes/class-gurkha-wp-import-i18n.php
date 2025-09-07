<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Gurkha_WP_Import
 * @subpackage Gurkha_WP_Import/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Gurkha_WP_Import
 * @subpackage Gurkha_WP_Import/includes
 * @author     Your Name <email@example.com>
 */
class Gurkha_WP_Import_i18n {


    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {

        load_plugin_textdomain(
            'gurkha-wp-import',
            false,
            dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
        );

    }



}
