<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://yourwebsite.com
 * @since      1.0.0
 *
 * @package    Gurkha_WP_Import
 * @subpackage Gurkha_WP_Import/admin/partials
 */
?>

<div class="wrap">

    <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

    <?php
    // Handle bulk import results
    if ( isset( $_GET['bulk_success'] ) && $_GET['bulk_success'] == 1 ) {
        $bulk_results = get_transient( 'gurkha_wp_import_bulk_results' );
        if ( $bulk_results ) {
            echo '<div class="updated"><p>Bulk import completed! Processed ' . count( $bulk_results ) . ' files.</p></div>';
            
            echo '<h3>Bulk Import Results</h3>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>File</th><th>Status</th><th>Post</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            foreach ( $bulk_results as $result ) {
                echo '<tr>';
                echo '<td>' . esc_html( $result['filename'] ) . '</td>';
                
                if ( $result['success'] ) {
                    echo '<td><span style="color: green;">✓ Success</span></td>';
                    echo '<td>' . esc_html( $result['post_title'] ) . '</td>';
                    echo '<td><a href="' . get_edit_post_link( $result['post_id'] ) . '" target="_blank">Edit Post</a></td>';
                } else {
                    echo '<td><span style="color: red;">✗ Failed</span></td>';
                    echo '<td colspan="2">' . esc_html( $result['error'] ) . '</td>';
                }
                
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            delete_transient( 'gurkha_wp_import_bulk_results' );
        }
    }
    
    // Handle single import results  
    if ( isset( $_GET['success'] ) && $_GET['success'] == 1 ) {
        echo '<div class="updated"><p>Post imported successfully!</p></div>';

        if ( isset( $_GET['post_id'] ) ) {
            $post_id = intval( $_GET['post_id'] );
            $post = get_post( $post_id );

            if ( $post ) {
                echo '<h3>Import Details</h3>';
                echo '<p><strong>Title:</strong> ' . esc_html( $post->post_title ) . '</p>';
                echo '<p><strong>Slug:</strong> ' . esc_html( $post->post_name ) . '</p>';

                $tags = get_the_tags( $post_id );
                if ( $tags ) {
                    $tag_names = array();
                    foreach ( $tags as $tag ) {
                        $tag_names[] = $tag->name;
                    }
                    echo '<p><strong>Tags:</strong> ' . esc_html( implode( ', ', $tag_names ) ) . '</p>';
                }

                echo '<p><a href="' . get_edit_post_link( $post_id ) . '" target="_blank">Edit Post</a></p>';

                $image_log = get_transient( 'gurkha_wp_import_image_log_' . $post_id );
                if ( $image_log ) {
                    echo '<h4>Image Import Log</h4>';
                    echo '<ul>';
                    foreach ( $image_log as $log ) {
                        echo '<li>' . esc_html( $log ) . '</li>';
                    }
                    echo '</ul>';
                    delete_transient( 'gurkha_wp_import_image_log_' . $post_id );
                }

                if ( isset( $_GET['verbose'] ) && $_GET['verbose'] == '1' ) {
                    $import_log = get_transient( 'gurkha_wp_import_import_log_' . $post_id );
                    if ( $import_log ) {
                        echo '<h4>Verbose Import Log</h4>';
                        echo '<ol style="max-height: 300px; overflow:auto; background:#fff; padding:10px; border:1px solid #ccd0d4;">';
                        foreach ( $import_log as $log ) {
                            echo '<li>' . esc_html( $log ) . '</li>';
                        }
                        echo '</ol>';
                        delete_transient( 'gurkha_wp_import_import_log_' . $post_id );
                    }
                }
            }
        }
    }
    ?>

    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field( 'gurkha_wp_import', 'gurkha_wp_import_nonce' ); ?>
        
        <h3>Single File Import</h3>
        <p>
            <label for="zip_file">Select a .zip file to upload:</label>
            <input type="file" id="zip_file" name="zip_file" accept=".zip">
        </p>
        
        <h3>Bulk Import</h3>
        <p>
            <label for="zip_files">Select multiple .zip files to upload:</label>
            <input type="file" id="zip_files" name="zip_files[]" accept=".zip" multiple>
        </p>
        
        <p>
            <label>
                <input type="checkbox" name="gwi_verbose" value="1" /> Verbose logging
            </label>
        </p>
        
        <?php submit_button( 'Upload and Import' ); ?>
    </form>

</div>
