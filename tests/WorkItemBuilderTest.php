<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Tests;

use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\Models\WorkItemBuilder;
use Reb3r\ADOAPC\AzureDevOpsApiClient;

final class WorkItemBuilderTest extends TestCase
{
    private AzureDevOpsApiClient $apiClient;

    public function setUp(): void
    {
        $this->apiClient = $this->createMock(AzureDevOpsApiClient::class);
        $this->apiClient->method('getProjectBaseUrl')->willReturn('http://fake/project/_apis/');
    }

    public function testBuildBugCreatesBuilderWithBugType(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);

        $this->assertInstanceOf(WorkItemBuilder::class, $builder);
        $this->assertEquals('Bug', $builder->type);
    }

    public function testBuildPBICreatesBuilderWithPBIType(): void
    {
        $builder = WorkItemBuilder::buildPBI($this->apiClient);

        $this->assertInstanceOf(WorkItemBuilder::class, $builder);
        $this->assertEquals('Product Backlog Item', $builder->type);
    }

    public function testBuildIssueCreatesBuilderWithIssueType(): void
    {
        $builder = WorkItemBuilder::buildIssue($this->apiClient);

        $this->assertInstanceOf(WorkItemBuilder::class, $builder);
        $this->assertEquals('Issue', $builder->type);
    }

    public function testBuildUserStoryCreatesBuilderWithUserStoryType(): void
    {
        $builder = WorkItemBuilder::buildUserStory($this->apiClient);

        $this->assertInstanceOf(WorkItemBuilder::class, $builder);
        $this->assertEquals('User Story', $builder->type);
    }

    public function testTitleAddsToRequestBody(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $builder->title('Test Title');

        $this->assertArrayHasKey('title', $builder->requestBody);
        $this->assertEquals('Test Title', $builder->requestBody['title']['value']);
        $this->assertEquals('/fields/System.Title', $builder->requestBody['title']['path']);
    }

    public function testTitleReturnsBuilderForChaining(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $result = $builder->title('Test');

        $this->assertSame($builder, $result);
    }

    public function testReproStepsAddsToRequestBody(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $builder->reproSteps('Test Repro Steps');

        $this->assertArrayHasKey('reproSteps', $builder->requestBody);
        $this->assertStringContainsString('Test Repro Steps', $builder->requestBody['reproSteps']['value']);
        $this->assertEquals('/fields/Microsoft.VSTS.TCM.ReproSteps', $builder->requestBody['reproSteps']['path']);
    }

    public function testReproStepsReturnsBuilderForChaining(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $result = $builder->reproSteps('Test');

        $this->assertSame($builder, $result);
    }

    public function testDescriptionAddsToRequestBody(): void
    {
        $builder = WorkItemBuilder::buildUserStory($this->apiClient);
        $builder->description('Test Description');

        $this->assertArrayHasKey('description', $builder->requestBody);
        $this->assertStringContainsString('Test Description', $builder->requestBody['description']['value']);
        $this->assertEquals('/fields/System.Description', $builder->requestBody['description']['path']);
    }

    public function testDescriptionReturnsBuilderForChaining(): void
    {
        $builder = WorkItemBuilder::buildUserStory($this->apiClient);
        $result = $builder->description('Test');

        $this->assertSame($builder, $result);
    }

    public function testTagsAddsToRequestBody(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $builder->tags('tag1;tag2;tag3');

        $this->assertArrayHasKey('tags', $builder->requestBody);
        $this->assertEquals('tag1;tag2;tag3', $builder->requestBody['tags']['value']);
        $this->assertEquals('/fields/System.Tags', $builder->requestBody['tags']['path']);
    }

    public function testTagsReturnsBuilderForChaining(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $result = $builder->tags('tag1;tag2');

        $this->assertSame($builder, $result);
    }

    public function testAreaPathAddsToRequestBody(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $builder->areaPath('Project\\Area\\Subarea');

        $this->assertArrayHasKey('areaPath', $builder->requestBody);
        $this->assertEquals('Project\\Area\\Subarea', $builder->requestBody['areaPath']['value']);
        $this->assertEquals('/fields/System.AreaPath', $builder->requestBody['areaPath']['path']);
    }

    public function testAreaPathReturnsBuilderForChaining(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $result = $builder->areaPath('Path');

        $this->assertSame($builder, $result);
    }

    public function testInIterationPathAddsToRequestBody(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $builder->inIterationPath('Project\\Iteration\\Sprint 1');

        $this->assertArrayHasKey('iterationPath', $builder->requestBody);
        $this->assertEquals('Project\\Iteration\\Sprint 1', $builder->requestBody['iterationPath']['value']);
        $this->assertEquals('/fields/System.IterationPath', $builder->requestBody['iterationPath']['path']);
    }

    public function testInIterationPathReturnsBuilderForChaining(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $result = $builder->inIterationPath('Path');

        $this->assertSame($builder, $result);
    }

    public function testAttachmentsAddsToRequestBody(): void
    {
        $attachments = [
            \Reb3r\ADOAPC\Models\AttachmentReference::fromArray(['azureDevOpsUrl' => 'http://attachment1.url']),
            \Reb3r\ADOAPC\Models\AttachmentReference::fromArray(['azureDevOpsUrl' => 'http://attachment2.url'])
        ];

        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $builder->attachments($attachments);

        // Attachments are added directly to requestBody array, not as keyed item
        $this->assertCount(
            2,
            array_filter(
                $builder->requestBody,
                fn($item) =>
                isset($item['path']) && $item['path'] === '/relations/-'
            )
        );
    }

    public function testAttachmentsReturnsBuilderForChaining(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient);
        $result = $builder->attachments([]);

        $this->assertSame($builder, $result);
    }

    public function testFluentChaining(): void
    {
        $builder = WorkItemBuilder::buildBug($this->apiClient)
            ->title('Chained Title')
            ->reproSteps('Chained Steps')
            ->tags('bug;critical')
            ->areaPath('Project\\Area');

        $this->assertArrayHasKey('title', $builder->requestBody);
        $this->assertArrayHasKey('reproSteps', $builder->requestBody);
        $this->assertArrayHasKey('tags', $builder->requestBody);
        $this->assertArrayHasKey('areaPath', $builder->requestBody);
    }
}
