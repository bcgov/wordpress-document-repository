<?php

namespace Bcgov\WordpressDocumentRepository;

use Bcgov\WordpressDocumentRepository\DocumentUploader;
use Bcgov\WordpressDocumentRepository\DocumentMetadataManager;
use WP_Error;

/**
 * CsvBulkUploader - CSV Bulk Upload and Auto-Tagging Handler
 *
 * This service handles CSV file uploads and processes them to automatically
 * tag existing documents based on CSV content.
 */
class CsvBulkUploader {
    /**
     * Configuration service.
     *
     * @var RepositoryConfig
     */
    private RepositoryConfig $config;

    /**
     * Document uploader service.
     *
     * @var DocumentUploader
     */
    private DocumentUploader $uploader;

    /**
     * Metadata manager service.
     *
     * @var DocumentMetadataManager
     */
    private DocumentMetadataManager $metadata_manager;

    /**
     * Constructor.
     *
     * @param RepositoryConfig        $config Configuration service.
     * @param DocumentUploader        $uploader Document uploader service.
     * @param DocumentMetadataManager $metadata_manager Metadata manager service.
     */
    public function __construct(
        RepositoryConfig $config,
        DocumentUploader $uploader,
        DocumentMetadataManager $metadata_manager
    ) {
        $this->config           = $config;
        $this->uploader         = $uploader;
        $this->metadata_manager = $metadata_manager;
    }

    /**
     * Process CSV file for bulk document tagging.
     *
     * @param array $file CSV file data from $_FILES.
     * @param array $options Processing options.
     * @return array|WP_Error Processing results or error.
     */
    public function process_csv_bulk_upload( array $file, array $options = [] ) {
        // Validate CSV file
        $validation_result = $this->validate_csv_file( $file );
        if ( is_wp_error( $validation_result ) ) {
            return $validation_result;
        }

        // Parse CSV content
        $csv_data = $this->parse_csv_file( $file['tmp_name'] );
        if ( is_wp_error( $csv_data ) ) {
            return $csv_data;
        }

        // Validate CSV structure
        $structure_validation = $this->validate_csv_structure( $csv_data );
        if ( is_wp_error( $structure_validation ) ) {
            return $structure_validation;
        }

        // Process CSV data and tag documents
        $processing_result = $this->process_csv_data( $csv_data, $options );

        return $processing_result;
    }

