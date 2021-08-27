<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\AzureDevOpsApiClient;
use Reb3r\ADOAPC\Models\Team;
use Reb3r\ADOAPC\Models\Workitem;

final class AzureDevOpsApiClientTest extends TestCase
{
    protected $mockHandler;
    protected $historyContainer;

    protected $apiClient;

    public function setUp(): void
    {
        // See for guzzle testing magic: https://docs.guzzlephp.org/en/stable/testing.html
        $this->historyContainer = [];
        $history = Middleware::history($this->historyContainer);
        $this->mockHandler = new MockHandler([]);

        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $guzzle = new Client(['handler' => $handlerStack]);

        $this->apiClient = new AzureDevOpsApiClient('username', 'secret', 'http://fake/', 'Aveyara', 'project');
        $this->apiClient->setHttpClient($guzzle);
    }

    private function assertAuthorizationInRequests(string $username = 'username', string $secret = 'secret'): void
    {
        /** @var array */
        foreach ($this->historyContainer as $transaction) {
            $expectedValue = 'Basic ' . base64_encode($username . ':' . $secret);
            $this->assertEquals($expectedValue, $transaction['request']->getHeader('Authorization')[0]);
        }
    }

    public function testCreateBug(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/createWorkitems.json')));

        $workitem = $this->apiClient->createBug('Title', 'Description', collect());

        $expectedUri = 'http://fake/Aveyara/project/_apis/wit/workitems/$Bug?api-version=6.1-preview.3';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
    }

    public function testCreateBugError(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Could not create Bug: 300');

        $this->apiClient->createBug('Title', 'Description', collect());

        $expectedUri = 'http://fake/Aveyara/project/_apis/wit/workitems/$Bug?api-version=6.1-preview.3';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
    }

    public function testUploadAttachment(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/uploadAttachmentTextFile.json')));

        $this->apiClient->uploadAttachment('FileName', 'Hello World');

        $expectedUri = 'http://fake/Aveyara/project/_apis/wit/attachments?fileName=FileName&api-version=6.0-preview.3';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
    }

    public function testUploadAttachmentError(): void
    {
        $this->mockHandler->append(new Response(400));

        $this->expectException(RequestException::class);

        $this->apiClient->uploadAttachment('FileName', 'Hello World');

        $expectedUri = 'http://fake/Aveyara/project/_apis/wit/attachments?fileName=FileName&api-version=6.0-preview.3';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
    }

    public function testGetBackblogs(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/backlogs.json')));

        $backlogs = $this->apiClient->getBacklogs('team');

        $expectedUri = 'https://dev.azure.com/Aveyara/project/team/_apis/work/backlogs?api-version=6.0-preview.1';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(4, $backlogs);
    }

    public function testGetBackblogsError(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getBacklogs('team');
    }

    // Docs may be different from real api? test throws exceptions...
    /*
    public function testGetCurrentIterationPath(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/teams.json')));
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/teamsettings.json')));

        $teams = $this->apiClient->getCurrentIterationPath('Quality assurance');

        $expectedUri = 'http://fake/Aveyara/_apis/projects/project/teams?api-version=6.0';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(2, $teams);
        $teams->each(function ($team) {
            $this->assertTrue($team instanceof Team);
        });
    }*/

    public function testGetCurrentIterationPathError(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/teams.json')));
        $this->mockHandler->append(new Response(300, [], file_get_contents(__DIR__ . '/fixtures/teamsettings.json')));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getCurrentIterationPath('Quality assurance');
    }

    public function testGetTeams(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/teams.json')));

        $teams = $this->apiClient->getTeams();

        $expectedUri = 'https://dev.azure.com/Aveyara/_apis/projects/project/teams?api-version=6.0';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(2, $teams);
        $teams->each(function ($team) {
            $this->assertTrue($team instanceof Team);
        });
    }

    public function testGetTeamsError(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getTeams();
    }

    public function testGetAllTeams(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/teams.json')));

        $teams = $this->apiClient->getAllTeams();

        $expectedUri = 'http://fake/Aveyara/_apis/teams?api-version=6.0-preview.3';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(2, $teams);
        $teams->each(function ($team) {
            $this->assertTrue($team instanceof Team);
        });
    }

    public function testGetAllTeamsError(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getAllTeams();
    }

    public function testGetQueriesDepth1(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/queries_depth1.json')));

        $queries = $this->apiClient->getRootQueryFolders(1);

        $expectedUri = 'https://dev.azure.com/Aveyara/project/_apis/wit/queries?$depth=1&api-version=6.0';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(2, $queries);
    }

    public function testGetQueriesDepth1Error(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getRootQueryFolders(1);
    }

    public function testGetQueriesDepthDefault(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/queries_depth1.json')));

        $queries = $this->apiClient->getRootQueryFolders();

        $expectedUri = 'https://dev.azure.com/Aveyara/project/_apis/wit/queries?$depth=0&api-version=6.0';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(2, $queries);
    }

    public function testGetAllQueries(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/queries_depth1.json')));

        $queries = $this->apiClient->getAllQueries();

        $expectedUri = 'https://dev.azure.com/Aveyara/project/_apis/wit/queries?$depth=1&api-version=6.0';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(3, $queries);
    }

    /*
    public function testGetQueryResultById(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/teams.json')));
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/queryResultById.json')));

        $queries = $this->apiClient->getQueryResultById('Quality assurance', 'query-id');

        $expectedUri = 'https://dev.azure.com/Aveyara/project/_apis/wit/queries?$depth=1&api-version=6.0';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(3, $queries);
    }*/

    public function testGetWorkitems(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/workitems.json')));

        $workitems = $this->apiClient->getWorkitemsById([297, 299, 300]);

        $expectedUri = 'http://fake/Aveyara/project/_apis/wit/workitems?api-version=6.0&ids=297,299,300';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(3, $workitems);
        $workitems->each(function ($workitem) {
            $this->assertTrue($workitem instanceof Workitem);
        });
    }

    public function testGetWorkitemsError(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getWorkitemsById([297, 299, 300]);
    }

    public function testGetBacklogWorkitems(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/backlogWorkItems.json')));

        $workitems = $this->apiClient->getBacklogWorkItems('team', 'backlog-id1');

        $expectedUri = 'https://dev.azure.com/Aveyara/project/team/_apis/work/backlogs/backlog-id1/workitems?api-version=6.0-preview.1';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(1, $workitems);
    }

    public function testGetBacklogWorkitemsError(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getBacklogWorkItems('team', 'backlog-id1');
    }

    public function testGetProjects(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/projects.json')));

        $projects = $this->apiClient->getProjects();

        $expectedUri = 'http://fake/Aveyara/_apis/projects?api-version=6.0';
        $this->assertEquals($expectedUri, $this->historyContainer[0]['request']->getUri()->__toString());
        $this->assertAuthorizationInRequests();
        $this->assertCount(3, $projects);
    }

    public function testGetProjectsError(): void
    {
        $this->mockHandler->append(new Response(300));

        $this->expectException(\Reb3r\ADOAPC\Exceptions\Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 300');

        $this->apiClient->getProjects();
    }
}
