<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://gurkhatech.com
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
 * @author     Arjan KC <arjan@gurkhatech.com>
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
    include_once plugin_dir_path( __FILE__ ) . 'partials/gurkha-wp-import-admin-display.php';
    }

    /**
     * Handle the file upload and import process.
     *
     * @since    1.0.0
     */
    public function handle_file_upload() {
        if ( isset( $_POST['gurkha_wp_import_nonce'] ) && wp_verify_nonce( $_POST['gurkha_wp_import_nonce'], 'gurkha_wp_import' ) ) {
            $verbose = isset( $_POST['gwi_verbose'] );
            
            // Check for bulk upload (multiple files)
            if ( isset( $_FILES['zip_files'] ) && is_array( $_FILES['zip_files']['name'] ) ) {
                $this->handle_bulk_upload( $_FILES['zip_files'], $verbose );
                return;
            }
            
            // Handle single file upload
            if ( isset( $_FILES['zip_file'] ) ) {
                $file = $_FILES['zip_file'];
                $import_log = array();

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
                if ( $verbose ) { $import_log[] = 'Temp dir created: ' . $temp_dir; }

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
                if ( $verbose ) { $import_log[] = 'ZIP extracted: ' . basename( $file['name'] ); }

                // Discover content (HTML) and meta (JSON) files recursively
                $content_file = '';
                $meta_file = '';

                $html_candidates = array();
                $json_candidates = array();

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator( $temp_dir, RecursiveDirectoryIterator::SKIP_DOTS )
                );

                $featured_image_candidates = array();

                foreach ( $iterator as $fileinfo ) {
                    if ( ! $fileinfo->isFile() ) { continue; }
                    $ext = strtolower( $fileinfo->getExtension() );
                    $basename = strtolower( basename( $fileinfo->getPathname() ) );
                    
                    if ( in_array( $ext, array( 'html', 'htm' ), true ) ) {
                        $html_candidates[] = $fileinfo->getPathname();
                    } elseif ( 'json' === $ext ) {
                        $json_candidates[] = $fileinfo->getPathname();
                    } elseif ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ), true ) ) {
                        // Check if this could be a featured image
                        if ( strpos( $basename, 'featured-image' ) !== false ) {
                            $featured_image_candidates[] = $fileinfo->getPathname();
                        }
                    }
                }
                if ( $verbose ) {
                    $import_log[] = 'HTML candidates: ' . ( empty( $html_candidates ) ? 'none' : implode( ', ', array_map( 'basename', $html_candidates ) ) );
                    $import_log[] = 'JSON candidates: ' . ( empty( $json_candidates ) ? 'none' : implode( ', ', array_map( 'basename', $json_candidates ) ) );
                    $import_log[] = 'Featured image candidates: ' . ( empty( $featured_image_candidates ) ? 'none' : implode( ', ', array_map( 'basename', $featured_image_candidates ) ) );
                }

                // Pick HTML: prefer a file named like content/index, else largest by size, else first
                if ( ! empty( $html_candidates ) ) {
                    $preferred = array_filter( $html_candidates, function( $p ) {
                        $name = strtolower( basename( $p ) );
                        return ( $name === 'content.html' || $name === 'index.html' || $name === 'content.htm' || $name === 'index.htm' );
                    } );
                    if ( ! empty( $preferred ) ) {
                        $content_file = reset( $preferred );
                    } else {
                        // choose largest
                        usort( $html_candidates, function( $a, $b ) { return filesize( $b ) <=> filesize( $a ); } );
                        $content_file = $html_candidates[0];
                    }
                }
                if ( $verbose && ! empty( $content_file ) ) { $import_log[] = 'Selected HTML: ' . basename( $content_file ); }

                // Pick JSON: prefer one containing required keys
                if ( ! empty( $json_candidates ) ) {
                    foreach ( $json_candidates as $candidate ) {
                        $raw0 = @file_get_contents( $candidate );
                        if ( false === $raw0 ) { continue; }
                        // Try raw first
                        $data = json_decode( $raw0, true );
                        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) && isset( $data['metaTitle'], $data['slug'] ) ) {
                            $meta_file = $candidate;
                            break;
                        }
                        // Try sanitized
                        $raw1 = $this->sanitize_json( $raw0 );
                        $data = json_decode( $raw1, true );
                        if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) && isset( $data['metaTitle'], $data['slug'] ) ) {
                            $meta_file = $candidate;
                            break;
                        }
                    }
                    if ( empty( $meta_file ) ) {
                        // fallback: first JSON
                        $meta_file = $json_candidates[0];
                    }
                }
                if ( $verbose && ! empty( $meta_file ) ) { $import_log[] = 'Selected JSON: ' . basename( $meta_file ); }

                if ( empty( $content_file ) || empty( $meta_file ) ) {
                    $msg = 'Invalid zip archive. Could not find an HTML content file and a JSON metadata file.';
                    if ( empty( $content_file ) && ! empty( $html_candidates ) ) {
                        $msg .= ' Found HTML candidates: ' . esc_html( implode( ', ', array_map( 'basename', $html_candidates ) ) ) . '.';
                    }
                    if ( empty( $meta_file ) && ! empty( $json_candidates ) ) {
                        $msg .= ' Found JSON candidates: ' . esc_html( implode( ', ', array_map( 'basename', $json_candidates ) ) ) . '.';
                    }
                    wp_die( $msg );
                }

                // Process the files
                $post_id = $this->process_import( $temp_dir, $content_file, $meta_file, $featured_image_candidates, $import_log, $verbose );

                // Clean up the temporary directory
                $this->cleanup_temp_dir( $temp_dir );

                // Persist verbose import log if any
                if ( $verbose && ! empty( $post_id ) && ! empty( $import_log ) ) {
                    set_transient( 'gurkha_wp_import_import_log_' . $post_id, $import_log, HOUR_IN_SECONDS );
                }

                // Redirect to the success page
                $query = 'edit.php?page=' . $this->plugin_name . '&success=1&post_id=' . $post_id . ( $verbose ? '&verbose=1' : '' );
                wp_redirect( admin_url( $query ) );
                exit;
            }
        }
    }

    /**
     * Handle bulk file upload and import process.
     *
     * @since    1.0.0
     */
    private function handle_bulk_upload( $files, $verbose = false ) {
        $bulk_results = array();
        $file_count = count( $files['name'] );
        
        for ( $i = 0; $i < $file_count; $i++ ) {
            $file = array(
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i]
            );
            
            $result = array(
                'filename' => $file['name'],
                'success'  => false,
                'post_id'  => 0,
                'post_title' => '',
                'error'    => ''
            );
            
            try {
                // Skip empty slots
                if ( $file['error'] === UPLOAD_ERR_NO_FILE ) {
                    continue;
                }
                
                // Check for upload errors
                if ( $file['error'] !== UPLOAD_ERR_OK ) {
                    $result['error'] = 'Upload error: ' . $file['error'];
                    $bulk_results[] = $result;
                    continue;
                }
                
                // Check file type
                $file_type = wp_check_filetype( $file['name'] );
                if ( $file_type['ext'] !== 'zip' ) {
                    $result['error'] = 'Invalid file type. Must be .zip';
                    $bulk_results[] = $result;
                    continue;
                }
                
                // Process this file
                $post_id = $this->process_single_file( $file, $verbose );
                
                if ( $post_id > 0 ) {
                    $post = get_post( $post_id );
                    $result['success'] = true;
                    $result['post_id'] = $post_id;
                    $result['post_title'] = $post ? $post->post_title : 'Unknown';
                } else {
                    $result['error'] = 'Import failed';
                }
                
            } catch ( Exception $e ) {
                $result['error'] = $e->getMessage();
            }
            
            $bulk_results[] = $result;
        }
        
        // Store results for display
        set_transient( 'gurkha_wp_import_bulk_results', $bulk_results, HOUR_IN_SECONDS );
        
        // Redirect to results page
        wp_redirect( admin_url( 'edit.php?page=' . $this->plugin_name . '&bulk_success=1' ) );
        exit;
    }

    /**
     * Process a single file (extracted from handle_file_upload for reuse).
     *
     * @since    1.0.0
     */
    private function process_single_file( $file, $verbose = false ) {
        $import_log = array();
        
        // Create a temporary directory
        $temp_dir = trailingslashit( get_temp_dir() ) . uniqid( 'gurkha-wp-import-' );
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            throw new Exception( 'Could not create temporary directory.' );
        }
        if ( $verbose ) { $import_log[] = 'Temp dir created: ' . $temp_dir; }

        // Move the uploaded file to the temporary directory
        $uploaded_file = trailingslashit( $temp_dir ) . $file['name'];
        if ( ! move_uploaded_file( $file['tmp_name'], $uploaded_file ) ) {
            $this->cleanup_temp_dir( $temp_dir );
            throw new Exception( 'Could not move uploaded file.' );
        }

        // Extract the zip archive
        $zip = new ZipArchive();
        if ( $zip->open( $uploaded_file ) === TRUE ) {
            $zip->extractTo( $temp_dir );
            $zip->close();
        } else {
            $this->cleanup_temp_dir( $temp_dir );
            throw new Exception( 'Could not extract zip archive.' );
        }
        if ( $verbose ) { $import_log[] = 'ZIP extracted: ' . basename( $file['name'] ); }

        // Discover content files
        $html_candidates = array();
        $json_candidates = array();
        $featured_image_candidates = array();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $temp_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );

        foreach ( $iterator as $fileinfo ) {
            if ( ! $fileinfo->isFile() ) { continue; }
            $ext = strtolower( $fileinfo->getExtension() );
            $basename = strtolower( basename( $fileinfo->getPathname() ) );
            
            if ( in_array( $ext, array( 'html', 'htm' ), true ) ) {
                $html_candidates[] = $fileinfo->getPathname();
            } elseif ( 'json' === $ext ) {
                $json_candidates[] = $fileinfo->getPathname();
            } elseif ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ), true ) ) {
                // Check if this could be a featured image
                if ( strpos( $basename, 'featured-image' ) !== false ) {
                    $featured_image_candidates[] = $fileinfo->getPathname();
                }
            }
        }

        // Select best files
        $content_file = '';
        $meta_file = '';

        // Pick HTML
        if ( ! empty( $html_candidates ) ) {
            $preferred = array_filter( $html_candidates, function( $p ) {
                $name = strtolower( basename( $p ) );
                return ( $name === 'content.html' || $name === 'index.html' || $name === 'content.htm' || $name === 'index.htm' );
            } );
            if ( ! empty( $preferred ) ) {
                $content_file = reset( $preferred );
            } else {
                usort( $html_candidates, function( $a, $b ) { return filesize( $b ) <=> filesize( $a ); } );
                $content_file = $html_candidates[0];
            }
        }

        // Pick JSON
        if ( ! empty( $json_candidates ) ) {
            foreach ( $json_candidates as $candidate ) {
                $raw0 = @file_get_contents( $candidate );
                if ( false === $raw0 ) { continue; }
                $data = json_decode( $raw0, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) && isset( $data['metaTitle'], $data['slug'] ) ) {
                    $meta_file = $candidate;
                    break;
                }
                $raw1 = $this->sanitize_json( $raw0 );
                $data = json_decode( $raw1, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) && isset( $data['metaTitle'], $data['slug'] ) ) {
                    $meta_file = $candidate;
                    break;
                }
            }
            if ( empty( $meta_file ) ) {
                $meta_file = $json_candidates[0];
            }
        }

        if ( empty( $content_file ) || empty( $meta_file ) ) {
            $this->cleanup_temp_dir( $temp_dir );
            throw new Exception( 'Invalid zip archive. Missing HTML content or JSON metadata.' );
        }

        // Process the import
        $post_id = $this->process_import( $temp_dir, $content_file, $meta_file, $featured_image_candidates, $import_log, $verbose );

        // Clean up
        $this->cleanup_temp_dir( $temp_dir );

        return $post_id;
    }

    /**
     * Process the import.
     *
     * @since    1.0.0
     * @param    string    $temp_dir    The temporary directory where the files are located.
     */
    private function process_import( $temp_dir, $content_file, $meta_file, $featured_image_candidates = array(), &$import_log = array(), $verbose = false ) {
        // Load meta
        $meta_raw_original = file_get_contents( $meta_file );
        if ( $verbose ) { $import_log[] = 'Selected JSON file: ' . basename( $meta_file ) . ' (' . strlen( $meta_raw_original ) . ' bytes)'; }

        // Try decode raw
        $meta_data = json_decode( $meta_raw_original, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $meta_data ) ) {
            // Try sanitized
            $meta_raw = $this->sanitize_json( $meta_raw_original );
            if ( $verbose ) { $import_log[] = 'meta.json bytes (sanitized): ' . strlen( $meta_raw ); }
            $meta_data = json_decode( $meta_raw, true );
        } else {
            $meta_raw = $meta_raw_original;
        }

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $meta_data ) ) {
            // Fallback: attempt to extract JSON object between outer braces
            $braceStart = strpos( $meta_raw, '{' );
            $braceEnd = strrpos( $meta_raw, '}' );
            if ( $braceStart !== false && $braceEnd !== false && $braceEnd > $braceStart ) {
                $candidate = substr( $meta_raw, $braceStart, $braceEnd - $braceStart + 1 );
                $meta_data = json_decode( $candidate, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $meta_data ) ) {
                    if ( $verbose ) { $import_log[] = 'Parsed JSON via brace-extraction fallback.'; }
                }
            }
        }

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $meta_data ) ) {
            $snippet = substr( $meta_raw, 0, 200 );
            wp_die( 'Invalid meta.json in ' . esc_html( basename( $meta_file ) ) . ': ' . json_last_error_msg() . '\nSnippet: ' . esc_html( $snippet ) );
        }

        // Load content
    $content = file_get_contents( $content_file );
    if ( $verbose ) { $import_log[] = 'Loaded HTML: ' . basename( $content_file ) . ' (' . strlen( $content ) . ' bytes)'; }

        // Process images
        $doc = new DOMDocument();
        @$doc->loadHTML( mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

    $images = $doc->getElementsByTagName( 'img' );
    $image_log = array();
    $image_total = $images->length;
    if ( $verbose ) { $import_log[] = 'Images found in HTML: ' . $image_total; }

    foreach ( $images as $image ) {
            $src = $image->getAttribute( 'src' );

            // Check if the image is local
            if ( strpos( $src, 'http' ) !== 0 ) {
                $image_path = trailingslashit( $temp_dir ) . $src;

                if ( file_exists( $image_path ) ) {
                    $upload = wp_upload_bits( basename( $image_path ), null, file_get_contents( $image_path ) );

            if ( ! $upload['error'] ) {
                        $attachment = array(
                // Determine MIME type from uploaded file path
                'post_mime_type' => wp_check_filetype( $upload['file'] )['type'] ?? '',
                'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $image_path ) ),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        );

                        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

                        if ( ! is_wp_error( $attachment_id ) ) {
                            require_once( ABSPATH . 'wp-admin/includes/image.php' );
                            $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
                            wp_update_attachment_metadata( $attachment_id, $attachment_data );

                            $image->setAttribute( 'src', $upload['url'] );
                            $image_log[] = "Image '{$src}' uploaded successfully.";
                            if ( $verbose ) { $import_log[] = "Replaced image src '{$src}' → '{$upload['url']}'"; }
                        } else {
                            $image_log[] = "Error creating attachment for image '{$src}'.";
                            if ( $verbose ) { $import_log[] = "Attachment error for image '{$src}'"; }
                        }
                    } else {
                        $image_log[] = "Error uploading image '{$src}': " . $upload['error'];
                        if ( $verbose ) { $import_log[] = "Upload error for image '{$src}': " . $upload['error']; }
                    }
                } else {
                    $image_log[] = "Image '{$src}' not found in the zip file.";
                    if ( $verbose ) { $import_log[] = "Image not found in bundle: '{$src}'"; }
                }
            }
        }

        $content = $doc->saveHTML();

        // Get next available publish date
        $publish_date = $this->get_next_available_publish_date();
        if ( $verbose ) { $import_log[] = 'Scheduled publish date: ' . $publish_date; }

        // Create post
        $post_data = array(
            'post_title'   => $meta_data['metaTitle'],
            'post_content' => $content,
            'post_name'    => $meta_data['slug'],
            'post_status'  => 'future',
            'post_date'    => $publish_date,
            'post_author'  => get_current_user_id(),
        );

    $post_id = wp_insert_post( $post_data );
    if ( $verbose ) { $import_log[] = 'Inserted post ID: ' . ( is_wp_error( $post_id ) ? 'ERROR' : $post_id ); }

    if ( ! is_wp_error( $post_id ) ) {
            // Set tags
            if ( isset( $meta_data['tags'] ) ) {
                wp_set_post_tags( $post_id, $meta_data['tags'] );
                if ( $verbose ) { $import_log[] = 'Tags applied: ' . implode( ', ', $meta_data['tags'] ); }
            }

            // Set RankMath meta
            if ( isset( $meta_data['metaDescription'] ) ) {
                update_post_meta( $post_id, 'rank_math_description', $meta_data['metaDescription'] );
                if ( $verbose ) { $import_log[] = 'RankMath description set (' . strlen( $meta_data['metaDescription'] ) . ' chars)'; }
            }

            if ( isset( $meta_data['focusKeywords'] ) ) {
                update_post_meta( $post_id, 'rank_math_focus_keyword', implode( ', ', $meta_data['focusKeywords'] ) );
                if ( $verbose ) { $import_log[] = 'RankMath focus keywords set (' . count( (array) $meta_data['focusKeywords'] ) . ' items)'; }
            }

            // Set featured image if available
            if ( ! empty( $featured_image_candidates ) ) {
                $featured_image_path = $featured_image_candidates[0]; // Use first match
                if ( file_exists( $featured_image_path ) ) {
                    $featured_upload = wp_upload_bits( basename( $featured_image_path ), null, file_get_contents( $featured_image_path ) );
                    
                    if ( ! $featured_upload['error'] ) {
                        $featured_attachment = array(
                            'post_mime_type' => wp_check_filetype( $featured_upload['file'] )['type'] ?? '',
                            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $featured_image_path ) ),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        );

                        $featured_attachment_id = wp_insert_attachment( $featured_attachment, $featured_upload['file'] );

                        if ( ! is_wp_error( $featured_attachment_id ) ) {
                            require_once( ABSPATH . 'wp-admin/includes/image.php' );
                            $featured_attachment_data = wp_generate_attachment_metadata( $featured_attachment_id, $featured_upload['file'] );
                            wp_update_attachment_metadata( $featured_attachment_id, $featured_attachment_data );

                            // Set as featured image
                            set_post_thumbnail( $post_id, $featured_attachment_id );
                            if ( $verbose ) { $import_log[] = 'Featured image set: ' . basename( $featured_image_path ); }
                        } else {
                            if ( $verbose ) { $import_log[] = 'Error creating featured image attachment: ' . basename( $featured_image_path ); }
                        }
                    } else {
                        if ( $verbose ) { $import_log[] = 'Error uploading featured image: ' . $featured_upload['error']; }
                    }
                }
            }

            // Persist image log for preview screen
            if ( ! empty( $image_log ) ) {
                set_transient( 'gurkha_wp_import_image_log_' . $post_id, $image_log, HOUR_IN_SECONDS );
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

    /**
     * Sanitize JSON string to improve compatibility:
     * - Remove UTF-8 BOM
     * - Strip XML CDATA and fenced code blocks
     * - Normalize smart quotes to ASCII quotes
     * - Remove trailing commas in objects/arrays
     * - Trim control chars/whitespace
     *
     * @param string $raw
     * @return string
     */
    private function sanitize_json( $raw ) {
        if ( ! is_string( $raw ) ) { return ''; }

        // Normalize encoding to UTF-8 when possible
        if ( function_exists( 'mb_detect_encoding' ) && function_exists( 'mb_convert_encoding' ) ) {
            $enc = @mb_detect_encoding( $raw, 'UTF-8, UTF-16LE, UTF-16BE, UTF-32LE, UTF-32BE, Windows-1252, ISO-8859-1', true );
            if ( $enc && strtoupper( $enc ) !== 'UTF-8' ) {
                $raw = @mb_convert_encoding( $raw, 'UTF-8', $enc );
            }
        }

        // Remove BOMs (UTF-8/UTF-16/UTF-32)
        $raw = preg_replace( "/^\xEF\xBB\xBF|^\xFE\xFF|^\xFF\xFE|^\x00\x00\xFE\xFF|^\xFF\xFE\x00\x00/", '', $raw );

        // Normalize line endings
        $raw = str_replace( array("\r\n", "\r"), "\n", $raw );

        // Strip leading control chars
        $raw = preg_replace( '/^[\x00-\x20\x{FEFF}]+/u', '', $raw );

        // Strip XML CDATA wrapper
        if ( preg_match( '/^\s*<!\[CDATA\[(.*)\]\]>\s*$/s', $raw, $m ) ) {
            $raw = $m[1];
        }

        // Strip fenced code blocks ```json ... ```
        if ( preg_match( '/^\s*```/m', $raw ) ) {
            if ( preg_match( '/^\s*```[a-zA-Z]*\s*(.*?)\s*```\s*$/s', $raw, $fm ) ) {
                $raw = $fm[1];
            }
        }

        // If JSON has JS-style comments, remove them conservatively
        if ( strpos( $raw, '//' ) !== false || strpos( $raw, '/*' ) !== false ) {
            // Remove /* ... */ block comments
            $raw = preg_replace( '#/\*.*?\*/#s', '', $raw );
            // Remove // line comments (not perfect if inside strings, but helps common cases)
            $raw = preg_replace( '#(^|[\s\{\[,])//.*$#m', '$1', $raw );
        }

        // Replace curly quotes with straight quotes
            $raw = str_replace(
                array("\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99"),
                array('"', '"', '\'', '\''),
                $raw
            );

        // Remove trailing commas in objects and arrays
    $raw = preg_replace( '/,\s*([}\]])/', '$1', $raw );

        return trim( $raw );
    }

    /**
     * Find the next available date without scheduled posts.
     *
     * @since    1.0.0
     * @return   string  MySQL datetime format
     */
    private function get_next_available_publish_date() {
        global $wpdb;
        
        // Start from today
        $current_date = new DateTime();
        $max_attempts = 365; // Don't search more than a year ahead
        $attempt = 0;
        
        while ( $attempt < $max_attempts ) {
            $date_string = $current_date->format( 'Y-m-d' );
            
            // Check if any posts are scheduled for this date
            $scheduled_count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_status IN ('publish', 'future') 
                AND DATE(post_date) = %s 
                AND post_type = 'post'",
                $date_string
            ) );
            
            // If no posts scheduled for this date, use it
            if ( $scheduled_count == 0 ) {
                // Generate random time between 9 AM and 6 PM
                $hour = wp_rand( 9, 18 );
                $minute = wp_rand( 0, 59 );
                $second = wp_rand( 0, 59 );
                
                $current_date->setTime( $hour, $minute, $second );
                return $current_date->format( 'Y-m-d H:i:s' );
            }
            
            // Move to next day
            $current_date->add( new DateInterval( 'P1D' ) );
            $attempt++;
        }
        
        // Fallback: if we can't find an empty day, schedule for tomorrow with random time
        $fallback_date = new DateTime( '+1 day' );
        $hour = wp_rand( 9, 18 );
        $minute = wp_rand( 0, 59 );
        $second = wp_rand( 0, 59 );
        $fallback_date->setTime( $hour, $minute, $second );
        
        return $fallback_date->format( 'Y-m-d H:i:s' );
    }

}
