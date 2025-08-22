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
            $csv_data[] = $row_data;
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
        
        // Check for required columns
        $required_columns = [ 'title', 'name' ]; // At least one of these must exist
        $has_required_column = false;
        
        foreach ( $required_columns as $required_column ) {
            if ( in_array( $required_column, $header, true ) ) {
                $has_required_column = true;
                break;
            }
        }

        if ( ! $has_required_column ) {
            return new WP_Error(
                'csv_missing_required_column',
                sprintf(
                    'CSV must contain at least one of these columns: %s',
                    implode( ', ', $required_columns )
                )
            );
        }

        // Check for metadata columns
        $metadata_fields = $this->metadata_manager->get_metadata_fields();
        $available_metadata_fields = array_column( $metadata_fields, 'id' );
        
        $metadata_columns = array_intersect( $header, $available_metadata_fields );
        
        if ( empty( $metadata_columns ) ) {
            return new WP_Error(
                'csv_no_metadata_columns',
                'CSV must contain at least one metadata column for tagging.'
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

        // Determine title column
        $title_column = null;
        foreach ( [ 'title', 'name' ] as $possible_title_column ) {
            if ( in_array( $possible_title_column, $header, true ) ) {
                $title_column = $possible_title_column;
                break;
            }
        }

        // Process each row
        foreach ( $rows as $row_index => $row_data ) {
            $row_number = $row_index + 2; // +2 because we start after header and arrays are 0-indexed
            
            try {
                $row_result = $this->process_csv_row( $row_data, $title_column, $field_map, $options );
                
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
     * @param string $title_column Title column name.
     * @param array  $field_map Metadata field map.
     * @param array  $options Processing options.
     * @return array|WP_Error Row processing result or error.
     */
    private function process_csv_row( array $row_data, string $title_column, array $field_map, array $options ) {
        $title = trim( $row_data[ $title_column ] ?? '' );
        
        if ( empty( $title ) ) {
            return new WP_Error(
                'empty_title',
                'Document title is empty.'
            );
        }

        // Find documents by title
        $documents = $this->find_documents_by_title( $title );
        
        if ( empty( $documents ) ) {
            return [
                'title'                => $title,
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
            $document_result = $this->apply_metadata_to_document( $document, $row_data, $field_map, $options );
            
            if ( is_wp_error( $document_result ) ) {
                continue; // Skip this document but continue with others
            }

            $tags_applied += $document_result['tags_applied'];
            $metadata_applied = array_merge( $metadata_applied, $document_result['metadata_applied'] );
        }

        return [
            'title'                => $title,
            'documents_found'      => count( $documents ),
            'documents_not_found'  => 0,
            'tags_applied'         => $tags_applied,
            'metadata_applied'     => $metadata_applied,
            'status'               => 'success',
        ];
    }

    /**
     * Find documents by title.
     *
     * @param string $title Document title to search for.
     * @return array Array of document posts.
     */
    private function find_documents_by_title( string $title ) {
        $query = new \WP_Query( [
            'post_type'      => $this->config->get_post_type(),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'document_file_name',
                    'value'   => $title,
                    'compare' => 'LIKE',
                ],
            ],
        ] );

        if ( ! $query->have_posts() ) {
            // Try searching by post title as fallback
            $query = new \WP_Query( [
                'post_type'      => $this->config->get_post_type(),
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                's'              => $title,
            ] );
        }

        return $query->posts ?? [];
    }

    /**
     * Apply metadata to a document.
     *
     * @param \WP_Post $document Document post.
     * @param array    $row_data CSV row data.
     * @param array    $field_map Metadata field map.
     * @param array    $options Processing options.
     * @return array|WP_Error Result or error.
     */
    private function apply_metadata_to_document( \WP_Post $document, array $row_data, array $field_map, array $options ) {
        $tags_applied = 0;
        $metadata_applied = [];

        foreach ( $row_data as $column => $value ) {
            $column = trim( $column );
            $value = trim( $value );

            // Skip empty values and title column
            if ( empty( $value ) || in_array( $column, [ 'title', 'name' ], true ) ) {
                continue;
            }

            // Check if this column corresponds to a metadata field
            if ( ! isset( $field_map[ $column ] ) ) {
                continue;
            }

            $field = $field_map[ $column ];
            $field_id = $field['id'];

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
        
        $template = [
            'title' => 'Document Title or Name',
        ];

        foreach ( $metadata_fields as $field ) {
            $template[ $field['id'] ] = $field['label'] . ' (' . $field['type'] . ')';
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
        
        // Write header row
        fputcsv( $output, array_values( $template ) );
        
        // Write example row
        $example_row = array_map( function( $description ) {
            if ( strpos( $description, 'Document Title' ) !== false ) {
                return 'Example Document.pdf';
            }
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
        }, $template );
        
        fputcsv( $output, $example_row );
        
        fclose( $output );
        exit;
    }
} 