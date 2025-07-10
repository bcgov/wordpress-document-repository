# WordPress Plugin Template

This repository provides a modern, well-structured template for developing WordPress plugins. It's designed to help developers quickly bootstrap new WordPress plugins with best practices, modern development tools, and a standardized structure.

## Features

- Modern development environment with Composer for PHP dependencies
- Block editor (Gutenberg) support with built-in block registration
- ESLint configuration for JavaScript/TypeScript development
- GitHub Actions workflows for automated testing and deployment
- Standardized plugin structure following WordPress coding standards
- Apache 2.0 License

## Prerequisites

- PHP 7.4 or higher
- WordPress 6.4.4 or higher
- Composer
- Node.js and npm (for block development)

## Getting Started

1. **Create a new repository from this template**
   - Click the "Use this template" button on GitHub
   - Name your new repository
   - Clone the new repository to your local machine

2. **Update plugin information**
   - Edit `plugin.php` and replace the placeholder values:
     - `{Plugin Name}` with your plugin name
     - `{plugin-name}` with your plugin slug
     - `{PluginName}` with your plugin's namespace
     - Update the description and other metadata

3. **Install dependencies**
   ```bash
   composer install
   ```

4. **Development**
   - Place your PHP classes in the `src` directory
   - Create new blocks in the `Blocks` directory
   - Use the provided namespace structure: `Bcgov\{PluginName}\{ClassName}`

5. **Building blocks**
   ```bash
   npm install
   npm run build
   ```

## Project Structure

```
├── Blocks/           # Gutenberg blocks
├── src/             # PHP source files
├── vendor/          # Composer dependencies
├── workflows/       # GitHub Actions workflows
├── plugin.php       # Main plugin file
├── composer.json    # PHP dependencies
└── package.json     # Node.js dependencies
```

## Development Guidelines

1. **PHP Development**
   - Follow PSR-4 autoloading standards
   - Use namespaces for all classes
   - Document your code with PHPDoc comments

2. **Block Development**
   - Create new blocks in the `Blocks` directory
   - Use block.json for block registration
   - Follow WordPress block development best practices

3. **Testing**
   - Write unit tests for your PHP code
   - Test blocks in the WordPress block editor
   - Ensure compatibility with WordPress versions specified in plugin.php

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This template is licensed under the Apache License 2.0. See the [LICENSE](LICENSE) file for details.

## Support

For questions or issues, please create an issue in the repository.
