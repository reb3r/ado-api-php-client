<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Models;

/**
 * Docs: https://docs.microsoft.com/en-us/rest/api/azure/devops/core/projects/list?view=azure-devops-rest-6.0
 *
 * @package Reb3r\ADOAPC\Models
 */
class AttachmentReference
{
    public function __construct(
        private string $id,
        private string $url,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
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
            (string) $data['url'],
        );
    }
}
