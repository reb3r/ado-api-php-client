<?php

namespace Reb3r\ADOAPC\Models;

/**
 * Docs: https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0#identity
 * 
 * @package Reb3r\ADOAPC\Models
 */
class Team
{
    public function __construct(
        private string $id,
        private string $description,
        private array $identity = [],
        private string $identityUrl,
        private string $name,
        private string $projectId,
        private string $projectName,
        private string $url
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIdentity(): array
    {
        return $this->identity;
    }

    public function getIdentityUrl(): string
    {
        return $this->identityUrl;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getProjectName(): string
    {
        return $this->projectName;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['description'],
            $data['identity'] ?? [],
            $data['identityUrl'],
            $data['name'],
            $data['projectId'] ?? '',
            $data['projectName'] ?? '',
            $data['url']
        );
    }
}
