<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC;

use RuntimeException;
use GuzzleHttp\Client;
use Reb3r\ADOAPC\Models\Tag;
use Reb3r\ADOAPC\Models\Team;
use Reb3r\ADOAPC\Models\Project;
use Reb3r\ADOAPC\Models\Workitem;
use Illuminate\Support\Collection;
use Reb3r\ADOAPC\Exceptions\Exception;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\GuzzleException;
use Reb3r\ADOAPC\Models\AttachmentReference;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;
use Reb3r\ADOAPC\Exceptions\WorkItemNotFoundException;
use Reb3r\ADOAPC\Exceptions\WorkItemNotUniqueException;

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
    private $organizationBaseUrl;
    /** @var string */
    private $projectBaseUrl;
    /** @var Client */
    private $guzzle;

    public function __construct(string $username, string $secret, string $base_url, string $organization, string $project)
    {
        $this->username = $username;
        $this->password = $secret;
        $this->baseUrl = $base_url;
        $this->organization = $organization;
        $this->project = $project;
        $this->organizationBaseUrl = $this->baseUrl . $this->organization . '/';
        $this->projectBaseUrl =   $this->organizationBaseUrl . $this->project . '/_apis/';
        $this->guzzle = new Client();
    }

    private function getAuthHeader(): array
    {
        if (empty($this->username)) {
            return ['Authorization' => 'Bearer ' . $this->password];
        }
        return ['Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)];
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


    public function getProjectBaseUrl(): string
    {
        return $this->projectBaseUrl;
    }

    public function getOrganizationBaseUrl(): string
    {
        return $this->organizationBaseUrl;
    }

    /**
     * sends a post Request to azure via json post
     * ['Content-Type' => 'application/json']
     * @param string $url
     * @param string $body
     *
     * @return Workitem
     * @throws AuthenticationException
     * @throws Exception
     */
    public function post(string $url, string $body): ResponseInterface
    {
        $headers = ['Content-Type' => 'application/json'];
        return $this->sendPost($url, $headers, $body);
    }

    /**
     * sends a post Request to azure via json patch
     * ['Content-Type' => 'application/json-patch+json']
     * @param string $url
     * @param string $body
     *
     * @return Workitem
     * @throws AuthenticationException
     * @throws Exception
     */
    public function patch(string $url, string $body): ResponseInterface
    {
        $headers = ['Content-Type' => 'application/json-patch+json'];
        return $this->sendPost($url, $headers, $body);
    }

    private function sendPost($url, $headers, $body): ResponseInterface
    {
        $headers = array_merge($headers, $this->getAuthHeader());

        $response = $this->guzzle->post($url, [
            'body' => $body,
            'headers' => $headers
        ]);
        if ($response->getStatusCode() === 200) {
            return $response;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request failed: ' . $response->getStatusCode());
    }

    /**
     * Downloads an Attachment from Azure DevOps and returns it
     * @param string $url Full Url is needed!
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws AuthenticationException
     * @throws Exception
     */
    public function getImageAttachment(string $url): \Psr\Http\Message\ResponseInterface
    {
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);

        if ($response->getStatusCode() === 200) {
            return $response;
        } else if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        } else {
            throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
        }
    }

    /**
     * @deprecated use WorkItemBuilder
     *
     * Creates and stores a new bug in azure DevOps
     * @param string $title
     * @param string $description = '' (ReproSteps)
     * @param Collection<array> $attachments (can be an empty Collection)
     * @param Collection<Tag> $tags
     *
     * @return Workitem the created item
     * @throws Exception when Request fails
     */
    public function createBug(string $title, string $description, Collection $attachments, Collection $tags = new Collection()): Workitem
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
                            'comment' => 'Added from TicketStudio'
                        ]
                    ]
                ];
            }
        }

        if ($tags->isNotEmpty()) {
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

        /*if ($this->azureDevOpsConfiguration->path != null) {
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
        }*/
        $headers = ['Content-Type' => 'application/json-patch+json'];
        $headers = array_merge($headers, $this->getAuthHeader());
        $response = $this->guzzle->post($url, [
            'body' => json_encode($requestBody),
            'headers' => $headers
        ]);
        if ($response->getStatusCode() === 200) {
            return Workitem::fromArray(json_decode($response->getBody()->getContents(), true), $this);
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Could not create Bug: ' . $response->getStatusCode());
    }

    /**
     * tries to find the azure devops id of the team by throwing a team name against the api
     * @param string $teamName
     *
     * @return string $id
     * @throws Exception when no team or more than one team was found
     * @deprecated
     */
    public function getTeamIdByName(string $teamName): string
    {
        $teams = $this->getTeams();
        $team = $teams->filter(function (Team $team) use ($teamName) {
            return $team->getName() === $teamName;
        });
        if ($team->count() < 1) {
            throw new Exception('Team not found');
        }
        if ($team->count() > 1) {
            throw new Exception('More than one team found');
        }
        return $team->first()->getId();
    }


    /**
     * append the new reprosteps text to the azure devops workitem
     * @param Workitem $workitem
     * @param string $reproStepsText
     * @param Collection $attachments (can be an empty Collection)
     * @return void
     * @throws Exception when request fails
     */
    public function updateWorkitemReproStepsAndAttachments(Workitem $workitem, string $reproStepsText, Collection $attachments): void
    {
        $query = '?api-version=6.0';
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
        $headers = array_merge($headers, $this->getAuthHeader());
        $response = $this->guzzle->patch($url, [
            'body' => json_encode($requestBody),
            'headers' => $headers
        ]);
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        if ($response->getStatusCode() >= 300) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }
    }

    /**
     * Adds a comment to a workitem
     * @param Workitem $workitem
     * @param string $commentText
     *
     * @return void
     * @throws Exception when request fails
     */
    public function addCommentToWorkitem(Workitem $workitem, string $commentText): void
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/comments/add?view=azure-devops-rest-6.0#commentmention
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0#webapiteam
        $query = '?api-version=6.0-preview.3';
        $requestUrl = 'wit/workitems/' . $workitem->getId() . '/comments';
        $url = $this->projectBaseUrl . $requestUrl . $query;

        // https://stackoverflow.com/questions/58558388/ping-user-in-azure-devops-comment
        $requestBody = [
            'text' => $commentText
        ];
        $headers = ['Content-Type' => 'application/json'];
        $headers = array_merge($headers, $this->getAuthHeader());

        $response = $this->guzzle->post($url, [
            'body' => json_encode($requestBody),
            'headers' => $headers
        ]);
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        if ($response->getStatusCode() >= 300) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }
    }

    /**
     * Uploads and File to Azure Dev Ops an returns the answer
     * the answer includes the created id and link
     *
     * @param string $fileName
     * @param string $content
     * @return AttachmentReference
     */
    public function uploadAttachment(string $fileName,  string $content): AttachmentReference
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/attachments/create?view=azure-devops-rest-6.0
        $query = '?fileName=' . $fileName . '&api-version=6.0-preview.3';
        $requestUrl = 'wit/attachments';
        $url = $this->projectBaseUrl . $requestUrl . $query;

        $contentType = 'application/octet-stream';

        //$response = Http::withBasicAuth($this->username, $this->password)->withBody($content, $contentType)->post($url);
        $response = $this->guzzle->post($url, [
            'headers' => $this->getAuthHeader(),
            'body' => $content,
        ]);

        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        if ($response->getStatusCode() >= 400) {
            throw new Exception('Could not update workitem: ' . $response->getStatusCode());
        }

        return AttachmentReference::fromArray(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * gets the workitem from azure dev ops from an api url
     * @param string $apiUrl

     * @return Workitem
     * @throws Exception when Request fails
     */
    public function getWorkItemFromApiUrl(string $apiUrl): Workitem
    {
        $response = $this->guzzle->get($apiUrl, ['headers' => $this->getAuthHeader()]);

        if ($response->getStatusCode() === 200) {
            return Workitem::fromArray(json_decode($response->getBody()->getContents(), true), $this);
        } else if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        } else {
            throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
        }
    }

    /**
     * gets the workitem from azure dev ops that belogs to the given otrsticket
     * @param string $searchtext

     * @return Workitem
     * @throws WorkItemNotFoundException if a workitem with the name can not be found
     * @throws WorkItemNotUniqueException if more than one workitem is found
     * @throws Exception when Request fails
     */
    public function searchWorkitem($searchtext): Workitem
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

        $response = $this->guzzle->post(
            $url,
            [
                'headers' => $this->getAuthHeader(),
                'body' => json_encode($requestBody)
            ]
        );
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['count'] === 0) {
                throw new WorkItemNotFoundException('Could not find WorkItem for ' . $searchtext);
            }
            if ($result['count'] > 1) {
                throw new WorkItemNotUniqueException('More than one WorkItem found for OTRS#' . $searchtext);
            }

            return Workitem::fromArray($result['results'][0], $this);
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
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
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/search/work-item-search-results/fetch-work-item-search-results?view=azure-devops-rest-6.0&tabs=HTTP
        if (empty($ids) === true) {
            return collect();
        }

        $idsString = '';
        foreach ($ids as $id) {
            $idsString = $idsString . $id . ',';
        }
        $idsString = substr_replace($idsString, "", -1); // remove last comma

        $query = '?api-version=6.0&ids=' . $idsString;
        $requestUrl = 'wit/workitems';
        $url = $this->projectBaseUrl  . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);

        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['value']);

            $retCol = collect();
            // dd($result);

            $result->each(function (array $row) use ($retCol) {
                $retCol->push(Workitem::fromArray($row, $this));
            });
            return $retCol;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    public function getBacklogs(string $team): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/work/backlogs/list?view=azure-devops-rest-6.0
        $query = '?api-version=6.0-preview.1';
        $requestUrl = 'work/backlogs';
        $url = 'https://dev.azure.com/'  . $this->organization . '/' . $this->project . '/' . $team . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['value']);

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    public function getBacklogWorkItems(Team $team, string $backlogId): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/work/backlogs/get%20backlog%20level%20work%20items?view=azure-devops-rest-6.0
        $query = '?api-version=6.0-preview.1';
        $requestUrl = 'work/backlogs/' . $backlogId . '/workitems';
        $url = 'https://dev.azure.com/'  . $this->organization . '/' . $this->project . '/' . $team->getId() . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true));

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Returns the current iterationPath value to save it in work Items
     * Depends on azureDevOpsConfiguration >organization >project and <team
     *
     * @param Team $team
     *
     * @return string
     * @throws Exception when path could not be found
     */
    public function getCurrentIterationPath(Team $team): string
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/work/teamsettings/get?view=azure-devops-rest-6.0
        $query = '?$timeframe=current&api-version=6.0';
        $requestUrl = 'work/teamsettings/iterations';
        $url = $this->baseUrl . $this->organization  . '/' . $this->project . '/' . $team->getId() . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['count'] === 0) {
                throw new Exception('Could not find Iteration for ' .  $this->organization  . '/' . $this->project . '/' . $team->getId());
            }

            if ($result['count'] > 1) {
                throw new Exception('More than one Iteration found for ' .  $this->organization  . '/' . $this->project . '/' . $team->getId());
            }
            return $result['value'][0]['path'];
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Get the teams of the configured organization and project
     * @throws Exception
     */
    public function getTeams(): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get%20all%20teams?view=azure-devops-rest-6.0
        $query = '?api-version=6.0';
        $requestUrl = 'teams';
        $url = 'https://dev.azure.com/'  . $this->organization .  '/_apis/projects/' . $this->project . '/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['value']);
            $retCol = collect();
            foreach ($result as $row) {
                $retCol->push(Team::fromArray($row));
            }

            return $retCol;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Get all the teams visible by api
     * @return Collection
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws Exception
     */
    public function getAllTeams(): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get-all-teams?view=azure-devops-rest-6.0
        $query = '?api-version=6.0-preview.3';
        $requestUrl = '/_apis/teams';
        $url = $this->baseUrl . $this->organization . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['value']);
            $retCol = collect();
            foreach ($result as $row) {
                $retCol->push(Team::fromArray($row));
            }

            return $retCol;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Get the team by ID of the configured organization and project
     * @return Team
     * @throws Exception
     * @throws AuthenticationException
     */
    public function getTeam(string $teamId): Team
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/teams/get?view=azure-devops-rest-6.0
        $query = '?api-version=6.0';
        $requestUrl = 'teams/' . $teamId;
        $url = 'https://dev.azure.com/'  . $this->organization .  '/_apis/projects/' . $this->project . '/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true);
            return Team::fromArray($result);
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    public function getRootQueryFolders(int $depth = 0): Collection
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/queries/list?view=azure-devops-rest-6.0
        $query = '?$depth=' . $depth . '&api-version=6.0';
        $requestUrl = '_apis/wit/queries';
        $url = 'https://dev.azure.com/'  . $this->organization .  '/' . $this->project . '/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['value']);

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    public function getAllQueries(): Collection
    {
        $queryFolders = $this->getRootQueryFolders(1);

        $queries = collect();
        foreach ($queryFolders as $queryFolder) {
            if ($queryFolder['hasChildren'] === true) {
                $queries = $queries->merge($queryFolder['children']);
            }
        }
        return $queries;
    }

    /**
     * @see https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/wiql/query%20by%20id?view=azure-devops-rest-6.0
     *
     * @param string $teamname
     * @param string $queryId
     * @return Collection
     * @throws Exception
     * @throws GuzzleException
     * @throws RuntimeException
     */
    public function getQueryResultById(Team $team, string $queryId): Collection
    {
        $query = '?api-version=6.0';
        $requestUrl = '/_apis/wit/wiql/' . $queryId;
        $url = $this->baseUrl . $this->organization  . '/' . $this->project . '/' . $team->getId() . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true)['workItems']);

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Gets the projects
     * @return Collection
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws Exception
     */
    public function getProjects()
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/projects/list?view=azure-devops-rest-6.0
        $query = '?api-version=6.0';
        $requestUrl = '/_apis/projects';
        $url = $this->baseUrl . $this->organization . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect();
            foreach (json_decode($response->getBody()->getContents(), true)['value'] as $row) {
                $result->push(Project::fromArray($row));
            }

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Gets a project by ID
     * @return Project
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws AuthenticationException
     * @throws Exception
     */
    public function getProject(string $projectId): Project
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/core/projects/list?view=azure-devops-rest-6.0
        $query = '?api-version=6.0';
        $requestUrl = '/_apis/projects/' . $projectId;
        $url = $this->baseUrl . $this->organization . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true);
            return Project::fromArray($result);
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Returns the list of work item types
     * @return Collection
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws AuthenticationException
     * @throws Exception
     */
    public function getWorkItemTypes(): Collection
    {
        $query = '?api-version=7.1-preview.2';
        $requestUrl = '/' . $this->project . '/_apis/wit/workitemtypes';
        $url = $this->baseUrl . $this->organization . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = collect(json_decode($response->getBody()->getContents(), true));
            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }
}
