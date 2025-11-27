# CSV Bulk Upload & Auto-Tagging Feature

This feature allows you to upload CSV files to automatically tag existing documents in the WordPress Document Repository with metadata, eliminating the need to manually edit each document.

## How It Works

1. **CSV Structure**: Upload a CSV file with a "title" column and metadata fields
2. **Auto-Matching**: The system finds existing documents by title and applies metadata from the CSV
3. **Selective Updates**: Only metadata fields present in the CSV are updated; existing metadata not in the CSV remains unchanged
4. **Bulk Processing**: Process hundreds of documents at once instead of editing them individually

## CSV Format Requirements

### Required Columns
- **`title`**: Document title (required for matching)

### Metadata Columns
- Any column name that matches an existing metadata field ID will be processed
- Supported field types: text, date, number, taxonomy

### Example CSV Structure
```csv
title,author,department,publish_date,status
Annual Report 2024,John Doe,Finance,2024-01-15,Published
Q4 Budget,Jane Smith,Accounting,2024-01-20,Draft
```

## How to Use

### 1. Download CSV Template
- Click the "Download CSV Template" button to get a pre-formatted template
- The template includes all available metadata fields with example values

### 2. Prepare Your CSV
- Fill in the document titles/names in the first column
- Add metadata values in the corresponding columns
- Save as a CSV file

### 3. Upload and Process
- Click "Choose CSV File" and select your prepared CSV
- Review processing options
- Click "Process CSV" to start bulk tagging

## Processing Options

- **Create missing taxonomy terms automatically**: When enabled, new taxonomy terms will be created if they don't exist
- **Overwrite existing metadata**: Choose whether to replace existing metadata or skip it

## What Happens During Processing

1. **Validation**: CSV file is checked for format and structure
2. **Document Matching**: System searches for documents by title/filename
3. **Metadata Application**: Metadata fields are applied to matching documents
4. **Results Summary**: You get a detailed report of what was processed

## Processing Results

After processing, you'll see:
- Total rows processed
- Documents found and tagged
- Successful tags applied
- Documents not found
- Any errors that occurred

## Error Handling

The system provides detailed error reporting:
- Row-by-row error details
- Specific error messages for each issue
- Suggestions for fixing common problems

## Supported Metadata Types

### Text Fields
- Simple text input
- Automatically sanitized

### Date Fields
- Format: YYYY-MM-DD
- Automatically validated

### Number Fields
- Numeric values only
- Automatically converted to proper format

### Taxonomy Fields
- Comma-separated values supported
- New terms created automatically (if enabled)
- Existing terms reused

## Best Practices

1. **Backup First**: Always backup your data before bulk operations
2. **Test Small**: Test with a few documents first
3. **Check Titles**: Ensure document titles in CSV match exactly
4. **Validate Data**: Verify metadata values are in correct format
5. **Review Results**: Always check the processing results

## Troubleshooting

### Common Issues

**Documents Not Found**
- Check if document titles in CSV match exactly with existing document titles (including case)
- Verify documents exist and are published
- Check for extra spaces or special characters in titles

**Metadata Not Applied**
- Ensure column names match metadata field IDs exactly
- Check if metadata fields are properly configured
- Verify field types match expected values

**CSV Format Errors**
- Ensure file is saved as CSV format
- Check for proper comma separation
- Verify no special characters in headers

## Technical Details

- **File Size Limit**: 10MB maximum
- **Supported Formats**: CSV files only
- **Encoding**: UTF-8 recommended
- **Processing**: Server-side processing with progress tracking
- **Security**: Nonce verification and permission checks

## API Endpoints

The feature adds two new REST API endpoints:

- `POST /wp-json/wp-document-repository/v1/csv-bulk-upload` - Process CSV upload
- `GET /wp-json/wp-document-repository/v1/csv-bulk-upload/template` - Download template

## Permissions

Users must have the `edit_documents` capability to use this feature.

## Integration

This feature integrates seamlessly with:
- Existing document metadata system
- Document status management
- Bulk operations interface
- Notification system 