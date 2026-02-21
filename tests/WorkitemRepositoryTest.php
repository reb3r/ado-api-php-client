<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\AzureDevOpsApiClient;
use Reb3r\ADOAPC\Models\Workitem;
use Reb3r\ADOAPC\Repository\WorkitemRepository;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;
use Reb3r\ADOAPC\Exceptions\Exception;
use Reb3r\ADOAPC\Exceptions\WorkItemNotFoundException;
use Reb3r\ADOAPC\Exceptions\WorkItemNotUniqueException;

final class WorkitemRepositoryTest extends TestCase
{
    private MockHandler $mockHandler;
    private WorkitemRepository $repository;
    private AzureDevOpsApiClient $apiClient;

    public function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $authHeader = ['Authorization' => 'Bearer test-token'];
        $this->repository = new WorkitemRepository(
            $guzzleClient,
            'https://dev.azure.com/org/project/_apis/',
            'org',
            'project',
            $authHeader
        );

        $this->apiClient = $this->createMock(AzureDevOpsApiClient::class);
    }

    public function testCreateBugSuccessful(): void
    {
        $responseBody = json_encode(
            [
            'id' => '123',
            'fields' => [
                'System.Title' => 'Test Bug',
                'System.State' => 'New',
                'System.CreatedDate' => '2024-01-01',
                'System.IterationPath' => 'Path',
                'System.WorkItemType' => 'Bug',
                'System.Description' => '',
                'Microsoft.VSTS.TCM.ReproSteps' => 'Steps',
                'Microsoft.VSTS.Common.AcceptanceCriteria' => '',
                'Microsoft.VSTS.TCM.SystemInfo' => '',
                'Microsoft.VSTS.Common.Resolution' => ''
            ],
            'url' => 'http://test.url',
            '_links' => []
            ]
        );

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $workitem = $this->repository->createBug('Test Bug', 'Test Description', [], [], $this->apiClient);

        $this->assertInstanceOf(Workitem::class, $workitem);
        $this->assertEquals('123', $workitem->getId());
    }

    public function testCreateBugWithAttachments(): void
    {
        $responseBody = json_encode(
            [
            'id' => '456',
            'fields' => [
                'System.Title' => 'Bug with Attachments',
                'System.State' => 'New',
                'System.CreatedDate' => '2024-01-01',
                'System.IterationPath' => 'Path',
                'System.WorkItemType' => 'Bug',
                'System.Description' => '',
                'Microsoft.VSTS.TCM.ReproSteps' => 'Steps',
                'Microsoft.VSTS.Common.AcceptanceCriteria' => '',
                'Microsoft.VSTS.TCM.SystemInfo' => '',
                'Microsoft.VSTS.Common.Resolution' => ''
            ],
            'url' => 'http://test.url',
            '_links' => []
            ]
        );

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $attachments = [\Reb3r\ADOAPC\Models\AttachmentReference::fromArray([
            'id' => '1',
            'url' => 'http://attachment.url'
        ])];
        $workitem = $this->repository->createBug('Test Bug', 'Desc', $attachments, [], $this->apiClient);

        $this->assertInstanceOf(Workitem::class, $workitem);
    }

    public function testCreateBugWithTags(): void
    {
        $responseBody = json_encode(
            [
            'id' => '789',
            'fields' => [
                'System.Title' => 'Bug with Tags',
                'System.State' => 'New',
                'System.CreatedDate' => '2024-01-01',
                'System.IterationPath' => 'Path',
                'System.WorkItemType' => 'Bug',
                'System.Description' => '',
                'Microsoft.VSTS.TCM.ReproSteps' => 'Steps',
                'Microsoft.VSTS.Common.AcceptanceCriteria' => '',
                'Microsoft.VSTS.TCM.SystemInfo' => '',
                'Microsoft.VSTS.Common.Resolution' => ''
            ],
            'url' => 'http://test.url',
            '_links' => []
            ]
        );

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $tags = ['urgent', 'critical'];
        $workitem = $this->repository->createBug('Test Bug', 'Desc', [], $tags, $this->apiClient);

        $this->assertInstanceOf(Workitem::class, $workitem);
    }

    public function testCreateBugThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('API-Call could not be authenticated correctly.');

        $this->repository->createBug('Test Bug', 'Desc', [], [], $this->apiClient);
    }

    public function testCreateBugThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(400));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not create Bug: 400');

        $this->repository->createBug('Test Bug', 'Desc', [], [], $this->apiClient);
    }

    public function testUpdateWorkitemReproStepsAndAttachmentsSuccessful(): void
    {
        $this->mockHandler->append(new Response(200));

        $workitem = $this->createMock(Workitem::class);
        $workitem->method('getId')->willReturn('123');

        $this->repository->updateWorkitemReproStepsAndAttachments($workitem, 'New steps', []);

        $this->expectNotToPerformAssertions();
    }

    public function testUpdateWorkitemWithAttachments(): void
    {
        $this->mockHandler->append(new Response(200));

        $workitem = $this->createMock(Workitem::class);
        $workitem->method('getId')->willReturn('123');

        $attachments = [['azureDevOpsUrl' => 'http://attachment.url']];
        $this->repository->updateWorkitemReproStepsAndAttachments($workitem, 'New steps', $attachments);

        $this->expectNotToPerformAssertions();
    }

    public function testUpdateWorkitemThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $workitem = $this->createMock(Workitem::class);
        $workitem->method('getId')->willReturn('123');

        $this->expectException(AuthenticationException::class);

        $this->repository->updateWorkitemReproStepsAndAttachments($workitem, 'Steps', []);
    }

    public function testUpdateWorkitemThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(400));

        $workitem = $this->createMock(Workitem::class);
        $workitem->method('getId')->willReturn('123');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Could not update workitem: 400');

        $this->repository->updateWorkitemReproStepsAndAttachments($workitem, 'Steps', []);
    }

    public function testAddCommentToWorkitemSuccessful(): void
    {
        $this->mockHandler->append(new Response(200));

        $workitem = $this->createMock(Workitem::class);
        $workitem->method('getId')->willReturn('123');

        $this->repository->addCommentToWorkitem($workitem, 'Test comment');

        $this->expectNotToPerformAssertions();
    }

    public function testAddCommentThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $workitem = $this->createMock(Workitem::class);
        $workitem->method('getId')->willReturn('123');

        $this->expectException(AuthenticationException::class);

        $this->repository->addCommentToWorkitem($workitem, 'Comment');
    }

    public function testAddCommentThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(500));

        $workitem = $this->createMock(Workitem::class);
        $workitem->method('getId')->willReturn('123');

        $this->expectException(Exception::class);

        $this->repository->addCommentToWorkitem($workitem, 'Comment');
    }

    public function testGetWorkItemFromApiUrlSuccessful(): void
    {
        $responseBody = json_encode(
            [
            'id' => '999',
            'fields' => [
                'System.Title' => 'Retrieved Bug',
                'System.State' => 'Active',
                'System.CreatedDate' => '2024-01-01',
                'System.IterationPath' => 'Path',
                'System.WorkItemType' => 'Bug',
                'System.Description' => '',
                'Microsoft.VSTS.TCM.ReproSteps' => 'Steps',
                'Microsoft.VSTS.Common.AcceptanceCriteria' => '',
                'Microsoft.VSTS.TCM.SystemInfo' => '',
                'Microsoft.VSTS.Common.Resolution' => ''
            ],
            'url' => 'http://test.url',
            '_links' => []
            ]
        );

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $workitem = $this->repository->getWorkItemFromApiUrl('http://api.url', $this->apiClient);

        $this->assertInstanceOf(Workitem::class, $workitem);
        $this->assertEquals('999', $workitem->getId());
    }

    public function testGetWorkItemFromApiUrlThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $this->expectException(AuthenticationException::class);

        $this->repository->getWorkItemFromApiUrl('http://api.url', $this->apiClient);
    }

    public function testGetWorkItemFromApiUrlThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(404));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 404');

        $this->repository->getWorkItemFromApiUrl('http://api.url', $this->apiClient);
    }

    public function testSearchWorkitemSuccessful(): void
    {
        $responseBody = json_encode(
            [
            'count' => 1,
            'results' => [
                [
                    'id' => '555',
                    'fields' => [
                        'System.Title' => 'Found Bug',
                        'System.State' => 'New',
                        'System.CreatedDate' => '2024-01-01',
                        'System.IterationPath' => 'Path',
                        'System.WorkItemType' => 'Bug',
                        'System.Description' => '',
                        'Microsoft.VSTS.TCM.ReproSteps' => 'Steps',
                        'Microsoft.VSTS.Common.AcceptanceCriteria' => '',
                        'Microsoft.VSTS.TCM.SystemInfo' => '',
                        'Microsoft.VSTS.Common.Resolution' => ''
                    ],
                    'url' => 'http://test.url',
                    '_links' => []
                ]
            ]
            ]
        );

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $workitem = $this->repository->searchWorkitem('test search', $this->apiClient);

        $this->assertInstanceOf(Workitem::class, $workitem);
        $this->assertEquals('555', $workitem->getId());
    }

    public function testSearchWorkitemThrowsNotFoundExceptionWhenNoResults(): void
    {
        $responseBody = json_encode(['count' => 0, 'results' => []]);

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $this->expectException(WorkItemNotFoundException::class);
        $this->expectExceptionMessage('Could not find WorkItem for test search');

        $this->repository->searchWorkitem('test search', $this->apiClient);
    }

    public function testSearchWorkitemThrowsNotUniqueExceptionWhenMultipleResults(): void
    {
        $responseBody = json_encode(['count' => 2, 'results' => []]);

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $this->expectException(WorkItemNotUniqueException::class);

        $this->repository->searchWorkitem('test search', $this->apiClient);
    }

    public function testSearchWorkitemThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $this->expectException(AuthenticationException::class);

        $this->repository->searchWorkitem('test search', $this->apiClient);
    }

    public function testSearchWorkitemThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(500));

        $this->expectException(Exception::class);

        $this->repository->searchWorkitem('test search', $this->apiClient);
    }

    public function testGetWorkitemsByIdSuccessful(): void
    {
        $responseBody = json_encode(
            [
            'value' => [
                [
                    'id' => '111',
                    'fields' => [
                        'System.Title' => 'Bug 1',
                        'System.State' => 'New',
                        'System.CreatedDate' => '2024-01-01',
                        'System.IterationPath' => 'Path',
                        'System.WorkItemType' => 'Bug',
                        'System.Description' => '',
                        'Microsoft.VSTS.TCM.ReproSteps' => '',
                        'Microsoft.VSTS.Common.AcceptanceCriteria' => '',
                        'Microsoft.VSTS.TCM.SystemInfo' => '',
                        'Microsoft.VSTS.Common.Resolution' => ''
                    ],
                    'url' => 'http://test.url',
                    '_links' => []
                ],
                [
                    'id' => '222',
                    'fields' => [
                        'System.Title' => 'Bug 2',
                        'System.State' => 'Active',
                        'System.CreatedDate' => '2024-01-01',
                        'System.IterationPath' => 'Path',
                        'System.WorkItemType' => 'Bug',
                        'System.Description' => '',
                        'Microsoft.VSTS.TCM.ReproSteps' => '',
                        'Microsoft.VSTS.Common.AcceptanceCriteria' => '',
                        'Microsoft.VSTS.TCM.SystemInfo' => '',
                        'Microsoft.VSTS.Common.Resolution' => ''
                    ],
                    'url' => 'http://test.url',
                    '_links' => []
                ]
            ]
            ]
        );

        $this->mockHandler->append(new Response(200, [], $responseBody));

        $workitems = $this->repository->getWorkitemsById([111, 222], $this->apiClient);

        $this->assertCount(2, $workitems);
        $this->assertInstanceOf(Workitem::class, $workitems[0]);
        $this->assertEquals('111', $workitems[0]->getId());
        $this->assertEquals('222', $workitems[1]->getId());
    }

    public function testGetWorkitemsByIdReturnsEmptyArrayForEmptyInput(): void
    {
        $workitems = $this->repository->getWorkitemsById([], $this->apiClient);

        $this->assertEmpty($workitems);
    }

    public function testGetWorkitemsByIdThrowsAuthenticationExceptionOn203(): void
    {
        $this->mockHandler->append(new Response(203));

        $this->expectException(AuthenticationException::class);

        $this->repository->getWorkitemsById([123], $this->apiClient);
    }

    public function testGetWorkitemsByIdThrowsExceptionOnNon200(): void
    {
        $this->mockHandler->append(new Response(500));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Request to AzureDevOps failed: 500');

        $this->repository->getWorkitemsById([123], $this->apiClient);
    }
}
