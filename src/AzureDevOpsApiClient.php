<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC;

use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Reb3r\ADOAPC\Exceptions\Exception;
use Reb3r\ADOAPC\Exceptions\WorkItemNotFoundException;
use Reb3r\ADOAPC\Exceptions\WorkItemNotUniqueException;
use Reb3r\ADOAPC\Models\AzureDevOpsWorkitem;

class AzureDevOpsApiClient
{
    /** @var string */
    private $username;
    /** @var string */
    private $password;
    /** @var string */
    private $baseUrl;
    /** @var string */
    private $organization;
    /** @var string */
    private $project;
    /** @var string */
    private $projectBaseUrl;
    /** @var Client */
    private $guzzle;
    /** @var string */
    private $team;

    public function __construct($username, $secret, $base_url, $organization, $project)
    {
        $this->username = $username;
        $this->password = $secret;
        $this->baseUrl = $base_url;
        $this->organization = $organization;
        $this->project = $project;
        $this->projectBaseUrl = $this->baseUrl . $this->organization . '/' . $this->project . '/_apis/';
        $this->guzzle = new Client();
    }

    /**
     * Sets an alternative http client for api calls.
     * Also useful for unit tests.
     * 
     * @param Client $client 
     * @return void 
     */
    public function setHttpClient(Client $client)
    {
        $this->guzzle = $client;
    }

    /**
     * Creates and stores a new bug in azure DevOps
     * @param string $title
     * @param string $description = '' (ReproSteps)
     * @param Collection $attachments (can be an empty Collection)
     *
     * @return AzureDevOpsWorkitem the created item
     * @throws Exception when Request fails
     */
    public function createBug(string $title, string $description, Collection $attachments): AzureDevOpsWorkitem
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/work items/create?view=azure-devops-rest-6.1
        $type = 'Bug';
        $query = '?api-version=6.1-preview.3';
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

