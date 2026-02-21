# Claude Code Project Context

## Project Overview

This is a PHP client for the Azure DevOps REST API. The library provides an easy-to-use interface for common Azure DevOps operations. It should use API version 7.1.

## Project Structure

```
src/
├── AzureDevOpsApiClient.php   # Main API client
├── Models/                     # Data models (Workitem, Tag, Team, Project, etc.)
└── Repository/                 # Repository classes (WorkitemRepository)

tests/                          # PHPUnit tests
docs/                          # Documentation
```

## Important Files

- **src/AzureDevOpsApiClient.php**: Central client class with all API methods
- **src/Repository/WorkitemRepository.php**: Repository pattern for Work Items
- **src/Models/WorkItemBuilder.php**: Builder pattern for Work Item creation

## Development Guidelines

### Code Quality Standards

- PHP 8.2+ with strict types
- PSR-12 Coding Standard (enforced via PHP_CodeSniffer)
- PHPStan Level 6 for static analysis
- Mutation Testing with Infection (min-msi=40, min-covered-msi=60)
- PSR-4 Autoloading

### Running Tests

```bash
# Unit tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyse

# Code style check (PSR-12 standard)
vendor/bin/phpcs

# Auto-fix code style issues
vendor/bin/phpcbf

# Mutation testing
./infection.phar --min-msi=40 --min-covered-msi=60 --threads=4
```

### Coding Conventions

1. **Type Safety**: Strict type definitions in PHPDoc and signatures
2. **Null-Safety**: Use explicit null checks and null-coalescing
3. **Immutability**: Models should be immutable where possible
4. **Builder Pattern**: Use for complex object creation (see WorkItemBuilder)

## API Architecture

The client is divided into several areas:

- **Work Items**: CRUD operations, search, updates
- **Attachments**: Upload and download
- **Teams**: Team management, iterations
- **Projects**: Project information, Work Item Types
- **Queries**: Execute WIQL queries
- **Backlogs**: Backlog management

## Current Development Goals

- Refactoring collections (current branch: `removeCollection`)
- Improved type-safety with generics
- Enhanced DTOs for all API responses
- Better error handling
- Usage of builder pattern for complex object creation

## Dependencies

- Guzzle HTTP Client (^7.10) for API requests
- Azure DevOps REST API specification can be found [here](https://github.com/MicrosoftDocs/vsts-rest-api-specs) as OpenAPI

## Useful Commands

```bash
# Installation
composer install

# Tests with coverage
vendor/bin/phpunit --coverage-html build/html-coverage

# Check code style (PSR-12 standard)
vendor/bin/phpcs

# Auto-fix code style issues
vendor/bin/phpcbf

# Static analysis
vendor/bin/phpstan analyse --level=6
```
