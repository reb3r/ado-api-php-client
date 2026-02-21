<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Tests;

use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\Models\Project;

final class ProjectTest extends TestCase
{
    public function testGetId(): void
    {
        $project = new Project('proj-123', 'Project Name', 'Description', 'http://url', 'wellFormed');

        $this->assertEquals('proj-123', $project->getId());
    }

    public function testGetName(): void
    {
        $project = new Project('proj-123', 'Test Project', 'Description', 'http://url', 'wellFormed');

        $this->assertEquals('Test Project', $project->getName());
    }

    public function testGetDescription(): void
    {
        $project = new Project('proj-123', 'Project Name', 'Test Description', 'http://url', 'wellFormed');

        $this->assertEquals('Test Description', $project->getDescription());
    }

    public function testGetUrl(): void
    {
        $project = new Project('proj-123', 'Project Name', 'Description', 'http://test.url', 'wellFormed');

        $this->assertEquals('http://test.url', $project->getUrl());
    }

    public function testGetState(): void
    {
        $project = new Project('proj-123', 'Project Name', 'Description', 'http://url', 'unchanged');

        $this->assertEquals('unchanged', $project->getState());
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'from-array-id',
            'name' => 'From Array Project',
            'description' => 'From Array Description',
            'url' => 'http://from.array',
            'state' => 'wellFormed'
        ];

        $project = Project::fromArray($data);

        $this->assertEquals('from-array-id', $project->getId());
        $this->assertEquals('From Array Project', $project->getName());
        $this->assertEquals('From Array Description', $project->getDescription());
    }
}
