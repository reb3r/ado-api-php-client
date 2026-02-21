<?php

namespace Reb3r\ADOAPC\Models;

use Reb3r\ADOAPC\AzureDevOpsApiClient;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;

class Workitem
{
    private ?string $htmlLink = null;

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
        return $this->state === 'Done';
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
        return $withEmbeddedADOImages
            ? $this->getImagesFromADOAndConvertToBase64($this->description)
            : $this->description;
    }

    public function getReproSteps(bool $withEmbeddedADOImages = false): string
    {
        return $withEmbeddedADOImages
            ? $this->getImagesFromADOAndConvertToBase64($this->reprosteps)
            : $this->reprosteps;
    }

    public function getSystemInfo(bool $withEmbeddedADOImages = false): string
    {
        return $withEmbeddedADOImages
            ? $this->getImagesFromADOAndConvertToBase64($this->systemInfo)
            : $this->systemInfo;
    }

    public function getAcceptanceCriteria(bool $withEmbeddedADOImages = false): string
    {
        return $withEmbeddedADOImages
            ? $this->getImagesFromADOAndConvertToBase64($this->acceptanceCriteria)
            : $this->acceptanceCriteria;
    }

    public function getResolution(bool $withEmbeddedADOImages = false): string
    {
        return $withEmbeddedADOImages
            ? $this->getImagesFromADOAndConvertToBase64($this->resolution)
            : $this->resolution;
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
            $mimeContentType = $mimeContentTypeFromHeader ?: 'image';

            $imgTag = '<img src="data:' . $mimeContentType . ';base64,' . $imgB64;
            $exploded[$key] = substr_replace($finding, $imgTag, 0, strlen($imgurlPart2));
        }
        return implode('', $exploded);
    }

    /**
     * Get all the text area fields of the workitem as collection.
     * Key of the Items is the field key from azure devops like System.Description
     * the item consists of an array that has name and conent of the field
     *
     * [
     * 'System.Description' =>
     *  [
     *      'name' => 'Description',
     *      'content' => '<div>This ist the description</div>'
     *  ]
     * ]
     * Array can be empty!
     *
     * @return array
     */
    public function getFieldsWithTextArea(bool $withEmbeddedADOImages = false): array
    {
        $fields = [];

        $fieldMapping = [
            'System.Description' => ['name' => 'Description', 'getter' => 'getDescription'],
            'Microsoft.VSTS.TCM.ReproSteps' => ['name' => 'Repro Steps', 'getter' => 'getReproSteps'],
            'Microsoft.VSTS.Common.SystemInfo' => ['name' => 'System Info', 'getter' => 'getSystemInfo'],
            'Microsoft.VSTS.Common.AcceptanceCriteria' => ['name' => 'Acceptence Criteria', 'getter' => 'getAcceptanceCriteria'],
            'Microsoft.VSTS.Common.Resolution' => ['name' => 'Resolution', 'getter' => 'getResolution'],
        ];

        foreach ($fieldMapping as $key => $config) {
            $content = $this->{$config['getter']}($withEmbeddedADOImages);
            if (!empty($content)) {
                $fields[$key] = [
                    'name' => $config['name'],
                    'content' => $content
                ];
            }
        }

        return $fields;
    }

    public function getHtmlLink(AzureDevOpsApiClient $azureApiClient): string
    {
        if ($this->htmlLink === null) {
            $azureDevOpsWorkitem = $azureApiClient->getWorkItemFromApiUrl($this->url);
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
