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
            }
        }
    }
    ?>

    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field( 'gurkha_wp_import', 'gurkha_wp_import_nonce' ); ?>
        <p>
            <label for="zip_file">Select a .zip file to upload:</label>
            <input type="file" id="zip_file" name="zip_file" accept=".zip">
        </p>
        <?php submit_button( 'Upload and Import' ); ?>
    </form>

</div>
