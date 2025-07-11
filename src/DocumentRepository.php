<?php

namespace Bcgov\WordpressDocumentRepository;

use Bcgov\WordpressDocumentRepository\{
    RepositoryConfig,
    DocumentPostType,
    DocumentUploader,
    AdminUIManager,
    RestApiController,
    DocumentMetadataManager,
};

/**
 * DocumentRepository - Main Plugin Class
 *
 * This class serves as the central coordinator for the Document Repository system.
 * It manages service instantiation, WordPress integration, and overall architecture.
 */
class DocumentRepository {
    /**
     * Configuration instance.
     *
     * @var RepositoryConfig
     */
    private RepositoryConfig $config;

    /**
     * Document post type service instance.
     *
     * @var DocumentPostType|null
     */
    private ?DocumentPostType $post_type = null;

    /**
     * Document uploader service instance.
     *
     * @var DocumentUploader|null
     */
    private ?DocumentUploader $uploader = null;

    /**
     * Admin UI manager service instance.
     *
     * @var AdminUIManager|null
     */
    private ?AdminUIManager $admin_ui = null;

    /**
     * REST API controller service instance.
     *
     * @var RestApiController|null
     */
    private ?RestApiController $rest_api = null;

    /**
     * Metadata manager service instance.
     *
     * @var DocumentMetadataManager|null
     */
    private ?DocumentMetadataManager $metadata_manager = null;

    /**
     * Constructor.
     *
     * @param RepositoryConfig|null $config Optional config instance for dependency injection.
     */
    public function __construct( RepositoryConfig $config = null ) {
        // Initialize configuration or use provided one.
        $this->config = $config ?? new RepositoryConfig();
    }

    /**
     * Initialize frontend-specific functionality.
     * This method is called when the plugin runs on the frontend (not admin).
     *
     * @return void
     */
    public function init_frontend(): void {
        // Note: Post types and taxonomies are registered in the main plugin file.
        // to avoid duplicate registrations between admin and frontend.

        // Register REST routes for frontend access if needed.
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 10 );

