<?php
/**
 * CSV File Splitter Utility
 * 
 * Splits a large CSV file into smaller chunks to avoid timeout issues.
 * 
 * Usage: php split-csv.php input.csv [rows_per_file]
 * 
 * Example: php split-csv.php large-file.csv 500
 */

if ( $argc < 2 ) {
    echo "Usage: php split-csv.php <input_file> [rows_per_file]\n";
    echo "Example: php split-csv.php myfile.csv 500\n";
    exit( 1 );
}

$input_file = $argv[1];
$rows_per_file = isset( $argv[2] ) ? (int) $argv[2] : 500;

if ( ! file_exists( $input_file ) ) {
    echo "Error: File '$input_file' not found.\n";
    exit( 1 );
}

if ( $rows_per_file < 1 ) {
    echo "Error: Rows per file must be at least 1.\n";
    exit( 1 );
}

echo "Splitting '$input_file' into files with $rows_per_file rows each...\n";

// Open input file
$handle = fopen( $input_file, 'r' );
if ( ! $handle ) {
    echo "Error: Could not open input file.\n";
    exit( 1 );
}

// Read and save header row
$header = fgetcsv( $handle );
if ( $header === false ) {
    echo "Error: Could not read header row.\n";
    fclose( $handle );
    exit( 1 );
}

$file_number = 1;
$row_count = 0;
$output_handle = null;
$base_name = pathinfo( $input_file, PATHINFO_FILENAME );
$directory = dirname( $input_file ) ?: '.';

// Process rows
while ( ( $row = fgetcsv( $handle ) ) !== false ) {
    // Start new file if needed
    if ( $row_count === 0 ) {
        // Close previous file if open
        if ( $output_handle ) {
            fclose( $output_handle );
        }
        
        // Open new output file
        $output_filename = $directory . '/' . $base_name . '_part' . $file_number . '.csv';
        $output_handle = fopen( $output_filename, 'w' );
        
        if ( ! $output_handle ) {
            echo "Error: Could not create output file '$output_filename'.\n";
            fclose( $handle );
            exit( 1 );
        }
        
        // Write header to new file
        fputcsv( $output_handle, $header );
        
        echo "Creating: $output_filename\n";
    }
    
    // Write row to current file
    fputcsv( $output_handle, $row );
    $row_count++;
    
    // Move to next file if we've reached the limit
    if ( $row_count >= $rows_per_file ) {
        fclose( $output_handle );
        $output_handle = null;
        $row_count = 0;
        $file_number++;
    }
}

// Close last file if still open
if ( $output_handle ) {
    fclose( $output_handle );
}

fclose( $handle );

echo "\nDone! Split into $file_number file(s).\n";
echo "Files created:\n";
for ( $i = 1; $i <= $file_number; $i++ ) {
    $filename = $directory . '/' . $base_name . '_part' . $i . '.csv';
    if ( file_exists( $filename ) ) {
        $line_count = count( file( $filename ) ) - 1; // Subtract header
        echo "  - $filename ($line_count rows)\n";
    }
}

