<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\AzureDevOpsApiClient;
use Reb3r\ADOAPC\Models\Team;
use Reb3r\ADOAPC\Models\Workitem;

final class AzureDevOpsApiClientTest extends TestCase
{
    protected $mockHandler;

    protected $apiClient;

    public function setUp(): void
    {
        $this->mockHandler = new MockHandler([]);

        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzle = new Client(['handler' => $handlerStack]);

        $this->apiClient = new AzureDevOpsApiClient('username', 'secret', 'http://fake', 'Aveyara', 'project');
        $this->apiClient->setHttpClient($guzzle);
    }

    public function testGetBackblogs(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/backlogs.json')));

        $backlogs = $this->apiClient->getBacklogs('team');

        $this->assertCount(4, $backlogs);
    }

    public function testGetTeams(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/teams.json')));

        $teams = $this->apiClient->getTeams();

        $this->assertCount(2, $teams);
        $teams->each(function ($team) {
            $this->assertTrue($team instanceof Team);
        });
    }

    public function testGetQueriesDepth1(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/queries_depth1.json')));

        $queries = $this->apiClient->getRootQueryFolders(1);

        $this->assertCount(2, $queries);
    }

    public function testGetWorkitems(): void
    {
        $this->mockHandler->append(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/workitems.json')));

        $workitems = $this->apiClient->getWorkitemsById([297, 299, 300]);

        $this->assertCount(3, $workitems);
        $workitems->each(function ($workitem) {
            $this->assertTrue($workitem instanceof Workitem);
        });
    }
}
