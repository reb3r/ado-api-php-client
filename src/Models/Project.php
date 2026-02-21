<?php

namespace Reb3r\ADOAPC\Models;

/**
 * Docs: https://docs.microsoft.com/en-us/rest/api/azure/devops/core/projects/list?view=azure-devops-rest-6.0
 * 
 * @package Reb3r\ADOAPC\Models
 */
class Project
{
    public function __construct(
        private string $id,
        private string $name,
        private string $description,
        private string $url,
        private string $state
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['id'],
            (string) $data['name'],
            $data['description'] ?? '',
            (string) $data['url'],
            (string) $data['state']
        );
    }
}
