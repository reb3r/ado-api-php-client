<?php

namespace Reb3r\ADOAPC\Models;

use Reb3r\ADOAPC\AzureDevOpsApiClient;

class AzureDevOpsWorkitem
{

    /** @var int */
    public $id;
    /** @var string */
    public $title;
    /** @var string */
    public $project;
    /** @var string */
    public $htmlLink;
    /** @var string */
    public $apiUrl;
    /** @var string */
    public $state;
    /** @var string */
    public $reprosteps;

    public function __construct(array $ticketArr)
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
            $key = array_pop(explode('.', $key));

            //Finally set the Attributes
            $this->{mb_strtolower((string) $key)} = $value;
        }
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
}
