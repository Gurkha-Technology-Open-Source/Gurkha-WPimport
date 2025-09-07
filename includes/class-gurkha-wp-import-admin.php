<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Gurkha_WP_Import
 * @subpackage Gurkha_WP_Import/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Gurkha_WP_Import
 * @subpackage Gurkha_WP_Import/admin
 * @author     Your Name <email@example.com>
 */
class Gurkha_WP_Import_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Gurkha_WP_Import_Loader as all of the hooks are defined
         * in that particular class._Admin
         *
         * The Gurkha_WP_Import_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/gurkha-wp-import-admin.css', array(), $this->version, 'all' );

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Gurkha_WP_Import_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Gurkha_WP_Import_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/gurkha-wp-import-admin.js', array( 'jquery' ), $this->version, false );

    }

    /**
     * Add the admin menu for the plugin.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {

        add_submenu_page(
            'edit.php',
            'Gurkha WP Import',
            'Import Bundle',
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_plugin_setup_page' )
        );
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {
        include_once( 'partials/gurkha-wp-import-admin-display.php' );
    }

    /**
     * Handle the file upload and import process.
     *
     * @since    1.0.0
     */
    public function handle_file_upload() {
        if ( isset( $_POST['gurkha_wp_import_nonce'] ) && wp_verify_nonce( $_POST['gurkha_wp_import_nonce'], 'gurkha_wp_import' ) ) {
            if ( isset( $_FILES['zip_file'] ) ) {
                $file = $_FILES['zip_file'];

                // Check for errors
                if ( $file['error'] !== UPLOAD_ERR_OK ) {
                    // Handle error
                    wp_die( 'File upload error: ' . $file['error'] );
                }

                // Check file type
                $file_type = wp_check_filetype( $file['name'] );
                if ( $file_type['ext'] !== 'zip' ) {
                    wp_die( 'Invalid file type. Please upload a .zip file.' );
                }

                // Create a temporary directory
                $temp_dir = trailingslashit( get_temp_dir() ) . uniqid( 'gurkha-wp-import-' );
                if ( ! wp_mkdir_p( $temp_dir ) ) {
                    wp_die( 'Could not create temporary directory.' );
                }

                // Move the uploaded file to the temporary directory
                $uploaded_file = trailingslashit( $temp_dir ) . $file['name'];
                if ( ! move_uploaded_file( $file['tmp_name'], $uploaded_file ) ) {
                    wp_die( 'Could not move uploaded file.' );
                }

                // Extract the zip archive
                $zip = new ZipArchive();
                if ( $zip->open( $uploaded_file ) === TRUE ) {
                    $zip->extractTo( $temp_dir );
                    $zip->close();
                } else {
                    wp_die( 'Could not extract zip archive.' );
                }

                // Validate the extracted files
                $content_file = trailingslashit( $temp_dir ) . 'content.html';
                $meta_file = trailingslashit( $temp_dir ) . 'meta.json';

                if ( ! file_exists( $content_file ) || ! file_exists( $meta_file ) ) {
                    wp_die( 'Invalid zip archive. Missing content.html or meta.json.' );
                }

                // Process the files
                $post_id = $this->process_import( $temp_dir );

                // Clean up the temporary directory
                $this->cleanup_temp_dir( $temp_dir );

                // Redirect to the success page
                wp_redirect( admin_url( 'edit.php?page=' . $this->plugin_name . '&success=1&post_id=' . $post_id ) );
                exit;
            }
        }
    }

    /**
     * Process the import.
     *
     * @since    1.0.0
     * @param    string    $temp_dir    The temporary directory where the files are located.
     */
    private function process_import( $temp_dir ) {
        $meta_file = trailingslashit( $temp_dir ) . 'meta.json';
        $content_file = trailingslashit( $temp_dir ) . 'content.html';

        $meta_data = json_decode( file_get_contents( $meta_file ), true );
        $content = file_get_contents( $content_file );

        // Process images
        $doc = new DOMDocument();
        @$doc->loadHTML( mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        $images = $doc->getElementsByTagName( 'img' );

        foreach ( $images as $image ) {
            $src = $image->getAttribute( 'src' );

            // Check if the image is local
            if ( strpos( $src, 'http' ) !== 0 ) {
                $image_path = trailingslashit( $temp_dir ) . $src;

                if ( file_exists( $image_path ) ) {
                    $upload = wp_upload_bits( basename( $image_path ), null, file_get_contents( $image_path ) );

                    if ( ! $upload['error'] ) {
                        $attachment = array(
                            'post_mime_type' => $upload['type'],
                            'post_title'     => preg_replace( '/.[^.]+$/', '', basename( $image_path ) ),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        );

                        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

                        if ( ! is_wp_error( $attachment_id ) ) {
                            require_once( ABSPATH . 'wp-admin/includes/image.php' );
                            $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
                            wp_update_attachment_metadata( $attachment_id, $attachment_data );

                            $image->setAttribute( 'src', $upload['url'] );
                            $image_log[] = 'Image '' . $src . '' uploaded successfully.';
                        } else {
                            $image_log[] = 'Error creating attachment for image '' . $src . ''.';
                        }
                    } else {
                        $image_log[] = 'Error uploading image '' . $src . '': ' . $upload['error'];
                    }
                } else {
                    $image_log[] = 'Image '' . $src . '' not found in the zip file.';
                }
            }
        }

        $content = $doc->saveHTML();

        // Create post
        $post_data = array(
            'post_title'   => $meta_data['metaTitle'],
            'post_content' => $content,
            'post_name'    => $meta_data['slug'],
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
        );

        $post_id = wp_insert_post( $post_data );

        if ( ! is_wp_error( $post_id ) ) {
            // Set tags
            if ( isset( $meta_data['tags'] ) ) {
                wp_set_post_tags( $post_id, $meta_data['tags'] );
            }

            // Set RankMath meta
            if ( isset( $meta_data['metaDescription'] ) ) {
                update_post_meta( $post_id, 'rank_math_description', $meta_data['metaDescription'] );
            }

            if ( isset( $meta_data['focusKeywords'] ) ) {
                update_post_meta( $post_id, 'rank_math_focus_keyword', implode( ', ', $meta_data['focusKeywords'] ) );
            }

            return $post_id;
        }

        return 0;
    }

    /**
     * Clean up the temporary directory.
     *
     * @since    1.0.0
     * @param    string    $temp_dir    The temporary directory to clean up.
     */
    private function cleanup_temp_dir( $temp_dir ) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $temp_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $files as $fileinfo ) {
            $todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
            $todo( $fileinfo->getRealPath() );
        }

        rmdir( $temp_dir );
    }

}
