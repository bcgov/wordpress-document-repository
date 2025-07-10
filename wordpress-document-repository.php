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

// For admin area, initialize everything.
if ( is_admin() ) {
    $document_repository->init();
} else { // For frontend, initialize only frontend-specific features.
    $document_repository->init_frontend();
}
