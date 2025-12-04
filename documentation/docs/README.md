# WordPress Document Repository

A WordPress plugin for uploading, organizing, and managing documents (PDFs) with custom metadata in a streamlined admin UI. It provides a custom post type, REST API endpoints, a React-based admin application, and optional public display enhancements.

## Features

- Custom post type: Documents
- PDF upload with progress and size/type validation
- Custom metadata fields (text, date, taxonomy)
- Automatic taxonomy registration for taxonomy-type fields
- Spreadsheet-style bulk metadata editing
- Trash/restore and delete operations
- Duplicate filename detection endpoint
- Admin UI built with React (Gutenberg components)
- Files stored under uploads/documents with consistent URLs
- Search results link directly to files and show file type/size
- Capability-based access control

## Architecture

- Core orchestrator: `src/DocumentRepository.php`
- Configuration: `src/RepositoryConfig.php`
- Post type and taxonomies: `src/DocumentPostType.php`
- Uploads and attachment handling: `src/DocumentUploader.php`
- REST endpoints: `src/RestApiController.php`
- Metadata fields and caching: `src/DocumentMetadataManager.php`
- Admin assets and bootstrapping: `src/AdminUIManager.php`
- Settings toggle (enable/disable feature): `src/Settings.php`

Front-end/document behavior (search permalink override, title augmentation, publish after untrash) lives in `/wordpress-document-repository.php`

## Admin UI

The admin app mounts under “Document Repository” and “Metadata Settings”:

- Upload documents via drag-and-drop or picker (PDF only)
- Edit metadata inline or in a modal
- Spreadsheet mode for multi-row editing
- Bulk operations: select all, trash, restore, delete
- Status filters: All, Trash
- Upload feedback and notifications

Entry and bundles:

- App entry: `/assets/js/apps/document-repository/index.js`
- Main app: `/assets/js/apps/document-repository/App.js`
- Styles: `/assets/scss/index.scss`

## Metadata

- Define fields in the Metadata Settings UI (text, date, taxonomy).
- Taxonomy fields automatically register a taxonomy with a doc_ prefix.
- Server-side:
  - Fields: `Bcgov\WordpressDocumentRepository\DocumentMetadataManager::get_metadata_fields`
  - Taxonomies: `Bcgov\WordpressDocumentRepository\DocumentPostType::register_metadata_taxonomies`

Hook triggered after metadata fields change:

- Action: `bcgov_document_repository_metadata_fields_updated` (see README.md)

## REST API

Namespace from config: `RepositoryConfig::get_api_namespace` in (src/RepositoryConfig.php) (default: `bcgov-document-repository/v1`).

Endpoints (selected):

- GET `/{namespace}/documents`
  - Query param: `status` (publish, draft, trash, any, all)
- POST `/{namespace}/documents`
  - Uploads a PDF and creates a document post with metadata
- GET `/{namespace}/documents/check-duplicate?search_name={filename}`
  - Returns duplicate status by original file name
- GET `/{namespace}/documents/{id}`
  - Fetch a single document

See controller: `src/RestApiController.php`

Security:

- Nonce: `wp_rest`
- Capability: `RepositoryConfig::get_capability` in `src/RepositoryConfig.php` (default cap: `upload_files`)

## File Storage and Search Behavior

- Files stored under `/uploads/documents`
- Upload directory managed by `DocumentUploader::custom_upload_dir`  in `src/DocumentUploader.php`
- Search:
  - Permalinks for document results link to the file URL
  - Titles include file type and size
  - Logic in `wordpress-document-repository.php`

## Requirements

- WordPress 6.4.4+
- PHP 7.4+
- Composer
- Node 22.16.0
- npm 10.9.2+

## Installation

First, clone the repo and set up the plugin:

```shell
# clone repo
gowp plugins # or manually cd to your plugins directory
git clone https://github.com/bcgov/wordpress-document-repository.git
cd wordpress-document-repository

# Install dependencies:
composer install
npm install

# Build assets:
npm run build
```

After these steps, simply activate the plugin from the WordPress Admin.

## Install and Testing Scripts

### Plugin

- Build plugin assets: `npm run build`
- Production checks (lint/format/phpcs): `composer production`
- PHPUnit (local): `composer test-setup` then `composer test`
- Coverage: `composer coverage`

### Documentation (VuePress)

- Dev: `npm run docs:dev` (in documentation/)
- Build: `npm run docs:build` (in documentation/)

## Configuration

Defaults: `src/RepositoryConfig.php`

- Post type: `document`
- Capability: `upload_files`
- Allowed MIME types: PDF only
- Max file size: 20 MB
- REST namespace: `bcgov-document-repository/v1`
- Per-page default: 20
- Cache disabled by default

Values can be filtered via `bcgov_document_repository_settings`.
