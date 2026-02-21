<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Tests;

use PHPUnit\Framework\TestCase;
use Reb3r\ADOAPC\Models\Team;

final class TeamTest extends TestCase
{
    public function testGetId(): void
    {
        $team = new Team('team-123', 'Description', [], 'identity-url', 'Team Name', 'proj-456', 'Project Name', 'url');

        $this->assertEquals('team-123', $team->getId());
    }

    public function testGetDescription(): void
    {
        $team = new Team('team-123', 'Test Description', [], 'identity-url', 'Team Name', 'proj-456', 'Project Name', 'url');

        $this->assertEquals('Test Description', $team->getDescription());
    }

    public function testGetName(): void
    {
        $team = new Team('team-123', 'Description', [], 'identity-url', 'My Team', 'proj-456', 'Project Name', 'url');

        $this->assertEquals('My Team', $team->getName());
    }

    public function testGetIdentity(): void
    {
        $identity = ['id' => 'ident-123', 'name' => 'Identity Name'];
        $team = new Team('team-123', 'Description', $identity, 'identity-url', 'Team Name', 'proj-456', 'Project Name', 'url');

        $this->assertEquals($identity, $team->getIdentity());
    }

    public function testGetIdentityUrl(): void
    {
        $team = new Team('team-123', 'Description', [], 'http://identity.url', 'Team Name', 'proj-456', 'Project Name', 'url');

        $this->assertEquals('http://identity.url', $team->getIdentityUrl());
    }

    public function testGetProjectId(): void
    {
        $team = new Team('team-123', 'Description', [], 'identity-url', 'Team Name', 'proj-789', 'Project Name', 'url');

        $this->assertEquals('proj-789', $team->getProjectId());
    }

    public function testGetProjectName(): void
    {
        $team = new Team('team-123', 'Description', [], 'identity-url', 'Team Name', 'proj-456', 'Test Project', 'url');

        $this->assertEquals('Test Project', $team->getProjectName());
    }

    public function testGetUrl(): void
    {
        $team = new Team('team-123', 'Description', [], 'identity-url', 'Team Name', 'proj-456', 'Project Name', 'http://team.url');

        $this->assertEquals('http://team.url', $team->getUrl());
    }

    public function testFromArray(): void
    {
        $data = [
            'id' => 'from-array-id',
            'description' => 'From Array Desc',
            'identity' => ['key' => 'value'],
            'identityUrl' => 'http://identity',
            'name' => 'From Array Name',
            'projectId' => 'proj-id',
            'projectName' => 'Proj Name',
            'url' => 'http://url'
        ];

        $team = Team::fromArray($data);

        $this->assertEquals('from-array-id', $team->getId());
        $this->assertEquals('From Array Desc', $team->getDescription());
        $this->assertEquals('From Array Name', $team->getName());
    }
}