        if ($attachments->isNotEmpty()) {
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

        if ($this->azureDevOpsConfiguration->path != null) {
            try {
                $requestBody[] = [
                    'op' => 'add',
                    'path' => '/fields/System.AreaPath',
                    'value' => $this->azureDevOpsConfiguration->path
                ];
            } catch (Exception $e) {
            }
        }

        if ($this->azureDevOpsConfiguration->isWorkItemIterationCurrent() === true) {
            try {
                $requestBody[] = [
                    'op' => 'add',
                    'path' => '/fields/System.IterationPath',
                    'value' => $this->getCurrentIterationPath()
                ];
            } catch (Exception $e) {
            }
        }
        $headers = ['Content-Type' => 'application/json-patch+json'];
        $response = $this->guzzle->post($url, [
            'auth' => [$this->username, $this->password],
            'body' => $requestBody,
            'headers' => $headers
        ]);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return new AzureDevOpsWorkitem(json_decode($response->getBody()->getContents(), true));
        }
        throw new Exception('Could not create Bug: ' . $response->getStatusCode());
    }

    /**
     * tries to find the azure devops id of the team by throwing a team name against the api
     * @param string $teamName
     *
     * @return string $id
     * @throws Exception when no team or more than one team was found
     */
    public function getTeamIdByName(string $teamName): string
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0
        $query = '?api-version=6.0-preview.3';
        $requestUrl = 'teams';
        $url = $this->baseUrl . $this->organization  . '/_apis/' . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['auth' => [$this->username, $this->password]]);
        if ($response->getStatusCode() >= 400) {
            throw new Exception('Could not get Team: ' . $response->getStatusCode());
        }
        $teams = collect(json_decode($response->getBody()->getContents(), true)['value']);
        $team = $teams->whereStrict('name', $teamName);
        if ($team->count() < 1) {
            throw new Exception('Team not found');
        }
        if ($team->count() > 1) {
            throw new Exception('More than one team found');
        }
        return $team->first()['id'];
    }

    /**
     * append the new reprosteps text to the azure devops workitem
     * @param AzureDevOpsWorkitem $azureDevOpsWorkitem
     * @param string $reproStepsText
     * @param Collection $attachments (can be an empty Collection)
     * @return void
     * @throws Exception when request fails
     */
    public function updateWorkitemReproStepsAndAttachments(AzureDevOpsWorkitem $azureDevOpsWorkitem, string $reproStepsText, Collection $attachments): void
    {
        $query = '?api-version=6.0';
        $requestUrl = 'wit/workitems/' . $azureDevOpsWorkitem->id;
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

        if ($attachments->isNotEmpty()) {
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
        $response = $this->guzzle->patch($url, [
            'auth' => [$this->username, $this->password],
            'body' => $requestBody,
            'headers' => $headers
        ]);
        if ($response->getStatusCode() >= 400) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }
    }

    /**
     * Adds a comment to a workitem
     * @param AzureDevOpsWorkitem $azureDevOpsWorkitem
     * @param string $commentText
     *
     * @return void
     * @throws Exception when request fails
     */
    public function addCommentToWorkitem(AzureDevOpsWorkitem $azureDevOpsWorkitem, string $commentText): void
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/comments/add?view=azure-devops-rest-6.0#commentmention
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0#webapiteam
        $query = '?api-version=6.0-preview.3';
        $requestUrl = 'wit/workitems/' . $azureDevOpsWorkitem->id . '/comments';
        $url = $this->projectBaseUrl . $requestUrl . $query;

        // https://stackoverflow.com/questions/58558388/ping-user-in-azure-devops-comment
        $requestBody = [
            'text' => $commentText
        ];
        $headers = ['Content-Type' => 'application/json'];

        $response = $this->guzzle->post($url, [
            'auth' => [$this->username, $this->password],
            'body' => $requestBody,
            'headers' => $headers
        ]);
        if ($response->getStatusCode() >= 400) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }
    }

    /**
     * Uploads and File to Azure Dev Ops an returns the answer
     * the answer includes the created id and link
     *
     * @param string $fileName
     * @param string $content
     * @return array $response->json()
     */
    public function uploadAttachment(string $fileName,  string $content)
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/attachments/create?view=azure-devops-rest-6.0
        $query = '?fileName=' . $fileName . '&api-version=6.0-preview.3';
        $requestUrl = 'wit/attachments';
        $url = $this->projectBaseUrl . $requestUrl . $query;

        $contentType = 'application/octet-stream';

        //$response = Http::withBasicAuth($this->username, $this->password)->withBody($content, $contentType)->post($url);
        $response = $this->guzzle->post($url, [
            'auth' => [$this->username, $this->password],
            'body' => $content,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * gets the workitem from azure dev ops from an api url
     * @param string $apiUrl

     * @return AzureDevOpsWorkitem
     * @throws Exception when Request fails
     */
    public function getWorkItemFromApiUrl(string $apiUrl): AzureDevOpsWorkitem
    {
        $response = $this->guzzle->get($apiUrl, ['auth' => [$this->username, $this->password]]);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return new AzureDevOpsWorkitem(json_decode($response->getBody()->getContents(), true));
        } else {
            throw new Exception('Could not create Bug: ' . $response->getStatusCode());
        }
    }

    /**
     * gets the workitem from azure dev ops that belogs to the given otrsticket
     * @param string $otrsTicket

     * @return AzureDevOpsWorkitem
     * @throws WorkItemNotFoundException if a workitem with the name can not be found
     * @throws WorkItemNotUniqueException if more than one workitem is found
     * @throws Exception when Request fails
     */
    public function searchWorkitem($searchtext): AzureDevOpsWorkitem
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/search/work%20item%20search%20results/fetch%20work%20item%20search%20results?view=azure-devops-rest-6.0

        $query = '?api-version=6.0-preview.1';
        $requestUrl = 'search/workitemsearchresults';
        $url = 'https://almsearch.dev.azure.com/'  . $this->organization . '/' . $this->project . '/_apis/' . $requestUrl . $query;
        $requestBody = [
            'searchText' => $searchtext,
            '$skip' => 0,
            '$top' => 1,
            // 'filters' => [
            //     'System.WorkItemType' => ['Bug'],
            //     'System.State' => ['New', 'Active']
            // ],
            'filters' => null,
            '$orderBy' => [
                'field' => 'system.id',
                'sortOrder' => 'ASC'
            ],
            'includeFacets' => true
        ];

        $response = $this->guzzle->post($url, ['auth' => [$this->username, $this->password], 'body' => $requestBody]);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            if (json_decode($response->getBody()->getContents(), true)['count'] === 0) {
                throw new WorkItemNotFoundException('Could not find WorkItem for ' . $searchtext);
            }
            if (json_decode($response->getBody()->getContents(), true)['count'] > 1) {
                throw new WorkItemNotUniqueException('More than one WorkItem found for OTRS#' . $searchtext);
            }
            $result = json_decode($response->getBody()->getContents(), true)['results'][0];

            return new AzureDevOpsWorkitem($result);
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * gets the workitem from azure dev ops that belogs to the given otrsticket
     * @throws WorkItemNotFoundException if a workitem with the name can not be found
     * @throws WorkItemNotUniqueException if more than one workitem is found
     * @throws Exception when Request fails
     */
    public function getWorkitemsById(array $ids): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/search/work%20item%20search%20results/fetch%20work%20item%20search%20results?view=azure-devops-rest-6.0
        $idsString = '';
        foreach ($ids as $id) {
            $idsString = $idsString . $id . ',';
        }
        $idsString = substr_replace($idsString, "", -1); // remove last comma

        $query = '?api-version=6.0&ids=' . $idsString;
        $requestUrl = 'wit/workitems';
        $url = 'https://dev.azure.com/'  . $this->organization . '/' . $this->project . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['auth' => [$this->username, $this->password]]);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['value']);

            $retCol = collect();
            $result->each(function ($value) use ($retCol) {
                $retCol->push(new AzureDevOpsWorkitem($value));
            });
            return $retCol;
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    public function getBacklogs(string $team): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/work/backlogs/list?view=azure-devops-rest-6.0
        $query = '?api-version=6.0-preview.1';
        $requestUrl = 'work/backlogs';
        $url = 'https://dev.azure.com/'  . $this->organization . '/' . $this->project . '/' . $team . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['auth' => [$this->username, $this->password]]);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['value']);

            return $result;
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    public function getBacklogWorkItems(string $team, string $backlogId)
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/work/backlogs/get%20backlog%20level%20work%20items?view=azure-devops-rest-6.0
        $query = '?api-version=6.0-preview.1';
        $requestUrl = 'work/backlogs/' . $backlogId . '/workitems';
        $url = 'https://dev.azure.com/'  . $this->organization . '/' . $this->project . '/' . $team . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['auth' => [$this->username, $this->password]]);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $result = collect(json_decode($response->getBody()->getContents(), true));

            return $result;
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Returns the current iterationPath value to save it in work Items
     * Depends on azureDevOpsConfiguration >organization >project and <team
     *
     * @return string
     * @throws Exception when path could not be found
     */
    public function getCurrentIterationPath(): string
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/work/teamsettings/get?view=azure-devops-rest-6.0
        $teamname = $this->team;
        if ($this->team === null) {
            throw new Exception('No Team configured');
        }
        $teamId = $this->getTeamIdByName($teamname);
        $query = '?$timeframe=current&api-version=6.0';
        $requestUrl = 'work/teamsettings/iterations';
        $url = $this->baseUrl . $this->organization  . '/' . $this->project . '/' . $teamId . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['auth' => [$this->username, $this->password]]);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            if (json_decode($response->getBody()->getContents(), true)['count'] === 0) {
                throw new Exception('Could not find Iteration for ' .  $this->organization  . '/' . $this->project . '/' . $teamname);
            }
            if (json_decode($response->getBody()->getContents(), true)['count'] > 1) {
                throw new Exception('More than one Iteration found for ' .  $this->organization  . '/' . $this->project . '/' . $teamname);
            }
            return json_decode($response->getBody()->getContents(), true)['value'][0]['path'];
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Get the teams of the configured organization and project
     */
    public function getTeams(): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0
        $query = '?api-version=6.0';
        $requestUrl = 'teams';
        $url = 'https://dev.azure.com/'  . $this->organization .  '/_apis/projects/' . $this->project . '/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['auth' => [$this->username, $this->password]]);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $result = collect(json_decode($response->getBody()->getContents(), true)('value'));

            return $result;
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }
}