    /**
     * Validate CSV file.
     *
     * @param array $file File data.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    private function validate_csv_file( array $file ) {
        // Check if file exists and is readable
        if ( ! file_exists( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
            return new WP_Error(
                'csv_file_not_readable',
                'CSV file is not readable or does not exist.'
            );
        }

        // Check file size (max 10MB for CSV files)
        $max_csv_size = 10 * 1024 * 1024; // 10MB
        if ( $file['size'] > $max_csv_size ) {
            return new WP_Error(
                'csv_file_too_large',
                sprintf(
                    'CSV file is too large. Maximum size allowed is %s MB.',
                    number_format( $max_csv_size / ( 1024 * 1024 ), 2 )
                )
            );
        }

        // Check file type
        $file_type = wp_check_filetype( basename( $file['name'] ), null );
        $allowed_types = [ 'text/csv', 'text/plain', 'application/csv' ];
        
        if ( empty( $file_type['type'] ) || ! in_array( $file_type['type'], $allowed_types, true ) ) {
            // Also check file extension as fallback
            $extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
            if ( 'csv' !== $extension ) {
                return new WP_Error(
                    'invalid_csv_file_type',
                    'File must be a valid CSV file.'
                );
            }
        }

        return true;
    }

    /**
     * Parse CSV file content.
     *
     * @param string $file_path Path to CSV file.
     * @return array|WP_Error Parsed CSV data or error.
     */
    private function parse_csv_file( string $file_path ) {
        $csv_data = [];
        
        // Try to detect file encoding
        $file_content = file_get_contents( $file_path );
        if ( false === $file_content ) {
            return new WP_Error(
                'csv_file_read_failed',
                'Failed to read CSV file content.'
            );
        }

        // Detect encoding and convert to UTF-8 if needed
        $encoding = mb_detect_encoding( $file_content, [ 'UTF-8', 'ISO-8859-1', 'Windows-1252' ], true );
        if ( 'UTF-8' !== $encoding ) {
            $file_content = mb_convert_encoding( $file_content, 'UTF-8', $encoding );
        }

        // Parse CSV content
        $handle = fopen( 'php://temp', 'r+' );
        fwrite( $handle, $file_content );
        rewind( $handle );

        // Read header row
        $header = fgetcsv( $handle );
        if ( false === $header ) {
            fclose( $handle );
            return new WP_Error(
                'csv_header_read_failed',
                'Failed to read CSV header row.'
            );
        }

        // Clean header row
        $header = array_map( 'trim', $header );
        $header = array_filter( $header ); // Remove empty columns

        if ( empty( $header ) ) {
            fclose( $handle );
            return new WP_Error(
                'csv_header_empty',
                'CSV header row is empty or contains no valid columns.'
            );
        }

        // Read data rows
        $row_number = 1; // Start after header
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $row_number++;
            
            // Skip empty rows
            if ( empty( array_filter( $row ) ) ) {
                continue;
            }

            // Ensure row has same number of columns as header
            if ( count( $row ) !== count( $header ) ) {
                // Pad or truncate row to match header length
                $row = array_pad( array_slice( $row, 0, count( $header ) ), count( $header ), '' );
            }

            // Create associative array from header and row
            $row_data = array_combine( $header, $row );
            if ( false === $row_data ) {
                continue; // Skip malformed rows
            }

            // Clean row data
            $row_data = array_map( 'trim', $row_data );
            
            // Normalize keys to handle case-insensitive access
            $normalized_row_data = [];
            foreach ( $row_data as $key => $value ) {
                $normalized_key = trim( $key );
                $normalized_row_data[ $normalized_key ] = $value;
            }
            $csv_data[] = $normalized_row_data;
        }

        fclose( $handle );

        if ( empty( $csv_data ) ) {
            return new WP_Error(
                'csv_no_data_rows',
                'CSV file contains no valid data rows.'
            );
        }

