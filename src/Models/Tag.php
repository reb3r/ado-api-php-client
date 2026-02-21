<?php

namespace Reb3r\ADOAPC\Models;

/**
 * Docs: https://learn.microsoft.com/en-us/rest/api/azure/devops/wit/tags/list?view=azure-devops-rest-6.0&tabs=HTTP
 * 
 * @package Reb3r\ADOAPC\Models
 */
class Tag
{
    public function __construct(
        private string $id,
        private string $name,
        private string $url,
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


    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['id'],
            (string) $data['name'],
            (string) $data['url'],
        );
    }
}
