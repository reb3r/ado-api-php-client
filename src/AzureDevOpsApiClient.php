<?php

declare(strict_types=1);

namespace Reb3r\ADOAPC;

use RuntimeException;
use GuzzleHttp\Client;
use Reb3r\ADOAPC\Models\Tag;
use Reb3r\ADOAPC\Models\Team;
use Reb3r\ADOAPC\Models\Project;
use Reb3r\ADOAPC\Models\Workitem;
use Reb3r\ADOAPC\Exceptions\Exception;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\GuzzleException;
use Reb3r\ADOAPC\Models\AttachmentReference;
use Reb3r\ADOAPC\Exceptions\AuthenticationException;
use Reb3r\ADOAPC\Exceptions\WorkItemNotFoundException;
use Reb3r\ADOAPC\Exceptions\WorkItemNotUniqueException;
use Reb3r\ADOAPC\Repository\WorkitemRepository;

class AzureDevOpsApiClient
{
    /**
     * @var string
     */
    private $username;
    /**
     * @var string
     */
    private $password;
    /**
     * @var string
     */
    private $baseUrl;
    /**
     * @var string
     */
    private $organization;
    /**
     * @var string
     */
    private $project;
    /**
     * @var string
     */
    private $organizationBaseUrl;
    /**
     * @var string
     */
    private $projectBaseUrl;
    /**
     * @var Client
     */
    private $guzzle;
    private WorkitemRepository $workitemRepository;

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
        $this->workitemRepository = new WorkitemRepository(
            $this->guzzle,
            $this->projectBaseUrl,
            $this->organization,
            $this->project,
            $this->getAuthHeader()
        );
    }

    /**
     * @return array<string, string>
     */
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
     * @param  Client $client
     * @return void
     */
    public function setHttpClient(Client $client)
    {
        $this->guzzle = $client;
        $this->workitemRepository = new WorkitemRepository(
            $this->guzzle,
            $this->projectBaseUrl,
            $this->organization,
            $this->project,
            $this->getAuthHeader()
        );
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
     *
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
     *
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

    /**
     * @param array<string, string> $headers
     */
    private function sendPost(string $url, array $headers, string $body): ResponseInterface
    {
        $headers = array_merge($headers, $this->getAuthHeader());

        $response = $this->guzzle->post(
            $url,
            [
            'body' => $body,
            'headers' => $headers
            ]
        );
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
     *
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
        } elseif ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        } else {
            throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
        }
    }

    /**
     * @deprecated use WorkItemBuilder
     *
     * Creates and stores a new bug in azure DevOps
     * @param      string                     $title
     * @param      string                     $description = '' (ReproSteps)
     * @param      array<AttachmentReference> $attachments (can be an empty array)
     * @param      array<Tag>                 $tags
     *
     * @return Workitem the created item
     * @throws Exception when Request fails
     */
    public function createBug(string $title, string $description, array $attachments, array $tags = []): Workitem
    {
        return $this->workitemRepository->createBug($title, $description, $attachments, $tags, $this);
    }

    /**
     * tries to find the azure devops id of the team by throwing a team name against the api
     *
     * @param string $teamName
     *
     * @return     string $id
     * @throws     Exception when no team or more than one team was found
     * @deprecated
     */
    public function getTeamIdByName(string $teamName): string
    {
        $teams = $this->getTeams();
        $team = array_filter(
            $teams,
            function (Team $team) use ($teamName) {
                return $team->getName() === $teamName;
            }
        );
        if (count($team) < 1) {
            throw new Exception('Team not found');
        }
        if (count($team) > 1) {
            throw new Exception('More than one team found');
        }
        return reset($team)->getId();
    }


    /**
     * append the new reprosteps text to the azure devops workitem
     *
     * @param  Workitem                   $workitem
     * @param  string                     $reproStepsText
     * @param  array<AttachmentReference> $attachments    (can be an empty array)
     * @return void
     * @throws Exception when request fails
     */
    public function updateWorkitemReproStepsAndAttachments(Workitem $workitem, string $reproStepsText, array $attachments): void
    {
        $this->workitemRepository->updateWorkitemReproStepsAndAttachments($workitem, $reproStepsText, $attachments);
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
        $this->workitemRepository->addCommentToWorkitem($workitem, $commentText);
    }

    /**
     * Uploads and File to Azure Dev Ops an returns the answer
     * the answer includes the created id and link
     *
     * @param  string $fileName
     * @param  string $content
     * @return AttachmentReference
     */
    public function uploadAttachment(string $fileName, string $content): AttachmentReference
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/wit/attachments/create?view=azure-devops-rest-7.1
        $query = '?fileName=' . $fileName . '&api-version=7.1';
        $requestUrl = 'wit/attachments';
        $url = $this->projectBaseUrl . $requestUrl . $query;

        $contentType = 'application/octet-stream';

        //$response = Http::withBasicAuth($this->username, $this->password)->withBody($content, $contentType)->post($url);
        $response = $this->guzzle->post(
            $url,
            [
            'headers' => $this->getAuthHeader(),
            'body' => $content,
            ]
        );

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
     *
     * @param string $apiUrl

     * @return Workitem
     * @throws Exception when Request fails
     */
    public function getWorkItemFromApiUrl(string $apiUrl): Workitem
    {
        return $this->workitemRepository->getWorkItemFromApiUrl($apiUrl, $this);
    }

    /**
     * gets the workitem from azure dev ops that belogs to the given otrsticket
     *
     * @param string $searchtext

     * @return Workitem
     * @throws WorkItemNotFoundException if a workitem with the name can not be found
     * @throws WorkItemNotUniqueException if more than one workitem is found
     * @throws Exception when Request fails
     */
    public function searchWorkitem($searchtext): Workitem
    {
        return $this->workitemRepository->searchWorkitem($searchtext, $this);
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
    public function getWorkitemsById(array $ids): array
    {
        return $this->workitemRepository->getWorkitemsById($ids, $this);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBacklogs(string $team): array
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/work/backlogs/list?view=azure-devops-rest-7.1
        $query = '?api-version=7.1';
        $requestUrl = 'work/backlogs';
        $url = 'https://dev.azure.com/'  . $this->organization . '/' . $this->project . '/' . $team . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true)['value'];

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    public function getBacklogWorkItems(Team $team, string $backlogId): array
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/work/backlogs/get-backlog-level-work-items?view=azure-devops-rest-7.1
        $query = '?api-version=7.1';
        $requestUrl = 'work/backlogs/' . $backlogId . '/workitems';
        $url = 'https://dev.azure.com/'  . $this->organization . '/' . $this->project . '/' . $team->getId() . '/_apis/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true);

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
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/work/iterations/list?view=azure-devops-rest-7.1
        $query = '?$timeframe=current&api-version=7.1';
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
     *
     * @return array<Team>
     * @throws Exception
     */
    public function getTeams(): array
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/core/teams/get-teams?view=azure-devops-rest-7.1
        $query = '?api-version=7.1';
        $requestUrl = 'teams';
        $url = 'https://dev.azure.com/'  . $this->organization .  '/_apis/projects/' . $this->project . '/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true)['value'];
            $retCol = [];
            foreach ($result as $row) {
                $retCol[] = Team::fromArray($row);
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
     *
     * @return array<Team>
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws Exception
     */
    public function getAllTeams(): array
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/core/teams/get-all-teams?view=azure-devops-rest-7.1
        $query = '?api-version=7.1';
        $requestUrl = '/_apis/teams';
        $url = $this->baseUrl . $this->organization . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true)['value'];
            $retCol = [];
            foreach ($result as $row) {
                $retCol[] = Team::fromArray($row);
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
     *
     * @return Team
     * @throws Exception
     * @throws AuthenticationException
     */
    public function getTeam(string $teamId): Team
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/core/teams/get?view=azure-devops-rest-7.1
        $query = '?api-version=7.1';
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRootQueryFolders(int $depth = 0): array
    {
        // https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/queries/list?view=azure-devops-rest-6.0
        $query = '?$depth=' . $depth . '&api-version=7.1';
        $requestUrl = '_apis/wit/queries';
        $url = 'https://dev.azure.com/'  . $this->organization .  '/' . $this->project . '/' . $requestUrl . $query;

        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true)['value'];

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllQueries(): array
    {
        $queryFolders = $this->getRootQueryFolders(1);

        $queries = [];
        foreach ($queryFolders as $queryFolder) {
            if ($queryFolder['hasChildren'] === true) {
                $queries = array_merge($queries, $queryFolder['children']);
            }
        }
        return $queries;
    }

    /**
     * @see https://docs.microsoft.com/en-us/rest/api/azure/devops/wit/wiql/query%20by%20id?view=azure-devops-rest-6.0
     *
     * @param  Team   $team
     * @param  string $queryId
     * @return array<int, array<string, mixed>>
     * @throws Exception
     * @throws GuzzleException
     * @throws RuntimeException
     */
    public function getQueryResultById(Team $team, string $queryId): array
    {
        $query = '?api-version=7.1';
        $requestUrl = '/_apis/wit/wiql/' . $queryId;
        $url = $this->baseUrl . $this->organization  . '/' . $this->project . '/' . $team->getId() . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true)['workItems'];

            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }

    /**
     * Gets the projects
     *
     * @return array<Project>
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws Exception
     */
    public function getProjects(): array
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/core/projects/list?view=azure-devops-rest-7.1
        $query = '?api-version=7.1';
        $requestUrl = '/_apis/projects';
        $url = $this->baseUrl . $this->organization . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = [];
            foreach (json_decode($response->getBody()->getContents(), true)['value'] as $row) {
                $result[] = Project::fromArray($row);
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
     *
     * @return Project
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws AuthenticationException
     * @throws Exception
     */
    public function getProject(string $projectId): Project
    {
        // https://learn.microsoft.com/en-us/rest/api/azure/devops/core/projects/get?view=azure-devops-rest-7.1
        $query = '?api-version=7.1';
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
     *
     * @return array<int, array<string, mixed>>
     * @throws GuzzleException
     * @throws RuntimeException
     * @throws AuthenticationException
     * @throws Exception
     */
    public function getWorkItemTypes(): array
    {
        $query = '?api-version=7.1';
        $requestUrl = '/' . $this->project . '/_apis/wit/workitemtypes';
        $url = $this->baseUrl . $this->organization . $requestUrl . $query;
        $response = $this->guzzle->get($url, ['headers' => $this->getAuthHeader()]);
        if ($response->getStatusCode() === 200) {
            $result = json_decode($response->getBody()->getContents(), true)['value'];
            return $result;
        }
        if ($response->getStatusCode() === 203) {
            throw new AuthenticationException('API-Call could not be authenticated correctly.');
        }
        throw new Exception('Request to AzureDevOps failed: ' . $response->getStatusCode());
    }
}
