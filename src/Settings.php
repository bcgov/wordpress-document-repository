<?php

namespace Bcgov\WordpressDocumentRepository;

/**
 * Class Settings
 *
 * This class handles the settings for the Document Repository feature.
 *
 * @package Bcgov\WordpressDocumentRepository
 */
class Settings {
    /**
     * The option name used to store the setting in WordPress options table.
     */
    const OPTION_NAME = 'wordpress_document_repository_enabled';

    /**
     * Initialize the class by registering WordPress hooks.
     *
     * @return void
     */
    public function init() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Register the settings for the document repository feature.
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            'wordpress-document-repository_options_group',
            self::OPTION_NAME,
            [
                'type'              => 'string',
                'default'           => '0',
                'show_in_rest'      => true,
                'sanitize_callback' => function ( $value ) {
                    return $value ? '1' : '0';
                },
            ]
        );
    }

    /**
     * Add the submenu page to the Design System menu.
     *
     * @return void
     */
    public function add_menu() {
        add_submenu_page(
            'wordpress-document-repository-admin-menu',
            __( 'Document Repository Settings', 'wordpress-document-repository' ),
            __( 'Document Repository', 'wordpress-document-repository' ),
            'manage_options',
            'wordpress-document-repository-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Render the settings page HTML.
     *
     * @return void
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Include the template file.
        require_once __DIR__ . '/View.php';
    }
}
