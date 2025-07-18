<?php

namespace Bcgov\WordpressDocumentRepository;

use Bcgov\WordpressDocumentRepository\RepositoryConfig;

/**
 * DocumentPostType - Custom Post Type Handler
 *
 * This service handles the registration and configuration of the custom post type
 * used to store documents in the repository.
 */
class DocumentPostType {
    /**
     * Configuration service.
     *
     * @var RepositoryConfig
     */
    private RepositoryConfig $config;

    /**
     * Constructor.
     *
     * @param RepositoryConfig $config Configuration service.
     */
    public function __construct( RepositoryConfig $config ) {
        $this->config = $config;
    }

    /**
     * Register the document custom post type.
     */
    public function register(): void {
        $post_type = $this->config->get_post_type();
        $label     = $this->config->get( 'post_type_label' );
        $singular  = $this->config->get( 'post_type_singular' );

        $args = [
            'labels'              => [
                'name'                  => $label,
                'singular_name'         => $singular,
                'add_new'               => sprintf( 'Add New %s', $singular ),
                'add_new_item'          => sprintf( 'Add New %s', $singular ),
                'edit_item'             => sprintf( 'Edit %s', $singular ),
                'new_item'              => sprintf( 'New %s', $singular ),
                'view_item'             => sprintf( 'View %s', $singular ),
                'view_items'            => sprintf( 'View %s', $label ),
                'search_items'          => sprintf( 'Search %s', $label ),
                'not_found'             => sprintf( 'No %s found', strtolower( $label ) ),
                'not_found_in_trash'    => sprintf( 'No %s found in Trash', strtolower( $label ) ),
                'parent_item_colon'     => sprintf( 'Parent %s:', $singular ),
                'all_items'             => sprintf( 'All %s', $label ),
                'archives'              => sprintf( '%s Archives', $singular ),
                'attributes'            => sprintf( '%s Attributes', $singular ),
                'insert_into_item'      => sprintf( 'Insert into %s', strtolower( $singular ) ),
                'uploaded_to_this_item' => sprintf( 'Uploaded to this %s', strtolower( $singular ) ),
                'featured_image'        => 'Featured Image',
                'set_featured_image'    => 'Set featured image',
                'remove_featured_image' => 'Remove featured image',
                'use_featured_image'    => 'Use as featured image',
                'menu_name'             => $label,
                'filter_items_list'     => sprintf( 'Filter %s list', $label ),
                'items_list_navigation' => sprintf( '%s list navigation', $label ),
                'items_list'            => sprintf( '%s list', $label ),
            ],
            'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields' ],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => $this->config->get( 'menu_position' ),
            'menu_icon'           => $this->config->get( 'menu_icon' ),
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => false,
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
            'rest_base'           => $post_type,
        ];

        register_post_type( $post_type, $args );

        // Register metadata fields for REST API.
        $this->register_metadata_fields();

