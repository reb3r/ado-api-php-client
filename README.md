# Azure DevOps API PHP Client

A PHP library to interact with the Azure DevOps REST API. This library provides an easy-to-use interface for common Azure DevOps operations including work items, teams, projects, queries, and more.

[![PHP Composer](https://github.com/reb3r/ado-api-php-client/actions/workflows/php.yml/badge.svg)](https://github.com/reb3r/ado-api-php-client/actions/workflows/php.yml)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Freb3r%2Fado-api-php-client%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/reb3r/ado-api-php-client/main)

## Requirements

- PHP 8.2 or higher
- Composer

## Installation

Install this package via Composer:

```bash
composer require reb3r/ado-api-php-client
```

## Dependencies

This library depends on:
- [Guzzle HTTP Client](https://github.com/guzzle/guzzle) (^7.10)
- [Illuminate Collections](https://github.com/illuminate/collections) (^12.0)

For detailed information, see [composer.json](composer.json).

## Configuration

Create a new instance of the `AzureDevOpsApiClient` with your Azure DevOps credentials:

```php
use Reb3r\ADOAPC\AzureDevOpsApiClient;

$client = new AzureDevOpsApiClient(
    username: 'your-username',           // Leave empty for PAT authentication
    secret: 'your-personal-access-token',
    base_url: 'https://dev.azure.com/',
    organization: 'your-organization',
    project: 'your-project'
);
```

### Authentication Methods

**Personal Access Token (PAT)** - Recommended:
```php
$client = new AzureDevOpsApiClient('', $pat, $baseUrl, $organization, $project);
```

**Basic Authentication**:
```php
$client = new AzureDevOpsApiClient($username, $password, $baseUrl, $organization, $project);
```

## Usage

### Work Items

```php
// Create a bug
$attachments = collect([]);
$tags = collect(['bug', 'frontend']);
$bug = $client->createBug('Bug title', 'Description', $attachments, $tags);

// Search for a work item
$workitem = $client->searchWorkitem('search text');

// Get work items by IDs
$workitems = $client->getWorkitemsById([123, 456, 789]);

// Update work item
$client->updateWorkitemReproStepsAndAttachments($workitem, 'Reproduction steps', $attachments);

// Add comment to work item
$client->addCommentToWorkitem($workitem, 'Comment text');
```

### Attachments

```php
// Upload an attachment
$attachment = $client->uploadAttachment('screenshot.png', $fileContent);

// Get image attachment
$response = $client->getImageAttachment($attachmentUrl);
```

### Teams

```php
// Get all teams
$teams = $client->getAllTeams();

// Get specific team
$team = $client->getTeam($teamId);

// Get team by name
$teamId = $client->getTeamIdByName('Team Name');

// Get current iteration path
$iterationPath = $client->getCurrentIterationPath($team);
```

### Backlogs

```php
// Get backlogs for a team
$backlogs = $client->getBacklogs($teamName);

// Get work items in a backlog
$workitems = $client->getBacklogWorkItems($team, $backlogId);
```

### Projects

```php
// Get all projects
$projects = $client->getProjects();

// Get specific project
$project = $client->getProject($projectId);

// Get work item types
$workItemTypes = $client->getWorkItemTypes();
```

### Queries

```php
// Get root query folders
$queryFolders = $client->getRootQueryFolders($depth = 2);

// Get all queries
$queries = $client->getAllQueries();

// Execute a query
$results = $client->getQueryResultById($team, $queryId);
```

## Features

This library currently supports the following Azure DevOps API operations:

- ✅ Work Items (create, read, update, search)
- ✅ Attachments (upload, download)
- ✅ Comments
- ✅ Teams (list, get)
- ✅ Projects (list, get)
- ✅ Backlogs
- ✅ Iterations
- ✅ Queries
- ✅ Work Item Types
- ✅ Tags

## Development

### Running Tests

This package uses PHPUnit for testing:

```bash
vendor/bin/phpunit
```

Run with coverage:

```bash
vendor/bin/phpunit --coverage-html build/html-coverage
```

### Static Analysis

Run PHPStan for static code analysis:

```bash
vendor/bin/phpstan analyse
```

### Mutation Testing

This project uses Infection for mutation testing to ensure test quality:

```bash
./infection.phar --min-msi=40 --min-covered-msi=60 --threads=4
```

## Code Quality

- **Test Coverage**: High test coverage maintained
- **Mutation Testing**: Tracked via Stryker Mutator dashboard
- **Static Analysis**: PHPStan Level 6
- **PHP Standards**: PSR-4 autoloading

## Status

This library is in active development. It currently supports the API endpoints needed for common Azure DevOps operations. While it's being used in production projects, the API may still evolve.

### Known Areas for Improvement

- Enhanced type safety with generics
- More comprehensive DTOs (Data Transfer Objects)
- Additional API endpoint coverage
- Improved error handling

## Getting Help

If you have questions, concerns, or bug reports, please [file an issue](https://github.com/reb3r/ado-api-php-client/issues) in this repository's Issue Tracker.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the [GPL-3.0-or-later](LICENSE) license.

## Author

**Christian Reber**
- Email: info@christianreber.eu
- GitHub: [@reb3r](https://github.com/reb3r)
