# WP Parser

WP-Parser is the parser for creating the new code reference at [developer.wordpress.org](https://developer.wordpress.org/reference). It parses the inline documentation and produces custom post type entries in WordPress.

There is a guide to developing for developer.wordpress.org in the [WordPress documentation handbook](https://make.wordpress.org/docs/handbook/projects/devhub/)

## Requirements

* **PHP 8.1+**
* **Node.js 20+** and **npm 9+** (for development environment)
* **Docker** (for wp-env testing environment)
* **Composer**

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
