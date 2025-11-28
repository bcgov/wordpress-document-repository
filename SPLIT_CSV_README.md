# CSV File Splitter Utility

This utility script helps you split large CSV files into smaller chunks to avoid timeout issues when uploading to the Document Repository.

## Usage

```bash
php split-csv.php <input_file> [rows_per_file]
```

### Parameters

- `input_file`: The CSV file you want to split (required)
- `rows_per_file`: Number of rows per output file (optional, default: 500)

### Examples

Split a file into chunks of 500 rows each (default):
```bash
php split-csv.php large-file.csv
```

Split a file into chunks of 1000 rows each:
```bash
php split-csv.php large-file.csv 1000
```

Split a file into chunks of 250 rows each:
```bash
php split-csv.php large-file.csv 250
```

## Output

The script will create multiple files with the naming pattern:
- `originalname_part1.csv`
- `originalname_part2.csv`
- `originalname_part3.csv`
- etc.

Each file will include the header row from the original file, so they can be processed independently.

## Example

If you have a file called `documents.csv` with 2000 rows:

```bash
php split-csv.php documents.csv 500
```

This will create:
- `documents_part1.csv` (500 rows + header)
- `documents_part2.csv` (500 rows + header)
- `documents_part3.csv` (500 rows + header)
- `documents_part4.csv` (500 rows + header)

You can then upload each part separately through the CSV Bulk Uploader.

## Notes

- The original file is not modified
- Each output file includes the header row
- Files are created in the same directory as the input file
- The script preserves all CSV formatting and special characters