        // Hide document attachments from media library.
        add_filter( 'ajax_query_attachments_args', [ $this, 'hide_document_attachments' ] );
    }

    /**
     * Register metadata fields for REST API exposure.
     */
    public function register_metadata_fields(): void {
        $post_type = $this->config->get_post_type();

        $standard_fields = [
            'document_file_url'  => [
                'type'         => 'string',
                'description'  => 'Document file URL',
                'show_in_rest' => true,
                'single'       => true,
            ],
            'document_file_name' => [
                'type'         => 'string',
                'description'  => 'Document file name',
                'show_in_rest' => true,
                'single'       => true,
            ],
            'document_file_type' => [
                'type'         => 'string',
                'description'  => 'Document file MIME type',
                'show_in_rest' => true,
                'single'       => true,
            ],
            'document_file_size' => [
                'type'         => 'integer',
                'description'  => 'Document file size in bytes',
                'show_in_rest' => true,
                'single'       => true,
            ],
        ];

        foreach ( $standard_fields as $field_id => $schema ) {
            register_post_meta( $post_type, $field_id, $schema );
        }
    }

    /**
     * Register taxonomies for taxonomy-type metadata fields.
     * This should be called on the init hook.
     */
    public function register_metadata_taxonomies(): void {
        // Prevent multiple registrations in the same request.
        static $already_registered = false;
        if ( $already_registered ) {
            return;
        }
        $already_registered = true;

        $metadata_fields = get_option( 'document_repository_metadata_fields', [] );
        $post_type       = $this->config->get_post_type();

        foreach ( $metadata_fields as $field ) {
            if ( 'taxonomy' === $field['type'] ) {
                $field_id      = $field['id'];
                $field_label   = $field['label'];
                $field_options = $field['options'] ?? [];

                // Create clean taxonomy name with doc_ prefix.
                $taxonomy_name = sanitize_title( $field_id );

                // Skip if taxonomy already exists.
                if ( taxonomy_exists( $taxonomy_name ) ) {
                    continue;
                }

                // Prepare taxonomy labels.
                $labels = [
                    'name'          => $field_label,
                    'singular_name' => $field_label,
                    'search_items'  => sprintf( 'Search %s', $field_label ),
                    'all_items'     => sprintf( 'All %s', $field_label ),
                    'edit_item'     => sprintf( 'Edit %s', $field_label ),
                    'update_item'   => sprintf( 'Update %s', $field_label ),
                    'add_new_item'  => sprintf( 'Add New %s', $field_label ),
                    'new_item_name' => sprintf( 'New %s Name', $field_label ),
                    'menu_name'     => $field_label,
                ];

                // Register the taxonomy.
                $result = register_taxonomy(
                    $taxonomy_name,
                    $post_type,
                    [
						'labels'            => $labels,
						'hierarchical'      => false,
						'show_ui'           => true,
						'show_admin_column' => true,
						'query_var'         => false,
						'rewrite'           => false,
						'show_in_rest'      => true,
						'meta_box_cb'       => false, // We'll handle the UI ourselves.
					]
                );

                // Create terms for this taxonomy.
                if ( ! is_wp_error( $result ) && ! empty( $field_options ) ) {
                    $this->create_taxonomy_terms( $taxonomy_name, $field_options );
                }
            }
        }
    }

    /**
     * Create terms for a taxonomy.
     *
     * @param string $taxonomy_name Taxonomy name.
     * @param array  $terms Array of term names.
     */
    private function create_taxonomy_terms( string $taxonomy_name, array $terms ): void {

        foreach ( $terms as $term_data ) {
            $term_name = '';

            // Handle both string values and objects.
            if ( is_array( $term_data ) || is_object( $term_data ) ) {
                $term_array = (array) $term_data;
                if ( isset( $term_array['name'] ) ) {
                    $term_name = $term_array['name'];
                } elseif ( isset( $term_array['label'] ) ) {
                    $term_name = $term_array['label'];
                } else {
                    continue;
                }
            } elseif ( is_string( $term_data ) ) {
                $term_name = $term_data;
            } else {
                continue;
            }

            $term_name = trim( (string) $term_name );
            if ( empty( $term_name ) ) {
                continue;
            }

            // Check if term already exists.
            $existing_term = get_term_by( 'name', $term_name, $taxonomy_name );
            if ( $existing_term ) {
                continue;
            }

            // Create the term.
            $result = wp_insert_term( $term_name, $taxonomy_name );
        }
    }

    /**
     * Add custom meta boxes for the document post type.
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'document_file_meta_box',
            'Document File',
            [ $this, 'render_file_meta_box' ],
            $this->config->get_post_type(),
            'normal',
            'high'
        );
    }

    /**
     * Render the document file meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_file_meta_box( \WP_Post $post ): void {
        // This is just a placeholder - with our React app, we don't need PHP templates.
        echo '<div id="document-repository-file-metabox" data-post-id="' . esc_attr( $post->ID ) . '"></div>';

        // Add nonce field for security.
        wp_nonce_field( 'document_file_meta_box', 'document_file_meta_box_nonce' );
    }

    /**
     * Hide document attachments from the media library.
     *
     * @param array $query The query arguments.
     * @return array Modified query arguments.
     */
    public function hide_document_attachments( $query ): array {
        // Get all document post IDs.
        $document_ids = get_posts(
            [
				'post_type'      => $this->config->get_post_type(),
				'fields'         => 'ids',
				'posts_per_page' => -1,
			]
        );

        if ( ! empty( $document_ids ) ) {
            // Get all attachment IDs associated with documents.
            $attachment_ids = [];
            foreach ( $document_ids as $doc_id ) {
                $file_id = get_post_meta( $doc_id, 'document_file_id', true );
                if ( $file_id ) {
                    $attachment_ids[] = $file_id;
                }
            }

            if ( ! empty( $attachment_ids ) ) {
                $query['post__not_in'] = isset( $query['post__not_in'] )
                    ? array_merge( $query['post__not_in'], $attachment_ids )
                    : $attachment_ids;
            }
        }

        return $query;
    }
}
