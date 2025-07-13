# Contributing to WP Parser

Thank you for your interest in contributing to WP Parser! This document provides guidelines for contributing to the project.

## Getting Started

### Prerequisites

Before you begin, ensure you have:

- **PHP 8.1+** installed locally (for development outside Docker)
- **Node.js 20+** and **npm 9+**
- **Docker Desktop** (for wp-env testing environment)
- **Git** for version control
- Basic understanding of PHP, WordPress development, and PHPDoc

### Development Setup

1. **Fork and Clone**
   ```bash
   git clone https://github.com/your-username/phpdoc-parser.git
   cd phpdoc-parser
   ```

2. **Install Dependencies**
   ```bash
   # Install Node.js dependencies
   npm install
   
   # Install PHP dependencies via wp-env
   npm run composer:setup
   ```

3. **Start Development Environment**
   ```bash
   # Start WordPress with Docker
   npm start
   
   # Verify everything works
   npm test
   ```

## Development Workflow

### Making Changes

1. **Create a branch** for your feature or bug fix:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/issue-description
   ```

2. **Make your changes** following the coding standards below

3. **Test your changes**:
   ```bash
   # Run full test suite
   npm test
   
   # Run specific tests
   npm run test:phpunit -- --filter=YourTestName
   
   # Watch tests during development
   composer run test:watch
   ```

4. **Commit your changes** with descriptive messages:
   ```bash
   git add .
   git commit -m "Add support for parsing readonly properties"
   ```

### Testing

All contributions must include appropriate tests:

- **Unit tests** for new parser functionality
- **Integration tests** for WordPress-specific features
- **Regression tests** for bug fixes

```bash
# Run tests in different ways
npm test                          # Full test suite
npm run test:phpunit:setup       # First-time setup + tests
composer run test:coverage       # Generate coverage report

# Debug failing tests
npm run test:phpunit -- --filter=test_name --debug
```

### Code Quality

Before submitting, ensure your code passes all quality checks:

```bash
# Currently, the project doesn't have linting configured
# But ensure your code follows WordPress coding standards
```

## Coding Standards

### PHP Code Style

Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):

- **Indentation**: Use tabs, not spaces
- **Line length**: Aim for 100 characters max
- **Naming**: Use snake_case for functions/variables, PascalCase for classes
- **Documentation**: All public methods must have PHPDoc blocks

```php
/**
 * Parse a function node and extract function information.
 *
 * @param Node\Stmt\Function_ $node The function node.
 * @return array Function data with name, parameters, and docblock.
 */
protected function processFunction( Node\Stmt\Function_ $node ) {
    // Implementation
}
```

### Documentation

- **PHPDoc blocks** required for all public methods
- **Inline comments** for complex logic
- **README updates** for new features
- **Example usage** in docblocks when helpful

### Git Commit Messages

Use clear, descriptive commit messages:

```bash
# Good
git commit -m "Add namespace detection to File_Reflector"
git commit -m "Fix property docblock parsing for static properties" 
git commit -m "Update README with Docker setup instructions"

# Avoid
git commit -m "Fix bug"
git commit -m "Update stuff"
git commit -m "WIP"
```

## Architecture Guidelines

### Core Components

Understanding the parser architecture helps when contributing:

```
lib/
├── class-file-reflector.php      # Main AST parser (NodeVisitorAbstract)
├── class-hook-reflector.php      # WordPress hook detection
├── class-function-call-reflector.php # Function call parsing
├── class-method-call-reflector.php   # Instance method calls
├── class-static-method-call-reflector.php # Static method calls
├── runner.php                    # API compatibility layer
├── class-importer.php           # WordPress post creation
└── template.php                 # Output formatting
```

### Adding New Parsing Features

When adding new parser functionality:

1. **Extend File_Reflector** if it needs AST traversal
2. **Create dedicated reflector classes** for complex parsing
3. **Update runner.php** to maintain API compatibility
4. **Add comprehensive tests** in `tests/phpunit/tests/`

### Parsing Approach

The parser uses modern PHP libraries:

- **PHPParser v5**: AST-based parsing for accuracy
- **phpstan/phpdoc-parser**: Advanced PHPDoc understanding
- **NodeVisitorAbstract**: Traverse syntax trees efficiently

```php
// Example: Adding new node type handling
public function enterNode( Node $node ) {
    switch ( $node->getType() ) {
        case 'Stmt_YourNewType':
            $this->processYourNewType( $node );
            break;
    }
}
```

## WordPress Integration

### Testing with wp-env

The project uses [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) for WordPress integration:

```bash
# WordPress environment management
npm run wp-env start    # Start containers
npm run wp-env stop     # Stop containers  
npm run wp-env clean    # Reset everything

