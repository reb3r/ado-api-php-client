<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\AzureDevOpsApiClient;

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
}
