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
            'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ],
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => false,
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

        // Explicitly unregister default WordPress taxonomies (category, post_tag) from this post type
        // to prevent confusion with custom taxonomies (e.g., doc_category).
        // This ensures the default category taxonomy doesn't appear for document post types.
        // Note: unregister_taxonomy_for_object_type is safe to call even if not registered.
        $default_taxonomies = array( 'category', 'post_tag' );
        foreach ( $default_taxonomies as $taxonomy ) {
            if ( taxonomy_exists( $taxonomy ) ) {
                unregister_taxonomy_for_object_type( $taxonomy, $post_type );
            }
        }

        // Register metadata fields for REST API.
        $this->register_metadata_fields();

        // Manage document attachments.
        add_filter( 'ajax_query_attachments_args', [ $this, 'hide_document_attachments' ] );
        add_action( 'pre_get_posts', [ $this, 'hide_document_attachments_admin' ] );
        add_action( 'before_delete_post', [ $this, 'delete_document_attachments' ] );
        add_action( 'wp_restore_post_revision', [ $this, 'handle_revision_restore' ], 10, 2 );
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

        // Track current attachment.
        register_post_meta(
            $post_type,
            'document_file_id',
            [
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
			]
        );

        // Track previous versions.
        register_post_meta(
            $post_type,
            'document_file_versions',
            [
				'single'       => true,
				'type'         => 'array',
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
			]
        );
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
                $taxonomy_name = 'doc_' . sanitize_title( $field_id );

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
						'meta_box_cb'       => false,
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
     * Hide document-related attachments from the media modal (AJAX queries).
     *
     * @param array $query The query arguments.
     * @return array Modified query arguments.
     */
    public function hide_document_attachments( $query ): array {
        $attachment_ids = $this->get_document_attachment_ids();

        if ( ! empty( $attachment_ids ) ) {
            $query['post__not_in'] = isset( $query['post__not_in'] )
                ? array_merge( $query['post__not_in'], $attachment_ids )
                : $attachment_ids;
        }

        return $query;
    }

    /**
     * Hide document-related attachments from the Media Library list view.
     *
     * @param \WP_Query $query The current admin query object.
     * @return void
     */
    public function hide_document_attachments_admin( $query ): void {
        if ( ! is_admin() || 'attachment' !== $query->get( 'post_type' ) ) {
            return;
        }

        $attachment_ids = $this->get_document_attachment_ids();

        if ( ! empty( $attachment_ids ) ) {
            $query->set( 'post__not_in', $attachment_ids );
        }
    }

    /**
     * Get all attachment IDs associated with documents.
     *
     * @param bool $current_only If true, only return current document attachments; otherwise include previous versions.
     * @return int[]
     */
    private function get_document_attachment_ids( bool $current_only = false ): array {
        $document_ids = get_posts(
            [
				'post_type'      => $this->config->get_post_type(),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'post_status'    => [ 'publish','draft','pending','private','trash' ],
			]
        );

        if ( empty( $document_ids ) ) {
            return [];
        }

        $attachment_ids = [];

        foreach ( $document_ids as $doc_id ) {
            // Current attachment.
            $file_id = get_post_meta( $doc_id, 'document_file_id', true );
            if ( $file_id ) {
                $attachment_ids[] = (int) $file_id;
            }

            // Previous versions.
            if ( ! $current_only ) {
                $versions = get_post_meta( $doc_id, 'document_file_versions', true );
                if ( is_array( $versions ) ) {
                    foreach ( $versions as $v_id ) {
                        $attachment_ids[] = (int) $v_id;
                    }
                }
            }
        }

        return array_unique( $attachment_ids );
    }

	/**
	 * Handle restoring a document revision and safely clean up newer attachments and revisions.
	 *
	 * When a revision is restored:
	 *   1. Attachments belonging to newer revisions (created after the restored revision)
	 *      are deleted if they are not referenced by the restored revision.
	 *   2. Newer revisions themselves are deleted.
	 *   3. Attachments and version history of the restored revision are preserved.
	 *
	 * This prevents orphaned attachments in the media library while keeping the restored revision intact.
	 *
	 * @param int $post_id     The ID of the post being restored.
	 * @param int $revision_id The ID of the revision being restored.
	 */
	public function handle_revision_restore( int $post_id, int $revision_id ): void {
		if ( get_post_type( $post_id ) !== $this->config->get_post_type() ) {
			return;
		}

		// Meta from the revision being restored.
		$revision_file_id  = (int) get_metadata( 'post', $revision_id, 'document_file_id', true );
		$revision_versions = get_metadata( 'post', $revision_id, 'document_file_versions', true );
		$revision_versions = is_array( $revision_versions ) ? array_map( 'intval', $revision_versions ) : [];

		// Build "keep" list: restored revision's file and its versions.
		$keep_ids = array_filter( array_unique( array_map( 'intval', array_merge( [ $revision_file_id ], $revision_versions ) ) ) );

		// Get all revisions of the post.
		$revisions = wp_get_post_revisions( $post_id );
		$to_delete = [];

		foreach ( $revisions as $rev ) {
			if ( $rev->ID <= $revision_id ) {
				continue;
			}

			// Collect all attachment IDs from this newer revision.
			$file_id  = (int) get_metadata( 'post', $rev->ID, 'document_file_id', true );
			$versions = get_metadata( 'post', $rev->ID, 'document_file_versions', true );
			$versions = is_array( $versions ) ? array_map( 'intval', $versions ) : [];

			$all_ids = array_filter( array_unique( array_merge( [ $file_id ], $versions ) ) );
			foreach ( $all_ids as $aid ) {
				// Only delete IDs that aren't part of the keep list.
				if ( $aid && ! in_array( $aid, $keep_ids, true ) ) {
					$to_delete[] = (int) $aid;
				}
			}

			// Delete the newer revision itself.
			wp_delete_post_revision( $rev->ID );
		}

		// Delete only orphaned attachments (unique, normalized ints).
		$to_delete = array_unique( array_map( 'intval', $to_delete ) );
		foreach ( $to_delete as $aid ) {
			if ( $aid && get_post( $aid ) ) {
				wp_delete_attachment( $aid, true );
			}
		}

		// Restore the restored revision's meta safely.
		update_post_meta( $post_id, 'document_file_id', $revision_file_id );
		update_post_meta( $post_id, 'document_file_versions', $revision_versions );
	}


    /**
     * Delete all attachments associated with a document, including old revisions.
     *
     * Triggered by before_delete_post to remove both the current and any
     * versioned attachments stored in document_file_versions.
     *
     * @param int $post_id The document post ID being deleted.
     */
    public function delete_document_attachments( int $post_id ): void {
        if ( get_post_type( $post_id ) !== $this->config->get_post_type() ) {
            return;
        }

        $attachment_ids = [];

        $current_id = (int) get_post_meta( $post_id, 'document_file_id', true );
        if ( $current_id ) {
            $attachment_ids[] = $current_id;
        }

        $versions = get_post_meta( $post_id, 'document_file_versions', true );
        if ( is_array( $versions ) ) {
            $attachment_ids = array_merge( $attachment_ids, array_map( 'intval', $versions ) );
        }

        $attachment_ids = array_unique( array_filter( $attachment_ids ) );

        foreach ( $attachment_ids as $aid ) {
            if ( get_post( $aid ) ) {
                wp_delete_attachment( $aid, true );
            }
        }

        // Clean meta.
        delete_post_meta( $post_id, 'document_file_versions' );
        delete_post_meta( $post_id, 'document_file_id' );
    }
}
