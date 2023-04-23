<?php

namespace Reb3r\ADOAPC\Models;

use Illuminate\Support\Collection;
use Reb3r\ADOAPC\AzureDevOpsApiClient;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;

class Workitem
{

    /** @var string */
    public $htmlLink;
    /** @var string */
    public $apiUrl;

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
        private string $state,
        private string $createddate,
        private string $iterationpath,
        private string $workitemtype,
        private string $description,
        private string $reprosteps,
        private string $acceptanceCriteria,
        private string $systemInfo,
        private string $resolution,
        private AzureDevOpsApiClient $azureApiClient
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getProjectName(): string
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

    public function getState(): string
    {
        return $this->state;
    }

    public function getCreatedDate(): string
    {
        return $this->createddate;
    }

    public function getIterationpath(): string
    {
        return $this->iterationpath;
    }

    public function getWorkitemtype(): string
    {
        return $this->workitemtype;
    }

    public function getDescription(bool $withEmbeddedADOImages = false): string
    {
        if ($withEmbeddedADOImages === true) {
            return $this->getImagesFromADOAndConvertToBase64($this->description);
        }
        return $this->description;
    }

    public function getReproSteps(bool $withEmbeddedADOImages = false): string
    {
        if ($withEmbeddedADOImages === true) {
            return $this->getImagesFromADOAndConvertToBase64($this->reprosteps);
        }
        return $this->reprosteps;
    }

    public function getSystemInfo(bool $withEmbeddedADOImages = false): string
    {
        if ($withEmbeddedADOImages === true) {
            return $this->getImagesFromADOAndConvertToBase64($this->systemInfo);
        }
        return $this->systemInfo;
    }

    public function getAcceptanceCriteria(bool $withEmbeddedADOImages = false): string
    {
        if ($withEmbeddedADOImages === true) {
            return $this->getImagesFromADOAndConvertToBase64($this->acceptanceCriteria);
        }
        return $this->acceptanceCriteria;
    }

    public function getResolution(bool $withEmbeddedADOImages = false): string
    {
        if ($withEmbeddedADOImages === true) {
            return $this->getImagesFromADOAndConvertToBase64($this->resolution);
        }
        return $this->resolution;
    }

    /**
     * Checks if in a text there are embedded images that are stored as attachments on Azure DevOps
     * If there are such images they are downloaded and added to the text in base64
     * @param string $content
     * @return string
     *
     * @throws AuthenticationException
     * @throws Exception
     */
    private function getImagesFromADOAndConvertToBase64(string $content): string
    {
        $azureDevOpsImgUrl = $this->azureApiClient->getOrganizationBaseUrl();

        $exploded = explode('<img src="' . $azureDevOpsImgUrl, $content);
        foreach ($exploded as $key => $finding) {
            // First Element of an array has three options:
            // Needle is part of string - if starts with needle [0] is '' if not [0] is part of string before needle
            // If Needle is not part of String [0] is full string
            // so [0 can be skipped]
            if ($key === 0) {
                continue;
            }
            $imgurlPart2 = strstr($finding, '"', true);
            $url = $azureDevOpsImgUrl . $imgurlPart2;
            
            $response =  $this->azureApiClient->getImageAttachment($url);
            $imgB64 = base64_encode($response->getBody()->getContents());

            $mimeContentTypeFromHeader = strstr($response->getHeader('Content-Type')[0], ';', true);
            $mimeContentType =  $mimeContentTypeFromHeader === false ? 'image' : $mimeContentTypeFromHeader;

            $imgTag = '<img src="data:' . $mimeContentType . ';base64,' . $imgB64;
            $exploded[$key] = substr_replace($finding, $imgTag, 0, strlen($imgurlPart2));
        }
        return (implode('', $exploded));
    }

    /**
     * Get all the text area fields of the workitem as collection.
     * Key of the Items is the field key from azure devops like System.Description
     * the item consists of an array that has name and conent of the field
     *
     * collect([
     * 'System.Description' =>
     *  [
     *      'name' => 'Description',
     *      'content' => '<div>This ist the description</div>'
     *  ]
     * ])
     * Collection can be emtpy!
     *
     * @return Collection
     */
    public function getFieldsWithTextArea(bool $withEmbeddedADOImages = false): Collection
    {
        $fields = collect();
        if (empty($this->getDescription()) === false) {
            $fieldArray = [];
            $fieldArray['name'] = 'Description';
            $fieldArray['content'] = $this->getDescription($withEmbeddedADOImages);
            $fields->put('System.Description', $fieldArray);
        }

        if (empty($this->getReproSteps()) === false) {
            $fieldArray = [];
            $fieldArray['name'] = 'Repro Steps';
            $fieldArray['content'] = $this->getReproSteps($withEmbeddedADOImages);
            $fields->put('Microsoft.VSTS.TCM.ReproSteps', $fieldArray);
        }

        if (empty($this->getSystemInfo()) === false) {
            $fieldArray = [];
            $fieldArray['name'] = 'System Info';
            $fieldArray['content'] = $this->getSystemInfo($withEmbeddedADOImages);
            $fields->put('Microsoft.VSTS.Common.SystemInfo', $fieldArray);
        }

        if (empty($this->getAcceptanceCriteria()) === false) {
            $fieldArray = [];
            $fieldArray['name'] = 'Acceptence Criteria';
            $fieldArray['content'] = $this->getAcceptanceCriteria($withEmbeddedADOImages);
            $fields->put('Microsoft.VSTS.Common.AcceptanceCriteria', $fieldArray);
        }

        if (empty($this->getResolution()) === false) {
            $fieldArray = [];
            $fieldArray['name'] = 'Resolution';
            $fieldArray['content'] = $this->getResolution($withEmbeddedADOImages);
            $fields->put('Microsoft.VSTS.Common.Resolution', $fieldArray);
        }
        return $fields;
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

    /**
     * Adds a comment to this workitem
     * @param string $commentText
     *
     * @return void
     * @throws AuthenticationException
     * @throws Exception
     */
    public function addComment(string $commentText): void
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/comments/add?view=azure-devops-rest-6.0#commentmention
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0#webapiteam
        $query = '?api-version=6.0-preview.3';
        $requestUrl = 'wit/workitems/' . $this->getId() . '/comments';
        $url = $this->azureApiClient->getProjectBaseUrl() . $requestUrl . $query;

        // https://stackoverflow.com/questions/58558388/ping-user-in-azure-devops-comment
        $requestBody = [
            'text' => $commentText
        ];

        $this->azureApiClient->post($url, json_encode($requestBody));
    }

    public static function fromArray(array $data, AzureDevOpsApiClient $azureApiClient): self
    {
        return new self(
            $data['id'],
            $data['fields']['System.Title'] ?? '',
            $data['project'] ?? [],
            $data['links'] ?? [],
            $data['url'],
            $data['fields']['System.State'] ?? '',
            $data['fields']['System.CreatedDate'] ?? '',
            $data['fields']['System.IterationPath'] ?? '',
            $data['fields']['System.WorkItemType'] ?? '',
            $data['fields']['System.Description'] ?? '',
            $data['fields']['Microsoft.VSTS.TCM.ReproSteps'] ?? '',
            $data['fields']['Microsoft.VSTS.Common.AcceptanceCriteria'] ?? '',
            $data['fields']['Microsoft.VSTS.TCM.SystemInfo'] ?? '',
            $data['fields']['Microsoft.VSTS.Common.Resolution'] ?? '',
            $azureApiClient
        );
    }
}
