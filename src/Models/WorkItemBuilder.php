<?php

namespace Reb3r\ADOAPC\Models;

use Reb3r\ADOAPC\Models\Workitem;
use Illuminate\Support\Collection;
use Reb3r\ADOAPC\AzureDevOpsApiClient;
use Reb3r\ADOAPC\Exceptions\Exception;

class WorkItemBuilder
{
    /**
     * The request body  
     * 
     * @var array */
    public $requestBody = [];

    /**  @var AzureDevOpsApiClient */
    public $apiClient;

    /** @var string */
    public $type;

    /**
     * Create a new WorkItemBuilderInstance
     * 
     * @param AzureDevOpsApiClient $apiClient
     * @param string $type 
     */
    public function __construct(AzureDevOpsApiClient $apiClient, string $type)
    {
        $this->apiClient = $apiClient;
        $this->type = $type;
    }

    /**
     * Create a new WorkItemBuilderInstance to build a Bug
     * 
     * @param AzureDevOpsApiClient $apiClient
     * @return WorkItemBuilder $this
     */
    public static function buildBug(AzureDevOpsApiClient $apiClient): WorkItemBuilder
    {
        return new self($apiClient, 'Bug');
    }

    /**
     * Create a new WorkItemBuilderInstance to build a Product Backlog Item
     * 
     * @param AzureDevOpsApiClient $apiClient
     * @return WorkItemBuilder $this
     */
    public static function buildPBI(AzureDevOpsApiClient $apiClient): WorkItemBuilder
    {
        return new self($apiClient, 'Product Backlog Item');
    }

    /**
     * Create the new workitem in the current iterationpath of the given team
     * 
     * @param Team $team
     * @return WorkItemBuilder $this
     */
    public function inCurrentIterationPath(Team $team): WorkItemBuilder
    {
        return $this->inIterationPath($this->apiClient->getCurrentIterationPath($team));
    }

    /**
     * Create the new workitem in the given iterationpath
     * 
     * @param string $iterationPath
     * @return WorkItemBuilder $this
     */
    public function inIterationPath(string $iterationPath): WorkItemBuilder
    {
        $this->requestBody['iterationPath'] = [
            'op' => 'add',
            'path' => '/fields/System.IterationPath',
            'value' => $iterationPath
        ];
        return $this;
    }

    /**
     * Create the new workitem in the given area path
     * 
     * @param string $areaPath
     * @return WorkItemBuilder $this
     */
    public function areaPath(string $areaPath): WorkItemBuilder
    {
        $this->requestBody['areaPath'] = [
            'op' => 'add',
            'path' => '/fields/System.AreaPath',
            'value' => $areaPath
        ];
        return $this;
    }

    /**
     * Add a title to the workitem
     * 
     * @param string $title
     * @return WorkItemBuilder $this
     */
    public function title(string $title): WorkItemBuilder
    {
        $this->requestBody['title'] = [
            'op' => 'add',
            'path' => '/fields/System.Title',
            'from' => null,
            'value' => $title
        ];
        return $this;
    }

    /**
     * Add reproSteps to the workitem
     * 
     * @param string $reproSteps
     * @return WorkItemBuilder $this
     */
    public function reproSteps(string $reproSteps): WorkItemBuilder
    {
        $this->requestBody['reproSteps'] = [
            'op' => 'add',
            'path' => '/fields/Microsoft.VSTS.TCM.ReproSteps',
            'from' => null,
            //Text Inputs are enclosed by divs...
            'value' => '<div>' . $reproSteps . '</div>'
        ];
        return $this;
    }

    /**
     * Add a description to the workitem
     * 
     * @param string $description
     * @return WorkItemBuilder $this
     */
    public function description(string $description): WorkItemBuilder
    {
        $this->requestBody['description'] = [
            'op' => 'add',
            'path' => '/fields/System.Description',
            'from' => null,
            //Text Inputs are enclosed by divs...
            'value' => '<div>' . $description . '</div>'
        ];
        return $this;
    }

    /**
     * Add attachments to the workitem
     * 
     * @param Collection<array> $attachments (can be an empty Collection)
     * @return WorkItemBuilder $this
     */
    public function attachments(Collection $attachments): WorkItemBuilder
    {
        foreach ($attachments as $attachment) {
            $this->requestBody[] = [
                'op' => 'add',
                'path' => '/relations/-',
                'value' => [
                    'rel' => 'AttachedFile',
                    'url' => $attachment['azureDevOpsUrl']
                ]
            ];
        }
        return $this;
    }

    /**
     * Creates and stores a new workitem in azure DevOps
     *
     * @return Workitem the created item
     * @throws Exception when Request fails
     */
    public function create(): Workitem
    {
        $query = '?api-version=6.1-preview.3';
        $requestUrl = 'wit/workitems/$' . $this->type;
        $url = $this->apiClient->getProjectBaseUrl() . $requestUrl . $query;

        $response =  $this->apiClient->patch($url, json_encode(array_values($this->requestBody)));

        return Workitem::fromArray(json_decode($response->getBody()->getContents(), true), $this->apiClient);
    }
}
