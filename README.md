# wordpress-document-repository
This repo contains the code for the Wordpress Document Repository, a plugin that enhances the ability to upload and manage documents on your WordPress site.

## Installation

### Requirements

- WordPress 6.4.4 or higher
- PHP 7.4 or higher
- [Composer](https://getcomposer.org/) for PHP dependency management
- Node 22.16.0

### Setup

1. **Clone the repository into your WordPress plugins directory:**

    ```sh
    git clone https://github.com/bcgov/wordpress-document-repository.git {plugin-directory}/wordpress-document-repository
    ```

2. **Install PHP dependencies using Composer:**

    ```sh
    cd {plugin-directory}/wordpress-document-repository
    composer install
    ```

3. **Install JavaScript dependencies and build assets:**

    ```sh
    npm install
    npm run build
    ```

4. **Activate the plugin:**
    - Go to the WordPress admin dashboard.
    - Navigate to **Plugins**.
    - Find **WordPress Document Repository** and click **Activate**.

### Updating

After **pulling new changes**, always run:

```sh
composer install
npm install
npm run build
```

Before **pushing changes**, always run:

```sh
npm run build
composer production
```
---
## Custom Hooks

### bcgov_document_repository_metadata_fields_updated

**Description:**  
Triggered whenever the metadata fields for the Document Repository are updated (e.g., when a field is added, edited, or deleted).

**When it's triggered:**  
- After saving, updating, or deleting a metadata field.

**Parameters:**  
None.

**Usage Example:**
```php
add_action( 'bcgov_document_repository_metadata_fields_updated', function() {
    // Your custom logic here, e.g., flush cache or re-register fields.
} );
```
---

# TEST FOR PR PURPOSES