        // Hook to re-register metadata fields when they are updated (frontend).
        add_action( 'bcgov_document_repository_metadata_fields_updated', [ $this->get_document_post_type_instance(), 'register_metadata_fields' ] );
        add_action( 'bcgov_document_repository_metadata_fields_updated', [ $this, 'register_metadata_taxonomies' ] );
    }

    /**
     * Initialize the plugin by registering WordPress hooks and integrations.
     * This method is called when the plugin runs in the admin area.
     *
     * @return void
     */
    public function init(): void {
        // Note: Post types and taxonomies are registered in the main plugin file.
        // to avoid duplicate registrations between admin and frontend.

        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 10 );
        add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this->get_admin_ui_manager_instance(), 'enqueue_admin_scripts' ] );

        // Event listeners.
        add_action(
            'bcgov_document_repository_document_uploaded',
            [ $this->get_metadata_manager_instance(), 'clear_cache' ]
        );

        // Hook to re-register metadata fields when they are updated.
        add_action( 'bcgov_document_repository_metadata_fields_updated', [ $this->get_document_post_type_instance(), 'register_metadata_fields' ] );
        add_action( 'bcgov_document_repository_metadata_fields_updated', [ $this, 'register_metadata_taxonomies' ] );

        // Migrate existing files to the new direct path structure.
        add_action( 'admin_init', [ $this, 'migrate_existing_files' ] );
    }

    /**
     * Register metadata taxonomies.
     * This is called on the init hook with priority 15 to ensure post types are registered first.
     */
    public function register_metadata_taxonomies(): void {
        $this->get_document_post_type_instance()->register_metadata_taxonomies();
    }

    /**
     * Register custom post type for documents.
     */
    public function register_post_types(): void {
        $this->get_document_post_type_instance()->register();
    }

    /**
     * Log a debug message if WP_DEBUG is enabled.
     *
     * @param string $message The message to log.
     * @param string $level The log level (default: 'debug').
     * @return void
     */
    private function log( string $message, string $level = 'debug' ): void {
        // In production, we disable all logging.
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'production' ) {
            return;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( function_exists( 'wp_debug_log' ) ) {
                wp_debug_log( $message, $level );
            } elseif ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                // Fallback for older WordPress versions.
                $this->log_to_error_log( $message, $level );
            }
        }
    }

    /**
     * Log a message using WordPress's built-in logging functions.
     *
     * @param string $message The message to log.
     * @param string $level The log level.
     * @return void
     */
    private function log_to_error_log( string $message, string $level ): void {
        if ( function_exists( 'wp_debug_log' ) ) {
            wp_debug_log( $message, $level );
        } elseif ( function_exists( 'wp_log' ) ) {
            wp_log( $message, $level );
        } else {
            // If no logging functions are available, silently fail.
            // This is better than using error_log in production.
            return;
        }
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_rest_routes(): void {
        // Add logging to help debug REST API issues.
        $this->log( 'Registering Document Repository REST API routes' );

        // Add CORS headers.
        remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
        add_filter(
            'rest_pre_serve_request',
            function ( $value ) {
                header( 'Access-Control-Allow-Origin: *' );
                header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Credentials: true' );
                header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type, X-Requested-With' );

                // Handle preflight requests.
                if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
                    status_header( 200 );
                    exit();
                }

                return $value;
            }
        );

        // Log authentication info for debugging.
        if ( is_user_logged_in() ) {
            $this->log( sprintf( 'REST API - User logged in, ID: %d', get_current_user_id() ) );
            $this->log( sprintf( 'REST API - Nonce: %s', wp_create_nonce( 'wp_rest' ) ) );
        } else {
            $this->log( 'REST API - No user logged in' );
        }

        // Register the routes.
        $this->get_rest_api_controller_instance()->register_routes();

        // Log registered routes for debugging.
        global $wp_rest_server;
        if ( $wp_rest_server ) {
            $routes = $wp_rest_server->get_routes();
            foreach ( $routes as $route => $handlers ) {
                if ( strpos( $route, 'bcgov-document-repository' ) !== false ) {
                    $this->log( sprintf( 'Registered route: %s', $route ) );
                }
            }
        }
    }

    /**
     * Register admin menus and submenus.
     */
    public function register_admin_menus(): void {
        $this->get_admin_ui_manager_instance()->add_repository_menu();
        $this->get_admin_ui_manager_instance()->add_metadata_settings_submenu();
    }

    /**
     * Get the document post type service.
     *
     * @return DocumentPostType
     */
    public function get_document_post_type_instance(): DocumentPostType {
        if ( null === $this->post_type ) {
            $this->post_type = new DocumentPostType( $this->config );
        }
        return $this->post_type;
    }

    /**
     * Get the document uploader service.
     *
     * @return DocumentUploader
     */
    public function get_document_uploader_instance(): DocumentUploader {
        if ( null === $this->uploader ) {
            $this->uploader = new DocumentUploader( $this->config );
        }
        return $this->uploader;
    }

    /**
     * Get the admin UI manager service.
     *
     * @return AdminUIManager
     */
    public function get_admin_ui_manager_instance(): AdminUIManager {
        if ( null === $this->admin_ui ) {
            $this->admin_ui = new AdminUIManager(
                $this->config,
                $this->get_document_uploader_instance(),
                $this->get_metadata_manager_instance()
            );
        }
        return $this->admin_ui;
    }

    /**
     * Get the REST API controller service.
     *
     * @return RestApiController
     */
    public function get_rest_api_controller_instance(): RestApiController {
        if ( null === $this->rest_api ) {
            $this->rest_api = new RestApiController(
                $this->config,
                $this->get_document_uploader_instance(),
                $this->get_metadata_manager_instance()
            );
        }
        return $this->rest_api;
    }

    /**
     * Get the metadata manager service.
     *
     * @return DocumentMetadataManager
     */
    public function get_metadata_manager_instance(): DocumentMetadataManager {
        if ( null === $this->metadata_manager ) {
            $this->metadata_manager = new DocumentMetadataManager( $this->config );
        }
        return $this->metadata_manager;
    }

    /**
     * Migrate existing document files to the proper multisite paths.
     * This runs on plugin activation or update to ensure all files use the correct structure.
     */
    public function migrate_existing_files() {

        // Get all document posts.
        $post_type = $this->config->get_post_type();
        $documents = get_posts(
            [
                'post_type'      => $post_type,
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]
        );

        if ( empty( $documents ) ) {
            return;
        }

        // Get standard uploads directory info.
        $upload_dir = wp_upload_dir();
        $blog_id    = get_current_blog_id();

        foreach ( $documents as $document ) {
            // Get attachment ID.
            $attachment_id = get_post_meta( $document->ID, 'document_file_id', true );

            if ( ! $attachment_id ) {
                continue;
            }

            // Get attachment URL.
            $url = wp_get_attachment_url( $attachment_id );

            // Check if URL contains old hardcoded path.
            if ( strpos( $url, 'dswp-documents' ) !== false ) {
                // Get attachment file path.
                $file = get_attached_file( $attachment_id );
                if ( ! $file || ! file_exists( $file ) ) {
                    continue;
                }

                // Calculate proper multisite path.
                $filename         = basename( $file );
                $proper_directory = $upload_dir['basedir'] . '/documents';
                $proper_path      = $proper_directory . '/' . $filename;

                // Create directory if it doesn't exist.
                if ( ! file_exists( $proper_directory ) ) {
                    wp_mkdir_p( $proper_directory );
                }

                // Copy file to proper location if it doesn't exist.
                if ( ! file_exists( $proper_path ) ) {
                    copy( $file, $proper_path );

                    // Update attachment file path in WordPress.
                    update_attached_file( $attachment_id, $proper_path );

                    // Add reference to document post.
                    update_post_meta( $attachment_id, '_document_repository_post_id', $document->ID );
                }
            }
        }
    }
}
