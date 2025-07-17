<?php

namespace Bcgov\WordpressDocumentRepository;

use Bcgov\WordpressDocumentRepository\RepositoryConfig;
use WP_Query;
use WP_Error;

/**
 * DocumentMetadataManager - Metadata and Cache Handler.
 *
 * This service manages document metadata, custom fields, and caching for.
 * document queries and metadata settings.
 */
class DocumentMetadataManager {
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
     * Get raw metadata fields from database without processing.
     * This is used internally to avoid converting options to objects when saving.
     *
     * @return array Raw metadata fields.
     */
    private function get_raw_metadata_fields(): array {
        return get_option( 'document_repository_metadata_fields', [] );
    }

    /**
     * Get metadata fields configuration.
     *
     * @return array Metadata field definitions.
     */
    public function get_metadata_fields(): array {
        // Get fields from database or default to empty array if none exist.
        $fields = $this->get_raw_metadata_fields();

        // For taxonomy fields, ensure taxonomy is registered and terms are created, then add term IDs to options.
        foreach ( $fields as &$field ) {
            if ( 'taxonomy' === $field['type'] && ! empty( $field['options'] ) ) {
                $taxonomy_name = $this->get_taxonomy_name_for_field( $field['id'] );

                // Get the raw options - these should be simple strings.
                $raw_options = $field['options'];

                // If options are already objects (from previous processing), extract the names.
                if ( ! empty( $raw_options ) && ( is_array( $raw_options[0] ) || is_object( $raw_options[0] ) ) ) {
                    $string_options = [];
                    foreach ( $raw_options as $option ) {
                        $option_array = (array) $option;
                        if ( isset( $option_array['name'] ) ) {
                            $string_options[] = $option_array['name'];
                        } elseif ( isset( $option_array['label'] ) ) {
                            $string_options[] = $option_array['label'];
                        }
                    }
                    $raw_options = $string_options;
                }

                // Note: Taxonomy registration is now handled by DocumentPostType::register_metadata_taxonomies()
                // on the init hook. We just process the terms here.

                // Get the actual terms from the database.
                $terms = get_terms(
                    array(
						'taxonomy'   => $taxonomy_name,
						'hide_empty' => false,
                    )
                );

                // Convert options from just term names to objects with ID and name.
                $options_with_ids = array();

                if ( ! is_wp_error( $terms ) ) {
                    foreach ( $raw_options as $option_name ) {
                        // Find the matching term.
                        foreach ( $terms as $term ) {
                            if ( $term->name === $option_name ) {
                                $options_with_ids[] = array(
                                    'id'    => $term->term_id,
                                    'name'  => $term->name,
                                    'value' => $term->term_id, // For select field value.
                                    'label' => $term->name,    // For select field label.
                                );
                                break;
                            }
                        }
                    }
                }

                $field['options'] = $options_with_ids;
            }
        }

        return $fields;
    }

    /**
     * Validate a metadata field definition.
     *
     * @param array $field Field definition to validate.
     * @param bool  $skip_strict_validation Whether to skip strict validation (e.g., during deletion).
     * @return array Array of validation errors.
     */
    private function validate_field( array $field, bool $skip_strict_validation = false ): array {
        $errors = [];

        // Required fields.
        if ( empty( $field['id'] ) ) {
            $errors[] = __( 'Field ID is required', 'bcgov-design-system' );
        }
        if ( empty( $field['label'] ) ) {
            $errors[] = __( 'Field label is required', 'bcgov-design-system' );
        }
        if ( empty( $field['type'] ) ) {
            $errors[] = __( 'Field type is required', 'bcgov-design-system' );
        }

        // Validate type.
        $valid_types = [ 'text', 'date', 'taxonomy' ];
        if ( ! in_array( $field['type'], $valid_types, true ) ) {
            $errors[] = __( 'Invalid field type', 'bcgov-design-system' );
        }

        // Validate taxonomy options - be more lenient during deletion.
        if ( 'taxonomy' === $field['type'] ) {
            // Check if options exist and are not empty.
            if ( ! isset( $field['options'] ) || ! is_array( $field['options'] ) ) {
                // Set empty array as default if options don't exist.
                $field['options'] = [];
            } else {
                // Filter out empty values.
                $field['options'] = array_filter(
                    $field['options'],
                    function ( $option ) {
						return ! empty( trim( (string) $option ) );
					}
                );
            }

            // Only enforce strict validation when not in deletion context.
            if ( ! $skip_strict_validation && empty( $field['options'] ) ) {
                $errors[] = __( 'Taxonomy fields require at least one term', 'bcgov-design-system' );
            }
        }

        return $errors;
    }

