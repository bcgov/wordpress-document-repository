<?php
/**
 * Plugin Name: WordPress Document Repository
 * Plugin URI: https://github.com/bcgov/wordpress-document-repository
 * Author: govwordpress@gov.bc.ca
 * Author URI: https://citz-gdx.atlassian.net/browse/DSWP-225
 * Description: WordPress Document Repository plugin is a plugin that enhances the ability to upload and manage
 * documents on your WordPress site.
 * Requires at least: 6.4.4
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Version: 1.0.0
 * License: Apache License Version 2.0
 * License URI: LICENSE
 * Text Domain: wordpress-document-repository
 * Tags:
 *
 * @package WordpressDocumentRepository
 */

 // Ensure WordPress is loaded.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Bcgov\WordpressDocumentRepository\{
    DocumentRepository,
    Settings,
};

$local_composer = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $local_composer ) ) {
    require_once $local_composer;
}

if ( ! class_exists( 'Bcgov\\WordpressDocumentRepository\\Settings' ) ) {
    add_action(
        'admin_notices',
        function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'WordPress Document Repository plugin error: Autoloading failed. Please run composer install in the plugin directory.', 'wordpress-document-repository' );
			echo '</p></div>';
		}
    );
    return;
}

/**
 * Initialize the plugin
 *
 * This function is called when the plugin is loaded.
 */
function wordpress_document_repository_init() {
}

// Hook the function into the 'init' action.
add_action( 'init', 'wordpress_document_repository_init' );


// Initialize the Document Repository Settings.
$document_repository_settings = new Settings();
$document_repository_settings->init();

// Initialize Document Repository.
$document_repository = new DocumentRepository();

// Always register taxonomies first (needed in both admin and frontend).
add_action( 'init', [ $document_repository, 'register_post_types' ] );
add_action( 'init', [ $document_repository, 'register_metadata_taxonomies' ], 15 );

// For admin area, initialize everything.
if ( is_admin() ) {
    $document_repository->init();
} else { // For frontend, initialize only frontend-specific features.
    $document_repository->init_frontend();
}

// Override document search results.
add_filter( 'post_type_link', 'wordpress_document_repository_override_permalink', 10, 2 );
// Append file type and size to document titles in search results.
add_filter( 'the_title', 'wordpress_document_repository_append_file_info', 10, 2 );

// Automatically publish documents when restored from trash.
add_action( 'wp_insert_post', 'wordpress_document_repository_force_publish_after_untrash', 10, 3 );


/**
 * Override document post permalink in search results with the direct file URL.
 *
 * @param string  $post_link The default post permalink.
 * @param WP_Post $post      The current post object.
 * @return string             The modified or original permalink.
 */
function wordpress_document_repository_override_permalink( $post_link, $post ) {
    if ( is_search() && isset( $post->post_type ) && 'document' === $post->post_type ) {
        $file_id = get_post_meta( $post->ID, 'document_file_id', true );
        if ( $file_id ) {
            $file_path = get_attached_file( $file_id );
            $file_url  = wp_get_attachment_url( $file_id );

            if ( $file_path && false !== strpos( $file_path, '/documents/' ) ) {
                $upload_dir = wp_upload_dir();
                $file_url   = $upload_dir['baseurl'] . '/documents/' . basename( $file_path );
            }

            if ( ! empty( $file_url ) ) {
                return esc_url( $file_url );
            }
        }
    }
    return $post_link;
}

/**
 * Append file type and size to document titles in search results.
 *
 * Formats the document title to include the file extension in uppercase
 * and the file size with one decimal precision, using KB for sizes under
 * 1 MB and MB for sizes 1 MB or larger.
 *
 * If either the file type or size cannot be determined, the brackets are omitted.
 * Applies only to 'document' post types in search results.
 *
 * @param string $title   The original post title.
 * @param int    $post_id The current post ID.
 * @return string          The modified or original title.
 */
function wordpress_document_repository_append_file_info( $title, $post_id ) {
    if ( is_search() && 'document' === get_post_type( $post_id ) ) {
        $file_id = get_post_meta( $post_id, 'document_file_id', true );
        if ( $file_id ) {
            $file_path = get_attached_file( $file_id );
            if ( $file_path && file_exists( $file_path ) ) {
                $file_type = strtoupper( pathinfo( $file_path, PATHINFO_EXTENSION ) );

                $size_bytes          = filesize( $file_path );
                $file_size_formatted = '';

                // Convert the file size to MB or KB as appropriate.
                if ( $size_bytes > 0 ) {
                    if ( $size_bytes >= 1048576 ) {
                        $file_size_formatted = number_format_i18n( $size_bytes / 1048576, 1 ) . 'MB';
                    } else {
                        $file_size_formatted = number_format_i18n( $size_bytes / 1024, 1 ) . 'KB';
                    }
                }

                // Add the file type and size to the title, if available.
                $info_parts = [];
                if ( $file_type ) {
                    $info_parts[] = $file_type;
                }
                if ( $file_size_formatted ) {
                    $info_parts[] = $file_size_formatted;
                }
                if ( ! empty( $info_parts ) ) {
                    $title .= ' (' . implode( ', ', $info_parts ) . ')';
                }
            }
        }
    }
    return $title;
}

/**
 * Force post status to 'publish' after untrashing.
 *
 * @param int     $post_ID Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function wordpress_document_repository_force_publish_after_untrash( $post_ID, $post, $update ) {
	if ( 'document' === $post->post_type && $update ) {
		// Check if this was just untrashed by looking at the post status.
		$current_status = get_post_status( $post_ID );
		if ( 'draft' === $current_status ) {
			// Force it to publish.
			wp_update_post(
                array(
					'ID'          => $post_ID,
					'post_status' => 'publish',
                )
            );
		}
	}
}
