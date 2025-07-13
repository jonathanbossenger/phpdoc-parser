# WP Parser

WP-Parser is the parser for creating the new code reference at [developer.wordpress.org](https://developer.wordpress.org/reference). It parses the inline documentation and produces custom post type entries in WordPress.

The parser supports PHP 8.1+ and uses modern parsing libraries for improved accuracy and maintainability.

## Requirements

* **PHP 8.1+**
* **Node.js 20+** and **npm 9+** (for development environment)
* **Docker** (for wp-env testing environment)
* [Composer](https://getcomposer.org/)

## Quick Start

### 1. Clone the Repository

```bash
git clone https://github.com/WordPress/phpdoc-parser.git
cd phpdoc-parser
```

### 2. Install Dependencies

Install both Node.js and PHP dependencies:

```bash
# Install Node.js dependencies (includes @wordpress/env)
npm install

# Install PHP dependencies
npm run composer:setup
# OR if you have local PHP 8.1+: composer install
```

### 3. Start Development Environment

The project uses [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (wp-env) for local development:

```bash
# Start WordPress environment with Docker
npm start

# Or manually:
npm run wp-env start
```

This will start two WordPress environments:
- **Development**: `http://localhost:8888` (admin: `http://localhost:8888/wp-admin/` - admin/password)
- **Tests**: `http://localhost:8889` (for automated testing only)

### 4. Run Tests

```bash
# Run the full test suite
npm test

# Run tests with setup (first time)
npm run test:phpunit:setup

# Watch tests during development
composer run test:watch
```

## Development

### Architecture

The parser uses:

- **PHP 8.1+ support** with modern language features
- **PHPParser v5** for Abstract Syntax Tree (AST) parsing
- **phpstan/phpdoc-parser** for advanced PHPDoc analysis
- **PHPUnit 9** for testing compatibility with WordPress

### Key Components

- `lib/class-file-reflector.php` - Main file parser using AST traversal
- `lib/class-hook-reflector.php` - WordPress hook detection
- `lib/class-*-reflector.php` - Various reflectors for functions, methods, calls
- `lib/runner.php` - API compatibility layer and export functions

### Running the Parser

The parser runs via WP-CLI commands in the **development environment** (port 8888). There are two ways to run WP-CLI commands:

#### Option 1: Using Host WP-CLI (Recommended)

The project includes a `wp-cli.yml` configuration file that connects to the development environment:

```bash
# Activate plugins (requires wp-cli installed on host)
wp plugin activate phpdoc-parser posts-to-posts

# Parse WordPress core files
wp parser create /var/www/html --user=admin --quick

# Parse specific directory  
wp parser create /path/to/plugin/source --user=admin

# View parsed results at http://localhost:8888/wp-admin/
```

#### Option 2: Using Docker Container

```bash
# Find the development WordPress container ID
WORDPRESS_CONTAINER=$(docker ps --filter "name=wordpress-1" --format "{{.ID}}")

# Activate plugins in development environment
docker exec $WORDPRESS_CONTAINER wp plugin activate phpdoc-parser posts-to-posts

# Parse WordPress core files in development environment
docker exec $WORDPRESS_CONTAINER wp parser create /var/www/html --user=admin --quick
```

**Important**: Always use the development environment (8888) for WP-CLI operations. The test environment (8889) is only for automated PHPUnit tests.

### Testing

The project includes comprehensive tests that validate parsing accuracy:

```bash
# Run all tests
npm test

# Run specific test
npm run test:phpunit -- --filter=test_function_docblocks

# Generate coverage report
composer run test:coverage
```

### Using wp-env Commands

```bash
# Access development WordPress container
npm run wp-env run wordpress bash

# Access test WordPress container (for debugging tests only)
npm run wp-env run tests-wordpress bash

# Run WP-CLI in development environment
npm run wp-env run wordpress wp --info

# Stop environment
npm run wp-env stop

# Reset environment
npm run wp-env clean
```

### Environment Usage

- **Development (localhost:8888)**: Use for plugin development, WP-CLI commands, and manual testing
- **Tests (localhost:8889)**: Automatically used by `npm test` - don't run manual commands here

## Parsed Output

The parser extracts:

- **Functions** with parameters, return types, and docblocks
- **Classes** with methods, properties, and inheritance
- **WordPress Hooks** (actions and filters) with documentation
- **Method/Function calls** and their relationships
- **Namespaces** and class hierarchies
- **PHPDoc tags** (@param, @return, @since, etc.)

Output is compatible with the WordPress.org developer reference format.

## Troubleshooting

### Docker Issues
```bash
# Reset wp-env if having issues
npm run wp-env clean
npm run wp-env start
```

### PHP Version Issues
The parser requires PHP 8.1+. If you see PHP version errors:
- Ensure Docker is running (wp-env uses PHP 8.2 in containers)
- For local development, install PHP 8.1+ locally

### Memory Issues
For large codebases, you may need to increase PHP memory:
```bash
# In wp-env container
npm run wp-env run tests-wordpress bash
php -d memory_limit=512M vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for development guidelines, coding standards, and how to submit contributions.

## Resources

- [WordPress Developer Handbook](https://make.wordpress.org/docs/handbook/projects/devhub/)
- [WordPress.org Developer Reference](https://developer.wordpress.org/reference/)
- [@wordpress/env Documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- [PHPParser Documentation](https://github.com/nikic/PHP-Parser)
- [PHPStan PHPDoc Parser](https://github.com/phpstan/phpdoc-parser)