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
		$file_url = get_post_meta( $post->ID, 'document_file_url', true );
		if ( ! empty( $file_url ) ) {
			return esc_url( $file_url );
		}
	}
	return $post_link;
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
