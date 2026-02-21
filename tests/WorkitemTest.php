<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Tests;

use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\Models\Workitem;
use Reb3r\ADOAPC\AzureDevOpsApiClient;

final class WorkitemTest extends TestCase
{
    private AzureDevOpsApiClient $apiClient;

    public function setUp(): void
    {
        $this->apiClient = $this->createMock(AzureDevOpsApiClient::class);
    }

    public function testGetId(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('123', $workitem->getId());
    }

    public function testGetTitle(): void
    {
        $workitem = new Workitem('123', 'Test Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('Test Title', $workitem->getTitle());
    }

    public function testGetState(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('Active', $workitem->getState());
    }

    public function testIsDoneReturnsTrueWhenStateDone(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Done', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertTrue($workitem->isDone());
    }

    public function testIsDoneReturnsFalseWhenStateNotDone(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertFalse($workitem->isDone());
    }

    public function testGetCreatedDate(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01T10:00:00Z', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('2024-01-01T10:00:00Z', $workitem->getCreatedDate());
    }

    public function testGetIterationpath(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/iteration/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('/iteration/path', $workitem->getIterationpath());
    }

    public function testGetWorkitemtype(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'User Story', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('User Story', $workitem->getWorkitemtype());
    }

    public function testGetDescription(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'Test Description', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('Test Description', $workitem->getDescription());
    }

    public function testGetReproSteps(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'Repro Steps', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('Repro Steps', $workitem->getReproSteps());
    }

    public function testGetAcceptanceCriteria(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'Acceptance Criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('Acceptance Criteria', $workitem->getAcceptanceCriteria());
    }

    public function testGetSystemInfo(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'System Info', 'resolution', $this->apiClient);

        $this->assertEquals('System Info', $workitem->getSystemInfo());
    }

    public function testGetResolution(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'Test Resolution', $this->apiClient);

        $this->assertEquals('Test Resolution', $workitem->getResolution());
    }

    public function testGetProjectName(): void
    {
        $project = ['name' => 'Test Project', 'id' => '456'];
        $workitem = new Workitem('123', 'Title', $project, 'url', 'Active', '2024-01-01', '/path', 'Bug', 'desc', 'repro', 'criteria', 'info', 'resolution', $this->apiClient);

        $this->assertEquals('Test Project', $workitem->getProjectName());
    }

    public function testGetFieldsWithTextAreaReturnsDescription(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', 'Test Description', '', '', '', '', $this->apiClient);

        $fields = $workitem->getFieldsWithTextArea();

        $this->assertArrayHasKey('System.Description', $fields);
        $this->assertEquals('Description', $fields['System.Description']['name']);
        $this->assertEquals('Test Description', $fields['System.Description']['content']);
    }

    public function testGetFieldsWithTextAreaReturnsReproSteps(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', '', 'Test Repro', '', '', '', $this->apiClient);

        $fields = $workitem->getFieldsWithTextArea();

        $this->assertArrayHasKey('Microsoft.VSTS.TCM.ReproSteps', $fields);
        $this->assertEquals('Repro Steps', $fields['Microsoft.VSTS.TCM.ReproSteps']['name']);
        $this->assertEquals('Test Repro', $fields['Microsoft.VSTS.TCM.ReproSteps']['content']);
    }

    public function testGetFieldsWithTextAreaReturnsEmptyWhenNoFields(): void
    {
        $workitem = new Workitem('123', 'Title', [], 'url', 'Active', '2024-01-01', '/path', 'Bug', '', '', '', '', '', $this->apiClient);

        $fields = $workitem->getFieldsWithTextArea();

        $this->assertEmpty($fields);
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => '789',
            'url' => 'http://test.url',
            'fields' => [
                'System.Title' => 'From Array Title',
                'System.State' => 'New',
                'System.CreatedDate' => '2024-02-01',
                'System.IterationPath' => '/iter',
                'System.WorkItemType' => 'Task',
                'System.Description' => 'Description',
                'Microsoft.VSTS.TCM.ReproSteps' => 'Steps',
                'Microsoft.VSTS.Common.AcceptanceCriteria' => 'Criteria',
                'Microsoft.VSTS.TCM.SystemInfo' => 'Info',
                'Microsoft.VSTS.Common.Resolution' => 'Resolution'
            ],
            'project' => ['name' => 'Project']
        ];

        $workitem = Workitem::fromArray($data, $this->apiClient);

        $this->assertEquals('789', $workitem->getId());
        $this->assertEquals('From Array Title', $workitem->getTitle());
        $this->assertEquals('New', $workitem->getState());
    }
}