        return [
            'header' => $header,
            'rows'   => $csv_data,
            'total_rows' => count( $csv_data ),
        ];
    }

    /**
     * Validate CSV structure.
     *
     * @param array $csv_data Parsed CSV data.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    private function validate_csv_structure( array $csv_data ) {
        $header = $csv_data['header'];
        
        // Check for required "Asset Filename" column (case-insensitive)
        $has_asset_filename = false;
        foreach ( $header as $column ) {
            if ( strtolower( trim( $column ) ) === 'asset filename' ) {
                $has_asset_filename = true;
                break;
            }
        }
        
        if ( ! $has_asset_filename ) {
            return new WP_Error(
                'csv_missing_required_column',
                'CSV must contain the "Asset Filename" column to identify documents.'
            );
        }

        return true;
    }

    /**
     * Process CSV data and tag documents.
     *
     * @param array $csv_data Parsed CSV data.
     * @param array $options Processing options.
     * @return array Processing results.
     */
    private function process_csv_data( array $csv_data, array $options = [] ) {
        $results = [
            'total_rows'        => $csv_data['total_rows'],
            'processed_rows'    => 0,
            'successful_tags'   => 0,
            'failed_tags'       => 0,
            'documents_found'   => 0,
            'documents_not_found' => 0,
            'errors'            => [],
            'details'           => [],
        ];

        $header = $csv_data['header'];
        $rows = $csv_data['rows'];

        // Get metadata fields for validation
        $metadata_fields = $this->metadata_manager->get_metadata_fields();
        $field_map = array_column( $metadata_fields, null, 'id' );
        
        // Also create a normalized field map for case-insensitive lookup
        $normalized_field_map = [];
        foreach ( $metadata_fields as $field ) {
            $normalized_key = strtolower( trim( $field['id'] ) );
            $normalized_field_map[ $normalized_key ] = $field;
            
            // Also index by sanitized ID with hyphens
            $sanitized_id = sanitize_title( $field['id'] );
            if ( ! isset( $normalized_field_map[ $sanitized_id ] ) ) {
                $normalized_field_map[ $sanitized_id ] = $field;
            }
            
            // Also index by field ID as-is (handles underscores in IDs like "supervisory_review")
            // This allows "Supervisory Review" (sanitized to "supervisory-review") to match "supervisory_review"
            // by converting hyphens to underscores
            $field_id_underscore = str_replace( '-', '_', $sanitized_id );
            if ( $field_id_underscore !== $sanitized_id && ! isset( $normalized_field_map[ $field_id_underscore ] ) ) {
                $normalized_field_map[ $field_id_underscore ] = $field;
            }
            
            // Also index by label (case-insensitive)
            if ( ! empty( $field['label'] ) ) {
                $label_key = strtolower( trim( $field['label'] ) );
                if ( ! isset( $normalized_field_map[ $label_key ] ) ) {
                    $normalized_field_map[ $label_key ] = $field;
                }
                // Also index by sanitized label
                $sanitized_label = sanitize_title( $field['label'] );
                if ( ! isset( $normalized_field_map[ $sanitized_label ] ) ) {
                    $normalized_field_map[ $sanitized_label ] = $field;
                }
                // Also index by sanitized label with underscores
                $sanitized_label_underscore = str_replace( '-', '_', $sanitized_label );
                if ( $sanitized_label_underscore !== $sanitized_label && ! isset( $normalized_field_map[ $sanitized_label_underscore ] ) ) {
                    $normalized_field_map[ $sanitized_label_underscore ] = $field;
                }
            }
        }

        // Process each row
        foreach ( $rows as $row_index => $row_data ) {
            $row_number = $row_index + 2; // +2 because we start after header and arrays are 0-indexed
            
            try {
                $row_result = $this->process_csv_row( $row_data, $field_map, $normalized_field_map, $options );
                
                if ( is_wp_error( $row_result ) ) {
                    $results['errors'][] = [
                        'row'     => $row_number,
                        'message' => $row_result->get_error_message(),
                        'data'    => $row_data,
                    ];
                    $results['failed_tags']++;
                } else {
                    $results['successful_tags'] += $row_result['tags_applied'];
                    $results['documents_found'] += $row_result['documents_found'];
                    $results['documents_not_found'] += $row_result['documents_not_found'];
                    $results['details'][] = $row_result;
                }
                
                $results['processed_rows']++;
                
            } catch ( \Exception $e ) {
                $results['errors'][] = [
                    'row'     => $row_number,
                    'message' => 'Unexpected error: ' . $e->getMessage(),
                    'data'    => $row_data,
                ];
                $results['failed_tags']++;
            }
        }

        return $results;
    }

    /**
     * Process a single CSV row.
     *
     * @param array  $row_data Row data.
     * @param array  $field_map Metadata field map.
     * @param array  $normalized_field_map Normalized field map for case-insensitive lookup.
     * @param array  $options Processing options.
     * @return array|WP_Error Row processing result or error.
     */
    private function process_csv_row( array $row_data, array $field_map, array $normalized_field_map, array $options ) {
        // Find Asset Filename column (case-insensitive)
        $asset_filename = '';
        foreach ( $row_data as $key => $value ) {
            if ( strtolower( trim( $key ) ) === 'asset filename' ) {
                $asset_filename = trim( $value );
                break;
            }
        }
        
        if ( empty( $asset_filename ) ) {
            return new WP_Error(
                'empty_asset_filename',
                'Asset Filename is empty.'
            );
        }

        // Find documents by asset filename
        $documents = $this->find_documents_by_filename( $asset_filename );
        
        if ( empty( $documents ) ) {
            return [
                'asset_filename'       => $asset_filename,
                'documents_found'      => 0,
                'documents_not_found'  => 1,
                'tags_applied'         => 0,
                'metadata_applied'     => [],
                'status'               => 'no_documents_found',
            ];
        }

        $tags_applied = 0;
        $metadata_applied = [];

        // Process each found document
        foreach ( $documents as $document ) {
            $document_result = $this->apply_metadata_to_document( $document, $row_data, $field_map, $normalized_field_map, $options );
            
            if ( is_wp_error( $document_result ) ) {
                continue; // Skip this document but continue with others
            }

            $tags_applied += $document_result['tags_applied'];
            $metadata_applied = array_merge( $metadata_applied, $document_result['metadata_applied'] );
        }

        return [
            'asset_filename'       => $asset_filename,
            'documents_found'      => count( $documents ),
            'documents_not_found'  => 0,
            'tags_applied'         => $tags_applied,
            'metadata_applied'     => $metadata_applied,
            'status'               => 'success',
        ];
    }

    /**
     * Find documents by slug (post_name).
     *
     * @param string $asset_filename Asset filename from CSV (should match post slug).
     * @return array Array of document posts.
     */
    private function find_documents_by_filename( string $asset_filename ) {
        // Normalize filename for matching (remove path, trim whitespace)
        $filename_normalized = trim( basename( $asset_filename ) );
        
        // Remove extension to get the slug (slugs typically don't have extensions like .pdf)
        $slug_candidate = pathinfo( $filename_normalized, PATHINFO_FILENAME );
        
        // WordPress slugs are typically lowercase, so try both original and lowercase
        $slug_candidates = [
            $slug_candidate,                    // Original case
            strtolower( $slug_candidate ),     // Lowercase
            sanitize_title( $slug_candidate ), // WordPress-sanitized version
        ];
        
        // Remove duplicates
        $slug_candidates = array_unique( $slug_candidates );
        
        // First try exact match on post slug (post_name) without extension
        foreach ( $slug_candidates as $slug ) {
            if ( empty( $slug ) ) {
                continue;
            }
            
            $query = new \WP_Query( [
                'post_type'      => $this->config->get_post_type(),
                'post_status'    => 'any', // Search all statuses
                'posts_per_page' => -1,
                'name'           => $slug, // Match by post slug
            ] );
            
            if ( $query->have_posts() ) {
                return $query->posts;
            }
        }

        // If no match, try with the full filename (in case the slug includes extension)
        foreach ( [ $filename_normalized, strtolower( $filename_normalized ) ] as $full_filename ) {
            $query = new \WP_Query( [
                'post_type'      => $this->config->get_post_type(),
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'name'           => $full_filename, // Match by post slug with extension
            ] );
            
            if ( $query->have_posts() ) {
                return $query->posts;
            }
        }

        // Fallback: try matching by document_file_name meta as backup
        $query = new \WP_Query( [
            'post_type'      => $this->config->get_post_type(),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'document_file_name',
                    'value'   => $filename_normalized,
                    'compare' => '=',
                ],
            ],
        ] );

        return $query->posts ?? [];
    }

    /**
     * Get a field value from row data case-insensitively.
     *
     * @param array  $row_data Row data.
     * @param string $field_name Field name to find.
     * @return string|null Field value or null if not found.
     */
    private function get_field_value_case_insensitive( array $row_data, string $field_name ): ?string {
        $field_name_lower = strtolower( trim( $field_name ) );
        foreach ( $row_data as $key => $value ) {
            if ( strtolower( trim( $key ) ) === $field_name_lower ) {
                return trim( $value );
            }
        }
        return null;
    }

    /**
     * Apply metadata to a document.
     *
     * @param \WP_Post $document Document post.
     * @param array    $row_data CSV row data.
     * @param array    $field_map Metadata field map.
     * @param array    $normalized_field_map Normalized field map for case-insensitive lookup.
     * @param array    $options Processing options.
     * @return array|WP_Error Result or error.
     */
    private function apply_metadata_to_document( \WP_Post $document, array $row_data, array $field_map, array $normalized_field_map, array $options ) {
        $tags_applied = 0;
        $metadata_applied = [];

        // Handle special fields first
        $post_updates = [];

        // Update post title if Title field is provided (case-insensitive)
        $title_value = $this->get_field_value_case_insensitive( $row_data, 'Title' );
        if ( ! empty( $title_value ) ) {
            $post_updates['post_title'] = sanitize_text_field( $title_value );
            // Preserve the existing slug (post_name) - don't let WordPress regenerate it
            $post_updates['post_name'] = $document->post_name;
        }

        // Update post excerpt if Excerpt field is provided (case-insensitive)
        $excerpt_value = $this->get_field_value_case_insensitive( $row_data, 'Excerpt' );
        if ( ! empty( $excerpt_value ) ) {
            $post_updates['post_excerpt'] = sanitize_textarea_field( $excerpt_value );
        }

        // Update post date if Date field is provided (case-insensitive)
        $date_value = $this->get_field_value_case_insensitive( $row_data, 'Date' );
        if ( ! empty( $date_value ) ) {
            $timestamp = strtotime( $date_value );
            if ( false !== $timestamp ) {
                $post_updates['post_date'] = date( 'Y-m-d H:i:s', $timestamp );
            }
        }

        // Apply post updates if any
        if ( ! empty( $post_updates ) ) {
            $post_updates['ID'] = $document->ID;
            // Prevent slug regeneration by using wp_update_post with post_name preserved
            $update_result = wp_update_post( $post_updates, true );
            
            if ( is_wp_error( $update_result ) ) {
                // Log error but continue with metadata updates
                $metadata_applied[] = [
                    'field'  => 'post_fields',
                    'value'  => implode( ', ', array_keys( $post_updates ) ),
                    'status' => 'failed',
                    'error'  => $update_result->get_error_message(),
                ];
            } else {
                $tags_applied++;
                foreach ( array_keys( $post_updates ) as $field ) {
                    if ( 'ID' !== $field ) {
                        $metadata_applied[] = [
                            'field' => $field,
                            'value' => $post_updates[ $field ],
                            'status' => 'applied',
                        ];
                    }
                }
            }
        }

        // Process metadata fields
        foreach ( $row_data as $column => $value ) {
            $column = trim( $column );
            $value = trim( $value );

            // Skip empty values
            if ( empty( $value ) ) {
                continue;
            }

            // Skip special columns (case-insensitive)
            $column_lower = strtolower( $column );
            if ( in_array( $column_lower, [ 'asset filename', 'title', 'excerpt', 'date' ], true ) ) {
                continue;
            }

            // Check if this column corresponds to a metadata field (try exact match first)
            $field = null;
            $field_id = null;
            
            if ( isset( $field_map[ $column ] ) ) {
                $field = $field_map[ $column ];
                $field_id = $field['id'];
            } else {
                // Try normalized lookup (case-insensitive and sanitized)
                $column_normalized = strtolower( trim( $column ) );
                $column_sanitized = sanitize_title( $column );
                // Also try with underscores instead of hyphens (for field IDs like "supervisory_review")
                $column_sanitized_underscore = str_replace( '-', '_', $column_sanitized );
                
                if ( isset( $normalized_field_map[ $column_normalized ] ) ) {
                    $field = $normalized_field_map[ $column_normalized ];
                    $field_id = $field['id'];
                } elseif ( isset( $normalized_field_map[ $column_sanitized ] ) ) {
                    // Try sanitized version with hyphens
                    $field = $normalized_field_map[ $column_sanitized ];
                    $field_id = $field['id'];
                } elseif ( isset( $normalized_field_map[ $column_sanitized_underscore ] ) ) {
                    // Try sanitized version with underscores (handles "Supervisory Review" -> "supervisory_review")
                    $field = $normalized_field_map[ $column_sanitized_underscore ];
                    $field_id = $field['id'];
                }
            }
            
            // If still not found, try to save it as metadata anyway
            if ( ! $field ) {
                try {
                    // Get metadata fields for field ID lookup
                    $all_metadata_fields = $this->metadata_manager->get_metadata_fields();
                    
                    // Try to find the field using the custom matching logic
                    $result = $this->apply_custom_metadata_field( $document->ID, $column, $value, $field_map );
                    
                    if ( $result ) {
                        $tags_applied++;
                        // Try to determine the actual field ID that was used
                        $actual_field_id = $this->find_field_id_by_name( $column, $all_metadata_fields );
                        $metadata_applied[] = [
                            'field' => $actual_field_id ? $actual_field_id : $column,
                            'value' => $value,
                            'status' => 'applied',
                        ];
                    } else {
                        // Field not found and couldn't be saved
                        $metadata_applied[] = [
                            'field'  => $column,
                            'value'  => $value,
                            'status' => 'failed',
                            'error'  => 'Field not found in metadata fields and could not be saved',
                        ];
                    }
                } catch ( \Exception $e ) {
                    $metadata_applied[] = [
                        'field'  => $column,
                        'value'  => $value,
                        'status' => 'failed',
                        'error'  => $e->getMessage(),
                    ];
                }
                continue;
            }

            try {
                $result = $this->apply_metadata_field( $document->ID, $field, $value );
                
                if ( $result ) {
                    $tags_applied++;
                    $metadata_applied[] = [
                        'field' => $field_id,
                        'value' => $value,
                        'status' => 'applied',
                    ];
                }
            } catch ( \Exception $e ) {
                $metadata_applied[] = [
                    'field'  => $field_id,
                    'value'  => $value,
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ];
            }
        }

        return [
            'tags_applied'     => $tags_applied,
            'metadata_applied' => $metadata_applied,
        ];
    }

    /**
     * Find field ID by column name using various matching strategies.
     *
     * @param string $column_name CSV column name.
     * @param array  $metadata_fields All metadata fields.
     * @return string|null Field ID if found, null otherwise.
     */
    private function find_field_id_by_name( string $column_name, array $metadata_fields ): ?string {
        $column_normalized = strtolower( trim( $column_name ) );
        $column_sanitized = sanitize_title( $column_name );
        $column_sanitized_underscore = str_replace( '-', '_', $column_sanitized );
        
        foreach ( $metadata_fields as $field ) {
            $field_id_normalized = strtolower( trim( $field['id'] ) );
            $field_label_normalized = strtolower( trim( $field['label'] ?? '' ) );
            $field_id_sanitized = sanitize_title( $field['id'] );
            $field_label_sanitized = sanitize_title( $field['label'] ?? '' );
            
            if ( $field['id'] === $column_name ||
                 $field_id_normalized === $column_normalized ||
                 $field_label_normalized === $column_normalized ||
                 $field_id_sanitized === $column_sanitized ||
                 $field_label_sanitized === $column_sanitized ||
                 $field_id_sanitized === $column_sanitized_underscore ||
                 str_replace( '-', '_', $field_id_sanitized ) === $column_sanitized_underscore ) {
                return $field['id'];
            }
        }
        
        return null;
    }

    /**
     * Apply a custom metadata field that may not be in the field map.
     * Tries to match it as a taxonomy or saves as regular metadata.
     *
     * @param int    $document_id Document ID.
     * @param string $field_name Field name.
     * @param mixed  $value Field value.
     * @param array  $field_map Metadata field map.
     * @return bool Whether the field was applied successfully.
     */
    private function apply_custom_metadata_field( int $document_id, string $field_name, $value, array $field_map ) {
        // Normalize field name for matching
        $field_name_normalized = strtolower( trim( $field_name ) );
        $field_name_sanitized = sanitize_title( $field_name );
        
        // First check if it's a taxonomy field by looking for matching field ID or label
        $metadata_fields = $this->metadata_manager->get_metadata_fields();
        
        foreach ( $metadata_fields as $field ) {
            $field_id_normalized = strtolower( trim( $field['id'] ) );
            $field_label_normalized = strtolower( trim( $field['label'] ?? '' ) );
            $field_id_sanitized = sanitize_title( $field['id'] );
            
            // Match by: exact ID, normalized ID, label, or sanitized versions
            if ( $field['id'] === $field_name ||
                 $field_id_normalized === $field_name_normalized ||
                 $field_label_normalized === $field_name_normalized ||
                 $field_id_sanitized === $field_name_sanitized ||
                 sanitize_title( $field['label'] ?? '' ) === $field_name_sanitized ) {
                
                if ( 'taxonomy' === $field['type'] ) {
                    return $this->apply_taxonomy_field( $document_id, $field['id'], $value );
                } else {
                    return $this->apply_regular_metadata_field( $document_id, $field['id'], $value );
                }
            }
        }

        // If not found as a registered field, try to find the actual field ID first
        $metadata_fields = $this->metadata_manager->get_metadata_fields();
        $actual_field_id = $this->find_field_id_by_name( $field_name, $metadata_fields );
        
        if ( $actual_field_id ) {
            // Found the field, save using the actual field ID
            $sanitized_value = sanitize_text_field( $value );
            $result = update_post_meta( $document_id, $actual_field_id, $sanitized_value );
            return false !== $result;
        }
        
        // If still not found, save as regular metadata using the field name
        // This ensures data isn't lost even if fields aren't registered yet
        $sanitized_value = sanitize_text_field( $value );
        $result = update_post_meta( $document_id, $field_name, $sanitized_value );
        
        // Also try saving with sanitized key in case that's how it's stored
        if ( false === $result && $field_name_sanitized !== $field_name ) {
            $result = update_post_meta( $document_id, $field_name_sanitized, $sanitized_value );
        }
        
        return false !== $result;
    }

    /**
     * Apply a single metadata field to a document.
     *
     * @param int   $document_id Document ID.
     * @param array $field Field definition.
     * @param mixed $value Field value.
     * @return bool Whether the field was applied successfully.
     */
    private function apply_metadata_field( int $document_id, array $field, $value ) {
        $field_id = $field['id'];
        $field_type = $field['type'];

        switch ( $field_type ) {
            case 'taxonomy':
                return $this->apply_taxonomy_field( $document_id, $field_id, $value );
            
            case 'text':
            case 'date':
            case 'number':
            default:
                return $this->apply_regular_metadata_field( $document_id, $field_id, $value );
        }
    }

    /**
     * Apply taxonomy field to document.
     *
     * @param int    $document_id Document ID.
     * @param string $field_id Field ID.
     * @param mixed  $value Field value.
     * @return bool Whether the field was applied successfully.
     */
    private function apply_taxonomy_field( int $document_id, string $field_id, $value ) {
        $taxonomy_name = $this->metadata_manager->get_taxonomy_name_for_field( $field_id );
        
        if ( ! $taxonomy_name ) {
            return false;
        }

        // Handle comma-separated values
        $values = is_array( $value ) ? $value : explode( ',', $value );
        $values = array_map( 'trim', $values );
        $values = array_filter( $values );

        if ( empty( $values ) ) {
            return false;
        }

        $term_ids = [];
        
        foreach ( $values as $term_name ) {
            // Try to find existing term
            $term = get_term_by( 'name', $term_name, $taxonomy_name );
            
            if ( ! $term ) {
                // Create new term if it doesn't exist
                $term_result = wp_insert_term( $term_name, $taxonomy_name );
                if ( is_wp_error( $term_result ) ) {
                    continue; // Skip this term
                }
                $term_id = $term_result['term_id'];
            } else {
                $term_id = $term->term_id;
            }
            
            $term_ids[] = $term_id;
        }

        if ( ! empty( $term_ids ) ) {
            $result = wp_set_object_terms( $document_id, $term_ids, $taxonomy_name );
            return ! is_wp_error( $result );
        }

        return false;
    }

    /**
     * Apply regular metadata field to document.
     *
     * @param int    $document_id Document ID.
     * @param string $field_id Field ID.
     * @param mixed  $value Field value.
     * @return bool Whether the field was applied successfully.
     */
    private function apply_regular_metadata_field( int $document_id, string $field_id, $value ) {
        // Validate and sanitize value based on field type
        $field = $this->metadata_manager->get_metadata_fields();
        $field_definition = null;
        
        foreach ( $field as $f ) {
            if ( $f['id'] === $field_id ) {
                $field_definition = $f;
                break;
            }
        }

        if ( $field_definition ) {
            switch ( $field_definition['type'] ) {
                case 'date':
                    // Validate date format
                    $timestamp = strtotime( $value );
                    if ( false === $timestamp ) {
                        return false;
                    }
                    $value = date( 'Y-m-d', $timestamp );
                    break;
                
                case 'number':
                    // Validate number
                    if ( ! is_numeric( $value ) ) {
                        return false;
                    }
                    $value = floatval( $value );
                    break;
                
                case 'text':
                default:
                    $value = sanitize_text_field( $value );
                    break;
            }
        }

        $result = update_post_meta( $document_id, $field_id, $value );
        return false !== $result;
    }

    /**
     * Get CSV template structure.
     *
     * @return array CSV template structure.
     */
    public function get_csv_template() {
        $metadata_fields = $this->metadata_manager->get_metadata_fields();
        
        // Start with required and standard fields
        $template = [
            'Asset Filename' => 'Asset Filename (required - filename of uploaded document)',
            'Title'          => 'Title (post title)',
            'Excerpt'        => 'Excerpt (post excerpt)',
            'Date'           => 'Date (post date)',
            'Category'      => 'Category',
            'Type'          => 'Type',
            'Outcome'       => 'Outcome',
            'Industry'      => 'Industry',
            'Supervisory Review' => 'Supervisory Review',
        ];

        // Add other metadata fields that aren't already in the template
        $existing_fields = array_keys( $template );
        foreach ( $metadata_fields as $field ) {
            // Only add if not already in template
            if ( ! in_array( $field['id'], $existing_fields, true ) && 
                 ! in_array( $field['label'], $existing_fields, true ) ) {
                $template[ $field['id'] ] = $field['label'] . ' (' . $field['type'] . ')';
            }
        }

        return $template;
    }

    /**
     * Download CSV template.
     *
     * @return void
     */
    public function download_csv_template() {
        $template = $this->get_csv_template();
        
        // Set headers for CSV download
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="document-tagging-template.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Output CSV content
        $output = fopen( 'php://output', 'w' );
        
        // Write header row (use keys as column names)
        fputcsv( $output, array_keys( $template ) );
        
        // Write example row
        $example_row = array_map( function( $key, $description ) {
            // Handle specific fields
            if ( 'Asset Filename' === $key ) {
                return 'example-document.pdf';
            }
            if ( 'Title' === $key ) {
                return 'Example Document Title';
            }
            if ( 'Excerpt' === $key ) {
                return 'This is an example excerpt for the document.';
            }
            if ( 'Date' === $key ) {
                return '2024-01-15';
            }
            if ( in_array( $key, [ 'Category', 'Type', 'Outcome', 'Industry', 'Supervisory Review' ], true ) ) {
                return 'Example Value';
            }
            // Handle metadata fields by type
            if ( strpos( $description, 'taxonomy' ) !== false ) {
                return 'Category A, Category B';
            }
            if ( strpos( $description, 'date' ) !== false ) {
                return '2024-01-15';
            }
            if ( strpos( $description, 'number' ) !== false ) {
                return '123';
            }
            return 'Example Value';
        }, array_keys( $template ), $template );
        
        fputcsv( $output, $example_row );
        
        fclose( $output );
        exit;
    }
} 