    /**
     * Save metadata fields configuration.
     *
     * @param array $fields Array of field definitions.
     * @param bool  $skip_strict_validation Whether to skip strict validation (e.g., during deletion).
     * @return bool|WP_Error True on success, WP_Error on validation failure.
     */
    public function save_metadata_fields( array $fields, bool $skip_strict_validation = false ) {
        // Validate all fields.
        $all_errors = [];
        foreach ( $fields as $index => $field ) {
            $errors = $this->validate_field( $field, $skip_strict_validation );
            if ( ! empty( $errors ) ) {
                $all_errors[ $index ] = $errors;
            }
        }

        if ( ! empty( $all_errors ) ) {

            return new WP_Error(
                'validation_failed',
                __( 'Field validation failed', 'bcgov-design-system' ),
                [ 'errors' => $all_errors ]
            );
        }

        // Sort fields by order.
        usort(
            $fields,
            function ( $a, $b ) {
                return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
            }
        );

        // Save fields.
        $result = update_option( 'document_repository_metadata_fields', $fields );

        // Handle taxonomy fields.
        if ( $result ) {
            foreach ( $fields as $field ) {
                if ( 'taxonomy' === $field['type'] ) {
                    $this->create_taxonomy_for_field( $field );
                }
            }
        }

        // Trigger re-registration of metadata fields with WordPress REST API.
        if ( $result ) {
            do_action( 'bcgov_document_repository_metadata_fields_updated' );
        }

        return $result;
    }

    /**
     * Add a new metadata field.
     *
     * @param array $field Field definition.
     * @return bool Whether the addition was successful.
     */
    public function add_metadata_field( array $field ): bool {
        if ( ! isset( $field['id'] ) || ! isset( $field['label'] ) || ! isset( $field['type'] ) ) {
            return false;
        }

        $fields = $this->get_raw_metadata_fields();

        // Check for duplicate ID.
        foreach ( $fields as $existing ) {
            if ( $existing['id'] === $field['id'] ) {
                return false;
            }
        }

        // Add new field.
        $fields[] = $field;

        $result = $this->save_metadata_fields( $fields );
        return ! is_wp_error( $result ) && $result;
    }

    /**
     * Update an existing metadata field.
     *
     * @param string $field_id Field ID to update.
     * @param array  $field New field definition.
     * @return bool Whether the update was successful.
     */
    public function update_metadata_field( string $field_id, array $field ): bool {
        $fields  = $this->get_raw_metadata_fields();
        $updated = false;

        foreach ( $fields as $key => $existing ) {
            if ( $existing['id'] === $field_id ) {
                $fields[ $key ] = $field;
                $updated        = true;
                break;
            }
        }

        if ( ! $updated ) {
            return false;
        }

        $result = $this->save_metadata_fields( $fields );

        // Handle validation errors.
        if ( is_wp_error( $result ) ) {
            return false;
        }

        // Handle taxonomy field updates.
        if ( $result && 'taxonomy' === $field['type'] ) {
            $this->update_taxonomy_terms( $field );
        }

        return $result;
    }

    /**
     * Delete a taxonomy and all its terms completely from the database.
     *
     * @param string $taxonomy_name Taxonomy name to delete.
     * @return bool Whether the deletion was successful.
     */
    private function delete_taxonomy_completely( string $taxonomy_name ): bool {

        // Get all terms in this taxonomy.
        $terms = get_terms(
            [
				'taxonomy'   => $taxonomy_name,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
        );

        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            // Delete all terms.
            foreach ( $terms as $term_id ) {
                wp_delete_term( $term_id, $taxonomy_name );
            }
        }

        // Remove taxonomy from global taxonomies array.
        global $wp_taxonomies;
        if ( isset( $wp_taxonomies[ $taxonomy_name ] ) ) {
            unset( $wp_taxonomies[ $taxonomy_name ] );
        }

        // Clean up any term relationships for all documents.
        $this->cleanup_taxonomy_relationships( $taxonomy_name );

        return true;
    }