# Access WordPress container
npm run wp-env run tests-wordpress bash

# Run WP-CLI commands
npm run wp-env run tests-wordpress wp plugin list
```

### Parser WordPress Integration

- **Plugin activation**: Tests run in a real WordPress environment
- **Post creation**: Parsed data becomes WordPress posts
- **Hook detection**: WordPress-specific action/filter parsing
- **Database storage**: Results stored as custom post types

## Common Tasks

### Adding Support for New PHP Features

Example: Adding enum support

1. **Update File_Reflector** to detect enum nodes:
   ```php
   case 'Stmt_Enum':
       $this->processEnum( $node );
       break;
   ```

2. **Create processing method**:
   ```php
   protected function processEnum( Node\Stmt\Enum_ $node ) {
       // Extract enum data
   }
   ```

3. **Update export functions** in runner.php
4. **Add test cases** for various enum scenarios

### Improving WordPress Hook Detection

Hook detection happens in `isFilter()` method:

```php
protected function isFilter( Node\Expr\FuncCall $node ) {
    $function_name = $node->name->toString();
    return in_array( $function_name, [
        'apply_filters',
        'do_action', 
        'add_filter',
        'add_action'
        // Add new hook functions here
    ], true );
}
```

### Debugging Parser Issues

1. **Add debug logging**:
   ```php
   error_log( 'Debug: Found node type: ' . $node->getType() );
   ```

2. **Inspect AST structure**:
   ```bash
   # Use php-parse to see AST
   vendor/bin/php-parse /path/to/file.php
   ```

3. **Check test output**:
   ```bash
   npm run test:phpunit -- --filter=failing_test --debug
   ```

## Submitting Contributions

### Pull Request Process

1. **Ensure tests pass**: `npm test` should be green
2. **Update documentation** if needed
3. **Create detailed PR description**:
   - What changes were made
   - Why they were needed  
   - How to test the changes
   - Any breaking changes

4. **Link related issues**: Use "Fixes #123" in description

### PR Review Criteria

Your PR will be reviewed for:

- **Functionality**: Does it work as intended?
- **Testing**: Are there adequate tests?
- **Code quality**: Follows coding standards?
- **Documentation**: Is it properly documented?
- **WordPress compatibility**: Works with WordPress ecosystem?
- **Performance**: No significant performance regressions?

## Getting Help

### Resources

- **WordPress Developer Handbook**: https://make.wordpress.org/docs/handbook/projects/devhub/
- **PHPParser Documentation**: https://github.com/nikic/PHP-Parser/tree/master/doc
- **phpstan/phpdoc-parser**: https://github.com/phpstan/phpdoc-parser
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/

### Community

- **GitHub Issues**: For bug reports and feature requests
- **WordPress Slack**: #docs channel for general discussion
- **GitHub Discussions**: For questions and implementation discussions

### Common Issues

**Docker Problems**:
```bash
npm run wp-env clean
npm run wp-env start
```

**PHP Version Issues**:
- wp-env uses PHP 8.2 in containers
- Ensure Docker is running
- For local development, ensure PHP 8.1+

**Test Failures**:
- Check that WordPress environment is running
- Verify all dependencies are installed
- Look at specific test output for clues

## Release Process

For maintainers:

1. **Update version** in relevant files
2. **Update changelog** with new features/fixes
3. **Tag release**: `git tag v1.x.x`
4. **Create GitHub release** with changelog
5. **Test on WordPress.org** staging environment

Thank you for contributing to WP Parser! Your efforts help improve the WordPress developer experience for thousands of developers worldwide.