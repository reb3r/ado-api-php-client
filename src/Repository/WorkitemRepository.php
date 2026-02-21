<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC\Repository;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Reb3r\ADOAPC\AzureDevOpsApiClient;
use Reb3r\ADOAPC\Models\AttachmentReference;
use Reb3r\ADOAPC\Models\Tag;
use Reb3r\ADOAPC\Models\Workitem;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;
use Reb3r\ADOAPC\Exceptions\Exception;
use Reb3r\ADOAPC\Exceptions\WorkItemNotFoundException;
use Reb3r\ADOAPC\Exceptions\WorkItemNotUniqueException;

class WorkitemRepository
{
    public function __construct(
        private Client $guzzle,
        private string $projectBaseUrl,
        private string $organization,
        private string $project,
        /**
         * @var array<string, string>
         */
        private array $authHeader
    ) {
    }

    /**
     * @deprecated use WorkItemBuilder
     *
     * Creates and stores a new bug in azure DevOps
     * @param      string                     $title
     * @param      string                     $description = '' (ReproSteps)
     * @param      array<AttachmentReference|array<string, string>> $attachments (can be an empty array)
     * @param      array<Tag|string>          $tags
     *
     * @return Workitem the created item
     * @throws Exception|AuthenticationException when Request fails
     */
    public function createBug(
        string $title,
        string $description,
        array $attachments,
        array $tags,
        AzureDevOpsApiClient $apiClient
    ): Workitem {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/wit/work-items/create?view=azure-devops-rest-7.1
        $type = 'Bug';
        $query = '?api-version=7.1';
        $requestUrl = 'wit/workitems/$' . $type;
        $url = $this->projectBaseUrl . $requestUrl . $query;

        $requestBody = [
            [
                'op' => 'add',
                //path describes the Attribute that is defined by value
                'path' => '/fields/System.Title',
                'from' => null,
                'value' => $title
            ],
            [
                'op' => 'add',
                //path describes the Attribute that is defined by value
                'path' => '/fields/Microsoft.VSTS.TCM.ReproSteps',
                'from' => null,
                //Text Inputs are enclosed by divs...
                'value' => '<div>' . $description . '</div>'
            ]
        ];

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $url = $attachment instanceof AttachmentReference
                    ? $attachment->getUrl()
                    : $attachment['azureDevOpsUrl'];

                $requestBody[] = [
                    'op' => 'add',
                    'path' => '/relations/-',
                    'value' => [
                        'rel' => 'AttachedFile',
                        'url' => $url,
                        'attributes' => [
                            'comment' => 'Added from TicketStudio'
                        ]
                    ]
                ];
            }
        }

        if (!empty($tags)) {
            $value = '';
            foreach ($tags as $tag) {
                $value .= $tag . ';';
            }
            $value = mb_substr($value, 0, -1);
            $requestBody[] = [
                'op' => 'add',
                'path' => 'fields/System.Tags',
                'value' => $value
            ];
        }

        $headers = ['Content-Type' => 'application/json-patch+json'];
        $headers = array_merge($headers, $this->authHeader);

        try {
            $response = $this->guzzle->post(
                $url,
                [
                'body' => json_encode($requestBody),
                'headers' => $headers,
                'http_errors' => false
                ]
            );
        } catch (GuzzleException $e) {
            throw new Exception('Could not create Bug: ' . $e->getMessage());
        }

        if ($response->getStatusCode() === 200) {
            return Workitem::fromArray(json_decode($response->getBody()->getContents(), true), $apiClient);
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Could not create Bug: ' . $response->getStatusCode());
    }

    /**
     * append the new reprosteps text to the azure devops workitem
     *
     * @param  Workitem                   $workitem
     * @param  string                     $reproStepsText
     * @param  array<AttachmentReference|array<string, string>> $attachments    (can be an empty array)
     * @return void
     * @throws Exception when request fails
     */
    public function updateWorkitemReproStepsAndAttachments(Workitem $workitem, string $reproStepsText, array $attachments): void
    {
        $query = '?api-version=7.1';
        $requestUrl = 'wit/workitems/' . $workitem->getId();
        $url = $this->projectBaseUrl . $requestUrl . $query;

        $requestBody = [
            [
                'op' => 'add',
                //path describes the Attribute that is defined by value
                'path' => '/fields/Microsoft.VSTS.TCM.ReproSteps',
                'from' => null,
                //Text Inputs are enclosed by divs...
                'value' => '<div>' . $reproStepsText . '</div>'
            ]
        ];

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $requestBody[] = [
                    'op' => 'add',
                    'path' => '/relations/-',
                    'value' => [
                        'rel' => 'AttachedFile',
                        'url' => $attachment['azureDevOpsUrl'],
                        'attributes' => [
                            'comment' => 'Added from OTRS'
                        ]
                    ]
                ];
            }
        }

        $headers = ['Content-Type' => 'application/json-patch+json'];
        $headers = array_merge($headers, $this->authHeader);

        try {
            $response = $this->guzzle->patch(
                $url,
                [
                'body' => json_encode($requestBody),
                'headers' => $headers,
                'http_errors' => false
                ]
            );
        } catch (GuzzleException $e) {
            throw new Exception('Could not update workitem: ' . $e->getMessage());
        }

        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        if ($response->getStatusCode() >= 300) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }
    }

    /**
     * Adds a comment to a workitem
     *
     * @param Workitem $workitem
     * @param string   $commentText
     *
     * @return void
     * @throws Exception when request fails
     */
    public function addCommentToWorkitem(Workitem $workitem, string $commentText): void
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/comments/add?view=azure-devops-rest-6.0#commentmention
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0#webapiteam
        $query = '?api-version=7.1';
        $requestUrl = 'wit/workitems/' . $workitem->getId() . '/comments';
        $url = $this->projectBaseUrl . $requestUrl . $query;

        // https://stackoverflow.com/questions/58558388/ping-user-in-azure-devops-comment
        $requestBody = [
            'text' => $commentText
        ];
        $headers = ['Content-Type' => 'application/json'];
        $headers = array_merge($headers, $this->authHeader);

        try {
            $response = $this->guzzle->post(
                $url,
                [
                'body' => json_encode($requestBody),
                'headers' => $headers,
                'http_errors' => false
                ]
            );
        } catch (GuzzleException $e) {
            throw new Exception('Could not update workitem: ' . $e->getMessage());
        }

        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        if ($response->getStatusCode() >= 300) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }
    }

    /**
     * gets the workitem from azure dev ops from an api url
     *
     * @param string $apiUrl
     *
     * @return Workitem
     * @throws Exception when Request fails
     */
    public function getWorkItemFromApiUrl(string $apiUrl, AzureDevOpsApiClient $apiClient): Workitem
    {
        try {
            $response = $this->guzzle->get(
                $apiUrl,
                [
                'headers' => $this->authHeader,
                'http_errors' => false
                ]
            );
        } catch (GuzzleException $e) {
            throw new Exception('Request to AzureDevOps failed: ' . $e->getMessage());
        }

        if ($response->getStatusCode() === 200) {
            return Workitem::fromArray(json_decode($response->getBody()->getContents(), true), $apiClient);
        } elseif ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        } else {
            throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
        }
    }

    /**
     * gets the workitem from azure dev ops that belogs to the given otrsticket
     *
     * @param string $searchtext
     *
     * @return Workitem
     * @throws WorkItemNotFoundException if a workitem with the name can not be found
     * @throws WorkItemNotUniqueException if more than one workitem is found
     * @throws Exception when Request fails
     */
    public function searchWorkitem(string $searchtext, AzureDevOpsApiClient $apiClient): Workitem
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/search/work-item-search-results/fetch-work-item-search-results?view=azure-devops-rest-7.1

        $query = '?api-version=7.1';
        $requestUrl = 'search/workitemsearchresults';
        $url = 'https://almsearch.dev.azure.com/'  . $this->organization . '/' . $this->project . '/_apis/' . $requestUrl . $query;
        $requestBody = [
            'searchText' => $searchtext,
            '$skip' => 0,
            '$top' => 1,
            'filters' => null,
            '$orderBy' => [
                'field' => 'system.id',
                'sortOrder' => 'ASC'
            ],
            'includeFacets' => true
        ];

        try {
            $response = $this->guzzle->post(
                $url,
                [
                    'headers' => $this->authHeader,
                    'body' => json_encode($requestBody),
                    'http_errors' => false
                ]
            );
        } catch (GuzzleException $e) {
            throw new Exception('Request to AzureDevOps failed: ' . $e->getMessage());
        }

        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['count'] === 0) {
                throw new WorkItemNotFoundException('Could not find WorkItem for ' . $searchtext);
            }
            if ($result['count'] > 1) {
                throw new WorkItemNotUniqueException('More than one WorkItem found for OTRS#' . $searchtext);
            }

            return Workitem::fromArray($result['results'][0], $apiClient);
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * gets the workitem from azure dev ops that belogs to the given otrsticket
     *
     * @throws WorkItemNotFoundException if a workitem with the name can not be found
     * @throws WorkItemNotUniqueException if more than one workitem is found
     * @throws Exception when Request fails
     */
    /**
     * @param  array<int> $ids
     * @return array<Workitem>
     */
    public function getWorkitemsById(array $ids, AzureDevOpsApiClient $apiClient): array
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/search/work%20item%20search%20results/fetch%20work%20item%20search%20results?view=azure-devops-rest-6.0
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/search/work-item-search-results/fetch-work-item-search-results?view=azure-devops-rest-6.0&tabs=HTTP
        if (empty($ids) === true) {
            return [];
        }

        $idsString = '';
        foreach ($ids as $id) {
            $idsString = $idsString . $id . ',';
        }
        $idsString = substr_replace($idsString, "", -1); // remove last comma

        $query = '?api-version=7.1&ids=' . $idsString;
        $requestUrl = 'wit/workitems';
        $url = $this->projectBaseUrl  . $requestUrl . $query;

        try {
            $response = $this->guzzle->get(
                $url,
                [
                'headers' => $this->authHeader,
                'http_errors' => false
                ]
            );
        } catch (GuzzleException $e) {
            throw new Exception('Request to AzureDevOps failed: ' . $e->getMessage());
        }

        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true)['value'];

            $retCol = [];

            foreach ($result as $row) {
                $retCol[] = Workitem::fromArray($row, $apiClient);
            }
            return $retCol;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }
}