    /**
     * Clean up taxonomy term relationships for all documents.
     *
     * @param string $taxonomy_name Taxonomy name.
     * @return void
     */
    private function cleanup_taxonomy_relationships( string $taxonomy_name ): void {
        global $wpdb;

        // Get all document posts.
        $post_type    = $this->config->get_post_type();
        $document_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
                $post_type
            )
        );

        foreach ( $document_ids as $doc_id ) {
            wp_set_object_terms( $doc_id, [], $taxonomy_name );
        }
    }

    /**
     * Delete a metadata field.
     *
     * @param string $field_id Field ID to delete.
     * @return bool Whether the deletion was successful.
     */
    public function delete_metadata_field( string $field_id ): bool {
        $fields          = $this->get_raw_metadata_fields();
        $updated         = false;
        $field_to_delete = null;

        foreach ( $fields as $key => $field ) {
            if ( $field['id'] === $field_id ) {
                $field_to_delete = $field;
                unset( $fields[ $key ] );
                $updated = true;
                break;
            }
        }

        if ( ! $updated ) {
            return false;
        }

        // If this was a taxonomy field, delete the taxonomy and all its terms.
        if ( $field_to_delete && 'taxonomy' === $field_to_delete['type'] ) {
            $taxonomy_name = $this->get_taxonomy_name_for_field( $field_id );
            $this->delete_taxonomy_completely( $taxonomy_name );
        }

        // Reindex array.
        $fields = array_values( $fields );

        $result = $this->save_metadata_fields( $fields, true ); // Pass true to skip strict validation for deletion.

        return ! is_wp_error( $result ) && $result;
    }

    /**
     * Get document metadata.
     *
     * @param int $post_id Document post ID.
     * @return array Document metadata.
     */
    public function get_document_metadata( int $post_id ): array {
        // Get all metadata for the post.
        $all_meta = get_post_meta( $post_id );
        $metadata = [];

        // Convert single value arrays to scalar values.
        foreach ( $all_meta as $key => $values ) {
            $metadata[ $key ] = is_array( $values ) && 1 === count( $values ) ? $values[0] : $values;
        }

        // Add taxonomy values for taxonomy-type metadata fields.
        $fields = $this->get_metadata_fields();
        foreach ( $fields as $field ) {
            if ( 'taxonomy' === $field['type'] ) {
                $taxonomy_name = $this->get_taxonomy_name_for_field( $field['id'] );
                $terms         = wp_get_object_terms( $post_id, $taxonomy_name );

                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    // For single term assignments, return the term name directly.
                    // For multiple term assignments, return array of term names.
                    if ( count( $terms ) === 1 ) {
                        $metadata[ $field['id'] ] = $terms[0]->name;
                    } else {
                        $metadata[ $field['id'] ] = array_map(
                            function ( $term ) {
                                return $term->name;
                            },
                            $terms
                        );
                    }
                }
            }
        }

        // Add file data.
        $file_id = get_post_meta( $post_id, 'document_file_id', true );
        if ( $file_id ) {
            $metadata['document_file_id'] = $file_id;

            // Get the file path and URL.
            $file_path = get_attached_file( $file_id );
            $file_url  = wp_get_attachment_url( $file_id );

            // If the file is in the documents directory, update the URL.
            if ( strpos( $file_path, '/documents/' ) !== false ) {
                $upload_dir = wp_upload_dir();
                $file_url   = $upload_dir['baseurl'] . '/documents/' . basename( $file_path );
            }

            $metadata['document_file_url']  = $file_url;
            $metadata['document_file_name'] = basename( $file_path );
            $metadata['document_file_type'] = get_post_mime_type( $file_id );
            $metadata['document_file_size'] = filesize( $file_path );
        }

        return $metadata;
    }

    /**
     * Get counts of documents by post status.
     *
     * @return array Associative array of status => count.
     */
    public function get_document_status_counts(): array {
        $counts = wp_count_posts( $this->config->get_post_type() );
        return is_object( $counts ) ? (array) $counts : [];
    }

    /**
     * Save document metadata.
     *
     * @param int   $post_id Document post ID.
     * @param array $metadata Metadata to save.
     * @return bool Whether the save was successful.
     */
    public function save_document_metadata( int $post_id, array $metadata ): bool {
        $fields    = $this->get_metadata_fields();
        $field_map = array_column( $fields, null, 'id' );

        foreach ( $metadata as $field_id => $value ) {
            // Skip if not a registered field.
            if ( ! isset( $field_map[ $field_id ] ) && 'document_file_id' !== $field_id ) {
                continue;
            }

            // Check if this is a taxonomy field.
            if ( isset( $field_map[ $field_id ] ) && 'taxonomy' === $field_map[ $field_id ]['type'] ) {
                // This is a taxonomy field - set the term relationships instead of saving as meta.
                $taxonomy_name = $this->get_taxonomy_name_for_field( $field_id );

                if ( ! empty( $value ) ) {
                    // Handle both single values and arrays.
                    $terms = is_array( $value ) ? $value : [ $value ];

                    // Convert term names to IDs for wp_set_object_terms.
                    $term_ids = [];
                    foreach ( $terms as $term_name ) {
                        $term_obj = get_term_by( 'name', $term_name, $taxonomy_name );
                        if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                            $term_ids[] = $term_obj->term_id;
                        }
                    }

                    // Set the taxonomy terms for this document.
                    $result = wp_set_object_terms( $post_id, $term_ids, $taxonomy_name );

                } else {
                    // Clear taxonomy terms if value is empty.
                    wp_set_object_terms( $post_id, [], $taxonomy_name );
                }
                continue;
            }

            // Sanitize value based on field type for regular metadata.
            if ( isset( $field_map[ $field_id ] ) ) {
                $field = $field_map[ $field_id ];
                switch ( $field['type'] ) {
                    case 'text':
                        $value = sanitize_text_field( $value );
                        break;
                    case 'textarea':
                        $value = sanitize_textarea_field( $value );
                        break;
                    case 'date':
                        $value = sanitize_text_field( $value );
                        break;
                    default:
                        $value = sanitize_text_field( $value );
                        break;
                }
            } else {
                $value = sanitize_text_field( $value );
            }

            // Save the sanitized value as post meta.
            update_post_meta( $post_id, $field_id, $value );
        }

        // Update file-related metadata to ensure it's current in REST API.
        $this->update_file_metadata( $post_id );

        return true;
    }

    /**
     * Update file-related metadata for a document.
     * This ensures file data is current and available via REST API.
     *
     * @param int $post_id Document post ID.
     */
    private function update_file_metadata( int $post_id ): void {
        $file_id = get_post_meta( $post_id, 'document_file_id', true );
        if ( $file_id ) {
            // Get the file path and URL.
            $file_path = get_attached_file( $file_id );
            $file_url  = wp_get_attachment_url( $file_id );

            // If the file is in the documents directory, update the URL.
            if ( $file_path && strpos( $file_path, '/documents/' ) !== false ) {
                $upload_dir = wp_upload_dir();
                $file_url   = $upload_dir['baseurl'] . '/documents/' . basename( $file_path );
            }

            // Update file metadata.
            if ( $file_url ) {
                update_post_meta( $post_id, 'document_file_url', $file_url );
            }
            if ( $file_path ) {
                update_post_meta( $post_id, 'document_file_name', basename( $file_path ) );
                if ( file_exists( $file_path ) ) {
                    update_post_meta( $post_id, 'document_file_size', filesize( $file_path ) );
                }
            }

            $mime_type = get_post_mime_type( $file_id );
            if ( $mime_type ) {
                update_post_meta( $post_id, 'document_file_type', $mime_type );
            }
        }
    }

    /**
     * Get documents with pagination.
     *
     * @param array $args Query arguments.
     * @return array Documents and pagination info.
     */
    public function get_documents( array $args = [] ): array {
        $defaults = [
            'paged'      => 1,
            'per_page'   => $this->config->get( 'per_page' ),
            'orderby'    => 'date',
            'order'      => 'DESC',
            'search'     => '',
            'meta_query' => [],
            'tax_query'  => [],
        ];

        $args = wp_parse_args( $args, $defaults );

        // Determine post status filter.
        $status = $args['status'] ?? 'all';
        switch ( $status ) {
            case 'trash':
                $post_status = [ 'trash' ];
                break;
            case 'all':
            default:
                $post_status = [ 'publish', 'draft', 'pending', 'private' ];
                break;
        }

        // Build query.
        $query_args = [
            'post_type'      => $this->config->get_post_type(),
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['paged'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'post_status'    => $post_status,
        ];

        // Add search.
        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = $args['search'];
        }

        // Add meta query.
        if ( ! empty( $args['meta_query'] ) ) {
            $query_args['meta_query'] = $args['meta_query'];
        }

        // Add taxonomy query.
        if ( ! empty( $args['tax_query'] ) ) {
            $query_args['tax_query'] = $args['tax_query'];
        }

        // Run query.
        $query = new WP_Query( $query_args );

        // Format results.
        $documents = [];
        foreach ( $query->posts as $post ) {
            $documents[] = [
                'id'       => $post->ID,
                'title'    => $post->post_title,
                'date'     => $post->post_date,
                'author'   => get_the_author_meta( 'display_name', $post->post_author ),
                'metadata' => $this->get_document_metadata( $post->ID ),
            ];
        }

        return [
            'documents'    => $documents,
            'total'        => $query->found_posts,
            'total_pages'  => $query->max_num_pages,
            'current_page' => $args['paged'],
        ];
    }

    /**
     * Clear cache - now a no-op function for backward compatibility.
     */
    public function clear_cache(): void {
        // This function is kept for backward compatibility but does nothing.
        $this->log( 'Cache clearing requested but caching is disabled', 'debug' );
    }

    /**
     * Log message to WordPress log.
     *
     * @param string $message Message to log.
     * @param string $level Log level.
     */
    private function log( string $message, string $level = 'info' ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            do_action( 'document_repository_log', $message, $level );
        }
    }

    /**
     * Create and register a taxonomy for a metadata field.
     *
     * @param array $field Metadata field definition.
     * @return bool Whether the taxonomy was created successfully.
     */
    public function create_taxonomy_for_field( array $field ): bool {
        if ( 'taxonomy' !== $field['type'] ) {
            return false;
        }

        // Register the taxonomy with terms.
        return $this->register_field_taxonomy( $field['id'], $field['label'], $field['options'] ?? [] );
    }

    /**
     * Get the taxonomy name for a metadata field.
     *
     * @param string $field_id Field ID.
     * @return string Taxonomy name.
     */
    public function get_taxonomy_name_for_field( string $field_id ): string {
        return 'doc_' . sanitize_title( $field_id );
    }

    /**
     * Register a taxonomy for a metadata field.
     *
     * @param string $field_id Field ID.
     * @param string $field_label Field label.
     * @param array  $field_options Optional. Field options/terms to create.
     * @return bool Whether the taxonomy was registered.
     */
    public function register_field_taxonomy( string $field_id, string $field_label, array $field_options = [] ): bool {
        $taxonomy_name = $this->get_taxonomy_name_for_field( $field_id );
        $post_type     = $this->config->get_post_type();

        // Check if taxonomy is already registered.
        if ( taxonomy_exists( $taxonomy_name ) ) {
            // If taxonomy exists, still try to create any missing terms.
            if ( ! empty( $field_options ) ) {
                $this->create_taxonomy_terms( $taxonomy_name, $field_options );
            }
            return true;
        }

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

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
            'meta_box_cb'       => false, // We'll handle the UI ourselves.
        ];

        $result = register_taxonomy( $taxonomy_name, $post_type, $args );

        if ( ! is_wp_error( $result ) && ! empty( $field_options ) ) {
            // Create terms after successful taxonomy registration.
            $this->create_taxonomy_terms( $taxonomy_name, $field_options );
        }

        return ! is_wp_error( $result );
    }

    /**
     * Create terms for a taxonomy.
     *
     * @param string $taxonomy_name Taxonomy name.
     * @param array  $terms Array of term names (strings) or term objects.
     * @return bool Whether terms were created successfully.
     */
    public function create_taxonomy_terms( string $taxonomy_name, array $terms ): bool {
        if ( empty( $terms ) ) {
            return true;
        }

        $success_count = 0;
        $total_terms   = 0;

        foreach ( $terms as $term_data ) {
            $term_name = '';

            // Handle both string values and objects.
            if ( is_array( $term_data ) || is_object( $term_data ) ) {
                // Convert object to array for consistent handling.
                $term_array = (array) $term_data;

                // Extract the term name from the object/array.
                if ( isset( $term_array['name'] ) ) {
                    $term_name = $term_array['name'];
                } elseif ( isset( $term_array['label'] ) ) {
                    $term_name = $term_array['label'];
                } else {
                    // If it's just a plain array, skip.
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

            ++$total_terms;

            // Check if term already exists.
            $existing_term = get_term_by( 'name', $term_name, $taxonomy_name );
            if ( $existing_term ) {
                ++$success_count;
                continue;
            }

            // Create the term.
            $result = wp_insert_term( $term_name, $taxonomy_name );
            if ( ! is_wp_error( $result ) ) {
                ++$success_count;
            }
        }

        return $success_count === $total_terms;
    }

    /**
     * Update taxonomy terms for a field.
     *
     * @param array $field Metadata field definition.
     * @return bool Whether terms were updated successfully.
     */
    public function update_taxonomy_terms( array $field ): bool {
        if ( 'taxonomy' !== $field['type'] ) {
            return false;
        }

        $taxonomy_name = $this->get_taxonomy_name_for_field( $field['id'] );

        // Get existing terms.
        $existing_terms = get_terms(
            [
				'taxonomy'   => $taxonomy_name,
				'hide_empty' => false,
			]
        );

        if ( is_wp_error( $existing_terms ) ) {
            return false;
        }

        $existing_term_names = array_map(
            function ( $term ) {
                return $term->name;
            },
            $existing_terms
        );

        // Safely handle options that might contain arrays or non-string values.
        $new_term_names = [];
        if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
            foreach ( $field['options'] as $option ) {
                if ( is_string( $option ) ) {
                    $trimmed = trim( $option );
                    if ( ! empty( $trimmed ) ) {
                        $new_term_names[] = $trimmed;
                    }
                }
            }
        }

        // Delete terms that are no longer in the options.
        $terms_to_delete = array_diff( $existing_term_names, $new_term_names );
        foreach ( $terms_to_delete as $term_name ) {
            $term = get_term_by( 'name', $term_name, $taxonomy_name );
            if ( $term ) {
                // Remove term relationships from all documents before deleting.
                $this->remove_term_from_all_documents( $term->term_id, $taxonomy_name );

                // Delete the term.
                wp_delete_term( $term->term_id, $taxonomy_name );
            }
        }

        // Add new terms.
        $terms_to_add = array_diff( $new_term_names, $existing_term_names );
        if ( ! empty( $terms_to_add ) ) {
            $this->create_taxonomy_terms( $taxonomy_name, $terms_to_add );
        }

        return true;
    }

    /**
     * Remove a specific term from all documents.
     *
     * @param int    $term_id Term ID to remove.
     * @param string $taxonomy_name Taxonomy name.
     * @return void
     */
    private function remove_term_from_all_documents( int $term_id, string $taxonomy_name ): void {
        global $wpdb;

        // Get the term_taxonomy_id for this term and taxonomy.
        $term_taxonomy_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} 
             WHERE term_id = %d AND taxonomy = %s",
                $term_id,
                $taxonomy_name
            )
        );

        if ( ! $term_taxonomy_id ) {
            return;
        }

        // Get all documents that have this term.
        $post_type    = $this->config->get_post_type();
        $document_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             WHERE p.post_type = %s AND tr.term_taxonomy_id = %d",
                $post_type,
                $term_taxonomy_id
            )
        );

        foreach ( $document_ids as $doc_id ) {
                // Get current terms for this document.
            $current_terms = wp_get_object_terms( $doc_id, $taxonomy_name, [ 'fields' => 'ids' ] );
            if ( ! is_wp_error( $current_terms ) ) {
                // Remove the term we're deleting.
                $updated_terms = array_diff( $current_terms, [ $term_id ] );
                wp_set_object_terms( $doc_id, $updated_terms, $taxonomy_name );
            }
        }
    }
}
