<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\Http\AzureDevOpsHttpClient;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;
use Reb3r\ADOAPC\Exceptions\Exception;

final class AzureDevOpsHttpClientTest extends TestCase
{
    private MockHandler $mockHandler;
    private AzureDevOpsHttpClient $httpClient;

    public function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $this->httpClient = new AzureDevOpsHttpClient('user', 'pass', $guzzleClient);
    }

    public function testGetSuccessful(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"success": true}'));

        $response = $this->httpClient->get('http://test.url');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('API-Call could not be authenticated correctly.');

        $this->httpClient->get('http://test.url');
    }

    public function testGetThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(500));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 500');

        $this->httpClient->get('http://test.url');
    }

    public function testPostSuccessful(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"created": true}'));

        $response = $this->httpClient->post('http://test.url', '{"data": "test"}');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPostThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $this->expectException(AuthenticationException::class);

        $this->httpClient->post('http://test.url', '{}');
    }

    public function testPostThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(400));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Request failed: 400');

        $this->httpClient->post('http://test.url', '{}');
    }

    public function testPatchSuccessful(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"updated": true}'));

        $response = $this->httpClient->patch('http://test.url', '{"data": "test"}');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testPatchThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $this->expectException(AuthenticationException::class);

        $this->httpClient->patch('http://test.url', '{}');
    }

    public function testPatchThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(404));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Request failed: 404');

        $this->httpClient->patch('http://test.url', '{}');
    }

    public function testDecodeJsonResponse(): void
    {
        $response = new Response(200, [], '{"key": "value", "number": 123}');

        $data = $this->httpClient->decodeJsonResponse($response);

        $this->assertEquals(['key' => 'value', 'number' => 123], $data);
    }

    public function testDecodeJsonResponseWithKey(): void
    {
        $response = new Response(200, [], '{"data": {"nested": "value"}, "other": "stuff"}');

        $data = $this->httpClient->decodeJsonResponse($response, 'data');

        $this->assertEquals(['nested' => 'value'], $data);
    }

    public function testDecodeJsonResponseThrowsExceptionOnInvalidJson(): void
    {
        $response = new Response(200, [], 'invalid json{]');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to decode JSON response:');

        $this->httpClient->decodeJsonResponse($response);
    }

    public function testUsesBasicAuthWhenUsernameProvided(): void
    {
        $mockHandler = new MockHandler([new Response(200)]);
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push($history);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $httpClient = new AzureDevOpsHttpClient('testuser', 'testpass', $guzzleClient);
        $httpClient->get('http://test.url');

        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $authHeader = $request->getHeader('Authorization')[0];
        $this->assertStringStartsWith('Basic ', $authHeader);
        $this->assertEquals('Basic ' . base64_encode('testuser:testpass'), $authHeader);
    }

    public function testUsesBearerTokenWhenNoUsernameProvided(): void
    {
        $mockHandler = new MockHandler([new Response(200)]);
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push($history);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $httpClient = new AzureDevOpsHttpClient('', 'my-token', $guzzleClient);
        $httpClient->get('http://test.url');

        $this->assertCount(1, $container);
        $request = $container[0]['request'];
        $authHeader = $request->getHeader('Authorization')[0];
        $this->assertStringStartsWith('Bearer ', $authHeader);
        $this->assertEquals('Bearer my-token', $authHeader);
    }
}
