<?php

namespace Reb3r\ADOAPC\Models;

use Reb3r\ADOAPC\AzureDevOpsApiClient;

class Workitem
{

    /** @var string */
    public $htmlLink;
    /** @var string */
    public $apiUrl;
    /** @var string */
    public $state;
    /** @var string */
    public $reprosteps;

    /*public function __construct(array $ticketArr)
    {
        // different JSON depending on search result or Create return value
        if (array_key_exists('id', $ticketArr)) {
            $this->id = $ticketArr['id'];
        }
        if (array_key_exists('project', $ticketArr)) {
            $this->project = $ticketArr['project']['name'];
        }
        if (array_key_exists('_links', $ticketArr)) {
            $this->htmlLink = $ticketArr['_links']['html']['href'];
        }
        if (array_key_exists('url', $ticketArr)) {
            $this->apiUrl = $ticketArr['url'];
        }
        foreach ($ticketArr['fields'] as $key => $value) {
            // modify Key. Get string after last "."
            $elements = explode('.', $key);
            $key = array_pop($elements);

            //Finally set the Attributes
            $this->{mb_strtolower($key)} = $value;
        }
    }*/
    public function __construct(
        private string $id,
        private string $title,
        private array $project,
        private array $links,
        private string $url,

    ) {
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getProjectName()
    {
        return $this->project['name'];
    }

    public function isDone(): bool
    {
        if ($this->state === 'Done') {
            return true;
        }
        return false;
    }

    public function getHtmlLink(AzureDevOpsApiClient $azureApiClient): string
    {
        /**
         * Suppress psalm check. Psalm could not determine, that the assignment in constructor was optional
         * @psalm-suppress RedundantPropertyInitializationCheck
         */
        if (isset($this->htmlLink) === false) {
            $azureDevOpsWorkitem = $azureApiClient->getWorkItemFromApiUrl($this->apiUrl);
            $this->htmlLink = $azureDevOpsWorkitem->htmlLink;
        }
        return $this->htmlLink;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['fields']['System.Title'] ?? '',
            $data['project'] ?? [],
            $data['links'] ?? [],
            $data['url']
        );
    }
}
